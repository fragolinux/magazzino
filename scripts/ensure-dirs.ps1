$ErrorActionPreference = "Stop"

$root = Resolve-Path (Join-Path $PSScriptRoot "..")

$dirs = @(
    (Join-Path $root "data/uploads/datasheet"),
    (Join-Path $root "data/uploads/images"),
    (Join-Path $root "data/db"),
    (Join-Path $root "data/nginx-logs"),
    (Join-Path $root "data/php-logs")
)

foreach ($dir in $dirs) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir | Out-Null
    }

    $gitkeep = Join-Path $dir ".gitkeep"
    if (-not (Test-Path $gitkeep)) {
        try {
            New-Item -ItemType File -Path $gitkeep -ErrorAction Stop | Out-Null
        } catch {
            # Directory might be owned by a container (e.g. DB volume); skip silently.
        }
    }
}
