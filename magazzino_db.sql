-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Creato il: Ott 26, 2025 alle 08:30
-- Versione del server: 10.4.32-MariaDB
-- Versione PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `magazzino_db`
--

-- --------------------------------------------------------

--
-- Struttura della tabella `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dump dei dati per la tabella `categories`
--

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
(11, 'Lampadine a filamento', NULL),
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

-- --------------------------------------------------------

--
-- Struttura della tabella `compartments`
--

CREATE TABLE `compartments` (
  `id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `components`
--

CREATE TABLE `components` (
  `id` int(11) NOT NULL,
  `codice_prodotto` varchar(100) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `costruttore` varchar(100) DEFAULT NULL,
  `fornitore` varchar(100) DEFAULT NULL,
  `codice_fornitore` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `location_id` int(11) DEFAULT NULL,
  `compartment_id` int(11) DEFAULT NULL,
  `datasheet_url` varchar(255) DEFAULT NULL,
  `equivalents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`equivalents`)),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('scaffale','cassettiera','scatola','altro','valigetta') NOT NULL DEFAULT 'altro',
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `users`
--

CREATE TABLE `users` (
  `id` mediumint(8) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dump dei dati per la tabella `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `created_at`) VALUES
(1, 'RG4Tech', 'ef797c8118f02dfb649607dd5d3f8c7623048c9c063d532cc95c5ed7a898a64f', 'admin', '2025-10-20 14:42:34');

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indici per le tabelle `compartments`
--
ALTER TABLE `compartments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indici per le tabelle `components`
--
ALTER TABLE `components`
  ADD PRIMARY KEY (`id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `compartment_id` (`compartment_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indici per le tabelle `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indici per le tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT per la tabella `compartments`
--
ALTER TABLE `compartments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `components`
--
ALTER TABLE `components`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `users`
--
ALTER TABLE `users`
  MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Limiti per le tabelle scaricate
--

--
-- Limiti per la tabella `compartments`
--
ALTER TABLE `compartments`
  ADD CONSTRAINT `compartments_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `components`
--
ALTER TABLE `components`
  ADD CONSTRAINT `components_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `components_ibfk_2` FOREIGN KEY (`compartment_id`) REFERENCES `compartments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `components_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
