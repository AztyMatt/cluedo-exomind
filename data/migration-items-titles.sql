-- Migration pour ajouter les nouveaux champs à la table items
-- À exécuter si la table items existe déjà

-- Ajouter la colonne title si elle n'existe pas
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'items'
    AND COLUMN_NAME = 'title'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `items` ADD COLUMN `title` VARCHAR(255) NOT NULL DEFAULT "" AFTER `path`',
    'SELECT "Colonne title déjà existante" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter la colonne subtitle si elle n'existe pas
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'items'
    AND COLUMN_NAME = 'subtitle'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `items` ADD COLUMN `subtitle` VARCHAR(500) NOT NULL DEFAULT "" AFTER `title`',
    'SELECT "Colonne subtitle déjà existante" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajouter la colonne solved_title si elle n'existe pas
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'items'
    AND COLUMN_NAME = 'solved_title'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `items` ADD COLUMN `solved_title` VARCHAR(500) NULL AFTER `subtitle`',
    'SELECT "Colonne solved_title déjà existante" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Mettre à jour les données existantes avec les titres et sous-titres
UPDATE `items` SET 
    `title` = 'Clé USB',
    `subtitle` = 'Où la brancher ?'
WHERE `id` = 1;

UPDATE `items` SET 
    `title` = 'Ciseaux',
    `subtitle` = 'Qui a perdu ces ciseaux?'
WHERE `id` = 2;

UPDATE `items` SET 
    `title` = 'Burger',
    `subtitle` = 'Où placer ce burger Mc Do peu ragoûtant?'
WHERE `id` = 3;

UPDATE `items` SET 
    `title` = 'Bloc Minecraft',
    `subtitle` = 'Un amateur de Minecraft?'
WHERE `id` = 4;

UPDATE `items` SET 
    `title` = 'Poudre marron',
    `subtitle` = 'Que faire de cette poudre?'
WHERE `id` = 5;

UPDATE `items` SET 
    `title` = 'Tasse à café',
    `subtitle` = 'Quelqu\'un a encore perdu sa tasse...'
WHERE `id` = 6;

UPDATE `items` SET 
    `title` = 'Engrenage',
    `subtitle` = 'Redonnons vie à une branche'
WHERE `id` = 7;

UPDATE `items` SET 
    `title` = 'Feuille',
    `subtitle` = 'Encore un engrenage qui a cassé...'
WHERE `id` = 8;

UPDATE `items` SET 
    `title` = 'WD 40',
    `subtitle` = 'Faudrait penser à la huiler...'
WHERE `id` = 9;

UPDATE `items` SET 
    `title` = 'PC Portable',
    `subtitle` = 'Ca serait bien de le placer quelque part'
WHERE `id` = 10;

UPDATE `items` SET 
    `title` = 'Rubik\'s Cube',
    `subtitle` = 'Un Rubik\'s Cube égaré'
WHERE `id` = 11;

UPDATE `items` SET 
    `title` = 'Cache',
    `subtitle` = 'Un cache, mais qui cache quoi?'
WHERE `id` = 12;

UPDATE `items` SET 
    `title` = 'Aiguilles',
    `subtitle` = 'Remettre les pendules à l\'heure'
WHERE `id` = 13;

UPDATE `items` SET 
    `title` = 'Plante',
    `subtitle` = 'Un peu de verdure chez Exo'
WHERE `id` = 14;

UPDATE `items` SET 
    `title` = 'Mousse',
    `subtitle` = 'Mousse qui peut!'
WHERE `id` = 15;

UPDATE `items` SET 
    `title` = 'Clé',
    `subtitle` = 'Qu\'ouvre cette clé?'
WHERE `id` = 16;

UPDATE `items` SET 
    `title` = 'Roue',
    `subtitle` = 'Une roulette égarée'
WHERE `id` = 17;

UPDATE `items` SET 
    `title` = 'Rond vert',
    `subtitle` = 'Où placer ce rond vert?'
WHERE `id` = 18;

-- Supprimer les valeurs par défaut maintenant que les données sont mises à jour
ALTER TABLE `items` 
MODIFY COLUMN `title` VARCHAR(255) NOT NULL,
MODIFY COLUMN `subtitle` VARCHAR(500) NOT NULL;
