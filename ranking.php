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

// Fonction pour calculer le score basé sur la durée de résolution
function calculateScore($timestampStart, $timestampEnd) {
    if (!$timestampStart || !$timestampEnd) {
        return 0; // Pas de score si pas résolu
    }
    
    $start = new DateTime($timestampStart);
    $end = new DateTime($timestampEnd);
    $diff = $start->diff($end);
    
    // Calculer la durée totale en minutes
    $totalMinutes = ($diff->h * 60) + $diff->i + ($diff->s / 60);
    
    // Score de base : 2000 points
    $baseScore = 2000;
    
    // Pénalité : -100 points par tranche de 15 minutes
    $penaltyPer15Minutes = 100;
    $penaltyMinutes = floor($totalMinutes / 15) * 15; // Arrondir à la tranche de 15 minutes
    $penalty = ($penaltyMinutes / 15) * $penaltyPer15Minutes;
    
    // Calculer le score final
    $finalScore = $baseScore - $penalty;
    
    // Score minimum de 0
    return max(0, $finalScore);
}

// Calculer le jour du jeu
$gameDay = getGameDay($dbConnection);

// Récupérer tous les groupes avec leurs données depuis la base de données
$teams = [];
if ($dbConnection) {
    try {
        $stmt = $dbConnection->prepare("SELECT id, name, pole_name, color, img_path FROM `groups` ORDER BY id ASC");
        $stmt->execute();
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: vérifier les équipes chargées au début
        error_log("Équipes chargées au début: " . count($teams));
        foreach ($teams as $team) {
            error_log("- " . $team['name'] . " (ID: " . $team['id'] . ")");
        }
        
        // Récupérer les données supplémentaires pour chaque groupe
        $teamsWithData = [];
        foreach ($teams as $team) {
            // Récupérer les objets associés à cette équipe
            $stmt = $dbConnection->prepare("SELECT id, path, title, subtitle, solved FROM `items` WHERE group_id = ? ORDER BY id ASC");
            $stmt->execute([$team['id']]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $team['items'] = $items;
            
            // Compter les objets résolus
            $solvedItemsCount = 0;
            foreach ($items as $item) {
                if ($item['solved']) $solvedItemsCount++;
            }
            $team['solved_items_count'] = $solvedItemsCount;
            $team['total_items_count'] = count($items);
            
            // Récupérer les membres de l'équipe
            $stmt = $dbConnection->prepare("SELECT firstname, lastname FROM `users` WHERE group_id = ? ORDER BY firstname ASC");
            $stmt->execute([$team['id']]);
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Créer la liste des membres avec des virgules
            $team['members'] = [];
            foreach ($members as $member) {
                $team['members'][] = $member['firstname'] . ' ' . $member['lastname'];
            }
            $team['members_list'] = implode(', ', $team['members']);
            
            // Récupérer les données pour chaque jour (1, 2, 3)
            for ($day = 1; $day <= 3; $day++) {
                // Récupérer les infos de papiers depuis total_papers_found_group
                $stmt = $dbConnection->prepare("SELECT total_to_found, total_founded, complete FROM `total_papers_found_group` WHERE id_group = ? AND id_day = ?");
                $stmt->execute([$team['id'], $day]);
                $paperStats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($paperStats) {
                    $team["day_{$day}_papers_found"] = $paperStats['total_founded'];
                    $team["day_{$day}_total_papers"] = $paperStats['total_to_found'];
                    $team["day_{$day}_complete"] = $paperStats['complete'];
                } else {
                    $team["day_{$day}_papers_found"] = 0;
                    $team["day_{$day}_total_papers"] = 10; // Valeur par défaut
                    $team["day_{$day}_complete"] = false;
                }
                
                // Récupérer le statut de l'énigme pour chaque jour avec les timestamps
                $stmt = $dbConnection->prepare("
                    SELECT e.status, e.datetime_solved, 
                           esd.timestamp_start, esd.timestamp_end
                    FROM `enigmes` e 
                    LEFT JOIN `enigm_solutions_durations` esd ON e.id = esd.id_enigm 
                    WHERE e.id_group = ? AND e.id_day = ?
                ");
                $stmt->execute([$team['id'], $day]);
                $enigmaData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($enigmaData) {
                    $team["day_{$day}_enigma_status"] = (int)$enigmaData['status'];
                    $team["day_{$day}_datetime_solved"] = $enigmaData['datetime_solved'];
                    $team["day_{$day}_timestamp_start"] = $enigmaData['timestamp_start'];
                    $team["day_{$day}_timestamp_end"] = $enigmaData['timestamp_end'];
                    
                    // Calculer le score basé sur la durée
                    $team["day_{$day}_enigma_score"] = calculateScore($enigmaData['timestamp_start'], $enigmaData['timestamp_end']);
                } else {
                    $team["day_{$day}_enigma_status"] = 0;
                    $team["day_{$day}_datetime_solved"] = null;
                    $team["day_{$day}_timestamp_start"] = null;
                    $team["day_{$day}_timestamp_end"] = null;
                    $team["day_{$day}_enigma_score"] = 0;
                }
            }
            
            // Calculer le total des points (objets + énigmes)
            $totalPoints = $team['solved_items_count'] * 500; // Points des objets
            $totalPoints += $team['day_1_enigma_score'] + $team['day_2_enigma_score'] + $team['day_3_enigma_score']; // Points des énigmes
            $team['total_points'] = $totalPoints;
            
            $teamsWithData[] = $team;
        }
        
        // Utiliser les équipes avec données
        $teams = $teamsWithData;
        
        // Trier les équipes par total de points décroissant (objets + énigmes)
        usort($teams, function($a, $b) {
            return $b['total_points'] - $a['total_points'];
        });
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des équipes: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cluedo - Classement</title>
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
        }

        .btn-rules:hover {
            background: #f4d03f;
        }

        .btn-ranking {
            background: #ff6b35;
            color: white;
        }

        .btn-ranking:hover {
            background: #e55a2b;
        }

        .btn-play {
            background: #073545;
            color: white;
        }

        .btn-play:hover {
            background: #0a4a5e;
        }


        .ranking-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .ranking-table th {
            background: #2a2a2a;
            color: white;
            padding: 15px 10px;
            text-align: center;
            font-weight: bold;
            font-size: 1rem;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
        }

        .ranking-table td {
            padding: 12px 10px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            vertical-align: middle;
            height: 100%;
            display: table-cell;
        }

        .ranking-table tr {
            background-color: #666 !important;
        }

        .ranking-table tr:hover td {
            opacity: 0.8;
        }

        .team-name-cell {
            text-align: left;
            padding: 8px 10px;
            max-width: 650px;
            width: 650px;
        }

        .team-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .team-character-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: contain;
            background-color: rgba(255, 255, 255, 0.1);
        }

        .team-details {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .team-character-name {
            font-weight: bold;
            color: #f093fb;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
            font-size: 0.9rem;
            text-align: left;
        }

        .team-pole-name {
            color: #bbb;
            font-size: 0.8rem;
            font-style: italic;
            text-align: left;
        }

        .team-members-list {
            color: #ddd;
            font-size: 0.75rem;
            margin-top: 2px;
            line-height: 1.2;
            text-align: left;
        }

        .points-cell {
            font-weight: bold;
            font-size: 1.1rem;
            color: #ff6b35;
        }

        .objects-cell {
            text-align: left;
            padding: 5px 10px;
        }

        .object-item {
            display: flex;
            align-items: center;
            margin-bottom: 3px;
            font-size: 0.85rem;
        }

        .object-item:last-child {
            margin-bottom: 0;
        }

        .object-icon {
            margin-right: 6px;
            font-size: 0.9rem;
        }

        .object-icon.found {
            color: #4CAF50;
        }

        .object-icon.not-found {
            color: #f44336;
        }

        .object-miniature {
            width: 30px;
            height: 30px;
            margin-right: 8px;
            border-radius: 5px;
            object-fit: cover;
        }

        .object-name {
            color: #eee;
            font-size: 0.8rem;
        }

        .object-points {
            margin-left: 4px;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: bold;
            min-width: 40px;
            text-align: center;
        }

        .object-points.found {
            background: rgba(76, 175, 80, 0.3);
            color: #4CAF50;
            border: 1px solid rgba(76, 175, 80, 0.5);
        }

        .object-points.not-found {
            background: rgba(244, 67, 54, 0.3);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.5);
        }

        .day-cell {
            font-size: 0.9rem;
        }

        .day-enigma-status {
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: bold;
            text-align: center;
        }

        .day-enigma-text {
            font-size: 0.8rem;
            margin-bottom: 4px;
            text-align: center;
        }

        .day-enigma-text.resolved {
            color: #4CAF50;
            font-weight: bold;
        }

        .day-enigma-text.not-resolved {
            color: #f44336;
            font-weight: bold;
        }

        .day-points {
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: bold;
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            color: #ff6b35;
        }

        .day-enigma-status.resolved {
            background: rgba(76, 175, 80, 0.3);
            color: #4CAF50;
            border: 1px solid rgba(76, 175, 80, 0.5);
        }

        .day-enigma-status.not-resolved {
            background: rgba(244, 67, 54, 0.3);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.5);
        }

        .day-complete {
            color: #4CAF50;
            font-weight: bold;
        }

        .day-incomplete {
            color: #ff9800;
        }

        .day-not-started {
            color: #f44336;
        }

        .rank-cell {
            font-weight: bold;
            font-size: 1rem;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }


        .rank-1 { 
            background: #FFD700; 
            color: white; 
            border: 3px solid #FFA500;
        }
        .rank-2 { 
            background: #C0C0C0; 
            color: #333; 
            border: 3px solid #A0A0A0;
        }
        .rank-3 { 
            background: #CD7F32; 
            color: white; 
            border: 3px solid #8B4513;
        }
        .rank-4, .rank-5, .rank-6 { 
            background: #333; 
            color: white; 
            border: 3px solid #555;
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
    <div class="container">
        <a href="index.php">
            <img src="assets/img/logo.png" alt="CLUEDO Tak exomind" class="logo">
        </a>

        <div class="buttons-container">
            <button id="rulesBtn" class="game-button btn-rules">Règles du jeu</button>
            <a href="ranking.php" class="game-button btn-ranking">🏆 Classement</a>
            <a href="game.php" class="game-button btn-play">Jouer</a>
        </div>

        <?php if (!empty($teams)): ?>
            <table class="ranking-table">
                <thead>
                    <tr>
                        <th>Classement</th>
                        <th>Points</th>
                        <th>Équipe</th>
                        <th>Objets</th>
                        <th>Jour 1</th>
                        <th>Jour 2</th>
                        <th>Jour 3</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teams as $index => $team): ?>
                        <tr>
                            <td class="rank-cell rank-<?= $index + 1 ?>"><?= $index + 1 ?></td>
                            <td class="points-cell"><?= $team['total_points'] ?> pts</td>
                            <td class="team-name-cell">
                                <div class="team-info">
                                    <?php if (!empty($team['img_path']) && file_exists($team['img_path'])): ?>
                                        <img src="<?= htmlspecialchars($team['img_path']) ?>" 
                                             alt="<?= htmlspecialchars($team['name']) ?>" 
                                             class="team-character-image">
                                    <?php else: ?>
                                        <div class="team-character-image" style="background: <?= htmlspecialchars($team['color'] ?? '#888') ?>; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                                            🎭
                                        </div>
                                    <?php endif; ?>
                                    <div class="team-details">
                                        <div class="team-character-name"><?= htmlspecialchars($team['name']) ?></div>
                                        <div class="team-pole-name"><?= htmlspecialchars($team['pole_name']) ?></div>
                                        <?php if (!empty($team['members_list'])): ?>
                                            <div class="team-members-list"><?= htmlspecialchars($team['members_list']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="objects-cell">
                                <?php if (!empty($team['items'])): ?>
                                    <?php foreach ($team['items'] as $item): ?>
                                        <div class="object-item">
                                            <span class="object-icon <?= $item['solved'] ? 'found' : 'not-found' ?>">
                                                <?= $item['solved'] ? '✓' : '✗' ?>
                                            </span>
                                            <img src="<?= htmlspecialchars($item['path']) ?>" 
                                                 alt="<?= htmlspecialchars($item['title']) ?>" 
                                                 class="object-miniature"
                                                 title="<?= htmlspecialchars($item['title']) ?> - <?= htmlspecialchars($item['subtitle']) ?>">
                                            <span class="object-name"><?= htmlspecialchars($item['title']) ?></span>
                                            <span class="object-points <?= $item['solved'] ? 'found' : 'not-found' ?>">
                                                <?= $item['solved'] ? '500 pts' : '0 pts' ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color: #666; font-style: italic;">Aucun objet</span>
                                <?php endif; ?>
                            </td>
                            <td class="day-cell">
                                <div class="day-enigma-text <?= $team['day_1_enigma_status'] == 2 ? 'resolved' : 'not-resolved' ?>">
                                    <?php if ($team['day_1_enigma_status'] == 2): ?>
                                        Énigme résolue
                                    <?php else: ?>
                                        Énigme non résolue
                                    <?php endif; ?>
                                </div>
                                <div class="day-points">
                                    <?php if ($team['day_1_enigma_status'] == 2): ?>
                                        <?= $team['day_1_enigma_score'] ?> pts
                                    <?php else: ?>
                                        0 pts
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="day-cell">
                                <div class="day-enigma-text <?= $team['day_2_enigma_status'] == 2 ? 'resolved' : 'not-resolved' ?>">
                                    <?php if ($team['day_2_enigma_status'] == 2): ?>
                                        Énigme résolue
                                    <?php else: ?>
                                        Énigme non résolue
                                    <?php endif; ?>
                                </div>
                                <div class="day-points">
                                    <?php if ($team['day_2_enigma_status'] == 2): ?>
                                        <?= $team['day_2_enigma_score'] ?> pts
                                    <?php else: ?>
                                        0 pts
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="day-cell">
                                <div class="day-enigma-text <?= $team['day_3_enigma_status'] == 2 ? 'resolved' : 'not-resolved' ?>">
                                    <?php if ($team['day_3_enigma_status'] == 2): ?>
                                        Énigme résolue
                                    <?php else: ?>
                                        Énigme non résolue
                                    <?php endif; ?>
                                </div>
                                <div class="day-points">
                                    <?php if ($team['day_3_enigma_status'] == 2): ?>
                                        <?= $team['day_3_enigma_score'] ?> pts
                                    <?php else: ?>
                                        0 pts
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="coming-soon">
                🚧 Aucune équipe trouvée - Vérifiez la base de données ! 🚧
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Gestion de la modale des règles (même code que teams.php)
        const rulesBtn = document.getElementById('rulesBtn');
        
        // Ouvrir la modale des règles
        rulesBtn.addEventListener('click', () => {
            // Pour l'instant, rediriger vers teams.php pour voir les règles
            window.location.href = 'teams.php';
        });
    </script>
</body>
</html>
