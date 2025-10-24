// ========== INITIALISATION DU CANVAS ==========
const canvasElement = document.getElementById('c');
canvasElement.width = window.innerWidth;
canvasElement.height = window.innerHeight;

const canvas = new fabric.Canvas("c", {
  selection: true,
});

canvas.defaultCursor = 'default';
canvas.hoverCursor = 'move';

// Variables globales
let backgroundImage;
let isPlayerMode = false;
let isLassoMode = false;
let isEditingMode = false; // Nouveau: distinguer le mode édition du mode traçage
let isPlacingPaper = false;
let currentBackgroundKey = '';

// Variables pour le lasso
let points = [];
let curveHandles = {};
let isDraggingHandle = false;
let draggingSegmentIndex = -1;
let isDraggingPoint = false;
let draggingPointIndex = -1;
let editingMask = null;
let tempLines = [];
let tempCircles = [];
let handleCircles = [];
let previewLine = null;
let polygonClosed = false;

// Variables pour le papier
let paperPreviewGroup = null;
let paperPreviewSize = null;
let paperPlaceholderImg = null;
let paperPlaceholderMoveHandler = null;
const paperPlaceholderScale = 0.25;
let lastMousePos = { x: null, y: null };

// Variables pour le pan
let isDragging = false;
let lastPosX, lastPosY;

// Variable pour la sélection de masque
let lastSelectedMask = null;

// Variable pour empêcher la sauvegarde lors de suppression en mode player
let isRemovingPaperInPlayerMode = false;

// Suivre la position de la souris
window.addEventListener('mousemove', (e) => {
  lastMousePos.x = e.clientX;
  lastMousePos.y = e.clientY;
});

// Contraindre le mouvement des flèches
canvas.on('object:moving', function(e) {
  const obj = e.target;
  
  // Si c'est une flèche
  if (obj && obj.isArrow && backgroundImage) {
    const ARROW_SIDE_OFFSET = 150; // Même valeur que dans arrow-tool.js
    const imgWidth = backgroundImage.width * backgroundImage.scaleX;
    const minX = ARROW_SIDE_OFFSET;
    const maxX = imgWidth - ARROW_SIDE_OFFSET;
    
    // Contraindre la position horizontale
    if (obj.left < minX) {
      obj.left = minX;
    } else if (obj.left > maxX) {
      obj.left = maxX;
    }
    
    // S'assurer que la position Y reste fixe (lockMovementY devrait déjà gérer ça)
    obj.setCoords();
  }
});

// Empêcher la sélection des flèches en mode player
canvas.on('before:selection:created', function(e) {
  const target = e.target;
  
  // Si on essaie de sélectionner une flèche en mode player, on annule
  if (target && target.isArrow && isPlayerMode) {
    e.e.preventDefault();
    e.e.stopPropagation();
    canvas.discardActiveObject();
    return false;
  }
});

canvas.on('selection:created', function(e) {
  const target = e.selected && e.selected[0];
  
  // Si une flèche a été sélectionnée en mode player, on la désélectionne immédiatement
  if (target && target.isArrow && isPlayerMode) {
    canvas.discardActiveObject();
    canvas.requestRenderAll();
  }
});

// Gestionnaire de curseur pour les flèches et papiers en mode player
canvas.on('mouse:move', function(opt) {
  if (isPlayerMode && !isDragging) {
    const obj = canvas.findTarget(opt.e, false);
    if (obj && obj.isArrow) {
      canvas.defaultCursor = 'pointer';
      canvas.setCursor('pointer');
    } else if (obj && obj._objects && obj._objects.length >= 2) {
      // Vérifier si c'est un groupe de papier (contient une image et une bordure)
      const hasImage = obj._objects.some(subObj => subObj.type === 'image');
      const hasBorder = obj._objects.some(subObj => subObj.type === 'rect' && subObj.stroke);
      
      if (hasImage && hasBorder) {
        canvas.defaultCursor = 'pointer';
        canvas.setCursor('pointer');
      } else {
        canvas.defaultCursor = 'grab';
        canvas.setCursor('grab');
      }
    } else {
      canvas.defaultCursor = 'grab';
      canvas.setCursor('grab');
    }
  }
});

// Gestionnaire de clic global pour les flèches et papiers en mode player
canvas.on('mouse:down', function(opt) {
  if (!opt.target) return;
  
  const obj = opt.target;
  
  // Si c'est une flèche et qu'on est en mode player
  if (obj.isArrow && isPlayerMode && !opt.e.shiftKey) {
    // Empêcher la sélection
    opt.e.preventDefault();
    opt.e.stopPropagation();
    canvas.discardActiveObject();
    
    // Navigation vers la photo cible
    if (obj.targetPhotoName && typeof setBackgroundImage === 'function') {
      const targetPath = roomImages.find(path => {
        const filename = path.split('/').pop();
        const photoName = filename.replace(/\.[^/.]+$/, '');
        return photoName === obj.targetPhotoName;
      });
      
      if (targetPath) {
('🎯 Navigation globale vers:', obj.targetPhotoName, '(mode player)');
        setBackgroundImage(targetPath);
        
        // Réinitialiser le curseur après le changement de photo
        setTimeout(() => {
          if (isPlayerMode) {
            canvas.defaultCursor = 'grab';
            canvas.hoverCursor = 'grab';
            canvas.setCursor('grab');
          }
        }, 50);
      } else {
        console.warn('⚠️ Photo cible non trouvée:', obj.targetPhotoName);
      }
    }
    
    canvas.requestRenderAll();
    return false;
  }
  
  // Si c'est un papier et qu'on est en mode player
  if (isPlayerMode && obj._objects && obj._objects.length >= 2) {
    // Vérifier si c'est un groupe de papier (contient une image et une bordure)
    const hasImage = obj._objects.some(subObj => subObj.type === 'image');
    const hasBorder = obj._objects.some(subObj => subObj.type === 'rect' && subObj.stroke);
    
    if (hasImage && hasBorder) {
      // Empêcher la sélection
      opt.e.preventDefault();
      opt.e.stopPropagation();
      canvas.discardActiveObject();
      
      // Console.log temporaire pour préparer le terrain pour le comptage
('📄 Papier cliqué en mode player - ID:', obj.id || 'sans ID', 'Position:', { left: obj.left, top: obj.top });
      
      // Supprimer le papier du canvas (pas de la BDD)
      // Activer le flag pour empêcher la sauvegarde automatique
      isRemovingPaperInPlayerMode = true;
      canvas.remove(obj);
      canvas.requestRenderAll();
      // Réinitialiser le flag après un court délai
      setTimeout(() => {
        isRemovingPaperInPlayerMode = false;
      }, 10);
      
('🗑️ Papier supprimé du canvas (pas de la BDD)');
      
      return false;
    }
  }
});

