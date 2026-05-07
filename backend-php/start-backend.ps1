param(
    [int]$Port = 5001
)

$phpCommand = Get-Command php -ErrorAction Stop
$phpExe = $phpCommand.Source
$phpDir = Split-Path $phpExe -Parent
$extensionDir = Join-Path $phpDir 'ext'

$arguments = @()

if (Test-Path (Join-Path $extensionDir 'php_pdo_mysql.dll')) {
    $arguments += '-d'
    $arguments += "extension_dir=$extensionDir"
    $arguments += '-d'
    $arguments += 'extension=php_pdo_mysql.dll'
}

$arguments += '-S'
$arguments += "localhost:$Port"
$arguments += '-t'
$arguments += 'public'
$arguments += 'router.php'

Write-Host "Starting PHP app server on http://localhost:$Port"
& $phpExe @arguments
