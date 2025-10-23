<?php
// Démarrer la session avec une durée de vie prolongée
session_start([
    'cookie_lifetime' => 86400 * 7, // 7 jours
    'cookie_secure' => false,        // Mettre à true en production avec HTTPS
    'cookie_httponly' => true,       // Empêche l'accès JavaScript aux cookies
    'cookie_samesite' => 'Strict'    // Protection CSRF
]);

// Connexion à la base de données
require_once __DIR__ . '/db-connection.php';
$dbConnection = getDBConnection();

// Fonction pour calculer le jour du jeu basé sur la date courante
function getGameDay($dbConnection) {
    try {
        // Récupérer la date courante de la base de données
        $query = "SELECT `date` FROM `current_date` WHERE `id` = 1";
        $stmt = $dbConnection->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && isset($result['date'])) {
            $currentDate = new DateTime($result['date']);
        } else {
            // Si pas de date en base, utiliser la date actuelle
            $currentDate = new DateTime();
        }
        
        // Date de référence : 27 octobre 2025 = Jour 1
        $referenceDate = new DateTime('2025-10-27');
        
        // Calculer la différence en jours
        $diff = $currentDate->diff($referenceDate);
        $daysDiff = $diff->days;
        
        // Si la date courante est avant le 27/10/2025, retourner jour 1
        if ($currentDate < $referenceDate) {
            return 1;
        }
        
        // Calculer le jour : 27/10 = jour 1, 28/10 = jour 2, 29/10 = jour 3
        $gameDay = $daysDiff + 1;
        
        // Limiter à jour 3 maximum, sinon retourner jour 1
        if ($gameDay > 3) {
            return 1;
        }
        
        return $gameDay;
        
    } catch (Exception $e) {
        // En cas d'erreur, retourner jour 1 par défaut
        return 1;
    }
}

// Calculer le jour du jeu une seule fois
$currentGameDay = getGameDay($dbConnection);

// Charger l'image papier et la convertir en base64
$paperPath = 'papier.png';
$paperData = '';
if (file_exists($paperPath)) {
    $paperData = base64_encode(file_get_contents($paperPath));
    $paperData = 'data:image/png;base64,' . $paperData;
}

// Charger l'image flèche et la convertir en base64
$arrowPath = 'arrow.png';
$arrowData = '';
if (file_exists($arrowPath)) {
    $arrowData = base64_encode(file_get_contents($arrowPath));
    $arrowData = 'data:image/png;base64,' . $arrowData;
}

// Lister les images disponibles dans /rooms pour le select
$roomsDir = __DIR__ . '/rooms';
$roomImages = [];
if (is_dir($roomsDir)) {
    foreach (scandir($roomsDir) as $file) {
        if ($file[0] === '.') continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $roomImages[] = 'rooms/' . $file;
        }
    }
    sort($roomImages);
}

// ========== API POUR ENREGISTRER UN PAPIER TROUVÉ ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'paper_found') {
    $paperId = $_POST['paper_id'] ?? null;
    $playerId = $_SESSION['user_id'] ?? null;
    $dayId = $_POST['day_id'] ?? $currentGameDay;
    
    if (!$paperId || !$playerId) {
        echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
        exit;
    }
    
    try {
        if (!$dbConnection) {
            echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
            exit;
        }
        
        // Insérer dans papers_found_user (ou ignorer si déjà trouvé)
        $stmt = $dbConnection->prepare("INSERT IGNORE INTO `papers_found_user` (id_paper, id_player, id_day) VALUES (?, ?, ?)");
        $stmt->execute([$paperId, $playerId, $dayId]);
        
        $inserted = $stmt->rowCount() > 0;
        
                if ($inserted) {
                    // Récupérer les infos du joueur et de son groupe
                    $stmt = $dbConnection->prepare("SELECT u.username, u.firstname, u.lastname, u.group_id, g.color, g.img_path, g.pole_name FROM `users` u LEFT JOIN `groups` g ON u.group_id = g.id WHERE u.id = ?");
                    $stmt->execute([$playerId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user && $user['group_id']) {
                        // Incrémenter total_founded dans total_papers_found_group
                        $stmt = $dbConnection->prepare("UPDATE `total_papers_found_group` SET total_founded = total_founded + 1 WHERE id_group = ? AND id_day = ?");
                        $stmt->execute([$user['group_id'], $dayId]);
                        
                        // CHRONOMÉTRAGE : Si c'est le premier papier trouvé pour cette énigme, démarrer le chrono
                        $stmt = $dbConnection->prepare("SELECT id FROM `enigmes` WHERE id_group = ? AND id_day = ?");
                        $stmt->execute([$user['group_id'], $dayId]);
                        $enigma = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($enigma) {
                            // Vérifier si c'est le premier papier trouvé (total_founded = 1 après l'incrémentation)
                            $stmt = $dbConnection->prepare("SELECT total_founded FROM `total_papers_found_group` WHERE id_group = ? AND id_day = ?");
                            $stmt->execute([$user['group_id'], $dayId]);
                            $paperStats = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($paperStats && $paperStats['total_founded'] == 1) {
                                // Premier papier trouvé ! Démarrer le chrono si pas déjà démarré
                                $stmt = $dbConnection->prepare("UPDATE `enigm_solutions_durations` SET timestamp_start = NOW() WHERE id_enigm = ? AND timestamp_start IS NULL");
                                $stmt->execute([$enigma['id']]);
                                
                                if ($stmt->rowCount() > 0) {
                                    error_log("⏱️ Chrono démarré pour l'énigme ID " . $enigma['id'] . " (équipe " . $user['group_id'] . ", jour " . $dayId . ")");
                                }
                            }
                        }
                
                // Vérifier si tous les papiers ont été trouvés
                $stmt = $dbConnection->prepare("SELECT total_to_found, total_founded FROM `total_papers_found_group` WHERE id_group = ? AND id_day = ?");
                $stmt->execute([$user['group_id'], $dayId]);
                $paperStats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $enigmaUnlocked = false;
                if ($paperStats && $paperStats['total_founded'] >= $paperStats['total_to_found']) {
                    // Tous les papiers ont été trouvés ! Débloquer l'énigme
                    // Mettre à jour le status de l'énigme de 0 à 1 (uniquement si actuellement à 0)
                    $stmt = $dbConnection->prepare("UPDATE `enigmes` SET status = 1 WHERE id_group = ? AND id_day = ? AND status = 0");
                    $stmt->execute([$user['group_id'], $dayId]);
                    
                    if ($stmt->rowCount() > 0) {
                        $enigmaUnlocked = true;
                        error_log("🔓 Énigme débloquée pour le groupe " . $user['group_id'] . " au jour " . $dayId);
                    }
                }
                
                // Récupérer la datetime de création
                $stmt = $dbConnection->prepare("SELECT created_at FROM `papers_found_user` WHERE id_paper = ? AND id_player = ? AND id_day = ?");
                $stmt->execute([$paperId, $playerId, $dayId]);
                $paperFound = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Formater la date et l'heure avec "à" entre les deux
                $datetime = $paperFound ? strtotime($paperFound['created_at']) : time();
                $formattedDateTime = date('d/m/Y', $datetime) . ' à ' . date('H:i:s', $datetime);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Papier enregistré', 
                    'new_find' => true,
                    'found_by' => $user['username'],
                    'found_by_display' => ucfirst(strtolower($user['firstname'])) . ' ' . strtoupper($user['lastname']),
                    'found_at' => $formattedDateTime,
                    'team_color' => $user['color'],
                    'team_img' => $user['img_path'],
                    'team_pole' => $user['pole_name'],
                    'enigma_unlocked' => $enigmaUnlocked,
                    'papers_found' => $paperStats ? (int)$paperStats['total_founded'] : 0,
                    'papers_total' => $paperStats ? (int)$paperStats['total_to_found'] : 0
                ]);
            } else {
                echo json_encode(['success' => true, 'message' => 'Papier enregistré (pas de groupe)', 'new_find' => true]);
            }
        } else {
            echo json_encode(['success' => true, 'message' => 'Papier déjà trouvé', 'new_find' => false]);
        }
        
    } catch (PDOException $e) {
        error_log("⚠️ Erreur enregistrement papier: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()]);
    }
    exit;
}

// ========== API POUR CHARGER LES DONNÉES ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'load') {
    $key = $_POST['key'] ?? '';
    
    try {
        if (!$dbConnection) {
            echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
            exit;
        }
        
        // Trouver la photo correspondant à la clé
        $stmt = $dbConnection->prepare("SELECT id FROM `photos` WHERE filename LIKE ?");
        $stmt->execute([$key . '.%']);
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($photo) {
            $photoId = $photo['id'];
            $result = [];
            
            // Charger tous les papers de cette photo
            $stmt = $dbConnection->prepare("SELECT id, position_left, position_top, scale_x, scale_y, angle, z_index FROM `papers` WHERE photo_id = ? ORDER BY z_index ASC, id ASC");
            $stmt->execute([$photoId]);
            $papers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($papers as $paper) {
                $result[] = [
                    'type' => 'paper',
                    'id' => $paper['id'],
                    'left' => (float)$paper['position_left'],
                    'top' => (float)$paper['position_top'],
                    'scaleX' => (float)$paper['scale_x'],
                    'scaleY' => (float)$paper['scale_y'],
                    'angle' => (float)$paper['angle'],
                    'zIndex' => (int)$paper['z_index']
                ];
            }
            
            // Charger tous les masks de cette photo
            $stmt = $dbConnection->prepare("SELECT id, original_points, curve_handles, position_left, position_top, z_index FROM `masks` WHERE photo_id = ? ORDER BY z_index ASC, id ASC");
            $stmt->execute([$photoId]);
            $masks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($masks as $mask) {
                $result[] = [
                    'type' => 'mask',
                    'id' => $mask['id'],
                    'originalPoints' => json_decode($mask['original_points'], true),
                    'curveHandles' => json_decode($mask['curve_handles'], true),
                    'left' => (float)$mask['position_left'],
                    'top' => (float)$mask['position_top'],
                    'zIndex' => (int)$mask['z_index']
                ];
            }
            
            // Charger toutes les arrows de cette photo
            $stmt = $dbConnection->prepare("SELECT a.id, a.position_left, a.position_top, a.angle, a.active, a.free_placement, a.target_photo_id, p.filename as target_photo_filename FROM `arrows` a LEFT JOIN `photos` p ON a.target_photo_id = p.id WHERE a.photo_id = ? ORDER BY a.id ASC");
            $stmt->execute([$photoId]);
            $arrows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($arrows as $arrow) {
                $targetPhotoName = null;
                if ($arrow['target_photo_filename']) {
                    $targetPhotoName = pathinfo($arrow['target_photo_filename'], PATHINFO_FILENAME);
                }
                
                $result[] = [
                    'type' => 'arrow',
                    'id' => $arrow['id'],
                    'left' => (float)$arrow['position_left'],
                    'top' => (float)$arrow['position_top'],
                    'angle' => (float)$arrow['angle'],
                    'targetPhotoName' => $targetPhotoName,
                    'freePlacement' => (bool)$arrow['free_placement'],
                    'zIndex' => 1000
                ];
            }
            
            // Trier tous les objets par z-index
            usort($result, function($a, $b) {
                return ($a['zIndex'] ?? 0) <=> ($b['zIndex'] ?? 0);
            });
            
            echo json_encode(['success' => true, 'data' => json_encode($result), 'source' => 'database']);
            exit;
        }
        
        echo json_encode(['success' => true, 'data' => json_encode([]), 'source' => 'database_empty']);
    } catch (PDOException $e) {
        error_log("⚠️ Erreur chargement BDD: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement: ' . $e->getMessage()]);
    }
    exit;
}

// Fonction pour formater le nom : Prénom NOM
function formatUserName($firstname, $lastname) {
    return ucfirst(strtolower($firstname)) . ' ' . strtoupper($lastname);
}

// ========== TRAITEMENT DU FORMULAIRE D'ACTIVATION ==========
$error_message = '';
$show_activation_form = false;
$user = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activation_code'])) {
    $activation_code = strtoupper(trim($_POST['activation_code']));
    
    if ($dbConnection) {
        try {
            // Vérifier si le code existe
            $stmt = $dbConnection->prepare("SELECT u.*, g.name as team_name, g.pole_name, g.color as team_color, g.img_path as team_img FROM `users` u LEFT JOIN `groups` g ON u.group_id = g.id WHERE u.activation_code = ?");
            $stmt->execute([$activation_code]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Code valide ! Activer l'utilisateur
                $stmt = $dbConnection->prepare("UPDATE `users` SET has_activated = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Créer le cookie avec le code d'activation (durée : 30 jours)
                setcookie('cluedo_activation', $activation_code, [
                    'expires' => time() + (86400 * 30),
                    'path' => '/',
                    'secure' => false,  // Mettre à true en production avec HTTPS
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
                
                // Créer la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['activation_code'] = $activation_code;
                $_SESSION['has_activated'] = 1;
                
                // Recharger la page pour afficher le jeu
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            } else {
                $error_message = "❌ Code d'activation invalide. Veuillez réessayer.";
                $show_activation_form = true;
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification du code: " . $e->getMessage());
            $error_message = "⚠️ Erreur lors de la vérification. Veuillez réessayer.";
            $show_activation_form = true;
        }
    }
}

// ========== VÉRIFICATION DU COOKIE ET DE LA SESSION ==========
if (!$show_activation_form) {
    // Vérifier si le cookie existe
    $activation_code_cookie = $_COOKIE['cluedo_activation'] ?? null;
    
    if ($activation_code_cookie && $dbConnection) {
        try {
            // Vérifier si le code du cookie existe en base
            $stmt = $dbConnection->prepare("SELECT u.*, g.name as team_name, g.pole_name, g.color as team_color, g.img_path as team_img FROM `users` u LEFT JOIN `groups` g ON u.group_id = g.id WHERE u.activation_code = ? AND u.has_activated = 1");
            $stmt->execute([$activation_code_cookie]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Cookie valide ! Créer/mettre à jour la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['activation_code'] = $activation_code_cookie;
                $_SESSION['has_activated'] = 1;
                
                // Récupérer le statut de l'énigme pour cette équipe
                $stmt = $dbConnection->prepare("SELECT status, datetime_solved, enigm_solution FROM `enigmes` WHERE id_group = ? AND id_day = ?");
                $stmt->execute([$user['group_id'], $currentGameDay]);
                $enigmaData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($enigmaData) {
                    $user['enigma_status'] = (int)$enigmaData['status']; // 0 = à reconstituer, 1 = en cours, 2 = résolue
                    $user['datetime_solved'] = $enigmaData['datetime_solved'];
                    $user['enigma_solution'] = $enigmaData['enigm_solution'];
                } else {
                    // Valeur par défaut si pas d'énigme
                    $user['enigma_status'] = 0;
                    $user['datetime_solved'] = null;
                    $user['enigma_solution'] = '';
                }
            } else {
                // Cookie invalide ou utilisateur non activé -> demander le code
                $show_activation_form = true;
                // Supprimer le cookie invalide
                setcookie('cluedo_activation', '', time() - 3600, '/');
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification du cookie: " . $e->getMessage());
            $show_activation_form = true;
        }
    } else {
        // Pas de cookie -> afficher le formulaire
        $show_activation_form = true;
    }
}

// Si on doit afficher le formulaire, on s'arrête ici
if ($show_activation_form) {
    // Le formulaire sera affiché plus bas dans le HTML
} else if (!$user) {
    // Sécurité supplémentaire : si on arrive ici sans utilisateur, redemander le code
    $show_activation_form = true;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cluedo - Jeu en cours</title>
    
    <!-- Fabric.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
    
    <!-- Canvas Confetti pour les animations -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #000000;
            color: #eee;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(42, 42, 42, 0.95);
            border-radius: 12px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.1);
            z-index: 1000;
            backdrop-filter: blur(10px);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, <?= htmlspecialchars($user['team_color'] ?? '#888') ?>, rgba(255,255,255,0.3));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            border: 2px solid <?= htmlspecialchars($user['team_color'] ?? '#888') ?>;
            flex-shrink: 0;
        }

        .user-details h2 {
            font-size: 0.95rem;
            color: #fff;
            margin-bottom: 3px;
            white-space: nowrap;
        }

        .user-team {
            font-size: 0.8rem;
            color: <?= htmlspecialchars($user['team_color'] ?? '#888') ?>;
            font-weight: bold;
        }

        .game-content {
            background: rgba(42, 42, 42, 0.95);
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.1);
            min-height: 500px;
        }

        .game-title {
            font-size: 2rem;
            color: #fff;
            text-align: center;
            margin-bottom: 30px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
        }

        .game-description {
            font-size: 1.2rem;
            color: #ccc;
            text-align: center;
            line-height: 1.8;
            margin-bottom: 30px;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 40px;
        }

        .action-btn {
            padding: 15px 40px;
            font-size: 1.2rem;
            font-weight: bold;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .welcome-message {
            background: rgba(102, 126, 234, 0.2);
            border-left: 4px solid <?= htmlspecialchars($user['team_color'] ?? '#888') ?>;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .welcome-message h3 {
            color: #fff;
            margin-bottom: 10px;
        }

        .welcome-message p {
            color: #ccc;
            line-height: 1.6;
        }
        
        /* ========== STYLES POUR LE JEU ========== */
        #game-canvas-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 88vh;
            overflow: hidden;
            background: #000000;
            opacity: 1;
            visibility: visible;
            transition: opacity 0.6s ease;
        }
        
        #game-canvas-container.loading {
            opacity: 0;
        }
        
        #game-canvas-container canvas {
            display: block;
        }
        
        #loading-logo-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            pointer-events: none;
            transform: translateY(-50px);
        }
        
        #loading-logo {
            max-width: 300px;
            max-height: 200px;
            width: auto;
            height: auto;
            opacity: 0;
        }
        
        #loading-logo.show {
            animation: logoFadeInOut 4s ease-in-out infinite;
        }
        
        @keyframes logoFadeInOut {
            0% { opacity: 0; }
            20% { opacity: 1; }
            30% { opacity: 1; }
            50% { opacity: 0; }
            60% { opacity: 0; }
            80% { opacity: 1; }
            90% { opacity: 1; }
            100% { opacity: 0; }
        }
        
        /* Pop-up d'information papier trouvé */
        #paper-info-popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(42, 42, 42, 0.98);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.7);
            z-index: 10000;
            min-width: 350px;
            border: 3px solid;
            backdrop-filter: blur(10px);
        }
        
        #paper-info-popup.show {
            display: block;
            animation: popupSlideIn 0.3s ease;
        }
        
        @keyframes popupSlideIn {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }
        
        #popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            backdrop-filter: blur(5px);
        }
        
        #popup-overlay.show {
            display: block;
        }
        
        .popup-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #fff;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .popup-content {
            font-size: 1.1rem;
            color: #eee;
            line-height: 1.8;
            text-align: center;
        }
        
        .popup-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            font-size: 20px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .popup-close:hover {
            background: rgba(255, 77, 77, 0.8);
            transform: rotate(90deg);
        }
        
        /* Message de félicitations */
        #congrats-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.7);
            z-index: 20000;
            backdrop-filter: blur(3px);
        }
        
        #congrats-overlay.show {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        
        #congrats-message {
            background: linear-gradient(135deg, rgba(26, 127, 26, 0.95) 0%, rgba(56, 239, 125, 0.95) 100%);
            padding: 40px 60px;
            border-radius: 20px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.8);
            text-align: center;
            border: 3px solid rgba(255, 255, 255, 0.3);
            animation: bounceIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes bounceIn {
            0% {
                transform: scale(0.5);
                opacity: 0;
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        #congrats-message h2 {
            font-size: 2.5rem;
            color: #fff;
            margin-bottom: 15px;
            text-shadow: 0 3px 10px rgba(0, 0, 0, 0.5);
        }
        
        #congrats-message p {
            font-size: 1.3rem;
            color: #fff;
            font-weight: 500;
        }
        
        /* Notification d'énigme débloquée */
        #enigma-unlocked-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.85);
            z-index: 30000;
            backdrop-filter: blur(5px);
        }
        
        #enigma-unlocked-overlay.show {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.5s ease;
        }
        
        #enigma-unlocked-message {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.95) 0%, rgba(118, 75, 162, 0.95) 100%);
            padding: 50px 70px;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.9);
            text-align: center;
            border: 4px solid rgba(255, 215, 0, 0.6);
            animation: bounceIn 0.6s ease;
        }
        
        #enigma-unlocked-message h2 {
            font-size: 3rem;
            color: #FFD700;
            margin-bottom: 20px;
            text-shadow: 0 3px 15px rgba(0, 0, 0, 0.7);
        }
        
        #enigma-unlocked-message p {
            font-size: 1.5rem;
            color: #fff;
            font-weight: 600;
            line-height: 1.8;
        }
        
        /* Conteneur de notifications en haut à droite */
        #notifications-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 15000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 350px;
        }
        
        .notification-item {
            background: rgba(42, 42, 42, 0.98);
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 4px solid;
            backdrop-filter: blur(10px);
            animation: slideInRight 0.4s ease;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        
        .notification-item.hiding {
            opacity: 0;
            transform: translateX(400px);
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(400px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .notification-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--notif-color), rgba(255,255,255,0.2));
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 2px solid var(--notif-color);
            overflow: hidden;
        }
        
        .notification-avatar img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .notification-content {
            flex: 1;
            color: #fff;
        }
        
        .notification-name {
            font-weight: bold;
            font-size: 0.95rem;
            margin-bottom: 3px;
        }
        
        .notification-pole {
            font-size: 0.8rem;
            color: var(--notif-color);
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .notification-action {
            font-size: 0.85rem;
            color: #ccc;
        }
        
        /* Style pour quota atteint */
        .quota-reached {
            color: #ff4444 !important;
        }
        
        /* Pop-up de quota atteint */
        #quota-warning-popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(235, 51, 73, 0.98);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.7);
            z-index: 10000;
            min-width: 400px;
            border: 3px solid #ff6b6b;
            backdrop-filter: blur(10px);
            text-align: center;
        }
        
        #quota-warning-popup.show {
            display: block;
            animation: popupSlideIn 0.3s ease;
        }
        
        #quota-warning-popup h2 {
            font-size: 1.8rem;
            color: #fff;
            margin-bottom: 15px;
        }
        
        #quota-warning-popup p {
            font-size: 1.1rem;
            color: #fff;
            line-height: 1.6;
        }
        
        #game-bottom-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100vw;
            height: 12vh;
            background: rgba(42, 42, 42, 0.98);
            border-top: 2px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: stretch;
            justify-content: space-around;
            padding: 15px 30px;
            box-sizing: border-box;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .bar-section {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .user-info-compact {
            display: flex;
            align-items: center;
            gap: 12px;
            background: <?= htmlspecialchars($user['team_color'] ?? '#2a2a2a') ?>cc;
            padding: 12px 20px;
            border-radius: 12px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: white;
        }
        
        .user-info-compact * {
            color: white !important;
        }
        
        .user-avatar-small {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, <?= htmlspecialchars($user['team_color'] ?? '#888') ?>, rgba(255,255,255,0.3));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            border: 2px solid <?= htmlspecialchars($user['team_color'] ?? '#888') ?>;
            flex-shrink: 0;
        }
        
        .user-details-small {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        
        .user-name-small {
            font-size: 1rem;
            color: #fff;
            font-weight: bold;
        }
        
        .user-team-small {
            font-size: 0.85rem;
            color: <?= htmlspecialchars($user['team_color'] ?? '#888') ?>;
            font-weight: bold;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.05);
            padding: 12px 20px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .stat-icon {
            font-size: 2rem;
        }
        
        .stat-content {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-value {
            font-size: 1.2rem;
            color: #fff;
            font-weight: bold;
        }
        
        .btn-back {
            padding: 12px 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: bold;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }
        
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($show_activation_form): ?>
            <!-- ========== FORMULAIRE D'ACTIVATION ========== -->
            <div class="game-content" style="max-width: 600px; margin: 100px auto;">
                <h1 class="game-title">🔐 Activation de votre compte</h1>
                
                <div class="game-description" style="margin-bottom: 40px;">
                    <p>Pour accéder au jeu Cluedo, veuillez entrer votre code d'activation unique.</p>
                    <p style="font-size: 0.9rem; color: #aaa; margin-top: 15px;">Ce code vous a été fourni avec votre invitation au jeu.</p>
                </div>

                <?php if ($error_message): ?>
                    <div style="background: rgba(235, 51, 73, 0.2); border-left: 4px solid #eb3349; padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #ff6b6b;">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= $_SERVER['REQUEST_URI'] ?>" style="display: flex; flex-direction: column; gap: 20px;">
                    <div>
                        <label for="activation_code" style="display: block; margin-bottom: 10px; font-size: 1rem; color: #fff; font-weight: bold;">
                            Code d'activation
                        </label>
                        <input 
                            type="text" 
                            id="activation_code" 
                            name="activation_code" 
                            placeholder="Ex: A7K9X2"
                            required
                            maxlength="10"
                            style="width: 100%; padding: 15px; font-size: 1.2rem; border: 2px solid #555; border-radius: 8px; background: #1a1a1a; color: #fff; text-transform: uppercase; letter-spacing: 2px; text-align: center; font-weight: bold;"
                            autocomplete="off"
                        />
                    </div>

                    <button type="submit" class="action-btn btn-success" style="width: 100%; margin: 0;">
                        ✅ Activer mon compte
                    </button>
                </form>

                <div style="text-align: center; margin-top: 30px;">
                    <a href="/teams" style="color: #667eea; text-decoration: none; font-size: 0.95rem;">
                        ← Retour à la page des équipes
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- ========== CONTENU DU JEU (utilisateur activé) ========== -->
            
            <!-- Canvas Fabric.js -->
            <div id="game-canvas-container">
                <canvas id="c"></canvas>
            </div>
            
            <!-- Logo de chargement séparé -->
            <div id="loading-logo-container">
                <img id="loading-logo" src="assets/img/logo.png" alt="Loading Logo">
            </div>
            
            <!-- Conteneur de notifications (papiers trouvés récemment) -->
            <div id="notifications-container"></div>
            
            <!-- Message de félicitations -->
            <div id="congrats-overlay">
                <div id="congrats-message">
                    <h2>🎉 Félicitations !</h2>
                    <p>Vous avez trouvé un morceau de l'énigme</p>
                </div>
            </div>
            
            <!-- Message d'énigme débloquée -->
            <div id="enigma-unlocked-overlay">
                <div id="enigma-unlocked-message">
                    <h2>🔓 Énigme débloquée !</h2>
                    <p>Votre équipe a trouvé tous les papiers !<br>L'énigme peut maintenant être résolue.</p>
                </div>
            </div>
            
            <!-- Pop-up d'information papier trouvé -->
            <div id="popup-overlay"></div>
            <div id="paper-info-popup">
                <button class="popup-close" onclick="closePaperPopup()">&times;</button>
                <div class="popup-title">📄 Papier trouvé</div>
                <div class="popup-content" id="popup-content">
                    <!-- Contenu dynamique -->
                </div>
            </div>
            
            <!-- Pop-up de quota atteint -->
            <div id="quota-warning-popup">
                <h2>🔒 Quota atteint !</h2>
                <p id="quota-warning-content">
                    <!-- Contenu dynamique -->
                </p>
            </div>

            <!-- Barre d'information en bas -->
            <div id="game-bottom-bar">
                <div class="bar-section">
                    <div class="user-info-compact">
                        <div class="user-avatar-small">
                            <?php if (!empty($user['team_img']) && file_exists($user['team_img'])): ?>
                                <img src="<?= htmlspecialchars($user['team_img']) ?>" alt="<?= htmlspecialchars($user['team_name']) ?>" style="width: 100%; height: 100%; object-fit: contain; border-radius: 50%;">
                            <?php else: ?>
                                🎮
                            <?php endif; ?>
                        </div>
                        <div class="user-details-small">
                            <div class="user-name-small"><?= htmlspecialchars(formatUserName($user['firstname'], $user['lastname'])) ?></div>
                            <div class="user-team-small"><?= htmlspecialchars($user['pole_name'] ?? 'Non assigné') ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="bar-section">
                    <div class="stat-item">
                        <div class="stat-icon">📄</div>
                        <div class="stat-content">
                            <div class="stat-label">Papiers trouvés équipe</div>
                            <div class="stat-value"><span id="papers-found-team">0</span> / <span id="papers-total">0</span></div>
                        </div>
                    </div>
                </div>
                
                <div class="bar-section">
                    <div class="stat-item">
                        <div class="stat-icon">✅</div>
                        <div class="stat-content">
                            <div class="stat-label">Papiers que j'ai trouvé</div>
                            <div class="stat-value" id="my-papers-display">
                                <span id="papers-found-me">0</span> <span id="quota-info" style="font-size: 0.9rem; color: #aaa;">(Quota illimité)</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bar-section">
                    <div class="stat-item">
                        <div class="stat-icon">📅</div>
                        <div class="stat-content">
                            <div class="stat-label">Jour</div>
                            <div class="stat-value">Jour <?php echo $currentGameDay; ?></div>
                            <!-- Debug: Date courante = <?php echo $currentGameDay; ?> -->
                        </div>
                    </div>
                </div>
                
                <div class="bar-section">
                    <?php if ($user['enigma_status'] == 0): ?>
                        <!-- Énigme verrouillée -->
                        <div class="btn-back" style="background: linear-gradient(135deg, #666 0%, #888 100%); cursor: not-allowed; opacity: 0.6;">
                            🔒 Énigme verrouillée
                        </div>
                    <?php elseif ($user['enigma_status'] == 1): ?>
                        <!-- Énigme déverrouillée -->
                        <a href="enigme.php?day=<?php echo $currentGameDay; ?>" class="btn-back" style="background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%);">
                            🎭 Résoudre l'énigme
                        </a>
                    <?php else: ?>
                        <!-- Énigme résolue -->
                        <a href="enigme.php?day=<?php echo $currentGameDay; ?>" class="btn-back" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                            ✅ Énigme résolue
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="bar-section">
                    <a href="/teams" class="btn-back" style="background: #667eea;">
                        🏆 ÉQUIPES
                    </a>
                </div>
            </div>

        <?php endif; ?>
    </div>
    
    <?php if (!$show_activation_form): ?>
    <!-- ========== SCRIPTS POUR LE JEU ========== -->
    <script>
        // Variables PHP injectées en JavaScript
        const roomImages = <?php echo json_encode($roomImages); ?>;
        const paperDataUrl = <?php echo json_encode($paperData); ?>;
        const arrowDataUrl = <?php echo json_encode($arrowData); ?>;
        
        // ========== INITIALISATION DU CANVAS ==========
        const canvasElement = document.getElementById('c');
        const gameContainer = document.getElementById('game-canvas-container');
        canvasElement.width = window.innerWidth;
        canvasElement.height = window.innerHeight * 0.88; // 88% de hauteur
        
        const canvas = new fabric.Canvas("c", {
            selection: false, // Désactiver la sélection en mode jeu
        });
        
        canvas.defaultCursor = 'grab';
        canvas.hoverCursor = 'grab';
        
        // Variables globales
        let backgroundImage;
        let currentBackgroundKey = '';
        let isDragging = false;
        let lastPosX, lastPosY;
        let baseZoom = 1;
        let isAtBaseZoom = true;
        
        // ========== GESTION DU VIEWPORT (ZOOM/PAN) ==========
        function computeBaseViewport() {
            if (!backgroundImage) return { zoom: 1, panX: 0, panY: 0 };
            const iw = backgroundImage.width;
            const ih = backgroundImage.height;
            const cw = canvas.getWidth();
            const ch = canvas.getHeight();
            const zoom = Math.min(cw / iw, ch / ih);
            const panX = (cw - iw * zoom) / 2;
            const panY = (ch - ih * zoom) / 2;
            return { zoom, panX, panY };
        }
        
        function applyBaseViewport() {
            const { zoom, panX, panY } = computeBaseViewport();
            baseZoom = zoom;
            canvas.setViewportTransform([zoom, 0, 0, zoom, panX, panY]);
            canvas.requestRenderAll();
        }
        
        function resetZoomAndPan() {
            applyBaseViewport();
            isAtBaseZoom = true;
        }
        
        // Contraindre le viewport pour ne jamais montrer en dehors de l'image
        function constrainViewportToImage() {
            if (!backgroundImage) return;
            
            const vpt = canvas.viewportTransform;
            const zoom = vpt[0];
            
            const iw = backgroundImage.width * (backgroundImage.scaleX || 1);
            const ih = backgroundImage.height * (backgroundImage.scaleY || 1);
            const cw = canvas.getWidth();
            const ch = canvas.getHeight();
            
            const scaledWidth = iw * zoom;
            const scaledHeight = ih * zoom;
            
            if (scaledWidth <= cw) {
                vpt[4] = (cw - scaledWidth) / 2;
            } else {
                const minPanX = cw - scaledWidth;
                const maxPanX = 0;
                vpt[4] = Math.max(minPanX, Math.min(maxPanX, vpt[4]));
            }
            
            if (scaledHeight <= ch) {
                vpt[5] = (ch - scaledHeight) / 2;
            } else {
                const minPanY = ch - scaledHeight;
                const maxPanY = 0;
                vpt[5] = Math.max(minPanY, Math.min(maxPanY, vpt[5]));
            }
            
            canvas.setViewportTransform(vpt);
            canvas.requestRenderAll();
        }
        
        // Redimensionnement de la fenêtre
        window.addEventListener('resize', () => {
            canvasElement.width = window.innerWidth;
            canvasElement.height = window.innerHeight * 0.88;
            canvas.setDimensions({ width: window.innerWidth, height: window.innerHeight * 0.88 });
            if (isAtBaseZoom) {
                applyBaseViewport();
            } else {
                canvas.renderAll();
                constrainViewportToImage();
            }
        });
        
        // Zoom à la molette
        canvas.on("mouse:wheel", function (opt) {
            const delta = opt.e.deltaY;
            let zoom = canvas.getZoom();
            zoom *= 0.999 ** delta;
            zoom = Math.max(Math.min(zoom, 10), baseZoom);
            canvas.zoomToPoint({ x: opt.e.offsetX, y: opt.e.offsetY }, zoom);
            constrainViewportToImage();
            isAtBaseZoom = Math.abs(canvas.getZoom() - baseZoom) < 1e-6;
            opt.e.preventDefault();
            opt.e.stopPropagation();
        });
        
        // ========== GESTION DU PAN ==========
        canvas.on("mouse:down", (opt) => {
            if (!opt.target) {
                isDragging = true;
                lastPosX = opt.e.clientX;
                lastPosY = opt.e.clientY;
                canvas.defaultCursor = 'grabbing';
            }
        });
        
        canvas.on("mouse:move", (opt) => {
            if (isDragging) {
                const e = opt.e;
                const vpt = canvas.viewportTransform;
                vpt[4] += e.clientX - lastPosX;
                vpt[5] += e.clientY - lastPosY;
                constrainViewportToImage();
                lastPosX = e.clientX;
                lastPosY = e.clientY;
            }
        });
        
        canvas.on("mouse:up", () => {
            isDragging = false;
            canvas.defaultCursor = 'grab';
        });
        
        // ========== GESTION DES CURSEURS ==========
        canvas.on('mouse:move', function(opt) {
            if (!isDragging) {
                const obj = canvas.findTarget(opt.e, false);
                if (obj && obj.isArrow) {
                    canvas.setCursor('pointer');
                } else if (obj && obj.isPaper && !obj.isFound) {
                    // Pointer uniquement si le papier n'a pas été trouvé
                    canvas.setCursor('pointer');
                } else {
                    canvas.setCursor('grab');
                }
            }
        });
        
        // ========== GESTION DES CLICS (FLÈCHES ET PAPIERS) ==========
        let totalPapers = 0;      // Total de papiers à trouver (depuis BDD)
        let foundPapersTeam = 0;  // Papiers trouvés par l'équipe (depuis BDD, mis à jour via AJAX)
        let foundPapersMe = 0;    // Papiers trouvés par moi (local, persistant)
        let quotaPerUser = 0;     // Quota max par utilisateur (0 = illimité)
        let quotaReached = false; // Quota atteint ou non
        
        function updatePaperCount() {
            document.getElementById('papers-found-team').textContent = foundPapersTeam;
            document.getElementById('papers-total').textContent = totalPapers;
            
            const myPapersDisplay = document.getElementById('my-papers-display');
            const quotaInfo = document.getElementById('quota-info');
            
            // Construire l'affichage du quota
            let quotaText = '';
            if (quotaPerUser === 0) {
                quotaText = '(Quota illimité)';
            } else {
                quotaText = '(Quota : ' + quotaPerUser + ')';
            }
            
            // Si quota atteint, mettre en rouge avec cadenas
            if (quotaReached) {
                myPapersDisplay.innerHTML = '<span style="color: #ff4444;">🔒 ' + foundPapersMe + '</span> <span style="font-size: 0.9rem; color: #ff4444;">' + quotaText + '</span>';
            } else {
                myPapersDisplay.innerHTML = '<span id="papers-found-me">' + foundPapersMe + '</span> <span id="quota-info" style="font-size: 0.9rem; color: #aaa;">' + quotaText + '</span>';
            }
        }
        
        canvas.on('mouse:down', function(opt) {
            if (!opt.target) return;
            
            const obj = opt.target;
            
            // Navigation avec les flèches
            if (obj.isArrow && obj.targetPhotoName) {
                opt.e.preventDefault();
                opt.e.stopPropagation();
                canvas.discardActiveObject();
                
                const targetPath = roomImages.find(path => {
                    const filename = path.split('/').pop();
                    const photoName = filename.replace(/\.[^/.]+$/, '');
                    return photoName === obj.targetPhotoName;
                });
                
                if (targetPath) {
                    console.log('🎯 Navigation vers:', obj.targetPhotoName);
                    setBackgroundImage(targetPath);
                    
                    setTimeout(() => {
                        canvas.defaultCursor = 'grab';
                        canvas.hoverCursor = 'grab';
                        canvas.setCursor('grab');
                    }, 50);
                } else {
                    console.warn('⚠️ Photo cible non trouvée:', obj.targetPhotoName);
                }
                
                canvas.requestRenderAll();
                return false;
            }
            
            // Ramassage des papiers
            if (obj.isPaper) {
                opt.e.preventDefault();
                opt.e.stopPropagation();
                canvas.discardActiveObject();
                
                const paperId = obj.paperId;
                
                if (!paperId) {
                    console.warn('⚠️ Papier sans ID, impossible d\'enregistrer');
                    return false;
                }
                
                // Vérifier si le papier a déjà été trouvé
                if (obj.isFound) {
                    console.log('ℹ️ Papier déjà trouvé - ID:', paperId);
                    return false;
                }
                
                // Vérifier le quota SAUF si l'équipe a déjà trouvé tous ses papiers
                if (quotaReached && foundPapersTeam < totalPapers) {
                    console.log('🔒 Quota atteint ! Impossible de trouver plus de papiers');
                    showQuotaWarning();
                    return false;
                }
                
                // Marquer temporairement comme "en cours de traitement" pour éviter les double-clics
                obj.isProcessing = true;
                obj.evented = false; // Désactiver temporairement les événements
                
                console.log('📄 Papier ramassé - ID:', paperId);
                
                // Envoyer au serveur pour enregistrer dans papers_found_user
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=paper_found&paper_id=' + paperId + '&day_id=' + <?php echo $currentGameDay; ?>
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        if (result.new_find) {
                            console.log('✅ Papier enregistré en BDD');
                            
                            // ANIMATION DE FÉLICITATIONS + CONFETTIS
                            showCongratulations();
                            
                            foundPapersMe++; // Incrémenter le compteur local
                            
                            // Vérifier si le quota est maintenant atteint
                            if (quotaPerUser > 0 && foundPapersMe >= quotaPerUser) {
                                quotaReached = true;
                                console.log('🔒 Quota personnel atteint:', foundPapersMe, '/', quotaPerUser);
                            }
                            
                            updatePaperCount();
                            
                            // Appliquer immédiatement le style "trouvé" au papier
                            if (result.found_by_display && result.found_at && result.team_color) {
                                applyFoundStyle(paperId, result.found_by_display, result.found_at, result.team_color, result.team_img, result.team_pole, true);
                            }
                            
                            // Vérifier si l'énigme a été débloquée
                            if (result.enigma_unlocked) {
                                console.log('🔓 ÉNIGME DÉBLOQUÉE ! Tous les papiers ont été trouvés !');
                                
                                // Afficher une notification spéciale après les félicitations
                                setTimeout(() => {
                                    showEnigmaUnlockedNotification();
                                }, 3500);
                                
                                // Masquer immédiatement tous les papiers non trouvés
                                setTimeout(() => {
                                    hideUnfoundPapers();
                                }, 500);
                                
                                // Afficher le bouton "Résoudre l'énigme"
                                const enigmaBtn = document.getElementById('enigma-btn');
                                if (enigmaBtn) {
                                    enigmaBtn.style.display = 'inline-block';
                                }
                            }
                            
                            // Mettre à jour les données de l'équipe après un délai
                            setTimeout(updateGameData, 500);
                        } else {
                            console.log('ℹ️ Papier déjà trouvé précédemment - Le papier reste non-cliquable');
                            // Marquer définitivement comme trouvé
                            obj.isFound = true;
                            obj.isProcessing = false;
                            // Le papier reste non-cliquable (evented = false)
                        }
                    } else {
                        console.error('❌ Erreur enregistrement papier:', result.message);
                        // Réactiver le papier en cas d'erreur serveur
                        obj.isProcessing = false;
                        obj.evented = true;
                    }
                })
                .catch(error => {
                    console.error('❌ Erreur AJAX:', error);
                    // Réactiver le papier en cas d'erreur réseau
                    obj.isProcessing = false;
                    obj.evented = true;
                });
                
                return false;
            }
        });
        
        // ========== CHARGEMENT D'IMAGE DE FOND ==========
        function pathToKey(p) {
            const base = (p || '').split('/').pop() || '';
            return base.includes('.') ? base.substring(0, base.lastIndexOf('.')) : base;
        }
        
        function setBackgroundImage(src) {
            if (!src) return;
            
            currentBackgroundKey = pathToKey(src);
            console.log('🔄 Chargement de:', src, 'clé:', currentBackgroundKey);
            
            const canvasContainer = document.getElementById('game-canvas-container');
            const loadingLogo = document.getElementById('loading-logo');
            const loadingLogoContainer = document.getElementById('loading-logo-container');
            
            // Étape 1: Fondu de l'image actuelle vers le noir + affichage du logo
            canvasContainer.classList.add('loading');
            loadingLogo.classList.add('show');
            
            // Attendre que le fondu soit terminé avant de charger la nouvelle image
            setTimeout(() => {
                // Masquer le canvas mais garder le logo visible pendant TOUT le chargement
                canvasContainer.style.visibility = 'hidden';
                
                // Nettoyer le canvas
                canvas.getObjects().slice().forEach(o => canvas.remove(o));
                
                fabric.Image.fromURL(
                    src,
                    function (img) {
                        backgroundImage = img;
                        backgroundImage.set({
                            left: 0,
                            top: 0,
                            scaleX: 1,
                            scaleY: 1,
                            originX: 'left',
                            originY: 'top',
                            selectable: false,
                            evented: false
                        });
                        canvas.add(backgroundImage);
                        canvas.sendToBack(backgroundImage);
                        applyBaseViewport();
                        isAtBaseZoom = true;
                        canvas.requestRenderAll();
                        console.log('✅ Image de fond chargée:', src);
                        
                        // Charger les données et masquer le fondu quand tout est prêt
                        loadFromServer().then(() => {
                            // Étape 3: Fondu du noir vers la nouvelle image + masquer le logo
                            loadingLogo.classList.remove('show');
                            canvasContainer.style.visibility = 'visible';
                            canvasContainer.classList.remove('loading');
                        });
                    },
                    { crossOrigin: 'anonymous' }
                );
            }, 600);
        }
        
        // ========== CHARGEMENT DES DONNÉES ==========
        function loadFromServer() {
            console.log('📂 Chargement des données pour:', currentBackgroundKey);
            
            return fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=load&key=' + encodeURIComponent(currentBackgroundKey)
            })
            .then(response => response.json())
            .then(result => {
                const dataStr = result && result.success ? result.data : null;
                
                if (!dataStr) {
                    console.log('ℹ️ Aucune donnée pour', currentBackgroundKey);
                    return Promise.resolve();
                }
                
                let savedObjects = [];
                try { savedObjects = JSON.parse(dataStr) || []; } catch(e) { savedObjects = []; }
                
                if (!Array.isArray(savedObjects) || savedObjects.length === 0) {
                    console.log('ℹ️ Tableau vide pour', currentBackgroundKey);
                    return Promise.resolve();
                }
                
                console.log(`📂 Chargement de ${savedObjects.length} objets`);
                
                // foundPapersTeam et totalPapers seront mis à jour via AJAX depuis la BDD
                // foundPapersMe reste local et persistant entre les photos
                // On met à jour les données depuis l'API
                updateGameData();
                
                savedObjects.forEach(objData => {
                    if (objData.type === 'mask') {
                        recreateMask(objData);
                    } else if (objData.type === 'arrow') {
                        recreateArrow(objData);
                    } else if (objData.type === 'paper') {
                        recreatePaper(objData);
                    }
                });
                
                // Après avoir chargé tous les papiers, vérifier lesquels ont été trouvés
                return new Promise(resolve => {
                    setTimeout(() => {
                        checkFoundPapers();
                        resolve();
                    }, 500);
                });
            })
            .catch(error => {
                console.error('❌ Erreur de chargement:', error);
                return Promise.resolve(); // Continuer même en cas d'erreur
            });
        }
        
        // Vérifier quels papiers ont été trouvés et appliquer le style
        // Cette fonction récupère les papiers trouvés sur TOUS les jours pour que les drapeaux restent visibles
        function checkFoundPapers() {
            fetch('game_check_found_papers.php?day=' + <?php echo $currentGameDay; ?>, {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.found_papers) {
                    data.found_papers.forEach(foundPaper => {
                        // Appliquer le style "trouvé" pour tous les papiers trouvés, peu importe le jour
                        applyFoundStyle(foundPaper.id_paper, foundPaper.found_by_display, foundPaper.found_at, foundPaper.team_color, foundPaper.team_img, foundPaper.team_pole, false);
                    });
                    console.log('🏁 Drapeaux appliqués pour', data.found_papers.length, 'papiers trouvés sur tous les jours');
                }
            })
            .catch(error => {
                console.error('❌ Erreur vérification papiers trouvés:', error);
            });
        }
        
        // Fonction pour afficher la pop-up d'information
        function showPaperPopup(foundBy, foundAt, teamColor, teamImg = null, teamPole = null) {
            const popup = document.getElementById('paper-info-popup');
            const overlay = document.getElementById('popup-overlay');
            const content = document.getElementById('popup-content');
            
            // Construire le HTML avec l'icône du personnage
            let htmlContent = '';
            
            if (teamImg) {
                htmlContent += `
                    <div style="margin-bottom: 20px;">
                        <img src="${teamImg}" alt="Personnage" style="width: 80px; height: 80px; object-fit: contain; border-radius: 50%; border: 3px solid ${teamColor}; background: linear-gradient(135deg, ${teamColor}, rgba(255,255,255,0.2));">
                    </div>
                `;
            }
            
            if (teamPole) {
                htmlContent += `
                    <div style="font-size: 1rem; color: ${teamColor}; font-weight: bold; margin-bottom: 10px;">
                        ${teamPole}
                    </div>
                `;
            }
            
            htmlContent += `
                <div style="font-size: 1.2rem; margin-bottom: 15px;">
                    Trouvé par <strong>${foundBy}</strong>
                </div>
                <div style="font-size: 1rem; color: #ccc;">
                    ${foundAt}
                </div>
            `;
            
            content.innerHTML = htmlContent;
            
            popup.style.borderColor = teamColor || '#888';
            popup.classList.add('show');
            overlay.classList.add('show');
        }
        
        function closePaperPopup() {
            const popup = document.getElementById('paper-info-popup');
            const overlay = document.getElementById('popup-overlay');
            
            popup.classList.remove('show');
            overlay.classList.remove('show');
        }
        
        // Fonction pour afficher l'avertissement de quota
        function showQuotaWarning() {
            const popup = document.getElementById('quota-warning-popup');
            const content = document.getElementById('quota-warning-content');
            
            content.innerHTML = `
                Vous avez déjà trouvé <strong>${foundPapersMe} papier${foundPapersMe > 1 ? 's' : ''}</strong>.<br>
                Votre quota est atteint.<br><br>
                Aux autres membres du groupe de trouver !
            `;
            
            popup.classList.add('show');
            
            // Masquer après 4 secondes
            setTimeout(() => {
                popup.classList.remove('show');
            }, 4000);
        }
        
        // Fermer la pop-up en cliquant sur l'overlay
        document.getElementById('popup-overlay')?.addEventListener('click', closePaperPopup);
        
        // Fermer la pop-up avec la touche Échap
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closePaperPopup();
            }
        });
        
        // Fonction pour afficher les félicitations avec confettis
        function showCongratulations() {
            const congratsOverlay = document.getElementById('congrats-overlay');
            
            // Afficher le message
            congratsOverlay.classList.add('show');
            
            // Lancer les confettis
            launchConfetti();
            
            // Masquer après 3 secondes
            setTimeout(() => {
                congratsOverlay.classList.remove('show');
            }, 3000);
        }
        
        // Fonction pour lancer les confettis
        function launchConfetti() {
            const duration = 3000;
            const animationEnd = Date.now() + duration;
            const defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 25000 };
            
            function randomInRange(min, max) {
                return Math.random() * (max - min) + min;
            }
            
            const interval = setInterval(function() {
                const timeLeft = animationEnd - Date.now();
                
                if (timeLeft <= 0) {
                    return clearInterval(interval);
                }
                
                const particleCount = 50 * (timeLeft / duration);
                
                // Confettis depuis la gauche
                confetti(Object.assign({}, defaults, {
                    particleCount,
                    origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 }
                }));
                
                // Confettis depuis la droite
                confetti(Object.assign({}, defaults, {
                    particleCount,
                    origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 }
                }));
            }, 250);
        }
        
        // Fonction pour afficher la notification d'énigme débloquée
        function showEnigmaUnlockedNotification() {
            const overlay = document.getElementById('enigma-unlocked-overlay');
            
            // Afficher la notification
            overlay.classList.add('show');
            
            // Lancer des confettis dorés
            launchGoldenConfetti();
            
            // Masquer après 5 secondes
            setTimeout(() => {
                overlay.classList.remove('show');
            }, 5000);
        }
        
        // Fonction pour lancer des confettis dorés (pour l'énigme débloquée)
        function launchGoldenConfetti() {
            const duration = 5000;
            const animationEnd = Date.now() + duration;
            
            const interval = setInterval(function() {
                const timeLeft = animationEnd - Date.now();
                
                if (timeLeft <= 0) {
                    return clearInterval(interval);
                }
                
                const particleCount = 30 * (timeLeft / duration);
                
                // Confettis dorés depuis le centre-haut
                confetti({
                    particleCount,
                    angle: 90,
                    spread: 100,
                    origin: { x: 0.5, y: 0 },
                    colors: ['#FFD700', '#FFA500', '#FFFF00', '#FF8C00'],
                    zIndex: 35000
                });
            }, 300);
        }
        
        // Appliquer le style "trouvé" à un papier
        function applyFoundStyle(paperId, foundBy, foundAt, teamColor, teamImg = null, teamPole = null, animate = true) {
            // Trouver le papier sur le canvas
            const papers = canvas.getObjects().filter(obj => obj.isPaper && obj.paperId === paperId);
            
            if (papers.length === 0) {
                console.warn('⚠️ Papier ID', paperId, 'non trouvé sur le canvas');
                return;
            }
            
            const paper = papers[0];
            
            // Marquer comme trouvé pour éviter de re-styler (sauf si c'est le premier appel)
            if (paper.isFound && !paper.isProcessing) {
                console.log('⚠️ Papier déjà stylisé - ID:', paperId);
                return;
            }
            
            // Marquer définitivement comme trouvé
            paper.isFound = true;
            paper.isProcessing = false;
            paper.evented = false; // Désactiver les événements pour rendre le papier non-cliquable
            paper.foundBy = foundBy;
            paper.foundAt = foundAt;
            paper.teamColor = teamColor;
            paper.teamImg = teamImg;
            paper.teamPole = teamPole;
            
            // 1. Ajouter un point de couleur au centre du papier
            const dot = new fabric.Circle({
                radius: 15,
                fill: teamColor || '#888',
                left: paper.left,
                top: paper.top,
                originX: 'center',
                originY: 'center',
                selectable: false,
                evented: false,
                shadow: new fabric.Shadow({
                    color: 'rgba(0,0,0,0.6)',
                    blur: 8,
                    offsetX: 0,
                    offsetY: 2
                })
            });
            
            // 2. Créer un drapeau avec cercle de couleur AU CENTRE du papier
            const flagBg = new fabric.Circle({
                radius: 30,
                fill: teamColor || '#888',
                originX: 'center',
                originY: 'center',
                shadow: new fabric.Shadow({
                    color: 'rgba(0,0,0,0.7)',
                    blur: 12,
                    offsetX: 0,
                    offsetY: 4
                })
            });
            
            const flagEmoji = new fabric.Text('🚩', {
                fontSize: 35,
                originX: 'center',
                originY: 'center',
                left: 0,
                top: 0
            });
            
            const flag = new fabric.Group([flagBg, flagEmoji], {
                left: paper.left,
                top: paper.top,
                originX: 'center',
                originY: 'center',
                selectable: false,
                evented: true,
                hoverCursor: 'pointer'
            });
            
            // Ajouter l'événement de clic sur le drapeau
            flag.on('mousedown', function(opt) {
                opt.e.preventDefault();
                opt.e.stopPropagation();
                showPaperPopup(foundBy, foundAt, teamColor, teamImg, teamPole);
                return false;
            });
            
            // Stocker les références
            paper.foundDot = dot;
            paper.foundFlag = flag;
            
            // Ajouter au canvas
            canvas.add(dot);
            canvas.add(flag);
            canvas.bringToFront(flag);
            canvas.renderAll();
            
            console.log('✨ Style "trouvé" appliqué au papier ID', paperId, '- Point et drapeau au centre');
        }
        
        // ========== RECRÉATION DES OBJETS ==========
        function recreateMask(maskData) {
            const savedPoints = maskData.originalPoints;
            const savedHandles = maskData.curveHandles;
            
            let finalPoints = [];
            for (let i = 0; i < savedPoints.length; i++) {
                const p1 = savedPoints[i];
                const p2 = savedPoints[(i + 1) % savedPoints.length];
                const handle = savedHandles[i];
                
                if (handle) {
                    const steps = 15;
                    for (let t = 0; t < steps; t++) {
                        const tt = t / steps;
                        const mt = 1 - tt;
                        const x = mt * mt * p1.x + 2 * mt * tt * handle.x + tt * tt * p2.x;
                        const y = mt * mt * p1.y + 2 * mt * tt * handle.y + tt * tt * p2.y;
                        finalPoints.push({ x, y });
                    }
                } else {
                    finalPoints.push(p1);
                }
            }
            
            const points = finalPoints.length > 0 ? finalPoints : savedPoints;
            const clipPolygon = new fabric.Polygon(points, { fill: "transparent", stroke: "transparent" });
            const bounds = clipPolygon.getBoundingRect();
            
            const tempCanvas = document.createElement('canvas');
            const ctx = tempCanvas.getContext('2d');
            tempCanvas.width = bounds.width;
            tempCanvas.height = bounds.height;
            
            ctx.beginPath();
            points.forEach((point, index) => {
                const x = point.x - bounds.left;
                const y = point.y - bounds.top;
                if (index === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            });
            ctx.closePath();
            ctx.clip();
            
            const imgElement = backgroundImage._element;
            const imgScale = backgroundImage.scaleX;
            const imgLeft = backgroundImage.left || 0;
            const imgTop = backgroundImage.top || 0;
            
            ctx.drawImage(
                imgElement,
                (bounds.left - imgLeft) / imgScale,
                (bounds.top - imgTop) / imgScale,
                bounds.width / imgScale,
                bounds.height / imgScale,
                0, 0, bounds.width, bounds.height
            );
            
            fabric.Image.fromURL(tempCanvas.toDataURL(), (cutoutImg) => {
                cutoutImg.set({
                    left: bounds.left,
                    top: bounds.top,
                    selectable: false,
                    evented: true, // Bloquer les clics sur les papiers en dessous
                    originX: 'left',
                    originY: 'top'
                });
                
                const maskGroup = new fabric.Group([cutoutImg], {
                    left: bounds.left,
                    top: bounds.top,
                    selectable: false,
                    evented: true,
                    lockMovementX: true,
                    lockMovementY: true,
                    lockRotation: true,
                    lockScalingX: true,
                    lockScalingY: true,
                    hasControls: false,
                    hasBorders: false,
                    subTargetCheck: true,
                    perPixelTargetFind: true,
                    targetFindTolerance: 0,
                    maskData: {
                        originalPoints: savedPoints,
                        curveHandles: savedHandles,
                        isMask: true,
                        dbId: maskData.id || null,
                        zIndex: maskData.zIndex || 0
                    }
                });
                
                canvas.add(maskGroup);
                canvas.renderAll();
            });
        }
        
        function recreateArrow(arrowData) {
            if (!arrowDataUrl) return;
            
            fabric.Image.fromURL(arrowDataUrl, (arrowImg) => {
                arrowImg.set({
                    left: arrowData.left,
                    top: arrowData.top,
                    angle: arrowData.angle || 0,
                    selectable: false,
                    evented: true,
                    hasControls: false,
                    hasBorders: false,
                    originX: 'center',
                    originY: 'center',
                    lockScalingX: true,
                    lockScalingY: true,
                    lockMovementY: true,
                    hasRotatingPoint: false,
                    isArrow: true,
                    targetPhotoName: arrowData.targetPhotoName || null,
                    hoverCursor: 'pointer'
                });
                
                canvas.add(arrowImg);
                canvas.bringToFront(arrowImg);
                canvas.renderAll();
            });
        }
        
        function recreatePaper(paperData) {
            if (!paperDataUrl) return;
            
            fabric.Image.fromURL(paperDataUrl, (paperImg) => {
                paperImg.set({
                    left: 0,
                    top: 0,
                    scaleX: 0.25,
                    scaleY: 0.25,
                    selectable: false,
                    evented: false,
                    originX: 'center',
                    originY: 'center'
                });
                
                const paperWidth = paperImg.width * 0.25;
                const paperHeight = paperImg.height * 0.25;
                
                const paperGroup = new fabric.Group([paperImg], {
                    left: paperData.left,
                    top: paperData.top,
                    originX: 'center',
                    originY: 'center',
                    scaleX: paperData.scaleX || 1,
                    scaleY: paperData.scaleY || 1,
                    angle: paperData.angle || 0,
                    selectable: false,
                    evented: true,
                    hasControls: false,
                    hasBorders: false,
                    subTargetCheck: false,
                    hoverCursor: 'pointer',
                    isPaper: true,  // Marqueur pour identifier les papiers
                    paperId: paperData.id || null // Stocker l'ID de la BDD
                });
                
                canvas.add(paperGroup);
                canvas.renderAll();
            });
        }
        
        // ========== MISE À JOUR EN TEMPS RÉEL DES PAPIERS ÉQUIPE ==========
        function updateGameData() {
            fetch('game_data_real_time.php?day=' + <?php echo $currentGameDay; ?>)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        console.error('Erreur lors de la récupération des données du jeu');
                        return;
                    }
                    
                    // Mettre à jour les variables globales
                    foundPapersTeam = data.papers_found_team;
                    totalPapers = data.papers_total;
                    foundPapersMe = data.papers_found_me; // Synchroniser avec la BDD
                    quotaPerUser = data.quota_per_user || 0;
                    quotaReached = data.quota_reached || false;
                    
                    // Mettre à jour l'affichage
                    updatePaperCount();
                    
                    // Mettre à jour le bouton d'énigme selon le statut
                    updateEnigmaButton(data.enigma_status);
                    
                    // Vérifier si l'équipe a atteint son quota (prioritaire sur le quota individuel)
                    if (foundPapersTeam >= totalPapers) {
                        console.log('🎯 Quota équipe atteint ! Masquage des papiers non trouvés...');
                        hideUnfoundPapers();
                    }
                    
                    console.log('📊 Données jeu mises à jour - Équipe:', foundPapersTeam, '/', totalPapers, '| Moi:', foundPapersMe, '| Quota:', quotaPerUser === 0 ? 'illimité' : quotaPerUser, '| Atteint:', quotaReached, '| Énigme:', data.enigma_status);
                })
                .catch(error => {
                    console.error('Erreur AJAX game:', error);
                });
        }
        
        // Fonction pour mettre à jour le bouton d'énigme selon le statut
        function updateEnigmaButton(enigmaStatus) {
            const enigmaSection = document.querySelector('.bar-section:nth-child(5)'); // 5ème section (énigme)
            if (!enigmaSection) return;
            
            let buttonHTML = '';
            
            if (enigmaStatus == 0) {
                // Énigme verrouillée
                buttonHTML = '<div class="btn-back" style="background: linear-gradient(135deg, #666 0%, #888 100%); cursor: not-allowed; opacity: 0.6;">🔒 Énigme verrouillée</div>';
            } else if (enigmaStatus == 1) {
                // Énigme déverrouillée
                buttonHTML = '<a href="enigme.php?day=' + <?php echo $currentGameDay; ?> + '" class="btn-back" style="background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%);">🎭 Résoudre l\'énigme</a>';
            } else {
                // Énigme résolue
                buttonHTML = '<a href="enigme.php?day=' + <?php echo $currentGameDay; ?> + '" class="btn-back" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">✅ Énigme résolue</a>';
            }
            
            enigmaSection.innerHTML = buttonHTML;
        }
        
        // Fonction pour masquer tous les papiers non trouvés
        function hideUnfoundPapers() {
            const papers = canvas.getObjects().filter(obj => obj.isPaper);
            
            papers.forEach(paper => {
                if (!paper.isFound) {
                    // Masquer le papier (le rendre invisible et non-cliquable)
                    paper.set({
                        visible: false,
                        evented: false,
                        selectable: false
                    });
                    console.log('👻 Papier ID', paper.paperId, 'masqué (non trouvé)');
                }
            });
            
            canvas.renderAll();
            console.log('✅ Tous les papiers non trouvés ont été masqués');
        }
        
        // ========== INITIALISATION ==========
        const defaultPath = 'rooms/P1080905.JPG';
        const initialImage = roomImages.includes(defaultPath) ? defaultPath : (roomImages[0] || null);
        if (initialImage) {
            setBackgroundImage(initialImage);
        }
        
        // Lancer la première mise à jour après 1 seconde (laisser le temps au canvas de se charger)
        setTimeout(updateGameData, 1000);
        
        // Puis mettre à jour toutes les 10 secondes
        setInterval(updateGameData, 10000);
        
        // Vérifier les papiers trouvés toutes les 15 secondes (pour voir ceux des autres joueurs)
        setInterval(checkFoundPapers, 15000);
        
        // ========== SYSTÈME DE NOTIFICATIONS PAPIERS RÉCENTS ==========
        
        // Tracker les notifications déjà affichées (pour ne pas répéter)
        const shownNotifications = new Set();
        
        function checkRecentPapers() {
            fetch('game_recent_papers.php?day=' + <?php echo $currentGameDay; ?>)
                .then(response => response.json())
                .then(data => {
                    if (!data.success || !data.recent_papers) {
                        return;
                    }
                    
                    // Filtrer les papiers trouvés il y a moins de 60 secondes
                    const recentPapers = data.recent_papers.filter(paper => paper.seconds_ago < 60);
                    
                    // Afficher les nouvelles notifications
                    recentPapers.forEach(paper => {
                        // Ne pas afficher si déjà montré
                        if (!shownNotifications.has(paper.id)) {
                            shownNotifications.add(paper.id);
                            showNotification(paper);
                        }
                    });
                })
                .catch(error => {
                    console.error('❌ Erreur récupération papiers récents:', error);
                });
        }
        
        function showNotification(paper) {
            const container = document.getElementById('notifications-container');
            
            // Créer l'élément de notification
            const notif = document.createElement('div');
            notif.className = 'notification-item';
            notif.style.setProperty('--notif-color', paper.team_color);
            notif.style.borderLeftColor = paper.team_color;
            
            // Construire le contenu
            let avatarContent = '🎮';
            if (paper.team_img) {
                avatarContent = `<img src="${paper.team_img}" alt="${paper.team_name}">`;
            }
            
            notif.innerHTML = `
                <div class="notification-avatar" style="--notif-color: ${paper.team_color};">
                    ${avatarContent}
                </div>
                <div class="notification-content">
                    <div class="notification-name">${paper.display_name}</div>
                    <div class="notification-pole">${paper.pole_name}</div>
                    <div class="notification-action">vient de trouver un papier</div>
                </div>
            `;
            
            // Ajouter au conteneur
            container.appendChild(notif);
            
            console.log('🔔 Notification affichée pour', paper.display_name);
            
            // Masquer et supprimer après 20 secondes
            setTimeout(() => {
                notif.classList.add('hiding');
                setTimeout(() => {
                    if (notif.parentNode) {
                        notif.parentNode.removeChild(notif);
                    }
                }, 300); // Attendre la fin de l'animation
            }, 20000);
        }
        
        // Vérifier les papiers récents toutes les 5 secondes
        setInterval(checkRecentPapers, 5000);
        
        // Première vérification après 2 secondes
        setTimeout(checkRecentPapers, 2000);
        
        console.log('🔄 Mise à jour automatique activée (données: 10s, papiers trouvés: 15s, notifications: 5s)');
    </script>
    <?php endif; ?>
</body>
</html>

