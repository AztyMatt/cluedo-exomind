-- Requête pour obtenir le classement des équipes par durée de résolution
-- Le classement est déterminé par la plus petite durée (timestamp_end - timestamp_start)
SELECT 
    e.id_group,
    g.name as team_name,
    g.pole_name,
    e.id_day,
    CASE 
        WHEN e.id_day = 1 THEN '🏛️ Scène du crime'
        WHEN e.id_day = 2 THEN '🔪 Arme du crime'
        WHEN e.id_day = 3 THEN '🎭 Auteur du crime'
    END as day_objective,
    esd.timestamp_start,
    esd.timestamp_end,
    CASE 
        WHEN esd.timestamp_start IS NULL THEN 'Pas encore commencé'
        WHEN esd.timestamp_end IS NULL THEN 'En cours'
        ELSE CONCAT(
            FLOOR(TIMESTAMPDIFF(SECOND, esd.timestamp_start, esd.timestamp_end) / 60), 'm ',
            TIMESTAMPDIFF(SECOND, esd.timestamp_start, esd.timestamp_end) % 60, 's'
        )
    END as duration_display,
    CASE 
        WHEN esd.timestamp_start IS NULL THEN NULL
        WHEN esd.timestamp_end IS NULL THEN NULL
        ELSE TIMESTAMPDIFF(SECOND, esd.timestamp_start, esd.timestamp_end)
    END as duration_seconds
FROM `enigmes` e
LEFT JOIN `groups` g ON e.id_group = g.id
LEFT JOIN `enigm_solutions_durations` esd ON e.id = esd.id_enigm
WHERE e.status = 2  -- Seulement les énigmes résolues
ORDER BY 
    e.id_day ASC,
    duration_seconds ASC;  -- Plus rapide en premier
