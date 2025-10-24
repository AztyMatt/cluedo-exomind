<?php
// Script de test pour déboguer les notifications
echo "=== TEST DES NOTIFICATIONS ===\n\n";

// Test 1: game_notifications.php
echo "1. Test de game_notifications.php:\n";
$url1 = 'http://localhost/game_notifications.php?day=1';
$response1 = file_get_contents($url1);
echo "Réponse: " . $response1 . "\n\n";

// Test 2: golden-paper-notification.php  
echo "2. Test de golden-paper-notification.php:\n";
$url2 = 'http://localhost/golden-paper-notification.php?day=1';
$response2 = file_get_contents($url2);
echo "Réponse: " . $response2 . "\n\n";

// Test 3: Vérification de la base de données
echo "3. Test de la base de données:\n";
require_once 'db-connection.php';
$db = getDBConnection();

if ($db) {
    echo "✅ Connexion BDD OK\n";
    
    // Vérifier les papiers récents
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM papers_found_user WHERE created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Papiers trouvés dans les 60 dernières secondes: " . $result['count'] . "\n";
    
    // Vérifier les papiers dorés récents
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM papers_found_user pf JOIN papers p ON pf.id_paper = p.id WHERE p.paper_type = 1 AND pf.created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Papiers dorés trouvés dans les 60 dernières secondes: " . $result['count'] . "\n";
    
} else {
    echo "❌ Erreur connexion BDD\n";
}

echo "\n=== FIN DU TEST ===\n";
?>
