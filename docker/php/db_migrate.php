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

$didWork = false;

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
if (!$didWork) {
    echo "DB ok: no migrations needed.\n";
}
