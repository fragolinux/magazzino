-- Migrazione versione 1.2
-- Aggiunto sistema di aggiornamento automatico

-- Creazione tabella setting
CREATE TABLE IF NOT EXISTS `setting` (
    `id_setting` SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_name` VARCHAR(255) NOT NULL COLLATE 'utf8_general_ci',
    `setting_value` MEDIUMTEXT NOT NULL COLLATE 'utf8_general_ci',
    PRIMARY KEY (`id_setting`) USING BTREE,
    UNIQUE KEY `uk_setting_name` (`setting_name`)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8
COLLATE=utf8_general_ci;
