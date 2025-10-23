// ========== GESTION DE L'ÉTAT DES BOUTONS ==========

function updateButtonStates() {
  const activeObject = canvas.getActiveObject();
  const hasSelection = activeObject && activeObject !== backgroundImage;
  const isMaskSelected = hasSelection && activeObject.maskData && activeObject.maskData.isMask;
  const isPaperSelected = hasSelection && activeObject._objects && !activeObject.maskData;
  
  // Bouton "Modifier le tracé" : disponible uniquement si masque sélectionné ET pas en mode lasso
  const editMaskBtn = document.getElementById("editMask");
  if ((isMaskSelected && !isLassoMode) || isEditingMode) {
    // Disponible si masque sélectionné OU si on est en mode édition (pour servir de "Valider")
    editMaskBtn.classList.remove('disabled');
  } else {
    editMaskBtn.classList.add('disabled');
  }
  
  // Vérifier si une flèche est sélectionnée
  const isArrowSelected = hasSelection && activeObject.isArrow;
  
  // Bouton "Associer Item" : disponible uniquement si masque sélectionné ET pas en mode lasso/édition
  const associateItemBtn = document.getElementById("associateItem");
  if (isMaskSelected && !isLassoMode && !isEditingMode) {
    associateItemBtn.classList.remove('disabled');
  } else {
    associateItemBtn.classList.add('disabled');
  }
  
  // Boutons "Premier plan" et "Arrière plan" : disponibles si masque, papier ou flèche sélectionné
  const bringForwardBtn = document.getElementById("bringForward");
  const sendBackwardBtn = document.getElementById("sendBackward");
  if ((isMaskSelected || isPaperSelected || isArrowSelected) && !isLassoMode) {
    bringForwardBtn.classList.remove('disabled');
    sendBackwardBtn.classList.remove('disabled');
  } else {
    bringForwardBtn.classList.add('disabled');
    sendBackwardBtn.classList.add('disabled');
  }
  
  // En mode Lasso (traçage) : désactiver tous les boutons sauf Sauvegarder et Mode Toggle
  const addPaperBtn = document.getElementById("addPaper");
  const addArrowBtn = document.getElementById("addArrow");
  const toggleLassoBtn = document.getElementById("toggleLasso");
  
  if (isLassoMode && !isEditingMode) {
    // Désactiver les boutons en mode Lasso (traçage)
    addPaperBtn.classList.add('disabled');
    addArrowBtn.classList.add('disabled');
    editMaskBtn.classList.add('disabled');
    associateItemBtn.classList.add('disabled');
    bringForwardBtn.classList.add('disabled');
    sendBackwardBtn.classList.add('disabled');
  } else if (isEditingMode) {
    // En mode édition : désactiver tout sauf "Modifier le tracé" (qui sert à sortir)
    addPaperBtn.classList.add('disabled');
    addArrowBtn.classList.add('disabled');
    toggleLassoBtn.classList.add('disabled');
    associateItemBtn.classList.add('disabled');
    bringForwardBtn.classList.add('disabled');
    sendBackwardBtn.classList.add('disabled');
    // Le bouton editMask reste actif pour servir de "Valider"
    editMaskBtn.classList.remove('disabled');
  } else if (!isLassoMode) {
    // Réactiver les boutons en mode normal (sauf ceux qui dépendent de la sélection)
    addPaperBtn.classList.remove('disabled');
    addArrowBtn.classList.remove('disabled');
    toggleLassoBtn.classList.remove('disabled');
    // editMask, associateItem, bringForward et sendBackward gérés ci-dessus selon la sélection
  }
}

// Mettre à jour l'état des boutons lors de la sélection
canvas.on('selection:created', updateButtonStates);
canvas.on('selection:updated', updateButtonStates);
canvas.on('selection:cleared', updateButtonStates);

// Mettre à jour au chargement initial
setTimeout(updateButtonStates, 100);

