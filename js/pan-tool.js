// ========== GESTION DU PAN PAR CLIC-GLISSER ET ÉVÉNEMENTS DE SÉLECTION ==========

// Gestion du pan/drag
canvas.on("mouse:down", (opt) => {
  if (isPlacingPaper) {
    finalizePaperPlacement(opt);
    return;
  }
  
  if (isPlacingArrow) {
    finalizeArrowPlacement(opt);
    return;
  }
  
  // Pan avec Alt ou clic-glisser sur espace vide
  // En mode player, permet le pan uniquement sur l'espace vide (pas sur les objets)
  if (!opt.target && (isPlayerMode || (!isLassoMode || opt.e.altKey))) {
    isDragging = true;
    lastPosX = opt.e.clientX;
    lastPosY = opt.e.clientY;
    canvas.defaultCursor = 'grabbing';
  }
});

canvas.on("mouse:move", (opt) => {
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
    
    // Contraindre le viewport en mode player pour ne jamais voir en dehors de l'image
    if (isPlayerMode) {
      constrainViewportToImage();
    } else {
      canvas.requestRenderAll();
    }
    
    lastPosX = e.clientX;
    lastPosY = e.clientY;
  } else if (isDraggingPoint && draggingPointIndex >= 0) {
    points[draggingPointIndex] = {
      x: pointer.x,
      y: pointer.y
    };
    
    const prevIdx = (draggingPointIndex - 1 + points.length) % points.length;
    const nextIdx = draggingPointIndex;
    
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
    curveHandles[draggingSegmentIndex] = {
      x: pointer.x,
      y: pointer.y
    };
    updatePolygonPreview();
  } else if (isLassoMode && points.length > 0 && !polygonClosed) {
    const lastPoint = points[points.length - 1];
    
    if (previewLine) {
      canvas.remove(previewLine);
    }
    
    previewLine = new fabric.Line(
      [lastPoint.x, lastPoint.y, pointer.x, pointer.y],
      {
        stroke: '#ffff00',
        strokeWidth: 2,
        strokeDashArray: [8, 4],
        selectable: false,
        evented: false
      }
    );
    canvas.add(previewLine);
    canvas.renderAll();
  }
});

canvas.on("mouse:up", () => {
  // Arrêter l'auto-pan si on était en train de glisser un point ou une poignée
  if (isDraggingPoint || isDraggingHandle) {
    if (typeof stopAutoPan !== 'undefined') {
      stopAutoPan();
    }
  }
  
  isDragging = false;
  isDraggingHandle = false;
  draggingSegmentIndex = -1;
  isDraggingPoint = false;
  draggingPointIndex = -1;
  canvas.defaultCursor = isPlayerMode ? 'grab' : 'default';
});

// Gestion de la sélection des masques
canvas.on("selection:created", (e) => {
  const selectedObj = e.selected[0];
  if (selectedObj && selectedObj.maskData && selectedObj.maskData.isMask) {
    if (lastSelectedMask && lastSelectedMask !== selectedObj) {
      setMaskBorderColor(lastSelectedMask, '#00ff00');
    }
    setMaskBorderColor(selectedObj, '#ff00ff');
    lastSelectedMask = selectedObj;
    canvas.requestRenderAll();
  } else if (lastSelectedMask) {
    setMaskBorderColor(lastSelectedMask, '#00ff00');
    lastSelectedMask = null;
    canvas.requestRenderAll();
  }
});

canvas.on("selection:updated", (e) => {
  const selectedObj = e.selected[0];
  if (selectedObj && selectedObj.maskData && selectedObj.maskData.isMask) {
    if (lastSelectedMask && lastSelectedMask !== selectedObj) {
      setMaskBorderColor(lastSelectedMask, '#00ff00');
    }
    setMaskBorderColor(selectedObj, '#ff00ff');
    lastSelectedMask = selectedObj;
    canvas.requestRenderAll();
  } else if (lastSelectedMask) {
    setMaskBorderColor(lastSelectedMask, '#00ff00');
    lastSelectedMask = null;
    canvas.requestRenderAll();
  }
});

canvas.on("selection:cleared", () => {
  if (lastSelectedMask) {
    setMaskBorderColor(lastSelectedMask, '#00ff00');
    lastSelectedMask = null;
    canvas.requestRenderAll();
  }
});

