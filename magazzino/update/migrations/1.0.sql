/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8mb4 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE DATABASE IF NOT EXISTS `magazzino_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `magazzino_db`;

-- Tabelle di base (senza vincoli FK)
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`, `name`, `description`) VALUES
	(1, 'Resistenze THT', NULL),
	(2, 'Condensatori elettrolitici THT', NULL),
	(3, 'Connettori', NULL),
	(4, 'Ponte di diodi', NULL),
	(5, 'Diodi THT', NULL),
	(6, 'Transistor THT', NULL),
	(7, 'Mosfet THT', NULL),
	(8, 'IGBT THT', NULL),
	(9, 'SCR', NULL),
	(10, 'Triac', NULL),
	(11, 'Lampadine', NULL),
	(12, 'Condensatori alta tensione', NULL),
	(13, 'Condensatori ceramici THT', NULL),
	(14, 'Condensatori poliestere THT', NULL),
	(15, 'Resistenze di potenza', NULL),
	(16, 'Cavi', NULL),
	(17, 'Eprom', NULL),
	(18, 'EEprom', NULL),
	(19, 'Microcontrollori THT', NULL),
	(20, 'Dissipatori', NULL),
	(21, 'Varie', NULL),
	(22, 'Varistori', NULL),
	(23, 'Induttori THT', NULL),
	(24, 'Microswitch a leva', NULL),
	(25, 'Microswitch', NULL),
	(26, 'DipSwitch', NULL),
	(27, 'Commutatori rotativi', NULL),
	(28, 'Interruttori a slitta', NULL),
	(29, 'Interruttori a leva', NULL),
	(30, 'Sensore ottico', NULL),
	(31, 'Condensatori variabili THT', NULL),
	(32, 'Quarzi, risuonatori e oscillatori THT', NULL),
	(33, 'Zoccoli per C.I.', NULL),
	(34, 'Pulsanti', NULL),
	(35, 'Buzzer', NULL),
	(36, 'Pin strip', NULL),
	(37, 'Interruttori vari', NULL),
	(38, 'Circuito integrato THT', NULL),
	(39, 'Circuito integrato SMD', NULL),
	(40, 'PTC, NTC THT', NULL),
	(41, 'Regolatore di tensione THT', NULL),
	(42, 'Portafusibili', NULL),
	(43, 'Pinze, coccodrilli', NULL),
	(44, 'Relè', NULL),
	(45, 'Meccanica', NULL),
	(46, 'Viteria', NULL),
	(47, 'Morsetti', NULL),
	(48, 'Scaricatori', NULL),
	(49, 'TransZorb, TVS, Transil THT', NULL),
	(50, 'Led THT', NULL),
	(51, 'Potenziometri', NULL),
	(52, 'Optoisolatori THT', NULL),
	(53, 'Fusibili', NULL),
	(54, 'Fusibili Poliswitch THT', NULL),
	(55, 'DC/DC Converter', NULL),
	(56, 'AC/DC Converter', NULL),
	(57, 'Diodi SMD', NULL),
	(58, 'Led SMD', NULL),
	(59, 'Transistor SMD', NULL),
	(60, 'Condensatori THT', NULL),
	(61, 'Mosfet SMD', NULL),
	(62, 'TransZorb, TVS, Transil SMD', NULL),
	(63, 'Diodi zener THT', NULL),
	(64, 'Diodi zener SMD', NULL),
	(65, 'Microcontrollori SMD', NULL),
	(66, 'Optoisolatori SMD', NULL),
	(67, 'Quarzi, risuonatori e oscillatori SMD', NULL),
	(68, 'Regolatore di tensione SMD', NULL),
	(69, 'Resistenze SMD', NULL),
	(70, 'Condensatori SMD ceramici', NULL),
	(71, 'PTC, NTC SMD', NULL),
	(72, 'Induttori SMD', NULL),
	(73, 'Fusibili Poliswitch SMD', NULL),
	(74, 'Condensatori SMD elettrolitici', NULL);

CREATE TABLE IF NOT EXISTS `locali` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_locale_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Locali fisici (studio, garage, sede remota, ecc.)';

INSERT INTO `locali` (`id`, `name`, `description`, `created_at`) VALUES
	(1, 'Laboratorio', 'Magazzino principale', '2025-10-20 23:41:36');

-- locations: locale_id reso NULLABLE per supportare ON DELETE SET NULL
CREATE TABLE IF NOT EXISTS `locations` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `locale_id` int(11) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('scaffale','cassettiera','scatola','altro','valigetta') NOT NULL DEFAULT 'altro',
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_locale_id` (`locale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `compartments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `location_id` int(11) unsigned NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `compartments_ibfk_1` (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `components` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `codice_prodotto` varchar(100) NOT NULL,
  `category_id` int(11) unsigned DEFAULT NULL,
  `costruttore` varchar(100) DEFAULT NULL,
  `fornitore` varchar(100) DEFAULT NULL,
  `codice_fornitore` varchar(100) DEFAULT NULL,
  `quantity` int(11) unsigned DEFAULT 0 COMMENT 'Quantità disponibile',
  `quantity_min` int(11) unsigned DEFAULT 0 COMMENT 'Scorta minima per allerta',
  `location_id` int(11) unsigned DEFAULT NULL,
  `compartment_id` int(11) unsigned DEFAULT NULL,
  `datasheet_url` varchar(255) DEFAULT NULL,
  `equivalents` longtext DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `prezzo` decimal(10,2) DEFAULT NULL COMMENT 'Prezzo unitario componente',
  `link_fornitore` varchar(255) DEFAULT NULL COMMENT 'URL pagina prodotto fornitore',
  `unita_misura` varchar(20) DEFAULT 'pz' COMMENT 'Unità di misura: pz, m, kg, l, ecc.',
  `package` varchar(50) DEFAULT NULL COMMENT 'Package/contenitore: TO-220, SOT-23, DIP-8, ecc.',
  `tensione` varchar(50) DEFAULT NULL COMMENT 'Tensione operativa o massima (es: 12V, 400V)',
  `corrente` varchar(50) DEFAULT NULL COMMENT 'Corrente operativa o massima (es: 1A, 500mA)',
  `potenza` varchar(50) DEFAULT NULL COMMENT 'Potenza (es: 1W, 0.25W, 10W)',
  `hfe` varchar(50) DEFAULT NULL COMMENT 'HFE per transistor (es: 100-300)',
  `tags` longtext DEFAULT NULL COMMENT 'Tag separati da virgola per categorizzazione e ricerca',
  `datasheet_file` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_package` (`package`),
  KEY `idx_unita_misura` (`unita_misura`),
  KEY `components_ibfk_1` (`location_id`),
  KEY `components_ibfk_2` (`compartment_id`),
  KEY `components_ibfk_3` (`category_id`),
  FULLTEXT KEY `idx_tags` (`tags`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `db_version` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(10) NOT NULL COMMENT 'Numero versione (es: 1.4)',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Data applicazione',
  `description` text DEFAULT NULL COMMENT 'Descrizione aggiornamento',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `db_version` (`id`, `version`, `applied_at`, `description`) VALUES
	(1, '1.0', '2025-10-20 23:41:36', 'Versione iniziale');

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `attempt_time` datetime NOT NULL,
  `user_agent` text DEFAULT NULL,
  `successful` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`,`attempt_time`),
  KEY `idx_username_time` (`username`,`attempt_time`),
  KEY `idx_successful` (`successful`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_token` (`token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `setting` (
  `id_setting` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext NOT NULL,
  PRIMARY KEY (`id_setting`) USING BTREE,
  UNIQUE KEY `uk_setting_name` (`setting_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `created_at`) VALUES
	(1, 'RG4Tech', '$2y$10$tCR2SKbFye0w8QVPF70WSuw5qMVs4R8qwaAV0BCUJd9c8IsH9BWBu', 'admin', '2025-10-20 14:42:34');

-- Ora aggiungiamo esplicitamente i vincoli FK con ALTER TABLE
ALTER TABLE `locations` ADD CONSTRAINT `fk_locations_locali` FOREIGN KEY (`locale_id`) REFERENCES `locali` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `compartments` ADD CONSTRAINT `compartments_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE;
ALTER TABLE `components` ADD CONSTRAINT `components_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL;
ALTER TABLE `components` ADD CONSTRAINT `components_ibfk_2` FOREIGN KEY (`compartment_id`) REFERENCES `compartments` (`id`) ON DELETE SET NULL;
ALTER TABLE `components` ADD CONSTRAINT `components_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
SET FOREIGN_KEY_CHECKS=1;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
