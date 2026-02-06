$ErrorActionPreference = "Stop"

$root = Resolve-Path (Join-Path $PSScriptRoot "..")

$dirs = @(
    (Join-Path $root "data/uploads/datasheet"),
    (Join-Path $root "data/uploads/images"),
    (Join-Path $root "data/db"),
    (Join-Path $root "data/nginx-logs"),
    (Join-Path $root "data/php-logs"),
    (Join-Path $root "data/config")
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

$configDir = Join-Path $root "data/config"
if (Test-Path $configDir) {
    $basePathSrc = Join-Path $root "magazzino/config/base_path.php"
    $basePathDst = Join-Path $configDir "base_path.php"
    if ((Test-Path $basePathSrc) -and -not (Test-Path $basePathDst)) {
        Copy-Item $basePathSrc $basePathDst
    }

    $settingsSrc = Join-Path $root "magazzino/config/settings.php.example"
    $settingsDst = Join-Path $configDir "settings.php"
    if ((Test-Path $settingsSrc) -and -not (Test-Path $settingsDst)) {
        Copy-Item $settingsSrc $settingsDst
    }

    $dbSrc = Join-Path $root "overrides/database.php"
    $dbDst = Join-Path $configDir "database.php"
    if ((Test-Path $dbSrc) -and -not (Test-Path $dbDst)) {
        Copy-Item $dbSrc $dbDst
    }
}
