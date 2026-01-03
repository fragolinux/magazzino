$ErrorActionPreference = "Stop"

param(
    [string]$BackupPath
)

$root = Resolve-Path (Join-Path $PSScriptRoot "..")
$backupRoot = Join-Path $root "backup"
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

if ([string]::IsNullOrWhiteSpace($BackupPath)) {
    $latest = Get-ChildItem -Path $backupRoot -Directory | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if ($null -eq $latest) {
        throw "No backups found in $backupRoot"
    }
    $BackupPath = $latest.FullName
}

if (-not (Test-Path $BackupPath)) {
    throw "Backup folder not found: $BackupPath"
}

$sqlFile = Join-Path $BackupPath "db.sql"
if (-not (Test-Path $sqlFile)) {
    throw "db.sql not found in $BackupPath"
}

Get-Content -Raw $sqlFile | docker @composeArgs exec -T db sh -c 'mysql -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"'

Write-Host "Restore completed from $BackupPath"
