<?php
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
        $db = [];
        if (file_exists($dataFile)) {
            $decoded = json_decode(file_get_contents($dataFile), true);
            if (is_array($decoded)) { $db = $decoded; }
        }
        $db[$key] = $payload;
        file_put_contents($dataFile, json_encode($db, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true, 'message' => 'Données sauvegardées']);
        exit;
    }

    if ($action === 'load') {
        $key = $_POST['key'] ?? '';
        if (file_exists($dataFile)) {
            $db = json_decode(file_get_contents($dataFile), true);
            if (!is_array($db)) { $db = []; }
            if ($key !== '') {
                $subset = $db[$key] ?? [];
                echo json_encode(['success' => true, 'data' => json_encode($subset)]);
            } else {
                echo json_encode(['success' => true, 'data' => json_encode($db)]);
            }
        } else {
            echo json_encode(['success' => true, 'data' => json_encode($key !== '' ? [] : new stdClass())]);
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
  <link rel="stylesheet" href="cluedo.css">
</head>
<body>
  <div id="toolbar">
    <div class="toolbar-top">
      <button id="addPaper"><span class="icon">📄</span><span class="label-wrap"><span class="btn-label">Ajouter Papier</span></span></button>
      <button id="toggleLasso"><span class="icon">🖊️</span><span class="label-wrap"><span class="btn-label">Mode Lasso</span></span></button>
      <button id="validateMask" style="display: none;"><span class="icon">✅</span><span class="label-wrap"><span class="btn-label">Valider le masque</span></span></button>
      <button id="editMask"><span class="icon">✏️</span><span class="label-wrap"><span class="btn-label">Modifier le tracé</span></span></button>
      <button id="togglePan"><span class="icon">✋</span><span class="label-wrap"><span class="btn-label">Mode Main</span></span></button>
      <button id="bringForward"><span class="icon">⬆️</span><span class="label-wrap"><span class="btn-label">Premier plan</span></span></button>
      <button id="sendBackward"><span class="icon">⬇️</span><span class="label-wrap"><span class="btn-label">Arrière plan</span></span></button>
    </div>
    <div class="spacer"></div>
    <div class="toolbar-bottom">
      <button id="saveData"><span class="icon">💾</span><span class="label-wrap"><span class="btn-label">Sauvegarder</span></span></button>
      <button id="modeToggle"><span class="icon">🛠️</span><span class="label-wrap"><span class="btn-label">Editor Mode</span></span></button>
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
  <script src="js/init.js"></script>
</body>
</html>
