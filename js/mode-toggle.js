// ========== TOGGLE EDITOR/PLAYER MODE ==========

function toggleBordersVisibility() {
  canvas.getObjects().forEach(obj => {
    if (obj.maskData || obj._objects) {
      obj.set({
        opacity: isPlayerMode ? 1 : 1,
        visible: true
      });
      
      if (obj._objects && obj._objects.length > 1) {
        obj._objects.forEach((subObj, idx) => {
          if (idx === 1) {
            subObj.set({ visible: !isPlayerMode });
          }
        });
      }
    }
  });
  canvas.renderAll();
}

document.getElementById("modeToggle").onclick = () => {
  isPlayerMode = !isPlayerMode;
  const btn = document.getElementById("modeToggle");
  const icon = btn.querySelector('.icon');
  const label = btn.querySelector('.btn-label');
  const toolbarTop = document.querySelector('#toolbar .toolbar-top');
  
  if (isPlayerMode) {
    // Mode Player
    icon.textContent = "🎮";
    label.textContent = "Player Mode";
    btn.style.background = "#1a7f1a";
    if (toolbarTop) toolbarTop.style.display = 'none';
    document.getElementById("saveData").style.display = "inline-flex";
    isLassoMode = false;
    isPanMode = false;
    canvas.selection = false;
    canvas.discardActiveObject();
    canvas.getObjects().forEach(obj => { 
      if (obj !== backgroundImage) obj.set({ selectable: false, evented: false }); 
    });
    resetZoomAndPan();
    canvas.defaultCursor = 'default';
    canvas.hoverCursor = 'default';
    
    console.log("🎮 Mode Player activé - Bordures masquées, édition désactivée, zoom réinitialisé");
  } else {
    // Mode Editor
    icon.textContent = "🛠️";
    label.textContent = "Editor Mode";
    btn.style.background = "#3a3a3a";
    if (toolbarTop) toolbarTop.style.display = 'flex';
    document.getElementById("saveData").style.display = "inline-flex";
    
    canvas.selection = true;
    canvas.getObjects().forEach(obj => { 
      if (obj !== backgroundImage) obj.set({ selectable: true, evented: true }); 
    });
    
    canvas.defaultCursor = isPanMode ? 'grab' : 'default';
    canvas.hoverCursor = isPanMode ? 'grab' : 'move';
    
    console.log("🛠️ Mode Editor activé - Bordures visibles, édition activée");
  }
  
  toggleBordersVisibility();
};

