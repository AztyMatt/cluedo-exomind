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
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: sans-serif;
      height: 100vh;
      width: 100vw;
      background: #1e1e1e;
      color: #eee;
      overflow: hidden;
    }
    
    #toolbar {
      position: fixed;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      z-index: 1000;
      display: flex;
      flex-direction: column;
      gap: 8px;
      background: transparent;
      padding: 0;
      border-radius: 0;
      box-shadow: none;
      align-items: stretch;
    }
    #toolbar .toolbar-top,
    #toolbar .toolbar-bottom {
      display: flex;
      flex-direction: column;
      gap: 8px;
      align-items: stretch;
    }
    #toolbar .spacer { flex: 1 1 auto; }

    /* Boutons compacts icône-only */
    button {
      background: #3a3a3a;
      border: none;
      color: #eee;
      min-width: 20px;
      width: fit-content;
      height: 40px;
      border-radius: 10px;
      cursor: pointer;
      font-size: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 0 10px;
      overflow: hidden; /* label reste dans le bouton */
      white-space: nowrap;
      position: relative;
      transition: background 0.2s;
    }
    button:hover { background: #555; }

    /* Désactiver l'ancien tooltip ::after */
    #toolbar button::after { content: none !important; }

    /* Wrapper de label qui s'élargit au hover */
    #toolbar .label-wrap {
      display: none;
      overflow: hidden;
      max-width: 0;
      transition: max-width 180ms ease;
    }

    /* Label inline qui se déploie (sans changer la mise en page initiale) */
    #toolbar .btn-label {
      transform: scaleX(0);
      transform-origin: left center;
      opacity: 0;
      transition: transform 180ms ease, opacity 180ms ease;
      font-size: 13px;
      color: #eee;
    }

    #toolbar button:hover .label-wrap { max-width: 220px; display: flex; }
    #toolbar button:hover .btn-label { transform: scaleX(1); opacity: 1; }

    /* Sauvegarder reste vert */
    #saveData { background: #1a7f1a !important; }
    #saveData:hover { background: #228b22 !important; }

    #canvas-container {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      overflow: hidden;
    }
    canvas { display: block; }

    /* Sélecteur d'images en bas de page */
    #roomSelectContainer { position: absolute; left: 50%; bottom: 12px; transform: translateX(-50%); z-index: 1200; }
    #roomSelector { background: #3a3a3a; color: #eee; border: 1px solid #555; border-radius: 8px; padding: 6px 10px; }
  </style>
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
  <script>
    // Initialiser le canvas en plein écran
    const canvasElement = document.getElementById('c');
    canvasElement.width = window.innerWidth;
    canvasElement.height = window.innerHeight;
    
    const canvas = new fabric.Canvas("c", {
      // La sélection doit être active par défaut (mode main inactif)
      selection: true,
    });
    
    // Curseurs (mode main inactif par défaut)
    canvas.defaultCursor = 'default';
    canvas.hoverCursor = 'move';

    // Variables de viewport (base fit)
    let baseZoom = 1;
    let isAtBaseZoom = true; // vrai tant qu'on n'a pas zoomé au-delà du fit

    // Helpers: utiliser la taille native de l'image et adapter via viewport (contain), sans la scaler
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

    // Redimension : garder les éléments superposés; si on est au fit, réappliquer le fit
    window.addEventListener('resize', () => {
      canvas.setDimensions({ width: window.innerWidth, height: window.innerHeight });
      if (isAtBaseZoom) {
        applyBaseViewport();
      } else {
        canvas.renderAll();
      }
      if (isPlacingPaper) updatePaperPlaceholderSize();
    });

    let isPlayerMode = false; // Mode Player (false = Editor mode)
    let isLassoMode = false;
    let isPanMode = false; // Mode main inactif par défaut
    let points = [];
    let curveHandles = {}; // Poignées de contrôle pour courber les lignes {segmentIndex: {x, y}}
    let isDraggingHandle = false;
    let draggingSegmentIndex = -1;
    let isDraggingPoint = false; // Glisser un point du polygone
    let draggingPointIndex = -1;
    let editingMask = null; // Masque en cours d'édition
    let backgroundImage;
    let tempLines = [];
    let tempCircles = [];
    let handleCircles = [];
    let previewLine = null;
    let polygonClosed = false; // Le polygone est-il fermé ?

    // Sélection masque: border violet
    let lastSelectedMask = null;
    function setMaskBorderColor(group, color) {
      if (!group || !group._objects || group._objects.length < 2) return;
      const border = group._objects[1];
      if (border && border.set) border.set({ stroke: color });
    }

    // === Variables pour le mode placement de papier ===
    let isPlacingPaper = false;
    let paperPreviewGroup = null;
    let paperPreviewSize = null; // { w, h, scale }
    // Nouveau: placeholder DOM img qui suit le curseur
    let paperPlaceholderImg = null;
    let paperPlaceholderMoveHandler = null;
    const paperPlaceholderScale = 0.5;
    let lastMousePos = { x: null, y: null };

    window.addEventListener('mousemove', (e) => {
      lastMousePos.x = e.clientX;
      lastMousePos.y = e.clientY;
    });

    function updatePaperPlaceholderSize() {
      if (!paperPlaceholderImg || !paperPreviewSize) return;
      const zoom = canvas.getZoom();
      paperPlaceholderImg.style.width = `${paperPreviewSize.w * zoom}px`;
      paperPlaceholderImg.style.height = `${paperPreviewSize.h * zoom}px`;
    }

    // Aide: positionner l'aperçu au centre de l'écran (en coordonnées monde)
    function positionPaperPreviewAtCenter() {
      if (!paperPreviewSize) return;
      if (paperPlaceholderImg) {
        const x = (lastMousePos.x !== null) ? lastMousePos.x : window.innerWidth / 2;
        const y = (lastMousePos.y !== null) ? lastMousePos.y : window.innerHeight / 2;
        paperPlaceholderImg.style.left = `${x}px`;
        paperPlaceholderImg.style.top = `${y}px`;
        updatePaperPlaceholderSize();
      }
      canvas.requestRenderAll();
    }

    function removePaperPlaceholder() {
      if (paperPlaceholderMoveHandler) {
        window.removeEventListener('mousemove', paperPlaceholderMoveHandler);
        paperPlaceholderMoveHandler = null;
      }
      if (paperPlaceholderImg && paperPlaceholderImg.parentNode) {
        paperPlaceholderImg.parentNode.removeChild(paperPlaceholderImg);
      }
      paperPlaceholderImg = null;
    }

    function cancelPaperPlacement() {
      // Supprimer le placeholder DOM
      removePaperPlaceholder();
      // Nettoyage ancien preview Fabric si existant
      if (paperPreviewGroup) {
        canvas.remove(paperPreviewGroup);
        paperPreviewGroup = null;
      }
      paperPreviewSize = null;
      isPlacingPaper = false;
      canvas.skipTargetFind = false;
      canvas.selection = !isPanMode;
      canvas.defaultCursor = isPanMode ? 'grab' : 'default';
      canvas.hoverCursor = isPanMode ? 'grab' : 'move';
      // Réinitialiser la couleur du bouton Ajouter Papier
      document.getElementById("addPaper").style.background = "#3a3a3a";
      canvas.requestRenderAll();
    }

    // ========== CHARGER L'IMAGE DE FOND ==========
    // Le chargement initial via imageDataUrl est supprimé.
    // L’image de fond est désormais pilotée par le select (#roomSelector)
    // et chargée par setBackgroundImage() avec une valeur par défaut.

    // ========== INIT SELECT / ROOMS ET IMAGE DE FOND ==========
    let currentBackgroundKey = '';
    const roomImages = <?php echo json_encode($roomImages); ?>;

    function pathToKey(p){
      const base = (p || '').split('/').pop() || '';
      return base.includes('.') ? base.substring(0, base.lastIndexOf('.')) : base;
    }

    function initRoomSelector() {
      const sel = document.getElementById('roomSelector');
      if (!sel) return;
      sel.innerHTML = '';
      if (!Array.isArray(roomImages) || roomImages.length === 0) {
        const opt = document.createElement('option');
        opt.textContent = 'Aucune image dans /rooms';
        opt.disabled = true;
        opt.selected = true;
        sel.appendChild(opt);
        return;
      }
      roomImages.forEach((path) => {
        const opt = document.createElement('option');
        opt.value = path;
        opt.textContent = path.split('/').pop();
        sel.appendChild(opt);
      });
      const defaultPath = 'rooms/P1080918.JPG';
      sel.value = roomImages.includes(defaultPath) ? defaultPath : roomImages[0];
      setBackgroundImage(sel.value);
      sel.addEventListener('change', (e) => setBackgroundImage(e.target.value));
    }

    // Remplace l’ancien chargement basé sur imageDataUrl par l’initialisation ci-dessus
    initRoomSelector();

    // Fonction utilitaire pour charger/remplacer l'image de fond
    function setBackgroundImage(src) {
      if (!src) return;
      currentBackgroundKey = pathToKey(src);
      // Nettoyer toute la scène avant de recharger pour ce fond (conserver le BG après)
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
          console.log('✅ Image de fond remplacée:', src, 'clé:', currentBackgroundKey);
          loadFromServer();
        },
        { crossOrigin: 'anonymous' }
      );
    }

    // ========== ZOOM / PAN ==========
    canvas.on("mouse:wheel", function (opt) {
      // Pas de zoom en mode Player
      if (isPlayerMode) {
        opt.e.preventDefault();
        opt.e.stopPropagation();
        return;
      }
      const delta = opt.e.deltaY;
      let zoom = canvas.getZoom();
      zoom *= 0.999 ** delta;
      // Ne jamais dézoomer en dessous du fit
      zoom = Math.max(Math.min(zoom, 10), baseZoom);
      canvas.zoomToPoint({ x: opt.e.offsetX, y: opt.e.offsetY }, zoom);
      isAtBaseZoom = Math.abs(canvas.getZoom() - baseZoom) < 1e-6;
      if (isPlacingPaper) updatePaperPlaceholderSize();
      opt.e.preventDefault();
      opt.e.stopPropagation();
    });

    let isDragging = false;
    let lastPosX, lastPosY;
    
    // Détecter la sélection d'objets
    canvas.on("selection:created", (e) => {
      const selectedObj = e.selected[0];
      // Si on sélectionne un masque et qu'on est en mode pan, désactiver le pan
      if (isPanMode && selectedObj && selectedObj.maskData && selectedObj.maskData.isMask) {
        isPanMode = false;
        document.getElementById("togglePan").style.background = "#3a3a3a";
        canvas.defaultCursor = 'default';
        canvas.hoverCursor = 'move';
        console.log("⚠️ Mode Main désactivé (masque sélectionné)");
      }
      // Colorer la bordure du masque sélectionné en violet
      if (selectedObj && selectedObj.maskData && selectedObj.maskData.isMask) {
        if (lastSelectedMask && lastSelectedMask !== selectedObj) {
          setMaskBorderColor(lastSelectedMask, 'lime');
        }
        setMaskBorderColor(selectedObj, 'purple');
        lastSelectedMask = selectedObj;
        canvas.requestRenderAll();
      } else if (lastSelectedMask) {
        setMaskBorderColor(lastSelectedMask, 'lime');
        lastSelectedMask = null;
        canvas.requestRenderAll();
      }
    });
    
    canvas.on("selection:updated", (e) => {
      const selectedObj = e.selected[0];
      // Si on sélectionne un masque et qu'on est en mode pan, désactiver le pan
      if (isPanMode && selectedObj && selectedObj.maskData && selectedObj.maskData.isMask) {
        isPanMode = false;
        document.getElementById("togglePan").style.background = "#3a3a3a";
        canvas.defaultCursor = 'default';
        canvas.hoverCursor = 'move';
        console.log("⚠️ Mode Main désactivé (masque sélectionné)");
      }
      // Mettre à jour la couleur de bordure selon la sélection
      if (selectedObj && selectedObj.maskData && selectedObj.maskData.isMask) {
        if (lastSelectedMask && lastSelectedMask !== selectedObj) {
          setMaskBorderColor(lastSelectedMask, 'lime');
        }
        setMaskBorderColor(selectedObj, 'purple');
        lastSelectedMask = selectedObj;
        canvas.requestRenderAll();
      } else if (lastSelectedMask) {
        setMaskBorderColor(lastSelectedMask, 'lime');
        lastSelectedMask = null;
        canvas.requestRenderAll();
      }
    });

    canvas.on("selection:cleared", () => {
      if (lastSelectedMask) {
        setMaskBorderColor(lastSelectedMask, 'lime');
        lastSelectedMask = null;
        canvas.requestRenderAll();
      }
    });

    canvas.on("mouse:down", (opt) => {
      // Si on est en mode placement de papier, valider l'emplacement et stopper le reste
      if (isPlacingPaper) {
        finalizePaperPlacement(opt);
        return;
      }
      // Pan autorisé uniquement en Editor et quand pas sur un objet
      if ((opt.e.altKey || isPanMode) && !opt.target && !isPlayerMode) {
        isDragging = true;
        lastPosX = opt.e.clientX;
        lastPosY = opt.e.clientY;
        canvas.defaultCursor = 'grabbing';
      }
    });
    canvas.on("mouse:move", (opt) => {
      // Déplacer l'aperçu de papier sous le curseur
      if (isPlacingPaper && paperPreviewGroup && paperPreviewSize) {
        const p = canvas.getPointer(opt.e);
        const left = p.x - paperPreviewSize.w / 2;
        const top = p.y - paperPreviewSize.h / 2;
        paperPreviewGroup.set({ left, top });
        canvas.requestRenderAll();
        return;
      }
      const pointer = canvas.getPointer(opt.e);
      
      if (isDragging) {
        const e = opt.e;
        const vpt = canvas.viewportTransform;
        vpt[4] += e.clientX - lastPosX;
        vpt[5] += e.clientY - lastPosY;
        canvas.requestRenderAll();
        lastPosX = e.clientX;
        lastPosY = e.clientY;
      } else if (isDraggingPoint && draggingPointIndex >= 0) {
        // Mettre à jour la position du point
        points[draggingPointIndex] = {
          x: pointer.x,
          y: pointer.y
        };
        
        // Ajuster les poignées des segments adjacents pour qu'elles restent au milieu
        const prevIdx = (draggingPointIndex - 1 + points.length) % points.length;
        const nextIdx = draggingPointIndex;
        
        // Si les segments adjacents ont des poignées, les réinitialiser au milieu
        if (curveHandles[prevIdx]) {
          const p1 = points[prevIdx];
          const p2 = points[draggingPointIndex];
          curveHandles[prevIdx] = {
            x: (p1.x + p2.x) / 2,
            y: (p1.y + p2.y) / 2
          };
        }
        
        if (curveHandles[nextIdx]) {
          const p1 = points[draggingPointIndex];
          const p2 = points[(draggingPointIndex + 1) % points.length];
          curveHandles[nextIdx] = {
            x: (p1.x + p2.x) / 2,
            y: (p1.y + p2.y) / 2
          };
        }
        
        updatePolygonPreview();
      } else if (isDraggingHandle && draggingSegmentIndex >= 0) {
        // Mettre à jour la position de la poignée
        curveHandles[draggingSegmentIndex] = {
          x: pointer.x,
          y: pointer.y
        };
        updatePolygonPreview();
      } else if (isLassoMode && points.length > 0 && !polygonClosed) {
        // Afficher une ligne de prévisualisation seulement si le polygone n'est pas fermé
        const lastPoint = points[points.length - 1];
        
        if (previewLine) {
          canvas.remove(previewLine);
        }
        
        previewLine = new fabric.Line(
          [lastPoint.x, lastPoint.y, pointer.x, pointer.y],
          {
            stroke: 'yellow',
            strokeWidth: 1,
            strokeDashArray: [5, 5],
            selectable: false,
            evented: false
          }
        );
        canvas.add(previewLine);
        canvas.renderAll();
      }
    });
    
    canvas.on("mouse:up", () => {
      isDragging = false;
      isDraggingHandle = false;
      draggingSegmentIndex = -1;
      isDraggingPoint = false;
      draggingPointIndex = -1;
      canvas.defaultCursor = isPanMode ? 'grab' : 'default';
    });

    function finalizePaperPlacement(opt) {
      if (!isPlacingPaper || !paperPreviewSize) return;
      const p = canvas.getPointer(opt.e);
      const paperLeft = p.x - paperPreviewSize.w / 2;
      const paperTop = p.y - paperPreviewSize.h / 2;
      // Supprimer le placeholder DOM et tout aperçu Fabric
      removePaperPlaceholder();
      if (paperPreviewGroup) {
        canvas.remove(paperPreviewGroup);
        paperPreviewGroup = null;
      }
      isPlacingPaper = false;
      const savedScale = paperPreviewSize.scale;
      const savedW = paperPreviewSize.w;
      const savedH = paperPreviewSize.h;
      paperPreviewSize = null;
      canvas.skipTargetFind = false;
      // Réinitialiser la couleur du bouton Ajouter Papier
      document.getElementById("addPaper").style.background = "#3a3a3a";
      
      // Créer le vrai papier à l'endroit cliqué
      fabric.Image.fromURL(paperDataUrl, (paperImg) => {
        paperImg.set({
          left: paperLeft,
          top: paperTop,
          scaleX: savedScale,
          scaleY: savedScale,
          selectable: false,
          evented: false,
          originX: 'left',
          originY: 'top'
        });
        const paperBorder = new fabric.Rect({
          left: paperLeft,
          top: paperTop,
          width: savedW,
          height: savedH,
          fill: 'transparent',
          stroke: 'cyan',
          strokeWidth: 2,
          strokeDashArray: [5, 5],
          selectable: false,
          evented: false,
          originX: 'left',
          originY: 'top',
          visible: !isPlayerMode
        });
        const paperGroup = new fabric.Group([paperImg, paperBorder], {
          left: paperLeft,
          top: paperTop,
          selectable: true,
          evented: true,
          hasControls: true,
          hasBorders: false,
          cornerColor: 'cyan',
          cornerStyle: 'circle',
          subTargetCheck: false
        });
        canvas.add(paperGroup);
        canvas.selection = true;
        canvas.setActiveObject(paperGroup);
        canvas.defaultCursor = 'move';
        canvas.hoverCursor = 'move';
        canvas.requestRenderAll();
        console.log("✅ Papier ajouté !");
      });
    }

    // ========== MODE LASSO ==========
    document.getElementById("toggleLasso").onclick = () => {
      // Si on désactive le lasso et qu'on était en train d'éditer, remettre le masque
      if (isLassoMode && editingMask) {
        canvas.add(editingMask);
        editingMask = null;
      }
      
      isLassoMode = !isLassoMode;
      
      // Désactiver le mode Pan si on active le Lasso
      if (isLassoMode && isPanMode) {
        isPanMode = false;
        document.getElementById("togglePan").style.background = "#3a3a3a";
        canvas.defaultCursor = 'default';
      }
      
      // Nettoyer les tracés temporaires
      points = [];
      curveHandles = {};
      polygonClosed = false;
      tempLines.forEach(line => canvas.remove(line));
      tempLines = [];
      tempCircles.forEach(circle => canvas.remove(circle));
      tempCircles = [];
      handleCircles.forEach(handle => canvas.remove(handle));
      handleCircles = [];
      if (previewLine) {
        canvas.remove(previewLine);
        previewLine = null;
      }
      document.getElementById("validateMask").style.display = "none";
      
      // Désactiver la sélection en mode lasso
      canvas.selection = !isLassoMode;
      canvas.discardActiveObject();
      canvas.renderAll();
      
      document.getElementById("toggleLasso").style.background = isLassoMode
        ? "#1a7f1a"
        : "#3a3a3a";
    };
    
    // ========== BOUTON VALIDER LE MASQUE ==========
    document.getElementById("validateMask").onclick = () => {
      if (points.length < 3) return;
      
      // Si on édite un masque existant, le supprimer
      if (editingMask) {
        canvas.remove(editingMask);
        editingMask = null;
      }
      
      createCutout();
      
      // Réinitialiser
      points = [];
      curveHandles = {};
      polygonClosed = false;
      tempLines.forEach(line => canvas.remove(line));
      tempLines = [];
      tempCircles.forEach(circle => canvas.remove(circle));
      tempCircles = [];
      handleCircles.forEach(handle => canvas.remove(handle));
      handleCircles = [];
      document.getElementById("validateMask").style.display = "none";
    };
    
    // ========== BOUTON MODIFIER LE TRACÉ ==========
    document.getElementById("editMask").onclick = () => {
      const activeObject = canvas.getActiveObject();
      
      if (!activeObject) {
        alert("❌ Veuillez d'abord sélectionner un masque à modifier");
        return;
      }
      
      if (!activeObject.maskData || !activeObject.maskData.isMask) {
        alert("❌ Cet objet n'est pas un masque modifiable");
        return;
      }
      
      console.log("✏️ Édition du masque...");
      
      // Récupérer les points originaux
      points = JSON.parse(JSON.stringify(activeObject.maskData.originalPoints));
      curveHandles = JSON.parse(JSON.stringify(activeObject.maskData.curveHandles));
      
      // Stocker le masque en cours d'édition et le cacher complètement
      editingMask = activeObject;
      canvas.remove(editingMask); // Le retirer du canvas pour qu'il soit invisible
      
      // Activer le mode lasso et fermer le polygone
      isLassoMode = true;
      polygonClosed = true;
      document.getElementById("toggleLasso").style.background = "#1a7f1a";
      
      // Désactiver le mode pan si actif
      if (isPanMode) {
        isPanMode = false;
        document.getElementById("togglePan").style.background = "#3a3a3a";
      }
      
      // Afficher le bouton valider
      document.getElementById("validateMask").style.display = "flex";
      
      // Désélectionner l'objet et désactiver la sélection
      canvas.discardActiveObject();
      canvas.selection = false;
      canvas.renderAll();
      
      // Afficher le polygone éditable
      updatePolygonPreview();
      
      console.log("✅ Vous pouvez maintenant modifier les points et courbes. Cliquez sur 'Valider' quand c'est terminé.");
    };
    
    // ========== MODE MAIN (PAN) ==========
    document.getElementById("togglePan").onclick = () => {
      isPanMode = !isPanMode;
      
      // Désactiver le mode Lasso si on active le Pan
      if (isPanMode && isLassoMode) {
        isLassoMode = false;
        document.getElementById("toggleLasso").style.background = "#3a3a3a";
        // Nettoyer les tracés temporaires
        points = [];
        tempLines.forEach(line => canvas.remove(line));
        tempLines = [];
        tempCircles.forEach(circle => canvas.remove(circle));
        tempCircles = [];
        if (previewLine) {
          canvas.remove(previewLine);
          previewLine = null;
        }
        document.getElementById("validateMask").style.display = "none";
      }
      
      // Ne pas désactiver la sélection en mode Pan
      canvas.selection = true;
      canvas.discardActiveObject();
      canvas.defaultCursor = isPanMode ? 'grab' : 'default';
      canvas.hoverCursor = isPanMode ? 'grab' : 'move';
      canvas.renderAll();
      
      document.getElementById("togglePan").style.background = isPanMode
        ? "#1a7f1a"
        : "#3a3a3a";
    };

    canvas.on("mouse:down", (opt) => {
      if (!isLassoMode || opt.e.altKey) return;
      
      const pointer = canvas.getPointer(opt.e);
      
      // Si c'est le premier point
      if (points.length === 0) {
        points.push({ x: pointer.x, y: pointer.y });
        
        // Ajouter un cercle pour visualiser le point
        const circle = new fabric.Circle({
          left: pointer.x - 4,
          top: pointer.y - 4,
          radius: 4,
          fill: 'lime',
          selectable: false,
          evented: false
        });
        canvas.add(circle);
        tempCircles.push(circle);
        
        console.log("Premier point placé");
        return;
      }
      
      // Si le polygone est fermé, on est en mode édition avec poignées
      if (polygonClosed) {
        // D'abord vérifier si on clique sur un point du polygone (rayon de 12px pour faciliter)
        for (let i = 0; i < points.length; i++) {
          const p = points[i];
          const distToPoint = Math.sqrt(
            Math.pow(pointer.x - p.x, 2) + 
            Math.pow(pointer.y - p.y, 2)
          );
          
          if (distToPoint < 12) {
            // Commencer à glisser ce point
            isDraggingPoint = true;
            draggingPointIndex = i;
            console.log(`Glissement du point ${i}`);
            return;
          }
        }
        
        // Sinon, vérifier si on clique sur une poignée de courbe
        for (let i = 0; i < points.length; i++) {
          const p1 = points[i];
          const p2 = points[(i + 1) % points.length];
          
          // Position par défaut de la poignée (au milieu de la ligne)
          const handlePos = curveHandles[i] || {
            x: (p1.x + p2.x) / 2,
            y: (p1.y + p2.y) / 2
          };
          
          const distToHandle = Math.sqrt(
            Math.pow(pointer.x - handlePos.x, 2) + 
            Math.pow(pointer.y - handlePos.y, 2)
          );
          
          if (distToHandle < 10) {
            // Commencer à glisser cette poignée
            isDraggingHandle = true;
            draggingSegmentIndex = i;
            console.log(`Glissement de la poignée du segment ${i}`);
            return;
          }
        }
        return;
      }
      
      // Vérifier si on clique près du premier point pour fermer le polygone
      const firstPoint = points[0];
      const distToFirst = Math.sqrt(
        Math.pow(pointer.x - firstPoint.x, 2) + 
        Math.pow(pointer.y - firstPoint.y, 2)
      );
      
      if (distToFirst < 10 && points.length >= 3) {
        // Fermer le polygone et passer en mode édition
        console.log("Polygone fermé. Cliquez sur les points ou lignes pour les arrondir. Puis cliquez sur 'Valider le masque'.");
        polygonClosed = true;
        
        // Supprimer la ligne de prévisualisation jaune
        if (previewLine) {
          canvas.remove(previewLine);
          previewLine = null;
        }
        
        document.getElementById("validateMask").style.display = "flex";
        updatePolygonPreview();
        return;
      }
      
      // Ajouter un nouveau point
      const lastPoint = points[points.length - 1];
      
      // Créer une ligne permanente verte
      const line = new fabric.Line(
        [lastPoint.x, lastPoint.y, pointer.x, pointer.y],
        {
          stroke: 'lime',
          strokeWidth: 2,
          selectable: false,
          evented: false
        }
      );
      canvas.add(line);
      tempLines.push(line);
      
      // Ajouter un cercle pour visualiser le nouveau point
      const circle = new fabric.Circle({
        left: pointer.x - 4,
        top: pointer.y - 4,
        radius: 4,
        fill: 'lime',
        selectable: false,
        evented: false
      });
      canvas.add(circle);
      tempCircles.push(circle);
      
      points.push({ x: pointer.x, y: pointer.y });
      console.log("Point ajouté, total:", points.length);
    });

    // Double-clic pour fermer le polygone
    canvas.on("mouse:dblclick", (opt) => {
      if (!isLassoMode || points.length < 3 || polygonClosed) return;
      console.log("Polygone fermé. Cliquez sur les points ou lignes pour les arrondir. Puis cliquez sur 'Valider le masque'.");
      polygonClosed = true;
      
      // Supprimer la ligne de prévisualisation jaune
      if (previewLine) {
        canvas.remove(previewLine);
        previewLine = null;
      }
      
      document.getElementById("validateMask").style.display = "flex";
      updatePolygonPreview();
    });
    
    // Gestion des touches clavier
    document.addEventListener('keydown', (e) => {
      // Annuler le placement de papier avec Echap
      if (e.key === 'Escape' && isPlacingPaper) {
        cancelPaperPlacement();
        return;
      }
      // Echap pour annuler le tracé
      if (e.key === 'Escape' && isLassoMode && points.length > 0) {
        console.log("Tracé annulé");
        
        // Si on était en train d'éditer un masque, le remettre
        if (editingMask) {
          canvas.add(editingMask);
          editingMask = null;
        }
        
        points = [];
        curveHandles = {};
        polygonClosed = false;
        tempLines.forEach(line => canvas.remove(line));
        tempLines = [];
        tempCircles.forEach(circle => canvas.remove(circle));
        tempCircles = [];
        handleCircles.forEach(handle => canvas.remove(handle));
        handleCircles = [];
        if (previewLine) {
          canvas.remove(previewLine);
          previewLine = null;
        }
        document.getElementById("validateMask").style.display = "none";
        canvas.renderAll();
        return;
      }
      
      const activeObject = canvas.getActiveObject();
      if (!activeObject || activeObject === backgroundImage) return;
      
      // Flèches directionnelles pour déplacer (sauf si l'objet est verrouillé)
      const moveStep = e.shiftKey ? 10 : 1; // 10 pixels si Shift est enfoncé
      
      switch(e.key) {
        case 'ArrowUp':
          if (!activeObject.lockMovementY) {
            activeObject.set('top', activeObject.top - moveStep);
            canvas.renderAll();
          }
          e.preventDefault();
          break;
        case 'ArrowDown':
          if (!activeObject.lockMovementY) {
            activeObject.set('top', activeObject.top + moveStep);
            canvas.renderAll();
          }
          e.preventDefault();
          break;
        case 'ArrowLeft':
          if (!activeObject.lockMovementX) {
            activeObject.set('left', activeObject.left - moveStep);
            canvas.renderAll();
          }
          e.preventDefault();
          break;
        case 'ArrowRight':
          if (!activeObject.lockMovementX) {
            activeObject.set('left', activeObject.left + moveStep);
            canvas.renderAll();
          }
          e.preventDefault();
          break;
        case 'Delete':
        case 'Backspace':
          canvas.remove(activeObject);
          canvas.renderAll();
          console.log("✅ Objet supprimé");
          e.preventDefault();
          break;
      }
    });
    
    // Fonction pour calculer la distance entre un point et un segment de ligne
    function distancePointToSegment(point, p1, p2) {
      const A = point.x - p1.x;
      const B = point.y - p1.y;
      const C = p2.x - p1.x;
      const D = p2.y - p1.y;

      const dot = A * C + B * D;
      const lenSq = C * C + D * D;
      let param = -1;
      
      if (lenSq !== 0) {
        param = dot / lenSq;
      }

      let xx, yy;

      if (param < 0) {
        xx = p1.x;
        yy = p1.y;
      } else if (param > 1) {
        xx = p2.x;
        yy = p2.y;
      } else {
        xx = p1.x + param * C;
        yy = p1.y + param * D;
      }

      const dx = point.x - xx;
      const dy = point.y - yy;
      return Math.sqrt(dx * dx + dy * dy);
    }
    
    // Fonction pour mettre à jour la prévisualisation du polygone fermé
    function updatePolygonPreview() {
      // Nettoyer les éléments temporaires
      tempLines.forEach(line => canvas.remove(line));
      tempLines = [];
      tempCircles.forEach(circle => canvas.remove(circle));
      tempCircles = [];
      handleCircles.forEach(handle => canvas.remove(handle));
      handleCircles = [];
      
      // Créer le chemin avec courbes de Bézier
      let pathString = '';
      
      for (let i = 0; i < points.length; i++) {
        const p1 = points[i];
        const p2 = points[(i + 1) % points.length];
        
        if (i === 0) {
          pathString = `M ${p1.x} ${p1.y}`;
        }
        
        // Vérifier si ce segment a une poignée déplacée
        const handle = curveHandles[i];
        
        if (handle) {
          // Courbe de Bézier quadratique
          pathString += ` Q ${handle.x} ${handle.y}, ${p2.x} ${p2.y}`;
        } else {
          // Ligne droite
          pathString += ` L ${p2.x} ${p2.y}`;
        }
      }
      
      pathString += ' Z'; // Fermer le chemin
      
      // Dessiner le chemin
      const path = new fabric.Path(pathString, {
        fill: 'transparent',
        stroke: 'lime',
        strokeWidth: 2,
        selectable: false,
        evented: false
      });
      canvas.add(path);
      tempLines.push(path);
      
      // Dessiner les points (plus gros pour faciliter le glissement)
      points.forEach((point, idx) => {
        const circle = new fabric.Circle({
          left: point.x - 6,
          top: point.y - 6,
          radius: 6,
          fill: 'lime',
          stroke: 'white',
          strokeWidth: 2,
          selectable: false,
          evented: false
        });
        canvas.add(circle);
        tempCircles.push(circle);
      });
      
      // Dessiner les poignées de courbe (carrés cyan)
      for (let i = 0; i < points.length; i++) {
        const p1 = points[i];
        const p2 = points[(i + 1) % points.length];
        
        const handlePos = curveHandles[i] || {
          x: (p1.x + p2.x) / 2,
          y: (p1.y + p2.y) / 2
        };
        
        const isCurved = curveHandles[i] !== undefined;
        
        const handle = new fabric.Rect({
          left: handlePos.x - 4,
          top: handlePos.y - 4,
          width: 8,
          height: 8,
          fill: isCurved ? 'cyan' : 'rgba(0, 255, 255, 0.3)',
          stroke: 'cyan',
          strokeWidth: 1,
          selectable: false,
          evented: false
        });
        canvas.add(handle);
        handleCircles.push(handle);
        
        // Si courbé, dessiner une ligne vers la poignée
        if (isCurved) {
          const guideLines = [
            new fabric.Line([p1.x, p1.y, handlePos.x, handlePos.y], {
              stroke: 'rgba(0, 255, 255, 0.5)',
              strokeWidth: 1,
              strokeDashArray: [3, 3],
              selectable: false,
              evented: false
            }),
            new fabric.Line([p2.x, p2.y, handlePos.x, handlePos.y], {
              stroke: 'rgba(0, 255, 255, 0.5)',
              strokeWidth: 1,
              strokeDashArray: [3, 3],
              selectable: false,
              evented: false
            })
          ];
          guideLines.forEach(line => {
            canvas.add(line);
            tempLines.push(line);
          });
        }
      }
      
      canvas.renderAll();
    }
    
    // Fonction pour lisser seulement les points marqués comme arrondis
    function applyRoundedCorners(pts, roundedSet) {
      if (pts.length < 3) return pts;
      
      const result = [];
      
      for (let i = 0; i < pts.length; i++) {
        const p0 = pts[(i - 1 + pts.length) % pts.length];
        const p1 = pts[i];
        const p2 = pts[(i + 1) % pts.length];
        
        // Si le point actuel doit être arrondi
        if (roundedSet.has(i)) {
          // Calculer les points de contrôle pour une courbe quadratique de Bézier
          const radius = 15; // Rayon de l'arrondi
          
          // Vecteur de p0 vers p1
          const v1x = p1.x - p0.x;
          const v1y = p1.y - p0.y;
          const len1 = Math.sqrt(v1x * v1x + v1y * v1y);
          
          // Vecteur de p2 vers p1
          const v2x = p1.x - p2.x;
          const v2y = p1.y - p2.y;
          const len2 = Math.sqrt(v2x * v2x + v2y * v2y);
          
          if (len1 > 0 && len2 > 0) {
            // Point de départ de la courbe (sur la ligne p0-p1)
            const startX = p1.x - (v1x / len1) * radius;
            const startY = p1.y - (v1y / len1) * radius;
            
            // Point de fin de la courbe (sur la ligne p1-p2)
            const endX = p1.x - (v2x / len2) * radius;
            const endY = p1.y - (v2y / len2) * radius;
            
            // Ajouter le point de départ
            result.push({ x: startX, y: startY });
            
            // Créer la courbe quadratique de Bézier
            const steps = 8;
            for (let t = 1; t <= steps; t++) {
              const tt = t / steps;
              const mt = 1 - tt;
              
              // Courbe quadratique: B(t) = (1-t)²P0 + 2(1-t)tP1 + t²P2
              const x = mt * mt * startX + 2 * mt * tt * p1.x + tt * tt * endX;
              const y = mt * mt * startY + 2 * mt * tt * p1.y + tt * tt * endY;
              
              result.push({ x, y });
            }
          } else {
            result.push(p1);
          }
        } else {
          // Point non arrondi, garder tel quel
          result.push(p1);
        }
      }
      
      return result;
    }
    
    // Fonction pour créer l'image découpée
    function createCutout() {
      // Supprimer les lignes temporaires et la ligne de prévisualisation
      tempLines.forEach(line => canvas.remove(line));
      tempLines = [];
      tempCircles.forEach(circle => canvas.remove(circle));
      tempCircles = [];
      if (previewLine) {
        canvas.remove(previewLine);
        previewLine = null;
      }
      
      // IMPORTANT : Sauvegarder les points et poignées avant le callback asynchrone
      const savedPoints = [...points];
      const savedHandles = { ...curveHandles};
      
      // Convertir le chemin avec courbes de Bézier en points
      let finalPoints = [];
      
      for (let i = 0; i < savedPoints.length; i++) {
        const p1 = savedPoints[i];
        const p2 = savedPoints[(i + 1) % savedPoints.length];
        const handle = savedHandles[i];
        
        if (handle) {
          // Courbe de Bézier quadratique : interpoler des points
          const steps = 15;
          for (let t = 0; t < steps; t++) {
            const tt = t / steps;
            const mt = 1 - tt;
            
            // Formule de Bézier quadratique
            const x = mt * mt * p1.x + 2 * mt * tt * handle.x + tt * tt * p2.x;
            const y = mt * mt * p1.y + 2 * mt * tt * handle.y + tt * tt * p2.y;
            
            finalPoints.push({ x, y });
          }
        } else {
          // Ligne droite
          finalPoints.push(p1);
        }
      }
      
      const savedFinalPoints = finalPoints.length > 0 ? finalPoints : savedPoints;
      console.log("✅ Courbes appliquées:", savedPoints.length, "points originaux →", savedFinalPoints.length, "points avec courbes");
      
      // Calculer les limites du polygone
      const clipPolygon = new fabric.Polygon(savedFinalPoints, {
        fill: "transparent",
        stroke: "transparent"
      });
      const bounds = clipPolygon.getBoundingRect();
      
      console.log("Création de l'image découpée...", savedFinalPoints.length, "points");
      
      // Créer un canvas temporaire pour découper l'image
      const tempCanvas = document.createElement('canvas');
      const ctx = tempCanvas.getContext('2d');
      
      // Dimensionner le canvas temporaire
      tempCanvas.width = bounds.width;
      tempCanvas.height = bounds.height;
      
      // Dessiner le masque (le polygone)
      ctx.beginPath();
      savedFinalPoints.forEach((point, index) => {
        const x = point.x - bounds.left;
        const y = point.y - bounds.top;
        if (index === 0) {
          ctx.moveTo(x, y);
        } else {
          ctx.lineTo(x, y);
        }
      });
      ctx.closePath();
      ctx.clip();
      
      // Récupérer l'image source de l'élément fabric
      const imgElement = backgroundImage._element;
      const imgScale = backgroundImage.scaleX;
      const imgLeft = backgroundImage.left || 0;
      const imgTop = backgroundImage.top || 0;
      
      // Dessiner la partie de l'image dans le masque
      ctx.drawImage(
        imgElement,
        (bounds.left - imgLeft) / imgScale,  // source x
        (bounds.top - imgTop) / imgScale,    // source y
        bounds.width / imgScale,              // source width
        bounds.height / imgScale,             // source height
        0,                                    // dest x
        0,                                    // dest y
        bounds.width,                         // dest width
        bounds.height                         // dest height
      );
      
      // Créer une nouvelle image fabric à partir du canvas temporaire
      fabric.Image.fromURL(tempCanvas.toDataURL(), (cutoutImg) => {
        cutoutImg.set({
          left: bounds.left,
          top: bounds.top,
          selectable: false, // L'image seule n'est pas sélectionnable
          evented: false,
          originX: 'left',
          originY: 'top'
        });
        
        // Créer le polygone de bordure verte en pointillés (suit le contour exact avec courbes)
        const borderPolygon = new fabric.Polygon(
          savedFinalPoints.map(p => ({ x: p.x - bounds.left, y: p.y - bounds.top })),
          {
            left: bounds.left,
            top: bounds.top,
            fill: 'transparent',
            stroke: 'lime',
            strokeWidth: 2,
            strokeDashArray: [5, 5],
            selectable: false,
            evented: false,
            originX: 'left',
            originY: 'top',
            visible: !isPlayerMode // Cacher en mode Player
          }
        );
        
        // Créer un groupe contenant l'image et la bordure
        const maskGroup = new fabric.Group([cutoutImg, borderPolygon], {
          left: bounds.left,
          top: bounds.top,
          selectable: true,
          evented: true,
          lockMovementX: true,
          lockMovementY: true,
          lockRotation: true,
          lockScalingX: true,
          lockScalingY: true,
          hasControls: false,
          hasBorders: false, // Pas besoin de bordure supplémentaire
          subTargetCheck: false,
          // Stocker les données pour modification ultérieure
          maskData: {
            originalPoints: JSON.parse(JSON.stringify(savedPoints)),
            curveHandles: JSON.parse(JSON.stringify(savedHandles)),
            isMask: true
          }
        });
        
        canvas.add(maskGroup);
        
        // Désactiver le mode lasso et sélectionner le groupe
        isLassoMode = false;
        document.getElementById("toggleLasso").style.background = "#3a3a3a";
        canvas.selection = true;
        canvas.setActiveObject(maskGroup);
        canvas.renderAll();
        
        console.log("✅ Masque créé !");
        saveCanvasState();
      });
      
      // Réinitialiser
      points = [];
    }

    // ========== SAUVEGARDE / RESTAURATION ==========
    function saveCanvasState() {
      // La sauvegarde automatique est désactivée
      // Utiliser le bouton "💾 Sauvegarder" pour sauvegarder manuellement
    }
    
    // ========== SAUVEGARDE / CHARGEMENT SERVEUR ==========
    function saveToServer() {
      const objectsToSave = [];
      canvas.getObjects().forEach(obj => {
        if (obj === backgroundImage) return; // Ne pas sauvegarder l'image de fond
        if (obj.maskData && obj.maskData.isMask) {
          objectsToSave.push({
            type: 'mask',
            originalPoints: obj.maskData.originalPoints,
            curveHandles: obj.maskData.curveHandles,
            left: obj.left,
            top: obj.top
          });
        } else if (obj._objects && obj._objects.length >= 2) {
          objectsToSave.push({
            type: 'paper',
            left: obj.left,
            top: obj.top,
            scaleX: obj.scaleX,
            scaleY: obj.scaleY,
            angle: obj.angle
          });
        }
      });

      const dataToSave = JSON.stringify(objectsToSave, null, 2);

      fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=save&key=' + encodeURIComponent(currentBackgroundKey) + '&data=' + encodeURIComponent(dataToSave)
      })
      .then(response => response.json())
      .then(result => {
        if (result.success) {
          console.log('✅ Sauvegardé pour', currentBackgroundKey, '(', objectsToSave.length, 'objets)');
          alert('✅ Données sauvegardées avec succès !');
        } else {
          console.error('❌ Erreur de sauvegarde');
        }
      })
      .catch(error => {
        console.error('❌ Erreur:', error);
        alert('❌ Erreur lors de la sauvegarde');
      });
    }
    
    function loadFromServer() {
      // Nettoyer les objets (hors image de fond) avant de recréer
      canvas.getObjects().slice().forEach(o => { if (o !== backgroundImage) canvas.remove(o); });
      fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=load&key=' + encodeURIComponent(currentBackgroundKey)
      })
      .then(response => response.json())
      .then(result => {
        const dataStr = result && result.success ? result.data : null;
        if (!dataStr) {
          console.log('ℹ️ Rien à charger pour', currentBackgroundKey);
          return;
        }
        let savedObjects = [];
        try { savedObjects = JSON.parse(dataStr) || []; } catch(e) { savedObjects = []; }
        if (!Array.isArray(savedObjects) || savedObjects.length === 0) {
          console.log('ℹ️ Aucune entrée pour', currentBackgroundKey);
          canvas.renderAll();
          return;
        }
        console.log('📂 Chargement de', savedObjects.length, 'objets pour', currentBackgroundKey, '...');
        let loaded = 0;
        savedObjects.forEach(objData => {
          if (objData.type === 'mask') {
            recreateMask(objData, () => { loaded++; if (loaded === savedObjects.length) console.log('✅ Tous les objets chargés !'); });
          } else if (objData.type === 'paper') {
            recreatePaper(objData, () => { loaded++; if (loaded === savedObjects.length) console.log('✅ Tous les objets chargés !'); });
          }
        });
      })
      .catch(error => {
        console.error('❌ Erreur de chargement:', error);
      });
    }

    // Fonction pour recréer un masque à partir des données sauvegardées
    function recreateMask(maskData, callback) {
      // Utiliser la même logique que createCutout mais avec des points sauvegardés
      const savedPoints = maskData.originalPoints;
      const savedHandles = maskData.curveHandles;
      
      // Convertir les courbes en points
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
      const clipPolygon = new fabric.Polygon(points, {
        fill: "transparent",
        stroke: "transparent"
      });
      const bounds = clipPolygon.getBoundingRect();
      
      // Créer le canvas de découpe
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
          evented: false,
          originX: 'left',
          originY: 'top'
        });
        
        const borderPolygon = new fabric.Polygon(
          points.map(p => ({ x: p.x - bounds.left, y: p.y - bounds.top })),
          {
            left: bounds.left,
            top: bounds.top,
            fill: 'transparent',
            stroke: 'lime',
            strokeWidth: 2,
            strokeDashArray: [5, 5],
            selectable: false,
            evented: false,
            originX: 'left',
            originY: 'top',
            visible: !isPlayerMode
          }
        );
        
        const maskGroup = new fabric.Group([cutoutImg, borderPolygon], {
          left: bounds.left,
          top: bounds.top,
          selectable: true,
          evented: true,
          lockMovementX: true,
          lockMovementY: true,
          lockRotation: true,
          lockScalingX: true,
          lockScalingY: true,
          hasControls: false,
          hasBorders: false,
          subTargetCheck: false,
          maskData: {
            originalPoints: savedPoints,
            curveHandles: savedHandles,
            isMask: true
          }
        });
        
        canvas.add(maskGroup);
        canvas.renderAll();
        if (callback) callback();
      });
    }
    
    // Fonction pour recréer un papier à partir des données sauvegardées
    function recreatePaper(paperData, callback) {
      if (!paperDataUrl) {
        if (callback) callback();
        return;
      }
      
      fabric.Image.fromURL(paperDataUrl, (paperImg) => {
        paperImg.set({
          left: 0,
          top: 0,
          scaleX: 0.5,
          scaleY: 0.5,
          selectable: false,
          evented: false,
          originX: 'left',
          originY: 'top'
        });
        
        const paperWidth = paperImg.width * 0.5;
        const paperHeight = paperImg.height * 0.5;
        
        const paperBorder = new fabric.Rect({
          left: 0,
          top: 0,
          width: paperWidth,
          height: paperHeight,
          fill: 'transparent',
          stroke: 'cyan',
          strokeWidth: 2,
          strokeDashArray: [5, 5],
          selectable: false,
          evented: false,
          originX: 'left',
          originY: 'top',
          visible: !isPlayerMode
        });
        
        const paperGroup = new fabric.Group([paperImg, paperBorder], {
          left: paperData.left,
          top: paperData.top,
          scaleX: paperData.scaleX || 1,
          scaleY: paperData.scaleY || 1,
          angle: paperData.angle || 0,
          selectable: true,
          evented: true,
          hasControls: true,
          hasBorders: false,
          cornerColor: 'cyan',
          cornerStyle: 'circle',
          subTargetCheck: false
        });
        
        canvas.add(paperGroup);
        canvas.renderAll();
        if (callback) callback();
      });
    }
    
    // Bouton de sauvegarde
    document.getElementById("saveData").onclick = () => {
      saveToServer();
    };
    
    // Nettoyer l'ancien cache localStorage
    localStorage.removeItem("fabricCanvas");
    
    // Initialiser le bouton Mode Main comme inactif
    document.getElementById("togglePan").style.background = "#3a3a3a";

    // ========== TOGGLE EDITOR/PLAYER MODE ==========
    function toggleBordersVisibility() {
      canvas.getObjects().forEach(obj => {
        // Masques et papiers ont des bordures
        if (obj.maskData || obj._objects) {
          // En player mode : cacher les bordures
          // En editor mode : montrer les bordures
          obj.set({
            opacity: isPlayerMode ? 1 : 1, // L'objet reste visible
            visible: true
          });
          
          // Cacher/montrer les bordures des groupes
          if (obj._objects && obj._objects.length > 1) {
            obj._objects.forEach((subObj, idx) => {
              // Le deuxième objet est généralement la bordure (rectangle ou polygone)
              if (idx === 1) {
                subObj.set({ visible: !isPlayerMode });
              }
            });
          }
        }
      });
      canvas.renderAll();
    }
    
    document.getElementById("modeToggle").onclick = () => {
      isPlayerMode = !isPlayerMode;
      const btn = document.getElementById("modeToggle");
      const icon = btn.querySelector('.icon');
      const label = btn.querySelector('.btn-label');
      const toolbarTop = document.querySelector('#toolbar .toolbar-top');
      
      if (isPlayerMode) {
        // Mode Player
        icon.textContent = "🎮";
        label.textContent = "Player Mode";
        btn.style.background = "#1a7f1a";
        // Afficher uniquement la section du bas (Sauvegarder + Mode)
        if (toolbarTop) toolbarTop.style.display = 'none';
        document.getElementById("saveData").style.display = "inline-flex";
        // Désactiver tous les modes d'édition
        isLassoMode = false;
        isPanMode = false;
        canvas.selection = false;
        canvas.discardActiveObject();
        canvas.getObjects().forEach(obj => { if (obj !== backgroundImage) obj.set({ selectable: false, evented: false }); });
        // Réinitialiser zoom/pan au fit
        resetZoomAndPan();
        canvas.defaultCursor = 'default';
        canvas.hoverCursor = 'default';
        
        console.log("🎮 Mode Player activé - Bordures masquées, édition désactivée, zoom réinitialisé");
      } else {
        // Mode Editor
        icon.textContent = "🛠️";
        label.textContent = "Editor Mode";
        btn.style.background = "#3a3a3a";
        // Réafficher les actions du haut
        if (toolbarTop) toolbarTop.style.display = 'flex';
        document.getElementById("saveData").style.display = "inline-flex";
        
        // Réactiver la sélection
        canvas.selection = true;
        canvas.getObjects().forEach(obj => { if (obj !== backgroundImage) obj.set({ selectable: true, evented: true }); });
        
        // Curseurs
        canvas.defaultCursor = isPanMode ? 'grab' : 'default';
        canvas.hoverCursor = isPanMode ? 'grab' : 'move';
        
        console.log("🛠️ Mode Editor activé - Bordures visibles, édition activée");
      }
      
      toggleBordersVisibility();
    };
    
    // ========== BOUTON AJOUTER PAPIER ==========
    const paperDataUrl = <?php echo json_encode($paperData); ?>;
    
    document.getElementById("addPaper").onclick = () => {
      if (!paperDataUrl) {
        alert("L'image papier.png n'a été trouvée !");
        return;
      }
      if (isPlacingPaper) return; // déjà en placement
      isPlacingPaper = true;
      // Bouton actif en vert
      document.getElementById("addPaper").style.background = "#1a7f1a";
      canvas.discardActiveObject();
      // Désactiver la sélection et le ciblage pour éviter les sélections de zone pendant le placement
      canvas.selection = false;
      canvas.skipTargetFind = true;
      canvas.defaultCursor = 'crosshair';
      canvas.hoverCursor = 'crosshair';
      
      // Créer un placeholder DOM qui suit le curseur (50% opacité) et taille EXACTE selon zoom
      const img = new Image();
      img.src = paperDataUrl;
      img.style.position = 'fixed';
      img.style.pointerEvents = 'none';
      img.style.opacity = '0.5';
      img.style.zIndex = '2000';
      img.style.transform = 'translate(-50%, -50%)'; // centrer sous le curseur
      img.style.display = 'none';
      document.body.appendChild(img);
      paperPlaceholderImg = img;
      img.onload = () => {
        const wWorld = img.naturalWidth * paperPlaceholderScale;
        const hWorld = img.naturalHeight * paperPlaceholderScale;
        // Mémoriser la taille MONDE pour placer l'objet final
        paperPreviewSize = { w: wWorld, h: hWorld, scale: paperPlaceholderScale };
        img.style.display = 'block';
        positionPaperPreviewAtCenter(); // place sous le curseur si on a déjà une position
      };
      
      // Suivre le curseur
      paperPlaceholderMoveHandler = (e) => {
        if (!paperPlaceholderImg) return;
        paperPlaceholderImg.style.left = `${e.clientX}px`;
        paperPlaceholderImg.style.top = `${e.clientY}px`;
        updatePaperPlaceholderSize();
      };
      window.addEventListener('mousemove', paperPlaceholderMoveHandler);
      console.log("👆 Placez le papier avec un clic.");
    };

    // ========== BOUTONS Z-INDEX ==========
    document.getElementById("bringForward").onclick = () => {
      const activeObject = canvas.getActiveObject();
      if (!activeObject) {
        console.log("❌ Aucun objet sélectionné");
        return;
      }
      
      if (activeObject === backgroundImage) {
        console.log("❌ Impossible de déplacer l'image de fond");
        return;
      }
      
      console.log("Ordre AVANT:", canvas.getObjects().indexOf(activeObject));
      
      // Mettre complètement au premier plan
      activeObject.bringToFront();
      
      console.log("Ordre APRÈS:", canvas.getObjects().indexOf(activeObject));
      console.log("Nombre total d'objets:", canvas.getObjects().length);
      
      // Afficher tous les objets dans l'ordre
      console.log("Ordre complet des objets:");
      canvas.getObjects().forEach((obj, idx) => {
        if (obj === backgroundImage) {
          console.log(`  ${idx}: Image de fond`);
        } else if (obj === activeObject) {
          console.log(`  ${idx}: >>> OBJET SÉLECTIONNÉ <<<`);
        } else {
          console.log(`  ${idx}: Autre objet`);
        }
      });
      
      // Forcer un recalcul complet du canvas
      canvas.getObjects().forEach(obj => {
        obj.setCoords();
      });
      
      canvas.discardActiveObject();
      canvas.setActiveObject(activeObject);
      canvas.renderAll();
      
      // Double rendu pour forcer la mise à jour
      setTimeout(() => {
        canvas.renderAll();
      }, 10);
      
      console.log("✅ Objet mis au premier plan");
    };
    
    document.getElementById("sendBackward").onclick = () => {
      const activeObject = canvas.getActiveObject();
      if (!activeObject) {
        console.log("❌ Aucun objet sélectionné");
        return;
      }
      
      if (activeObject === backgroundImage) {
        console.log("❌ Impossible de déplacer l'image de fond");
        return;
      }
      
      console.log("Ordre AVANT:", canvas.getObjects().indexOf(activeObject));
      
      // Mettre complètement en arrière (mais devant l'image de fond)
      activeObject.sendToBack();
      
      // S'assurer que l'image de fond reste toujours la plus en arrière
      if (backgroundImage) {
        backgroundImage.sendToBack();
      }
      
      console.log("Ordre APRÈS:", canvas.getObjects().indexOf(activeObject));
      console.log("Nombre total d'objets:", canvas.getObjects().length);
      
      // Afficher tous les objets dans l'ordre
      console.log("Ordre complet des objets:");
      canvas.getObjects().forEach((obj, idx) => {
        if (obj === backgroundImage) {
          console.log(`  ${idx}: Image de fond`);
        } else if (obj === activeObject) {
          console.log(`  ${idx}: >>> OBJET SÉLECTIONNÉ <<<`);
        } else {
          console.log(`  ${idx}: Autre objet`);
        }
      });
      
      // Forcer un recalcul complet du canvas
      canvas.getObjects().forEach(obj => {
        obj.setCoords();
      });
      
      canvas.discardActiveObject();
      canvas.setActiveObject(activeObject);
      canvas.renderAll();
      
      // Double rendu pour forcer la mise à jour
      setTimeout(() => {
        canvas.renderAll();
      }, 10);
      
      console.log("✅ Objet mis en arrière-plan");
    };
  </script>
</body>
</html>

