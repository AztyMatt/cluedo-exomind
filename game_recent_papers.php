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
    'recent_papers' => []
];

if (!$dbConnection) {
    echo json_encode($response);
    exit;
}

try {
    // Récupérer le jour actuel (par défaut jour 1)
    $currentDay = isset($_GET['day']) ? (int)$_GET['day'] : 1;
    $currentDay = max(1, min(3, $currentDay));
    
    // Récupérer l'ID du joueur connecté depuis la session
    $currentPlayerId = $_SESSION['user_id'] ?? null;
    
    error_log("🔍 Notifications - Joueur connecté ID: " . ($currentPlayerId ?? 'null'));
    
    // Récupérer tous les papiers trouvés dans les 2 dernières minutes pour ce jour
    $stmt = $dbConnection->prepare("
        SELECT 
            pfu.id,
            pfu.id_paper,
            pfu.id_player,
            u.username,
            u.firstname,
            u.lastname,
            g.name as team_name,
            g.pole_name,
            g.color as team_color,
            g.img_path as team_img,
            pfu.created_at,
            TIMESTAMPDIFF(SECOND, pfu.created_at, NOW()) as seconds_ago
        FROM `papers_found_user` pfu
        JOIN `users` u ON pfu.id_player = u.id
        LEFT JOIN `groups` g ON u.group_id = g.id
        WHERE pfu.id_day = ?
        AND pfu.created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        ORDER BY pfu.created_at DESC
    ");
    $stmt->execute([$currentDay]);
    $recentPapers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater les données (en excluant le joueur connecté)
    $formattedPapers = [];
    foreach ($recentPapers as $paper) {
        // Ne pas inclure les papiers trouvés par le joueur lui-même
        if ($currentPlayerId && (int)$paper['id_player'] === (int)$currentPlayerId) {
            error_log("🚫 Notification filtrée - C'est le joueur connecté (ID: " . $paper['id_player'] . ")");
            continue;
        }
        
        error_log("✅ Notification ajoutée - Joueur ID: " . $paper['id_player'] . " ≠ Connecté ID: " . ($currentPlayerId ?? 'null'));
        
        $formattedPapers[] = [
            'id' => (int)$paper['id'],
            'id_paper' => (int)$paper['id_paper'],
            'id_player' => (int)$paper['id_player'],
            'username' => $paper['username'],
            'display_name' => ucfirst(strtolower($paper['firstname'])) . ' ' . strtoupper($paper['lastname']),
            'team_name' => $paper['team_name'],
            'pole_name' => $paper['pole_name'],
            'team_color' => $paper['team_color'],
            'team_img' => $paper['team_img'],
            'seconds_ago' => (int)$paper['seconds_ago'],
            'created_at' => $paper['created_at']
        ];
    }
    
    $response['success'] = true;
    $response['recent_papers'] = $formattedPapers;
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des papiers récents: " . $e->getMessage());
    $response['error'] = $e->getMessage();
}

echo json_encode($response);

