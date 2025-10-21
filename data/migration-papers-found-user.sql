-- Migration pour modifier la table papers_found_user
-- Remplacer date par id_day

-- Ajouter la colonne id_day si elle n'existe pas
ALTER TABLE `papers_found_user` 
ADD COLUMN IF NOT EXISTS `id_day` INT NOT NULL AFTER `id_player`;

-- Ajouter la clé étrangère vers days si elle n'existe pas
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'papers_found_user'
    AND CONSTRAINT_NAME = 'papers_found_user_ibfk_3'
);

SET @sql_fk = IF(@fk_exists = 0,
    'ALTER TABLE `papers_found_user` ADD CONSTRAINT `papers_found_user_ibfk_3` FOREIGN KEY (id_day) REFERENCES `days`(id) ON DELETE CASCADE',
    'SELECT "Contrainte FK vers days déjà existante" AS message'
);

PREPARE stmt FROM @sql_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Supprimer la contrainte unique sur (id_paper, id_player, date) si elle existe
SET @unique_date_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'papers_found_user'
    AND CONSTRAINT_NAME = 'unique_paper_player_date'
);

SET @sql_drop_unique = IF(@unique_date_exists > 0,
    'ALTER TABLE `papers_found_user` DROP INDEX `unique_paper_player_date`',
    'SELECT "Contrainte unique_paper_player_date inexistante" AS message'
);

PREPARE stmt FROM @sql_drop_unique;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter la nouvelle contrainte unique sur (id_paper, id_player, id_day) si elle n'existe pas
SET @unique_day_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'papers_found_user'
    AND CONSTRAINT_NAME = 'unique_paper_player_day'
);

SET @sql_add_unique = IF(@unique_day_exists = 0,
    'ALTER TABLE `papers_found_user` ADD UNIQUE KEY `unique_paper_player_day` (id_paper, id_player, id_day)',
    'SELECT "Contrainte unique_paper_player_day déjà existante" AS message'
);

PREPARE stmt FROM @sql_add_unique;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Optionnel: Si vous avez encore la colonne date, vous pouvez la supprimer
-- ATTENTION: Faites une sauvegarde avant !
-- ALTER TABLE `papers_found_user` DROP COLUMN IF EXISTS `date`;

