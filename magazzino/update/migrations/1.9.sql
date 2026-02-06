-- Migrazione versione 1.9 -

-- Modifica a tabella locations
ALTER TABLE locations DROP INDEX name;
ALTER TABLE locations ADD UNIQUE KEY `unique_name_per_locale` (name, locale_id);

-- Tabella configurazione sito personale
CREATE TABLE IF NOT EXISTS `personal_site_config` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `enabled` TINYINT(1) DEFAULT 0,
  `site_title` VARCHAR(255),
  `logo_path` VARCHAR(255),
  `favicon_path` VARCHAR(255),
  `theme_preset` VARCHAR(50) DEFAULT 'modern_minimal',
  `header_content` LONGTEXT,
  `footer_content` LONGTEXT,
  `background_image` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella sezioni della home personale
CREATE TABLE IF NOT EXISTS `personal_site_sections` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `section_order` INT DEFAULT 0,
  `menu_label` VARCHAR(100) NOT NULL,
  `section_title` VARCHAR(255),
  `section_content` LONGTEXT,
  `background_image` VARCHAR(255),
  `enabled` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;