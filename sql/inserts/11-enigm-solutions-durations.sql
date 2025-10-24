-- Insertion des durées de résolution des énigmes (18 énigmes : 6 équipes × 3 jours)
-- Chaque énigme aura des timestamps null au départ
-- Ce fichier doit être exécuté APRÈS 08-enigmes.sql

-- Insertion automatique des durées pour toutes les énigmes existantes
INSERT INTO `enigm_solutions_durations` (`id_enigm`, `timestamp_start`, `timestamp_end`)
SELECT 
    e.id as id_enigm,
    NULL as timestamp_start,
    NULL as timestamp_end
FROM `enigmes` e
WHERE NOT EXISTS (
    SELECT 1 FROM `enigm_solutions_durations` esd 
    WHERE esd.id_enigm = e.id
)
ON DUPLICATE KEY UPDATE 
    `timestamp_start` = VALUES(`timestamp_start`),
    `timestamp_end` = VALUES(`timestamp_end`);
