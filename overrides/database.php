<?php
require_once __DIR__ . '/base_path.php';

return [
    "host" => getenv("DB_HOST") ?: "db",
    "db" => getenv("DB_NAME") ?: "magazzino_db",
    "user" => getenv("DB_USER") ?: "magazzino_app",
    "pass" => getenv("DB_PASS") ?: "",
    "charset" => "utf8mb4",
    "port" => getenv("DB_PORT") ?: 3306,
];
