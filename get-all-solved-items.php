<?php
// Activer l'affichage des erreurs pour le debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Inclure la connexion à la base de données
    require_once 'db-connection.php';
    $dbConnection = getDBConnection();
    
    // Vérifier que la requête est en POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }
    
    // Récupérer les données JSON
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (!$input) {
        throw new Exception('Données JSON invalides: ' . json_last_error_msg());
    }
    
    $groupId = $input['groupId'] ?? null;
    
    if (!$groupId) {
        throw new Exception('Paramètre groupId requis');
    }
    
    // Récupérer TOUS les objets résolus (toutes équipes confondues)
    $stmt = $dbConnection->prepare("
        SELECT 
            i.id,
            i.title,
            i.subtitle,
            i.solved_title,
            i.solved,
            i.datetime_solved,
            i.id_mask,
            u.username as solved_by_username,
            u.firstname as solved_by_firstname,
            u.lastname as solved_by_lastname,
            g.color as team_color
        FROM `items` i
        LEFT JOIN `users` u ON i.id_solved_user = u.id
        LEFT JOIN `groups` g ON i.group_id = g.id
        WHERE i.solved = 1
        ORDER BY i.id ASC
    ");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Réponse de succès
    echo json_encode([
        'success' => true,
        'items' => $items,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Réponse d'erreur
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
