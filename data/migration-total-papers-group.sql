-- Migration pour modifier la table total_papers_found_group
-- Remplacer date par id_day et ajouter les colonnes manquantes

-- Ajouter la colonne id_day si elle n'existe pas
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'total_papers_found_group'
    AND COLUMN_NAME = 'id_day'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `total_papers_found_group` ADD COLUMN `id_day` INT NOT NULL AFTER `id_group`',
    'SELECT "Colonne id_day déjà existante" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter la colonne total_to_found si elle n'existe pas
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'total_papers_found_group'
    AND COLUMN_NAME = 'total_to_found'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `total_papers_found_group` ADD COLUMN `total_to_found` INT NOT NULL DEFAULT 0 AFTER `id_day`',
    'SELECT "Colonne total_to_found déjà existante" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter la colonne total_founded si elle n'existe pas
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'total_papers_found_group'
    AND COLUMN_NAME = 'total_founded'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `total_papers_found_group` ADD COLUMN `total_founded` INT NOT NULL DEFAULT 0 AFTER `total_to_found`',
    'SELECT "Colonne total_founded déjà existante" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter la colonne complete si elle n'existe pas
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'total_papers_found_group'
    AND COLUMN_NAME = 'complete'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `total_papers_found_group` ADD COLUMN `complete` BOOLEAN NOT NULL DEFAULT FALSE AFTER `total_founded`',
    'SELECT "Colonne complete déjà existante" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter la clé étrangère vers days si elle n'existe pas
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'total_papers_found_group'
    AND CONSTRAINT_NAME = 'total_papers_found_group_ibfk_2'
);

SET @sql_fk = IF(@fk_exists = 0,
    'ALTER TABLE `total_papers_found_group` ADD CONSTRAINT `total_papers_found_group_ibfk_2` FOREIGN KEY (id_day) REFERENCES `days`(id) ON DELETE CASCADE',
    'SELECT "Contrainte FK vers days déjà existante" AS message'
);

PREPARE stmt FROM @sql_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Supprimer la contrainte unique sur (id_group, date) si elle existe
SET @unique_date_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'total_papers_found_group'
    AND CONSTRAINT_NAME = 'unique_group_date'
);

SET @sql_drop_unique = IF(@unique_date_exists > 0,
    'ALTER TABLE `total_papers_found_group` DROP INDEX `unique_group_date`',
    'SELECT "Contrainte unique_group_date inexistante" AS message'
);

PREPARE stmt FROM @sql_drop_unique;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter la nouvelle contrainte unique sur (id_group, id_day) si elle n'existe pas
SET @unique_day_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'total_papers_found_group'
    AND CONSTRAINT_NAME = 'unique_group_day'
);

SET @sql_add_unique = IF(@unique_day_exists = 0,
    'ALTER TABLE `total_papers_found_group` ADD UNIQUE KEY `unique_group_day` (id_group, id_day)',
    'SELECT "Contrainte unique_group_day déjà existante" AS message'
);

PREPARE stmt FROM @sql_add_unique;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Optionnel: Si vous avez encore la colonne date, vous pouvez la supprimer
-- ATTENTION: Faites une sauvegarde avant !
-- ALTER TABLE `total_papers_found_group` DROP COLUMN IF EXISTS `date`;

