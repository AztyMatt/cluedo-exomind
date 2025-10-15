// ========== OUTIL PAN (MODE MAIN) ==========

document.getElementById("togglePan").onclick = () => {
  isPanMode = !isPanMode;
  
  if (isPanMode && isLassoMode) {
    isLassoMode = false;
    document.getElementById("toggleLasso").style.background = "#3a3a3a";
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
  
  canvas.selection = true;
  canvas.discardActiveObject();
  canvas.defaultCursor = isPanMode ? 'grab' : 'default';
  canvas.hoverCursor = isPanMode ? 'grab' : 'move';
  canvas.renderAll();
  
  document.getElementById("togglePan").style.background = isPanMode ? "#1a7f1a" : "#3a3a3a";
};

// Gestion du pan/drag
canvas.on("mouse:down", (opt) => {
  if (isPlacingPaper) {
    finalizePaperPlacement(opt);
    return;
  }
  if ((opt.e.altKey || isPanMode) && !opt.target && !isPlayerMode) {
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
    canvas.requestRenderAll();
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

// Gestion de la sélection des masques
canvas.on("selection:created", (e) => {
  const selectedObj = e.selected[0];
  if (isPanMode && selectedObj && selectedObj.maskData && selectedObj.maskData.isMask) {
    isPanMode = false;
    document.getElementById("togglePan").style.background = "#3a3a3a";
    canvas.defaultCursor = 'default';
    canvas.hoverCursor = 'move';
    console.log("⚠️ Mode Main désactivé (masque sélectionné)");
  }
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
  if (isPanMode && selectedObj && selectedObj.maskData && selectedObj.maskData.isMask) {
    isPanMode = false;
    document.getElementById("togglePan").style.background = "#3a3a3a";
    canvas.defaultCursor = 'default';
    canvas.hoverCursor = 'move';
    console.log("⚠️ Mode Main désactivé (masque sélectionné)");
  }
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

