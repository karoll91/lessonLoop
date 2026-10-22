<#
.SYNOPSIS
    Builds the admin panel and the board for the classroom server.

.DESCRIPTION
    Run after every update (git pull). The pages are built with
    "--mode classroom" (board/.env.classroom, admin/.env.classroom): they talk
    to the server they were opened from, so the same build works on any IP.
    The Reverb key and port are taken from backend\.env.
#>
$ErrorActionPreference = 'Stop'
$Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path

$backendEnv = @{}
foreach ($line in Get-Content (Join-Path $Root 'backend\.env')) {
    if ($line -match '^\s*([A-Z0-9_]+)\s*=\s*"?([^"]*)"?\s*$') { $backendEnv[$Matches[1]] = $Matches[2] }
}
if (-not $backendEnv['REVERB_APP_KEY']) { throw 'backend\.env da REVERB_APP_KEY yo''q' }

$env:VITE_REVERB_APP_KEY = $backendEnv['REVERB_APP_KEY']
# No VITE_REVERB_PORT: the built board reaches Reverb through the web
# server's own port (/app/ is passed on to Reverb), see board/src/config.ts.
$env:VITE_REVERB_SCHEME = 'http'

# npm prints warnings on stderr; success is judged by the exit code.
$ErrorActionPreference = 'Continue'

foreach ($app in @('board', 'admin')) {
    $dir = Join-Path $Root $app
    if (-not (Test-Path (Join-Path $dir 'node_modules'))) {
        Write-Host "== ${app}: npm ci"
        npm --prefix $dir ci
        if ($LASTEXITCODE -ne 0) { throw "${app}: npm ci xato" }
    }
    Write-Host "== ${app}: build"
    npm --prefix $dir run build -- --mode classroom
    if ($LASTEXITCODE -ne 0) { throw "${app}: build xato" }
}

Write-Host "Tayyor: backend\public\admin va backend\public\board. Endi serverni ishga tushiring (QOLLANMA.md)."
