// ========== GESTION DU VIEWPORT (ZOOM/PAN) ==========
let baseZoom = 1;
let isAtBaseZoom = true;

function computeBaseViewport() {
  if (!backgroundImage) return { zoom: 1, panX: 0, panY: 0 };
  const iw = backgroundImage.width;
  const ih = backgroundImage.height;
  const cw = canvas.getWidth();
  const ch = canvas.getHeight();
  const zoom = Math.min(cw / iw, ch / ih);
  const panX = (cw - iw * zoom) / 2;
  const panY = (ch - ih * zoom) / 2;
  return { zoom, panX, panY };
}

function applyBaseViewport() {
  const { zoom, panX, panY } = computeBaseViewport();
  baseZoom = zoom;
  canvas.setViewportTransform([zoom, 0, 0, zoom, panX, panY]);
  canvas.requestRenderAll();
}

function resetZoomAndPan() {
  applyBaseViewport();
  isAtBaseZoom = true;
}

// Contraindre le viewport pour ne jamais montrer en dehors de l'image
function constrainViewportToImage() {
  if (!backgroundImage || !isPlayerMode) return;
  
  const vpt = canvas.viewportTransform;
  const zoom = vpt[0];
  
  // Dimensions de l'image (en tenant compte du scale de l'image elle-même)
  const iw = backgroundImage.width * (backgroundImage.scaleX || 1);
  const ih = backgroundImage.height * (backgroundImage.scaleY || 1);
  const cw = canvas.getWidth();
  const ch = canvas.getHeight();
  
  // Dimensions de l'image zoomée dans le viewport
  const scaledWidth = iw * zoom;
  const scaledHeight = ih * zoom;
  
  // Si l'image est plus petite que le canvas, on centre
  if (scaledWidth <= cw) {
    vpt[4] = (cw - scaledWidth) / 2;
  } else {
    // Sinon, on contraint pour ne pas dépasser les bords
    // L'image doit toujours remplir complètement le viewport (pas d'espace vide)
    const minPanX = cw - scaledWidth; // Bord droit de l'image au bord droit du canvas
    const maxPanX = 0; // Bord gauche de l'image au bord gauche du canvas
    vpt[4] = Math.max(minPanX, Math.min(maxPanX, vpt[4]));
  }
  
  if (scaledHeight <= ch) {
    vpt[5] = (ch - scaledHeight) / 2;
  } else {
    const minPanY = ch - scaledHeight; // Bord bas de l'image au bord bas du canvas
    const maxPanY = 0; // Bord haut de l'image au bord haut du canvas
    vpt[5] = Math.max(minPanY, Math.min(maxPanY, vpt[5]));
  }
  
  canvas.setViewportTransform(vpt);
  canvas.requestRenderAll();
}

// Redimensionnement de la fenêtre
window.addEventListener('resize', () => {
  canvas.setDimensions({ width: window.innerWidth, height: window.innerHeight });
  if (isAtBaseZoom) {
    applyBaseViewport();
  } else {
    canvas.renderAll();
    constrainViewportToImage();
  }
  if (isPlacingPaper) updatePaperPlaceholderSize();
});

// Zoom à la molette
canvas.on("mouse:wheel", function (opt) {
  const delta = opt.e.deltaY;
  let zoom = canvas.getZoom();
  zoom *= 0.999 ** delta;
  zoom = Math.max(Math.min(zoom, 10), baseZoom);
  canvas.zoomToPoint({ x: opt.e.offsetX, y: opt.e.offsetY }, zoom);
  
  // Contraindre le viewport en mode player
  if (isPlayerMode) {
    constrainViewportToImage();
  }
  
  isAtBaseZoom = Math.abs(canvas.getZoom() - baseZoom) < 1e-6;
  if (isPlacingPaper) updatePaperPlaceholderSize();
  opt.e.preventDefault();
  opt.e.stopPropagation();
});

