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
    // On est en mode Player, afficher "Editor Mode" pour indiquer qu'on peut revenir en mode Editor
    icon.textContent = "🛠️";
    label.textContent = "Editor Mode";
    btn.style.background = "#1a7f1a";
    if (toolbarTop) toolbarTop.style.display = 'none';
    document.getElementById("saveData").style.display = "inline-flex";
    
    // Cacher le bouton de changement de pièce en mode player
    const changeRoomBtn = document.getElementById('changeRoomBtn');
    if (changeRoomBtn) changeRoomBtn.style.display = 'none';
    
    isLassoMode = false;
    canvas.selection = false;
    canvas.discardActiveObject();
    canvas.getObjects().forEach(obj => { 
      if (obj !== backgroundImage) {
        // Les flèches doivent rester cliquables en mode player pour la navigation
        // mais sans être sélectionnables ou éditables
        if (obj.isArrow) {
          obj.set({ 
            selectable: false, 
            evented: true,
            hasControls: false,
            hasBorders: false,
            hoverCursor: 'pointer'
          });
        } else {
          obj.set({ selectable: false, evented: false });
        }
      }
    });
    resetZoomAndPan();
    canvas.defaultCursor = 'default';
    canvas.hoverCursor = 'default'; // Le curseur changera en pointer au survol des flèches
    
    console.log("🎮 Mode Player activé - Bordures masquées, édition désactivée, zoom réinitialisé, flèches cliquables");
  } else {
    // On est en mode Editor, afficher "Player Mode" pour indiquer qu'on peut basculer en mode Player
    icon.textContent = "🎮";
    label.textContent = "Player Mode";
    btn.style.background = "#3a3a3a";
    if (toolbarTop) toolbarTop.style.display = 'flex';
    document.getElementById("saveData").style.display = "inline-flex";
    
    // Afficher le bouton de changement de pièce en mode editor
    const changeRoomBtn = document.getElementById('changeRoomBtn');
    if (changeRoomBtn) changeRoomBtn.style.display = 'block';
    
    canvas.selection = true;
    canvas.getObjects().forEach(obj => { 
      if (obj !== backgroundImage) {
        obj.set({ selectable: true, evented: true });
        // Restaurer les contrôles pour les flèches
        if (obj.isArrow) {
          obj.set({
            hasControls: true,
            hasBorders: true,
            hoverCursor: 'move'
          });
        }
      }
    });
    
    canvas.defaultCursor = 'default';
    canvas.hoverCursor = 'move';
    
    console.log("🛠️ Mode Editor activé - Bordures visibles, édition activée");
  }
  
  toggleBordersVisibility();
};

