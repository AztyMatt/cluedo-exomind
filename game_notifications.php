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
    'papers' => []
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
    
    // Récupérer tous les papiers trouvés pour ce jour (EXCLURE les papiers dorés ET le joueur connecté)
    $stmt = $dbConnection->prepare("
        SELECT 
            pfu.id,
            pfu.id_paper,
            pfu.id_player,
            pfu.id_day,
            u.username,
            u.firstname,
            u.lastname,
            g.name as team_name,
            g.pole_name,
            g.color as team_color,
            g.img_path as team_img,
            pfu.created_at as datetime,
            p.paper_type
        FROM `papers_found_user` pfu
        JOIN `users` u ON pfu.id_player = u.id
        LEFT JOIN `groups` g ON u.group_id = g.id
        JOIN `papers` p ON pfu.id_paper = p.id
        WHERE pfu.id_day = ?
        AND p.paper_type = 0
        AND pfu.id_player != ?
        ORDER BY pfu.created_at DESC
    ");
    $stmt->execute([$currentDay, $currentPlayerId]);
    $recentPapers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater les données (exclure le joueur connecté pour les notifications)
    $formattedPapers = [];
    foreach ($recentPapers as $paper) {
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
            'datetime' => $paper['datetime'],
            'paper_type' => (int)$paper['paper_type'],
            'id_day' => (int)$paper['id_day']
        ];
    }
    
    $response['success'] = true;
    $response['papers'] = $formattedPapers;
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des notifications: " . $e->getMessage());
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
