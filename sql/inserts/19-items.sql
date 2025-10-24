SET NAMES utf8mb4;

INSERT INTO `items` (`id`, `group_id`, `path`, `title`, `subtitle`, `solved_title`, `solved`, `id_solved_user`, `datetime_solved`, `id_mask`, `created_at`, `updated_at`) VALUES
(1, 1, '/assets/img/items/1.png', 'Clé USB', 'Où la brancher ?', NULL, 0, NULL, NULL, 312, '2025-10-23 10:02:59', '2025-10-23 11:49:31'),
(2, 1, '/assets/img/items/2.png', 'Ciseaux', 'Qui a perdu ces ciseaux?', NULL, 0, NULL, NULL, 302, '2025-10-23 10:02:59', '2025-10-23 11:47:51'),
(3, 1, '/assets/img/items/3.png', 'Burger', 'Où placer ce burger Mc Do peu ragoûtant?', NULL, 0, NULL, NULL, 314, '2025-10-23 10:02:59', '2025-10-23 11:50:42'),
(4, 2, '/assets/img/items/4.png', 'Bloc Minecraft', 'Un amateur de Minecraft?', NULL, 0, NULL, NULL, 297, '2025-10-23 10:02:59', '2025-10-23 11:43:13'),
(5, 2, '/assets/img/items/5.png', 'Poudre marron', 'Que faire de cette poudre?', NULL, 0, NULL, NULL, 275, '2025-10-23 10:02:59', '2025-10-23 10:53:19'),
(6, 2, '/assets/img/items/6.png', 'Tasse à café', 'Quelqu\'un a encore perdu sa tasse...', NULL, 0, NULL, NULL, 274, '2025-10-23 10:02:59', '2025-10-23 11:44:02'),
(7, 3, '/assets/img/items/7.png', 'Engrenage', 'Encore un engrenage qui a cassé...', NULL, 0, NULL, NULL, 300, '2025-10-23 10:02:59', '2025-10-23 11:46:15'),
(8, 3, '/assets/img/items/8.png', 'Feuille', 'Redonnons vie à une branche', NULL, 0, NULL, NULL, NULL, '2025-10-23 10:02:59', '2025-10-23 11:48:55'),
(9, 3, '/assets/img/items/9.png', 'WD 40', 'Faudrait penser à la huiler...', NULL, 0, NULL, NULL, 265, '2025-10-23 10:02:59', '2025-10-23 10:42:23'),
(10, 4, '/assets/img/items/10.png', 'PC Portable', 'Ca serait bien de le placer quelque part', NULL, 0, NULL, NULL, 294, '2025-10-23 10:02:59', '2025-10-23 11:40:41'),
(11, 4, '/assets/img/items/11.png', 'Rubik\'s Cube', 'Un Rubik\'s Cube égaré', NULL, 0, NULL, NULL, 289, '2025-10-23 10:02:59', '2025-10-23 11:37:22'),
(12, 4, '/assets/img/items/12.png', 'Cache', 'Un cache, mais qui cache quoi?', NULL, 0, NULL, NULL, 293, '2025-10-23 10:02:59', '2025-10-23 11:39:41'),
(13, 5, '/assets/img/items/13.png', 'Aiguilles', 'Remettre les pendules à l\'heure', NULL, 0, NULL, NULL, 272, '2025-10-23 10:02:59', '2025-10-23 10:50:17'),
(14, 5, '/assets/img/items/14.png', 'Plante', 'Un peu de verdure chez Exo', NULL, 0, NULL, NULL, 175, '2025-10-23 10:02:59', '2025-10-23 10:49:28'),
(15, 5, '/assets/img/items/15.png', 'Mousse', 'Mousse qui peut!', NULL, 0, NULL, NULL, 264, '2025-10-23 10:02:59', '2025-10-23 10:39:18'),
(16, 6, '/assets/img/items/16.png', 'Clé', 'Qu\'ouvre cette clé?', NULL, 0, NULL, NULL, 299, '2025-10-23 10:02:59', '2025-10-23 11:45:21'),
(17, 6, '/assets/img/items/17.png', 'Roue', 'Une roulette égarée', NULL, 0, NULL, NULL, 271, '2025-10-23 10:02:59', '2025-10-23 10:48:40'),
(18, 6, '/assets/img/items/18.png', 'Rond vert', 'Où placer ce rond vert?', NULL, 0, NULL, NULL, 278, '2025-10-23 10:02:59', '2025-10-23 10:55:10')

ON DUPLICATE KEY UPDATE 
    `group_id` = VALUES(`group_id`),
    `path` = VALUES(`path`),
    `title` = VALUES(`title`),
    `subtitle` = VALUES(`subtitle`),
    `solved_title` = VALUES(`solved_title`),
    `solved` = VALUES(`solved`),
    `id_solved_user` = VALUES(`id_solved_user`),
    `datetime_solved` = VALUES(`datetime_solved`),
    `id_mask` = VALUES(`id_mask`),
    `updated_at` = VALUES(`updated_at`);
