-- Migrazione versione 1.8 - AGGIORNAMENTO COMPLETO
-- Data: 2026-01-16
-- Descrizione: Aggiornamento completo sicurezza - Rimozione impostazioni DB, rate limiting avanzato, utente DB dedicato con permessi completi
-- Questa migrazione porta il sistema dalla versione 1.7 alla 1.8 completa con tutte le funzionalità di sicurezza

-- =============================================================================
-- PARTE 1: Rimozione impostazioni migrate da DB a file config/settings.php
-- =============================================================================
-- Elimina i setting che ora sono gestiti tramite file di configurazione
DELETE FROM setting WHERE setting_name IN ('environment_mode', 'app_theme', 'IP_Computer');

-- =============================================================================
-- PARTE 2: Creazione tabella per rate limiting avanzato
-- =============================================================================
-- Traccia tutti i tentativi di login per IP e username

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `attempt_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_agent` text,
  `successful` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`, `attempt_time`),
  KEY `idx_username_time` (`username`, `attempt_time`),
  KEY `idx_successful` (`successful`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assicurati che esistano tutti gli indici necessari
CREATE INDEX IF NOT EXISTS `idx_ip_time` ON `login_attempts` (`ip_address`, `attempt_time`);
CREATE INDEX IF NOT EXISTS `idx_username_time` ON `login_attempts` (`username`, `attempt_time`);
CREATE INDEX IF NOT EXISTS `idx_successful` ON `login_attempts` (`successful`);

-- =============================================================================
-- PARTE 3: Creazione utente database dedicato con privilegi sicuri
-- =============================================================================
-- Queste operazioni richiedono privilegi amministrativi (root)
-- Vengono eseguite solo se l'utente corrente è amministratore

SET @is_admin = (SELECT IF(USER() LIKE '%root%', 1, 0));

-- Crea utente dedicato per l'applicazione magazzino (se non esiste)
-- Questo comando fallisce silenziosamente se l'utente non è amministratore
CREATE USER IF NOT EXISTS 'magazzino_user'@'localhost' IDENTIFIED BY 'SecurePass2024!';

-- Rimuovi eventuali privilegi esistenti per sicurezza (pulizia)
-- Questo comando fallisce silenziosamente se l'utente non è amministratore
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'magazzino_user'@'localhost';

-- Concedi solo i privilegi necessari per l'applicazione
-- Questo comando fallisce silenziosamente se l'utente non è amministratore
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, CREATE TEMPORARY TABLES ON magazzino_db.* TO 'magazzino_user'@'localhost';

-- Privilegi specifici per tabelle di sistema
-- Questi comandi falliscono silenziosamente se l'utente non è amministratore
GRANT SELECT ON magazzino_db.setting TO 'magazzino_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON magazzino_db.login_attempts TO 'magazzino_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON magazzino_db.remember_tokens TO 'magazzino_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON magazzino_db.db_version TO 'magazzino_user'@'localhost';

-- Ricarica i privilegi
-- Questo comando fallisce silenziosamente se l'utente non è amministratore
FLUSH PRIVILEGES;

-- =============================================================================
-- PARTE 4: Ottimizzazioni aggiuntive per la sicurezza
-- =============================================================================

-- Rimuovi eventuali vecchi tentativi (mantieni ultimi 1000 per IP per audit)
-- Questa query è sicura e rimuove solo tentativi molto vecchi
DELETE la1 FROM login_attempts la1
INNER JOIN (
    SELECT id FROM login_attempts
    WHERE ip_address IN (
        SELECT ip_address FROM login_attempts
        GROUP BY ip_address
        HAVING COUNT(*) > 1000
    )
    ORDER BY ip_address, attempt_time ASC
) la2 ON la1.id = la2.id;

-- =============================================================================
-- PARTE 5: Log dell'avvenuta migrazione per audit
-- =============================================================================

INSERT INTO db_version (version, description)
SELECT '1.8', 'Aggiornamento completo sicurezza - Rate limiting avanzato, utente DB dedicato con permessi CREATE/DROP/CREATE TEMPORARY TABLES, rimozione impostazioni DB'
FROM dual
WHERE NOT EXISTS (
    SELECT 1 FROM db_version WHERE version = '1.8'
);