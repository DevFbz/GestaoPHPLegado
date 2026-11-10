[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$PhpRuntimePath,
    [string]$OutputDirectory = 'dist\GestaoBrindesOffline',
    [switch]$SkipUiTests
)

$ErrorActionPreference = 'Stop'
$project = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$runtimeSource = [System.IO.Path]::GetFullPath($PhpRuntimePath)
if (-not (Test-Path -LiteralPath (Join-Path $runtimeSource 'php.exe') -PathType Leaf)) {
    throw "PHP runtime x64 NTS ausente: $runtimeSource"
}
if (-not (Test-Path -LiteralPath (Join-Path $project 'vendor\autoload.php') -PathType Leaf)) {
    throw 'Dependências Composer ausentes. Execute composer install --no-dev antes do empacotamento.'
}
$php = Join-Path $runtimeSource 'php.exe'
$runtimeExtensions = Join-Path $runtimeSource 'ext'
foreach ($extension in @('php_pdo_sqlite.dll', 'php_sqlite3.dll', 'php_mbstring.dll', 'php_gd.dll', 'php_fileinfo.dll', 'php_openssl.dll', 'php_zip.dll', 'php_curl.dll')) {
    if (-not (Test-Path -LiteralPath (Join-Path $runtimeExtensions $extension) -PathType Leaf)) {
        throw "Extensão PHP obrigatória ausente: $extension"
    }
}

$destination = [System.IO.Path]::GetFullPath((Join-Path $project $OutputDirectory))
if ($destination -eq $project -or -not $destination.StartsWith($project, [StringComparison]::OrdinalIgnoreCase)) {
    throw "O destino precisa estar dentro do projeto: $destination"
}
if (Test-Path -LiteralPath $destination) { Remove-Item -LiteralPath $destination -Recurse -Force }
New-Item -ItemType Directory -Force -Path $destination | Out-Null
$payload = Join-Path $destination 'app'
New-Item -ItemType Directory -Force -Path $payload | Out-Null
foreach ($item in @('app', 'config', 'database', 'public', 'resources', 'vendor')) {
    Copy-Item -LiteralPath (Join-Path $project $item) -Destination $payload -Recurse -Force
}
New-Item -ItemType Directory -Force -Path (Join-Path $payload 'bin') | Out-Null
Copy-Item -LiteralPath (Join-Path $project 'bin\install.php') -Destination (Join-Path $payload 'bin\install.php')
Copy-Item -LiteralPath (Join-Path $project 'bin\export-json.php') -Destination (Join-Path $payload 'bin\export-json.php')
$runtimeDestination = Join-Path $destination 'runtime\php'
New-Item -ItemType Directory -Force -Path $runtimeDestination | Out-Null
Copy-Item -Path (Join-Path $runtimeSource '*') -Destination $runtimeDestination -Recurse -Force
$ini = @'
extension_dir="ext"
extension=pdo_sqlite
extension=sqlite3
extension=pdo_mysql
extension=mbstring
extension=gd
extension=fileinfo
extension=openssl
extension=zip
extension=curl
upload_max_filesize=25M
post_max_size=26M
memory_limit=512M
max_execution_time=180
'@
[System.IO.File]::WriteAllText((Join-Path $runtimeDestination 'php.ini'), $ini, [System.Text.UTF8Encoding]::new($false))
$loaded = & (Join-Path $runtimeDestination 'php.exe') -c (Join-Path $runtimeDestination 'php.ini') -m 2>&1 | Out-String
if ($LASTEXITCODE -ne 0) { throw "PHP runtime não inicializou: $loaded" }
foreach ($required in @('pdo_sqlite', 'sqlite3', 'mbstring', 'gd', 'fileinfo', 'openssl', 'zip', 'curl')) {
    if ($loaded.IndexOf($required, [StringComparison]::OrdinalIgnoreCase) -lt 0) { throw "Extensão PHP não carregou: $required`n$loaded" }
}

$launcherProject = Join-Path $project 'offline-launcher\GestaoBrindesOffline.csproj'
& dotnet publish $launcherProject --configuration Release --runtime win-x64 --self-contained true -p:PublishSingleFile=true --output $destination
if ($LASTEXITCODE -ne 0) { throw "Build do executável offline falhou ($LASTEXITCODE)." }
if (-not (Test-Path -LiteralPath (Join-Path $destination 'GestaoBrindesOffline.exe') -PathType Leaf)) {
    throw 'dotnet publish não produziu GestaoBrindesOffline.exe.'
}
if (-not $SkipUiTests) {
    Push-Location $project
    try {
        & node --test tests\assets.test.mjs
        if ($LASTEXITCODE -ne 0) { throw "Testes dos ativos locais falharam ($LASTEXITCODE)." }
    } finally { Pop-Location }
}
$readme = @'
CONTROLE DE BRINDES — APLICATIVO LOCAL OFFLINE

Inicie GestaoBrindesOffline.exe. O programa abre o sistema em uma janela local do Microsoft Edge e encerra o servidor ao fechar a janela.

Banco SQLite e arquivos privados ficam em %LOCALAPPDATA%\GestaoBrindes\Offline, fora da pasta do executável. Os dados permanecem no tablet após fechar ou atualizar o aplicativo. Não desinstale/apague essa pasta sem fazer backup.

Primeiro acesso: admin@brindes.local / Trocar@123. O sistema exige a troca da senha no primeiro login.

Sem internet: cadastros, estoque, solicitações, aprovações, entregas, eventos, relatórios e autenticação rodam localmente. E-mail e integrações externas (incluindo Lecom) não funcionam desconectados. Este aplicativo não sincroniza automaticamente com outros tablets.

Para exportar JSON, execute GestaoBrindesOffline.exe --export-json; os arquivos serão gravados em %LOCALAPPDATA%\GestaoBrindes\Offline\exports. Para XLSX, use Relatórios > Excel dentro do sistema. Proteja as exportações: podem conter dados pessoais e histórico operacional.

Para importar uma exportação completa da versão web, abra Configurações no executável offline, selecione o arquivo Excel, confira a prévia e importe os registros novos. Registros locais existentes não são substituídos. Usuários, senhas e permissões não são importados; vínculos de registros com usuários que não existem neste tablet podem ser ignorados.

O servidor escuta somente em 127.0.0.1; outros dispositivos não acessam o banco por rede.
'@
[System.IO.File]::WriteAllText((Join-Path $destination 'LEIA-ME.txt'), $readme, [System.Text.UTF8Encoding]::new($false))

Push-Location $project
try {
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $project 'tests\offline_package.ps1') -ExecutablePath (Join-Path $destination 'GestaoBrindesOffline.exe')
    if ($LASTEXITCODE -ne 0) { throw "Teste de inicialização offline falhou ($LASTEXITCODE)." }
} finally { Pop-Location }

$archive = Join-Path (Split-Path $destination -Parent) ((Split-Path $destination -Leaf) + '-portable.zip')
if (Test-Path -LiteralPath $archive) { Remove-Item -LiteralPath $archive -Force }
Compress-Archive -Path (Join-Path $destination '*') -DestinationPath $archive -CompressionLevel Optimal
if (-not (Test-Path -LiteralPath $archive -PathType Leaf) -or (Get-Item $archive).Length -lt 1) {
    throw 'O pacote ZIP não foi gerado corretamente.'
}
Write-Output "Executável: $(Join-Path $destination 'GestaoBrindesOffline.exe')"
Write-Output "Pacote portátil: $archive"
