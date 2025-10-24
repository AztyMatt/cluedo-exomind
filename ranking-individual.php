<?php
// Connexion à la base de données
require_once __DIR__ . '/db-connection.php';
$dbConnection = getDBConnection();

// Vérifier la connexion avant de continuer
if (!$dbConnection) {
    error_log("Erreur critique: Impossible de se connecter à la base de données");
    die("Erreur de connexion à la base de données");
}

// Fonction pour calculer le jour du jeu basé sur la date courante
function getGameDay($dbConnection) {
    if (!$dbConnection) {
        error_log("Erreur: Connexion à la base de données échouée dans getGameDay()");
        return 1; // Retourner jour 1 par défaut
    }
    
    try {
        // Récupérer la date courante de la base de données
        $query = "SELECT `date` FROM `current_date` WHERE `id` = 1";
        $stmt = $dbConnection->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && isset($result['date'])) {
            $currentDate = new DateTime($result['date']);
        } else {
            // Si pas de date en base, utiliser la date actuelle
            $currentDate = new DateTime();
        }
        
        // Date de référence : 27 octobre 2025 = Jour 1
        $referenceDate = new DateTime('2025-10-27');
        
        // Calculer la différence en jours
        $diff = $currentDate->diff($referenceDate);
        $daysDiff = $diff->days;
        
        // Si la date courante est avant le 27/10/2025, retourner jour 1
        if ($currentDate < $referenceDate) {
            return 1;
        }
        
        // Calculer le jour : 27/10 = jour 1, 28/10 = jour 2, 29/10 = jour 3
        $gameDay = $daysDiff + 1;
        
        // Limiter à jour 3 maximum, sinon retourner jour 1
        if ($gameDay > 3) {
            return 1;
        }
        
        return $gameDay;
        
    } catch (Exception $e) {
        // En cas d'erreur, retourner jour 1 par défaut
        return 1;
    }
}

// Fonction pour formater le nom : Prénom NOM
function formatUserName($firstname, $lastname) {
    // Formater : première lettre majuscule pour le prénom, tout en majuscules pour le nom
    $formattedFirstName = ucfirst(strtolower($firstname));
    $formattedLastName = strtoupper($lastname);
    
    return $formattedFirstName . ' ' . $formattedLastName;
}

// Calculer le jour du jeu
$gameDay = getGameDay($dbConnection);

// Test simple d'abord - récupérer tous les utilisateurs avec nombre de papiers trouvés, objets trouvés et papiers en or par jour
$simpleQuery = "SELECT u.id, u.firstname, u.lastname, u.has_activated, g.name as group_name, g.pole_name, g.img_path, 
                COALESCE(papers_count.total_papers, 0) as total_papers_found,
                COALESCE(items_count.total_items, 0) as total_items_found,
                COALESCE(golden_day1.golden_papers, 0) as golden_papers_day1,
                COALESCE(golden_day2.golden_papers, 0) as golden_papers_day2,
                COALESCE(golden_day3.golden_papers, 0) as golden_papers_day3
                FROM users u 
                LEFT JOIN `groups` g ON u.group_id = g.id 
                LEFT JOIN (
                    SELECT id_player, COUNT(*) as total_papers 
                    FROM papers_found_user 
                    GROUP BY id_player
                ) papers_count ON u.id = papers_count.id_player
                LEFT JOIN (
                    SELECT id_solved_user, COUNT(*) as total_items 
                    FROM items 
                    WHERE solved = 1
                    GROUP BY id_solved_user
                ) items_count ON u.id = items_count.id_solved_user
                LEFT JOIN (
                    SELECT pf.id_player, COUNT(*) as golden_papers
                    FROM papers_found_user pf
                    INNER JOIN papers p ON pf.id_paper = p.id
                    WHERE p.paper_type = 1 AND pf.id_day = 1
                    GROUP BY pf.id_player
                ) golden_day1 ON u.id = golden_day1.id_player
                LEFT JOIN (
                    SELECT pf.id_player, COUNT(*) as golden_papers
                    FROM papers_found_user pf
                    INNER JOIN papers p ON pf.id_paper = p.id
                    WHERE p.paper_type = 1 AND pf.id_day = 2
                    GROUP BY pf.id_player
                ) golden_day2 ON u.id = golden_day2.id_player
                LEFT JOIN (
                    SELECT pf.id_player, COUNT(*) as golden_papers
                    FROM papers_found_user pf
                    INNER JOIN papers p ON pf.id_paper = p.id
                    WHERE p.paper_type = 1 AND pf.id_day = 3
                    GROUP BY pf.id_player
                ) golden_day3 ON u.id = golden_day3.id_player
                ORDER BY u.lastname ASC, u.firstname ASC";

try {
    $stmt = $dbConnection->prepare($simpleQuery);
    $stmt->execute();
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ajouter des colonnes vides pour la compatibilité avec l'affichage et calculer les points bonus
    foreach ($players as &$player) {
        $player['day1_points'] = $player['golden_papers_day1'] * 1000; // 1000 points par papier en or jour 1
        $player['day2_points'] = $player['golden_papers_day2'] * 1000; // 1000 points par papier en or jour 2
        $player['day3_points'] = $player['golden_papers_day3'] * 1000; // 1000 points par papier en or jour 3
        $player['items_count'] = $player['total_items_found'];
        $player['items_bonus_points'] = $player['total_items_found'] * 500; // 500 points par objet trouvé
        $player['golden_papers_total'] = $player['golden_papers_day1'] + $player['golden_papers_day2'] + $player['golden_papers_day3'];
        $player['golden_papers_bonus_points'] = $player['golden_papers_total'] * 1000; // 1000 points par papier en or total
    }
    
} catch (PDOException $e) {
    $players = [];
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cluedo - Classement Individuel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: url('assets/img/background.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: #eee;
            min-height: 100vh;
            padding: 20px 20px 40px 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .game-description {
            text-align: center;
            color: #fff;
            font-size: 1.4rem;
            font-weight: bold;
            margin-bottom: 40px;
            padding: 0 20px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }

        .logo {
            max-width: 400px;
            width: 100%;
            height: auto;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
            display: block;
            margin: 0 auto 20px auto;
        }

        .logo:hover {
            transform: scale(1.05);
            transition: transform 0.3s ease;
        }

        .buttons-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin: 30px auto;
            flex-wrap: wrap;
        }

        .game-button {
            padding: 15px 40px;
            font-size: 1.2rem;
            font-weight: bold;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .game-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        .game-button:active {
            transform: translateY(-1px);
        }

        .btn-rules {
            background: #ffdf29;
            color: #073545;
            padding: 18px 50px;
            font-size: 1.3rem;
        }

        .btn-rules:hover {
            background: #f4d03f;
        }

        .btn-ranking {
            background: #ff6b35;
            color: white;
            padding: 10px 25px;
            font-size: 0.9rem;
        }

        .btn-ranking:hover {
            background: #e55a2b;
        }

        .btn-play {
            background: #073545;
            color: white;
            padding: 18px 50px;
            font-size: 1.3rem;
        }

        .btn-play:hover {
            background: #0a4a5e;
        }

        .btn-individual {
            background: #4CAF50;
            color: white;
            padding: 10px 25px;
            font-size: 0.9rem;
        }

        .btn-individual:hover {
            background: #45a049;
        }

        .ranking-buttons {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .ranking-buttons-fixed {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .ranking-buttons-fixed .btn-ranking,
        .ranking-buttons-fixed .btn-individual {
            padding: 8px 20px;
            font-size: 0.8rem;
            min-width: 120px;
        }

        .btn-ranking-top {
            border-radius: 12px 12px 0 0;
            margin-bottom: 0;
        }

        .btn-ranking-bottom {
            border-radius: 0 0 12px 12px;
            margin-top: 0;
        }

        .ranking-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: bold;
            color: #fff;
            margin: 40px 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }

        .ranking-content {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 15px;
            padding: 30px;
            margin-top: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .ranking-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            margin-top: 20px;
        }

        .ranking-table th {
            background: linear-gradient(135deg, #073545, #0a4a5e);
            color: white;
            padding: 15px 10px;
            text-align: center;
            font-weight: bold;
            font-size: 0.9rem;
            border-bottom: 2px solid #ff6b35;
        }

        .ranking-table td {
            padding: 12px 8px;
            text-align: center;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            color: #333;
            font-size: 0.85rem;
        }

        .ranking-table tr:nth-child(even) {
            background: rgba(0, 0, 0, 0.02);
        }

        .ranking-table tr:hover {
            background: rgba(255, 107, 53, 0.1);
            transform: scale(1.01);
            transition: all 0.2s ease;
        }

        .rank-position {
            font-weight: bold;
            font-size: 1.1rem;
            color: #ff6b35;
        }

        .rank-1 { color: #ffd700; }
        .rank-2 { color: #c0c0c0; }
        .rank-3 { color: #cd7f32; }

        .player-name {
            font-weight: bold;
            color: #073545;
        }

        .group-info {
            font-size: 0.8rem;
            color: #666;
            font-style: italic;
        }

        .points {
            font-weight: bold;
            color: #4CAF50;
        }

        .day-points {
            font-weight: bold;
            color: #2196F3;
        }

        .items-count {
            font-weight: bold;
            color: #9C27B0;
        }

        .items-bonus {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .items-number {
            font-weight: bold;
            color: #9C27B0;
        }

        .bonus-points {
            background: #4CAF50;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            white-space: nowrap;
        }

        .day-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .golden-paper-info {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
        }

        .golden-paper-text {
            color: #FFD700;
            font-weight: bold;
        }

        .golden-bonus-points {
            background: #FFD700;
            color: #333;
            padding: 3px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            white-space: nowrap;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }

        @media (max-width: 1200px) {
            .ranking-table {
                font-size: 0.8rem;
            }
            
            .ranking-table th,
            .ranking-table td {
                padding: 8px 4px;
            }
        }

        @media (max-width: 768px) {
            .ranking-table {
                font-size: 0.7rem;
            }
            
            .ranking-table th,
            .ranking-table td {
                padding: 6px 2px;
            }
            
            .player-name {
                font-size: 0.8rem;
            }
            
            .group-info {
                font-size: 0.7rem;
            }
        }

        @media (max-width: 768px) {
            .ranking-title {
                font-size: 2rem;
            }

            .ranking-content {
                margin-top: 40px;
                padding: 20px;
            }

            .buttons-container {
                gap: 15px;
            }

            .game-button {
                padding: 12px 30px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Boutons de classement fixes en haut à gauche -->
    <div class="ranking-buttons-fixed">
        <a href="ranking.php" class="game-button btn-ranking btn-ranking-top">🏆 Classement Équipes</a>
        <a href="ranking-individual.php" class="game-button btn-individual btn-ranking-bottom">👤 Classement Individuel</a>
    </div>

    <div class="container">
        <a href="index.php">
            <img src="assets/img/logo.png" alt="CLUEDO Tak exomind" class="logo">
        </a>

        <h1 class="ranking-title">🏆 Classement Individuel</h1>

        <div class="ranking-content">
            <?php if (empty($players)): ?>
                <div class="no-data">
                    <h3>📊 Aucun joueur trouvé</h3>
                    <p>Il n'y a actuellement aucun joueur dans le système.</p>
                </div>
            <?php else: ?>
                <div style="text-align: center; color: white; font-size: 1.1rem; margin-bottom: 20px;">
                    📊 Nb joueurs (TAK & Exo) : <?php echo count($players); ?> | 
                    ✅ Joueurs activés : <?php echo count(array_filter($players, function($player) { return $player['has_activated']; })); ?>
                </div>

                <table class="ranking-table">
                    <thead>
                        <tr>
                            <th>🏆</th>
                            <th>Points</th>
                            <th>Joueur</th>
                            <th>Personnage & Pôle</th>
                            <th>Jour 1</th>
                            <th>Jour 2</th>
                            <th>Jour 3</th>
                            <th>Objets</th>
                            <th>Total papiers trouvés</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($players as $index => $player): ?>
                            <?php 
                            $rank = $index + 1;
                            $rankClass = '';
                            if ($rank == 1) $rankClass = 'rank-1';
                            elseif ($rank == 2) $rankClass = 'rank-2';
                            elseif ($rank == 3) $rankClass = 'rank-3';
                            ?>
                            <tr>
                                <td class="rank-position <?php echo $rankClass; ?>">
                                    <?php echo $rank; ?>
                                </td>
                                <td class="points">
                                    <?php echo number_format($player['day1_points'] + $player['day2_points'] + $player['day3_points'] + $player['items_bonus_points']); ?>
                                </td>
                                <td class="player-name">
                                    <?php echo formatUserName($player['firstname'], $player['lastname']); ?>
                                    <?php if (!$player['has_activated']): ?>
                                        <br><small style="color: #ff6b35;">⚠️ Non activé</small>
                                    <?php endif; ?>
                                </td>
                                <td class="group-info">
                                    <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                                        <?php if (!empty($player['img_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($player['img_path']); ?>" 
                                                 alt="<?php echo htmlspecialchars($player['group_name']); ?>" 
                                                 style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                        <?php endif; ?>
                                        <div>
                                            <?php echo htmlspecialchars($player['group_name']); ?><br>
                                            <small><?php echo htmlspecialchars($player['pole_name']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="day-points">
                                    <div class="day-content">
                                        <?php echo number_format($player['day1_points']); ?>
                                        <?php if ($player['golden_papers_day1'] > 0): ?>
                                            <div class="golden-paper-info">
                                                <span class="golden-paper-text">Papier en or trouvé</span>
                                                <span class="golden-bonus-points">+<?php echo number_format($player['golden_papers_day1'] * 1000); ?> pts</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="day-points">
                                    <div class="day-content">
                                        <?php echo number_format($player['day2_points']); ?>
                                        <?php if ($player['golden_papers_day2'] > 0): ?>
                                            <div class="golden-paper-info">
                                                <span class="golden-paper-text">Papier en or trouvé</span>
                                                <span class="golden-bonus-points">+<?php echo number_format($player['golden_papers_day2'] * 1000); ?> pts</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="day-points">
                                    <div class="day-content">
                                        <?php echo number_format($player['day3_points']); ?>
                                        <?php if ($player['golden_papers_day3'] > 0): ?>
                                            <div class="golden-paper-info">
                                                <span class="golden-paper-text">Papier en or trouvé</span>
                                                <span class="golden-bonus-points">+<?php echo number_format($player['golden_papers_day3'] * 1000); ?> pts</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="items-count">
                                    <div class="items-bonus">
                                        <span class="items-number"><?php echo $player['items_count']; ?></span>
                                        <?php if ($player['items_count'] > 0): ?>
                                            <span class="bonus-points">+<?php echo number_format($player['items_bonus_points']); ?> pts</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="items-count">
                                    <?php echo $player['total_papers_found']; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Page de classement individuel - pas de boutons de navigation supplémentaires
    </script>
</body>
</html>
