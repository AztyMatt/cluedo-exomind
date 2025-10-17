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
const paperPlaceholderScale = 0.5;
let lastMousePos = { x: null, y: null };

// Variables pour le pan
let isDragging = false;
let lastPosX, lastPosY;

// Variable pour la sélection de masque
let lastSelectedMask = null;

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

// Gestionnaire de curseur pour les flèches en mode player
canvas.on('mouse:move', function(opt) {
  if (isPlayerMode) {
    const obj = canvas.findTarget(opt.e, false);
    if (obj && obj.isArrow) {
      canvas.defaultCursor = 'pointer';
      canvas.setCursor('pointer');
    } else {
      canvas.defaultCursor = 'default';
      canvas.setCursor('default');
    }
  }
});

// Gestionnaire de clic global pour les flèches en mode player
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
        console.log('🎯 Navigation globale vers:', obj.targetPhotoName, '(mode player)');
        setBackgroundImage(targetPath);
        
        // Réinitialiser le curseur après le changement de photo
        setTimeout(() => {
          if (isPlayerMode) {
            canvas.defaultCursor = 'default';
            canvas.hoverCursor = 'default';
            canvas.setCursor('default');
          }
        }, 50);
      } else {
        console.warn('⚠️ Photo cible non trouvée:', obj.targetPhotoName);
      }
    }
    
    canvas.requestRenderAll();
    return false;
  }
});

