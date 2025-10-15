// ========== OUTILS Z-INDEX (PREMIER PLAN / ARRIÈRE PLAN) ==========

document.getElementById("bringForward").onclick = () => {
  const activeObject = canvas.getActiveObject();
  if (!activeObject) {
    console.log("❌ Aucun objet sélectionné");
    return;
  }
  
  if (activeObject === backgroundImage) {
    console.log("❌ Impossible de déplacer l'image de fond");
    return;
  }
  
  console.log("Ordre AVANT:", canvas.getObjects().indexOf(activeObject));
  
  activeObject.bringToFront();
  
  console.log("Ordre APRÈS:", canvas.getObjects().indexOf(activeObject));
  console.log("Nombre total d'objets:", canvas.getObjects().length);
  
  console.log("Ordre complet des objets:");
  canvas.getObjects().forEach((obj, idx) => {
    if (obj === backgroundImage) {
      console.log(`  ${idx}: Image de fond`);
    } else if (obj === activeObject) {
      console.log(`  ${idx}: >>> OBJET SÉLECTIONNÉ <<<`);
    } else {
      console.log(`  ${idx}: Autre objet`);
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
  
  console.log("✅ Objet mis au premier plan");
};

document.getElementById("sendBackward").onclick = () => {
  const activeObject = canvas.getActiveObject();
  if (!activeObject) {
    console.log("❌ Aucun objet sélectionné");
    return;
  }
  
  if (activeObject === backgroundImage) {
    console.log("❌ Impossible de déplacer l'image de fond");
    return;
  }
  
  console.log("Ordre AVANT:", canvas.getObjects().indexOf(activeObject));
  
  activeObject.sendToBack();
  
  if (backgroundImage) {
    backgroundImage.sendToBack();
  }
  
  console.log("Ordre APRÈS:", canvas.getObjects().indexOf(activeObject));
  console.log("Nombre total d'objets:", canvas.getObjects().length);
  
  console.log("Ordre complet des objets:");
  canvas.getObjects().forEach((obj, idx) => {
    if (obj === backgroundImage) {
      console.log(`  ${idx}: Image de fond`);
    } else if (obj === activeObject) {
      console.log(`  ${idx}: >>> OBJET SÉLECTIONNÉ <<<`);
    } else {
      console.log(`  ${idx}: Autre objet`);
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
  
  console.log("✅ Objet mis en arrière-plan");
};

