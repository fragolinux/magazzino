-- Migrazione versione 1.14

-- Riproposta query per MySQL (MariaDB la ignorerà grazie alla gestione idempotente)
-- Questa query è necessaria per MySQL poiché non supporta IF NOT EXISTS per gli indici
-- Il migration manager controllerà se l'indice esiste già prima di eseguire
ALTER TABLE `locali` 
ADD INDEX `idx_pdf_filename` (`pdf_filename`);

-- Creazione tabella movimenti_magazzino
CREATE TABLE IF NOT EXISTS movimenti_magazzino (
	id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	component_id INT(11) UNSIGNED NOT NULL,
	data_ora DATETIME NOT NULL,
	quantity INT(11) UNSIGNED NOT NULL,
	movimento VARCHAR(20) NOT NULL,
	commento VARCHAR(50) NULL DEFAULT NULL,
	user_id MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (id) USING BTREE
) ENGINE=InnoDB;

-- Popolamento iniziale: crea un record per ogni componente presente
INSERT INTO movimenti_magazzino (component_id, data_ora, quantity, movimento, commento, user_id)
SELECT id, NOW(), quantity, 'Creazione', 'Record iniziale', 0
FROM components;