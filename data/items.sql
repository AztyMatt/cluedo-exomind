-- Table des items (objets à trouver dans le jeu)
CREATE TABLE IF NOT EXISTS `items` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    path VARCHAR(500) NOT NULL,
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(500) NOT NULL,
    solved_title VARCHAR(500) NULL,
    solved BOOLEAN DEFAULT FALSE,
    id_solved_user INT NULL,
    datetime_solved DATETIME NULL,
    id_mask INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
    FOREIGN KEY (id_solved_user) REFERENCES `users`(id) ON DELETE SET NULL,
    FOREIGN KEY (id_mask) REFERENCES `masks`(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index pour optimiser les performances
CREATE INDEX idx_items_group ON `items`(group_id);
CREATE INDEX idx_items_solved ON `items`(solved);
CREATE INDEX idx_items_solved_user ON `items`(id_solved_user);
CREATE INDEX idx_items_mask ON `items`(id_mask);
