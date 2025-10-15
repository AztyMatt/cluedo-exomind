// ========== OUTIL PAPIER ==========

function updatePaperPlaceholderSize() {
  if (!paperPlaceholderImg || !paperPreviewSize) return;
  const zoom = canvas.getZoom();
  paperPlaceholderImg.style.width = `${paperPreviewSize.w * zoom}px`;
  paperPlaceholderImg.style.height = `${paperPreviewSize.h * zoom}px`;
}

function positionPaperPreviewAtCenter() {
  if (!paperPreviewSize) return;
  if (paperPlaceholderImg) {
    const x = (lastMousePos.x !== null) ? lastMousePos.x : window.innerWidth / 2;
    const y = (lastMousePos.y !== null) ? lastMousePos.y : window.innerHeight / 2;
    paperPlaceholderImg.style.left = `${x}px`;
    paperPlaceholderImg.style.top = `${y}px`;
    updatePaperPlaceholderSize();
  }
  canvas.requestRenderAll();
}

function removePaperPlaceholder() {
  if (paperPlaceholderMoveHandler) {
    window.removeEventListener('mousemove', paperPlaceholderMoveHandler);
    paperPlaceholderMoveHandler = null;
  }
  if (paperPlaceholderImg && paperPlaceholderImg.parentNode) {
    paperPlaceholderImg.parentNode.removeChild(paperPlaceholderImg);
  }
  paperPlaceholderImg = null;
}

function cancelPaperPlacement() {
  removePaperPlaceholder();
  if (paperPreviewGroup) {
    canvas.remove(paperPreviewGroup);
    paperPreviewGroup = null;
  }
  paperPreviewSize = null;
  isPlacingPaper = false;
  canvas.skipTargetFind = false;
  canvas.selection = !isPanMode;
  canvas.defaultCursor = isPanMode ? 'grab' : 'default';
  canvas.hoverCursor = isPanMode ? 'grab' : 'move';
  document.getElementById("addPaper").style.background = "#3a3a3a";
  canvas.requestRenderAll();
}

function finalizePaperPlacement(opt) {
  if (!isPlacingPaper || !paperPreviewSize) return;
  const p = canvas.getPointer(opt.e);
  const paperLeft = p.x - paperPreviewSize.w / 2;
  const paperTop = p.y - paperPreviewSize.h / 2;
  
  removePaperPlaceholder();
  if (paperPreviewGroup) {
    canvas.remove(paperPreviewGroup);
    paperPreviewGroup = null;
  }
  isPlacingPaper = false;
  const savedScale = paperPreviewSize.scale;
  const savedW = paperPreviewSize.w;
  const savedH = paperPreviewSize.h;
  paperPreviewSize = null;
  canvas.skipTargetFind = false;
  document.getElementById("addPaper").style.background = "#3a3a3a";
  
  fabric.Image.fromURL(paperDataUrl, (paperImg) => {
    paperImg.set({
      left: paperLeft,
      top: paperTop,
      scaleX: savedScale,
      scaleY: savedScale,
      selectable: false,
      evented: false,
      originX: 'left',
      originY: 'top'
    });
    const paperBorder = new fabric.Rect({
      left: paperLeft,
      top: paperTop,
      width: savedW,
      height: savedH,
      fill: 'transparent',
      stroke: 'cyan',
      strokeWidth: 2,
      strokeDashArray: [5, 5],
      selectable: false,
      evented: false,
      originX: 'left',
      originY: 'top',
      visible: !isPlayerMode
    });
    const paperGroup = new fabric.Group([paperImg, paperBorder], {
      left: paperLeft,
      top: paperTop,
      selectable: true,
      evented: true,
      hasControls: true,
      hasBorders: false,
      cornerColor: 'cyan',
      cornerStyle: 'circle',
      subTargetCheck: false
    });
    canvas.add(paperGroup);
    canvas.selection = true;
    canvas.setActiveObject(paperGroup);
    canvas.defaultCursor = 'move';
    canvas.hoverCursor = 'move';
    canvas.requestRenderAll();
    console.log("✅ Papier ajouté !");
  });
}

// Bouton Ajouter Papier
document.getElementById("addPaper").onclick = () => {
  if (!paperDataUrl) {
    alert("L'image papier.png n'a été trouvée !");
    return;
  }
  if (isPlacingPaper) return;
  isPlacingPaper = true;
  document.getElementById("addPaper").style.background = "#1a7f1a";
  canvas.discardActiveObject();
  canvas.selection = false;
  canvas.skipTargetFind = true;
  canvas.defaultCursor = 'crosshair';
  canvas.hoverCursor = 'crosshair';
  
  const img = new Image();
  img.src = paperDataUrl;
  img.style.position = 'fixed';
  img.style.pointerEvents = 'none';
  img.style.opacity = '0.5';
  img.style.zIndex = '2000';
  img.style.transform = 'translate(-50%, -50%)';
  img.style.display = 'none';
  document.body.appendChild(img);
  paperPlaceholderImg = img;
  img.onload = () => {
    const wWorld = img.naturalWidth * paperPlaceholderScale;
    const hWorld = img.naturalHeight * paperPlaceholderScale;
    paperPreviewSize = { w: wWorld, h: hWorld, scale: paperPlaceholderScale };
    img.style.display = 'block';
    positionPaperPreviewAtCenter();
  };
  
  paperPlaceholderMoveHandler = (e) => {
    if (!paperPlaceholderImg) return;
    paperPlaceholderImg.style.left = `${e.clientX}px`;
    paperPlaceholderImg.style.top = `${e.clientY}px`;
    updatePaperPlaceholderSize();
  };
  window.addEventListener('mousemove', paperPlaceholderMoveHandler);
  console.log("👆 Placez le papier avec un clic.");
};

// Fonction pour recréer un papier (utilisée au chargement)
function recreatePaper(paperData, callback) {
  if (!paperDataUrl) {
    if (callback) callback();
    return;
  }
  
  fabric.Image.fromURL(paperDataUrl, (paperImg) => {
    paperImg.set({
      left: 0,
      top: 0,
      scaleX: 0.5,
      scaleY: 0.5,
      selectable: false,
      evented: false,
      originX: 'left',
      originY: 'top'
    });
    
    const paperWidth = paperImg.width * 0.5;
    const paperHeight = paperImg.height * 0.5;
    
    const paperBorder = new fabric.Rect({
      left: 0,
      top: 0,
      width: paperWidth,
      height: paperHeight,
      fill: 'transparent',
      stroke: 'cyan',
      strokeWidth: 2,
      strokeDashArray: [5, 5],
      selectable: false,
      evented: false,
      originX: 'left',
      originY: 'top',
      visible: !isPlayerMode
    });
    
    const paperGroup = new fabric.Group([paperImg, paperBorder], {
      left: paperData.left,
      top: paperData.top,
      scaleX: paperData.scaleX || 1,
      scaleY: paperData.scaleY || 1,
      angle: paperData.angle || 0,
      selectable: true,
      evented: true,
      hasControls: true,
      hasBorders: false,
      cornerColor: 'cyan',
      cornerStyle: 'circle',
      subTargetCheck: false
    });
    
    canvas.add(paperGroup);
    canvas.renderAll();
    if (callback) callback();
  });
}

