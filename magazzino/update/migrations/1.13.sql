-- Migrazione versione 1.13
-- Aggiunta campo pdf_filename alla tabella locali per tracciare file PDF allegati

-- Aggiungi campo pdf_filename a locali
ALTER TABLE `locali` 
ADD COLUMN IF NOT EXISTS `pdf_filename` VARCHAR(255) NULL DEFAULT NULL 
AFTER `description`;

-- Aggiungi indice per ottimizzare ricerche per pdf_filename
ALTER TABLE `locali` 
ADD INDEX IF NOT EXISTS `idx_pdf_filename` (`pdf_filename`);

-- Aggiunta campo email alla tabella users
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `email` VARCHAR(255) NULL DEFAULT NULL 
AFTER `username`;
