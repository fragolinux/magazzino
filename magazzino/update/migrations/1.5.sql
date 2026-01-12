-- Migrazione versione 1.5
-- Aggiornamento DB per associare tutte le posizioni al locale di default

-- Inserisci locale di default se non esiste
INSERT IGNORE INTO `locali` (`id`, `name`, `description`) 
VALUES (1, 'Laboratorio', 'Magazzino principale');

-- Aggiorna tutte le locations senza locale assegnandole al locale 1
UPDATE `locations` 
SET `locale_id` = 1 
WHERE `locale_id` IS NULL;
