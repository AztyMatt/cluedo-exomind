// ========== SAUVEGARDE / CHARGEMENT ==========

function saveCanvasState() {
  // La sauvegarde automatique est désactivée
  // Utiliser le bouton "💾 Sauvegarder" pour sauvegarder manuellement
}

function saveToServer() {
  const objectsToSave = [];
  let zIndex = 0; // Compteur pour le z-index basé sur l'ordre du canvas
  
  canvas.getObjects().forEach(obj => {
    if (obj === backgroundImage) return;
    if (obj.maskData && obj.maskData.isMask) {
      // Stocker le z-index dans l'objet pour la prochaine sauvegarde
      obj.maskData.zIndex = zIndex;
      objectsToSave.push({
        type: 'mask',
        id: obj.maskData.dbId || null, // Garder l'ID de la BDD
        originalPoints: obj.maskData.originalPoints,
        curveHandles: obj.maskData.curveHandles,
        left: obj.left,
        top: obj.top,
        zIndex: zIndex++
      });
    } else if (obj._objects && obj._objects.length >= 2) {
      // Stocker le z-index dans l'objet pour la prochaine sauvegarde
      obj.zIndex = zIndex;
      objectsToSave.push({
        type: 'paper',
        id: obj.dbId || null, // Garder l'ID de la BDD
        left: obj.left,
        top: obj.top,
        scaleX: obj.scaleX,
        scaleY: obj.scaleY,
        angle: obj.angle,
        zIndex: zIndex++
      });
    }
  });
  
  // Inclure le mask en cours d'édition s'il existe
  if (typeof editingMask !== 'undefined' && editingMask && editingMask.maskData && editingMask.maskData.isMask) {
    // Sauvegarder les modifications EN COURS (points et curveHandles actuels)
    const currentPoints = (typeof points !== 'undefined' && points && points.length > 0) ? points : editingMask.maskData.originalPoints;
    const currentHandles = (typeof curveHandles !== 'undefined' && curveHandles) ? curveHandles : editingMask.maskData.curveHandles;
    
    // Trouver le z-index du mask en édition (basé sur sa position originale avant édition)
    const editingZIndex = editingMask.maskData.zIndex !== undefined ? editingMask.maskData.zIndex : zIndex;
    // Mettre à jour le z-index dans l'objet
    editingMask.maskData.zIndex = editingZIndex;
    
    objectsToSave.push({
      type: 'mask',
      id: editingMask.maskData.dbId || null,
      originalPoints: currentPoints,
      curveHandles: currentHandles,
      left: editingMask.left,
      top: editingMask.top,
      zIndex: editingZIndex
    });
    console.log('💾 Mask en cours d\'édition sauvegardé avec modifications (ID:', editingMask.maskData.dbId || 'nouveau', ',', currentPoints.length, 'points, z-index:', editingZIndex + ')');
  }

  const dataToSave = JSON.stringify(objectsToSave, null, 2);
  
  // Log détaillé pour vérifier les z-index
  console.log('📊 Objets à sauvegarder avec z-index:');
  objectsToSave.forEach((obj, idx) => {
    console.log(`  [${idx}] ${obj.type} - ID: ${obj.id || 'nouveau'}, z-index: ${obj.zIndex}`);
  });

  fetch(window.location.href, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=save&key=' + encodeURIComponent(currentBackgroundKey) + '&data=' + encodeURIComponent(dataToSave)
  })
  .then(response => response.json())
  .then(result => {
    if (result.success) {
      console.log('✅ Sauvegardé pour', currentBackgroundKey, '(', objectsToSave.length, 'objets)');
      
      // Mettre à jour les IDs et z-index dans les objets du canvas
      if (result.ids) {
        let canvasIndex = 0;
        canvas.getObjects().forEach(obj => {
          if (obj === backgroundImage) return;
          
          const idInfo = result.ids[canvasIndex];
          if (idInfo) {
            if (idInfo.type === 'mask' && obj.maskData && obj.maskData.isMask) {
              obj.maskData.dbId = idInfo.id;
              // Le z-index a déjà été mis à jour avant la sauvegarde
              console.log('🔄 Mask mis à jour - ID:', idInfo.id, 'z-index:', obj.maskData.zIndex);
            } else if (idInfo.type === 'paper' && obj._objects && obj._objects.length >= 2) {
              obj.dbId = idInfo.id;
              // Le z-index a déjà été mis à jour avant la sauvegarde
              console.log('🔄 Paper mis à jour - ID:', idInfo.id, 'z-index:', obj.zIndex);
            }
          }
          canvasIndex++;
        });
      }
      
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
  canvas.getObjects().slice().forEach(o => { if (o !== backgroundImage) canvas.remove(o); });
  fetch(window.location.href, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=load&key=' + encodeURIComponent(currentBackgroundKey)
  })
  .then(response => response.json())
  .then(result => {
    const dataStr = result && result.success ? result.data : null;
    const source = result && result.source ? result.source : 'unknown';
    
    if (!dataStr) {
      console.log('ℹ️ Rien à charger pour', currentBackgroundKey);
      return;
    }
    let savedObjects = [];
    try { savedObjects = JSON.parse(dataStr) || []; } catch(e) { savedObjects = []; }
    if (!Array.isArray(savedObjects) || savedObjects.length === 0) {
      console.log('ℹ️ Aucune entrée pour', currentBackgroundKey, '(source:', source + ')');
      canvas.renderAll();
      return;
    }
    
    // Afficher la source des données
    const sourceEmoji = source === 'database' ? '🗄️' : (source === 'json' ? '📄' : '❓');
    console.log(`📂 ${sourceEmoji} Chargement de ${savedObjects.length} objets pour ${currentBackgroundKey} depuis ${source.toUpperCase()}`);
    
    let loaded = 0;
    savedObjects.forEach(objData => {
      if (objData.type === 'mask') {
        recreateMask(objData, () => { 
          loaded++; 
          if (loaded === savedObjects.length) console.log('✅ Tous les objets chargés !'); 
        });
      } else if (objData.type === 'paper') {
        recreatePaper(objData, () => { 
          loaded++; 
          if (loaded === savedObjects.length) console.log('✅ Tous les objets chargés !'); 
        });
      }
    });
  })
  .catch(error => {
    console.error('❌ Erreur de chargement:', error);
  });
}

// Bouton de sauvegarde
document.getElementById("saveData").onclick = () => {
  saveToServer();
};

// Nettoyer l'ancien cache localStorage
localStorage.removeItem("fabricCanvas");

