-- Migration pour ajouter le champ paper_type à la table papers
-- paper_type: 0 = papier blanc (papier.png), 1 = papier doré (papier_dore.png)

ALTER TABLE `papers` ADD COLUMN `paper_type` TINYINT NOT NULL DEFAULT 0 COMMENT '0=blanc, 1=doré' AFTER `z_index`;

-- Mettre à jour tous les papiers existants pour qu'ils soient de type blanc (0)
UPDATE `papers` SET `paper_type` = 0 WHERE `paper_type` IS NULL;
