-- Inserts pour la table groups
-- Personnages du Cluedo avec leurs pôles associés

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

INSERT INTO `groups` (id, name, pole_name, color, img_path) VALUES
(1, 'Colonnel Moutarde', 'ADV Compta', '#FFDB58', 'assets/img/colonnel_moutarde.png'),
(2, 'Mademoiselle Rose', 'Comm Market', '#FF69B4', 'assets/img/mademoiselle_rose.png'),
(3, 'Madame Leblanc', 'Recruteurs', '#FFFFFF', 'assets/img/madame_leblanc.png'),
(4, 'Madame Pervenche', 'Business', '#1E3A8A', 'assets/img/madame_pervenche.png'),
(5, 'Révérend Olive', 'CDS', '#6B8E23', 'assets/img/reverend_olive.png'),
(6, 'Professeur Violet', 'Consultants', '#8F00FF', 'assets/img/professeur_violet.png')
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    pole_name = VALUES(pole_name),
    color = VALUES(color),
    img_path = VALUES(img_path),
    updated_at = CURRENT_TIMESTAMP;

