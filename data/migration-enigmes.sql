-- Migration pour ajouter les colonnes status et id_day à la table enigmes

-- Ajouter la colonne id_day si elle n'existe pas
ALTER TABLE `enigmes` 
ADD COLUMN IF NOT EXISTS `id_day` INT NOT NULL AFTER `id_group`;

-- Ajouter la colonne status si elle n'existe pas
ALTER TABLE `enigmes` 
ADD COLUMN IF NOT EXISTS `status` INT NOT NULL DEFAULT 0 COMMENT '0=pas débloqué, 1=en cours de résolution, 2=résolue' AFTER `enigm_solution`;

-- Ajouter la clé étrangère vers days si elle n'existe pas
-- Note: On vérifie d'abord si la contrainte existe déjà
SET @constraint_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'enigmes'
    AND CONSTRAINT_NAME = 'enigmes_ibfk_2'
);

SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE `enigmes` ADD CONSTRAINT `enigmes_ibfk_2` FOREIGN KEY (id_day) REFERENCES `days`(id) ON DELETE CASCADE',
    'SELECT "Contrainte de clé étrangère déjà existante" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Optionnel: Mettre à jour les enregistrements existants
-- Si vous avez déjà des énigmes sans id_day, vous devez les mettre à jour manuellement
-- Exemple pour assigner toutes les énigmes au jour 1:
-- UPDATE `enigmes` SET id_day = 1 WHERE id_day IS NULL OR id_day = 0;

