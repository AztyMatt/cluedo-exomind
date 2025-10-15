// ========== SÉLECTEUR D'IMAGES ET IMAGE DE FOND ==========

function pathToKey(p) {
  const base = (p || '').split('/').pop() || '';
  return base.includes('.') ? base.substring(0, base.lastIndexOf('.')) : base;
}

function initRoomSelector() {
  const sel = document.getElementById('roomSelector');
  if (!sel) return;
  sel.innerHTML = '';
  if (!Array.isArray(roomImages) || roomImages.length === 0) {
    const opt = document.createElement('option');
    opt.textContent = 'Aucune image dans /rooms';
    opt.disabled = true;
    opt.selected = true;
    sel.appendChild(opt);
    return;
  }
  roomImages.forEach((path) => {
    const opt = document.createElement('option');
    opt.value = path;
    opt.textContent = path.split('/').pop();
    sel.appendChild(opt);
  });
  const defaultPath = 'rooms/P1080918.JPG';
  sel.value = roomImages.includes(defaultPath) ? defaultPath : roomImages[0];
  setBackgroundImage(sel.value);
  sel.addEventListener('change', (e) => setBackgroundImage(e.target.value));
}

function setBackgroundImage(src) {
  if (!src) return;
  currentBackgroundKey = pathToKey(src);
  canvas.getObjects().slice().forEach(o => canvas.remove(o));
  fabric.Image.fromURL(
    src,
    function (img) {
      backgroundImage = img;
      backgroundImage.set({
        left: 0,
        top: 0,
        scaleX: 1,
        scaleY: 1,
        originX: 'left',
        originY: 'top',
        selectable: false,
        evented: false
      });
      canvas.add(backgroundImage);
      canvas.sendToBack(backgroundImage);
      applyBaseViewport();
      isAtBaseZoom = true;
      canvas.requestRenderAll();
      console.log('✅ Image de fond remplacée:', src, 'clé:', currentBackgroundKey);
      loadFromServer();
    },
    { crossOrigin: 'anonymous' }
  );
}

