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
        
        // Pour chaque utilisateur, récupérer le nombre de papiers trouvés
        foreach ($users as $userIndex => $user) {
            // Récupérer le vrai nombre de papiers trouvés depuis papers_found_user
            $stmt = $dbConnection->prepare("SELECT COUNT(*) as count FROM `papers_found_user` WHERE id_player = ? AND id_day = ?");
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
            SELECT e.status, e.datetime_solved, e.enigm_solution, 
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
            $teams[$index]['enigma_solution'] = $enigmaData['enigm_solution'];
            $teams[$index]['timestamp_start'] = $enigmaData['timestamp_start'];
            $teams[$index]['timestamp_end'] = $enigmaData['timestamp_end'];
            
            // Calculer la durée de résolution
            $teams[$index]['duration'] = formatDuration($enigmaData['timestamp_start'], $enigmaData['timestamp_end']);
        } else {
            // Valeurs par défaut si pas de données pour ce jour
            $teams[$index]['enigma_status'] = 0;
            $teams[$index]['datetime_solved'] = null;
            $teams[$index]['enigma_solution'] = '';
            $teams[$index]['timestamp_start'] = null;
            $teams[$index]['timestamp_end'] = null;
            $teams[$index]['duration'] = null;
        }
    }
    
    // Calculer le classement des équipes basé sur datetime_solved
    $solvedTeams = [];
    $unsolvedTeams = [];
    
    foreach ($teams as $team) {
        if ($team['enigma_status'] >= 2 && $team['datetime_solved']) {
            // Équipe qui a résolu l'énigme (statut 2 ou plus)
            $solvedTeams[] = $team;
        } else {
            // Équipe qui n'a pas résolu l'énigme
            $unsolvedTeams[] = $team;
        }
    }
    
    // Trier les équipes résolues par datetime_solved (le plus tôt en premier)
    usort($solvedTeams, function($a, $b) {
        return strtotime($a['datetime_solved']) - strtotime($b['datetime_solved']);
    });
    
    // Assigner les rangs (1, 2, 3, 4, 5, 6)
    $rank = 1;
    foreach ($solvedTeams as $index => $team) {
        $solvedTeams[$index]['ranking'] = $rank;
        $rank++;
    }
    
    // Les équipes non résolues n'ont pas de rang
    foreach ($unsolvedTeams as $index => $team) {
        $unsolvedTeams[$index]['ranking'] = null;
    }
    
    // Reconstituer la liste des équipes : résolues d'abord (par ordre de résolution), puis non résolues
    $teams = array_merge($solvedTeams, $unsolvedTeams);
    
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

