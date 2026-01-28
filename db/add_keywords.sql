-- ============================================================================
-- Add Keywords to Thema Options
-- Run this script on production database to populate keywords
-- ============================================================================

UPDATE option_definitions SET keywords = '[\"Information, Beratung\",\"Frühförderung\",\"Schulen\",\"Schulunterstützende Angebote\",\"Brücken - und Integrationsangebote\",\"Lehrstellen, Berufsschulen\",\"Erwachsenenbildung, Elternbildung\",\"Sprachschule\"]'
WHERE section = 'thema' AND label = 'Bildung';

UPDATE option_definitions SET keywords = '[\"Information, Beratung\",\"Berufsberatung, Neuorientierung\",\"Arbeitslosigkeit\",\"Soloeinsätze, Au Pair, Ferienjobs\",\"Freiwilligenarbeit\",\"Stellensuche\",\"Firmengründung\",\"Integrationsprogramme, geschützte Arbeitsplätze\"]'
WHERE section = 'thema' AND label = 'Arbeit';

UPDATE option_definitions SET keywords = '[\"Information, Budget- und Schuldenberatung\",\"Unterstützung, soziale Beiträge\",\"Fonds, Stiftungen\",\"Steuern, Pensionskasse\",\"Stipenden\",\"Günstig einkaufen, essen\"]'
WHERE section = 'thema' AND label = 'Finanzen';

UPDATE option_definitions SET keywords = '[\"Information, Beratung\",\"Gleichstellung\",\"Familie\",\"Familienergänzende Kinderbetreuung\",\"Kinder\",\"Jugendliche\",\"Ältere Menschen\"]'
WHERE section = 'thema' AND label = 'Frauen Männer jung und alt';

UPDATE option_definitions SET keywords = '[\"Information Gesundheit\",\"Information Behinderung\",\"Information Sucht\",\"Arzt, Therapie\",\"Klinische Angebote\",\"Pflege, Unterstützung zu Hause\",\"Soziale Angebote\",\"Hilfsmittel und Fahrdienste\",\"Sterben und Tod\"]'
WHERE section = 'thema' AND label = 'Gesundheit';

UPDATE option_definitions SET keywords = '[]'
WHERE section = 'thema' AND label = 'Wohnen';

UPDATE option_definitions SET keywords = '[\"Quartierangebote\",\"Treffpunkte\",\"Religionsgemeinschaften\",\"Selbsthilfe\",\"Freiwilligenarbeit\",\"Freizeitaktivitäten\",\"Tiere\"]'
WHERE section = 'thema' AND label = 'Austausch und Freizeit';

UPDATE option_definitions SET keywords = '[\"Information, Beratung\",\"Zuzug, Aufenthalt, Bewilligung, Auswandern\",\"Sprache, Bildung\",\"Vereine und Organisationen der Migrationsbevölkerung\",\"Flüchtlinge\"]'
WHERE section = 'thema' AND label = 'Migration und Integration';

UPDATE option_definitions SET keywords = '[\"Notdienste\",\"Gewalt und Krisen\",\"Finanzielle Nothilfe\",\"Notwohnungen, Notunterkünfte\"]'
WHERE section = 'thema' AND label = 'Notlagen';

UPDATE option_definitions SET keywords = '[\"Schreibdienste, Übersetzungen\",\"Allgemeine Rechtsberatung\",\"Ombudsstellen, Mediation\",\"Computer und Administration\",\"Gegenseitige Unterstützung\",\"Begleitung und andere Angebote\",\"Informationsstellen und Behörden\"]'
WHERE section = 'thema' AND label = 'Allgemeine Hilfeleistungen';

UPDATE option_definitions SET keywords = '[]'
WHERE section = 'thema' AND label = 'Recht';

-- Verify keywords were added
SELECT label, keywords FROM option_definitions WHERE section = 'thema' ORDER BY sort_order;
