-- Migration pour ajouter la colonne quota_per_user à la table groups

-- Vérifier si la colonne existe déjà
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'groups'
    AND COLUMN_NAME = 'quota_per_user'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `groups` ADD COLUMN `quota_per_user` INT NOT NULL DEFAULT 0 COMMENT "0=illimité, sinon nombre max de papiers par joueur par jour" AFTER `img_path`',
    'SELECT "Colonne quota_per_user déjà existante" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Mettre à jour les quotas pour chaque groupe
UPDATE `groups` SET quota_per_user = 0 WHERE id IN (1, 2);  -- Team 1 et 2 : illimité
UPDATE `groups` SET quota_per_user = 4 WHERE id = 3;        -- Team 3 : 4 papiers max
UPDATE `groups` SET quota_per_user = 3 WHERE id IN (4, 5, 6); -- Team 4, 5, 6 : 3 papiers max

