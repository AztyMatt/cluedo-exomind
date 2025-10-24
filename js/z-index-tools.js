// ========== OUTILS Z-INDEX (PREMIER PLAN / ARRIÈRE PLAN) ==========

document.getElementById("bringForward").onclick = function() {
  // Vérifier si le bouton est désactivé
  if (this.classList.contains('disabled')) return;
  
  const activeObject = canvas.getActiveObject();
  if (!activeObject) {
("❌ Aucun objet sélectionné");
    return;
  }
  
  if (activeObject === backgroundImage) {
("❌ Impossible de déplacer l'image de fond");
    return;
  }
  
("Ordre AVANT:", canvas.getObjects().indexOf(activeObject));
  
  activeObject.bringToFront();
  
("Ordre APRÈS:", canvas.getObjects().indexOf(activeObject));
("Nombre total d'objets:", canvas.getObjects().length);
  
("Ordre complet des objets:");
  canvas.getObjects().forEach((obj, idx) => {
    if (obj === backgroundImage) {
(`  ${idx}: Image de fond`);
    } else if (obj === activeObject) {
(`  ${idx}: >>> OBJET SÉLECTIONNÉ <<<`);
    } else {
(`  ${idx}: Autre objet`);
    }
  });
  
  canvas.getObjects().forEach(obj => {
    obj.setCoords();
  });
  
  canvas.discardActiveObject();
  canvas.setActiveObject(activeObject);
  canvas.renderAll();
  
  setTimeout(() => {
    canvas.renderAll();
  }, 10);
  
("✅ Objet mis au premier plan");
};

document.getElementById("sendBackward").onclick = function() {
  // Vérifier si le bouton est désactivé
  if (this.classList.contains('disabled')) return;
  
  const activeObject = canvas.getActiveObject();
  if (!activeObject) {
("❌ Aucun objet sélectionné");
    return;
  }
  
  if (activeObject === backgroundImage) {
("❌ Impossible de déplacer l'image de fond");
    return;
  }
  
("Ordre AVANT:", canvas.getObjects().indexOf(activeObject));
  
  activeObject.sendToBack();
  
  if (backgroundImage) {
    backgroundImage.sendToBack();
  }
  
("Ordre APRÈS:", canvas.getObjects().indexOf(activeObject));
("Nombre total d'objets:", canvas.getObjects().length);
  
("Ordre complet des objets:");
  canvas.getObjects().forEach((obj, idx) => {
    if (obj === backgroundImage) {
(`  ${idx}: Image de fond`);
    } else if (obj === activeObject) {
(`  ${idx}: >>> OBJET SÉLECTIONNÉ <<<`);
    } else {
(`  ${idx}: Autre objet`);
    }
  });
  
  canvas.getObjects().forEach(obj => {
    obj.setCoords();
  });
  
  canvas.discardActiveObject();
  canvas.setActiveObject(activeObject);
  canvas.renderAll();
  
  setTimeout(() => {
    canvas.renderAll();
  }, 10);
  
("✅ Objet mis en arrière-plan");
};

