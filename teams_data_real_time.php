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

// Calculer le jour du jeu
$gameDay = getGameDay($dbConnection);

// Récupérer le jour sélectionné depuis l'URL (par défaut jour 1)
$selectedDay = isset($_GET['day']) ? (int)$_GET['day'] : 1;
$selectedDay = max(1, min(3, $selectedDay)); // Limiter entre 1 et 3

$response = [
    'success' => false,
    'day' => $gameDay,
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
    
    foreach ($teams as &$team) {
        // Récupérer les utilisateurs du groupe
        $stmt = $dbConnection->prepare("SELECT id, firstname, lastname, username, email, has_activated FROM `users` WHERE group_id = ? ORDER BY lastname ASC, firstname ASC");
        $stmt->execute([$team['id']]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Pour chaque utilisateur, récupérer le nombre de papiers trouvés
        foreach ($users as &$user) {
            // Récupérer le vrai nombre de papiers trouvés depuis papers_found_user
            $stmt = $dbConnection->prepare("SELECT COUNT(*) as count FROM `papers_found_user` WHERE id_player = ? AND id_day = ?");
            $stmt->execute([$user['id'], $selectedDay]);
            $papersCount = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $user['papers_found'] = $papersCount ? (int)$papersCount['count'] : 0;
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
        
        $team['users'] = $users;
        
        // Récupérer les infos de papiers depuis total_papers_found_group
        $stmt = $dbConnection->prepare("SELECT total_to_found, total_founded, complete FROM `total_papers_found_group` WHERE id_group = ? AND id_day = ?");
        $stmt->execute([$team['id'], $selectedDay]);
        $paperStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($paperStats) {
            $team['total_to_found'] = (int)$paperStats['total_to_found'];
            $team['papers_found'] = (int)$paperStats['total_founded'];
            $team['complete'] = (bool)$paperStats['complete'];
        } else {
            $team['total_to_found'] = 10;
            $team['papers_found'] = 0;
            $team['complete'] = false;
        }
        
        // Récupérer le statut de l'énigme depuis la table enigmes
        $stmt = $dbConnection->prepare("SELECT status, datetime_solved, enigm_solution FROM `enigmes` WHERE id_group = ? AND id_day = ?");
        $stmt->execute([$team['id'], $selectedDay]);
        $enigmaData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($enigmaData) {
            $team['enigma_status'] = (int)$enigmaData['status'];
            $team['datetime_solved'] = $enigmaData['datetime_solved'];
            $team['enigma_solution'] = $enigmaData['enigm_solution'];
        } else {
            $team['enigma_status'] = 0;
            $team['datetime_solved'] = null;
            $team['enigma_solution'] = '';
        }
    }
    
    // Calculer le classement des équipes basé sur datetime_solved
    $solvedTeams = [];
    $unsolvedTeams = [];
    
    foreach ($teams as $team) {
        if ($team['enigma_status'] == 2 && $team['datetime_solved']) {
            // Équipe qui a résolu l'énigme
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
    foreach ($solvedTeams as &$team) {
        $team['ranking'] = $rank;
        $rank++;
    }
    
    // Les équipes non résolues n'ont pas de rang
    foreach ($unsolvedTeams as &$team) {
        $team['ranking'] = null;
    }
    
    // Reconstituer la liste des équipes : résolues d'abord (par ordre de résolution), puis non résolues
    $teams = array_merge($solvedTeams, $unsolvedTeams);
    
    $response['success'] = true;
    $response['teams'] = $teams;
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des données: " . $e->getMessage());
    $response['error'] = $e->getMessage();
}

echo json_encode($response);

