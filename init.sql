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

-- Table des pièces/rooms
CREATE TABLE IF NOT EXISTS `rooms` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
    UNIQUE KEY unique_room_per_group (group_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des photos
CREATE TABLE IF NOT EXISTS `photos` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES `rooms`(id) ON DELETE CASCADE,
    UNIQUE KEY unique_filename_per_room (room_id, filename)
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

-- Index pour optimiser les performances
-- Note: MySQL crée automatiquement des index pour toutes les clés étrangères (FOREIGN KEY)
-- Les index suivants sont donc déjà créés automatiquement :
-- - idx sur users(group_id) via la FK vers groups
-- - idx sur rooms(group_id) via la FK vers groups
-- - idx sur photos(room_id) via la FK vers rooms
-- - idx sur papers(photo_id) via la FK vers photos
-- - idx sur masks(photo_id) via la FK vers photos

