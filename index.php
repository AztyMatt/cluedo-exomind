<?php
// Connexion à la base de données
require_once __DIR__ . '/db-connection.php';
$dbConnection = getDBConnection();
// if ($dbConnection) {
//     echo "Connexion réussie !";
// } else {
//     echo "Échec de la connexion.";
// }
// return;

// Charger l'image de fond et la convertir en base64
$imagePath = 'rooms/P1080918.JPG';
$imageData = '';
if (file_exists($imagePath)) {
    // Déterminer le type MIME automatiquement
    $mime = function_exists('mime_content_type') ? mime_content_type($imagePath) : '';
    if (!$mime) {
        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        $mime = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg' : 'image/png';
    }
    $imageData = base64_encode(file_get_contents($imagePath));
    $imageData = 'data:' . $mime . ';base64,' . $imageData;
}

// Charger l'image papier et la convertir en base64
$paperPath = 'papier.png';
$paperData = '';
if (file_exists($paperPath)) {
    $paperData = base64_encode(file_get_contents($paperPath));
    $paperData = 'data:image/png;base64,' . $paperData;
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

// ========== API POUR SAUVEGARDER/CHARGER ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $dataFile = __DIR__ . '/cluedo_data.json';

    if ($action === 'save') {
        $key = $_POST['key'] ?? '';
        $data = $_POST['data'] ?? '[]';
        if ($key === '') {
            echo json_encode(['success' => false, 'message' => 'Clé manquante']);
            exit;
        }
        $payload = json_decode($data, true);
        if (!is_array($payload)) { $payload = []; }
        
        // ========== SAUVEGARDE EN BDD PUIS DANS LE JSON ==========
        try {
            if ($dbConnection) {
                // 1. S'assurer qu'on a une room 'default'
                $stmt = $dbConnection->prepare("SELECT id FROM `groups` WHERE name = 'default'");
                $stmt->execute();
                $group = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$group) {
                    $stmt = $dbConnection->prepare("INSERT INTO `groups` (name) VALUES ('default')");
                    $stmt->execute();
                    $groupId = $dbConnection->lastInsertId();
                } else {
                    $groupId = $group['id'];
                }
                
                $stmt = $dbConnection->prepare("SELECT id FROM `rooms` WHERE group_id = ? AND name = 'default'");
                $stmt->execute([$groupId]);
                $room = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$room) {
                    $stmt = $dbConnection->prepare("INSERT INTO `rooms` (group_id, name) VALUES (?, 'default')");
                    $stmt->execute([$groupId]);
                    $roomId = $dbConnection->lastInsertId();
                } else {
                    $roomId = $room['id'];
                }
                
                // 2. Trouver ou créer la photo (key = "P1080918" sans extension)
                $stmt = $dbConnection->prepare("SELECT id FROM `photos` WHERE room_id = ? AND filename LIKE ?");
                $stmt->execute([$roomId, $key . '.%']);
                $photo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$photo) {
                    // Chercher le vrai fichier dans rooms/
                    $foundFile = null;
                    $roomsDir = __DIR__ . '/rooms';
                    if (is_dir($roomsDir)) {
                        foreach (scandir($roomsDir) as $file) {
                            if ($file[0] === '.') continue;
                            if (pathinfo($file, PATHINFO_FILENAME) === $key) {
                                $foundFile = $file;
                                break;
                            }
                        }
                    }
                    $filename = $foundFile ?: ($key . '.JPG');
                    $filePath = 'rooms/' . $filename;
                    
                    $stmt = $dbConnection->prepare("INSERT INTO `photos` (room_id, filename, file_path) VALUES (?, ?, ?)");
                    $stmt->execute([$roomId, $filename, $filePath]);
                    $photoId = $dbConnection->lastInsertId();
                } else {
                    $photoId = $photo['id'];
                }
                
                // 3. Récupérer les IDs existants de papers et masks pour cette photo
                $stmt = $dbConnection->prepare("SELECT id FROM `papers` WHERE photo_id = ?");
                $stmt->execute([$photoId]);
                $existingPaperIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
                
                $stmt = $dbConnection->prepare("SELECT id FROM `masks` WHERE photo_id = ?");
                $stmt->execute([$photoId]);
                $existingMaskIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
                
                error_log("🔍 Payload count: " . count($payload));
                error_log("🔍 PhotoID: $photoId, Papers existants: " . count($existingPaperIds) . ", Masks existants: " . count($existingMaskIds));
                
                $updatedPaperIds = [];
                $updatedMaskIds = [];
                $responseMapping = []; // Mapping pour renvoyer les IDs au client
                
                // 4. UPDATE ou INSERT les papers/masks en BDD
                foreach ($payload as $index => $obj) {
                    error_log("🔍 Type d'objet: " . ($obj['type'] ?? 'UNDEFINED'));
                    
                    if (isset($obj['type']) && $obj['type'] === 'paper') {
                        $paperId = $obj['id'] ?? null;
                        
                        if ($paperId && in_array($paperId, $existingPaperIds)) {
                            // UPDATE
                            error_log("🔄 Mise à jour paper ID: $paperId");
                            $stmt = $dbConnection->prepare("UPDATE `papers` SET position_left = ?, position_top = ?, scale_x = ?, scale_y = ?, angle = ?, z_index = ? WHERE id = ?");
                            $stmt->execute([$obj['left'] ?? 0, $obj['top'] ?? 0, $obj['scaleX'] ?? 1, $obj['scaleY'] ?? 1, $obj['angle'] ?? 0, $obj['zIndex'] ?? 0, $paperId]);
                            $updatedPaperIds[] = $paperId;
                            $responseMapping[$index] = ['type' => 'paper', 'id' => $paperId];
                        } else {
                            // INSERT
                            error_log("📄 Insertion paper - left: " . ($obj['left'] ?? 0) . ", top: " . ($obj['top'] ?? 0) . ", z-index: " . ($obj['zIndex'] ?? 0));
                            $stmt = $dbConnection->prepare("INSERT INTO `papers` (photo_id, position_left, position_top, scale_x, scale_y, angle, z_index) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$photoId, $obj['left'] ?? 0, $obj['top'] ?? 0, $obj['scaleX'] ?? 1, $obj['scaleY'] ?? 1, $obj['angle'] ?? 0, $obj['zIndex'] ?? 0]);
                            $newId = $dbConnection->lastInsertId();
                            $updatedPaperIds[] = $newId;
                            $responseMapping[$index] = ['type' => 'paper', 'id' => $newId];
                            error_log("✅ Paper inséré avec ID: " . $newId);
                        }
                    } elseif (isset($obj['type']) && $obj['type'] === 'mask') {
                        $maskId = $obj['id'] ?? null;
                        
                        if ($maskId && in_array($maskId, $existingMaskIds)) {
                            // UPDATE
                            error_log("🔄 Mise à jour mask ID: $maskId");
                            $stmt = $dbConnection->prepare("UPDATE `masks` SET original_points = ?, curve_handles = ?, position_left = ?, position_top = ?, z_index = ? WHERE id = ?");
                            $stmt->execute([json_encode($obj['originalPoints'] ?? []), json_encode($obj['curveHandles'] ?? []), $obj['left'] ?? 0, $obj['top'] ?? 0, $obj['zIndex'] ?? 0, $maskId]);
                            $updatedMaskIds[] = $maskId;
                            $responseMapping[$index] = ['type' => 'mask', 'id' => $maskId];
                        } else {
                            // INSERT
                            error_log("🎭 Insertion mask - points: " . count($obj['originalPoints'] ?? []) . ", z-index: " . ($obj['zIndex'] ?? 0));
                            $stmt = $dbConnection->prepare("INSERT INTO `masks` (photo_id, original_points, curve_handles, position_left, position_top, z_index) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$photoId, json_encode($obj['originalPoints'] ?? []), json_encode($obj['curveHandles'] ?? []), $obj['left'] ?? 0, $obj['top'] ?? 0, $obj['zIndex'] ?? 0]);
                            $newId = $dbConnection->lastInsertId();
                            $updatedMaskIds[] = $newId;
                            $responseMapping[$index] = ['type' => 'mask', 'id' => $newId];
                            error_log("✅ Mask inséré avec ID: " . $newId);
                        }
                    } else {
                        error_log("⚠️ Objet ignoré - type: " . ($obj['type'] ?? 'UNDEFINED'));
                    }
                }
                
                // 5. Supprimer les papers/masks qui n'existent plus
                $papersToDelete = array_diff($existingPaperIds, $updatedPaperIds);
                foreach ($papersToDelete as $id) {
                    $stmt = $dbConnection->prepare("DELETE FROM `papers` WHERE id = ?");
                    $stmt->execute([$id]);
                    error_log("🗑️ Paper supprimé ID: $id");
                }
                
                $masksToDelete = array_diff($existingMaskIds, $updatedMaskIds);
                foreach ($masksToDelete as $id) {
                    $stmt = $dbConnection->prepare("DELETE FROM `masks` WHERE id = ?");
                    $stmt->execute([$id]);
                    error_log("🗑️ Mask supprimé ID: $id");
                }
                
                error_log("📊 Fin de l'insertion BDD");
                
                // Mettre à jour les IDs dans le payload pour le JSON
                foreach ($responseMapping as $index => $idInfo) {
                    if (isset($payload[$index])) {
                        $payload[$index]['id'] = $idInfo['id'];
                    }
                }
                
                // ========== SAUVEGARDE DANS LE JSON AVEC LES IDs ==========
                $db = [];
                if (file_exists($dataFile)) {
                    $decoded = json_decode(file_get_contents($dataFile), true);
                    if (is_array($decoded)) { $db = $decoded; }
                }
                $db[$key] = $payload;
                file_put_contents($dataFile, json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                error_log("💾 JSON mis à jour avec les IDs");
                
                echo json_encode(['success' => true, 'message' => 'Données sauvegardées', 'ids' => $responseMapping]);
            } else {
                // Pas de connexion BDD, sauvegarder uniquement dans le JSON
                $db = [];
                if (file_exists($dataFile)) {
                    $decoded = json_decode(file_get_contents($dataFile), true);
                    if (is_array($decoded)) { $db = $decoded; }
                }
                $db[$key] = $payload;
                file_put_contents($dataFile, json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                echo json_encode(['success' => true, 'message' => 'Données sauvegardées']);
            }
        } catch (PDOException $e) {
            error_log("⚠️ Erreur BDD (non bloquant): " . $e->getMessage());
            // En cas d'erreur BDD, sauvegarder quand même dans le JSON
            $db = [];
            if (file_exists($dataFile)) {
                $decoded = json_decode(file_get_contents($dataFile), true);
                if (is_array($decoded)) { $db = $decoded; }
            }
            $db[$key] = $payload;
            file_put_contents($dataFile, json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo json_encode(['success' => true, 'message' => 'Données sauvegardées']);
        }
        exit;
    }

    if ($action === 'load') {
        $key = $_POST['key'] ?? '';
        
        // Tenter de charger depuis la BDD d'abord
        try {
            if ($dbConnection) {
                // 1. Trouver le groupe 'default'
                $stmt = $dbConnection->prepare("SELECT id FROM `groups` WHERE name = 'default'");
                $stmt->execute();
                $group = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($group) {
                    $groupId = $group['id'];
                    
                    // 2. Trouver la room 'default'
                    $stmt = $dbConnection->prepare("SELECT id FROM `rooms` WHERE group_id = ? AND name = 'default'");
                    $stmt->execute([$groupId]);
                    $room = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($room) {
                        $roomId = $room['id'];
                        
                        // 3. Trouver la photo correspondant à la clé
                        $stmt = $dbConnection->prepare("SELECT id FROM `photos` WHERE room_id = ? AND filename LIKE ?");
                        $stmt->execute([$roomId, $key . '.%']);
                        $photo = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($photo) {
                            $photoId = $photo['id'];
                            $result = [];
                            
                            // 4. Charger tous les papers de cette photo
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
                            
                            // 5. Charger tous les masks de cette photo
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
                            
                            // Trier tous les objets par z-index pour recréer l'ordre exact
                            usort($result, function($a, $b) {
                                return ($a['zIndex'] ?? 0) <=> ($b['zIndex'] ?? 0);
                            });
                            
                            error_log("📂 Chargé depuis BDD: " . count($papers) . " papers, " . count($masks) . " masks");
                            echo json_encode(['success' => true, 'data' => json_encode($result), 'source' => 'database']);
                            exit;
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("⚠️ Erreur chargement BDD: " . $e->getMessage());
        }
        
        // Pas de fallback JSON - uniquement la BDD
        // Si rien n'a été trouvé en BDD, retourner un tableau vide
        echo json_encode(['success' => true, 'data' => json_encode([]), 'source' => 'database_empty']);
        exit;
        
        // // Fallback: charger depuis JSON si BDD échoue ou pas de données
        // if (file_exists($dataFile)) {
        //     $db = json_decode(file_get_contents($dataFile), true);
        //     if (!is_array($db)) { $db = []; }
        //     if ($key !== '') {
        //         $subset = $db[$key] ?? [];
        //         echo json_encode(['success' => true, 'data' => json_encode($subset), 'source' => 'json']);
        //     } else {
        //         echo json_encode(['success' => true, 'data' => json_encode($db), 'source' => 'json']);
        //     }
        // } else {
        //     echo json_encode(['success' => true, 'data' => json_encode($key !== '' ? [] : new stdClass()), 'source' => 'none']);
        // }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>Mini Éditeur Fabric.js - Image intégrée</title>
  <link rel="stylesheet" href="cluedo.css">
</head>
<body>
  <div id="toolbar">
    <div class="toolbar-top">
      <button id="addPaper"><span class="icon">📄</span><span class="label-wrap"><span class="btn-label">Ajouter Papier</span></span></button>
      <button id="toggleLasso"><span class="icon">🖊️</span><span class="label-wrap"><span class="btn-label">Mode Lasso</span></span></button>
      <button id="editMask"><span class="icon">✏️</span><span class="label-wrap"><span class="btn-label">Modifier le tracé</span></span></button>
      <button id="bringForward"><span class="icon">⬆️</span><span class="label-wrap"><span class="btn-label">Premier plan</span></span></button>
      <button id="sendBackward"><span class="icon">⬇️</span><span class="label-wrap"><span class="btn-label">Arrière plan</span></span></button>
    </div>
    <div class="spacer"></div>
    <div class="toolbar-bottom">
      <button id="saveData"><span class="icon">💾</span><span class="label-wrap"><span class="btn-label">Sauvegarder</span></span></button>
      <button id="modeToggle"><span class="icon">🎮</span><span class="label-wrap"><span class="btn-label">Player Mode</span></span></button>
    </div>
  </div>
  
  <div id="canvas-container">
    <canvas id="c"></canvas>
  </div>

  <!-- Select pour choisir l'image de fond -->
  <div id="roomSelectContainer">
    <select id="roomSelector"><option value="" disabled selected>Choisir une image…</option></select>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
  
  <!-- Variables PHP injectées en JavaScript -->
  <script>
    const roomImages = <?php echo json_encode($roomImages); ?>;
    const paperDataUrl = <?php echo json_encode($paperData); ?>;
  </script>
  
  <!-- Modules JavaScript -->
  <script src="js/canvas-init.js"></script>
  <script src="js/viewport.js"></script>
  <script src="js/lasso-tool.js"></script>
  <script src="js/paper-tool.js"></script>
  <script src="js/pan-tool.js"></script>
  <script src="js/z-index-tools.js"></script>
  <script src="js/mode-toggle.js"></script>
  <script src="js/room-selector.js"></script>
  <script src="js/save-load.js"></script>
  <script src="js/keyboard.js"></script>
  <script src="js/button-state.js"></script>
  <script src="js/init.js"></script>
</body>
</html>
