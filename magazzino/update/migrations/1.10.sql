-- Migrazione versione 1.10 -

-- 1. Creazione tabella fornitori (prima perché referenziata da progetti_componenti)
CREATE TABLE IF NOT EXISTS fornitori (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    link VARCHAR(255),
    apikey VARCHAR(255),
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Creazione tabella progetti (referenziata da progetti_componenti)
CREATE TABLE IF NOT EXISTS progetti (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    descrizione TEXT,
    numero_ordine VARCHAR(100),
    stato ENUM('bozza', 'confermato', 'completato') DEFAULT 'bozza',
    costo_totale DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Creazione tabella progetti_componenti (dopo perché ha le foreign key)
CREATE TABLE IF NOT EXISTS progetti_componenti (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    ks_progetto INT UNSIGNED NOT NULL,
    ks_componente INT UNSIGNED,
    ks_fornitore INT UNSIGNED,
    codice_componente VARCHAR(100), -- per componenti non in magazzino
    quantita INT UNSIGNED NOT NULL DEFAULT 1,
    quantita_scaricata INT NULL DEFAULT NULL,
    link_fornitore VARCHAR(255),
    note TEXT,
    prezzo DECIMAL(10,2),
    FOREIGN KEY (ks_progetto) REFERENCES progetti(id) ON DELETE CASCADE,
    FOREIGN KEY (ks_componente) REFERENCES components(id) ON DELETE SET NULL,
    FOREIGN KEY (ks_fornitore) REFERENCES fornitori(id) ON DELETE SET NULL,
    UNIQUE KEY unique_componente_progetto (ks_progetto, ks_componente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aggiunta indici per ottimizzazione
CREATE INDEX idx_progetto ON progetti_componenti(ks_progetto);
CREATE INDEX idx_componente ON progetti_componenti(ks_componente);
CREATE INDEX idx_fornitore ON progetti_componenti(ks_fornitore);
