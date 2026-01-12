-- Migrazione versione 1.7
-- Aggiunta nuovi campi alla tabella components
-- Ottimizzazione campi INT con UNSIGNED per ID e quantità

-- =====================================================
-- PARTE 1: Aggiungi nuovi campi alla tabella components
-- =====================================================

-- Prezzo del componente
ALTER TABLE `components` 
ADD COLUMN IF NOT EXISTS `prezzo` DECIMAL(10,2) DEFAULT NULL AFTER `notes`;

-- Link al fornitore per il componente
ALTER TABLE `components` 
ADD COLUMN IF NOT EXISTS `link_fornitore` VARCHAR(255) DEFAULT NULL AFTER `prezzo`;

-- Unità di misura (quantità, metri, kg, ecc.)
ALTER TABLE `components` 
ADD COLUMN IF NOT EXISTS `unita_misura` VARCHAR(20) DEFAULT 'pz' AFTER `link_fornitore`;

-- Contenitore/case del componente
ALTER TABLE `components` 
ADD COLUMN IF NOT EXISTS `package` VARCHAR(50) DEFAULT NULL AFTER `unita_misura`;

-- Caratteristiche elettriche
ALTER TABLE `components` 
ADD COLUMN IF NOT EXISTS `tensione` VARCHAR(50) DEFAULT NULL AFTER `package`;

ALTER TABLE `components` 
ADD COLUMN IF NOT EXISTS `corrente` VARCHAR(50) DEFAULT NULL AFTER `tensione`;

ALTER TABLE `components` 
ADD COLUMN IF NOT EXISTS `potenza` VARCHAR(50) DEFAULT NULL AFTER `corrente`;

ALTER TABLE `components` 
ADD COLUMN IF NOT EXISTS `hfe` VARCHAR(50) DEFAULT NULL AFTER `potenza`;

-- Tag separati da virgola per ricerche e categorizzazione
ALTER TABLE `components` 
ADD COLUMN IF NOT EXISTS `tags` LONGTEXT DEFAULT NULL AFTER `hfe`;

-- =====================================================
-- PARTE 2: DROP delle foreign key PRIMA delle modifiche
-- =====================================================

-- Drop delle foreign key esistenti (necessario per modificare i tipi di colonna)
ALTER TABLE `compartments` DROP FOREIGN KEY IF EXISTS `compartments_ibfk_1`;
ALTER TABLE `components` DROP FOREIGN KEY IF EXISTS `components_ibfk_1`;
ALTER TABLE `components` DROP FOREIGN KEY IF EXISTS `components_ibfk_2`;
ALTER TABLE `components` DROP FOREIGN KEY IF EXISTS `components_ibfk_3`;

-- =====================================================
-- PARTE 3: Ottimizzazione campi esistenti con UNSIGNED
-- =====================================================

-- Prima modifica le tabelle referenziate (categories, locations)
ALTER TABLE `categories` 
MODIFY `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `locations` 
MODIFY `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT;

-- Ottimizza db_version
ALTER TABLE `db_version` 
MODIFY `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT;

-- Ottimizza remember_tokens (creata nella migrazione 1.3)
ALTER TABLE `remember_tokens` 
MODIFY `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `remember_tokens` 
MODIFY `user_id` INT(11) UNSIGNED NOT NULL;

-- Poi modifica compartments
ALTER TABLE `compartments` 
MODIFY `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `compartments` 
MODIFY `location_id` INT(11) UNSIGNED NOT NULL;

-- Infine modifica components (ora le foreign key sono rimosse)
ALTER TABLE `components` 
MODIFY `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `components` 
MODIFY `category_id` INT(11) UNSIGNED DEFAULT NULL;

ALTER TABLE `components` 
MODIFY `quantity` INT(11) UNSIGNED DEFAULT 0;

ALTER TABLE `components` 
MODIFY `quantity_min` INT(11) UNSIGNED DEFAULT 0;

ALTER TABLE `components` 
MODIFY `location_id` INT(11) UNSIGNED DEFAULT NULL;

ALTER TABLE `components` 
MODIFY `compartment_id` INT(11) UNSIGNED DEFAULT NULL; 

-- =====================================================
-- PARTE 4: Ricrea le foreign key con i nuovi tipi UNSIGNED
-- =====================================================

-- Ricrea le foreign key con i tipi corretti UNSIGNED
ALTER TABLE `compartments`
ADD CONSTRAINT `compartments_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE;

ALTER TABLE `components`
ADD CONSTRAINT `components_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL;

ALTER TABLE `components`
ADD CONSTRAINT `components_ibfk_2` FOREIGN KEY (`compartment_id`) REFERENCES `compartments` (`id`) ON DELETE SET NULL;

ALTER TABLE `components`
ADD CONSTRAINT `components_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

-- =====================================================
-- PARTE 5: Ottimizzazioni indici
-- =====================================================

-- Gli indici verranno aggiunti solo se non esistono già (gestione errori duplicati nel PHP)
ALTER TABLE `components` ADD FULLTEXT INDEX `idx_tags` (`tags`);
ALTER TABLE `components` ADD INDEX `idx_package` (`package`);
ALTER TABLE `components` ADD INDEX `idx_unita_misura` (`unita_misura`);
