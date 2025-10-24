<?php
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

try {
    // Requête pour récupérer les objets placés récemment avec les informations du joueur et de l'équipe
    $query = "
        SELECT 
            i.id,
            i.path,
            i.title,
            i.subtitle,
            i.datetime_solved as datetime,
            u.firstname,
            u.lastname,
            g.name as team_name,
            g.color as team_color
        FROM items i
        INNER JOIN users u ON i.id_solved_user = u.id
        INNER JOIN `groups` g ON u.group_id = g.id
        WHERE i.solved = 1 
        AND i.datetime_solved IS NOT NULL
        ORDER BY i.datetime_solved DESC
    ";
    
    $stmt = $dbConnection->prepare($query);
    $stmt->execute();
    $objects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'objects' => $objects
    ];
    
    // Retourner la réponse en JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des objets: " . $e->getMessage());
    
    $response = [
        'success' => false,
        'error' => 'Erreur lors de la récupération des données',
        'objects' => []
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
}
?>