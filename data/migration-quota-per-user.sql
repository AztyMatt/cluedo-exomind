-- Migration pour ajouter la colonne quota_per_user à la table groups

-- Ajouter la colonne quota_per_user si elle n'existe pas
ALTER TABLE `groups` 
ADD COLUMN IF NOT EXISTS `quota_per_user` INT NOT NULL DEFAULT 0 COMMENT '0=illimité, sinon nombre max de papiers par joueur par jour' AFTER `img_path`;

-- Mettre à jour les quotas pour chaque groupe
UPDATE `groups` SET quota_per_user = 0 WHERE id IN (1, 2);  -- Team 1 et 2 : illimité
UPDATE `groups` SET quota_per_user = 4 WHERE id = 3;        -- Team 3 : 4 papiers max
UPDATE `groups` SET quota_per_user = 3 WHERE id IN (4, 5, 6); -- Team 4, 5, 6 : 3 papiers max

