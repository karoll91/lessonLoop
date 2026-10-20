<#
.SYNOPSIS
    Starts the LessonLoop classroom server and keeps it running.

.DESCRIPTION
    nginx (port HttpPort) serves the API, the admin panel (/admin/) and the
    board (/board/); PHP 8.3 FastCGI workers run Laravel; Reverb (port 8080)
    pushes board updates; the Laravel scheduler prunes old board sessions and
    backs up the database once a day. A process that stops is restarted.
    Close with Ctrl+C or run stop.bat.

    MySQL comes from OpenServer: start OpenServer first.

.PARAMETER HttpPort
    Overrides HttpPort from config.ps1 (for a trial run next to a dev server).
.PARAMETER SkipReverb
    Do not start Reverb (when another Reverb already listens on 8080).
.PARAMETER NoSupervise
    Start everything and return instead of watching the processes.
#>
param(
    [int]$HttpPort = 0,
    [switch]$SkipReverb,
    [switch]$NoSupervise
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'config.ps1')
if ($HttpPort -gt 0) { $LL.HttpPort = $HttpPort }

$Root    = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$Backend = Join-Path $Root 'backend'
$Runtime = Join-Path $PSScriptRoot 'runtime'
$Php     = Join-Path $LL.PhpDir 'php.exe'
$PhpCgi  = Join-Path $LL.PhpDir 'php-cgi.exe'
$Nginx   = Join-Path $LL.NginxDir 'nginx.exe'
$Artisan = Join-Path $Backend 'artisan'
$Prefix  = ($Runtime -replace '\\', '/') + '/'

New-Item -ItemType Directory -Force (Join-Path $Runtime 'logs'), (Join-Path $Runtime 'conf'), (Join-Path $Runtime 'temp') | Out-Null

function Write-Log([string]$Text) {
    $line = '{0:yyyy-MM-dd HH:mm:ss} {1}' -f (Get-Date), $Text
    Write-Host $line
    Add-Content -Path (Join-Path $Runtime 'logs\supervisor.log') -Value $line -Encoding UTF8
}

function Test-Port([int]$Port) {
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $connect = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        return $connect.AsyncWaitHandle.WaitOne(500) -and $client.Connected
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function Invoke-Artisan([string[]]$Arguments) {
    # Judge by exit code; warnings on stderr must not stop the start.
    $ErrorActionPreference = 'Continue'
    & $Php $Artisan @Arguments
    if ($LASTEXITCODE -ne 0) { throw "php artisan $($Arguments -join ' ') xato bilan tugadi" }
}

function Quote([string]$Value) {
    if ($Value -match '\s') { return '"' + $Value + '"' }
    return $Value
}

# ---------- checks ----------

if (Test-Path (Join-Path $Runtime 'pids.json')) {
    & (Join-Path $PSScriptRoot 'stop.ps1') -Quiet
}

foreach ($tool in @($Php, $PhpCgi, $Nginx)) {
    if (-not (Test-Path $tool)) { throw "Topilmadi: $tool -- deploy\config.ps1 ni tekshiring." }
}
foreach ($app in @('admin', 'board')) {
    if (-not (Test-Path (Join-Path $Root "$app\dist\index.html"))) { throw "$app\dist yo'q -- avval deploy\build.bat ni ishga tushiring." }
}
if (-not (Test-Port 3306)) {
    throw "MySQL (127.0.0.1:3306) ishlamayapti -- avval OpenServer'ni yoqing."
}
if (Test-Port $LL.HttpPort) {
    throw "Port $($LL.HttpPort) band (masalan, 'php artisan serve' ishlab turibdi). Uni to'xtating yoki config.ps1 da HttpPort ni o'zgartiring."
}

# ---------- Laravel ----------

Push-Location $Backend
try {
    Invoke-Artisan @('migrate', '--force')
    # Not config:cache: with a cached config `php artisan test` would ignore
    # phpunit.xml and run against this MySQL database. A leftover cache from
    # "php artisan optimize" is removed for the same reason.
    Invoke-Artisan @('config:clear')
    Invoke-Artisan @('route:cache')
    Invoke-Artisan @('event:cache')
    Invoke-Artisan @('view:cache')
} finally {
    Pop-Location
}

# ---------- nginx config ----------

$upstreams = (0..($LL.PhpWorkers - 1) | ForEach-Object { "        server 127.0.0.1:$($LL.PhpPortBase + $_);" }) -join "`n"
$conf = (Get-Content (Join-Path $PSScriptRoot 'nginx\nginx.conf.template') -Raw).
    Replace('{{ROOT}}', ($Root -replace '\\', '/')).
    Replace('{{DEPLOY}}', ($PSScriptRoot -replace '\\', '/')).
    Replace('{{HTTP_PORT}}', [string]$LL.HttpPort).
    Replace('{{PHP_UPSTREAMS}}', $upstreams)
[IO.File]::WriteAllText((Join-Path $Runtime 'conf\nginx.conf'), $conf)

# Through cmd: nginx -t reports even success on stderr, which Windows
# PowerShell 5.1 would turn into an error here.
$check = cmd /c "`"$Nginx`" -p `"$Prefix`" -c conf/nginx.conf -t 2>&1"
if ($LASTEXITCODE -ne 0) {
    $check | ForEach-Object { Write-Host $_ }
    throw 'nginx konfiguratsiyasida xato'
}

# ---------- processes ----------

# php-cgi on Windows quits after 500 requests unless this is 0.
$env:PHP_FCGI_MAX_REQUESTS = '0'

$services = @()
for ($i = 0; $i -lt $LL.PhpWorkers; $i++) {
    $services += @{
        Name = "php-$($i + 1)"
        File = $PhpCgi
        Args = @('-b', "127.0.0.1:$($LL.PhpPortBase + $i)", '-d', 'zend_extension=opcache', '-d', 'opcache.enable=1', '-d', 'opcache.memory_consumption=128', '-d', 'opcache.validate_timestamps=0')
        Dir  = $Backend
    }
}
if (-not $SkipReverb) {
    $services += @{ Name = 'reverb'; File = $Php; Args = @($Artisan, 'reverb:start', '--host=0.0.0.0'); Dir = $Backend }
}
$services += @{ Name = 'scheduler'; File = $Php; Args = @($Artisan, 'schedule:work'); Dir = $Backend }

function Start-Service2($Service) {
    $logs = Join-Path $Runtime 'logs'
    $process = Start-Process -FilePath $Service.File -ArgumentList (($Service.Args | ForEach-Object { Quote $_ }) -join ' ') `
        -WorkingDirectory $Service.Dir -WindowStyle Hidden -PassThru `
        -RedirectStandardOutput (Join-Path $logs "$($Service.Name).log") -RedirectStandardError (Join-Path $logs "$($Service.Name).err.log")
    $Service.Process = $process
}

function Save-Pids {
    $services | ForEach-Object { @{ name = $_.Name; pid = $_.Process.Id } } | ConvertTo-Json | Set-Content (Join-Path $Runtime 'pids.json') -Encoding UTF8
}

# nginx is watched through its port, not its process: on Windows the
# process Start-Process returns can exit while the real master keeps
# running, and a second nginx may bind the same port without an error.
function Start-Nginx {
    & (Join-Path $PSScriptRoot 'stop.ps1') -Quiet -NginxOnly
    Start-Process -FilePath $Nginx -ArgumentList "-p `"$Prefix`" -c conf/nginx.conf" -WorkingDirectory $LL.NginxDir -WindowStyle Hidden
    for ($i = 0; $i -lt 20 -and -not (Test-Port $LL.HttpPort); $i++) { Start-Sleep -Milliseconds 250 }
}

foreach ($service in $services) { Start-Service2 $service }
Save-Pids
Start-Nginx
if (-not (Test-Port $LL.HttpPort)) { throw "nginx ishga tushmadi -- deploy\runtime\logs\error.log ni ko'ring" }
# The adapter with a default gateway is the classroom network (not VirtualBox/Hyper-V).
$ip = (Get-NetIPConfiguration -ErrorAction SilentlyContinue |
    Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' } |
    Select-Object -First 1).IPv4Address.IPAddress
if (-not $ip) { $ip = 'localhost' }

Write-Log "LessonLoop ishga tushdi:"
Write-Log "  Admin panel:  http://${ip}:$($LL.HttpPort)/admin/"
Write-Log "  Doska:        http://${ip}:$($LL.HttpPort)/board/"
Write-Log "  Telefon (server manzili): http://${ip}:$($LL.HttpPort)/api"

if ($NoSupervise) { return }

# stop.ps1 ends this window first, so it does not restart what it stops.
Set-Content (Join-Path $Runtime 'supervisor.pid') $PID
Write-Log "Kuzatilmoqda. To'xtatish: Ctrl+C yoki deploy\stop.bat"
try {
    while ($true) {
        Start-Sleep -Seconds 5
        foreach ($service in $services) {
            if ($service.Process.HasExited) {
                Write-Log "$($service.Name) to'xtadi, qayta ishga tushirilmoqda"
                Start-Service2 $service
                Save-Pids
            }
        }
        if (-not (Test-Port $LL.HttpPort)) {
            Write-Log "nginx javob bermayapti, qayta ishga tushirilmoqda"
            Start-Nginx
        }
    }
} finally {
    & (Join-Path $PSScriptRoot 'stop.ps1') -Quiet
    Write-Log "LessonLoop to'xtatildi"
}
