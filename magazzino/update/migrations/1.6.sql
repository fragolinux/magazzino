-- Migrazione versione 1.6
-- Implementazione sistema di versioning database
-- Aggiornato file di configurazione connessione al DB
-- Supporto per porta personalizzata MySQL

-- Crea la tabella db_version per tracciare le versioni del database
CREATE TABLE IF NOT EXISTS `db_version` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `version` VARCHAR(10) NOT NULL COMMENT 'Numero versione (es: 1.4)',
  `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data applicazione',
  `description` TEXT NULL COMMENT 'Descrizione aggiornamento',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
