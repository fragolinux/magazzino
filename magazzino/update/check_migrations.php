<?php
$host = getenv('DB_HOST') ?: 'db';
$name = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';

if ($name === '' || $user === '') {
    fwrite(STDERR, "DB_NAME or DB_USER not set; cannot check migrations.\n");
    exit(1);
}

$dsn = "mysql:host={$host};dbname={$name};charset=utf8";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

function dbVersionTableExists(PDO $pdo): bool
{
    try {
        $result = $pdo->query("SHOW TABLES LIKE 'db_version'");
        $exists = $result && $result->rowCount() > 0;
        $result->closeCursor();
        return $exists;
    } catch (PDOException $e) {
        return false;
    }
}

function getCurrentVersion(PDO $pdo): string
{
    if (!dbVersionTableExists($pdo)) {
        return '0.9';
    }

    try {
        $stmt = $pdo->query("SELECT version FROM db_version ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $result ? $result['version'] : '0.9';
    } catch (PDOException $e) {
        return '0.9';
    }
}

function getAvailableMigrations(string $dir): array
{
    $migrations = [];
    foreach (glob($dir . '/*.sql') as $file) {
        $filename = basename($file, '.sql');
        if (preg_match('/^(\d+\.\d+)/', $filename, $matches)) {
            $version = $matches[1];
            $migrations[$version] = $file;
        }
    }

    uksort($migrations, 'version_compare');
    return $migrations;
}

$migrationsDir = __DIR__ . '/migrations';
$currentVersion = getCurrentVersion($pdo);
$available = getAvailableMigrations($migrationsDir);
$pending = [];

foreach ($available as $version => $file) {
    if (version_compare($version, $currentVersion, '>')) {
        $pending[$version] = $file;
    }
}

$latestVersion = !empty($available) ? array_key_last($available) : 'n/a';

echo "DB version: " . $currentVersion . "\n";
echo "Latest migration: " . $latestVersion . "\n";

if (empty($pending)) {
    echo "Pending migrations: none\n";
    exit(0);
}

echo "Pending migrations:\n";
foreach ($pending as $version => $file) {
    echo "- " . $version . " (" . basename($file) . ")\n";
}
