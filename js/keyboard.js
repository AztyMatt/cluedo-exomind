// ========== GESTION DU CLAVIER ==========

document.addEventListener('keydown', (e) => {
  // Annuler le placement de papier avec Echap
  if (e.key === 'Escape' && isPlacingPaper) {
    cancelPaperPlacement();
    return;
  }
  
  // Echap pour annuler le tracé
  if (e.key === 'Escape' && isLassoMode && points.length > 0) {
    console.log("Tracé annulé");
    
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
  
  // Flèches directionnelles pour déplacer
  const moveStep = e.shiftKey ? 10 : 1;
  
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

