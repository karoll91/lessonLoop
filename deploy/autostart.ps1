<#
.SYNOPSIS
    Starts LessonLoop automatically when this user signs in to Windows.
    powershell -ExecutionPolicy Bypass -File deploy\autostart.ps1            (turn on)
    powershell -ExecutionPolicy Bypass -File deploy\autostart.ps1 -Remove    (turn off)
    OpenServer must also start with Windows (its settings: "Autostart").
#>
param([switch]$Remove)

$name = 'LessonLoop'
Unregister-ScheduledTask -TaskName $name -Confirm:$false -ErrorAction SilentlyContinue

if ($Remove) {
    Write-Host "Avtomatik ishga tushirish o'chirildi"
    return
}

$start = Join-Path $PSScriptRoot 'start.ps1'
$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Minimized -File `"$start`""
# Give OpenServer (MySQL) a minute to start first.
$trigger = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME
$trigger.Delay = 'PT1M'
Register-ScheduledTask -TaskName $name -Action $action -Trigger $trigger -Description 'LessonLoop classroom server' | Out-Null
Write-Host "Yoqildi: Windows'ga kirilganda 1 daqiqadan keyin LessonLoop ishga tushadi"
