<?php

$host = getenv('DB_HOST') ?: 'db';
$name = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';

$migrationUser = getenv('DB_MIGRATION_USER') ?: $user;
$migrationPass = getenv('DB_MIGRATION_PASS');
if ($migrationPass === false || $migrationPass === '') {
    $migrationPass = $pass;
}

if ($name === '' || $migrationUser === '') {
    fwrite(STDERR, "DB_NAME or DB_MIGRATION_USER/DB_USER not set; skipping migration.\n");
    exit(0);
}

$dsn = "mysql:host={$host};dbname={$name};charset=utf8";

try {
    $pdo = new PDO($dsn, $migrationUser, $migrationPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$migrationManagerPath = '/var/www/html/update/migration_manager.php';
$migrationsDir = '/var/www/html/update/migrations';

function isAdminDbUser(PDO $pdo): bool
{
    try {
        $grants = $pdo->query("SHOW GRANTS")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($grants as $grant) {
            if (stripos($grant, 'ALL PRIVILEGES') !== false || stripos($grant, 'SUPER') !== false) {
                return true;
            }
        }
        $dbUser = $pdo->query("SELECT USER()")->fetchColumn();
        return $dbUser !== false && stripos((string)$dbUser, 'root') !== false;
    } catch (PDOException $e) {
        return false;
    }
}

function normalizeSqlForMariaDb(string $sql): string
{
    return preg_replace('/\bCREATE\s+INDEX\s+IF\s+NOT\s+EXISTS\b/i', 'CREATE INDEX', $sql);
}

function filterSqlForNonAdmin(string $sql): string
{
    $statements = preg_split('/;\s*\n|;\s*$/', $sql);
    $kept = [];

    foreach ($statements as $statement) {
        $s = trim($statement);
        $s = preg_replace('/--.*$/m', '', $s);
        $s = preg_replace('/\/\*.*?\*\//s', '', $s);
        $s = preg_replace('/^#.*$/m', '', $s);
        $s = trim($s);

        if ($s === '' || $s === ';') {
            continue;
        }

        if (preg_match('/^\s*(CREATE\s+USER|REVOKE\s+ALL\s+PRIVILEGES|GRANT\s+|FLUSH\s+PRIVILEGES)\b/i', $s)) {
            continue;
        }

        $kept[] = rtrim($s, ';');
    }

    if (empty($kept)) {
        return '';
    }

    return implode(";\n\n", $kept) . ";\n";
}

function getLatestMigrationVersion(string $dir): ?string
{
    $versions = [];

    foreach (glob($dir . '/*.sql') as $file) {
        $name = basename($file);
        if (preg_match('/^(\d+(?:\.\d+)+)\.sql$/', $name, $matches)) {
            $versions[] = $matches[1];
        }
    }

    if (empty($versions)) {
        return null;
    }

    usort($versions, 'version_compare');
    return end($versions) ?: null;
}

function buildEffectiveMigrationsDir(string $sourceDir, bool $isAdmin): array
{
    $tmpDir = sys_get_temp_dir() . '/magazzino_migrations_' . uniqid('', true);
    if (!@mkdir($tmpDir, 0777, true)) {
        return [$sourceDir, null];
    }

    foreach (glob($sourceDir . '/*.sql') as $file) {
        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }

        $content = normalizeSqlForMariaDb($content);
        if (!$isAdmin) {
            $content = filterSqlForNonAdmin($content);
        }

        file_put_contents($tmpDir . '/' . basename($file), $content);
    }

    return [$tmpDir, $tmpDir];
}

function cleanupTempDir(?string $tmpDir): void
{
    if ($tmpDir === null) {
        return;
    }

    foreach (glob($tmpDir . '/*.sql') as $file) {
        @unlink($file);
    }
    @rmdir($tmpDir);
}

if (!file_exists($migrationManagerPath) || !is_dir($migrationsDir)) {
    fwrite(STDERR, "Migration manager or migrations dir not found.\n");
    exit(1);
}

require_once $migrationManagerPath;
if (!class_exists('MigrationManager')) {
    fwrite(STDERR, "MigrationManager class not found.\n");
    exit(1);
}

$isAdmin = isAdminDbUser($pdo);
[$effectiveMigrationsDir, $tempDir] = buildEffectiveMigrationsDir($migrationsDir, $isAdmin);

try {
    $manager = new MigrationManager($pdo, $effectiveMigrationsDir);
    $result = $manager->runPendingMigrations();
    $applied = $result['applied'] ?? [];
    $latestVersion = getLatestMigrationVersion($effectiveMigrationsDir);

    if (!empty($result['message'])) {
        echo "DB migrations: " . $result['message'] . "\n";
    }

    $hasErrors = false;
    foreach ($applied as $migration) {
        $stats = $migration['stats'] ?? [];
        if (!empty($stats['errors'])) {
            $hasErrors = true;
            break;
        }
    }

    $currentVersion = $manager->getCurrentVersion();
    if ($latestVersion !== null && version_compare($currentVersion, $latestVersion, '<')) {
        fwrite(STDERR, "DB migrations incomplete: current {$currentVersion}, latest {$latestVersion}.\n");
        exit(1);
    }

    if ($hasErrors) {
        fwrite(
            STDERR,
            "DB migration completed with non-fatal statement errors; schema is at version {$currentVersion}.\n"
        );
    }
} finally {
    cleanupTempDir($tempDir);
}

exit(0);
