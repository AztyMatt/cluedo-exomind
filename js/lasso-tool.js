// ========== OUTIL LASSO ==========

// Fonction pour définir la couleur de bordure d'un masque
function setMaskBorderColor(group, color) {
  if (!group || !group._objects || group._objects.length < 2) return;
  const border = group._objects[1];
  if (border && border.set) border.set({ stroke: color });
}

// Toggle du mode lasso
document.getElementById("toggleLasso").onclick = () => {
  if (isLassoMode && editingMask) {
    canvas.add(editingMask);
    editingMask = null;
  }
  
  isLassoMode = !isLassoMode;
  
  if (isLassoMode && isPanMode) {
    isPanMode = false;
    document.getElementById("togglePan").style.background = "#3a3a3a";
    canvas.defaultCursor = 'default';
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
  
  canvas.selection = !isLassoMode;
  canvas.discardActiveObject();
  canvas.renderAll();
  
  document.getElementById("toggleLasso").style.background = isLassoMode ? "#1a7f1a" : "#3a3a3a";
};

// Validation du masque
document.getElementById("validateMask").onclick = () => {
  if (points.length < 3) return;
  
  if (editingMask) {
    canvas.remove(editingMask);
    editingMask = null;
  }
  
  createCutout();
  
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

// Modification du tracé
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
  
  points = JSON.parse(JSON.stringify(activeObject.maskData.originalPoints));
  curveHandles = JSON.parse(JSON.stringify(activeObject.maskData.curveHandles));
  
  editingMask = activeObject;
  canvas.remove(editingMask);
  
  isLassoMode = true;
  polygonClosed = true;
  document.getElementById("toggleLasso").style.background = "#1a7f1a";
  
  if (isPanMode) {
    isPanMode = false;
    document.getElementById("togglePan").style.background = "#3a3a3a";
  }
  
  document.getElementById("validateMask").style.display = "flex";
  
  canvas.discardActiveObject();
  canvas.selection = false;
  canvas.renderAll();
  
  updatePolygonPreview();
  
  console.log("✅ Vous pouvez maintenant modifier les points et courbes. Cliquez sur 'Valider' quand c'est terminé.");
};

// Gestion des clics pour dessiner le lasso
canvas.on("mouse:down", (opt) => {
  if (!isLassoMode || opt.e.altKey) return;
  
  const pointer = canvas.getPointer(opt.e);
  
  if (points.length === 0) {
    points.push({ x: pointer.x, y: pointer.y });
    
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
  
  if (polygonClosed) {
    // Mode édition: glisser un point
    for (let i = 0; i < points.length; i++) {
      const p = points[i];
      const distToPoint = Math.sqrt(
        Math.pow(pointer.x - p.x, 2) + 
        Math.pow(pointer.y - p.y, 2)
      );
      
      if (distToPoint < 12) {
        isDraggingPoint = true;
        draggingPointIndex = i;
        console.log(`Glissement du point ${i}`);
        return;
      }
    }
    
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
      
      if (distToHandle < 10) {
        isDraggingHandle = true;
        draggingSegmentIndex = i;
        console.log(`Glissement de la poignée du segment ${i}`);
        return;
      }
    }
    return;
  }
  
  // Fermer le polygone si on clique près du premier point
  const firstPoint = points[0];
  const distToFirst = Math.sqrt(
    Math.pow(pointer.x - firstPoint.x, 2) + 
    Math.pow(pointer.y - firstPoint.y, 2)
  );
  
  if (distToFirst < 10 && points.length >= 3) {
    console.log("Polygone fermé. Cliquez sur les points ou lignes pour les arrondir. Puis cliquez sur 'Valider le masque'.");
    polygonClosed = true;
    
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
  
  if (previewLine) {
    canvas.remove(previewLine);
    previewLine = null;
  }
  
  document.getElementById("validateMask").style.display = "flex";
  updatePolygonPreview();
});

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
  
  const path = new fabric.Path(pathString, {
    fill: 'transparent',
    stroke: 'lime',
    strokeWidth: 2,
    selectable: false,
    evented: false
  });
  canvas.add(path);
  tempLines.push(path);
  
  // Dessiner les points
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

// Création du masque final
function createCutout() {
  tempLines.forEach(line => canvas.remove(line));
  tempLines = [];
  tempCircles.forEach(circle => canvas.remove(circle));
  tempCircles = [];
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
      selectable: false,
      evented: false,
      originX: 'left',
      originY: 'top'
    });
    
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
        originalPoints: JSON.parse(JSON.stringify(savedPoints)),
        curveHandles: JSON.parse(JSON.stringify(savedHandles)),
        isMask: true
      }
    });
    
    canvas.add(maskGroup);
    
    isLassoMode = false;
    document.getElementById("toggleLasso").style.background = "#3a3a3a";
    canvas.selection = true;
    canvas.setActiveObject(maskGroup);
    canvas.renderAll();
    
    console.log("✅ Masque créé !");
    saveCanvasState();
  });
  
  points = [];
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

