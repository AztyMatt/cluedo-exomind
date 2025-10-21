-- Inserts pour la table total_papers_found_group
-- 6 équipes × 3 jours = 18 enregistrements

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

INSERT INTO `total_papers_found_group` (id_group, id_day, total_to_found, total_founded, complete) VALUES
-- Jour 1
(1, 1, 5, 0, FALSE),  -- Team 1: Colonnel Moutarde (ADV Compta)
(2, 1, 5, 0, FALSE),  -- Team 2: Mademoiselle Rose (Comm Market)
(3, 1, 7, 0, FALSE),  -- Team 3: Madame Leblanc (Recruteurs)
(4, 1, 10, 0, FALSE), -- Team 4: Madame Pervenche (Business)
(5, 1, 10, 0, FALSE), -- Team 5: Révérend Olive (CDS)
(6, 1, 10, 0, FALSE), -- Team 6: Professeur Violet (Consultants)

-- Jour 2
(1, 2, 5, 0, FALSE),  -- Team 1: Colonnel Moutarde (ADV Compta)
(2, 2, 5, 0, FALSE),  -- Team 2: Mademoiselle Rose (Comm Market)
(3, 2, 7, 0, FALSE),  -- Team 3: Madame Leblanc (Recruteurs)
(4, 2, 10, 0, FALSE), -- Team 4: Madame Pervenche (Business)
(5, 2, 10, 0, FALSE), -- Team 5: Révérend Olive (CDS)
(6, 2, 10, 0, FALSE), -- Team 6: Professeur Violet (Consultants)

-- Jour 3
(1, 3, 5, 0, FALSE),  -- Team 1: Colonnel Moutarde (ADV Compta)
(2, 3, 5, 0, FALSE),  -- Team 2: Mademoiselle Rose (Comm Market)
(3, 3, 7, 0, FALSE),  -- Team 3: Madame Leblanc (Recruteurs)
(4, 3, 10, 0, FALSE), -- Team 4: Madame Pervenche (Business)
(5, 3, 10, 0, FALSE), -- Team 5: Révérend Olive (CDS)
(6, 3, 10, 0, FALSE)  -- Team 6: Professeur Violet (Consultants)

ON DUPLICATE KEY UPDATE 
    total_to_found = VALUES(total_to_found),
    total_founded = VALUES(total_founded),
    complete = VALUES(complete),
    updated_at = CURRENT_TIMESTAMP;

