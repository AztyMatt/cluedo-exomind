// ========== SAUVEGARDE / CHARGEMENT ==========

// Variables pour la sauvegarde automatique (globales pour être accessibles depuis d'autres fichiers)
window.autoSaveTimeout = null;
const AUTO_SAVE_DELAY = 1000; // 1 seconde de délai après la dernière modification
let isLoadingFromServer = false; // Flag pour désactiver la sauvegarde pendant le chargement

// Fonction pour afficher l'indicateur de sauvegarde
function showAutoSaveIndicator() {
  const indicator = document.getElementById('autoSaveIndicator');
  if (indicator) {
    indicator.style.display = 'block';
    indicator.style.opacity = '1';
  }
}

// Fonction pour masquer l'indicateur de sauvegarde
function hideAutoSaveIndicator() {
  const indicator = document.getElementById('autoSaveIndicator');
  if (indicator) {
    indicator.style.opacity = '0';
    setTimeout(() => {
      indicator.style.display = 'none';
    }, 300);
  }
}

function triggerAutoSave() {
  // Ne pas sauvegarder si on est en train de charger depuis le serveur
  if (isLoadingFromServer) {
    return;
  }
  
  // Annuler le timer précédent s'il existe
  if (window.autoSaveTimeout) {
    clearTimeout(window.autoSaveTimeout);
  }
  
  // Programmer une nouvelle sauvegarde
  window.autoSaveTimeout = setTimeout(() => {
    console.log('💾 Sauvegarde automatique...');
    showAutoSaveIndicator();
    saveToServer(true); // true = mode silencieux (pas d'alert)
  }, AUTO_SAVE_DELAY);
}

function saveCanvasState() {
  // Déclencher la sauvegarde automatique
  triggerAutoSave();
}

function saveToServer(silent = false) {
  console.log('💾 saveToServer appelé avec currentBackgroundKey:', currentBackgroundKey);
  
  // IMPORTANT: Ne pas sauvegarder si on est en train de charger depuis le serveur
  // Cela évite de sauvegarder avec le mauvais currentBackgroundKey pendant un changement de photo
  if (isLoadingFromServer) {
    console.log('⚠️ Sauvegarde annulée car chargement en cours');
    return;
  }
  
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
    } else if (obj.isArrow) {
      // Flèche - toujours avec z-index 1000
      obj.zIndex = 1000;
      objectsToSave.push({
        type: 'arrow',
        id: obj.dbId || null, // Garder l'ID de la BDD
        left: obj.left,
        top: obj.top,
        angle: obj.angle,
        targetPhotoName: obj.targetPhotoName || null,
        freePlacement: obj.freePlacement || false, // Indique si la flèche est en mode libre
        zIndex: 1000 // Z-index fixe pour toutes les flèches
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
            } else if (idInfo.type === 'arrow' && obj.isArrow) {
              obj.dbId = idInfo.id;
              // Le z-index a déjà été mis à jour avant la sauvegarde
              console.log('🔄 Arrow mis à jour - ID:', idInfo.id, 'z-index:', obj.zIndex);
            } else if (idInfo.type === 'paper' && obj._objects && obj._objects.length >= 2) {
              obj.dbId = idInfo.id;
              // Le z-index a déjà été mis à jour avant la sauvegarde
              console.log('🔄 Paper mis à jour - ID:', idInfo.id, 'z-index:', obj.zIndex);
            }
          }
          canvasIndex++;
        });
      }
      
      // Masquer l'indicateur de sauvegarde automatique après succès
      if (silent) {
        setTimeout(() => hideAutoSaveIndicator(), 1000);
      }
      
      // N'afficher l'alert que si ce n'est pas une sauvegarde automatique silencieuse
      if (!silent) {
        alert('✅ Données sauvegardées avec succès !');
      }
    } else {
      console.error('❌ Erreur de sauvegarde');
      hideAutoSaveIndicator();
      if (!silent) {
        alert('❌ Erreur de sauvegarde');
      }
    }
  })
  .catch(error => {
    console.error('❌ Erreur:', error);
    hideAutoSaveIndicator();
    if (!silent) {
      alert('❌ Erreur lors de la sauvegarde');
    }
  });
}

function loadFromServer() {
  console.log('📂 loadFromServer appelé avec currentBackgroundKey:', currentBackgroundKey);
  
  // Annuler toute sauvegarde automatique en attente
  if (window.autoSaveTimeout) {
    clearTimeout(window.autoSaveTimeout);
    window.autoSaveTimeout = null;
    console.log('⏹️ Timer de sauvegarde automatique annulé (loadFromServer)');
  }
  
  // Activer le flag pour désactiver la sauvegarde automatique pendant le chargement
  isLoadingFromServer = true;
  
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
      // Réactiver la sauvegarde automatique avec un délai
      setTimeout(() => {
        isLoadingFromServer = false;
        console.log('🔓 Sauvegarde automatique réactivée (pas de données)');
      }, 100);
      return;
    }
    let savedObjects = [];
    try { savedObjects = JSON.parse(dataStr) || []; } catch(e) { savedObjects = []; }
    if (!Array.isArray(savedObjects) || savedObjects.length === 0) {
      console.log('ℹ️ Aucune entrée pour', currentBackgroundKey, '(source:', source + ')');
      canvas.renderAll();
      // Réactiver la sauvegarde automatique avec un délai
      setTimeout(() => {
        isLoadingFromServer = false;
        console.log('🔓 Sauvegarde automatique réactivée (tableau vide)');
      }, 100);
      return;
    }
    
    // Afficher la source des données
    const sourceEmoji = source === 'database' ? '🗄️' : (source === 'json' ? '📄' : '❓');
    console.log(`📂 ${sourceEmoji} Chargement de ${savedObjects.length} objets pour ${currentBackgroundKey} depuis ${source.toUpperCase()}`);
    
    let loaded = 0;
    const totalObjects = savedObjects.length;
    
    savedObjects.forEach(objData => {
      if (objData.type === 'mask') {
        recreateMask(objData, () => { 
          loaded++; 
          if (loaded === totalObjects) {
            console.log('✅ Tous les objets chargés !');
            // Réactiver la sauvegarde automatique avec un délai pour laisser les événements se terminer
            // Cela évite qu'un événement object:added déclenche une sauvegarde juste après le chargement
            setTimeout(() => {
              isLoadingFromServer = false;
              console.log('🔓 Sauvegarde automatique réactivée');
            }, 100);
          }
        });
      } else if (objData.type === 'arrow') {
        recreateArrow(objData, () => { 
          loaded++; 
          if (loaded === totalObjects) {
            console.log('✅ Tous les objets chargés !');
            // Réactiver la sauvegarde automatique avec un délai pour laisser les événements se terminer
            setTimeout(() => {
              isLoadingFromServer = false;
              console.log('🔓 Sauvegarde automatique réactivée');
            }, 100);
          }
        });
      } else if (objData.type === 'paper') {
        recreatePaper(objData, () => { 
          loaded++; 
          if (loaded === totalObjects) {
            console.log('✅ Tous les objets chargés !');
            // Réactiver la sauvegarde automatique avec un délai pour laisser les événements se terminer
            setTimeout(() => {
              isLoadingFromServer = false;
              console.log('🔓 Sauvegarde automatique réactivée');
            }, 100);
          }
        });
      }
    });
  })
  .catch(error => {
    console.error('❌ Erreur de chargement:', error);
    // Réactiver la sauvegarde automatique même en cas d'erreur, avec un délai
    setTimeout(() => {
      isLoadingFromServer = false;
      console.log('🔓 Sauvegarde automatique réactivée (après erreur)');
    }, 100);
  });
}

// Le bouton de sauvegarde a été supprimé car la sauvegarde est désormais entièrement automatique
// Toutes les modifications sont sauvegardées automatiquement après 1 seconde d'inactivité

// ========== ÉVÉNEMENTS POUR SAUVEGARDE AUTOMATIQUE ==========

// Déclencher la sauvegarde après modification d'objets
canvas.on('object:modified', function(e) {
  console.log('📝 Objet modifié, déclenchement de la sauvegarde automatique');
  triggerAutoSave();
});

// Déclencher la sauvegarde après ajout d'objets
canvas.on('object:added', function(e) {
  // Ne pas sauvegarder lors du chargement initial (backgroundImage)
  if (e.target === backgroundImage) return;
  console.log('➕ Objet ajouté, déclenchement de la sauvegarde automatique');
  triggerAutoSave();
});

// Déclencher la sauvegarde après suppression d'objets
canvas.on('object:removed', function(e) {
  // Ne pas sauvegarder lors du nettoyage (changement de pièce)
  if (e.target === backgroundImage) return;
  console.log('➖ Objet supprimé, déclenchement de la sauvegarde automatique');
  triggerAutoSave();
});

// Nettoyer l'ancien cache localStorage
localStorage.removeItem("fabricCanvas");

