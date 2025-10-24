<?php
header('Content-Type: application/json');

// Connexion à la base de données
require_once __DIR__ . '/db-connection.php';
$dbConnection = getDBConnection();

// Fonction pour calculer le jour du jeu basé sur la date courante
function getGameDay($dbConnection) {
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

// Fonction pour formater la durée de résolution
function formatDuration($timestampStart, $timestampEnd) {
    if (!$timestampStart || !$timestampEnd) {
        return null;
    }
    
    $start = new DateTime($timestampStart);
    $end = new DateTime($timestampEnd);
    $diff = $start->diff($end);
    
    $hours = $diff->h;
    $minutes = $diff->i;
    $seconds = $diff->s;
    
    $result = '';
    
    if ($hours > 0) {
        $result .= $hours . 'h ';
    }
    
    if ($minutes > 0) {
        $result .= $minutes . 'm ';
    }
    
    if ($seconds > 0 || ($hours == 0 && $minutes == 0)) {
        $result .= $seconds . 's';
    }
    
    return trim($result);
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

// Fonction pour calculer les points du papier doré pour une équipe
function calculateGoldenPaperScore($dbConnection, $teamId, $day) {
    if (!$dbConnection) {
        return 0;
    }
    
    try {
        // Vérifier si cette équipe a trouvé le papier doré pour ce jour
        $query = "
            SELECT COUNT(*) as count
            FROM papers_found_user pf
            INNER JOIN users u ON pf.id_player = u.id
            INNER JOIN papers p ON pf.id_paper = p.id
            WHERE p.paper_type = 1 AND pf.id_day = ? AND u.group_id = ?
        ";
        
        $stmt = $dbConnection->prepare($query);
        $stmt->execute([$day, $teamId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si l'équipe a trouvé le papier doré, ajouter 1500 points
        return ($result && $result['count'] > 0) ? 1500 : 0;
        
    } catch (PDOException $e) {
        error_log("Erreur lors du calcul des points du papier doré: " . $e->getMessage());
        return 0;
    }
}

// Calculer le jour du jeu
$gameDay = getGameDay($dbConnection);

// Récupérer le jour sélectionné depuis l'URL (par défaut jour 1)
$selectedDay = isset($_GET['day']) ? (int)$_GET['day'] : 1;
$selectedDay = max(1, min(3, $selectedDay)); // Limiter entre 1 et 3

$response = [
    'success' => false,
    'day' => $selectedDay,
    'selectedDay' => $selectedDay,
    'teams' => []
];

if (!$dbConnection) {
    echo json_encode($response);
    exit;
}

try {
    // Récupérer tous les groupes
    $stmt = $dbConnection->prepare("SELECT id, name, pole_name, color, img_path FROM `groups` ORDER BY id ASC");
    $stmt->execute();
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($teams as $index => $team) {
        // Récupérer les utilisateurs du groupe
        $stmt = $dbConnection->prepare("SELECT id, firstname, lastname, username, email, has_activated FROM `users` WHERE group_id = ? ORDER BY lastname ASC, firstname ASC");
        $stmt->execute([$team['id']]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Pour chaque utilisateur, récupérer le nombre de papiers trouvés (EXCLURE les papiers dorés)
        foreach ($users as $userIndex => $user) {
            // Récupérer le vrai nombre de papiers trouvés depuis papers_found_user (UNIQUEMENT les papiers normaux)
            $stmt = $dbConnection->prepare("
                SELECT COUNT(*) as count 
                FROM `papers_found_user` pf
                INNER JOIN `papers` p ON pf.id_paper = p.id
                WHERE pf.id_player = ? AND pf.id_day = ? AND p.paper_type = 0
            ");
            $stmt->execute([$user['id'], $selectedDay]);
            $papersCount = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $users[$userIndex]['papers_found'] = $papersCount ? (int)$papersCount['count'] : 0;
        }
        
        // Trier les utilisateurs : actifs avec le plus de papiers d'abord, puis inactifs
        usort($users, function($a, $b) {
            // Si l'un est actif et l'autre non, l'actif vient en premier
            if ($a['has_activated'] != $b['has_activated']) {
                return $b['has_activated'] - $a['has_activated']; // Actifs en premier
            }
            // Si les deux ont le même statut d'activation, trier par nombre de papiers (décroissant)
            return $b['papers_found'] - $a['papers_found'];
        });
        
        $teams[$index]['users'] = $users;
        
        // Récupérer les objets associés à cette équipe
        $stmt = $dbConnection->prepare("SELECT id, path, title, subtitle, solved FROM `items` WHERE group_id = ? ORDER BY id ASC");
        $stmt->execute([$team['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $teams[$index]['items'] = $items;
        
        // Récupérer les infos de papiers depuis total_papers_found_group
        $stmt = $dbConnection->prepare("SELECT total_to_found, total_founded, complete FROM `total_papers_found_group` WHERE id_group = ? AND id_day = ?");
        $stmt->execute([$team['id'], $selectedDay]);
        $paperStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($paperStats) {
            $teams[$index]['total_to_found'] = (int)$paperStats['total_to_found'];
            $teams[$index]['papers_found'] = (int)$paperStats['total_founded'];
            $teams[$index]['complete'] = (bool)$paperStats['complete'];
        } else {
            // Valeurs par défaut si pas de données pour ce jour
            $teams[$index]['total_to_found'] = 10;
            $teams[$index]['papers_found'] = 0;
            $teams[$index]['complete'] = false;
        }
        
        // Récupérer le statut de l'énigme depuis la table enigmes avec les timestamps de durée
        $stmt = $dbConnection->prepare("
            SELECT e.status, e.datetime_solved, 
                   esd.timestamp_start, esd.timestamp_end
            FROM `enigmes` e 
            LEFT JOIN `enigm_solutions_durations` esd ON e.id = esd.id_enigm 
            WHERE e.id_group = ? AND e.id_day = ?
        ");
        $stmt->execute([$team['id'], $selectedDay]);
        $enigmaData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($enigmaData) {
            $teams[$index]['enigma_status'] = (int)$enigmaData['status'];
            $teams[$index]['datetime_solved'] = $enigmaData['datetime_solved'];
            $teams[$index]['timestamp_start'] = $enigmaData['timestamp_start'];
            $teams[$index]['timestamp_end'] = $enigmaData['timestamp_end'];
            
            // Calculer la durée de résolution
            $teams[$index]['duration'] = formatDuration($enigmaData['timestamp_start'], $enigmaData['timestamp_end']);
            
            // Calculer le score basé sur la durée de l'énigme
            $enigmaScore = calculateScore($enigmaData['timestamp_start'], $enigmaData['timestamp_end']);
            
            // Ajouter les points du papier doré
            $goldenPaperScore = calculateGoldenPaperScore($dbConnection, $team['id'], $selectedDay);
            
            // Score total = score énigme + points papier doré
            $teams[$index]['score'] = $enigmaScore + $goldenPaperScore;
            $teams[$index]['enigma_score'] = $enigmaScore;
            $teams[$index]['golden_paper_score'] = $goldenPaperScore;
        } else {
            // Valeurs par défaut si pas de données pour ce jour
            $teams[$index]['enigma_status'] = 0;
            $teams[$index]['datetime_solved'] = null;
            $teams[$index]['timestamp_start'] = null;
            $teams[$index]['timestamp_end'] = null;
            $teams[$index]['duration'] = null;
            
            // Même sans énigme résolue, l'équipe peut avoir des points du papier doré
            $goldenPaperScore = calculateGoldenPaperScore($dbConnection, $team['id'], $selectedDay);
            $teams[$index]['score'] = $goldenPaperScore;
            $teams[$index]['enigma_score'] = 0;
            $teams[$index]['golden_paper_score'] = $goldenPaperScore;
        }
    }
    
    // Calculer le classement des équipes basé sur le score
    $scoredTeams = [];
    $unscoredTeams = [];
    
    foreach ($teams as $team) {
        if ($team['score'] > 0) {
            // Équipe qui a des points (énigme résolue OU papier doré trouvé)
            $scoredTeams[] = $team;
        } else {
            // Équipe qui n'a aucun point
            $unscoredTeams[] = $team;
        }
    }
    
    // Trier les équipes avec des points par score décroissant (le plus haut score en premier)
    usort($scoredTeams, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    // Assigner les rangs (1, 2, 3, 4, 5, 6)
    $rank = 1;
    foreach ($scoredTeams as $index => $team) {
        $scoredTeams[$index]['ranking'] = $rank;
        $rank++;
    }
    
    // Les équipes sans points n'ont pas de rang
    foreach ($unscoredTeams as $index => $team) {
        $unscoredTeams[$index]['ranking'] = null;
    }
    
    // Reconstituer la liste des équipes : avec points d'abord (par score), puis sans points
    $teams = array_merge($scoredTeams, $unscoredTeams);
    
    // Vérifier et supprimer les doublons par ID
    $uniqueTeams = [];
    $seenIds = [];
    foreach ($teams as $team) {
        if (!in_array($team['id'], $seenIds)) {
            $uniqueTeams[] = $team;
            $seenIds[] = $team['id'];
        }
    }
    $teams = $uniqueTeams;
    
    $response['success'] = true;
    $response['teams'] = $teams;
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des données: " . $e->getMessage());
    $response['error'] = $e->getMessage();
}

echo json_encode($response);

