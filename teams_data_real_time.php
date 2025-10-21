<?php
header('Content-Type: application/json');

// Connexion à la base de données
require_once __DIR__ . '/db-connection.php';
$dbConnection = getDBConnection();

// Récupérer le jour sélectionné
$selectedDay = isset($_GET['day']) ? (int)$_GET['day'] : 1;
$selectedDay = max(1, min(3, $selectedDay)); // Limiter entre 1 et 3

$response = [
    'success' => false,
    'day' => $selectedDay,
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
        $stmt = $dbConnection->prepare("SELECT status FROM `enigmes` WHERE id_group = ? AND id_day = ?");
        $stmt->execute([$team['id'], $selectedDay]);
        $enigmaData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($enigmaData) {
            $team['enigma_status'] = (int)$enigmaData['status'];
        } else {
            $team['enigma_status'] = 0;
        }
    }
    
    $response['success'] = true;
    $response['teams'] = $teams;
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des données: " . $e->getMessage());
    $response['error'] = $e->getMessage();
}

echo json_encode($response);

