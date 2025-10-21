// ========== OUTIL LASSO ==========

// Variables pour l'auto-pan
let autoPanAnimationId = null;
let lastAutoPanMousePos = { x: 0, y: 0 };

// Configuration de l'auto-pan
const AUTO_PAN_EDGE_SIZE = 50; // Taille de la zone de déclenchement en pixels depuis le bord
const AUTO_PAN_SPEED = 8; // Vitesse maximale du pan

// Fonction pour obtenir la taille des points en fonction du zoom
function getPointSize() {
  const zoom = canvas.getZoom();
  return 4 / zoom; // Taille de base 4px divisée par le zoom pour garder une taille constante à l'écran
}

function getEditPointSize() {
  const zoom = canvas.getZoom();
  return 8 / zoom; // Taille de base 8px pour les points en mode édition
}

function getHandleSize() {
  const zoom = canvas.getZoom();
  return 6 / zoom; // Taille de base 6px pour les poignées
}

function getStrokeWidth() {
  const zoom = canvas.getZoom();
  return 2 / zoom; // Largeur de trait de base 2px
}

// Fonction d'auto-pan
function checkAndApplyAutoPan() {
  // Arrêter l'auto-pan si on n'est plus en mode lasso ou si on n'a pas encore commencé à tracer
  if (!isLassoMode || (points.length === 0 && !polygonClosed)) {
    if (autoPanAnimationId) {
      cancelAnimationFrame(autoPanAnimationId);
      autoPanAnimationId = null;
    }
    return;
  }

  const mouseX = lastAutoPanMousePos.x;
  const mouseY = lastAutoPanMousePos.y;
  const canvasWidth = window.innerWidth;
  const canvasHeight = window.innerHeight;

  let panX = 0;
  let panY = 0;

  // Calculer le déplacement en fonction de la proximité des bords
  if (mouseX < AUTO_PAN_EDGE_SIZE) {
    // Bord gauche
    panX = AUTO_PAN_SPEED * (1 - mouseX / AUTO_PAN_EDGE_SIZE);
  } else if (mouseX > canvasWidth - AUTO_PAN_EDGE_SIZE) {
    // Bord droit
    panX = -AUTO_PAN_SPEED * (1 - (canvasWidth - mouseX) / AUTO_PAN_EDGE_SIZE);
  }

  if (mouseY < AUTO_PAN_EDGE_SIZE) {
    // Bord haut
    panY = AUTO_PAN_SPEED * (1 - mouseY / AUTO_PAN_EDGE_SIZE);
  } else if (mouseY > canvasHeight - AUTO_PAN_EDGE_SIZE) {
    // Bord bas
    panY = -AUTO_PAN_SPEED * (1 - (canvasHeight - mouseY) / AUTO_PAN_EDGE_SIZE);
  }

  // Appliquer le pan si nécessaire
  if (panX !== 0 || panY !== 0) {
    const vpt = canvas.viewportTransform;
    vpt[4] += panX;
    vpt[5] += panY;
    canvas.requestRenderAll();
    isAtBaseZoom = false;
  }

  // Continuer l'animation
  autoPanAnimationId = requestAnimationFrame(checkAndApplyAutoPan);
}

// Démarrer l'auto-pan
function startAutoPan() {
  if (!autoPanAnimationId) {
    autoPanAnimationId = requestAnimationFrame(checkAndApplyAutoPan);
  }
}

// Arrêter l'auto-pan
function stopAutoPan() {
  if (autoPanAnimationId) {
    cancelAnimationFrame(autoPanAnimationId);
    autoPanAnimationId = null;
  }
}

// Mettre à jour la position de la souris pour l'auto-pan
window.addEventListener('mousemove', (e) => {
  lastAutoPanMousePos.x = e.clientX;
  lastAutoPanMousePos.y = e.clientY;
});

// Fonction pour définir la couleur de bordure d'un masque
function setMaskBorderColor(group, color) {
  if (!group || !group._objects || group._objects.length < 2) return;
  const border = group._objects[1];
  if (border && border.set) border.set({ stroke: color });
}

// Toggle du mode lasso
document.getElementById("toggleLasso").onclick = function() {
  // Vérifier si le bouton est désactivé
  if (this.classList.contains('disabled')) return;
  
  // Si on désactive le lasso en mode édition, valider les changements
  if (isLassoMode && isEditingMode && points.length >= 3) {
    console.log("💾 Validation des modifications (désactivation du lasso)...");
    createCutout();
    return;
  }
  
  if (isLassoMode && editingMask) {
    canvas.add(editingMask);
    editingMask = null;
  }
  
  isLassoMode = !isLassoMode;
  isEditingMode = false; // Réinitialiser le mode édition
  
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
  
  canvas.selection = !isLassoMode;
  canvas.discardActiveObject();
  canvas.renderAll();
  
  document.getElementById("toggleLasso").style.background = isLassoMode ? "#1a7f1a" : "#3a3a3a";
  document.getElementById("editMask").style.background = "#3a3a3a"; // Réinitialiser le bouton édition
  
  // Arrêter l'auto-pan si on désactive le mode lasso
  if (!isLassoMode) {
    stopAutoPan();
  }
  
  // Mettre à jour l'état des boutons
  if (typeof updateButtonStates !== 'undefined') {
    updateButtonStates();
  }
};

// Modification du tracé
document.getElementById("editMask").onclick = function() {
  // Vérifier si le bouton est désactivé (sauf en mode édition où il sert à valider)
  if (!isEditingMode && this.classList.contains('disabled')) return;
  
  // Si on est déjà en mode édition, sortir du mode et valider les changements
  if (isEditingMode) {
    console.log("💾 Finalisation du mode édition...");
    if (points.length >= 3) {
      createCutout();
    } else {
      // Annuler si pas assez de points
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
      
      isLassoMode = false;
      isEditingMode = false;
      document.getElementById("editMask").style.background = "#3a3a3a";
      canvas.selection = true;
      canvas.renderAll();
      
      // Mettre à jour l'état des boutons
      if (typeof updateButtonStates !== 'undefined') {
        updateButtonStates();
      }
    }
    return;
  }
  
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
  
  points = JSON.parse(JSON.stringify(activeObject.maskData.originalPoints));
  curveHandles = JSON.parse(JSON.stringify(activeObject.maskData.curveHandles));
  
  editingMask = activeObject;
  canvas.remove(editingMask);
  
  isLassoMode = true;
  isEditingMode = true; // Activer le mode édition
  polygonClosed = true;
  
  // Mettre le bouton "Modifier le tracé" en vert au lieu du lasso
  document.getElementById("editMask").style.background = "#1a7f1a";
  
  canvas.discardActiveObject();
  canvas.selection = false;
  canvas.renderAll();
  
  updatePolygonPreview();
  
  // Mettre à jour l'état des boutons
  if (typeof updateButtonStates !== 'undefined') {
    updateButtonStates();
  }
  
  console.log("✅ Vous pouvez maintenant modifier les points et courbes. Cliquez à nouveau sur 'Modifier le tracé' pour finaliser.");
};

// Gestion des clics pour dessiner le lasso
canvas.on("mouse:down", (opt) => {
  if (!isLassoMode || opt.e.altKey) return;
  
  const pointer = canvas.getPointer(opt.e);
  
  if (points.length === 0) {
    points.push({ x: pointer.x, y: pointer.y });
    
    const pointSize = getPointSize();
    const circle = new fabric.Circle({
      left: pointer.x - pointSize,
      top: pointer.y - pointSize,
      radius: pointSize,
      fill: '#00ff00',
      selectable: false,
      evented: false
    });
    canvas.add(circle);
    tempCircles.push(circle);
    
    // Démarrer l'auto-pan maintenant qu'on a commencé à tracer
    startAutoPan();
    
    console.log("Premier point placé");
    return;
  }
  
  if (polygonClosed) {
    const clickTolerance = 12 / canvas.getZoom(); // Zone de détection adaptée au zoom
    
    // Mode édition: glisser un point
    for (let i = 0; i < points.length; i++) {
      const p = points[i];
      const distToPoint = Math.sqrt(
        Math.pow(pointer.x - p.x, 2) + 
        Math.pow(pointer.y - p.y, 2)
      );
      
      if (distToPoint < clickTolerance) {
        isDraggingPoint = true;
        draggingPointIndex = i;
        // Démarrer l'auto-pan quand on commence à glisser un point
        startAutoPan();
        console.log(`Glissement du point ${i}`);
        return;
      }
    }
    
    const handleTolerance = 10 / canvas.getZoom(); // Zone de détection pour les poignées
    
    // Glisser une poignée de courbe
    for (let i = 0; i < points.length; i++) {
      const p1 = points[i];
      const p2 = points[(i + 1) % points.length];
      
      const handlePos = curveHandles[i] || {
        x: (p1.x + p2.x) / 2,
        y: (p1.y + p2.y) / 2
      };
      
      const distToHandle = Math.sqrt(
        Math.pow(pointer.x - handlePos.x, 2) + 
        Math.pow(pointer.y - handlePos.y, 2)
      );
      
      if (distToHandle < handleTolerance) {
        isDraggingHandle = true;
        draggingSegmentIndex = i;
        // Démarrer l'auto-pan quand on commence à glisser une poignée
        startAutoPan();
        console.log(`Glissement de la poignée du segment ${i}`);
        return;
      }
    }
    return;
  }
  
  // Fermer le polygone si on clique près du premier point
  const firstPoint = points[0];
  const closeTolerance = 10 / canvas.getZoom(); // Zone de détection pour fermer le polygone
  const distToFirst = Math.sqrt(
    Math.pow(pointer.x - firstPoint.x, 2) + 
    Math.pow(pointer.y - firstPoint.y, 2)
  );
  
  if (distToFirst < closeTolerance && points.length >= 3) {
    console.log("Polygone fermé. Création du masque...");
    
    if (previewLine) {
      canvas.remove(previewLine);
      previewLine = null;
    }
    
    // Créer le masque automatiquement
    createCutout();
    return;
  }
  
  // Ajouter un nouveau point
  const lastPoint = points[points.length - 1];
  
  const strokeWidth = getStrokeWidth();
  const line = new fabric.Line(
    [lastPoint.x, lastPoint.y, pointer.x, pointer.y],
    {
      stroke: '#00ff00',
      strokeWidth: strokeWidth,
      selectable: false,
      evented: false
    }
  );
  canvas.add(line);
  tempLines.push(line);
  
  const pointSize = getPointSize();
  const circle = new fabric.Circle({
    left: pointer.x - pointSize,
    top: pointer.y - pointSize,
    radius: pointSize,
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
  console.log("Polygone fermé. Création du masque...");
  
  if (previewLine) {
    canvas.remove(previewLine);
    previewLine = null;
  }
  
  // Créer le masque automatiquement
  createCutout();
});

// Fonction pour redessiner les points temporaires en mode tracé (polygone non fermé)
function redrawTemporaryPoints() {
  // Supprimer les cercles existants
  tempCircles.forEach(circle => canvas.remove(circle));
  tempCircles = [];
  
  const pointSize = getPointSize();
  
  // Redessiner les points avec la bonne taille
  points.forEach((point, idx) => {
    const circle = new fabric.Circle({
      left: point.x - pointSize,
      top: point.y - pointSize,
      radius: pointSize,
      fill: idx === 0 ? '#00ff00' : 'lime',
      selectable: false,
      evented: false
    });
    canvas.add(circle);
    tempCircles.push(circle);
  });
  
  // Redessiner les lignes avec la bonne épaisseur
  tempLines.forEach(line => canvas.remove(line));
  tempLines = [];
  
  const strokeWidth = getStrokeWidth();
  for (let i = 0; i < points.length - 1; i++) {
    const p1 = points[i];
    const p2 = points[i + 1];
    const line = new fabric.Line(
      [p1.x, p1.y, p2.x, p2.y],
      {
        stroke: '#00ff00',
        strokeWidth: strokeWidth,
        selectable: false,
        evented: false
      }
    );
    canvas.add(line);
    tempLines.push(line);
  }
  
  canvas.renderAll();
}

// Mise à jour de la prévisualisation du polygone
function updatePolygonPreview() {
  tempLines.forEach(line => canvas.remove(line));
  tempLines = [];
  tempCircles.forEach(circle => canvas.remove(circle));
  tempCircles = [];
  handleCircles.forEach(handle => canvas.remove(handle));
  handleCircles = [];
  
  let pathString = '';
  
  for (let i = 0; i < points.length; i++) {
    const p1 = points[i];
    const p2 = points[(i + 1) % points.length];
    
    if (i === 0) {
      pathString = `M ${p1.x} ${p1.y}`;
    }
    
    const handle = curveHandles[i];
    
    if (handle) {
      pathString += ` Q ${handle.x} ${handle.y}, ${p2.x} ${p2.y}`;
    } else {
      pathString += ` L ${p2.x} ${p2.y}`;
    }
  }
  
  pathString += ' Z';
  
  const strokeWidth = getStrokeWidth();
  const path = new fabric.Path(pathString, {
    fill: 'transparent',
    stroke: 'lime',
    strokeWidth: strokeWidth,
    selectable: false,
    evented: false
  });
  canvas.add(path);
  tempLines.push(path);
  
  // Dessiner les points et poignées uniquement en mode édition
  if (isEditingMode) {
    const editPointSize = getEditPointSize();
    const pointStrokeWidth = 3 / canvas.getZoom();
    
    // Dessiner les points
    points.forEach((point, idx) => {
      const circle = new fabric.Circle({
        left: point.x - editPointSize,
        top: point.y - editPointSize,
        radius: editPointSize,
        fill: '#00ff00',
        stroke: 'white',
        strokeWidth: pointStrokeWidth,
        selectable: false,
        evented: false
      });
      canvas.add(circle);
      tempCircles.push(circle);
    });
    
    const handleSize = getHandleSize();
    const handleStrokeWidth = 2 / canvas.getZoom();
    
    // Dessiner les poignées de courbe
    for (let i = 0; i < points.length; i++) {
      const p1 = points[i];
      const p2 = points[(i + 1) % points.length];
      
      const handlePos = curveHandles[i] || {
        x: (p1.x + p2.x) / 2,
        y: (p1.y + p2.y) / 2
      };
      
      const isCurved = curveHandles[i] !== undefined;
      
      const handle = new fabric.Rect({
        left: handlePos.x - handleSize,
        top: handlePos.y - handleSize,
        width: handleSize * 2,
        height: handleSize * 2,
        fill: isCurved ? '#00ffff' : 'rgba(0, 255, 255, 0.4)',
        stroke: '#00ffff',
        strokeWidth: handleStrokeWidth,
        selectable: false,
        evented: false
      });
      canvas.add(handle);
      handleCircles.push(handle);
      
      if (isCurved) {
        const guideLines = [
          new fabric.Line([p1.x, p1.y, handlePos.x, handlePos.y], {
            stroke: 'rgba(0, 255, 255, 0.8)',
            strokeWidth: strokeWidth,
            strokeDashArray: [6 / canvas.getZoom(), 4 / canvas.getZoom()],
            selectable: false,
            evented: false
          }),
          new fabric.Line([p2.x, p2.y, handlePos.x, handlePos.y], {
            stroke: 'rgba(0, 255, 255, 0.8)',
            strokeWidth: strokeWidth,
            strokeDashArray: [6 / canvas.getZoom(), 4 / canvas.getZoom()],
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
  }
  
  canvas.renderAll();
}

// Création du masque final
function createCutout() {
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
  
  const savedPoints = [...points];
  const savedHandles = { ...curveHandles};
  
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
  
  const savedFinalPoints = finalPoints.length > 0 ? finalPoints : savedPoints;
  console.log("✅ Courbes appliquées:", savedPoints.length, "points originaux →", savedFinalPoints.length, "points avec courbes");
  
  const clipPolygon = new fabric.Polygon(savedFinalPoints, {
    fill: "transparent",
    stroke: "transparent"
  });
  const bounds = clipPolygon.getBoundingRect();
  
  console.log("Création de l'image découpée...", savedFinalPoints.length, "points");
  
  const tempCanvas = document.createElement('canvas');
  const ctx = tempCanvas.getContext('2d');
  
  tempCanvas.width = bounds.width;
  tempCanvas.height = bounds.height;
  
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
      selectable: true,
      evented: true,
      originX: 'left',
      originY: 'top'
    });
    
    const borderPolygon = new fabric.Polygon(
      savedFinalPoints.map(p => ({ x: p.x - bounds.left, y: p.y - bounds.top })),
      {
        left: bounds.left,
        top: bounds.top,
        fill: 'transparent',
        stroke: '#00ff00',
        strokeWidth: 3,
        strokeDashArray: [8, 4],
        selectable: false,
        evented: false,
        originX: 'left',
        originY: 'top',
        visible: !isPlayerMode
      }
    );
    
    // Conserver l'ID et le z-index du mask édité s'il existe
    const existingDbId = (editingMask && editingMask.maskData && editingMask.maskData.dbId) ? editingMask.maskData.dbId : null;
    const existingZIndex = (editingMask && editingMask.maskData && editingMask.maskData.zIndex !== undefined) ? editingMask.maskData.zIndex : null;
    
    // Si c'est un nouveau mask, calculer le z-index
    let newZIndex = existingZIndex;
    if (newZIndex === null) {
      // Compter le nombre d'objets (hors background) pour déterminer le z-index
      newZIndex = 0;
      canvas.getObjects().forEach(obj => {
        if (obj !== backgroundImage) newZIndex++;
      });
    }
    
    const maskGroup = new fabric.Group([cutoutImg, borderPolygon], {
      left: bounds.left,
      top: bounds.top,
      selectable: true,
      evented: true, // Activer pour bloquer les clics sur les papiers en dessous
      lockMovementX: true,
      lockMovementY: true,
      lockRotation: true,
      lockScalingX: true,
      lockScalingY: true,
      hasControls: false,
      hasBorders: false,
      subTargetCheck: true, // Activer la détection des sous-objets
      perPixelTargetFind: true, // Détection pixel par pixel pour l'image
      targetFindTolerance: 0, // Pas de tolérance - clic exact requis
      maskData: {
        originalPoints: JSON.parse(JSON.stringify(savedPoints)),
        curveHandles: JSON.parse(JSON.stringify(savedHandles)),
        isMask: true,
        dbId: existingDbId, // Conserver l'ID de la BDD
        zIndex: newZIndex // Conserver ou attribuer le z-index
      }
    });
    
    canvas.add(maskGroup);
    
    isLassoMode = false;
    isEditingMode = false; // Réinitialiser le mode édition
    document.getElementById("toggleLasso").style.background = "#3a3a3a";
    document.getElementById("editMask").style.background = "#3a3a3a"; // Réinitialiser le bouton édition
    canvas.selection = true;
    canvas.setActiveObject(maskGroup);
    canvas.renderAll();
    
    // Arrêter l'auto-pan après création du masque
    stopAutoPan();
    
    // Mettre à jour l'état des boutons
    if (typeof updateButtonStates !== 'undefined') {
      updateButtonStates();
    }
    
    console.log("✅ Masque créé !");
    saveCanvasState();
  });
  
  // Nettoyer toutes les variables de tracé
  points = [];
  curveHandles = {};
  polygonClosed = false;
  editingMask = null;
}

// Fonction pour recréer un masque (utilisée au chargement)
function recreateMask(maskData, callback) {
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
  const clipPolygon = new fabric.Polygon(points, {
    fill: "transparent",
    stroke: "transparent"
  });
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
      selectable: true,
      evented: true,
      originX: 'left',
      originY: 'top'
    });
    
    const borderPolygon = new fabric.Polygon(
      points.map(p => ({ x: p.x - bounds.left, y: p.y - bounds.top })),
      {
        left: bounds.left,
        top: bounds.top,
        fill: 'transparent',
        stroke: '#00ff00',
        strokeWidth: 3,
        strokeDashArray: [8, 4],
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
      evented: true, // Activer pour bloquer les clics sur les papiers en dessous
      lockMovementX: true,
      lockMovementY: true,
      lockRotation: true,
      lockScalingX: true,
      lockScalingY: true,
      hasControls: false,
      hasBorders: false,
      subTargetCheck: true, // Activer la détection des sous-objets
      perPixelTargetFind: true, // Détection pixel par pixel pour l'image
      targetFindTolerance: 0, // Pas de tolérance - clic exact requis
      maskData: {
        originalPoints: savedPoints,
        curveHandles: savedHandles,
        isMask: true,
        dbId: maskData.id || null, // Conserver l'ID de la BDD
        zIndex: maskData.zIndex || 0 // Conserver le z-index de la BDD
      }
    });
    
    canvas.add(maskGroup);
    canvas.renderAll();
    if (callback) callback();
  });
}

