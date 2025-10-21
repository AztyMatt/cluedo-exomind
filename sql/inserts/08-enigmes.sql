-- Inserts pour la table enigmes
-- 6 équipes × 3 jours = 18 énigmes

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

INSERT INTO `enigmes` (id_group, id_day, enigm_label, enigm_solution, status, solved) VALUES
-- Jour 1 : 2025-10-27
(1, 1, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.', 'solution', 0, FALSE), -- Team 1 - Jour 1
(2, 1, 'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.', 'solution', 0, FALSE), -- Team 2 - Jour 1
(3, 1, 'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.', 'solution', 0, FALSE), -- Team 3 - Jour 1
(4, 1, 'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.', 'solution', 0, FALSE), -- Team 4 - Jour 1
(5, 1, 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium.', 'solution', 0, FALSE), -- Team 5 - Jour 1
(6, 1, 'Totam rem aperiam eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt.', 'solution', 0, FALSE), -- Team 6 - Jour 1

-- Jour 2 : 2025-10-28
(1, 2, 'Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores.', 'solution', 0, FALSE), -- Team 1 - Jour 2
(2, 2, 'Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit.', 'solution', 0, FALSE), -- Team 2 - Jour 2
(3, 2, 'Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur.', 'solution', 0, FALSE), -- Team 3 - Jour 2
(4, 2, 'At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti.', 'solution', 0, FALSE), -- Team 4 - Jour 2
(5, 2, 'Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi.', 'solution', 0, FALSE), -- Team 5 - Jour 2
(6, 2, 'Temporibus autem quibusdam et aut officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates.', 'solution', 0, FALSE), -- Team 6 - Jour 2

-- Jour 3 : 2025-10-29
(1, 3, 'Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur.', 'solution', 0, FALSE), -- Team 1 - Jour 3
(2, 3, 'Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat.', 'solution', 0, FALSE), -- Team 2 - Jour 3
(3, 3, 'Facere possimus, omnis voluptas assumenda est, omnis dolor repellendus. Temporibus autem quibusdam et aut.', 'solution', 0, FALSE), -- Team 3 - Jour 3
(4, 3, 'Officiis debitis aut rerum necessitatibus saepe eveniet ut et voluptates repudiandae sint et molestiae non recusandae.', 'solution', 0, FALSE), -- Team 4 - Jour 3
(5, 3, 'Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis voluptatibus maiores alias consequatur aut perferendis.', 'solution', 0, FALSE), -- Team 5 - Jour 3
(6, 3, 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium totam rem aperiam.', 'solution', 0, FALSE) -- Team 6 - Jour 3

ON DUPLICATE KEY UPDATE 
    enigm_label = VALUES(enigm_label),
    enigm_solution = VALUES(enigm_solution),
    status = VALUES(status),
    solved = VALUES(solved),
    updated_at = CURRENT_TIMESTAMP;

