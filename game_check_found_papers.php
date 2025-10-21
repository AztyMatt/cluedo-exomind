<?php
header('Content-Type: application/json');

// Démarrer la session
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
    'found_papers' => []
];

if (!$dbConnection) {
    echo json_encode($response);
    exit;
}

try {
    // Récupérer le jour actuel (par défaut jour 1)
    $currentDay = isset($_GET['day']) ? (int)$_GET['day'] : 1;
    $currentDay = max(1, min(3, $currentDay));
    
    // Récupérer tous les papiers trouvés pour ce jour avec les infos des joueurs
    $stmt = $dbConnection->prepare("
        SELECT 
            pfu.id_paper,
            u.username,
            u.firstname,
            u.lastname,
            g.name as team_name,
            g.color as team_color,
            g.img_path as team_img,
            g.pole_name as team_pole,
            pfu.created_at
        FROM `papers_found_user` pfu
        JOIN `users` u ON pfu.id_player = u.id
        LEFT JOIN `groups` g ON u.group_id = g.id
        WHERE pfu.id_day = ?
        ORDER BY pfu.created_at DESC
    ");
    $stmt->execute([$currentDay]);
    $foundPapers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater les données
    $formattedPapers = [];
    foreach ($foundPapers as $paper) {
        $datetime = strtotime($paper['created_at']);
        $formattedDateTime = date('d/m/Y', $datetime) . ' à ' . date('H:i:s', $datetime);
        
        $formattedPapers[] = [
            'id_paper' => (int)$paper['id_paper'],
            'found_by' => $paper['username'],
            'found_by_display' => ucfirst(strtolower($paper['firstname'])) . ' ' . strtoupper($paper['lastname']),
            'found_at' => $formattedDateTime,
            'team_name' => $paper['team_name'],
            'team_color' => $paper['team_color'],
            'team_img' => $paper['team_img'],
            'team_pole' => $paper['team_pole']
        ];
    }
    
    $response['success'] = true;
    $response['found_papers'] = $formattedPapers;
    
} catch (PDOException $e) {
    error_log("Erreur lors de la vérification des papiers trouvés: " . $e->getMessage());
    $response['error'] = $e->getMessage();
}

echo json_encode($response);

