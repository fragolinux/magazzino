-- Migrazione versione 1.12

-- Inserimento fornitore Mouser Electronics se non esiste
INSERT INTO fornitori (nome, link)
SELECT 'Mouser Electronics', 'https://www.mouser.it'
WHERE NOT EXISTS (
    SELECT 1 FROM fornitori 
    WHERE LOWER(nome) LIKE '%mouser%'
);


-- Aggiunta campo note alla tabella progetti
ALTER TABLE progetti 
ADD COLUMN IF NOT EXISTS note TEXT AFTER numero_ordine;

-- Creazione tabella per link web dei progetti
CREATE TABLE IF NOT EXISTS progetti_link_web (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    progetto_id INT UNSIGNED NOT NULL,
    url VARCHAR(500) NOT NULL,
    descrizione VARCHAR(255),
    FOREIGN KEY (progetto_id) REFERENCES progetti(id) ON DELETE CASCADE,
    INDEX idx_progetto (progetto_id)
) ENGINE=InnoDB;

-- Creazione tabella per link a cartelle locali dei progetti
CREATE TABLE IF NOT EXISTS progetti_link_locali (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    progetto_id INT UNSIGNED NOT NULL,
    path VARCHAR(500) NOT NULL,
    descrizione VARCHAR(255),
    FOREIGN KEY (progetto_id) REFERENCES progetti(id) ON DELETE CASCADE,
    INDEX idx_progetto (progetto_id)
) ENGINE=InnoDB;
