// ========== SAUVEGARDE / CHARGEMENT ==========

function saveCanvasState() {
  // La sauvegarde automatique est désactivée
  // Utiliser le bouton "💾 Sauvegarder" pour sauvegarder manuellement
}

function saveToServer() {
  const objectsToSave = [];
  canvas.getObjects().forEach(obj => {
    if (obj === backgroundImage) return;
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

