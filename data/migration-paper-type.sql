-- Migration pour ajouter le champ paper_type à la table papers
-- paper_type: 0 = papier blanc (papier.png), 1 = papier doré (papier_dore.png)

-- Vérifier si la colonne existe déjà
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'papers'
    AND COLUMN_NAME = 'paper_type'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `papers` ADD COLUMN `paper_type` TINYINT NOT NULL DEFAULT 0 COMMENT "0=blanc, 1=doré" AFTER `z_index`',
    'SELECT "Colonne paper_type déjà existante" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Mettre à jour tous les papiers existants pour qu'ils soient de type blanc (0)
UPDATE `papers` SET `paper_type` = 0 WHERE `paper_type` IS NULL;
