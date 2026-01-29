-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Erstellungszeit: 27. Jan 2026 um 11:24
-- Server-Version: 10.4.28-MariaDB
-- PHP-Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `helpdesk_stats`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `option_definitions`
--

CREATE TABLE `option_definitions` (
  `id` int(11) NOT NULL,
  `section` enum('kontaktart','person','thema','zeitfenster','tageszeit','dauer','referenz') NOT NULL,
  `label` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `keywords` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of keywords for Thema options' CHECK (json_valid(`keywords`)),
  `param_group` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `option_definitions`
--

INSERT INTO `option_definitions` (`id`, `section`, `label`, `sort_order`, `is_active`, `created_at`, `keywords`, `param_group`) VALUES
(1, 'kontaktart', 'Besuch', 0, 1, '2026-01-18 18:32:02', NULL, 'contact'),
(2, 'kontaktart', 'Telefon', 1, 1, '2026-01-18 18:32:02', NULL, 'contact'),
(3, 'kontaktart', 'Mail', 2, 1, '2026-01-18 18:32:02', NULL, 'contact'),
(4, 'kontaktart', 'Passant/in', 3, 1, '2026-01-18 18:32:02', NULL, 'contact'),
(5, 'person', 'Mann', 0, 1, '2026-01-18 18:32:02', NULL, 'gender'),
(6, 'person', 'Frau', 1, 1, '2026-01-18 18:32:02', NULL, 'gender'),
(7, 'person', 'unter 55', 2, 1, '2026-01-18 18:32:02', NULL, 'age'),
(8, 'person', 'über 55', 3, 1, '2026-01-18 18:32:02', NULL, 'age'),
(9, 'person', 'über 80', 4, 1, '2026-01-18 18:32:02', NULL, 'age'),
(10, 'person', 'selbst betroffen', 6, 1, '2026-01-18 18:32:02', NULL, 'affected'),
(11, 'person', 'Angehörige Nachbarn und andere', 7, 1, '2026-01-18 18:32:02', NULL, 'affected'),
(12, 'person', 'Institution', 8, 1, '2026-01-18 18:32:02', NULL, 'affected'),
(13, 'tageszeit', 'Vormittag', 0, 1, '2026-01-18 18:32:02', NULL, 'day_time'),
(14, 'tageszeit', 'Nachmittag', 1, 1, '2026-01-18 18:32:02', NULL, 'day_time'),
(15, 'dauer', 'länger als 20 Minuten', 0, 1, '2026-01-18 18:32:02', NULL, 'duration'),
(16, 'thema', 'Bildung', 0, 1, '2026-01-18 18:32:02', '[\"Information, Beratung\",\"Fr\\u00fchf\\u00f6rderung\",\"Schulen\",\"Schulunterst\\u00fctzende Angebote\",\"Br\\u00fccken - und Integrationsangebote\",\"Lehrstellen, Berufsschulen\",\"Erwachsenenbildung, Elternbildung\",\"Sprachschule\"]', 'topic'),
(17, 'thema', 'Arbeit', 1, 1, '2026-01-18 18:32:02', '[\"Information, Beratung\",\"Berufsberatung, Neuorientierung\",\"Arbeitslosigkeit\",\"Soloeins\\u00e4tze, Au Pair, Ferienjobs\",\"Freiwilligenarbeit\",\"Stellensuche\",\"Firmengr\\u00fcndung\",\"Integrationsprogramme, gesch\\u00fctzte Arbeitspl\\u00e4tze\"]', 'topic'),
(18, 'person', 'Migrationshintergrund', 5, 1, '2026-01-18 18:32:02', NULL, 'background'),
(19, 'thema', 'Finanzen', 2, 1, '2026-01-18 18:32:02', '[\"Information, Budget- und Schuldenberatung\",\"Unterst\\u00fctzung, soziale Beitr\\u00e4ge\",\"Fonds, Stiftungen\",\"Steuern, Pensionskasse\",\"Stipenden\",\"G\\u00fcnstig einkaufen, essen\"]', 'topic'),
(20, 'thema', 'Frauen Männer jung und alt', 3, 1, '2026-01-18 18:32:02', '[\"Information, Beratung\",\"Gleichstellung\",\"Familie\",\"Familienerg\\u00e4nzende Kinderbetreuung\",\"Kinder\",\"Jugendliche\",\"\\u00c4ltere Menschen\"]', 'topic'),
(21, 'thema', 'Gesundheit', 4, 1, '2026-01-18 18:32:02', '[\"Information Gesundheit\",\"Information Behinderung\",\"Information Sucht\",\"Arzt, Therapie\",\"Klinische Angebote\",\"Pflege, Unterst\\u00fctzung zu Hause\",\"Soziale Angebote\",\"Hilfsmittel und Fahrdienste\",\"Sterben und Tod\"]', 'topic'),
(22, 'thema', 'Wohnen', 5, 1, '2026-01-18 18:32:02', NULL, 'topic'),
(23, 'thema', 'Austausch und Freizeit', 6, 1, '2026-01-18 18:32:02', '[\"Quartierangebote\",\"Treffpunkte\",\"Religionsgemeinschaften\",\"Selbsthilfe\",\"Freiwilligenarbeit\",\"Freizeitaktivit\\u00e4ten\",\"Tiere\"]', 'topic'),
(24, 'thema', 'Migration und Integration', 7, 1, '2026-01-18 18:32:02', '[\"Information, Beratung\",\"Zuzug, Aufenthalt, Bewilligung, Auswandern\",\"Sprache, Bildung\",\"Vereine und Organisationen der Migrationsbev\\u00f6lkerung\",\"Fl\\u00fcchtlinge\"]', 'topic'),
(25, 'thema', 'Notlagen', 8, 1, '2026-01-18 18:32:02', '[\"Notdienste\",\"Gewalt und Krisen\",\"Finanzielle Nothilfe\",\"Notwohnungen, Notunterk\\u00fcnfte\"]', 'topic'),
(26, 'thema', 'Allgemeine Hilfeleistungen', 9, 1, '2026-01-18 18:32:02', '[\"Schreibdienste, \\u00dcbersetzungen\",\"Allgemeine Rechtsberatung\",\"Ombudsstellen, Mediation\",\"Computer und Administration\",\"Gegenseitige Unterst\\u00fctzung\",\"Begleitung und andere Angebote\",\"Informationsstellen und Beh\\u00f6rden\"]', 'topic'),
(27, 'thema', 'Recht', 10, 1, '2026-01-18 18:32:02', '[]', 'topic'),
(28, 'referenz', 'Flyer/Plakat/Presse', 0, 1, '2026-01-18 18:32:02', NULL, 'referral'),
(29, 'referenz', 'Internet', 1, 1, '2026-01-18 18:32:02', NULL, 'referral'),
(30, 'referenz', 'empfohlen Freunde Bekannte', 2, 1, '2026-01-18 18:32:02', NULL, 'referral'),
(31, 'referenz', 'empfohlen Institution', 3, 1, '2026-01-18 18:32:02', NULL, 'referral'),
(32, 'referenz', 'empfohlen Fachperson', 4, 1, '2026-01-18 18:32:02', NULL, 'referral'),
(33, 'referenz', 'War schon mal hier', 5, 1, '2026-01-18 18:32:02', NULL, 'referral'),
(34, 'referenz', 'Andere', 6, 1, '2026-01-18 18:32:02', NULL, 'referral'),
(35, 'zeitfenster', '11:30 - 12:00', 1, 1, '2026-01-18 18:32:02', NULL, 'time_slot'),
(36, 'zeitfenster', '12:00 - 13:00', 2, 1, '2026-01-18 18:32:02', NULL, 'time_slot'),
(37, 'zeitfenster', '13:00 - 14:00', 3, 1, '2026-01-18 18:32:02', NULL, 'time_slot'),
(38, 'zeitfenster', '14:00 - 15:00', 4, 1, '2026-01-18 18:32:02', NULL, 'time_slot'),
(39, 'zeitfenster', '15:00 - 16:00', 5, 1, '2026-01-18 18:32:02', NULL, 'time_slot'),
(40, 'zeitfenster', '16:00 - 17:00', 6, 1, '2026-01-18 18:32:02', NULL, 'time_slot'),
(41, 'zeitfenster', '17:00 - 18:00', 7, 1, '2026-01-18 18:32:02', NULL, 'time_slot'),
(45, 'zeitfenster', '11:00 - 11:30', 0, 1, '2026-01-20 12:20:41', NULL, 'time_slot');

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `option_definitions`
--
ALTER TABLE `option_definitions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_section_label` (`section`,`label`),
  ADD KEY `idx_section_active` (`section`,`is_active`),
  ADD KEY `idx_section_sort` (`section`,`sort_order`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `option_definitions`
--
ALTER TABLE `option_definitions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=145;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
