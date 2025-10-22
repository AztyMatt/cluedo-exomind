-- Table pour stocker la date courante du jeu
-- Cette table permet de gérer la date affichée dans l'interface de jeu

CREATE TABLE IF NOT EXISTS `current_date` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion de la date par défaut (date actuelle)
INSERT INTO `current_date` (`id`, `date`) VALUES 
(1, CURDATE())
ON DUPLICATE KEY UPDATE 
    `date` = VALUES(`date`),
    `updated_at` = CURRENT_TIMESTAMP;
