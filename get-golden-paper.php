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
    // Requête pour récupérer le papier doré du jour spécifique
    // La logique : paper_type = 1 correspond au papier doré
    // Pour le jour 1 : premier ID trouvé avec paper_type = 1
    // Pour le jour 2 : deuxième ID trouvé avec paper_type = 1  
    // Pour le jour 3 : troisième ID trouvé avec paper_type = 1
    
    $query = "
        SELECT 
            pf.id,
            pf.id_player,
            pf.id_day,
            pf.datetime,
            u.firstname,
            u.lastname,
            g.name as team_name,
            g.color as team_color
        FROM papers_found_user pf
        INNER JOIN users u ON pf.id_player = u.id
        INNER JOIN groups g ON u.group_id = g.id
        WHERE pf.paper_type = 1
        ORDER BY pf.id ASC
        LIMIT 1 OFFSET ?
    ";
    
    $stmt = $dbConnection->prepare($query);
    $stmt->execute([$day - 1]); // OFFSET 0 pour jour 1, 1 pour jour 2, 2 pour jour 3
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        // Papier doré trouvé
        $response = [
            'success' => true,
            'found' => true,
            'id' => $result['id'],
            'id_player' => $result['id_player'],
            'id_day' => $result['id_day'],
            'datetime' => $result['datetime'],
            'firstname' => $result['firstname'],
            'lastname' => $result['lastname'],
            'team_name' => $result['team_name'],
            'team_color' => $result['team_color']
        ];
    } else {
        // Papier doré non trouvé
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
