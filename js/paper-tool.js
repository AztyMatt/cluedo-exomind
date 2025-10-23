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
  canvas.selection = true;
  canvas.defaultCursor = 'default';
  canvas.hoverCursor = 'move';
  document.getElementById("addPaper").style.background = "#3a3a3a";
  document.getElementById("addPaperDore").style.background = "#3a3a3a";
  canvas.requestRenderAll();
}

function finalizePaperPlacement(opt) {
  if (!isPlacingPaper || !paperPreviewSize) return;
  const p = canvas.getPointer(opt.e);
  // Position du centre du papier
  const paperCenterX = p.x;
  const paperCenterY = p.y;
  
  removePaperPlaceholder();
  if (paperPreviewGroup) {
    canvas.remove(paperPreviewGroup);
    paperPreviewGroup = null;
  }
  isPlacingPaper = false;
  const savedScale = paperPreviewSize.scale;
  const savedW = paperPreviewSize.w;
  const savedH = paperPreviewSize.h;
  const paperType = paperPreviewSize.paperType || 0; // 0 = blanc, 1 = doré
  paperPreviewSize = null;
  canvas.skipTargetFind = false;
  document.getElementById("addPaper").style.background = "#3a3a3a";
  document.getElementById("addPaperDore").style.background = "#3a3a3a";
  
  // Calculer le z-index pour le nouveau paper
  let newZIndex = 0;
  canvas.getObjects().forEach(obj => {
    if (obj !== backgroundImage) newZIndex++;
  });
  
  // Choisir l'image selon le type de papier
  const paperImageUrl = paperType === 1 ? paperDoreDataUrl : paperDataUrl;
  
  fabric.Image.fromURL(paperImageUrl, (paperImg) => {
    paperImg.set({
      left: 0,
      top: 0,
      scaleX: savedScale,
      scaleY: savedScale,
      selectable: false,
      evented: false,
      originX: 'center',
      originY: 'center'
    });
    const paperBorder = new fabric.Rect({
      left: 0,
      top: 0,
      width: savedW,
      height: savedH,
      fill: 'transparent',
      stroke: '#00ffff',
      strokeWidth: 3,
      strokeDashArray: [8, 4],
      selectable: false,
      evented: false,
      originX: 'center',
      originY: 'center',
      visible: !isPlayerMode
    });
    const paperGroup = new fabric.Group([paperImg, paperBorder], {
      left: paperCenterX,
      top: paperCenterY,
      originX: 'center',
      originY: 'center',
      selectable: true,
      evented: true,
      hasControls: true,
      hasBorders: false,
      cornerColor: 'cyan',
      cornerStyle: 'circle',
      subTargetCheck: false,
      zIndex: newZIndex, // Attribuer le z-index au nouveau paper
      paperType: paperType // Stocker le type de papier
    });
    canvas.add(paperGroup);
    canvas.selection = true;
    canvas.setActiveObject(paperGroup);
    canvas.defaultCursor = 'move';
    canvas.hoverCursor = 'move';
    canvas.requestRenderAll();
    console.log("✅ Papier " + (paperType === 1 ? "doré" : "blanc") + " ajouté !");
  });
}

// Bouton Ajouter Papier Blanc
document.getElementById("addPaper").onclick = function() {
  // Vérifier si le bouton est désactivé
  if (this.classList.contains('disabled')) return;
  
  if (!paperDataUrl) {
    alert("L'image papier.png n'a été trouvée !");
    return;
  }
  if (isPlacingPaper) return;
  isPlacingPaper = true;
  document.getElementById("addPaper").style.background = "#1a7f1a";
  document.getElementById("addPaperDore").style.background = "#3a3a3a";
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
    paperPreviewSize = { w: wWorld, h: hWorld, scale: paperPlaceholderScale, paperType: 0 };
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
  console.log("👆 Placez le papier blanc avec un clic.");
};

// Bouton Ajouter Papier Doré
document.getElementById("addPaperDore").onclick = function() {
  // Vérifier si le bouton est désactivé
  if (this.classList.contains('disabled')) return;
  
  if (!paperDoreDataUrl) {
    alert("L'image papier_dore.png n'a été trouvée !");
    return;
  }
  if (isPlacingPaper) return;
  isPlacingPaper = true;
  document.getElementById("addPaperDore").style.background = "#1a7f1a";
  document.getElementById("addPaper").style.background = "#3a3a3a";
  canvas.discardActiveObject();
  canvas.selection = false;
  canvas.skipTargetFind = true;
  canvas.defaultCursor = 'crosshair';
  canvas.hoverCursor = 'crosshair';
  
  const img = new Image();
  img.src = paperDoreDataUrl;
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
    paperPreviewSize = { w: wWorld, h: hWorld, scale: paperPlaceholderScale, paperType: 1 };
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
  console.log("👆 Placez le papier doré avec un clic.");
};

// Fonction pour recréer un papier (utilisée au chargement)
function recreatePaper(paperData, callback) {
  const paperType = paperData.paperType || 0;
  const paperImageUrl = paperType === 1 ? paperDoreDataUrl : paperDataUrl;
  
  if (!paperImageUrl) {
    if (callback) callback();
    return;
  }
  
  fabric.Image.fromURL(paperImageUrl, (paperImg) => {
    paperImg.set({
      left: 0,
      top: 0,
      scaleX: 0.25,
      scaleY: 0.25,
      selectable: false,
      evented: false,
      originX: 'center',
      originY: 'center'
    });
    
    const paperWidth = paperImg.width * 0.25;
    const paperHeight = paperImg.height * 0.25;
    
    const paperBorder = new fabric.Rect({
      left: 0,
      top: 0,
      width: paperWidth,
      height: paperHeight,
      fill: 'transparent',
      stroke: '#00ffff',
      strokeWidth: 3,
      strokeDashArray: [8, 4],
      selectable: false,
      evented: false,
      originX: 'center',
      originY: 'center',
      visible: !isPlayerMode
    });
    
    const paperGroup = new fabric.Group([paperImg, paperBorder], {
      left: paperData.left,
      top: paperData.top,
      originX: 'center',
      originY: 'center',
      scaleX: paperData.scaleX || 1,
      scaleY: paperData.scaleY || 1,
      angle: paperData.angle || 0,
      selectable: true,
      evented: true,
      hasControls: true,
      hasBorders: false,
      cornerColor: 'cyan',
      cornerStyle: 'circle',
      subTargetCheck: false,
      dbId: paperData.id || null, // Conserver l'ID de la BDD
      zIndex: paperData.zIndex || 0, // Conserver le z-index de la BDD
      paperType: paperType // Conserver le type de papier
    });
    
    canvas.add(paperGroup);
    canvas.renderAll();
    if (callback) callback();
  });
}

