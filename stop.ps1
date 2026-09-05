$ErrorActionPreference = 'Stop'
$pidFile = Join-Path $PSScriptRoot '.local/processes.json'
if (-not (Test-Path $pidFile)) { Write-Host 'No processes recorded by start.ps1.'; exit }
$records = Get-Content -LiteralPath $pidFile -Raw | ConvertFrom-Json
foreach ($record in $records) {
    $process = Get-CimInstance Win32_Process -Filter "ProcessId = $($record.Id)" -ErrorAction SilentlyContinue
    if ($process -and $process.CommandLine -and $process.CommandLine.Contains($PSScriptRoot)) {
        $children = Get-CimInstance Win32_Process -Filter "ParentProcessId = $($record.Id)" -ErrorAction SilentlyContinue
        foreach ($child in $children) {
            if ($child.CommandLine -and $child.CommandLine.Contains($PSScriptRoot)) {
                Stop-Process -Id $child.ProcessId -ErrorAction SilentlyContinue
            }
        }
        Stop-Process -Id $record.Id
        Write-Host "Stopped $($record.Name)."
    }
}
