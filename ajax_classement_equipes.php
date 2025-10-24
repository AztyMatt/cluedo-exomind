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
    
    // Pénalité : -3 points par minute écoulée
    $penaltyPerMinute = 3;
    $penalty = $totalMinutes * $penaltyPerMinute;
    
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
                
                // Récupérer les papiers dorés trouvés par l'équipe pour ce jour
                $stmt = $dbConnection->prepare("
                    SELECT COUNT(*) as golden_papers_count
                    FROM papers_found_user pf 
                    INNER JOIN papers p ON pf.id_paper = p.id 
                    INNER JOIN users u ON pf.id_player = u.id
                    WHERE u.group_id = ? AND p.paper_type = 1 AND pf.id_day = ?
                ");
                $stmt->execute([$team['id'], $day]);
                $goldenResult = $stmt->fetch(PDO::FETCH_ASSOC);
                $team["day_{$day}_golden_papers"] = (int)$goldenResult['golden_papers_count'];
                
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
            
            // Calculer le total des points (objets + énigmes + papiers dorés)
            $totalPoints = $team['solved_items_count'] * 500; // Points des objets
            $totalPoints += $team['day_1_enigma_score'] + $team['day_2_enigma_score'] + $team['day_3_enigma_score']; // Points des énigmes
            $totalPoints += ($team['day_1_golden_papers'] + $team['day_2_golden_papers'] + $team['day_3_golden_papers']) * 1500; // Points des papiers dorés
            $team['total_points'] = $totalPoints;
            
            $teamsWithData[] = $team;
        }
        
        // Utiliser les équipes avec données
        $teams = $teamsWithData;
        
        // Trier les équipes par total de points décroissant (objets + énigmes)
        usort($teams, function($a, $b) {
            return $b['total_points'] - $a['total_points'];
        });
        
        // Calculer les classements en gérant les ex-aequo et les équipes à 0 points
        $currentRank = 1;
        $previousPoints = null;
        
        foreach ($teams as $index => &$team) {
            $currentPoints = $team['total_points'];
            
            // Si l'équipe a 0 points, elle n'a pas de classement numérique
            if ($currentPoints <= 0) {
                $team['rank'] = '-';
            } else {
                // Si c'est la première équipe avec des points ou si les points sont différents de l'équipe précédente
                if ($previousPoints === null || $currentPoints != $previousPoints) {
                    $currentRank = $index + 1;
                }
                
                $team['rank'] = $currentRank;
                $previousPoints = $currentPoints;
            }
        }
        
        // Retourner les données en JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'teams' => $teams,
            'total_teams' => count($teams),
            'game_day' => $gameDay
        ]);
        
    } catch (PDOException $e) {
        error_log("Erreur PDO dans ajax_classement_equipes.php: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Erreur lors de la récupération des données'
        ]);
    }
}
?>
