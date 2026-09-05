$ErrorActionPreference = 'Stop'
$projectRoot = $PSScriptRoot
$localDir = Join-Path $projectRoot '.local'
New-Item -ItemType Directory -Force $localDir | Out-Null
if (-not (Test-Path (Join-Path $projectRoot 'clinic-backend/vendor/autoload.php'))) { throw 'Run composer install in clinic-backend first.' }
if (-not (Test-Path (Join-Path $projectRoot 'clinic-frontend/node_modules/vite/bin/vite.js'))) { throw 'Run npm install in clinic-frontend first.' }
$services = @(
    @{ Name='backend'; Port=8000; Exe=(Get-Command php).Source; Args=@(('"' + (Join-Path $projectRoot 'clinic-backend/artisan') + '"'),'serve','--host=127.0.0.1','--port=8000'); Dir=(Join-Path $projectRoot 'clinic-backend') },
    @{ Name='frontend'; Port=5173; Exe=(Get-Command node).Source; Args=@(('"' + (Join-Path $projectRoot 'clinic-frontend/node_modules/vite/bin/vite.js') + '"')); Dir=(Join-Path $projectRoot 'clinic-frontend') }
)
$started = @()
foreach ($service in $services) {
    $portCheck = [System.Net.Sockets.TcpClient]::new()
    $inUse = $false
    try { $portCheck.Connect('127.0.0.1', $service.Port); $inUse = $true } catch { } finally { $portCheck.Dispose() }
    if ($inUse) { Write-Host "$($service.Name): port $($service.Port) is already in use; leaving its process unchanged."; continue }
    $process = Start-Process -FilePath $service.Exe -ArgumentList $service.Args -WorkingDirectory $service.Dir -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $localDir ($service.Name + '.log')) -RedirectStandardError (Join-Path $localDir ($service.Name + '-error.log'))
    $started += @{ Name=$service.Name; Id=$process.Id }
}
if ($started.Count) { $started | ConvertTo-Json | Set-Content (Join-Path $localDir 'processes.json') }
Write-Host 'Website: http://127.0.0.1:5173'
Write-Host 'Staff:   http://127.0.0.1:5173/login'
Write-Host 'Local login details: .local/access.txt'
