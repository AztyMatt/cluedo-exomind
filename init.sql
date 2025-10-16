-- Script d'initialisation de la base de données MySQL pour Cluedo
-- Structure relationnelle complète

-- Table des groupes
CREATE TABLE IF NOT EXISTS `groups` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS `users` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
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

-- Index pour optimiser les performances
CREATE INDEX idx_users_group ON `users`(group_id);
CREATE INDEX idx_papers_photo ON `papers`(photo_id);
CREATE INDEX idx_masks_photo ON `masks`(photo_id);
CREATE INDEX idx_arrows_photo ON `arrows`(photo_id);

