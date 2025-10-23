<?php
// Connexion à la base de données
require_once __DIR__ . '/../db-connection.php';
$dbConnection = getDBConnection();
// if ($dbConnection) {
//     echo "Connexion réussie !";
// } else {
//     echo "Échec de la connexion.";
// }
// return;

// Charger l'image de fond et la convertir en base64
$imagePath = '../rooms/P1080905.JPG';
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

// Charger l'image papier blanc et la convertir en base64
$paperPath = '../papier.png';
$paperData = '';
if (file_exists($paperPath)) {
    $paperData = base64_encode(file_get_contents($paperPath));
    $paperData = 'data:image/png;base64,' . $paperData;
}

// Charger l'image papier doré et la convertir en base64
$paperDorePath = '../papier_dore.png';
$paperDoreData = '';
if (file_exists($paperDorePath)) {
    $paperDoreData = base64_encode(file_get_contents($paperDorePath));
    $paperDoreData = 'data:image/png;base64,' . $paperDoreData;
}

// Charger l'image flèche et la convertir en base64
$arrowPath = '../arrow.png';
$arrowData = '';
if (file_exists($arrowPath)) {
    $arrowData = base64_encode(file_get_contents($arrowPath));
    $arrowData = 'data:image/png;base64,' . $arrowData;
}

// Lister les images disponibles dans /rooms pour le select
$roomsDir = __DIR__ . '/../rooms';
$roomImages = [];
if (is_dir($roomsDir)) {
    foreach (scandir($roomsDir) as $file) {
        if ($file[0] === '.') continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $roomImages[] = '../rooms/' . $file;
        }
    }
    sort($roomImages);
}

// ========== FONCTIONS UTILITAIRES POUR LA SAUVEGARDE SQL ==========

function loadExistingSqlData() {
    $data = ['papers' => [], 'masks' => [], 'arrows' => [], 'photos' => []];
    
    // Créer les fichiers SQL vides s'ils n'existent pas
    createEmptySqlFilesIfNeeded();
    
    // Charger les photos depuis le fichier photos.sql
    if (file_exists('../data/photos.sql')) {
        $photosContent = file_get_contents('../data/photos.sql');
        $data['photos'] = parsePhotosFromSql($photosContent);
    }
    
    // Charger les papiers depuis papers.sql
    if (file_exists('../data/papers.sql')) {
        $papersContent = file_get_contents('../data/papers.sql');
        $data['papers'] = parsePapersFromSql($papersContent);
    }
    
    // Charger les masques depuis masks.sql
    if (file_exists('../data/masks.sql')) {
        $masksContent = file_get_contents('../data/masks.sql');
        $data['masks'] = parseMasksFromSql($masksContent);
    }
    
    // Charger les flèches depuis arrows.sql
    if (file_exists('../data/arrows.sql')) {
        $arrowsContent = file_get_contents('../data/arrows.sql');
        $data['arrows'] = parseArrowsFromSql($arrowsContent);
    }
    
    return $data;
}

function createEmptySqlFilesIfNeeded() {
    $files = [
        '../data/photos.sql' => "SET NAMES utf8mb4;\n\nINSERT INTO `photos` (`id`, `filename`, `file_path`, `created_at`, `updated_at`) VALUES\n;\n",
        '../data/papers.sql' => "SET NAMES utf8mb4;\n\nINSERT INTO `papers` (`id`, `photo_id`, `position_left`, `position_top`, `scale_x`, `scale_y`, `angle`, `z_index`, `paper_type`, `created_at`, `updated_at`) VALUES\n;\n",
        '../data/masks.sql' => "SET NAMES utf8mb4;\n\nINSERT INTO `masks` (`id`, `photo_id`, `original_points`, `curve_handles`, `position_left`, `position_top`, `z_index`, `created_at`, `updated_at`) VALUES\n;\n",
        '../data/arrows.sql' => "SET NAMES utf8mb4;\n\nINSERT INTO `arrows` (`id`, `photo_id`, `target_photo_id`, `position_left`, `position_top`, `angle`, `active`, `free_placement`, `created_at`, `updated_at`) VALUES\n;\n"
    ];
    
    foreach ($files as $file => $content) {
        if (!file_exists($file)) {
            file_put_contents($file, $content);
        }
    }
}

function parsePhotosFromSql($content) {
    $photos = [];
    // Vérifier s'il y a des données (pas juste le header)
    if (strpos($content, 'VALUES') !== false && strpos($content, ');') !== false) {
        if (preg_match_all('/INSERT INTO `photos`[^;]+VALUES\s*([^;]+);/s', $content, $matches)) {
            foreach ($matches[1] as $values) {
                if (preg_match_all('/\(([^)]+)\)/', $values, $rows)) {
                    foreach ($rows[1] as $row) {
                        $parts = explode(',', $row);
                        if (count($parts) >= 3) {
                            $photos[] = [
                                'id' => trim($parts[0]),
                                'filename' => trim($parts[1], " '"),
                                'file_path' => trim($parts[2], " '")
                            ];
                        }
                    }
                }
            }
        }
    }
    return $photos;
}

function parsePapersFromSql($content) {
    $papers = [];
    // Vérifier s'il y a des données (pas juste le header)
    if (strpos($content, 'VALUES') !== false && strpos($content, ');') !== false) {
        if (preg_match_all('/INSERT INTO `papers`[^;]+VALUES\s*([^;]+);/s', $content, $matches)) {
            foreach ($matches[1] as $values) {
                if (preg_match_all('/\(([^)]+)\)/', $values, $rows)) {
                    foreach ($rows[1] as $row) {
                        $parts = explode(',', $row);
                        if (count($parts) >= 9) {
                            $papers[] = [
                                'id' => trim($parts[0]),
                                'photo_id' => trim($parts[1]),
                                'position_left' => trim($parts[2]),
                                'position_top' => trim($parts[3]),
                                'scale_x' => trim($parts[4]),
                                'scale_y' => trim($parts[5]),
                                'angle' => trim($parts[6]),
                                'z_index' => trim($parts[7]),
                                'paper_type' => trim($parts[8]),
                                'created_at' => trim($parts[9], " '"),
                                'updated_at' => trim($parts[10], " '")
                            ];
                        }
                    }
                }
            }
        }
    }
    return $papers;
}

function parseMasksFromSql($content) {
    $masks = [];
    // Vérifier s'il y a des données (pas juste le header)
    if (strpos($content, 'VALUES') !== false && strpos($content, ');') !== false) {
        if (preg_match_all('/INSERT INTO `masks`[^;]+VALUES\s*([^;]+);/s', $content, $matches)) {
            foreach ($matches[1] as $values) {
                if (preg_match_all('/\(([^)]+)\)/', $values, $rows)) {
                    foreach ($rows[1] as $row) {
                        $parts = explode(',', $row);
                        if (count($parts) >= 7) {
                            $masks[] = [
                                'id' => trim($parts[0]),
                                'photo_id' => trim($parts[1]),
                                'original_points' => trim($parts[2], " '"),
                                'curve_handles' => trim($parts[3], " '"),
                                'position_left' => trim($parts[4]),
                                'position_top' => trim($parts[5]),
                                'z_index' => trim($parts[6]),
                                'created_at' => trim($parts[7], " '"),
                                'updated_at' => trim($parts[8], " '")
                            ];
                        }
                    }
                }
            }
        }
    }
    return $masks;
}

function parseArrowsFromSql($content) {
    $arrows = [];
    // Vérifier s'il y a des données (pas juste le header)
    if (strpos($content, 'VALUES') !== false && strpos($content, ');') !== false) {
        if (preg_match_all('/INSERT INTO `arrows`[^;]+VALUES\s*([^;]+);/s', $content, $matches)) {
            foreach ($matches[1] as $values) {
                if (preg_match_all('/\(([^)]+)\)/', $values, $rows)) {
                    foreach ($rows[1] as $row) {
                        $parts = explode(',', $row);
                        if (count($parts) >= 8) {
                            $arrows[] = [
                                'id' => trim($parts[0]),
                                'photo_id' => trim($parts[1]),
                                'target_photo_id' => trim($parts[2]) === 'NULL' ? null : trim($parts[2]),
                                'position_left' => trim($parts[3]),
                                'position_top' => trim($parts[4]),
                                'angle' => trim($parts[5]),
                                'active' => trim($parts[6]),
                                'free_placement' => trim($parts[7]),
                                'created_at' => trim($parts[8], " '"),
                                'updated_at' => trim($parts[9], " '")
                            ];
                        }
                    }
                }
            }
        }
    }
    return $arrows;
}

function findOrCreatePhotoId($key, &$existingData) {
    // Chercher dans les photos existantes
    foreach ($existingData['photos'] as $photo) {
        $filename = pathinfo($photo['filename'], PATHINFO_FILENAME);
        if ($filename === $key) {
            return $photo['id'];
        }
    }
    
    // Créer une nouvelle photo
    $newId = generateNewId($existingData['photos']);
    $filename = $key . '.JPG';
    $filePath = 'rooms/' . $filename;
    
    $existingData['photos'][] = [
        'id' => $newId,
        'filename' => $filename,
        'file_path' => $filePath
    ];
    
    return $newId;
}

function generateNewId($items) {
    if (empty($items)) return 1;
    $maxId = 0;
    foreach ($items as $item) {
        $maxId = max($maxId, (int)$item['id']);
    }
    return $maxId + 1;
}

function generateSqlFilesFromDatabase($dbConnection) {
    try {
        // Créer le dossier sql/inserts s'il n'existe pas
        $insertsDir = __DIR__ . '/../sql/inserts';
        if (!is_dir($insertsDir)) {
            mkdir($insertsDir, 0755, true);
        }
        
        // Supprimer les anciens fichiers générés automatiquement
        $filesToDelete = [
            $insertsDir . '/03-photos.sql',  // Fichier photos
            $insertsDir . '/04-arrows.sql',  // Fichier arrows
            $insertsDir . '/05-masks.sql',   // Fichier masks
            $insertsDir . '/06-papers.sql'  // Fichier papers
        ];
        
        foreach ($filesToDelete as $file) {
            if (file_exists($file)) {
                unlink($file);
                error_log("🗑️ Fichier supprimé: " . basename($file));
            }
        }
        // Récupérer toutes les photos
        $stmt = $dbConnection->prepare("SELECT id, filename, file_path, created_at, updated_at FROM photos ORDER BY id ASC");
        $stmt->execute();
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Générer photos.sql
        $photosSql = "SET NAMES utf8mb4;\n\n";
        $photosSql .= "INSERT INTO `photos` (`id`, `filename`, `file_path`, `created_at`, `updated_at`) VALUES\n";
        
        $photoValues = [];
        foreach ($photos as $photo) {
            $photoValues[] = sprintf(
                "(%d, '%s', '%s', '%s', '%s')",
                $photo['id'],
                addslashes($photo['filename']),
                addslashes($photo['file_path']),
                $photo['created_at'],
                $photo['updated_at']
            );
        }
        
        if (!empty($photoValues)) {
            $photosSql .= implode(",\n", $photoValues) . ";\n";
        } else {
            $photosSql .= ";\n";
        }
        file_put_contents($insertsDir . '/03-photos.sql', $photosSql);
        
        // Récupérer tous les papiers
        $stmt = $dbConnection->prepare("SELECT id, photo_id, position_left, position_top, scale_x, scale_y, angle, z_index, paper_type, created_at, updated_at FROM papers ORDER BY id ASC");
        $stmt->execute();
        $papers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Générer papers.sql
        $papersSql = "SET NAMES utf8mb4;\n\n";
        $papersSql .= "INSERT INTO `papers` (`id`, `photo_id`, `position_left`, `position_top`, `scale_x`, `scale_y`, `angle`, `z_index`, `paper_type`, `created_at`, `updated_at`) VALUES\n";
        
        $paperValues = [];
        foreach ($papers as $paper) {
            $paperValues[] = sprintf(
                "(%d, %d, %.10f, %.10f, %.6f, %.6f, %.6f, %d, %d, '%s', '%s')",
                $paper['id'],
                $paper['photo_id'],
                $paper['position_left'],
                $paper['position_top'],
                $paper['scale_x'],
                $paper['scale_y'],
                $paper['angle'],
                $paper['z_index'],
                $paper['paper_type'],
                $paper['created_at'],
                $paper['updated_at']
            );
        }
        
        if (!empty($paperValues)) {
            $papersSql .= implode(",\n", $paperValues) . ";\n";
        } else {
            $papersSql .= ";\n";
        }
        file_put_contents($insertsDir . '/06-papers.sql', $papersSql);
        
        // Récupérer tous les masques
        $stmt = $dbConnection->prepare("SELECT id, photo_id, original_points, curve_handles, position_left, position_top, z_index, created_at, updated_at FROM masks ORDER BY id ASC");
        $stmt->execute();
        $masks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Générer masks.sql
        $masksSql = "SET NAMES utf8mb4;\n\n";
        $masksSql .= "INSERT INTO `masks` (`id`, `photo_id`, `original_points`, `curve_handles`, `position_left`, `position_top`, `z_index`, `created_at`, `updated_at`) VALUES\n";
        
        $maskValues = [];
        foreach ($masks as $mask) {
            $maskValues[] = sprintf(
                "(%d, %d, '%s', '%s', %.10f, %.10f, %d, '%s', '%s')",
                $mask['id'],
                $mask['photo_id'],
                addslashes($mask['original_points']),
                addslashes($mask['curve_handles']),
                $mask['position_left'],
                $mask['position_top'],
                $mask['z_index'],
                $mask['created_at'],
                $mask['updated_at']
            );
        }
        
        if (!empty($maskValues)) {
            $masksSql .= implode(",\n", $maskValues) . ";\n";
        } else {
            $masksSql .= ";\n";
        }
        file_put_contents($insertsDir . '/05-masks.sql', $masksSql);
        
        // Récupérer toutes les flèches
        $stmt = $dbConnection->prepare("SELECT id, photo_id, target_photo_id, position_left, position_top, angle, active, free_placement, created_at, updated_at FROM arrows ORDER BY id ASC");
        $stmt->execute();
        $arrows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Générer arrows.sql
        $arrowsSql = "SET NAMES utf8mb4;\n\n";
        $arrowsSql .= "INSERT INTO `arrows` (`id`, `photo_id`, `target_photo_id`, `position_left`, `position_top`, `angle`, `active`, `free_placement`, `created_at`, `updated_at`) VALUES\n";
        
        $arrowValues = [];
        foreach ($arrows as $arrow) {
            $arrowValues[] = sprintf(
                "(%d, %d, %s, %.10f, %.10f, %.6f, %d, %d, '%s', '%s')",
                $arrow['id'],
                $arrow['photo_id'],
                $arrow['target_photo_id'] ? $arrow['target_photo_id'] : 'NULL',
                $arrow['position_left'],
                $arrow['position_top'],
                $arrow['angle'],
                $arrow['active'] ? 1 : 0,
                $arrow['free_placement'] ? 1 : 0,
                $arrow['created_at'],
                $arrow['updated_at']
            );
        }
        
        if (!empty($arrowValues)) {
            $arrowsSql .= implode(",\n", $arrowValues) . ";\n";
        } else {
            $arrowsSql .= ";\n";
        }
        file_put_contents($insertsDir . '/04-arrows.sql', $arrowsSql);
        
        error_log("📁 Fichiers SQL générés automatiquement - Photos: " . count($photos) . ", Papers: " . count($papers) . ", Masks: " . count($masks) . ", Arrows: " . count($arrows));
        
    } catch (PDOException $e) {
        error_log("⚠️ Erreur génération SQL: " . $e->getMessage());
    }
}

// ========== API POUR SAUVEGARDER/CHARGER ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $key = $_POST['key'] ?? '';
        $data = $_POST['data'] ?? '[]';
        if ($key === '') {
            echo json_encode(['success' => false, 'message' => 'Clé manquante']);
            exit;
        }
        $payload = json_decode($data, true);
        if (!is_array($payload)) { $payload = []; }
        
        // ========== SAUVEGARDE EN BDD + GÉNÉRATION SQL AUTOMATIQUE ==========
        try {
            if (!$dbConnection) {
                echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
                exit;
            }
            
            // 1. Trouver ou créer la photo (key = "P1080918" sans extension)
            $stmt = $dbConnection->prepare("SELECT id FROM `photos` WHERE filename LIKE ?");
            $stmt->execute([$key . '.%']);
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
                
                $stmt = $dbConnection->prepare("INSERT INTO `photos` (filename, file_path) VALUES (?, ?)");
                $stmt->execute([$filename, $filePath]);
                $photoId = $dbConnection->lastInsertId();
            } else {
                $photoId = $photo['id'];
            }
            
            // 2. Récupérer les IDs existants de papers, masks et arrows pour cette photo
            $stmt = $dbConnection->prepare("SELECT id FROM `papers` WHERE photo_id = ?");
            $stmt->execute([$photoId]);
            $existingPaperIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
            
            $stmt = $dbConnection->prepare("SELECT id FROM `masks` WHERE photo_id = ?");
            $stmt->execute([$photoId]);
            $existingMaskIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
            
            $stmt = $dbConnection->prepare("SELECT id FROM `arrows` WHERE photo_id = ?");
            $stmt->execute([$photoId]);
            $existingArrowIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
            
            error_log("🔍 Payload count: " . count($payload));
            error_log("🔍 PhotoID: $photoId, Papers existants: " . count($existingPaperIds) . ", Masks existants: " . count($existingMaskIds) . ", Arrows existants: " . count($existingArrowIds));
            
            $updatedPaperIds = [];
            $updatedMaskIds = [];
            $updatedArrowIds = [];
            $responseMapping = []; // Mapping pour renvoyer les IDs au client
            
            // 3. UPDATE ou INSERT les papers/masks en BDD
            foreach ($payload as $index => $obj) {
                error_log("🔍 Type d'objet: " . ($obj['type'] ?? 'UNDEFINED'));
                
                if (isset($obj['type']) && $obj['type'] === 'paper') {
                    $paperId = $obj['id'] ?? null;
                    $paperType = $obj['paperType'] ?? 0; // 0 = blanc, 1 = doré
                    
                    if ($paperId && in_array($paperId, $existingPaperIds)) {
                        // UPDATE
                        error_log("🔄 Mise à jour paper ID: $paperId, type: $paperType");
                        $stmt = $dbConnection->prepare("UPDATE `papers` SET position_left = ?, position_top = ?, scale_x = ?, scale_y = ?, angle = ?, z_index = ?, paper_type = ? WHERE id = ?");
                        $stmt->execute([$obj['left'] ?? 0, $obj['top'] ?? 0, $obj['scaleX'] ?? 1, $obj['scaleY'] ?? 1, $obj['angle'] ?? 0, $obj['zIndex'] ?? 0, $paperType, $paperId]);
                        $updatedPaperIds[] = $paperId;
                        $responseMapping[$index] = ['type' => 'paper', 'id' => $paperId];
                    } else {
                        // INSERT
                        error_log("📄 Insertion paper - left: " . ($obj['left'] ?? 0) . ", top: " . ($obj['top'] ?? 0) . ", z-index: " . ($obj['zIndex'] ?? 0) . ", type: $paperType");
                        $stmt = $dbConnection->prepare("INSERT INTO `papers` (photo_id, position_left, position_top, scale_x, scale_y, angle, z_index, paper_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$photoId, $obj['left'] ?? 0, $obj['top'] ?? 0, $obj['scaleX'] ?? 1, $obj['scaleY'] ?? 1, $obj['angle'] ?? 0, $obj['zIndex'] ?? 0, $paperType]);
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
                } elseif (isset($obj['type']) && $obj['type'] === 'arrow') {
                    $arrowId = $obj['id'] ?? null;
                    $freePlacement = isset($obj['freePlacement']) ? ($obj['freePlacement'] ? 1 : 0) : 0;
                    
                    // Trouver ou créer le target_photo_id si targetPhotoName est fourni
                    $targetPhotoId = null;
                    if (!empty($obj['targetPhotoName'])) {
                        $targetPhotoName = $obj['targetPhotoName'];
                        // Chercher ou créer une photo avec ce nom
                        $stmt = $dbConnection->prepare("SELECT id FROM `photos` WHERE filename LIKE ?");
                        $stmt->execute([$targetPhotoName . '%']);
                        $targetPhoto = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($targetPhoto) {
                            $targetPhotoId = $targetPhoto['id'];
                        } else {
                            // Créer la photo si elle n'existe pas
                            $targetFilePath = 'rooms/' . $targetPhotoName . '.JPG';
                            $stmt = $dbConnection->prepare("INSERT INTO `photos` (filename, file_path) VALUES (?, ?)");
                            $stmt->execute([$targetPhotoName . '.JPG', $targetFilePath]);
                            $targetPhotoId = $dbConnection->lastInsertId();
                            error_log("📍 Photo créée pour target: $targetPhotoName (ID: $targetPhotoId)");
                        }
                    }
                    
                    if ($arrowId && in_array($arrowId, $existingArrowIds)) {
                        // UPDATE
                        error_log("🔄 Mise à jour arrow ID: $arrowId");
                        $stmt = $dbConnection->prepare("UPDATE `arrows` SET position_left = ?, position_top = ?, angle = ?, target_photo_id = ?, free_placement = ? WHERE id = ?");
                        $stmt->execute([$obj['left'] ?? 0, $obj['top'] ?? 0, $obj['angle'] ?? 0, $targetPhotoId, $freePlacement, $arrowId]);
                        $updatedArrowIds[] = $arrowId;
                        $responseMapping[$index] = ['type' => 'arrow', 'id' => $arrowId];
                    } else {
                        // INSERT
                        error_log("➡️ Insertion arrow - left: " . ($obj['left'] ?? 0) . ", top: " . ($obj['top'] ?? 0) . ", angle: " . ($obj['angle'] ?? 0) . ", target: " . ($obj['targetPhotoName'] ?? 'none') . ", free: " . $freePlacement);
                        $stmt = $dbConnection->prepare("INSERT INTO `arrows` (photo_id, target_photo_id, position_left, position_top, angle, free_placement) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$photoId, $targetPhotoId, $obj['left'] ?? 0, $obj['top'] ?? 0, $obj['angle'] ?? 0, $freePlacement]);
                        $newId = $dbConnection->lastInsertId();
                        $updatedArrowIds[] = $newId;
                        $responseMapping[$index] = ['type' => 'arrow', 'id' => $newId];
                        error_log("✅ Arrow inséré avec ID: " . $newId);
                    }
                } else {
                    error_log("⚠️ Objet ignoré - type: " . ($obj['type'] ?? 'UNDEFINED'));
                }
            }
            
            // 4. Supprimer les papers/masks/arrows qui n'existent plus
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
            
            $arrowsToDelete = array_diff($existingArrowIds, $updatedArrowIds);
            foreach ($arrowsToDelete as $id) {
                $stmt = $dbConnection->prepare("DELETE FROM `arrows` WHERE id = ?");
                $stmt->execute([$id]);
                error_log("🗑️ Arrow supprimé ID: $id");
            }
            
            error_log("📊 Sauvegarde BDD terminée avec succès");
            
            // 5. NOUVELLE ÉTAPE : Générer automatiquement les fichiers SQL d'insertion
            try {
                generateSqlFilesFromDatabase($dbConnection);
                error_log("✅ Génération SQL réussie");
            } catch (Exception $e) {
                error_log("⚠️ Erreur génération SQL: " . $e->getMessage());
                // Ne pas faire échouer la sauvegarde pour une erreur de génération SQL
            }
            
            echo json_encode(['success' => true, 'message' => 'Données sauvegardées en base de données + fichiers SQL générés', 'ids' => $responseMapping]);
        } catch (PDOException $e) {
            error_log("⚠️ Erreur BDD: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'generate_sql') {
        // Générer les fichiers SQL d'insertion
        try {
            if (!$dbConnection) {
                echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
                exit;
            }
            
            // Générer papers.sql
            $stmt = $dbConnection->prepare("
                SELECT p.id, p.photo_id, p.position_left, p.position_top, p.scale_x, p.scale_y, p.angle, p.z_index, p.paper_type, p.created_at, p.updated_at
                FROM papers p 
                ORDER BY p.id ASC
            ");
            $stmt->execute();
            $papers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $papersSql = "SET NAMES utf8mb4;\n\n";
            $papersSql .= "INSERT INTO `papers` (`id`, `photo_id`, `position_left`, `position_top`, `scale_x`, `scale_y`, `angle`, `z_index`, `paper_type`, `created_at`, `updated_at`) VALUES\n";
            
            $paperValues = [];
            foreach ($papers as $paper) {
                $paperValues[] = sprintf(
                    "(%d, %d, %.10f, %.10f, %.6f, %.6f, %.6f, %d, %d, '%s', '%s')",
                    $paper['id'],
                    $paper['photo_id'],
                    $paper['position_left'],
                    $paper['position_top'],
                    $paper['scale_x'],
                    $paper['scale_y'],
                    $paper['angle'],
                    $paper['z_index'],
                    $paper['paper_type'],
                    $paper['created_at'],
                    $paper['updated_at']
                );
            }
            
            $papersSql .= implode(",\n", $paperValues) . ";\n";
            file_put_contents('../data/papers.sql', $papersSql);
            
            // Générer masks.sql
            $stmt = $dbConnection->prepare("
                SELECT m.id, m.photo_id, m.original_points, m.curve_handles, m.position_left, m.position_top, m.z_index, m.created_at, m.updated_at
                FROM masks m 
                ORDER BY m.id ASC
            ");
            $stmt->execute();
            $masks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $masksSql = "SET NAMES utf8mb4;\n\n";
            $masksSql .= "INSERT INTO `masks` (`id`, `photo_id`, `original_points`, `curve_handles`, `position_left`, `position_top`, `z_index`, `created_at`, `updated_at`) VALUES\n";
            
            $maskValues = [];
            foreach ($masks as $mask) {
                $maskValues[] = sprintf(
                    "(%d, %d, '%s', '%s', %.10f, %.10f, %d, '%s', '%s')",
                    $mask['id'],
                    $mask['photo_id'],
                    addslashes($mask['original_points']),
                    addslashes($mask['curve_handles']),
                    $mask['position_left'],
                    $mask['position_top'],
                    $mask['z_index'],
                    $mask['created_at'],
                    $mask['updated_at']
                );
            }
            
            $masksSql .= implode(",\n", $maskValues) . ";\n";
            file_put_contents('../data/masks.sql', $masksSql);
            
            // Générer arrows.sql
            $stmt = $dbConnection->prepare("
                SELECT a.id, a.photo_id, a.target_photo_id, a.position_left, a.position_top, a.angle, a.active, a.free_placement, a.created_at, a.updated_at
                FROM arrows a 
                ORDER BY a.id ASC
            ");
            $stmt->execute();
            $arrows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $arrowsSql = "SET NAMES utf8mb4;\n\n";
            $arrowsSql .= "INSERT INTO `arrows` (`id`, `photo_id`, `target_photo_id`, `position_left`, `position_top`, `angle`, `active`, `free_placement`, `created_at`, `updated_at`) VALUES\n";
            
            $arrowValues = [];
            foreach ($arrows as $arrow) {
                $arrowValues[] = sprintf(
                    "(%d, %d, %s, %.10f, %.10f, %.6f, %d, %d, '%s', '%s')",
                    $arrow['id'],
                    $arrow['photo_id'],
                    $arrow['target_photo_id'] ? $arrow['target_photo_id'] : 'NULL',
                    $arrow['position_left'],
                    $arrow['position_top'],
                    $arrow['angle'],
                    $arrow['active'] ? 1 : 0,
                    $arrow['free_placement'] ? 1 : 0,
                    $arrow['created_at'],
                    $arrow['updated_at']
                );
            }
            
            $arrowsSql .= implode(",\n", $arrowValues) . ";\n";
            file_put_contents('../data/arrows.sql', $arrowsSql);
            
            $goldPapers = count(array_filter($papers, function($p) { return $p['paper_type'] == 1; }));
            
            echo json_encode([
                'success' => true, 
                'message' => 'Fichiers SQL générés avec succès !',
                'stats' => [
                    'papers' => count($papers),
                    'goldPapers' => $goldPapers,
                    'masks' => count($masks),
                    'arrows' => count($arrows)
                ]
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la génération: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'load') {
        $key = $_POST['key'] ?? '';
        
        // ========== CHARGEMENT DEPUIS LA BDD UNIQUEMENT ==========
        try {
            if (!$dbConnection) {
                echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
                exit;
            }
            
            // 1. Trouver la photo correspondant à la clé
            $stmt = $dbConnection->prepare("SELECT id FROM `photos` WHERE filename LIKE ?");
            $stmt->execute([$key . '.%']);
            $photo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($photo) {
                $photoId = $photo['id'];
                $result = [];
                
                // 2. Charger tous les papers de cette photo
                $stmt = $dbConnection->prepare("SELECT id, position_left, position_top, scale_x, scale_y, angle, z_index, paper_type FROM `papers` WHERE photo_id = ? ORDER BY z_index ASC, id ASC");
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
                        'zIndex' => (int)$paper['z_index'],
                        'paperType' => (int)$paper['paper_type']
                    ];
                }
                
                // 3. Charger tous les masks de cette photo
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
                
                // 4. Charger toutes les arrows de cette photo
                $stmt = $dbConnection->prepare("SELECT a.id, a.position_left, a.position_top, a.angle, a.active, a.free_placement, a.target_photo_id, p.filename as target_photo_filename FROM `arrows` a LEFT JOIN `photos` p ON a.target_photo_id = p.id WHERE a.photo_id = ? ORDER BY a.id ASC");
                $stmt->execute([$photoId]);
                $arrows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Les arrows ont toujours un z-index fixe de 1000
                foreach ($arrows as $arrow) {
                    // Extraire le nom sans extension
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
                        'zIndex' => 1000 // Z-index fixe pour toutes les flèches
                    ];
                }
                
                // Trier tous les objets par z-index pour recréer l'ordre exact
                usort($result, function($a, $b) {
                    return ($a['zIndex'] ?? 0) <=> ($b['zIndex'] ?? 0);
                });
                
                error_log("📂 Chargé depuis BDD: " . count($papers) . " papers, " . count($masks) . " masks, " . count($arrows) . " arrows");
                echo json_encode(['success' => true, 'data' => json_encode($result), 'source' => 'database']);
                exit;
            }
            
            // Si rien n'a été trouvé en BDD, retourner un tableau vide
            error_log("📂 Aucune donnée trouvée pour la clé: $key");
            echo json_encode(['success' => true, 'data' => json_encode([]), 'source' => 'database_empty']);
        } catch (PDOException $e) {
            error_log("⚠️ Erreur chargement BDD: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement: ' . $e->getMessage()]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>Mini Éditeur Fabric.js - Image intégrée</title>
  <link rel="stylesheet" href="../cluedo.css">
</head>
<body>
  <div id="toolbar">
    <div class="toolbar-top">
      <button id="addPaper"><span class="icon">📄</span><span class="label-wrap"><span class="btn-label">Ajouter Papier Blanc</span></span></button>
      <button id="addPaperDore"><span class="icon">📜</span><span class="label-wrap"><span class="btn-label">Ajouter Papier Doré</span></span></button>
      <button id="addArrow"><span class="icon">➡️</span><span class="label-wrap"><span class="btn-label">Ajouter Flèche</span></span></button>
      <button id="toggleLasso"><span class="icon">🖊️</span><span class="label-wrap"><span class="btn-label">Mode Lasso</span></span></button>
      <button id="editMask"><span class="icon">✏️</span><span class="label-wrap"><span class="btn-label">Modifier le tracé</span></span></button>
      <button id="bringForward"><span class="icon">⬆️</span><span class="label-wrap"><span class="btn-label">Premier plan</span></span></button>
      <button id="sendBackward"><span class="icon">⬇️</span><span class="label-wrap"><span class="btn-label">Arrière plan</span></span></button>
    </div>
    <div class="spacer"></div>
    <div class="toolbar-bottom">
      <button id="modeToggle"><span class="icon">🎮</span><span class="label-wrap"><span class="btn-label">Player Mode</span></span></button>
    </div>
  </div>
  
  <div id="canvas-container">
    <canvas id="c"></canvas>
  </div>

  <!-- Indicateur de sauvegarde automatique -->
  <div id="autoSaveIndicator" style="display: none; position: fixed; top: 20px; left: 20px; background: rgba(26, 127, 26, 0.9); padding: 10px; border-radius: 8px; font-size: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 10000; transition: opacity 0.3s;">
    💾
  </div>

  <!-- Bouton pour choisir l'image de fond (mode editor uniquement) -->
  <div id="roomSelectContainer">
    <button id="changeRoomBtn" style="padding: 10px 20px; background: #3a3a3a; color: #eee; border: 1px solid #555; border-radius: 8px; cursor: pointer; font-size: 16px; display: none;">
      📷 Changer de pièce
    </button>
  </div>

  <!-- Modale pour sélectionner la pièce cible -->
  <div id="arrowTargetModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: #2a2a2a; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.5); max-width: 700px; width: 90%;">
      <h2 id="modalTitle" style="margin: 0 0 20px 0; color: #fff; font-size: 24px;">Sélectionner une photo</h2>
      <p id="modalDescription" style="color: #aaa; margin-bottom: 20px;">Choisissez une photo :</p>
      
      <!-- Carrousel -->
      <div style="position: relative; margin-bottom: 20px;">
        <!-- Image du carrousel -->
        <div id="carouselImageContainer" style="width: 100%; height: 300px; background: #1a1a1a; border: 1px solid #555; border-radius: 5px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative;">
          <img id="carouselImage" alt="Aperçu sélectionné" style="max-width: 100%; max-height: 100%; object-fit: contain;" />
          <div id="carouselPlaceholder" style="color: #666; font-size: 18px;">Aucune photo disponible</div>
        </div>
        
        <!-- Boutons de navigation -->
        <button id="carouselPrev" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.7); color: #fff; border: none; border-radius: 50%; width: 50px; height: 50px; font-size: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center;">‹</button>
        <button id="carouselNext" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.7); color: #fff; border: none; border-radius: 50%; width: 50px; height: 50px; font-size: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center;">›</button>
        
        <!-- Indicateur -->
        <div id="carouselIndicator" style="text-align: center; color: #fff; margin-top: 10px; font-size: 14px;">
          <span id="carouselCounter">0 / 0</span> - <span id="carouselName" style="font-weight: bold;">-</span>
        </div>
      </div>
      
      <div style="display: flex; gap: 10px; justify-content: flex-end;">
        <button id="cancelArrowBtn" style="padding: 10px 20px; background: #555; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">Annuler</button>
        <button id="confirmArrowBtn" style="padding: 10px 20px; background: #4CAF50; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">Confirmer</button>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
  
  <!-- Variables PHP injectées en JavaScript -->
  <script>
    const roomImages = <?php echo json_encode($roomImages); ?>;
    const paperDataUrl = <?php echo json_encode($paperData); ?>;
    const paperDoreDataUrl = <?php echo json_encode($paperDoreData); ?>;
    const arrowDataUrl = <?php echo json_encode($arrowData); ?>;
  </script>
  
  <!-- Modules JavaScript -->
  <script src="../js/canvas-init.js"></script>
  <script src="../js/viewport.js"></script>
  <script src="../js/lasso-tool.js"></script>
  <script src="../js/paper-tool.js"></script>
  <script src="../js/arrow-tool.js"></script>
  <script src="../js/pan-tool.js"></script>
  <script src="../js/z-index-tools.js"></script>
  <script src="../js/mode-toggle.js"></script>
  <script src="../js/room-selector.js"></script>
  <script src="../js/save-load.js"></script>
  <script src="../js/keyboard.js"></script>
  <script src="../js/button-state.js"></script>
  <script src="../js/init.js"></script>
  
</body>
</html>
