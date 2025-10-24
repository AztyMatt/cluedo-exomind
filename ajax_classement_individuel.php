<?php
// Connexion à la base de données
require_once __DIR__ . '/db-connection.php';
$dbConnection = getDBConnection();

// Vérifier la connexion avant de continuer
if (!$dbConnection) {
    error_log("Erreur critique: Impossible de se connecter à la base de données");
    die(json_encode(['error' => 'Erreur de connexion à la base de données']));
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

// Fonction pour calculer les points avec décrémentation temporelle
function calculatePointsWithTimeDecay($papersData, $goldenPapersData) {
    $dayPoints = [
        'day1' => ['points' => 0, 'papers_count' => 0, 'golden_count' => 0],
        'day2' => ['points' => 0, 'papers_count' => 0, 'golden_count' => 0],
        'day3' => ['points' => 0, 'papers_count' => 0, 'golden_count' => 0]
    ];
    
    // Traiter chaque jour
    for ($day = 1; $day <= 3; $day++) {
        $dayKey = "day{$day}";
        
        // Points des papiers normaux avec décrémentation temporelle
        if (isset($papersData[$day]) && !empty($papersData[$day])) {
            $papers = $papersData[$day];
            $dayPoints[$dayKey]['papers_count'] = count($papers);
            
            // Trier par timestamp croissant
            usort($papers, function($a, $b) {
                return strtotime($a['created_at']) - strtotime($b['created_at']);
            });
            
            $totalPoints = 0;
            $previousTime = null;
            
            foreach ($papers as $index => $paper) {
                $currentTime = strtotime($paper['created_at']);
                
                if ($index === 0) {
                    // Premier papier : toujours 100 points
                    $points = 100;
                } else {
                    // Calculer les minutes écoulées depuis le papier précédent
                    $minutesElapsed = ($currentTime - $previousTime) / 60;
                    $points = max(0, ceil(100 - $minutesElapsed)); // Décrémentation de 1 point par minute, arrondi à l'entier supérieur
                }
                
                $totalPoints += $points;
                $previousTime = $currentTime;
            }
            
            $dayPoints[$dayKey]['points'] = $totalPoints;
        }
        
        // Points des papiers en or (1500 points chacun)
        if (isset($goldenPapersData[$day]) && !empty($goldenPapersData[$day])) {
            $goldenCount = count($goldenPapersData[$day]);
            $dayPoints[$dayKey]['golden_count'] = $goldenCount;
            $dayPoints[$dayKey]['points'] += intval($goldenCount * 1500);
        }
    }
    
    return $dayPoints;
}

// Calculer le jour du jeu
$gameDay = getGameDay($dbConnection);

// Récupérer tous les utilisateurs avec leurs papiers trouvés par jour (avec timestamps)
$simpleQuery = "SELECT u.id, u.firstname, u.lastname, u.has_activated, g.name as group_name, g.pole_name, g.img_path,
                COALESCE(items_count.total_items, 0) as total_items_found
                FROM users u 
                LEFT JOIN `groups` g ON u.group_id = g.id 
                LEFT JOIN (
                    SELECT id_solved_user, COUNT(*) as total_items 
                    FROM items 
                    WHERE solved = 1
                    GROUP BY id_solved_user
                ) items_count ON u.id = items_count.id_solved_user
                ORDER BY u.lastname ASC, u.firstname ASC";

try {
    $stmt = $dbConnection->prepare($simpleQuery);
    $stmt->execute();
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pour chaque joueur, récupérer ses papiers trouvés avec timestamps
    foreach ($players as &$player) {
        $playerId = $player['id'];
        
        // Récupérer les papiers normaux trouvés par jour avec timestamps
        $papersQuery = "SELECT pf.id_day, pf.created_at 
                       FROM papers_found_user pf 
                       INNER JOIN papers p ON pf.id_paper = p.id 
                       WHERE pf.id_player = ? AND p.paper_type = 0 
                       ORDER BY pf.id_day, pf.created_at";
        $papersStmt = $dbConnection->prepare($papersQuery);
        $papersStmt->execute([$playerId]);
        $papersData = $papersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organiser les papiers par jour
        $papersByDay = [];
        foreach ($papersData as $paper) {
            $day = $paper['id_day'];
            if (!isset($papersByDay[$day])) {
                $papersByDay[$day] = [];
            }
            $papersByDay[$day][] = $paper;
        }
        
        // Récupérer les papiers en or trouvés par jour
        $goldenQuery = "SELECT pf.id_day, pf.created_at 
                       FROM papers_found_user pf 
                       INNER JOIN papers p ON pf.id_paper = p.id 
                       WHERE pf.id_player = ? AND p.paper_type = 1 
                       ORDER BY pf.id_day, pf.created_at";
        $goldenStmt = $dbConnection->prepare($goldenQuery);
        $goldenStmt->execute([$playerId]);
        $goldenData = $goldenStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organiser les papiers en or par jour
        $goldenByDay = [];
        foreach ($goldenData as $golden) {
            $day = $golden['id_day'];
            if (!isset($goldenByDay[$day])) {
                $goldenByDay[$day] = [];
            }
            $goldenByDay[$day][] = $golden;
        }
        
        // Calculer les points avec décrémentation temporelle
        $dayPoints = calculatePointsWithTimeDecay($papersByDay, $goldenByDay);
        
        // Assigner les données calculées au joueur
        $player['day1_points'] = $dayPoints['day1']['points'];
        $player['day2_points'] = $dayPoints['day2']['points'];
        $player['day3_points'] = $dayPoints['day3']['points'];
        $player['day1_papers_count'] = $dayPoints['day1']['papers_count'];
        $player['day2_papers_count'] = $dayPoints['day2']['papers_count'];
        $player['day3_papers_count'] = $dayPoints['day3']['papers_count'];
        $player['golden_papers_day1'] = $dayPoints['day1']['golden_count'];
        $player['golden_papers_day2'] = $dayPoints['day2']['golden_count'];
        $player['golden_papers_day3'] = $dayPoints['day3']['golden_count'];
        $player['golden_papers_total'] = $dayPoints['day1']['golden_count'] + $dayPoints['day2']['golden_count'] + $dayPoints['day3']['golden_count'];
        $player['total_papers_found'] = $dayPoints['day1']['papers_count'] + $dayPoints['day2']['papers_count'] + $dayPoints['day3']['papers_count'];
        $player['items_count'] = $player['total_items_found'];
        $player['items_bonus_points'] = intval($player['total_items_found'] * 500); // 500 points par objet trouvé
        
        // Calculer le total des points
        $player['total_points'] = $player['day1_points'] + $player['day2_points'] + $player['day3_points'] + $player['items_bonus_points'];
        
        // Formater le nom
        $player['formatted_name'] = formatUserName($player['firstname'], $player['lastname']);
    }
    
    // Trier les joueurs par points totaux décroissants
    usort($players, function($a, $b) {
        return $b['total_points'] - $a['total_points'];
    });
    
    // Calculer les classements en gérant les ex-aequo
    $currentRank = 1;
    $previousPoints = null;
    
    foreach ($players as $index => &$player) {
        $currentPoints = $player['total_points'];
        
        // Si c'est le premier joueur ou si les points sont différents du joueur précédent
        if ($previousPoints === null || $currentPoints != $previousPoints) {
            $currentRank = $index + 1;
        }
        
        $player['rank'] = $currentRank;
        $previousPoints = $currentPoints;
    }
    
    // Retourner les données en JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'players' => $players,
        'total_players' => count($players),
        'activated_players' => count(array_filter($players, function($player) { return $player['has_activated']; })),
        'game_day' => $gameDay
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur PDO dans ajax_classement_individuel.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la récupération des données'
    ]);
}
?>
