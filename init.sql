-- Script d'initialisation de la base de données MySQL pour Cluedo
-- Structure relationnelle complète

-- Table des groupes
CREATE TABLE IF NOT EXISTS `groups` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    pole_name VARCHAR(255) NOT NULL,
    color VARCHAR(7),
    img_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS `users` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    firstname VARCHAR(255) NOT NULL,
    lastname VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    activation_code VARCHAR(10) NOT NULL UNIQUE,
    has_activated BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des photos (entité principale indépendante)
CREATE TABLE IF NOT EXISTS `photos` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    file_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des papers (papiers/notes placés sur une photo)
CREATE TABLE IF NOT EXISTS `papers` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    photo_id INT NOT NULL,
    position_left DECIMAL(15, 10) NOT NULL,
    position_top DECIMAL(15, 10) NOT NULL,
    scale_x DECIMAL(10, 6) DEFAULT 1.000000,
    scale_y DECIMAL(10, 6) DEFAULT 1.000000,
    angle DECIMAL(10, 6) DEFAULT 0.000000,
    z_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (photo_id) REFERENCES `photos`(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des masks (zones/tracés sur une photo)
CREATE TABLE IF NOT EXISTS `masks` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    photo_id INT NOT NULL,
    original_points JSON NOT NULL,
    curve_handles JSON,
    position_left DECIMAL(15, 10) NOT NULL,
    position_top DECIMAL(15, 10) NOT NULL,
    z_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (photo_id) REFERENCES `photos`(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des flèches (navigation entre les photos)
CREATE TABLE IF NOT EXISTS `arrows` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    photo_id INT NOT NULL,
    target_photo_id INT NULL,
    position_left DECIMAL(15, 10) NOT NULL,
    position_top DECIMAL(15, 10) NOT NULL,
    angle DECIMAL(10, 6) DEFAULT 0.000000,
    active BOOLEAN DEFAULT TRUE,
    free_placement BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (photo_id) REFERENCES `photos`(id) ON DELETE CASCADE,
    FOREIGN KEY (target_photo_id) REFERENCES `photos`(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des énigmes
CREATE TABLE IF NOT EXISTS `enigmes` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_group INT NOT NULL,
    enigm_label VARCHAR(255) NOT NULL,
    enigm_solution VARCHAR(255) NOT NULL,
    solved BOOLEAN DEFAULT FALSE,
    datetime_solved DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_group) REFERENCES `groups`(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des jours
CREATE TABLE IF NOT EXISTS `days` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table du total de papiers trouvés par groupe
CREATE TABLE IF NOT EXISTS `total_papers_found_group` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_group INT NOT NULL,
    date DATE NOT NULL,
    compteur INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_group) REFERENCES `groups`(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_date (id_group, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des papiers trouvés par utilisateur
CREATE TABLE IF NOT EXISTS `papers_found_user` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_paper INT NOT NULL,
    id_player INT NOT NULL,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_paper) REFERENCES `papers`(id) ON DELETE CASCADE,
    FOREIGN KEY (id_player) REFERENCES `users`(id) ON DELETE CASCADE,
    UNIQUE KEY unique_paper_player_date (id_paper, id_player, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index pour optimiser les performances
CREATE INDEX idx_users_group ON `users`(group_id);
CREATE INDEX idx_papers_photo ON `papers`(photo_id);
CREATE INDEX idx_masks_photo ON `masks`(photo_id);
CREATE INDEX idx_arrows_photo ON `arrows`(photo_id);
CREATE INDEX idx_enigmes_group ON `enigmes`(id_group);
CREATE INDEX idx_total_papers_group ON `total_papers_found_group`(id_group);
CREATE INDEX idx_total_papers_date ON `total_papers_found_group`(date);
CREATE INDEX idx_papers_found_user_player ON `papers_found_user`(id_player);
CREATE INDEX idx_papers_found_user_paper ON `papers_found_user`(id_paper);
CREATE INDEX idx_papers_found_user_date ON `papers_found_user`(date);

-- Insertion des jours du jeu
INSERT INTO `days` (id, date) VALUES
(1, '2025-10-27'), -- Lundi 27 octobre 2025
(2, '2025-10-28'), -- Mardi 28 octobre 2025
(3, '2025-10-29')  -- Mercredi 29 octobre 2025
ON DUPLICATE KEY UPDATE 
    date = VALUES(date),
    updated_at = CURRENT_TIMESTAMP;

