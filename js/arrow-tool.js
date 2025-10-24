// ========== OUTIL FLÈCHE ==========

// Variables globales (accessibles depuis keyboard.js)
isPlacingArrow = false;
arrowPlaceholderImg = null;
arrowPlaceholderMoveHandler = null;
arrowPreviewSize = null;
arrowPreviewAngle = 0; // Angle de rotation pendant le placement
let arrowFreeMoveMode = false; // Mode de déplacement libre activé/désactivé

// Réinitialiser le mode de déplacement libre quand on désélectionne
canvas.on('selection:cleared', function() {
  if (arrowFreeMoveMode) {
    arrowFreeMoveMode = false;
('🔒 Mode déplacement libre désactivé (désélection)');
  }
});

// Synchroniser arrowFreeMoveMode avec freePlacement lors de la sélection
canvas.on('selection:created', function(e) {
  const obj = e.selected && e.selected[0];
  if (obj && obj.isArrow) {
    arrowFreeMoveMode = obj.freePlacement || false;
    if (arrowFreeMoveMode) {
('🔓 Flèche en mode libre détectée');
    }
  }
});

// Synchroniser arrowFreeMoveMode avec freePlacement lors du changement de sélection
canvas.on('selection:updated', function(e) {
  const obj = e.selected && e.selected[0];
  if (obj && obj.isArrow) {
    arrowFreeMoveMode = obj.freePlacement || false;
    if (arrowFreeMoveMode) {
('🔓 Flèche en mode libre détectée');
    }
  } else if (arrowFreeMoveMode) {
    arrowFreeMoveMode = false;
('🔒 Mode déplacement libre désactivé (changement de sélection)');
  }
});

// Variables pour la position contrainte et la modale
const ARROW_BOTTOM_OFFSET = 200; // Distance du bas de l'image en pixels
const ARROW_SIDE_OFFSET = 200; // Distance des bords gauche/droite en pixels (égale à celle du bas)
const ARROW_FIXED_Z_INDEX = 1000; // Z-index fixe pour toutes les flèches
let pendingArrowData = null; // Données de la flèche en attente de confirmation

function updateArrowPlaceholderSize() {
  if (!arrowPlaceholderImg || !arrowPreviewSize) return;
  const zoom = canvas.getZoom();
  arrowPlaceholderImg.style.width = `${arrowPreviewSize.w * zoom}px`;
  arrowPlaceholderImg.style.height = `${arrowPreviewSize.h * zoom}px`;
  arrowPlaceholderImg.style.transform = `translate(-50%, -50%) rotate(${arrowPreviewAngle}deg)`;
}

function positionArrowPreviewAtCenter() {
  if (!arrowPreviewSize) return;
  if (arrowPlaceholderImg) {
    // Position horizontale = curseur ou centre écran CONTRAINTE
    let x = (lastMousePos.x !== null) ? lastMousePos.x : window.innerWidth / 2;
    
    // Calculer la position verticale sur la ligne contrainte
    let y;
    if (backgroundImage) {
      const canvasRect = canvas.upperCanvasEl.getBoundingClientRect();
      const zoom = canvas.getZoom();
      const vpt = canvas.viewportTransform;
      const imgHeight = backgroundImage.height * backgroundImage.scaleY;
      y = (imgHeight - ARROW_BOTTOM_OFFSET) * zoom + vpt[5] + canvasRect.top;
    } else {
      y = (lastMousePos.y !== null) ? lastMousePos.y : window.innerHeight / 2;
    }
    
    // Appliquer les contraintes horizontales
    if (backgroundImage) {
      const canvasRect = canvas.upperCanvasEl.getBoundingClientRect();
      const zoom = canvas.getZoom();
      const vpt = canvas.viewportTransform;
      const imgWidth = backgroundImage.width * backgroundImage.scaleX;
      const minXScreen = ARROW_SIDE_OFFSET * zoom + vpt[4] + canvasRect.left;
      const maxXScreen = (imgWidth - ARROW_SIDE_OFFSET) * zoom + vpt[4] + canvasRect.left;
      x = Math.max(minXScreen, Math.min(maxXScreen, x));
    }
    
    arrowPlaceholderImg.style.left = `${x}px`;
    arrowPlaceholderImg.style.top = `${y}px`;
    updateArrowPlaceholderSize();
  }
  canvas.requestRenderAll();
}

function removeArrowPlaceholder() {
  if (arrowPlaceholderMoveHandler) {
    window.removeEventListener('mousemove', arrowPlaceholderMoveHandler);
    arrowPlaceholderMoveHandler = null;
  }
  if (arrowPlaceholderImg && arrowPlaceholderImg.parentNode) {
    arrowPlaceholderImg.parentNode.removeChild(arrowPlaceholderImg);
  }
  arrowPlaceholderImg = null;
}

function cancelArrowPlacement() {
  removeArrowPlaceholder();
  arrowPreviewSize = null;
  arrowPreviewAngle = 0;
  isPlacingArrow = false;
  canvas.skipTargetFind = false;
  canvas.selection = true;
  canvas.defaultCursor = 'default';
  canvas.hoverCursor = 'move';
  document.getElementById("addArrow").style.background = "#3a3a3a";
  canvas.requestRenderAll();
}

function finalizeArrowPlacement(opt) {
  if (!isPlacingArrow || !arrowPreviewSize) return;
  const p = canvas.getPointer(opt.e);
  
  // Position horizontale = position de la souris CONTRAINTE par les limites de l'image
  let arrowLeft = p.x;
  
  // Contraindre la position horizontale dans les limites de l'image avec padding
  if (backgroundImage) {
    const imgWidth = backgroundImage.width * backgroundImage.scaleX;
    const minX = ARROW_SIDE_OFFSET;
    const maxX = imgWidth - ARROW_SIDE_OFFSET;
    arrowLeft = Math.max(minX, Math.min(maxX, arrowLeft));
  }
  
  // Position verticale = contrainte à une ligne en bas de l'image
  // Calculer la position en fonction de la hauteur de l'image de fond
  let arrowTop;
  if (backgroundImage) {
    const imgHeight = backgroundImage.height * backgroundImage.scaleY;
    arrowTop = imgHeight - ARROW_BOTTOM_OFFSET;
  } else {
    arrowTop = canvas.height - ARROW_BOTTOM_OFFSET;
  }
  
  removeArrowPlaceholder();
  isPlacingArrow = false;
  const savedAngle = arrowPreviewAngle;
  arrowPreviewSize = null;
  arrowPreviewAngle = 0;
  canvas.skipTargetFind = false;
  document.getElementById("addArrow").style.background = "#3a3a3a";
  
  // Stocker les données de la flèche en attente
  pendingArrowData = {
    left: arrowLeft,
    top: arrowTop,
    angle: savedAngle,
    zIndex: ARROW_FIXED_Z_INDEX
  };
  
  // Afficher la modale pour sélectionner la pièce cible
  showArrowTargetModal();
}

// Bouton Ajouter Flèche
document.getElementById("addArrow").onclick = function() {
  // Vérifier si le bouton est désactivé
  if (this.classList.contains('disabled')) return;
  
  if (!arrowDataUrl) {
    alert("L'image arrow.png n'a pas été trouvée !");
    return;
  }
  if (isPlacingArrow) return;
  isPlacingArrow = true;
  arrowPreviewAngle = 0; // Réinitialiser l'angle
  document.getElementById("addArrow").style.background = "#1a7f1a";
  canvas.discardActiveObject();
  canvas.selection = false;
  canvas.skipTargetFind = true;
  canvas.defaultCursor = 'crosshair';
  canvas.hoverCursor = 'crosshair';
  
  const img = new Image();
  img.src = arrowDataUrl;
  img.style.position = 'fixed';
  img.style.pointerEvents = 'none';
  img.style.opacity = '0.5';
  img.style.zIndex = '2000';
  img.style.display = 'none';
  document.body.appendChild(img);
  arrowPlaceholderImg = img;
  img.onload = () => {
    const wWorld = img.naturalWidth;
    const hWorld = img.naturalHeight;
    arrowPreviewSize = { w: wWorld, h: hWorld };
    img.style.display = 'block';
    positionArrowPreviewAtCenter();
  };
  
  arrowPlaceholderMoveHandler = (e) => {
    if (!arrowPlaceholderImg) return;
    
    // Calculer la position en coordonnées canvas
    const canvasRect = canvas.upperCanvasEl.getBoundingClientRect();
    const zoom = canvas.getZoom();
    const vpt = canvas.viewportTransform;
    
    // Position horizontale = souris CONTRAINTE
    let targetX = e.clientX;
    
    // Appliquer les contraintes horizontales
    if (backgroundImage) {
      const imgWidth = backgroundImage.width * backgroundImage.scaleX;
      const minXScreen = ARROW_SIDE_OFFSET * zoom + vpt[4] + canvasRect.left;
      const maxXScreen = (imgWidth - ARROW_SIDE_OFFSET) * zoom + vpt[4] + canvasRect.left;
      targetX = Math.max(minXScreen, Math.min(maxXScreen, targetX));
    }
    
    arrowPlaceholderImg.style.left = `${targetX}px`;
    
    // Position verticale = ligne contrainte
    let targetY;
    if (backgroundImage) {
      const imgHeight = backgroundImage.height * backgroundImage.scaleY;
      targetY = (imgHeight - ARROW_BOTTOM_OFFSET) * zoom + vpt[5] + canvasRect.top;
    } else {
      targetY = e.clientY; // Fallback si pas d'image de fond
    }
    
    arrowPlaceholderImg.style.top = `${targetY}px`;
    updateArrowPlaceholderSize();
  };
  window.addEventListener('mousemove', arrowPlaceholderMoveHandler);
("👆 Placez la flèche avec un clic (position verticale automatique). Utilisez ← → pour la tourner.");
};

// Fonction pour recréer une flèche (utilisée au chargement)
function recreateArrow(arrowData, callback) {
  if (!arrowDataUrl) {
    if (callback) callback();
    return;
  }
  
  // Restaurer l'état de freePlacement depuis les données chargées
  const freePlacement = arrowData.freePlacement || false;
  
  fabric.Image.fromURL(arrowDataUrl, (arrowImg) => {
    arrowImg.set({
      left: arrowData.left,
      top: arrowData.top,
      angle: arrowData.angle || 0,
      selectable: true,
      evented: true,
      hasControls: true,
      hasBorders: true,
      cornerColor: 'cyan',
      cornerStyle: 'circle',
      originX: 'center',
      originY: 'center',
      // Désactiver les contrôles de redimensionnement
      lockScalingX: true,
      lockScalingY: true,
      // Verrouiller la position verticale SAUF si freePlacement est true
      lockMovementY: !freePlacement,
      // Garder les contrôles de rotation et position
      hasRotatingPoint: true,
      dbId: arrowData.id || null, // Conserver l'ID de la BDD
      zIndex: ARROW_FIXED_Z_INDEX, // Z-index fixe de 1000
      isArrow: true, // Marqueur pour identifier les flèches
      targetPhotoName: arrowData.targetPhotoName || null, // Photo de destination
      freePlacement: freePlacement // Conserver l'état de placement libre
    });
    
    // Ajouter le gestionnaire de clic pour la navigation
    arrowImg.on('mousedown', function(opt) {
      const isPlayerMode = typeof window.isPlayerMode !== 'undefined' && window.isPlayerMode;
      const shiftPressed = opt.e.shiftKey; // Touche Shift
      
      // En mode player : empêcher complètement la sélection
      if (isPlayerMode) {
        opt.e.preventDefault();
        opt.e.stopPropagation();
        
        // Navigation uniquement avec un clic simple (sans shift)
        if (!shiftPressed && this.targetPhotoName) {
          // Trouver le chemin complet de la photo cible
          const targetPath = roomImages.find(path => {
            const filename = path.split('/').pop();
            const photoName = filename.replace(/\.[^/.]+$/, '');
            return photoName === this.targetPhotoName;
          });
          
          if (targetPath) {
('🎯 Navigation vers:', this.targetPhotoName);
            setBackgroundImage(targetPath);
          } else {
            console.warn('⚠️ Photo cible non trouvée:', this.targetPhotoName);
          }
        }
        return false; // Empêcher tout traitement supplémentaire
      }
      
      // En mode editor : Shift+clic navigue
      if (shiftPressed && this.targetPhotoName) {
        // Trouver le chemin complet de la photo cible
        const targetPath = roomImages.find(path => {
          const filename = path.split('/').pop();
          const photoName = filename.replace(/\.[^/.]+$/, '');
          return photoName === this.targetPhotoName;
        });
        
        if (targetPath) {
('🎯 Navigation vers:', this.targetPhotoName, '(shift+clic)');
          setBackgroundImage(targetPath);
        } else {
          console.warn('⚠️ Photo cible non trouvée:', this.targetPhotoName);
        }
        
        opt.e.preventDefault();
        opt.e.stopPropagation();
        return false;
      }
    });
    
    // Changer le curseur au survol en mode player
    arrowImg.on('mouseover', function() {
      const isPlayerMode = typeof window.isPlayerMode !== 'undefined' && window.isPlayerMode;
      if (isPlayerMode) {
        canvas.hoverCursor = 'pointer';
      }
    });
    
    arrowImg.on('mouseout', function() {
      const isPlayerMode = typeof window.isPlayerMode !== 'undefined' && window.isPlayerMode;
      if (isPlayerMode) {
        canvas.hoverCursor = 'default';
      }
    });
    
    // Contraintes lors du déplacement (garder sur la ligne)
    arrowImg.on('moving', function() {
      const isPlayerMode = typeof window.isPlayerMode !== 'undefined' && window.isPlayerMode;
      
      // Si la flèche est en mode placement libre OU si le mode temporaire est activé
      if ((this.freePlacement || arrowFreeMoveMode) && !isPlayerMode) {
        return; // Pas de contraintes
      }
      
      // Sinon, appliquer les contraintes normales
      if (backgroundImage) {
        // Contraindre la position horizontale
        const imgWidth = backgroundImage.width * backgroundImage.scaleX;
        const minX = ARROW_SIDE_OFFSET;
        const maxX = imgWidth - ARROW_SIDE_OFFSET;
        this.left = Math.max(minX, Math.min(maxX, this.left));
        
        // Position verticale fixe
        const imgHeight = backgroundImage.height * backgroundImage.scaleY;
        this.top = imgHeight - ARROW_BOTTOM_OFFSET;
      }
    });
    
    // Rotation par paliers de 15 degrés
    arrowImg.on('rotating', function() {
      const snapAngle = 15;
      this.angle = Math.round(this.angle / snapAngle) * snapAngle;
    });
    
    canvas.add(arrowImg);
    canvas.bringToFront(arrowImg); // Toujours au premier plan
    canvas.renderAll();
    if (callback) callback();
  });
}

// ========== GESTION DE LA MODALE ==========

// Variables pour le carrousel
let carouselImages = [];
let carouselCurrentIndex = 0;
let carouselMode = 'arrow'; // 'arrow' pour flèche, 'room' pour changement de pièce
let editingArrow = null; // Flèche en cours de modification

function updateCarousel() {
  const carouselImage = document.getElementById('carouselImage');
  const carouselPlaceholder = document.getElementById('carouselPlaceholder');
  const carouselCounter = document.getElementById('carouselCounter');
  const carouselName = document.getElementById('carouselName');
  const prevBtn = document.getElementById('carouselPrev');
  const nextBtn = document.getElementById('carouselNext');
  
  if (carouselImages.length === 0) {
    carouselImage.style.display = 'none';
    carouselPlaceholder.style.display = 'block';
    carouselCounter.textContent = '0 / 0';
    carouselName.textContent = '-';
    prevBtn.style.display = 'none';
    nextBtn.style.display = 'none';
    return;
  }
  
  // Afficher l'image actuelle
  const currentItem = carouselImages[carouselCurrentIndex];
  carouselImage.src = currentItem.path;
  carouselImage.style.display = 'block';
  carouselPlaceholder.style.display = 'none';
  
  // Mettre à jour l'indicateur
  carouselCounter.textContent = `${carouselCurrentIndex + 1} / ${carouselImages.length}`;
  carouselName.textContent = currentItem.name;
  
  // Afficher/masquer les boutons selon la position
  prevBtn.style.display = 'flex';
  nextBtn.style.display = 'flex';
}

function showArrowTargetModal(mode = 'arrow', arrowToEdit = null) {
  const modal = document.getElementById('arrowTargetModal');
  const modalTitle = document.getElementById('modalTitle');
  const modalDescription = document.getElementById('modalDescription');
  
  carouselMode = mode;
  editingArrow = arrowToEdit;
  
  // Adapter le contenu selon le mode
  if (mode === 'arrow') {
    if (editingArrow) {
      modalTitle.textContent = 'Modifier la destination de la flèche';
      modalDescription.textContent = 'Sélectionnez la nouvelle photo de destination :';
    } else {
      modalTitle.textContent = 'Vers quelle photo ?';
      modalDescription.textContent = 'Sélectionnez la photo de destination pour cette flèche :';
    }
  } else {
    modalTitle.textContent = 'Changer de pièce';
    modalDescription.textContent = 'Sélectionnez la nouvelle pièce à afficher :';
  }
  
  // Initialiser le carrousel avec les images disponibles
  carouselImages = roomImages.map(imgPath => {
    const filename = imgPath.split('/').pop();
    const photoName = filename.replace(/\.[^/.]+$/, '');
    return {
      path: imgPath,
      name: photoName
    };
  });
  
  // Trouver l'index de départ pour le carrousel
  carouselCurrentIndex = 0;
  
  // Si on édite une flèche, démarrer sur sa photo cible
  if (editingArrow && editingArrow.targetPhotoName) {
    const targetIndex = carouselImages.findIndex(img => img.name === editingArrow.targetPhotoName);
    if (targetIndex !== -1) {
      carouselCurrentIndex = targetIndex;
('📍 Démarrage du carrousel sur la photo cible de la flèche:', editingArrow.targetPhotoName);
    }
  }
  // Sinon, démarrer sur la photo actuellement affichée
  else if (typeof currentBackgroundKey !== 'undefined' && currentBackgroundKey) {
    const currentIndex = carouselImages.findIndex(img => img.name === currentBackgroundKey);
    if (currentIndex !== -1) {
      carouselCurrentIndex = currentIndex;
('📍 Démarrage du carrousel sur la photo actuelle:', currentBackgroundKey, 'index:', currentIndex);
    }
  }
  
  updateCarousel();
  
  // Ajouter l'event listener pour les touches du clavier
  document.addEventListener('keydown', carouselKeyHandler);
  
  // Afficher la modale
  modal.style.display = 'flex';
}

// Gestionnaire de touches pour le carrousel
function carouselKeyHandler(e) {
  const modal = document.getElementById('arrowTargetModal');
  if (modal.style.display !== 'flex') return;
  
  if (e.key === 'ArrowLeft') {
    e.preventDefault();
    document.getElementById('carouselPrev').click();
  } else if (e.key === 'ArrowRight') {
    e.preventDefault();
    document.getElementById('carouselNext').click();
  } else if (e.key === 'Enter') {
    e.preventDefault();
    document.getElementById('confirmArrowBtn').click();
  } else if (e.key === 'Escape') {
    e.preventDefault();
    document.getElementById('cancelArrowBtn').click();
  }
}

// Gestionnaires de navigation du carrousel
document.getElementById('carouselPrev').onclick = function() {
  if (carouselImages.length === 0) return;
  carouselCurrentIndex = (carouselCurrentIndex - 1 + carouselImages.length) % carouselImages.length;
  updateCarousel();
};

document.getElementById('carouselNext').onclick = function() {
  if (carouselImages.length === 0) return;
  carouselCurrentIndex = (carouselCurrentIndex + 1) % carouselImages.length;
  updateCarousel();
};

function hideArrowTargetModal() {
  const modal = document.getElementById('arrowTargetModal');
  const carouselImage = document.getElementById('carouselImage');
  
  modal.style.display = 'none';
  pendingArrowData = null;
  
  // Retirer l'event listener du clavier
  document.removeEventListener('keydown', carouselKeyHandler);
  
  // Nettoyer le carrousel
  carouselImages = [];
  carouselCurrentIndex = 0;
  carouselImage.src = '';
  carouselMode = 'arrow';
  editingArrow = null;
}

function createArrowWithTarget(targetPhotoName) {
  if (!pendingArrowData) {
    console.error("❌ Pas de données de flèche en attente");
    return;
  }
  
  // Sauvegarder les données avant qu'elles ne soient réinitialisées
  // Arrondir l'angle à un multiple de 15
  const snapAngle = 15;
  const roundedAngle = Math.round(pendingArrowData.angle / snapAngle) * snapAngle;
  
  const arrowData = {
    left: pendingArrowData.left,
    top: pendingArrowData.top,
    angle: roundedAngle,
    zIndex: pendingArrowData.zIndex
  };
  
("🎯 Création de la flèche avec données:", arrowData, "vers", targetPhotoName);
  
  fabric.Image.fromURL(arrowDataUrl, (arrowImg) => {
    arrowImg.set({
      left: arrowData.left,
      top: arrowData.top,
      angle: arrowData.angle,
      selectable: true,
      evented: true,
      hasControls: true,
      hasBorders: true,
      cornerColor: 'cyan',
      cornerStyle: 'circle',
      originX: 'center',
      originY: 'center',
      // Désactiver les contrôles de redimensionnement
      lockScalingX: true,
      lockScalingY: true,
      // Garder les contrôles de rotation et position
      hasRotatingPoint: true,
      // Verrouiller la position verticale
      lockMovementY: true
    });
    
    // Attribuer l'ID de la BDD, le z-index et la photo cible
    arrowImg.dbId = null; // Sera assigné après sauvegarde
    arrowImg.zIndex = ARROW_FIXED_Z_INDEX; // Z-index fixe de 1000
    arrowImg.isArrow = true; // Marqueur pour identifier les flèches
    arrowImg.targetPhotoName = targetPhotoName; // Nom de la photo cible
    arrowImg.freePlacement = false; // Par défaut, la flèche est contrainte sur la ligne
    
    // Ajouter le gestionnaire de clic pour la navigation
    arrowImg.on('mousedown', function(opt) {
      const isPlayerMode = typeof window.isPlayerMode !== 'undefined' && window.isPlayerMode;
      const shiftPressed = opt.e.shiftKey; // Touche Shift
      
      // En mode player : empêcher complètement la sélection
      if (isPlayerMode) {
        opt.e.preventDefault();
        opt.e.stopPropagation();
        
        // Navigation uniquement avec un clic simple (sans shift)
        if (!shiftPressed && this.targetPhotoName) {
          // Trouver le chemin complet de la photo cible
          const targetPath = roomImages.find(path => {
            const filename = path.split('/').pop();
            const photoName = filename.replace(/\.[^/.]+$/, '');
            return photoName === this.targetPhotoName;
          });
          
          if (targetPath) {
('🎯 Navigation vers:', this.targetPhotoName);
            setBackgroundImage(targetPath);
          } else {
            console.warn('⚠️ Photo cible non trouvée:', this.targetPhotoName);
          }
        }
        return false; // Empêcher tout traitement supplémentaire
      }
      
      // En mode editor : Shift+clic navigue
      if (shiftPressed && this.targetPhotoName) {
        // Trouver le chemin complet de la photo cible
        const targetPath = roomImages.find(path => {
          const filename = path.split('/').pop();
          const photoName = filename.replace(/\.[^/.]+$/, '');
          return photoName === this.targetPhotoName;
        });
        
        if (targetPath) {
('🎯 Navigation vers:', this.targetPhotoName, '(shift+clic)');
          setBackgroundImage(targetPath);
        } else {
          console.warn('⚠️ Photo cible non trouvée:', this.targetPhotoName);
        }
        
        opt.e.preventDefault();
        opt.e.stopPropagation();
        return false;
      }
    });
    
    // Changer le curseur au survol en mode player
    arrowImg.on('mouseover', function() {
      const isPlayerMode = typeof window.isPlayerMode !== 'undefined' && window.isPlayerMode;
      if (isPlayerMode) {
        canvas.hoverCursor = 'pointer';
      }
    });
    
    arrowImg.on('mouseout', function() {
      const isPlayerMode = typeof window.isPlayerMode !== 'undefined' && window.isPlayerMode;
      if (isPlayerMode) {
        canvas.hoverCursor = 'default';
      }
    });
    
    // Contraintes lors du déplacement (garder sur la ligne)
    arrowImg.on('moving', function() {
      const isPlayerMode = typeof window.isPlayerMode !== 'undefined' && window.isPlayerMode;
      
      // Si la flèche est en mode placement libre OU si le mode temporaire est activé
      if ((this.freePlacement || arrowFreeMoveMode) && !isPlayerMode) {
        return; // Pas de contraintes
      }
      
      // Sinon, appliquer les contraintes normales
      if (backgroundImage) {
        // Contraindre la position horizontale
        const imgWidth = backgroundImage.width * backgroundImage.scaleX;
        const minX = ARROW_SIDE_OFFSET;
        const maxX = imgWidth - ARROW_SIDE_OFFSET;
        this.left = Math.max(minX, Math.min(maxX, this.left));
        
        // Position verticale fixe
        const imgHeight = backgroundImage.height * backgroundImage.scaleY;
        this.top = imgHeight - ARROW_BOTTOM_OFFSET;
      }
    });
    
    // Rotation par paliers de 15 degrés
    arrowImg.on('rotating', function() {
      const snapAngle = 15;
      this.angle = Math.round(this.angle / snapAngle) * snapAngle;
    });
    
    // Ajouter la flèche au canvas et la rendre visible
    canvas.add(arrowImg);
    canvas.bringToFront(arrowImg); // Mettre au premier plan
    canvas.selection = true;
    canvas.setActiveObject(arrowImg);
    canvas.defaultCursor = 'move';
    canvas.hoverCursor = 'move';
    canvas.renderAll();
("✅ Flèche ajoutée avec angle:", arrowData.angle + "° vers", targetPhotoName);
  });
  
  // Réinitialiser APRÈS avoir copié les données
  pendingArrowData = null;
}

// Gestion des événements de la modale
document.getElementById('confirmArrowBtn').onclick = function() {
  if (carouselImages.length === 0) {
    alert('Aucune photo disponible !');
    return;
  }
  
  const selectedImage = carouselImages[carouselCurrentIndex];
  
  if (carouselMode === 'arrow') {
    if (editingArrow) {
      // Mode édition : modifier la destination de la flèche existante
      editingArrow.targetPhotoName = selectedImage.name;
('✏️ Destination de la flèche modifiée vers:', selectedImage.name);
      canvas.renderAll();
      
      // Déclencher la sauvegarde automatique
      if (typeof triggerAutoSave === 'function') {
        triggerAutoSave();
      }
    } else {
      // Mode création : créer la flèche avec la photo cible
      createArrowWithTarget(selectedImage.name);
    }
  } else {
    // Mode changement de pièce : changer l'image de fond
    setBackgroundImage(selectedImage.path);
  }
  
  // Nettoyer et cacher la modale correctement
  const modal = document.getElementById('arrowTargetModal');
  const carouselImage = document.getElementById('carouselImage');
  
  // Retirer l'event listener du clavier
  document.removeEventListener('keydown', carouselKeyHandler);
  
  carouselImages = [];
  carouselCurrentIndex = 0;
  carouselImage.src = '';
  modal.style.display = 'none';
  carouselMode = 'arrow';
  editingArrow = null;
};

document.getElementById('cancelArrowBtn').onclick = function() {
  hideArrowTargetModal();
  canvas.selection = true;
};

