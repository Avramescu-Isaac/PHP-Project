$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$phpExe = Join-Path $projectRoot '.runtime\php\php.exe'
$phpArgs = @()
if (Test-Path -LiteralPath $phpExe) {
    $phpArgs = @('-c', (Join-Path $projectRoot '.runtime\php\php.ini'), '-d', ('extension_dir=' + (Join-Path $projectRoot '.runtime\php\ext')))
} else {
    $phpCommand = Get-Command php -CommandType Application -ErrorAction SilentlyContinue
    if (-not $phpCommand) { throw 'Install PHP 8.4+ with pdo_sqlite, add it to PATH, then restart VS Code. See README.md.' }
    $phpExe = $phpCommand.Source
}
$modules = & $phpExe @phpArgs -m
if ($LASTEXITCODE -ne 0 -or $modules -notcontains 'pdo_sqlite') { throw 'Enable pdo_sqlite in your PHP installation. See README.md.' }
New-Item -ItemType Directory -Force -Path (Join-Path $projectRoot 'storage') | Out-Null
Write-Host 'Revista: http://127.0.0.1:8000 (Ctrl+C to stop)'
& $phpExe @phpArgs -S 127.0.0.1:8000 -t (Join-Path $projectRoot 'public')
exit $LASTEXITCODE
