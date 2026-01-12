-- Migrazione versione 1.3
-- Aggiunta ricerca equivalente, QR Code, gestione datasheet PDF, remember me

-- 1.3.1 - Aggiungi colonna datasheet_file a components
ALTER TABLE `components` 
ADD COLUMN IF NOT EXISTS `datasheet_file` VARCHAR(255) NULL;

-- 1.3.2 - Creazione tabella remember_tokens per la funzione "Ricordami"
CREATE TABLE IF NOT EXISTS `remember_tokens` (
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
COLLATE=utf8_general_ci;
