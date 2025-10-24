<?php
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

// Vérifier la connexion avant de continuer
if (!$dbConnection) {
    error_log("Erreur critique: Impossible de se connecter à la base de données");
    die(json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']));
}

// Récupérer le jour depuis les paramètres GET
$day = isset($_GET['day']) ? (int)$_GET['day'] : 1;
$day = max(1, min(3, $day)); // Limiter entre 1 et 3

// Récupérer l'ID du joueur connecté depuis la session
$currentPlayerId = $_SESSION['user_id'] ?? null;

try {
    // Requête pour récupérer le papier doré trouvé récemment (dans les dernières 60 secondes)
    // EXCLURE le joueur connecté pour éviter les auto-notifications
    $query = "
        SELECT 
            pf.id,
            pf.id_paper,
            pf.id_player,
            pf.id_day,
            pf.created_at,
            u.firstname,
            u.lastname,
            u.username,
            g.name as team_name,
            g.color as team_color,
            g.img_path as team_img,
            g.pole_name,
            TIMESTAMPDIFF(SECOND, pf.created_at, NOW()) as seconds_ago
        FROM papers_found_user pf
        INNER JOIN users u ON pf.id_player = u.id
        INNER JOIN groups g ON u.group_id = g.id
        INNER JOIN papers p ON pf.id_paper = p.id
        WHERE p.paper_type = 1 
        AND pf.id_day = ?
        AND pf.id_player != ?
        ORDER BY pf.created_at DESC
        LIMIT 1
    ";
    
    $stmt = $dbConnection->prepare($query);
    $stmt->execute([$day, $currentPlayerId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        // Papier doré trouvé récemment
        $response = [
            'success' => true,
            'found' => true,
            'id' => $result['id'],
            'id_paper' => $result['id_paper'],
            'id_player' => $result['id_player'],
            'id_day' => $result['id_day'],
            'created_at' => $result['created_at'],
            'seconds_ago' => $result['seconds_ago'],
            'firstname' => $result['firstname'],
            'lastname' => $result['lastname'],
            'username' => $result['username'],
            'team_name' => $result['team_name'],
            'team_color' => $result['team_color'],
            'team_img' => $result['team_img'],
            'pole_name' => $result['pole_name'],
            'display_name' => ucfirst(strtolower($result['firstname'])) . ' ' . strtoupper($result['lastname'])
        ];
    } else {
        // Pas de papier doré trouvé récemment
        $response = [
            'success' => true,
            'found' => false,
            'day' => $day
        ];
    }
    
    // Retourner la réponse en JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération du papier doré: " . $e->getMessage());
    
    $response = [
        'success' => false,
        'error' => 'Erreur lors de la récupération des données',
        'found' => false
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
}
?>
