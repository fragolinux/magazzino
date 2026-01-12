-- Migrazione versione 1.4
-- Gestione quantità minima, locali, tema chiaro/scuro, gerarchia magazzino, immagini componenti

-- 1.4.1 - Creazione tabella locali
CREATE TABLE IF NOT EXISTS `locali` (
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
COMMENT='Locali fisici (studio, garage, sede remota, ecc.)';

-- 1.4.2 - Aggiungi colonna quantity_min a components
ALTER TABLE `components` 
ADD COLUMN IF NOT EXISTS `quantity_min` INT(11) UNSIGNED NULL DEFAULT NULL 
AFTER `quantity`;

-- 1.4.3 - Aggiungi colonna locale_id a locations
ALTER TABLE `locations` 
ADD COLUMN IF NOT EXISTS `locale_id` INT(11) UNSIGNED NULL DEFAULT '1' AFTER `id`;

-- Aggiungi indice se non esiste (gestito nel PHP perché ADD INDEX IF NOT EXISTS non esiste in MySQL)
-- SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
--                WHERE table_schema = DATABASE() AND table_name = 'locations' AND index_name = 'idx_locale_id');
-- SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE locations ADD KEY idx_locale_id (locale_id)', 'SELECT ''Index already exists''');
-- PREPARE stmt FROM @sqlstmt;
-- EXECUTE stmt;
