<?php
// Activer l'affichage des erreurs pour le debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Log pour debug
error_log("update-item-solved.php appelé");

try {
    // Inclure la connexion à la base de données
    require_once 'db-connection.php';
    $dbConnection = getDBConnection();
    error_log("Connexion DB chargée");
    
    // Vérifier que la requête est en POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }
    
    // Récupérer les données JSON
    $rawInput = file_get_contents('php://input');
    error_log("Données reçues: " . $rawInput);
    
    $input = json_decode($rawInput, true);
    
    if (!$input) {
        throw new Exception('Données JSON invalides: ' . json_last_error_msg());
    }
    
    $maskId = $input['maskId'] ?? null;
    $userId = $input['userId'] ?? null;
    
    error_log("maskId: " . $maskId . ", userId: " . $userId);
    
    if (!$maskId || !$userId) {
        throw new Exception('Paramètres manquants: maskId et userId requis');
    }
    
    // Vérifier que l'utilisateur existe
    $stmt = $dbConnection->prepare("SELECT id FROM `users` WHERE id = ?");
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) {
        throw new Exception('Utilisateur non trouvé');
    }
    
    // Trouver l'item correspondant au masque
    $stmt = $dbConnection->prepare("SELECT id FROM `items` WHERE id_mask = ?");
    $stmt->execute([$maskId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        throw new Exception('Aucun item trouvé pour ce masque');
    }
    
    $itemId = $item['id'];
    
    // Vérifier si l'item n'est pas déjà résolu
    $stmt = $dbConnection->prepare("SELECT solved FROM `items` WHERE id = ?");
    $stmt->execute([$itemId]);
    $currentItem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($currentItem && $currentItem['solved']) {
        throw new Exception('Cet item est déjà résolu');
    }
    
    // Mettre à jour l'item comme résolu
    $stmt = $dbConnection->prepare("
        UPDATE `items` 
        SET 
            solved = 1,
            id_solved_user = ?,
            datetime_solved = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$userId, $itemId]);
    
    if (!$result) {
        throw new Exception('Erreur lors de la mise à jour de l\'item');
    }
    
    // Récupérer les informations mises à jour
    $stmt = $dbConnection->prepare("
        SELECT 
            i.id,
            i.title,
            i.solved_title,
            i.solved,
            i.datetime_solved,
            u.username as solved_by_username
        FROM `items` i
        LEFT JOIN `users` u ON i.id_solved_user = u.id
        WHERE i.id = ?
    ");
    $stmt->execute([$itemId]);
    $updatedItem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Réponse de succès
    echo json_encode([
        'success' => true,
        'message' => 'Item mis à jour avec succès',
        'item' => $updatedItem
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
