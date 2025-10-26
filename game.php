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
        
        // Date limite : 30 octobre 2025 = Fin du jeu
        $endDate = new DateTime('2025-10-30');
        
        // Si la date courante est >= 30/10/2025, retourner jour 4 (fin de jeu)
        if ($currentDate >= $endDate) {
            return 4;
        }
        
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

// Charger l'image papier doré et la convertir en base64
$paperDorePath = 'papier_dore.png';
$paperDoreData = '';
if (file_exists($paperDorePath)) {
    $paperDoreData = base64_encode(file_get_contents($paperDorePath));
    $paperDoreData = 'data:image/png;base64,' . $paperDoreData;
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
        
        // Vérifier d'abord si c'est un papier doré
        $stmt = $dbConnection->prepare("SELECT paper_type FROM `papers` WHERE id = ?");
        $stmt->execute([$paperId]);
        $paperInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        $isGoldenPaper = $paperInfo && $paperInfo['paper_type'] == 1;
        
        // Vérifier si le papier a déjà été trouvé par quelqu'un (papier déjà résolu)
        $stmt = $dbConnection->prepare("SELECT COUNT(*) as count FROM `papers_found_user` WHERE id_paper = ? AND id_day = ?");
        $stmt->execute([$paperId, $dayId]);
        $paperAlreadyFound = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si le papier a déjà été trouvé par quelqu'un, ne pas comptabiliser la découverte
        if ($paperAlreadyFound && $paperAlreadyFound['count'] > 0) {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Papier déjà résolu - non comptabilisé']);
            exit;
        }
        
        // Insérer dans papers_found_user
        $stmt = $dbConnection->prepare("INSERT INTO `papers_found_user` (id_paper, id_player, id_day) VALUES (?, ?, ?)");
        $stmt->execute([$paperId, $playerId, $dayId]);
        
        $inserted = $stmt->rowCount() > 0;
        
                if ($inserted) {
                    // Récupérer les infos du joueur et de son groupe
                    $stmt = $dbConnection->prepare("SELECT u.username, u.firstname, u.lastname, u.group_id, g.color, g.img_path, g.pole_name FROM `users` u LEFT JOIN `groups` g ON u.group_id = g.id WHERE u.id = ?");
                    $stmt->execute([$playerId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user && $user['group_id']) {
                        // Incrémenter total_founded dans total_papers_found_group UNIQUEMENT si ce n'est PAS un papier doré
                        if (!$isGoldenPaper) {
                            $stmt = $dbConnection->prepare("UPDATE `total_papers_found_group` SET total_founded = total_founded + 1 WHERE id_group = ? AND id_day = ?");
                            $stmt->execute([$user['group_id'], $dayId]);
                        }
                        
                        // CHRONOMÉTRAGE : Si c'est le premier papier trouvé pour cette énigme, démarrer le chrono
                        $stmt = $dbConnection->prepare("SELECT id FROM `enigmes` WHERE id_group = ? AND id_day = ?");
                        $stmt->execute([$user['group_id'], $dayId]);
                        $enigma = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($enigma && !$isGoldenPaper) {
                            // Vérifier si c'est le premier papier trouvé (total_founded = 1 après l'incrémentation)
                            // UNIQUEMENT pour les papiers normaux (pas les papiers dorés)
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
                
                // Vérifier si tous les papiers ont été trouvés (UNIQUEMENT les papiers normaux, pas les papiers dorés)
                $enigmaUnlocked = false;
                if (!$isGoldenPaper) {
                    $stmt = $dbConnection->prepare("SELECT total_to_found, total_founded FROM `total_papers_found_group` WHERE id_group = ? AND id_day = ?");
                    $stmt->execute([$user['group_id'], $dayId]);
                    $paperStats = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($paperStats && $paperStats['total_founded'] >= $paperStats['total_to_found']) {
                        // Tous les papiers normaux ont été trouvés ! Débloquer l'énigme
                        // Mettre à jour le status de l'énigme de 0 à 1 (uniquement si actuellement à 0)
                        $stmt = $dbConnection->prepare("UPDATE `enigmes` SET status = 1 WHERE id_group = ? AND id_day = ? AND status = 0");
                        $stmt->execute([$user['group_id'], $dayId]);
                        
                        if ($stmt->rowCount() > 0) {
                            $enigmaUnlocked = true;
                            error_log("🔓 Énigme débloquée pour le groupe " . $user['group_id'] . " au jour " . $dayId);
                        }
                    }
                }
                
                // Récupérer la datetime de création
                $stmt = $dbConnection->prepare("SELECT created_at FROM `papers_found_user` WHERE id_paper = ? AND id_player = ? AND id_day = ?");
                $stmt->execute([$paperId, $playerId, $dayId]);
                $paperFound = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Formater la date et l'heure
                $datetime = $paperFound ? strtotime($paperFound['created_at']) : time();
                $formattedDateTime = date('d/m/Y', $datetime) . ' à ' . date('H:i:s', $datetime);
                
                // Pour les papiers dorés, ne pas inclure les statistiques de papiers normaux
                if ($isGoldenPaper) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Papier doré enregistré', 
                        'new_find' => true,
                        'found_by' => $user['username'],
                        'found_by_display' => ucfirst(strtolower($user['firstname'])) . ' ' . strtoupper($user['lastname']),
                        'found_at' => $formattedDateTime,
                        'team_color' => $user['color'],
                        'team_img' => $user['img_path'],
                        'team_pole' => $user['pole_name'],
                        'enigma_unlocked' => false, // Les papiers dorés ne débloquent jamais l'énigme
                        'is_golden_paper' => true
                    ]);
                } else {
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
                        'papers_total' => $paperStats ? (int)$paperStats['total_to_found'] : 0,
                        'is_golden_paper' => false
                    ]);
                }
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
            
            // D'abord récupérer TOUS les papiers dorés de toutes les photos pour déterminer l'ordre global
            $stmt = $dbConnection->prepare("SELECT id FROM `papers` WHERE paper_type = 1 ORDER BY id ASC");
            $stmt->execute();
            $allGoldenPapers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Créer un mapping ID -> jour global
            $goldenPaperDayMap = [];
            foreach ($allGoldenPapers as $index => $paper) {
                $goldenPaperDayMap[$paper['id']] = $index + 1; // Jour 1, 2, 3...
            }
            
            // Charger tous les papers de cette photo
            $stmt = $dbConnection->prepare("SELECT id, position_left, position_top, scale_x, scale_y, angle, z_index, paper_type FROM `papers` WHERE photo_id = ? ORDER BY z_index ASC, id ASC");
            $stmt->execute([$photoId]);
            $allPapers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Séparer papiers blancs et dorés
            $papers = [];
            $goldenPapers = [];
            
            foreach ($allPapers as $paper) {
                if ($paper['paper_type'] == 0) {
                    // Papiers blancs : toujours affichés
                    $papers[] = $paper;
                } else {
                    // Papiers dorés : collecter pour traitement spécial
                    $goldenPapers[] = $paper;
                }
            }
            
            // Trier les papiers dorés par ID croissant
            usort($goldenPapers, function($a, $b) {
                return $a['id'] - $b['id'];
            });
            
            // Traiter chaque papier doré selon la logique jour/trouvé
            foreach ($goldenPapers as $goldenPaper) {
                $dayForThisPaper = $goldenPaperDayMap[$goldenPaper['id']]; // Utiliser le mapping global
                
                // Vérifier si ce papier doré a été trouvé (peu importe le jour)
                $stmt = $dbConnection->prepare("SELECT COUNT(*) as found FROM `papers_found_user` WHERE id_paper = ?");
                $stmt->execute([$goldenPaper['id']]);
                $foundResult = $stmt->fetch(PDO::FETCH_ASSOC);
                $isFound = $foundResult['found'] > 0;
                
                if ($dayForThisPaper == $currentGameDay) {
                    // Papier du jour actuel : TOUJOURS l'afficher (quoi qu'il arrive)
                    $papers[] = $goldenPaper;
                } else if ($dayForThisPaper < $currentGameDay) {
                    // Papier d'un jour précédent : l'afficher seulement s'il a été trouvé
                    if ($isFound) {
                        $papers[] = $goldenPaper;
                    }
                } else {
                    // Papier d'un jour futur : ne jamais l'afficher
                }
            }
            
            foreach ($papers as $paper) {
                $result[] = [
                    'type' => 'paper',
                    'id' => $paper['id'],
                    'left' => (float)$paper['position_left'],
                    'top' => (float)$paper['position_top'],
                    'scaleX' => (float)$paper['scale_x'],
                    'scaleY' => (float)$paper['scale_y'],
                    'angle' => (float)$paper['angle'],
                    'zIndex' => (int)$paper['z_index'],
                    'paperType' => (int)$paper['paper_type']
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
                
                // Récupérer TOUS les objets (toutes équipes confondues) pour permettre l'affichage des objets trouvés par toutes les équipes
                $stmt = $dbConnection->prepare("SELECT id, path, title, subtitle, solved_title, solved, id_mask, group_id FROM `items` ORDER BY id ASC");
                $stmt->execute();
                $groupItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $user['group_items'] = $groupItems;
                
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
            position: relative;
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
        
        .notification-close {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #ccc;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.2s ease;
            z-index: 1;
        }
        
        .notification-close:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            transform: scale(1.1);
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
            justify-content: space-between;
            padding: 10px 15px;
            box-sizing: border-box;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-x: auto;
            overflow-y: hidden;
            gap: 8px;
        }
        
        .bar-section {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 100px;
            flex-shrink: 1;
            flex-grow: 1;
        }
        
        .user-info-compact {
            display: flex;
            align-items: center;
            gap: 8px;
            background: <?= htmlspecialchars($user['team_color'] ?? '#2a2a2a') ?>cc;
            padding: 8px 12px;
            border-radius: 8px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: white;
            min-width: 0;
            flex: 1;
        }
        
        .user-info-compact * {
            color: white !important;
        }
        
        .user-avatar-small {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, <?= htmlspecialchars($user['team_color'] ?? '#888') ?>, rgba(255,255,255,0.3));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            border: 2px solid <?= htmlspecialchars($user['team_color'] ?? '#888') ?>;
            flex-shrink: 0;
        }
        
        .user-details-small {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
            flex: 1;
        }
        
        .user-name-small {
            font-size: 0.8rem;
            color: #fff;
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .user-team-small {
            font-size: 0.65rem;
            color: <?= htmlspecialchars($user['team_color'] ?? '#888') ?>;
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.05);
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            min-width: 0;
            flex: 1;
        }
        
        .stat-icon {
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .stat-content {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
            flex: 1;
        }
        
        .stat-label {
            font-size: 0.6rem;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .stat-value {
            font-size: 0.9rem;
            color: #fff;
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Encart des objets du groupe en triangle */
        .group-items-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            padding: 8px 15px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            min-width: 100px;
            max-width: 120px;
            position: relative;
            z-index: 10;
        }
        
        .group-items-title {
            font-size: 0.6rem;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
            text-align: center;
        }
        
        .group-items-triangle {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }
        
        .group-item-row {
            display: flex;
            justify-content: center;
            gap: 3px;
        }
        
        .group-item {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .group-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 3px;
        }
        
        .group-item.solved {
            border-color: #4CAF50;
            background: rgba(76, 175, 80, 0.2);
        }
        
        .group-item.solved::after {
            content: '✓';
            position: absolute;
            top: -2px;
            right: -2px;
            background: #4CAF50;
            color: white;
            border-radius: 50%;
            width: 12px;
            height: 12px;
            font-size: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid white;
        }
        
        .btn-back {
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            white-space: nowrap;
            flex-shrink: 0;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }
        
        /* Styles pour la section des boutons verticaux */
        .buttons-section {
            flex-direction: column;
            align-items: stretch;
            justify-content: center;
            min-width: 120px;
        }
        
        .buttons-container {
            display: flex;
            flex-direction: column;
            gap: 6px;
            align-items: stretch;
            width: 100%;
        }
        
        #enigma-button-container {
            display: flex;
            align-items: stretch;
        }
        
        #enigma-button-container .btn-back {
            width: 100%;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Styles pour les objets du groupe */
        .group-objects-container {
            display: flex;
            gap: 4px;
            align-items: center;
            justify-content: flex-start;
            flex-wrap: wrap;
        }
        
        .group-object-item {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.05);
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }
        
        .group-object-item:hover {
            border-color: rgba(255, 255, 255, 0.4);
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.1);
        }
        
        .group-object-item:active {
            cursor: grabbing;
        }
        
        .group-object-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 3px;
        }
        
        .group-object-item.solved {
            border-color: #4CAF50;
            background: rgba(76, 175, 80, 0.2);
            cursor: not-allowed;
            opacity: 0.7;
            pointer-events: none;
        }
        
        .group-object-item.solved::after {
            content: '✓';
            position: absolute;
            top: -2px;
            right: -2px;
            background: #4CAF50;
            color: white;
            border-radius: 50%;
            width: 10px;
            height: 10px;
            font-size: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid white;
            z-index: 1;
        }
        
        /* Styles pour la modale d'objet résolu */
        #solved-item-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(40, 40, 40, 0.85);
            z-index: 999999;
            align-items: center;
            justify-content: center;
        }
        
        #solved-item-popup {
            display: none;
            background: #00000096;
            border-radius: 15px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            position: relative;
            border: 3px solid #f39c12;
            margin: auto;
            z-index: 9999999;
        }
        
        .solved-item-info {
            padding: 20px;
        }
        
        .solved-item-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            gap: 10px;
        }
        
        .team-badge {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .solved-item-header h3 {
            margin: 0;
            color: #333;
            font-size: 18px;
        }
        
        .solved-item-details p {
            margin: 8px 0;
            color: #666;
            line-height: 1.4;
        }
        
        .solved-item-details strong {
            color: #333;
        }
        
        .object-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #aaa;
        }
        
        /* Image qui suit le curseur */
        .dragging-image {
            position: fixed;
            pointer-events: none;
            z-index: 10000;
            width: 120px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.9;
            transition: none;
            cursor: none;
        }
        
        .dragging-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 8px;
        }
        
        /* Styles responsifs pour la barre du bas */
        @media (max-width: 1200px) {
            #game-bottom-bar {
                padding: 8px 10px;
                gap: 6px;
            }
            
            .bar-section {
                min-width: 80px;
                gap: 6px;
            }
            
            .stat-item {
                padding: 6px 8px;
                gap: 6px;
            }
            
            .stat-label {
                font-size: 0.55rem;
            }
            
            .stat-value {
                font-size: 0.8rem;
            }
            
            .user-info-compact {
                padding: 6px 8px;
                gap: 6px;
            }
            
            .user-avatar-small {
                width: 30px;
                height: 30px;
                font-size: 1rem;
            }
            
            .user-name-small {
                font-size: 0.75rem;
            }
            
            .user-team-small {
                font-size: 0.6rem;
            }
            
            .btn-back {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
            
            .buttons-container {
                gap: 4px;
            }
            
            .buttons-section {
                min-width: 100px;
            }
            
            .group-object-item {
                width: 20px;
                height: 20px;
            }
            
            .group-object-item:hover {
                transform: scale(1.05);
            }
            
            .group-object-item.solved::after {
                width: 8px;
                height: 8px;
                font-size: 6px;
            }
            
            .object-placeholder {
                font-size: 10px;
            }
        }
        
        @media (max-width: 900px) {
            #game-bottom-bar {
                height: 14vh;
                padding: 6px 8px;
                gap: 4px;
            }
            
            .bar-section {
                min-width: 70px;
                gap: 4px;
            }
            
            .stat-item {
                padding: 4px 6px;
                gap: 4px;
            }
            
            .stat-icon {
                font-size: 1rem;
            }
            
            .stat-label {
                font-size: 0.5rem;
            }
            
            .stat-value {
                font-size: 0.75rem;
            }
            
            .user-info-compact {
                padding: 4px 6px;
                gap: 4px;
            }
            
            .user-avatar-small {
                width: 25px;
                height: 25px;
                font-size: 0.9rem;
            }
            
            .user-name-small {
                font-size: 0.7rem;
            }
            
            .user-team-small {
                font-size: 0.55rem;
            }
            
            .btn-back {
                padding: 4px 8px;
                font-size: 0.75rem;
            }
            
            .buttons-container {
                gap: 3px;
            }
            
            .buttons-section {
                min-width: 80px;
            }
            
            .group-object-item {
                width: 18px;
                height: 18px;
            }
            
            .group-object-item:hover {
                transform: scale(1.05);
            }
            
            .group-object-item.solved::after {
                width: 7px;
                height: 7px;
                font-size: 5px;
            }
            
            .object-placeholder {
                font-size: 9px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($currentGameDay >= 4): ?>
            <!-- ========== FIN DU JEU ========== -->
            <div style="display: flex; justify-content: center; align-items: center; min-height: 80vh;">
                <div style="background: rgba(42, 42, 42, 0.95); border-radius: 20px; padding: 50px; max-width: 800px; text-align: center; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5); border: 3px solid rgba(255, 255, 255, 0.1);">
                    <h1 style="font-size: 3rem; color: #fff; margin-bottom: 30px; text-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);">
                        🎉 Merci d'avoir participé à cet événement Cluedo
                    </h1>
                    
                    <p style="font-size: 1.5rem; color: #ccc; margin-bottom: 30px; line-height: 1.8;">
                        Il n'est plus possible de jouer. Les trois journées sont terminées et closes.
                    </p>
                    
                    <p style="font-size: 1.2rem; color: #888; margin-bottom: 50px;">
                        Veuillez retrouver le classement général par équipe et individuel en cliquant sur les boutons ci-dessous :
                    </p>
                    
                    <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                        <a href="ranking" style="display: inline-block; padding: 15px 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 12px; font-weight: bold; font-size: 1.1rem; transition: transform 0.3s ease; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                            🏆 Classement par équipe
                        </a>
                        
                        <a href="ranking-individual" style="display: inline-block; padding: 15px 40px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; text-decoration: none; border-radius: 12px; font-weight: bold; font-size: 1.1rem; transition: transform 0.3s ease; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                            👤 Classement individuel
                        </a>
                    </div>
                </div>
            </div>
        <?php elseif ($show_activation_form): ?>
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
            
            <!-- Pop-up d'information objet trouvé -->
            <div id="solved-item-overlay"></div>
            <div id="solved-item-popup">
                <button class="popup-close" onclick="closeSolvedItemModal()">&times;</button>
                <div class="popup-title">🎯 Objet placé</div>
                <div class="popup-content" id="solved-item-content">
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
                
                <!-- Section des objets du groupe -->
                <div class="bar-section">
                    <div class="stat-item">
                        <div class="stat-icon">🎯</div>
                        <div class="stat-content">
                            <div class="stat-label">
                                Objets du groupe 
                                <?php 
                                // Calculer le compteur seulement pour les objets de l'équipe courante
                                $currentTeamItems = array_filter($user['group_items'] ?? [], function($item) use ($user) {
                                    return $item['group_id'] == $user['group_id'];
                                });
                                $totalItems = count($currentTeamItems);
                                $solvedItems = 0;
                                foreach ($currentTeamItems as $item) {
                                    if ($item['solved']) {
                                        $solvedItems++;
                                    }
                                }
                                ?>
                                <span id="objects-counter" style="color: #f39c12; font-weight: bold;">(<?= $solvedItems ?>/<?= $totalItems ?>)</span>
                            </div>
                            <div class="group-objects-container">
                                <?php if (!empty($user['group_items'])): ?>
                                    <?php 
                                    // Filtrer pour ne montrer que les objets de l'équipe courante dans la barre du bas
                                    $currentTeamItems = array_filter($user['group_items'], function($item) use ($user) {
                                        return $item['group_id'] == $user['group_id'];
                                    });
                                    ?>
                                    <?php foreach ($currentTeamItems as $item): ?>
                                        <div class="group-object-item <?= $item['solved'] ? 'solved' : '' ?>" 
                                             data-item-id="<?= $item['id'] ?>"
                                             data-item-title="<?= htmlspecialchars($item['title']) ?>"
                                             data-item-solved-title="<?= htmlspecialchars($item['solved_title'] ?? '') ?>"
                                             data-item-solved="<?= $item['solved'] ? 'true' : 'false' ?>"
                                             data-item-mask-id="<?= $item['id_mask'] ?? '' ?>">
                                            <?php 
                                            // Construire le chemin correct de l'image
                                            $imagePath = '';
                                            if (!empty($item['path'])) {
                                                // Si le chemin commence par 'assets/', utiliser tel quel
                                                if (strpos($item['path'], 'assets/') === 0) {
                                                    $imagePath = $item['path'];
                                                } else {
                                                    // Sinon, construire le chemin depuis assets/img/items/
                                                    $imagePath = 'assets/img/items/' . basename($item['path']);
                                                }
                                            } else {
                                                // Si pas de chemin, essayer avec l'ID
                                                $imagePath = 'assets/img/items/' . $item['id'] . '.png';
                                            }
                                            
                                            // Vérifier si l'image existe
                                            if (file_exists($imagePath)): ?>
                                                <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($item['title']) ?>" title="<?= htmlspecialchars($item['solved'] ? $item['solved_title'] : $item['title']) ?>">
                                            <?php else: ?>
                                                <div class="object-placeholder">🎯</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="object-placeholder">🎯</div>
                                    <div class="object-placeholder">🎯</div>
                                    <div class="object-placeholder">🎯</div>
                                <?php endif; ?>
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
                        </div>
                    </div>
                </div>
                
                <div class="bar-section buttons-section">
                    <div class="buttons-container">
                        <div id="enigma-button-container">
                            <!-- Le bouton d'énigme sera inséré ici par JavaScript -->
                        </div>
                        <a href="/teams" class="btn-back" style="background: #4A90E2; text-align: center;">
                            🏆 ÉQUIPES
                        </a>
                    </div>
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
        const paperDoreDataUrl = <?php echo json_encode($paperDoreData); ?>;
        const arrowDataUrl = <?php echo json_encode($arrowData); ?>;
        const currentPhotoId = <?php echo isset($photoId) ? $photoId : 'null'; ?>;
        
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
('🎯 Navigation vers:', obj.targetPhotoName);
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
('ℹ️ Papier déjà trouvé - ID:', paperId);
                    return false;
                }
                
                // Vérifier le quota SAUF si l'équipe a déjà trouvé tous ses papiers
                if (quotaReached && foundPapersTeam < totalPapers) {
('🔒 Quota atteint ! Impossible de trouver plus de papiers');
                    showQuotaWarning();
                    return false;
                }
                
                // Marquer temporairement comme "en cours de traitement" pour éviter les double-clics
                obj.isProcessing = true;
                obj.evented = false; // Désactiver temporairement les événements
                
('📄 Papier ramassé - ID:', paperId);
                
                // Envoyer au serveur pour enregistrer dans papers_found_user
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=paper_found&paper_id=' + paperId + '&day_id=' + <?php echo $currentGameDay; ?>
                })
                .then(response => {
                    // Vérifier si c'est une erreur 400 (Bad Request)
                    if (response.status === 400) {
                        showPaperAlreadyFoundMessage();
                        obj.isProcessing = false;
                        obj.evented = true;
                        return Promise.reject('Paper already found by another user');
                    }
                    return response.json();
                })
                .then(result => {
                    if (result.success) {
                        if (result.new_find) {
('✅ Papier enregistré en BDD');
                            
                            // Vérifier si c'est un papier doré pour l'explosion spéciale
                            if (result.is_golden_paper) {
('🏆 PAPIER DORÉ TROUVÉ ! MEGA EXPLOSION !');
                                launchGoldenPaperExplosion();
                            } else {
                                // ANIMATION DE FÉLICITATIONS + CONFETTIS (UNIQUEMENT pour les papiers normaux)
                                showCongratulations();
                            }
                            
                            // Incrémenter le compteur local UNIQUEMENT pour les papiers normaux
                            if (!result.is_golden_paper) {
                                foundPapersMe++; // Incrémenter le compteur local
                                
                                // Vérifier si le quota est maintenant atteint
                                if (quotaPerUser > 0 && foundPapersMe >= quotaPerUser) {
                                    quotaReached = true;
('🔒 Quota personnel atteint:', foundPapersMe, '/', quotaPerUser);
                                }
                                
                                updatePaperCount();
                            }
                            
                            // Appliquer immédiatement le style "trouvé" au papier
                            if (result.found_by_display && result.found_at && result.team_color) {
                                const foundDay = result.is_golden_paper ? <?php echo $currentGameDay; ?> : null;
                                applyFoundStyle(paperId, result.found_by_display, result.found_at, result.team_color, result.team_img, result.team_pole, true, result.is_golden_paper, foundDay);
                            }
                            
                            // Vérifier si l'énigme a été débloquée (UNIQUEMENT pour les papiers normaux)
                            if (result.enigma_unlocked && !result.is_golden_paper) {
('🔓 ÉNIGME DÉBLOQUÉE ! Tous les papiers normaux ont été trouvés !');
                                
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
('ℹ️ Papier déjà trouvé précédemment - Le papier reste non-cliquable');
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
('🔄 Chargement de:', src, 'clé:', currentBackgroundKey);
            
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
('✅ Image de fond chargée:', src);
                        
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
('📂 Chargement des données pour:', currentBackgroundKey);
            
            return fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=load&key=' + encodeURIComponent(currentBackgroundKey)
            })
            .then(response => response.json())
            .then(result => {
                const dataStr = result && result.success ? result.data : null;
                
                if (!dataStr) {
('ℹ️ Aucune donnée pour', currentBackgroundKey);
                    return Promise.resolve();
                }
                
                let savedObjects = [];
                try { savedObjects = JSON.parse(dataStr) || []; } catch(e) { savedObjects = []; }
                
                if (!Array.isArray(savedObjects) || savedObjects.length === 0) {
('ℹ️ Tableau vide pour', currentBackgroundKey);
                    return Promise.resolve();
                }
                
(`📂 Chargement de ${savedObjects.length} objets`);
                
                // Debug des papiers dorés
                const goldenPapers = savedObjects.filter(obj => obj.type === 'paper' && obj.paperType === 1);
(`🏆 Papiers dorés trouvés: ${goldenPapers.length}`);
                goldenPapers.forEach((paper, index) => {
                    const dayForThisPaper = index + 1;
(`Papier doré ID ${paper.id}: Jour assigné = ${dayForThisPaper}, Jour actuel = ${<?php echo $currentGameDay; ?>}`);
                });
                
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
                        const foundDay = foundPaper.is_golden_paper ? foundPaper.found_day : null;
                        applyFoundStyle(foundPaper.id_paper, foundPaper.found_by_display, foundPaper.found_at, foundPaper.team_color, foundPaper.team_img, foundPaper.team_pole, false, foundPaper.is_golden_paper, foundDay);
                    });
('🏁 Drapeaux appliqués pour', data.found_papers.length, 'papiers trouvés sur tous les jours');
                }
            })
            .catch(error => {
                console.error('❌ Erreur vérification papiers trouvés:', error);
            });
        }
        
        // Fonction pour afficher la pop-up d'information
        function showPaperPopup(foundBy, foundAt, teamColor, teamImg = null, teamPole = null, isGoldenPaper = false) {
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
            
            if (isGoldenPaper) {
                // Message spécial pour les papiers dorés
                // Récupérer le jour où le papier doré a été trouvé depuis les données
                const foundDay = window.goldenPaperFoundDay || <?php echo $currentGameDay; ?>;
                htmlContent += `
                    <div style="font-size: 1.4rem; margin-bottom: 15px; color: #FFD700; font-weight: bold;">
                        ✨ Papier doré trouvé ✨
                    </div>
                    <div style="font-size: 1.1rem; margin-bottom: 15px; color: #FFD700; font-weight: bold;">
                        Jour ${foundDay}
                    </div>
                    <div style="font-size: 1.2rem; margin-bottom: 15px;">
                        Trouvé par <strong>${foundBy}</strong>
                    </div>
                    <div style="font-size: 1rem; color: #ccc;">
                        ${foundAt}
                    </div>
                `;
            } else {
                // Message normal pour les papiers classiques
                htmlContent += `
                    <div style="font-size: 1.2rem; margin-bottom: 15px;">
                        Trouvé par <strong>${foundBy}</strong>
                    </div>
                    <div style="font-size: 1rem; color: #ccc;">
                        ${foundAt}
                    </div>
                `;
            }
            
            content.innerHTML = htmlContent;
            
            if (isGoldenPaper) {
                popup.style.borderColor = '#FFD700';
            } else {
                popup.style.borderColor = teamColor || '#888';
            }
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
        
        // Fonction pour afficher le message "Papier déjà trouvé par un autre utilisateur"
        function showPaperAlreadyFoundMessage() {
            // Créer l'élément de message
            const messageDiv = document.createElement('div');
            messageDiv.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(255, 68, 68, 0.95);
                color: white;
                padding: 20px 40px;
                border-radius: 15px;
                font-size: 1.4rem;
                font-weight: bold;
                z-index: 10000;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.7);
                animation: blinkRed 2s ease-in-out;
                text-align: center;
                max-width: 400px;
                word-wrap: break-word;
            `;
            messageDiv.textContent = 'Un autre utilisateur a déjà trouvé le papier';
            
            // Ajouter l'animation CSS
            const style = document.createElement('style');
            style.textContent = `
                @keyframes blinkRed {
                    0%, 100% { 
                        opacity: 1; 
                        transform: translate(-50%, -50%) scale(1);
                    }
                    25%, 75% { 
                        opacity: 0.7; 
                        transform: translate(-50%, -50%) scale(1.05);
                    }
                    50% { 
                        opacity: 1; 
                        transform: translate(-50%, -50%) scale(1.1);
                    }
                }
            `;
            document.head.appendChild(style);
            
            // Ajouter au DOM
            document.body.appendChild(messageDiv);
            
            // Supprimer après 3 secondes
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.parentNode.removeChild(messageDiv);
                }
                if (style.parentNode) {
                    style.parentNode.removeChild(style);
                }
            }, 3000);
        }

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
        
        // Fonction pour lancer une MEGA EXPLOSION de confettis dorés (pour le papier doré)
        function launchGoldenPaperExplosion() {
            const duration = 8000; // Plus long pour une explosion plus spectaculaire
            const animationEnd = Date.now() + duration;
            
            // Explosion initiale massive
            confetti({
                particleCount: 150,
                angle: 90,
                spread: 360,
                origin: { x: 0.5, y: 0.5 },
                colors: ['#FFD700', '#FFA500', '#FFFF00', '#FF8C00', '#FF6B35', '#FFD23F'],
                shapes: ['circle', 'square', 'triangle'],
                scalar: 2.5,
                zIndex: 40000
            });
            
            // Explosions secondaires
            setTimeout(() => {
                confetti({
                    particleCount: 100,
                    angle: 45,
                    spread: 120,
                    origin: { x: 0.2, y: 0.3 },
                    colors: ['#FFD700', '#FFA500', '#FFFF00'],
                    shapes: ['circle', 'square'],
                    scalar: 2,
                    zIndex: 40000
                });
            }, 500);
            
            setTimeout(() => {
                confetti({
                    particleCount: 100,
                    angle: 135,
                    spread: 120,
                    origin: { x: 0.8, y: 0.3 },
                    colors: ['#FFD700', '#FFA500', '#FFFF00'],
                    shapes: ['circle', 'square'],
                    scalar: 2,
                    zIndex: 40000
                });
            }, 1000);
            
            setTimeout(() => {
                confetti({
                    particleCount: 100,
                    angle: 225,
                    spread: 120,
                    origin: { x: 0.2, y: 0.7 },
                    colors: ['#FFD700', '#FFA500', '#FFFF00'],
                    shapes: ['circle', 'square'],
                    scalar: 2,
                    zIndex: 40000
                });
            }, 1500);
            
            setTimeout(() => {
                confetti({
                    particleCount: 100,
                    angle: 315,
                    spread: 120,
                    origin: { x: 0.8, y: 0.7 },
                    colors: ['#FFD700', '#FFA500', '#FFFF00'],
                    shapes: ['circle', 'square'],
                    scalar: 2,
                    zIndex: 40000
                });
            }, 2000);
            
            // Confettis continus pendant la durée
            const interval = setInterval(function() {
                const timeLeft = animationEnd - Date.now();
                
                if (timeLeft <= 0) {
                    return clearInterval(interval);
                }

                const particleCount = 20 * (timeLeft / duration);
                
                // Confettis dorés depuis le centre
                confetti({
                    particleCount,
                    angle: 90,
                    spread: 360,
                    origin: { x: 0.5, y: 0.5 },
                    colors: ['#FFD700', '#FFA500', '#FFFF00', '#FF8C00'],
                    shapes: ['circle', 'square'],
                    scalar: 1.5,
                    zIndex: 40000
                });
            }, 200);
            
            // Notification spéciale pour le papier doré
            showGoldenPaperNotification();
        }
        
        // Fonction pour afficher la notification spéciale du papier doré
        function showGoldenPaperNotification() {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: linear-gradient(135deg, #FFD700, #FFA500);
                color: #000;
                padding: 30px 50px;
                border-radius: 20px;
                font-size: 2rem;
                font-weight: bold;
                text-align: center;
                z-index: 50000;
                box-shadow: 0 20px 60px rgba(255, 215, 0, 0.8);
                animation: goldenPulse 2s ease-in-out;
                border: 4px solid #FF8C00;
            `;
            
            notification.innerHTML = '🏆 PAPIER DORÉ TROUVÉ ! 🏆';
            
            // Ajouter l'animation CSS
            const style = document.createElement('style');
            style.textContent = `
                @keyframes goldenPulse {
                    0% { transform: translate(-50%, -50%) scale(0.5); opacity: 0; }
                    50% { transform: translate(-50%, -50%) scale(1.2); opacity: 1; }
                    100% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
            
            // Ajouter la notification au DOM
            document.body.appendChild(notification);
            
            // Supprimer la notification après 4 secondes
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
                if (style.parentNode) {
                    style.parentNode.removeChild(style);
                }
            }, 4000);
        }
        
        // Appliquer le style "trouvé" à un papier
        function applyFoundStyle(paperId, foundBy, foundAt, teamColor, teamImg = null, teamPole = null, animate = true, isGoldenPaper = false, foundDay = null) {
            // Trouver le papier sur le canvas
            const papers = canvas.getObjects().filter(obj => obj.isPaper && obj.paperId === paperId);
            
            if (papers.length === 0) {
                console.warn('⚠️ Papier ID', paperId, 'non trouvé sur le canvas');
                return;
            }
            
            const paper = papers[0];
            
            // Pour les papiers dorés, stocker le jour où il a été trouvé
            if (isGoldenPaper && foundDay) {
                window.goldenPaperFoundDay = foundDay;
            }
            
            // Marquer comme trouvé pour éviter de re-styler (sauf si c'est le premier appel)
            if (paper.isFound && !paper.isProcessing) {
('⚠️ Papier déjà stylisé - ID:', paperId);
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
            
            if (isGoldenPaper) {
                // Pour les papiers dorés : créer une auréole avec la couleur de l'équipe et un drapeau avec la couleur de l'équipe
                
                // Créer l'auréole externe avec la couleur de l'équipe
                const outerHalo = new fabric.Circle({
                    radius: 60,
                    fill: 'transparent',
                    stroke: teamColor || '#888',
                    strokeWidth: 8,
                    originX: 'center',
                    originY: 'center',
                    opacity: 0.8,
                    shadow: new fabric.Shadow({
                        color: teamColor || '#888',
                        blur: 20,
                        offsetX: 0,
                        offsetY: 0
                    })
                });
                
                const innerHalo = new fabric.Circle({
                    radius: 45,
                    fill: 'transparent',
                    stroke: teamColor || '#888',
                    strokeWidth: 4,
                    originX: 'center',
                    originY: 'center',
                    opacity: 0.9,
                    shadow: new fabric.Shadow({
                        color: teamColor || '#888',
                        blur: 15,
                        offsetX: 0,
                        offsetY: 0
                    })
                });
                
                // Créer le drapeau avec la couleur de l'équipe
                const flagBg = new fabric.Circle({
                    radius: 45,
                    fill: teamColor || '#888',
                    originX: 'center',
                    originY: 'center',
                    shadow: new fabric.Shadow({
                        color: teamColor ? `${teamColor}80` : 'rgba(136, 136, 136, 0.8)',
                        blur: 15,
                        offsetX: 0,
                        offsetY: 4
                    })
                });
                
                const flagEmoji = new fabric.Text('🏆', {
                    fontSize: 40,
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
                
                const halo = new fabric.Group([outerHalo, innerHalo], {
                    left: paper.left,
                    top: paper.top,
                    originX: 'center',
                    originY: 'center',
                    selectable: false,
                    evented: false
                });
                
                // Ajouter l'événement de clic sur le drapeau
                flag.on('mousedown', function(opt) {
                    opt.e.preventDefault();
                    opt.e.stopPropagation();
                    showPaperPopup(foundBy, foundAt, teamColor, teamImg, teamPole, true); // true = papier doré
                    return false;
                });
                
                // Stocker les références
                paper.foundDot = dot;
                paper.foundFlag = flag;
                paper.foundHalo = halo;
                
                // Ajouter au canvas
                canvas.add(dot);
                canvas.add(halo);
                canvas.add(flag);
                canvas.bringToFront(flag);
                canvas.renderAll();
                
('✨ Style "trouvé" appliqué au PAPIER DORÉ ID', paperId, '- Auréole dorée et drapeau doré au centre');
            } else {
                // Pour les papiers normaux : créer un drapeau rouge classique
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
                
('✨ Style "trouvé" appliqué au papier ID', paperId, '- Point et drapeau au centre');
            }
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
            // Choisir l'image selon le type de papier
            const paperType = paperData.paperType || 0;
            const paperImageUrl = paperType === 1 ? paperDoreDataUrl : paperDataUrl;
            
            if (!paperImageUrl) return;
            
            fabric.Image.fromURL(paperImageUrl, (paperImg) => {
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
('🎯 Quota équipe atteint ! Masquage des papiers non trouvés...');
                        hideUnfoundPapers();
                    }
                    
('📊 Données jeu mises à jour - Équipe:', foundPapersTeam, '/', totalPapers, '| Moi:', foundPapersMe, '| Quota:', quotaPerUser === 0 ? 'illimité' : quotaPerUser, '| Atteint:', quotaReached, '| Énigme:', data.enigma_status);
                })
                .catch(error => {
                    console.error('Erreur AJAX game:', error);
                });
        }
        
        // Fonction pour mettre à jour le bouton d'énigme selon le statut
        function updateEnigmaButton(enigmaStatus) {
            const enigmaContainer = document.getElementById('enigma-button-container');
            if (!enigmaContainer) return;
            
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
            
            enigmaContainer.innerHTML = buttonHTML;
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
('👻 Papier ID', paper.paperId, 'masqué (non trouvé)');
                }
            });
            
            canvas.renderAll();
('✅ Tous les papiers non trouvés ont été masqués');
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
('🔍 Vérification des papiers récents...');
            fetch('game_notifications.php?day=' + <?php echo $currentGameDay; ?>)
                .then(response => {
('🔍 Réponse HTTP:', response.status, response.statusText);
                    return response.json();
                })
                .then(data => {
('📊 Données notifications reçues:', data);
                    
                    if (!data.success || !data.papers) {
('❌ Pas de données valides:', data);
                        return;
                    }
                    
                    // Ne pas filtrer par temps - afficher tous les papiers non encore vus
                    const recentPapers = data.papers.filter(paper => {
                        const paperDate = new Date(paper.datetime);
                        const now = new Date();
                        const secondsAgo = (now - paperDate) / 1000;
(`📄 Papier ${paper.id} (type: ${paper.paper_type}) trouvé il y a ${secondsAgo.toFixed(1)} secondes par ${paper.display_name}`);
                        return true; // Afficher tous les papiers, pas de filtre temporel
                    });
                    
(`🔔 ${recentPapers.length} papiers récents trouvés`);
(`🔔 Papiers récents:`, recentPapers);
                    
                    // Afficher les nouvelles notifications (UNIQUEMENT les papiers normaux)
                    recentPapers.forEach(paper => {
(`🔍 Traitement du papier ${paper.id} (type: ${paper.paper_type})`);
                        
                        // Ne traiter QUE les papiers normaux (paper_type = 0)
                        if (paper.paper_type !== 0) {
(`⏭️ Papier doré ignoré par checkRecentPapers (géré par checkGoldenPaperFound)`);
                            return;
                        }
                        
(`🔍 Papier normal détecté, vérification si déjà affiché...`);
(`🔍 Notifications déjà affichées:`, Array.from(shownNotifications));
                        
                        // Ne pas afficher si déjà montré
                        if (!shownNotifications.has(paper.id)) {
(`🔔 Nouvelle notification pour ${paper.display_name} - AJOUT AU SET`);
                            shownNotifications.add(paper.id);
                            
                            // Papier classique - utiliser la notification normale
(`🔔 Appel de showNotification() pour ${paper.display_name}`);
                            showNotification(paper);
                        } else {
(`⏭️ Notification déjà affichée pour ${paper.display_name} (ID: ${paper.id})`);
                        }
                    });
                })
                .catch(error => {
                    console.error('❌ Erreur récupération notifications:', error);
                });
        }
        
        function showNotification(paper) {
(`🎯 showNotification() appelée pour ${paper.display_name}`);
            const container = document.getElementById('notifications-container');
            
            if (!container) {
                console.error('❌ Conteneur notifications non trouvé !');
                return;
            }
            
(`🎯 Conteneur trouvé, création de la notification...`);
            
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
                <button class="notification-close" onclick="closeNotification(this)" title="Fermer">&times;</button>
            `;
            
            // Ajouter au conteneur
            container.appendChild(notif);
            
('🔔 Notification affichée pour', paper.display_name);
('🔔 Notification ajoutée au DOM, élément:', notif);
('🔔 Conteneur notifications:', container);
('🔔 Conteneur contient maintenant', container.children.length, 'notifications');
            
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
        
        // Fonction pour fermer une notification
        function closeNotification(closeButton) {
            const notification = closeButton.closest('.notification-item');
            if (notification) {
                // Ajouter l'animation de sortie
                notification.classList.add('hiding');
                
                // Supprimer après l'animation
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }
        }

        // Fonction pour afficher la notification d'objet trouvé avec confettis
        function showObjectFoundNotification() {
            // Créer plusieurs explosions de confettis pour un effet "feux d'artifice"
            const colors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff', '#ffa500', '#ff69b4'];
            
            // Explosion principale
            confetti({
                particleCount: 150,
                spread: 70,
                origin: { y: 0.6 },
                colors: colors,
                shapes: ['circle', 'square'],
                scalar: 1.2
            });
            
            // Explosions secondaires après un court délai
            setTimeout(() => {
                confetti({
                    particleCount: 100,
                    spread: 60,
                    origin: { y: 0.4, x: 0.2 },
                    colors: colors,
                    shapes: ['circle', 'square']
                });
                
                confetti({
                    particleCount: 100,
                    spread: 60,
                    origin: { y: 0.4, x: 0.8 },
                    colors: colors,
                    shapes: ['circle', 'square']
                });
            }, 200);
            
            // Explosion finale
            setTimeout(() => {
                confetti({
                    particleCount: 200,
                    spread: 80,
                    origin: { y: 0.5 },
                    colors: colors,
                    shapes: ['circle', 'square'],
                    scalar: 1.5
                });
            }, 400);
            
            // Créer la notification temporaire
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: linear-gradient(135deg, #2c3e50, #34495e);
                color: white;
                padding: 30px 50px;
                border-radius: 15px;
                border: 3px solid #f39c12;
                font-size: 1.5rem;
                font-weight: bold;
                text-align: center;
                z-index: 10000000;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
                animation: fadeInOut 3s ease-in-out forwards;
            `;
            
            notification.innerHTML = '🎯 Emplacement de l\'objet trouvé';
            
            // Ajouter l'animation CSS
            const style = document.createElement('style');
            style.textContent = `
                @keyframes fadeInOut {
                    0% { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
                    20% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
                    80% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
                    100% { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
                }
            `;
            document.head.appendChild(style);
            
            // Ajouter la notification au DOM
            document.body.appendChild(notification);
            
            // Supprimer la notification après 3 secondes
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
                if (style.parentNode) {
                    style.parentNode.removeChild(style);
                }
            }, 3000);
            
('🎆 Confettis et notification d\'objet trouvé affichés');
        }
        
        // Vérifier les papiers récents toutes les 5 secondes
        setInterval(checkRecentPapers, 5000);
        
        // Première vérification après 2 secondes
        setTimeout(checkRecentPapers, 2000);
        
('✅ Système de notifications papiers normaux activé');
        
        // Bouton de test pour vider les notifications déjà affichées (à supprimer en production)
        window.clearNotifications = function() {
            shownNotifications.clear();
('🧹 Notifications déjà affichées vidées');
        };
        
        window.clearGoldenNotifications = function() {
            shownGoldenPaperNotifications.clear();
('🧹 Notifications papiers dorés déjà affichées vidées');
        };
        
('🧪 Fonctions de test disponibles : clearNotifications() et clearGoldenNotifications()');
        
        // ========== SYSTÈME DE NOTIFICATIONS PAPIERS DORÉS ==========
        
        // Tracker les notifications de papiers dorés déjà affichées
        const shownGoldenPaperNotifications = new Set();
        
        function checkGoldenPaperFound() {
('🏆 Vérification des papiers dorés...');
            fetch('golden-paper-notification.php?day=' + <?php echo $currentGameDay; ?>)
                .then(response => {
('🏆 Réponse HTTP:', response.status, response.statusText);
                    return response.json();
                })
                .then(data => {
('🏆 Données papier doré reçues:', data);
                    
                    if (!data.success || !data.found) {
('🏆 Pas de papier doré trouvé récemment');
                        return;
                    }
                    
                    // Vérifier si c'est un nouveau papier doré trouvé
                    const notificationKey = `${data.id_paper}_${data.id_player}_${data.created_at}`;
                    
                    if (!shownGoldenPaperNotifications.has(notificationKey)) {
                        shownGoldenPaperNotifications.add(notificationKey);
                        
                        // Afficher la notification (le joueur actuel est déjà exclu côté serveur)
                        showGoldenPaperFoundNotification(data);
                    }
                })
                .catch(error => {
                    console.error('❌ Erreur récupération papier doré récent:', error);
                });
        }
        
        // Fonction pour formater l'heure de la notification
        function formatNotificationTime(createdAt) {
            const date = new Date(createdAt);
            const now = new Date();
            const diffMs = now - date;
            const diffMinutes = Math.floor(diffMs / (1000 * 60));
            const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
            
            if (diffMinutes < 1) {
                return 'À l\'instant';
            } else if (diffMinutes < 60) {
                return `Il y a ${diffMinutes} min`;
            } else if (diffHours < 24) {
                return `Il y a ${diffHours}h`;
            } else if (diffDays < 7) {
                return `Il y a ${diffDays} jour${diffDays > 1 ? 's' : ''}`;
            } else {
                // Pour les dates plus anciennes, afficher la date complète
                return date.toLocaleDateString('en-US', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
        }
        
        function showGoldenPaperFoundNotification(paper) {
            const container = document.getElementById('notifications-container');
            
            // Créer l'élément de notification spéciale pour le papier doré
            const notif = document.createElement('div');
            notif.className = 'notification-item golden-paper-notification';
            notif.setAttribute('data-notification-id', `golden_${paper.id_paper}_${paper.id_player}_${paper.created_at}`);
            notif.style.setProperty('--notif-color', paper.team_color);
            notif.style.borderLeftColor = paper.team_color;
            notif.style.background = `linear-gradient(135deg, ${paper.team_color}20, ${paper.team_color}40)`;
            notif.style.border = `2px solid ${paper.team_color}`;
            notif.style.boxShadow = `0 6px 20px ${paper.team_color}50`;
            
            // Construire le contenu
            let avatarContent = '🏆';
            if (paper.team_img) {
                avatarContent = `<img src="${paper.team_img}" alt="${paper.team_name}">`;
            }
            
            notif.innerHTML = `
                <div class="notification-avatar" style="--notif-color: ${paper.team_color}; background: linear-gradient(135deg, ${paper.team_color}, rgba(255,255,255,0.2)); border: 2px solid ${paper.team_color};">
                    ${avatarContent}
                </div>
                <div class="notification-content">
                    <div class="notification-name">${paper.display_name}</div>
                    <div class="notification-pole">${paper.pole_name}</div>
                    <div class="notification-action" style="color: ${paper.team_color}; font-weight: bold;">🎉 a trouvé le papier doré ! 🎉 (JOUR ${paper.id_day})</div>
                    <div class="notification-time" style="color: #ccc; font-size: 0.9rem; margin-top: 5px;">${formatNotificationTime(paper.created_at)}</div>
                </div>
                <button class="notification-close" onclick="closeNotification(this)" title="Fermer">&times;</button>
            `;
            
            // Ajouter au conteneur
            container.appendChild(notif);
            
            // Animation d'entrée spéciale
            notif.style.transform = 'translateX(400px)';
            setTimeout(() => {
                notif.style.transform = 'translateX(0)';
            }, 100);
            
            // Stocker la notification dans le localStorage pour la persistance
            const notificationData = {
                id: `golden_${paper.id_paper}_${paper.id_player}_${paper.created_at}`,
                paper: paper,
                timestamp: Date.now()
            };
            
            // Récupérer les notifications existantes
            const existingNotifications = JSON.parse(localStorage.getItem('goldenPaperNotifications') || '[]');
            
            // Vérifier si cette notification n'existe pas déjà
            const exists = existingNotifications.some(notif => notif.id === notificationData.id);
            if (!exists) {
                existingNotifications.push(notificationData);
                localStorage.setItem('goldenPaperNotifications', JSON.stringify(existingNotifications));
            }
            
            // Notification persistante pour les papiers dorés (ne pas supprimer automatiquement)
        }
        
        // Fonction pour restaurer les notifications persistantes au chargement de la page
        function restorePersistentNotifications() {
            const savedNotifications = JSON.parse(localStorage.getItem('goldenPaperNotifications') || '[]');
            
            savedNotifications.forEach(notificationData => {
                // Vérifier si la notification n'existe pas déjà dans le DOM
                const notificationId = `golden_${notificationData.paper.id_paper}_${notificationData.paper.id_player}_${notificationData.paper.created_at}`;
                const existingNotification = document.querySelector(`[data-notification-id="${notificationId}"]`);
                if (!existingNotification) {
                    showGoldenPaperFoundNotification(notificationData.paper);
                }
            });
        }
        
        // Vérifier les papiers dorés trouvés toutes les 3 secondes
        setInterval(checkGoldenPaperFound, 3000);
        
('✅ Système de notifications papiers dorés activé');
        
        // Fonction pour mettre à jour l'heure des notifications persistantes
        function updateNotificationTimes() {
            const notifications = document.querySelectorAll('.golden-paper-notification .notification-time');
            notifications.forEach(timeElement => {
                const notification = timeElement.closest('.golden-paper-notification');
                const notificationId = notification.getAttribute('data-notification-id');
                const savedNotifications = JSON.parse(localStorage.getItem('goldenPaperNotifications') || '[]');
                const notificationData = savedNotifications.find(notif => `golden_${notif.paper.id_paper}_${notif.paper.id_player}_${notif.paper.created_at}` === notificationId);
                
                if (notificationData) {
                    timeElement.textContent = formatNotificationTime(notificationData.paper.created_at);
                }
            });
        }
        
        // Restaurer les notifications persistantes au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(restorePersistentNotifications, 1000); // Délai pour s'assurer que le DOM est prêt
            
            // Mettre à jour l'heure des notifications toutes les minutes
            setInterval(updateNotificationTimes, 60000);
        });
        
        // Première vérification après 3 secondes
        setTimeout(checkGoldenPaperFound, 3000);
        
        // ========== NOTIFICATIONS OBJETS RÉCENTS ==========
        
        // Set pour garder trace des notifications d'objets déjà affichées
        const shownObjectNotifications = new Set();
        
        // Fonction pour vérifier les objets récents
        function checkRecentObjects() {
            fetch('game_recent_objects.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.success || !data.recent_objects) return;
                    
                    const recentObjects = data.recent_objects;
                    
                    // Afficher les nouvelles notifications
                    recentObjects.forEach(object => {
                        // Ne pas afficher si déjà montré
                        if (!shownObjectNotifications.has(object.id)) {
                            shownObjectNotifications.add(object.id);
                            showObjectFoundNotificationForOthers(object);
                        }
                    });
                })
                .catch(error => {
                    console.error('❌ Erreur récupération objets récents:', error);
                });
        }
        
        // Fonction pour afficher la notification d'objet trouvé par d'autres joueurs
        function showObjectFoundNotificationForOthers(object) {
            const container = document.getElementById('notifications-container');
            
            // Créer l'élément de notification
            const notif = document.createElement('div');
            notif.className = 'notification-item';
            notif.style.setProperty('--notif-color', object.team_color);
            notif.style.borderLeftColor = object.team_color;
            
            // Construire le contenu
            let avatarContent = '🎮';
            if (object.team_img) {
                avatarContent = `<img src="${object.team_img}" alt="${object.team_name}">`;
            }
            
            notif.innerHTML = `
                <div class="notification-avatar" style="--notif-color: ${object.team_color};">
                    ${avatarContent}
                </div>
                <div class="notification-content">
                    <div class="notification-name">${object.display_name}</div>
                    <div class="notification-pole">${object.pole_name}</div>
                    <div class="notification-action">vient de placer un objet</div>
                </div>
            `;
            
            // Ajouter au conteneur
            container.appendChild(notif);
            
('🔔 Notification objet affichée pour', object.display_name);
            
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
        
        // Vérifier les objets récents toutes les 5 secondes
        setInterval(checkRecentObjects, 5000);
        
        // Première vérification après 3 secondes
        setTimeout(checkRecentObjects, 3000);
        
        // ========== CLIC-DRAG DES OBJETS DU GROUPE ==========
        
        // Variables pour le système de curseur-objet
        let currentObjectImage = null;
        let isObjectMode = false;
        let currentItemMaskId = null;
        
        // Fonction pour créer l'image qui suit le curseur
        function createObjectImage(itemElement, mouseX, mouseY) {
            const objectDiv = document.createElement('div');
            objectDiv.className = 'dragging-image';
            
            // Récupérer l'image source
            const imgElement = itemElement.querySelector('img');
            if (imgElement) {
                const newImg = imgElement.cloneNode(true);
                objectDiv.appendChild(newImg);
            } else {
                // Si pas d'image, utiliser l'emoji du placeholder
                const placeholder = itemElement.querySelector('.object-placeholder');
                if (placeholder) {
                    objectDiv.innerHTML = placeholder.innerHTML;
                    objectDiv.style.fontSize = '48px';
                }
            }
            
            // Positionner l'image aux coordonnées du curseur
            objectDiv.style.left = (mouseX - 60) + 'px'; // -60 pour centrer (120/2)
            objectDiv.style.top = (mouseY - 60) + 'px';
            
            document.body.appendChild(objectDiv);
            return objectDiv;
        }
        
        // Fonction pour vérifier la collision avec les masques
        function checkMaskCollision(mouseX, mouseY, itemMaskId) {
            if (!itemMaskId) {
                return false;
            }
            
            // Récupérer tous les masques du canvas
            const canvasObjects = canvas.getObjects();
            const masks = canvasObjects.filter(obj => obj.maskData && obj.maskData.isMask);
            
            for (let mask of masks) {
                // Vérifier si l'ID du masque correspond à l'ID de l'item
                if (mask.maskData.dbId == itemMaskId) {
                    // Convertir les coordonnées de la souris en coordonnées du canvas Fabric.js
                    const canvasRect = canvasElement.getBoundingClientRect();
                    const canvasX = mouseX - canvasRect.left;
                    const canvasY = mouseY - canvasRect.top;
                    
                    // Utiliser la méthode correcte de Fabric.js pour convertir les coordonnées
                    const fabricPoint = canvas.restorePointerVpt(new fabric.Point(canvasX, canvasY));
                    
                    // Vérification simple : le point est-il dans les limites du masque ?
                    let collisionDetected = false;
                    
                    // Utiliser les points originaux du masque pour calculer les limites
                    const originalPoints = mask.maskData.originalPoints;
                    if (originalPoints && originalPoints.length > 0) {
                        // Calculer les limites min/max du masque
                        let minX = originalPoints[0].x;
                        let maxX = originalPoints[0].x;
                        let minY = originalPoints[0].y;
                        let maxY = originalPoints[0].y;
                        
                        originalPoints.forEach(point => {
                            minX = Math.min(minX, point.x);
                            maxX = Math.max(maxX, point.x);
                            minY = Math.min(minY, point.y);
                            maxY = Math.max(maxY, point.y);
                        });
                        
                        // Vérifier si le point est dans les limites
                        if (fabricPoint.x >= minX && fabricPoint.x <= maxX && 
                            fabricPoint.y >= minY && fabricPoint.y <= maxY) {
                            collisionDetected = true;
                        }
                    }
                    
                    if (collisionDetected) {
                        return true;
                    }
                }
            }
            
            return false;
        }
        
        // Fonction pour nettoyer l'image objet
        function cleanupObjectImage() {
            if (currentObjectImage) {
                document.body.removeChild(currentObjectImage);
                currentObjectImage = null;
            }
            isObjectMode = false;
            currentItemMaskId = null;
            document.body.style.cursor = '';
        }
        
        // Gestionnaires d'événements pour le système curseur-objet
        document.addEventListener('DOMContentLoaded', function() {
            const groupItems = document.querySelectorAll('.group-object-item');
            
            groupItems.forEach(item => {
                // Événement de clic pour activer/désactiver l'objet curseur
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Vérifier si l'objet est déjà résolu
                    if (this.classList.contains('solved') || this.dataset.itemSolved === 'true') {
                        return;
                    }
                    
                    if (isObjectMode) {
                        // Si déjà en mode objet, désactiver
                        cleanupObjectImage();
                    } else {
                        // Activer le mode objet
                        cleanupObjectImage(); // Nettoyer au cas où
                        
                        currentObjectImage = createObjectImage(this, e.clientX, e.clientY);
                        isObjectMode = true;
                        currentItemMaskId = this.dataset.itemMaskId;
                        
                        // Masquer le curseur
                        document.body.style.cursor = 'none';
                    }
                });
            });
            
            // Événement global pour faire suivre l'image au curseur
            document.addEventListener('mousemove', function(e) {
                if (isObjectMode && currentObjectImage) {
                    // Suivre le curseur
                    currentObjectImage.style.left = (e.clientX - 60) + 'px';
                    currentObjectImage.style.top = (e.clientY - 60) + 'px';
                }
            });
            
            // Événement de clic global pour désactiver le mode objet
            document.addEventListener('click', function(e) {
                // Ne pas désactiver si on clique sur un objet de la barre
                if (e.target.closest('.group-object-item')) {
                    return;
                }
                
                if (isObjectMode) {
                    // Vérifier la collision avec les masques avant de désactiver
                    if (currentItemMaskId && checkMaskCollision(e.clientX, e.clientY, currentItemMaskId)) {
                        // Collision détectée ! Afficher la notification avec feux d'artifice
                        showObjectFoundNotification();
                        
                        // Mettre à jour la base de données
                        updateItemAsSolved(currentItemMaskId);
                    }
                    
                    cleanupObjectImage();
                }
            });
            
            // Gestionnaire de clic sur le canvas pour les objets résolus
            canvas.on('mouse:down', function(e) {
                if (e.target && e.target.itemData && e.target.itemData.isSolvedItem) {
                    showSolvedItemModal(e.target.itemData);
                }
            });
            
            // Empêcher le drag par défaut des images
            document.addEventListener('dragstart', function(e) {
                if (e.target.tagName === 'IMG') {
                    e.preventDefault();
                }
            });
        });
        
        // Fonction pour mettre à jour l'item comme résolu
        function updateItemAsSolved(maskId) {
            fetch('update-item-solved.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    maskId: maskId,
                    userId: <?= $user['id'] ?>
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Mettre à jour l'interface utilisateur
                    updateItemUI(maskId);
                } else {
                    alert('Erreur lors de la sauvegarde: ' + data.error);
                }
            })
            .catch(error => {
                alert('Erreur de connexion lors de la sauvegarde: ' + error.message);
            });
        }
        
        // Fonction pour mettre à jour l'interface utilisateur
        function updateItemUI(maskId) {
            // Trouver l'objet correspondant dans la barre latérale
            const groupItems = document.querySelectorAll('.group-object-item');
            groupItems.forEach(item => {
                if (item.dataset.itemMaskId == maskId) {
                    // Marquer comme résolu
                    item.classList.add('solved');
                    item.dataset.itemSolved = 'true';
                    
                    // Changer le titre si un solved_title existe
                    const img = item.querySelector('img');
                    if (img && img.dataset.solvedTitle) {
                        img.title = img.dataset.solvedTitle;
                    }
                }
            });
        }
        
        // Fonction pour afficher la modale d'information d'un objet résolu
        function showSolvedItemModal(itemData) {
            const popup = document.getElementById('solved-item-popup');
            const overlay = document.getElementById('solved-item-overlay');
            const content = document.getElementById('solved-item-content');
            
            // Construire le HTML avec l'image de l'objet dans un rond coloré
            let htmlContent = '';
            
            // Image de l'objet dans un rond coloré (comme la modale papier trouvé)
            if (itemData.itemPath) {
                let imagePath = itemData.itemPath.startsWith('assets/') ? itemData.itemPath : 'assets/img/items/' + itemData.itemPath;
                htmlContent += `
                    <div style="margin-bottom: 20px;">
                        <img src="${imagePath}" alt="${itemData.itemTitle}" style="width: 120px; height: 120px; object-fit: contain; border-radius: 50%; border: 4px solid ${itemData.teamColor || '#f39c12'}; background: linear-gradient(135deg, ${itemData.teamColor || '#f39c12'}, rgba(255,255,255,0.2));">
                    </div>
                `;
            }
            
            // Format du nom : prénom.nom (comme la modale papier trouvé)
            const playerName = itemData.solvedByFirstname && itemData.solvedByLastname 
                ? `${itemData.solvedByFirstname} ${itemData.solvedByLastname}`
                : itemData.solvedByUsername || 'Joueur inconnu';
            
            htmlContent += `
                <div style="font-size: 1.4rem; margin-bottom: 15px; color: ${itemData.teamColor || '#f39c12'}; font-weight: bold;">
                    ${itemData.itemTitle || 'Objet'}
                </div>
                <div style="font-size: 1.1rem; margin-bottom: 10px; color: white;">
                    Placé par <strong>${playerName}</strong>
                </div>
                <div style="font-size: 1rem; color: #ccc; margin-bottom: 10px;">
                    <strong>Description :</strong> ${itemData.itemSubtitle || 'Sous-titre non disponible'}
                </div>
                <div style="font-size: 1rem; color: #ccc;">
                    <strong>Solution :</strong> ${itemData.solvedTitle || 'Solution non disponible'}
                </div>
            `;
            
            content.innerHTML = htmlContent;
            
            // Afficher la modale
            overlay.style.display = 'flex';
            popup.style.display = 'block';
            
            // Fermer en cliquant sur l'overlay
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    closeSolvedItemModal();
                }
            });
            
            // Fermer avec la touche Échap
            const handleEscape = function(e) {
                if (e.key === 'Escape') {
                    closeSolvedItemModal();
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);
        }
        
        // Fonction pour fermer la modale d'objet résolu
        function closeSolvedItemModal() {
            const overlay = document.getElementById('solved-item-overlay');
            const popup = document.getElementById('solved-item-popup');
            if (overlay && popup) {
                overlay.style.display = 'none';
                popup.style.display = 'none';
            }
        }
        
        // ========== SYNCHRONISATION TEMPS RÉEL DES OBJETS ==========
        
        // Fonction pour vérifier tous les objets résolus (toutes équipes)
        function checkAllSolvedItems() {
            fetch('get-all-solved-items.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    groupId: <?= $user['group_id'] ?>
                })
            })
            .then(response => response.json())
            .then(data => {
('🔍 Réponse get-all-solved-items:', data);
                if (data.success && data.items) {
('📊 Nombre d\'objets résolus reçus:', data.items.length);
                    updateSolvedItemsUI(data.items);
                } else {
                    console.warn('⚠️ Aucun objet résolu trouvé ou erreur dans la réponse');
                }
            })
            .catch(error => {
                // Erreur silencieuse pour ne pas perturber l'expérience utilisateur
            });
        }
        
        // Fonction pour mettre à jour l'interface des objets résolus
        function updateSolvedItemsUI(solvedItems) {
            const groupItems = document.querySelectorAll('.group-object-item');
            let solvedCount = 0;
            const totalCount = groupItems.length;
            
            // Traiter d'abord les objets de l'équipe courante pour la barre du bas
            groupItems.forEach(item => {
                const itemId = parseInt(item.dataset.itemId);
                const solvedItem = solvedItems.find(si => si.id === itemId);
                
                if (solvedItem && solvedItem.solved) {
                    // Marquer comme résolu si pas déjà fait
                    if (!item.classList.contains('solved')) {
                        item.classList.add('solved');
                        item.dataset.itemSolved = 'true';
                        
                        // Changer le titre si un solved_title existe
                        const img = item.querySelector('img');
                        if (img && solvedItem.solved_title) {
                            img.title = solvedItem.solved_title;
                        }
                    }
                    solvedCount++;
                }
            });
            
            // Traiter TOUS les objets résolus pour l'affichage sur le canvas
            solvedItems.forEach(solvedItem => {
                if (solvedItem && solvedItem.solved) {
                    // Afficher l'objet résolu sur le canvas (toutes équipes confondues)
                    displaySolvedItemOnCanvas(solvedItem);
                }
            });
            
            // Mettre à jour le compteur en temps réel
            const counterElement = document.getElementById('objects-counter');
            if (counterElement) {
                counterElement.textContent = `(${solvedCount}/${totalCount})`;
            }
        }
        
        // Fonction pour afficher un objet résolu sur le canvas
        function displaySolvedItemOnCanvas(solvedItem) {
            // Vérifier si l'objet n'est pas déjà affiché sur le canvas
            const existingItem = canvas.getObjects().find(obj => 
                obj.itemData && obj.itemData.isSolvedItem && obj.itemData.itemId === solvedItem.id
            );

            if (existingItem) {
                return;
            }

            // Vérifier si le masque appartient à la pièce courante
            const masks = canvas.getObjects().filter(obj => obj.maskData && obj.maskData.isMask);
            const correspondingMask = masks.find(mask => mask.maskData.dbId == solvedItem.id_mask);
            
            if (!correspondingMask) {
                // Le masque n'est pas dans cette pièce, ne pas afficher l'objet
                return;
            }

            // Le masque est dans cette pièce, afficher l'objet
            createSolvedItemOnCanvas(solvedItem, correspondingMask.maskData);
        }
        
        // Fonction pour créer l'objet résolu sur le canvas avec les données du masque
        function createSolvedItemOnCanvas(solvedItem, maskData) {
            // Utiliser directement les points originaux du masque pour calculer le centre
            const originalPoints = maskData.originalPoints;
            if (!originalPoints || originalPoints.length === 0) {
                return;
            }
            
            // Utiliser la même logique que recreateMask pour calculer le centre
            const clipPolygon = new fabric.Polygon(originalPoints, { fill: "transparent", stroke: "transparent" });
            const bounds = clipPolygon.getBoundingRect();

            // Centre du masque (même calcul que dans recreateMask)
            const centerX = bounds.left + bounds.width / 2;
            const centerY = bounds.top + bounds.height / 2;

            // Construire le chemin de l'image de l'objet
            let imagePath = '';
            if (solvedItem.path) {
                if (solvedItem.path.startsWith('assets/')) {
                    imagePath = solvedItem.path;
                } else {
                    imagePath = 'assets/img/items/' + solvedItem.path;
                }
            } else {
                imagePath = 'assets/img/items/' + solvedItem.id + '.png';
            }

('🎯 Chargement de l\'image:', imagePath);

            // Charger l'image de l'objet
            fabric.Image.fromURL(imagePath, function(img) {
                if (!img) {
('❌ Impossible de charger l\'image:', imagePath);
                    return;
                }

                // Redimensionner l'image (max 180px - 3x plus grand)
                const maxSize = 180;
                const scale = Math.min(maxSize / img.width, maxSize / img.height);
                img.scale(scale);

                // Positionner l'image au centre du masque
                img.set({
                    left: centerX,
                    top: centerY,
                    originX: 'center',
                    originY: 'center',
                    selectable: false,
                    evented: true,
                    hoverCursor: 'pointer',
                    itemData: {
                        isSolvedItem: true,
                        itemId: solvedItem.id,
                        itemTitle: solvedItem.title,
                        itemSubtitle: solvedItem.subtitle,
                        solvedTitle: solvedItem.solved_title,
                        solvedByUsername: solvedItem.solved_by_username,
                        solvedByFirstname: solvedItem.solved_by_firstname,
                        solvedByLastname: solvedItem.solved_by_lastname,
                        teamColor: solvedItem.team_color || '#4CAF50',
                        itemPath: imagePath
                    }
                });

                // Créer un rond coloré avec la couleur de l'équipe
                const teamColor = solvedItem.team_color || '#4CAF50';
                const circleSize = Math.max(img.width * scale, img.height * scale) + 20; // Taille du rond
                const backgroundCircle = new fabric.Circle({
                    left: centerX,
                    top: centerY,
                    radius: circleSize / 2,
                    fill: teamColor,
                    opacity: 0.8,
                    selectable: false,
                    evented: true,
                    originX: 'center',
                    originY: 'center',
                    itemData: {
                        isSolvedItem: true,
                        itemId: solvedItem.id,
                        itemTitle: solvedItem.title,
                        itemSubtitle: solvedItem.subtitle,
                        solvedTitle: solvedItem.solved_title,
                        solvedByUsername: solvedItem.solved_by_username,
                        solvedByFirstname: solvedItem.solved_by_firstname,
                        solvedByLastname: solvedItem.solved_by_lastname,
                        teamColor: teamColor,
                        itemPath: imagePath
                    }
                });

                // Ajouter tous les éléments au canvas (rond en arrière-plan, image par-dessus)
                canvas.add(backgroundCircle);
                canvas.add(img);

                // Mettre l'image au premier plan pour qu'elle soit cliquable
                canvas.bringToFront(img);
                canvas.renderAll();

            }, { crossOrigin: 'anonymous' });
        }
        
        // Fonction pour vérifier tous les objets résolus au chargement (comme checkFoundPapers)
        function checkAllSolvedItemsOnLoad() {
            fetch('get-all-solved-items.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    groupId: <?= $user['group_id'] ?>
                })
            })
            .then(response => response.json())
            .then(data => {
('🔍 Chargement initial - Objets résolus:', data);
                if (data.success && data.items) {
                    data.items.forEach(solvedItem => {
                        // Appliquer le style "résolu" pour tous les objets résolus
                        displaySolvedItemOnCanvas(solvedItem);
                    });
('🎯 Drapeaux appliqués pour', data.items.length, 'objets résolus au chargement');
                }
            })
            .catch(error => {
                console.error('❌ Erreur vérification objets résolus au chargement:', error);
            });
        }
        
        // Vérifier tous les objets résolus toutes les 3 secondes
        setInterval(checkAllSolvedItems, 3000);
        
        // Charger tous les objets résolus existants au démarrage (comme pour les papiers)
        setTimeout(checkAllSolvedItemsOnLoad, 2000);
        
('🔄 Mise à jour automatique activée (données: 10s, papiers trouvés: 15s, notifications: 5s, objets: 3s)');
('🎯 Système curseur-objet activé - Affichage de TOUS les objets résolus');
    </script>
    <?php endif; ?>
</body>
</html>

