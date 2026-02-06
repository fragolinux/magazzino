-- Migrazione versione 1.5
-- Aggiornamento DB per associare tutte le posizioni al locale di default

-- Inserisci locale di default se non esiste
INSERT INTO locali (id,name,description)
SELECT 1,'Laboratorio','Magazzino principale' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM locali WHERE id=1);

-- Aggiorna tutte le locations senza locale assegnandole al locale 1
UPDATE `locations` 
SET `locale_id` = 1 
WHERE `locale_id` IS NULL;
