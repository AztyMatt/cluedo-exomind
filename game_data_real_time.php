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
    'papers_found_team' => 0,
    'papers_total' => 0,
    'papers_found_me' => 0,
    'quota_per_user' => 0,
    'quota_reached' => false,
    'enigma_status' => 0,
    'day' => 1
];

if (!$dbConnection) {
    echo json_encode($response);
    exit;
}

// Récupérer l'utilisateur depuis le cookie
$activation_code_cookie = $_COOKIE['cluedo_activation'] ?? null;

if (!$activation_code_cookie) {
    echo json_encode($response);
    exit;
}

try {
    // Vérifier si le code du cookie existe en base et récupérer l'utilisateur
    $stmt = $dbConnection->prepare("SELECT u.*, g.id as group_id, g.quota_per_user FROM `users` u LEFT JOIN `groups` g ON u.group_id = g.id WHERE u.activation_code = ? AND u.has_activated = 1");
    $stmt->execute([$activation_code_cookie]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$user['group_id']) {
        echo json_encode($response);
        exit;
    }
    
    // Récupérer le jour actuel (par défaut jour 1)
    // TODO: Vous pouvez stocker le jour actuel dans la session ou le récupérer autrement
    $currentDay = isset($_GET['day']) ? (int)$_GET['day'] : 1;
    $currentDay = max(1, min(3, $currentDay)); // Limiter entre 1 et 3
    
    // Récupérer les statistiques de papiers pour l'équipe et le jour actuel
    $stmt = $dbConnection->prepare("SELECT total_to_found, total_founded FROM `total_papers_found_group` WHERE id_group = ? AND id_day = ?");
    $stmt->execute([$user['group_id'], $currentDay]);
    $paperStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($paperStats) {
        $response['papers_found_team'] = (int)$paperStats['total_founded'];
        $response['papers_total'] = (int)$paperStats['total_to_found'];
    } else {
        // Valeurs par défaut si pas de données
        $response['papers_found_team'] = 0;
        $response['papers_total'] = 10;
    }
    
    // Récupérer le nombre de papiers trouvés par ce joueur pour ce jour (EXCLURE les papiers dorés)
    $stmt = $dbConnection->prepare("
        SELECT COUNT(*) as count 
        FROM `papers_found_user` pf
        INNER JOIN `papers` p ON pf.id_paper = p.id
        WHERE pf.id_player = ? AND pf.id_day = ? AND p.paper_type = 0
    ");
    $stmt->execute([$user['id'], $currentDay]);
    $myPapersStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($myPapersStats) {
        $response['papers_found_me'] = (int)$myPapersStats['count'];
    }
    
    // Récupérer le quota par utilisateur
    $quotaPerUser = (int)$user['quota_per_user'];
    $response['quota_per_user'] = $quotaPerUser;
    
    // Vérifier si le quota est atteint (0 = illimité)
    if ($quotaPerUser > 0 && $response['papers_found_me'] >= $quotaPerUser) {
        $response['quota_reached'] = true;
    }
    
    // Récupérer le statut de l'énigme pour cette équipe
    $stmt = $dbConnection->prepare("SELECT status FROM `enigmes` WHERE id_group = ? AND id_day = ?");
    $stmt->execute([$user['group_id'], $currentDay]);
    $enigmaData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($enigmaData) {
        $response['enigma_status'] = (int)$enigmaData['status']; // 0 = à reconstituer, 1 = en cours, 2 = résolue
    } else {
        // Valeur par défaut si pas d'énigme
        $response['enigma_status'] = 0;
    }
    
    $response['day'] = $currentDay;
    $response['success'] = true;
    
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des données de jeu: " . $e->getMessage());
    $response['error'] = $e->getMessage();
}

echo json_encode($response);

