-- Migration pour augmenter la taille du champ enigm_label
-- Changement de VARCHAR(255) vers TEXT pour permettre des énigmes plus longues

ALTER TABLE `enigmes` MODIFY COLUMN `enigm_label` TEXT NOT NULL;
