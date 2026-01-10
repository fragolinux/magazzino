$ErrorActionPreference = "Stop"

$root = Resolve-Path (Join-Path $PSScriptRoot "..")
$envFile = Join-Path $root ".env"
$envExample = Join-Path $root ".env.example"

& (Join-Path $PSScriptRoot "ensure-dirs.ps1")

if (-not (Test-Path $envFile)) {
    Copy-Item $envExample $envFile
}

docker compose --project-directory $root pull
docker compose --project-directory $root up -d
