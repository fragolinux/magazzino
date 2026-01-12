<?php
$host = getenv('DB_HOST') ?: 'db';
$name = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';

if ($name === '' || $user === '') {
    fwrite(STDERR, "DB_NAME or DB_USER not set; skipping migration.\n");
    exit(0);
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

$migrationManagerPath = '/var/www/html/update/migration_manager.php';
$migrationsDir = '/var/www/html/update/migrations';

if (file_exists($migrationManagerPath) && is_dir($migrationsDir)) {
    require_once $migrationManagerPath;

    if (class_exists('MigrationManager')) {
        $manager = new MigrationManager($pdo, $migrationsDir);
        $result = $manager->runPendingMigrations();
        $applied = $result['applied'] ?? [];
        $hasErrors = false;

        foreach ($applied as $migration) {
            $stats = $migration['stats'] ?? [];
            if (!empty($stats['errors'])) {
                $hasErrors = true;
            }
        }

        if (!empty($result['message'])) {
            echo "DB migrations: " . $result['message'] . "\n";
        }

        if ($hasErrors) {
            fwrite(STDERR, "DB migration errors detected.\n");
            exit(1);
        }

        exit(0);
    }
}

$didWork = false;

function tableExists(PDO $pdo, string $dbName, string $table): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ? LIMIT 1"
    );
    $stmt->execute([$dbName, $table]);
    return (bool)$stmt->fetchColumn();
}

function columnExists(PDO $pdo, string $dbName, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1"
    );
    $stmt->execute([$dbName, $table, $column]);
    return (bool)$stmt->fetchColumn();
}

function constraintExists(PDO $pdo, string $dbName, string $table, string $constraint): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM information_schema.table_constraints WHERE table_schema = ? AND table_name = ? AND constraint_name = ? LIMIT 1"
    );
    $stmt->execute([$dbName, $table, $constraint]);
    return (bool)$stmt->fetchColumn();
}

$check = $pdo->prepare(
    "SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = 'setting' LIMIT 1"
);
$check->execute([$name]);

if (!$check->fetchColumn()) {
    $sql = "CREATE TABLE IF NOT EXISTS `setting` (
    `id_setting` SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_name` VARCHAR(255) NOT NULL COLLATE 'utf8_general_ci',
    `setting_value` MEDIUMTEXT NOT NULL COLLATE 'utf8_general_ci',
    PRIMARY KEY (`id_setting`) USING BTREE,
    UNIQUE KEY `uk_setting_name` (`setting_name`)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8
COLLATE=utf8_general_ci;";
    $pdo->exec($sql);
    echo "DB migrated: setting table created.\n";
    $didWork = true;
}

$check = $pdo->prepare(
    "SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = 'remember_tokens' LIMIT 1"
);
$check->execute([$name]);

if (!$check->fetchColumn()) {
    $sql = "CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `expires` DATETIME NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`) USING BTREE,
    UNIQUE KEY `uk_token` (`token`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_expires` (`expires`)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8
COLLATE=utf8_general_ci;";
    $pdo->exec($sql);
    echo "DB migrated: remember_tokens table created.\n";
    $didWork = true;
}

$check = $pdo->prepare(
    "SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = 'components' AND column_name = 'datasheet_file' LIMIT 1"
);
$check->execute([$name]);

if (!$check->fetchColumn()) {
    $pdo->exec("ALTER TABLE components ADD COLUMN datasheet_file VARCHAR(255) NULL");
    echo "DB migrated: components.datasheet_file added.\n";
    $didWork = true;
}

if (!tableExists($pdo, $name, 'locali')) {
    $sql = "CREATE TABLE `locali` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL COLLATE 'utf8_general_ci',
    `description` TEXT NULL DEFAULT NULL COLLATE 'utf8_general_ci',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`) USING BTREE,
    UNIQUE KEY `uk_locale_name` (`name`)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8
COLLATE=utf8_general_ci
COMMENT='Locali fisici (studio, garage, sede remota, ecc.)';";
    $pdo->exec($sql);
    echo "DB migrated: locali table created.\n";
    $didWork = true;
}

if (!columnExists($pdo, $name, 'components', 'quantity_min')) {
    $pdo->exec("ALTER TABLE `components` ADD COLUMN `quantity_min` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `quantity`");
    echo "DB migrated: components.quantity_min added.\n";
    $didWork = true;
}

if (!columnExists($pdo, $name, 'locations', 'locale_id')) {
    $pdo->exec("ALTER TABLE `locations` ADD COLUMN `locale_id` INT(11) UNSIGNED NULL DEFAULT '1' AFTER `id`, ADD KEY `idx_locale_id` (`locale_id`)");
    echo "DB migrated: locations.locale_id added.\n";
    $didWork = true;
}

if (tableExists($pdo, $name, 'locali') && columnExists($pdo, $name, 'locations', 'locale_id')) {
    if (!constraintExists($pdo, $name, 'locations', 'fk_locations_locali')) {
        $pdo->exec("UPDATE `locations` SET `locale_id` = NULL WHERE `locale_id` IS NOT NULL AND `locale_id` NOT IN (SELECT `id` FROM `locali`)");
        $pdo->exec("ALTER TABLE `locations` ADD CONSTRAINT `fk_locations_locali` FOREIGN KEY (`locale_id`) REFERENCES `locali` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        echo "DB migrated: fk_locations_locali added.\n";
        $didWork = true;
    }
}

if (tableExists($pdo, $name, 'locali')) {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM `locali`")->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare("INSERT INTO `locali` (`name`, `description`) VALUES (?, ?)");
        $stmt->execute(['Laboratorio', 'Magazzino principale']);
        $stmt->execute(['Garage', 'Magazzino secondario']);
        echo "DB migrated: locali sample rows inserted.\n";
        $didWork = true;
    }
}

if (tableExists($pdo, $name, 'locali') && columnExists($pdo, $name, 'locations', 'locale_id')) {
    $hasLocaleOne = (int)$pdo->query("SELECT COUNT(*) FROM `locali` WHERE `id` = 1")->fetchColumn() > 0;
    if ($hasLocaleOne) {
        $updated = $pdo->exec("UPDATE `locations` SET `locale_id` = 1 WHERE `locale_id` IS NULL");
        if ($updated > 0) {
            echo "DB migrated: locations.locale_id set to 1 for NULL rows.\n";
            $didWork = true;
        }
    } else {
        echo "DB ok: locali id=1 missing; skip locations.locale_id backfill.\n";
    }
}

if (!$didWork) {
    echo "DB ok: no migrations needed.\n";
}
