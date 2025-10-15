// ========== GESTION DU CLAVIER ==========

document.addEventListener('keydown', (e) => {
  // Annuler le placement de papier avec Echap
  if (e.key === 'Escape' && isPlacingPaper) {
    cancelPaperPlacement();
    return;
  }
  
  // Echap pour annuler le tracé ou valider l'édition
  if (e.key === 'Escape' && isLassoMode && points.length > 0) {
    // En mode édition, valider les changements
    if (isEditingMode) {
      console.log("💾 Validation des modifications (Échap)...");
      if (points.length >= 3 && typeof createCutout !== 'undefined') {
        createCutout();
      } else {
        // Pas assez de points, annuler
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
        document.getElementById("toggleLasso").style.background = "#3a3a3a";
        document.getElementById("editMask").style.background = "#3a3a3a";
        canvas.selection = true;
        canvas.renderAll();
      }
    } else {
      // En mode traçage normal, annuler
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
      
      // Réinitialiser les modes et boutons
      isLassoMode = false;
      isEditingMode = false;
      document.getElementById("toggleLasso").style.background = "#3a3a3a";
      document.getElementById("editMask").style.background = "#3a3a3a";
      canvas.selection = true;
      canvas.renderAll();
    }
    
    // Arrêter l'auto-pan lors de l'annulation
    if (typeof stopAutoPan !== 'undefined') {
      stopAutoPan();
    }
    
    // Mettre à jour l'état des boutons
    if (typeof updateButtonStates !== 'undefined') {
      updateButtonStates();
    }
    
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

