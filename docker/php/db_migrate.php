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

$check = $pdo->prepare(
    "SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = 'setting' LIMIT 1"
);
$check->execute([$name]);

if ($check->fetchColumn()) {
    echo "DB ok: setting table already exists.\n";
    exit(0);
}

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
