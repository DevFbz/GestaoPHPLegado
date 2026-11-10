param([string]$ExecutablePath)
$ErrorActionPreference = 'Stop'
$root = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if ([string]::IsNullOrWhiteSpace($ExecutablePath)) {
    $ExecutablePath = Join-Path $root 'dist\GestaoBrindesOffline\GestaoBrindesOffline.exe'
}
$executable = [System.IO.Path]::GetFullPath($ExecutablePath)
if (-not (Test-Path -LiteralPath $executable -PathType Leaf)) {
    throw "Offline executable not found: $executable"
}
$packageApp = Join-Path (Split-Path $executable -Parent) 'app'
$credentialGuide = Join-Path (Split-Path $executable -Parent) 'docs\Contas-e-Credenciais.md'

$importService = Join-Path $packageApp 'app\Services\DataImportService.php'
$settingsView = Join-Path $packageApp 'resources\views\pages\settings\index.php'
if (-not (Test-Path -LiteralPath $importService -PathType Leaf) -or -not (Test-Path -LiteralPath $settingsView -PathType Leaf)) {
    throw 'Offline package is missing the full Excel import feature.'
}
if (Test-Path -LiteralPath $credentialGuide) {
    throw 'Offline package must not include the local credential guide.'
}
if ((Get-Content -Raw $settingsView) -notmatch 'Importar exportação completa') {
    throw 'Offline package does not expose the full Excel import button.'
}
$testData = Join-Path $env:LOCALAPPDATA ('GestaoBrindes\offline-test-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Force -Path $testData | Out-Null
try {
    $env:BRINDES_OFFLINE_DATA_DIR = $testData
    $output = & $executable --smoke 2>&1 | Out-String
    if ($LASTEXITCODE -ne 0) { throw "Offline executable smoke test failed ($LASTEXITCODE): $output" }
    $marker = Join-Path $testData 'smoke-ok.txt'
    if (-not (Test-Path -LiteralPath $marker) -or (Get-Content -Raw $marker) -ne 'OFFLINE_SMOKE_OK') {
        throw "Offline executable did not confirm a healthy local SQLite app: $output"
    }
    $runtimeStorage = Join-Path $testData 'storage'
    $runtimeSessions = Join-Path $runtimeStorage 'cache\sessions'
    $packageSessions = Join-Path $packageApp 'storage\cache\sessions'
    $runtimeSessionCount = if (Test-Path -LiteralPath $runtimeSessions -PathType Container) {
        (Get-ChildItem -LiteralPath $runtimeSessions -File | Measure-Object).Count
    } else { 0 }
    if ($runtimeSessionCount -lt 1) {
        throw 'Offline runtime must write sessions under the per-user data directory.'
    }
    $packageSessionCount = if (Test-Path -LiteralPath $packageSessions -PathType Container) {
        (Get-ChildItem -LiteralPath $packageSessions -File | Measure-Object).Count
    } else { 0 }
    if ($packageSessionCount -gt 0) {
        throw 'Offline runtime must not write sessions into the application package.'
    }
    $database = Join-Path $testData 'brindes.sqlite'
    if (-not (Test-Path -LiteralPath $database -PathType Leaf) -or (Get-Item $database).Length -lt 1) {
        throw 'Smoke test did not create a persistent local SQLite database.'
    }
    $exportOutput = & $executable --smoke --export-json 2>&1 | Out-String
    if ($LASTEXITCODE -ne 0) { throw "JSON export from the offline executable failed ($LASTEXITCODE): $exportOutput" }
    $exportMarker = Join-Path $testData 'smoke-export-ok.txt'
    if (-not (Test-Path -LiteralPath $exportMarker) -or (Get-Content -Raw $exportMarker) -ne 'OFFLINE_JSON_EXPORT_OK') {
        throw "Offline executable did not confirm JSON export completion: $exportOutput"
    }
    $exportDirectory = Join-Path $testData 'exports'
    $metadata = Join-Path $exportDirectory 'meta.json'
    $users = Join-Path $exportDirectory 'users.json'
    if (-not (Test-Path -LiteralPath $metadata -PathType Leaf) -or -not (Test-Path -LiteralPath $users -PathType Leaf)) {
        throw 'Offline JSON export did not create metadata and users files.'
    }
    if ((Get-Content -Raw $users) -match 'password_hash|session_version|token_hash|reset_token|csrf_token') {
        throw 'Offline JSON export contains sensitive authentication fields.'
    }
    Write-Output 'Offline executable smoke test passed: local PHP, HTTP health, SQLite persistence, redacted JSON export.'
} finally {
    Remove-Item Env:BRINDES_OFFLINE_DATA_DIR -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $testData -Recurse -Force -ErrorAction SilentlyContinue
}
