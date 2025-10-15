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

