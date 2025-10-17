// ========== SÉLECTEUR D'IMAGES ET IMAGE DE FOND ==========

function pathToKey(p) {
  const base = (p || '').split('/').pop() || '';
  return base.includes('.') ? base.substring(0, base.lastIndexOf('.')) : base;
}

function initRoomSelector() {
  // Charger l'image par défaut au démarrage
  const defaultPath = 'rooms/P1080905.JPG';
  const initialImage = roomImages.includes(defaultPath) ? defaultPath : (roomImages[0] || null);
  if (initialImage) {
    setBackgroundImage(initialImage);
  }
  
  // Ajouter l'event listener au bouton de changement de pièce
  const changeRoomBtn = document.getElementById('changeRoomBtn');
  if (changeRoomBtn) {
    changeRoomBtn.onclick = function() {
      showArrowTargetModal('room');
    };
  }
}

function setBackgroundImage(src) {
  if (!src) return;
  
  const canvasContainer = document.getElementById('canvas-container');
  const newKey = pathToKey(src);
  
  console.log('🔄 setBackgroundImage - src:', src, 'newKey:', newKey, 'oldKey:', currentBackgroundKey);
  
  // Vérifier si c'est un changement de photo (pas le chargement initial)
  const isChanging = currentBackgroundKey && currentBackgroundKey !== newKey;
  
  currentBackgroundKey = newKey;
  console.log('✅ currentBackgroundKey mis à jour à:', currentBackgroundKey);
  
  // Si on change de photo, appliquer une transition
  if (isChanging && canvasContainer) {
    // Fade out
    canvasContainer.style.transition = 'opacity 0.3s ease-out';
    canvasContainer.style.opacity = '0';
    
    setTimeout(() => {
      // Nettoyer et charger la nouvelle image
      loadNewImage(src, canvasContainer);
    }, 300);
  } else {
    // Chargement initial sans transition
    loadNewImage(src, canvasContainer);
  }
}

function loadNewImage(src, canvasContainer) {
  // IMPORTANT: Annuler toute sauvegarde automatique en attente avant de changer de photo
  // Cela évite de sauvegarder les objets de l'ancienne photo avec la clé de la nouvelle photo
  if (window.autoSaveTimeout) {
    clearTimeout(window.autoSaveTimeout);
    window.autoSaveTimeout = null;
    console.log('⏹️ Timer de sauvegarde automatique annulé (changement de photo)');
  }
  
  canvas.getObjects().slice().forEach(o => canvas.remove(o));
  
  fabric.Image.fromURL(
    src,
    function (img) {
      backgroundImage = img;
      backgroundImage.set({
        left: 0,
        top: 0,
        scaleX: 1,
        scaleY: 1,
        originX: 'left',
        originY: 'top',
        selectable: false,
        evented: false
      });
      canvas.add(backgroundImage);
      canvas.sendToBack(backgroundImage);
      applyBaseViewport();
      isAtBaseZoom = true;
      canvas.requestRenderAll();
      console.log('✅ Image de fond remplacée:', src, 'clé:', currentBackgroundKey);
      loadFromServer();
      
      // Fade in
      if (canvasContainer) {
        setTimeout(() => {
          canvasContainer.style.opacity = '1';
        }, 50);
      }
    },
    { crossOrigin: 'anonymous' }
  );
}

