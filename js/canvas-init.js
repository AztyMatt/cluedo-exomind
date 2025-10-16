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

// Gestionnaire de clic global pour les flèches en mode player
canvas.on('mouse:down', function(opt) {
  if (!opt.target) return;
  
  const obj = opt.target;
  
  // Si c'est une flèche et qu'on est en mode player
  if (obj.isArrow && isPlayerMode && !opt.e.shiftKey) {
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
      } else {
        console.warn('⚠️ Photo cible non trouvée:', obj.targetPhotoName);
      }
    }
  }
});

