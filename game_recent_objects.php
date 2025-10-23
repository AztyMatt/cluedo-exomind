<?php
header('Content-Type: application/json');

// Démarrer la session pour accéder à l'ID du joueur
session_start([
    'cookie_lifetime' => 86400 * 7,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

// Connexion à la base de données
require_once __DIR__ . '/db-connection.php';
$dbConnection = getDBConnection();

$response = [
    'success' => false,
    'recent_objects' => []
];

if (!$dbConnection) {
    echo json_encode($response);
    exit;
}

try {
    // Récupérer l'ID du joueur connecté depuis la session
    $currentPlayerId = $_SESSION['user_id'] ?? null;
    
    error_log("🔍 Notifications objets - Joueur connecté ID: " . ($currentPlayerId ?? 'null'));
    
    // Récupérer tous les objets résolus dans les 2 dernières minutes
    $stmt = $dbConnection->prepare("
        SELECT 
            i.id,
            i.title,
            i.subtitle,
            i.solved_title,
            i.id_solved_user,
            u.username,
            u.firstname,
            u.lastname,
            g.name as team_name,
            g.pole_name,
            g.color as team_color,
            g.img_path as team_img,
            i.datetime_solved,
            TIMESTAMPDIFF(SECOND, i.datetime_solved, NOW()) as seconds_ago
        FROM `items` i
        JOIN `users` u ON i.id_solved_user = u.id
        LEFT JOIN `groups` g ON u.group_id = g.id
        WHERE i.solved = 1
        AND i.datetime_solved >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        ORDER BY i.datetime_solved DESC
    ");
    $stmt->execute();
    $recentObjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater les données (en excluant le joueur connecté)
    $formattedObjects = [];
    foreach ($recentObjects as $object) {
        // Ne pas inclure les objets résolus par le joueur lui-même
        if ($currentPlayerId && (int)$object['id_solved_user'] === (int)$currentPlayerId) {
            error_log("🚫 Notification objet filtrée - C'est le joueur connecté (ID: " . $object['id_solved_user'] . ")");
            continue;
        }
        
        error_log("✅ Notification objet ajoutée - Joueur ID: " . $object['id_solved_user'] . " ≠ Connecté ID: " . ($currentPlayerId ?? 'null'));
        
        $formattedObjects[] = [
            'id' => (int)$object['id'],
            'title' => $object['title'],
            'subtitle' => $object['subtitle'],
            'solved_title' => $object['solved_title'],
            'id_solved_user' => (int)$object['id_solved_user'],
            'username' => $object['username'],
            'display_name' => ucfirst(strtolower($object['firstname'])) . ' ' . strtoupper($object['lastname']),
            'team_name' => $object['team_name'],
            'pole_name' => $object['pole_name'],
            'team_color' => $object['team_color'],
            'team_img' => $object['team_img'],
            'seconds_ago' => (int)$object['seconds_ago'],
            'datetime_solved' => $object['datetime_solved']
        ];
    }
    
    $response['success'] = true;
    $response['recent_objects'] = $formattedObjects;
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des objets récents: " . $e->getMessage());
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>
