-- Migration pour ajouter le tracking du solveur dans la table enigmes
-- Ajouter une colonne pour tracker qui a résolu l'énigme

ALTER TABLE `enigmes` 
ADD COLUMN `solved_by_user_id` INT NULL AFTER `datetime_solved`,
ADD FOREIGN KEY (`solved_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;
