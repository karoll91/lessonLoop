<#
.SYNOPSIS
    Stops the LessonLoop classroom server started by start.ps1.
#>
param([switch]$Quiet, [switch]$NginxOnly)

# Stopping is best effort: something may already be gone. (start.ps1 runs
# with 'Stop', and PowerShell 5.1 turns a native command's stderr into an
# error under it.)
$ErrorActionPreference = 'Continue'

. (Join-Path $PSScriptRoot 'config.ps1')
$Runtime = Join-Path $PSScriptRoot 'runtime'
$PidFile = Join-Path $Runtime 'pids.json'
$Nginx   = Join-Path $LL.NginxDir 'nginx.exe'
$Prefix  = ($Runtime -replace '\\', '/') + '/'

# The supervisor (start.ps1) would restart what is stopped below, so it
# goes first -- unless it is the one calling (same PowerShell process).
$SupervisorFile = Join-Path $Runtime 'supervisor.pid'
if (-not $NginxOnly -and (Test-Path $SupervisorFile)) {
    $supervisor = [int](Get-Content $SupervisorFile -Raw)
    if ($supervisor -ne $PID) {
        Stop-Process -Id $supervisor -Force -ErrorAction SilentlyContinue
    }
    Remove-Item $SupervisorFile -Force -ErrorAction SilentlyContinue
}

# Every nginx started with this runtime folder (there may be more than one).
if (Test-Path (Join-Path $Runtime 'logs\nginx.pid')) {
    cmd /c "`"$Nginx`" -p `"$Prefix`" -c conf/nginx.conf -s stop >nul 2>&1"
    Start-Sleep -Milliseconds 500
}
Get-CimInstance Win32_Process -Filter "Name = 'nginx.exe'" |
    Where-Object { $_.CommandLine -and $_.CommandLine.Contains($Prefix) } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
Remove-Item (Join-Path $Runtime 'logs\nginx.pid') -Force -ErrorAction SilentlyContinue
if ($NginxOnly) { return }

if (Test-Path $PidFile) {
    # Assign first: in Windows PowerShell 5.1 ConvertFrom-Json emits a JSON
    # array as one item, so @(... | ConvertFrom-Json) would hold one entry.
    $entries = Get-Content $PidFile -Raw | ConvertFrom-Json
    foreach ($entry in $entries) {
        try {
            Stop-Process -Id $entry.pid -Force -ErrorAction Stop
            if (-not $Quiet) { Write-Host "To'xtatildi: $($entry.name)" }
        } catch {
            # Already gone.
        }
    }
    Remove-Item $PidFile -Force
}

if (-not $Quiet) { Write-Host "LessonLoop to'xtatildi" }

# This checkout is also used for development: drop the route/event/view
# caches start.ps1 made, so new routes work with "php artisan serve".
$Php = Join-Path $LL.PhpDir 'php.exe'
if (-not $NginxOnly -and (Test-Path $Php)) {
    & $Php (Join-Path $PSScriptRoot '..\backend\artisan') optimize:clear *> $null
}
