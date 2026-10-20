<#
.SYNOPSIS
    Lets phones and the board PC in the local network reach LessonLoop.
    Run once as Administrator:  right-click PowerShell -> Run as administrator,
    then:  powershell -ExecutionPolicy Bypass -File deploy\firewall.ps1
#>
. (Join-Path $PSScriptRoot 'config.ps1')

$ports = @($LL.HttpPort, 8080)
Get-NetFirewallRule -DisplayName 'LessonLoop' -ErrorAction SilentlyContinue | Remove-NetFirewallRule
New-NetFirewallRule -DisplayName 'LessonLoop' -Direction Inbound -Protocol TCP -LocalPort $ports -Action Allow -Profile Private, Domain | Out-Null
Write-Host "Ruxsat berildi: TCP $($ports -join ', ') (Private va Domain tarmoqlar)"
Write-Host "Wi-Fi tarmog'i Windows'da 'Private' (Shaxsiy) deb belgilangan bo'lishi kerak."
