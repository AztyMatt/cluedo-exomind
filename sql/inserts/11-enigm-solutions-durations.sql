-- Insertion des durées de résolution des énigmes (18 énigmes : 6 équipes × 3 jours)
-- Chaque énigme aura des timestamps null au départ
-- Ce fichier doit être exécuté APRÈS 08-enigmes.sql

INSERT INTO `enigm_solutions_durations` (`id_enigm`, `timestamp_start`, `timestamp_end`) VALUES
-- Jour 1 (énigmes ID 1-6)
(1, NULL, NULL),
(2, NULL, NULL),
(3, NULL, NULL),
(4, NULL, NULL),
(5, NULL, NULL),
(6, NULL, NULL),
-- Jour 2 (énigmes ID 7-12)
(7, NULL, NULL),
(8, NULL, NULL),
(9, NULL, NULL),
(10, NULL, NULL),
(11, NULL, NULL),
(12, NULL, NULL),
-- Jour 3 (énigmes ID 13-18)
(13, NULL, NULL),
(14, NULL, NULL),
(15, NULL, NULL),
(16, NULL, NULL),
(17, NULL, NULL),
(18, NULL, NULL)
ON DUPLICATE KEY UPDATE 
    `timestamp_start` = VALUES(`timestamp_start`),
    `timestamp_end` = VALUES(`timestamp_end`);
