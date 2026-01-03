$ErrorActionPreference = "Stop"

$root = Resolve-Path (Join-Path $PSScriptRoot "..")
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$backupDir = Join-Path $root "backup\$timestamp"
$composeArgs = @("compose", "--project-directory", $root)

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "docker is required but not found."
}

docker @composeArgs ps -q db | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw "Docker Compose project is not running. Start it with: docker compose up -d"
}

$dbId = docker @composeArgs ps -q db
if ([string]::IsNullOrWhiteSpace($dbId)) {
    throw "Database service is not running."
}

docker @composeArgs exec -T db sh -c 'mysqladmin ping -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" --silent' | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw "Database is not ready yet."
}

New-Item -ItemType Directory -Force -Path $backupDir | Out-Null

$dumpFile = Join-Path $backupDir "db.sql"
docker @composeArgs exec -T db sh -c 'mysqldump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' | Set-Content -Encoding ASCII $dumpFile

Write-Host "Backup created at $backupDir"
