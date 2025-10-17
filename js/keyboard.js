// ========== GESTION DU CLAVIER ==========

document.addEventListener('keydown', (e) => {
  // Annuler le placement de papier avec Echap
  if (e.key === 'Escape' && isPlacingPaper) {
    cancelPaperPlacement();
    return;
  }
  
  // Annuler le placement de flèche avec Echap
  if (e.key === 'Escape' && isPlacingArrow) {
    cancelArrowPlacement();
    return;
  }
  
  // Rotation de la flèche pendant le placement avec ← et → ou R
  if (isPlacingArrow && (e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'r' || e.key === 'R')) {
    let rotation = 0;
    if (e.key === 'ArrowLeft') {
      rotation = -15;
    } else if (e.key === 'ArrowRight' || e.key === 'r' || e.key === 'R') {
      rotation = 15;
    }
    arrowPreviewAngle += rotation;
    updateArrowPlaceholderSize();
    console.log("↻ Rotation de la flèche:", arrowPreviewAngle + "°");
    e.preventDefault();
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
  
  // Touche C pour changer la destination d'une flèche
  if ((e.key === 'c' || e.key === 'C') && activeObject.isArrow && !isPlayerMode) {
    console.log('🎯 Ouverture de la modale pour changer la destination de la flèche');
    if (typeof showArrowTargetModal === 'function') {
      showArrowTargetModal('arrow', activeObject);
    }
    e.preventDefault();
    return;
  }
  
  // Touche M pour centrer une flèche au milieu de la ligne
  if ((e.key === 'm' || e.key === 'M') && activeObject.isArrow && !isPlayerMode) {
    if (typeof backgroundImage !== 'undefined' && backgroundImage) {
      const imgWidth = backgroundImage.width * backgroundImage.scaleX;
      const centerX = imgWidth / 2;
      activeObject.set({ left: centerX });
      canvas.renderAll();
      console.log('🎯 Flèche centrée au milieu de la ligne');
      
      // Déclencher une sauvegarde automatique
      if (typeof saveCanvasState === 'function') {
        saveCanvasState();
      }
    }
    e.preventDefault();
    return;
  }
  
  // Touche X pour activer/désactiver le déplacement libre d'une flèche
  if ((e.key === 'x' || e.key === 'X') && activeObject.isArrow && !isPlayerMode) {
    if (typeof arrowFreeMoveMode !== 'undefined') {
      arrowFreeMoveMode = !arrowFreeMoveMode;
      
      // Mettre à jour le lockMovementY et freePlacement en conséquence
      activeObject.set({ 
        lockMovementY: !arrowFreeMoveMode,
        freePlacement: arrowFreeMoveMode
      });
      
      if (arrowFreeMoveMode) {
        console.log('🔓 Mode déplacement libre ACTIVÉ pour la flèche (appuyez sur X pour désactiver)');
      } else {
        console.log('🔒 Mode déplacement libre DÉSACTIVÉ pour la flèche');
        
        // Ramener la flèche sur la ligne contrainte
        if (typeof backgroundImage !== 'undefined' && backgroundImage) {
          const imgHeight = backgroundImage.height * backgroundImage.scaleY;
          const ARROW_BOTTOM_OFFSET = 200;
          activeObject.set({ top: imgHeight - ARROW_BOTTOM_OFFSET });
          canvas.renderAll();
        }
      }
      
      // Déclencher une sauvegarde automatique
      if (typeof saveCanvasState === 'function') {
        saveCanvasState();
      }
    }
    e.preventDefault();
    return;
  }
  
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

