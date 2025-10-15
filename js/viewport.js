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

// Redimensionnement de la fenêtre
window.addEventListener('resize', () => {
  canvas.setDimensions({ width: window.innerWidth, height: window.innerHeight });
  if (isAtBaseZoom) {
    applyBaseViewport();
  } else {
    canvas.renderAll();
  }
  if (isPlacingPaper) updatePaperPlaceholderSize();
});

// Zoom à la molette
canvas.on("mouse:wheel", function (opt) {
  if (isPlayerMode) {
    opt.e.preventDefault();
    opt.e.stopPropagation();
    return;
  }
  const delta = opt.e.deltaY;
  let zoom = canvas.getZoom();
  zoom *= 0.999 ** delta;
  zoom = Math.max(Math.min(zoom, 10), baseZoom);
  canvas.zoomToPoint({ x: opt.e.offsetX, y: opt.e.offsetY }, zoom);
  isAtBaseZoom = Math.abs(canvas.getZoom() - baseZoom) < 1e-6;
  if (isPlacingPaper) updatePaperPlaceholderSize();
  opt.e.preventDefault();
  opt.e.stopPropagation();
});

