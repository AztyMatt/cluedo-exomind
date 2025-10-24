// ========== GESTION DE L'ASSOCIATION D'ITEMS AUX MASQUES ==========

let selectedItemId = null;
let currentMaskForItemAssociation = null;

// Générer la grille des items
function generateItemsGrid() {
  const itemsGrid = document.getElementById('itemsGrid');
  itemsGrid.innerHTML = '';
  
  // Créer 18 items (1 à 18)
  for (let i = 1; i <= 18; i++) {
    const itemDiv = document.createElement('div');
    itemDiv.className = 'item-card';
    itemDiv.dataset.itemId = i;
    
    itemDiv.style.cssText = `
      background: #3a3a3a;
      border: 2px solid #555;
      border-radius: 8px;
      padding: 10px;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s;
      min-height: 80px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    `;
    
    // Créer l'image de l'item
    const img = document.createElement('img');
    img.src = `/assets/img/items/${i}.png`;
    img.alt = `Item ${i}`;
    img.style.cssText = `
      width: 40px;
      height: 40px;
      object-fit: contain;
      margin-bottom: 5px;
    `;
    
    // Créer le label
    const label = document.createElement('div');
    label.textContent = `Item ${i}`;
    label.style.cssText = `
      font-size: 12px;
      color: #eee;
      font-weight: bold;
    `;
    
    itemDiv.appendChild(img);
    itemDiv.appendChild(label);
    
    // Ajouter l'événement de clic
    itemDiv.addEventListener('click', () => selectItem(i, itemDiv));
    
    itemsGrid.appendChild(itemDiv);
  }
}

// Sélectionner un item
function selectItem(itemId, element) {
  // Désélectionner tous les items
  document.querySelectorAll('.item-card').forEach(card => {
    card.style.background = '#3a3a3a';
    card.style.borderColor = '#555';
  });
  
  // Sélectionner l'item cliqué
  element.style.background = '#1a7f1a';
  element.style.borderColor = '#00ff00';
  
  selectedItemId = itemId;
  
  // Activer le bouton de confirmation
  const confirmBtn = document.getElementById('confirmItemBtn');
  confirmBtn.disabled = false;
  confirmBtn.style.opacity = '1';
}

// Ouvrir la modale d'association d'item
function openItemAssociationModal(maskObject) {
  currentMaskForItemAssociation = maskObject;
  selectedItemId = null;
  
  // Générer la grille des items
  generateItemsGrid();
  
  // Réinitialiser le bouton de confirmation
  const confirmBtn = document.getElementById('confirmItemBtn');
  confirmBtn.disabled = true;
  confirmBtn.style.opacity = '0.5';
  
  // Afficher la modale
  const modal = document.getElementById('itemAssociationModal');
  modal.style.display = 'flex';
}

// Fermer la modale d'association d'item
function closeItemAssociationModal() {
  const modal = document.getElementById('itemAssociationModal');
  modal.style.display = 'none';
  currentMaskForItemAssociation = null;
  selectedItemId = null;
}

// Confirmer l'association de l'item
function confirmItemAssociation() {
  if (!selectedItemId || !currentMaskForItemAssociation) {
    alert('Veuillez sélectionner un item');
    return;
  }
  
  // Sauvegarder l'association en base de données
  saveItemMaskAssociation(selectedItemId, currentMaskForItemAssociation.maskData.dbId);
  
  // Ajouter l'image de l'item au masque
  addItemImageToMask(currentMaskForItemAssociation, selectedItemId);
  
  // Fermer la modale
  closeItemAssociationModal();
  
}

// Ajouter l'image de l'item au masque
function addItemImageToMask(maskObject, itemId) {
  
  // Supprimer l'ancienne image d'item s'il y en a une
  removeExistingItemImage(maskObject);
  
  // Créer l'image de l'item
  const imageUrl = `/assets/img/items/${itemId}.png`;
  
  fabric.Image.fromURL(imageUrl, (itemImg) => {
    
    // Calculer la position et la taille pour centrer l'image sur le masque
    const maskBounds = maskObject.getBoundingRect();
    const itemSize = Math.min(maskBounds.width, maskBounds.height) * 1.5; // 150% de la taille du masque (encore plus grand)
    
    
    // Calculer les nouvelles dimensions
    const scaleX = itemSize / itemImg.width;
    const scaleY = itemSize / itemImg.height;
    
    
    // Utiliser la position du masque plutôt que ses bounds
    const centerX = maskObject.left + (maskObject.width * maskObject.scaleX) / 2;
    const centerY = maskObject.top + (maskObject.height * maskObject.scaleY) / 2;
    
    
    itemImg.set({
      left: centerX - (itemSize / 2),
      top: centerY - (itemSize / 2),
      scaleX: scaleX,
      scaleY: scaleY,
      selectable: false,
      evented: false,
      originX: 'left',
      originY: 'top',
      opacity: 1.0, // Opacité à 100% pour être sûr de voir l'image
      // Ajouter un effet de brillance subtil
      shadow: new fabric.Shadow({
        color: 'rgba(255, 255, 255, 0.8)',
        blur: 20,
        offsetX: 5,
        offsetY: 5
      })
    });
    
    
    // Calculer le z-index pour être au-dessus du masque
    const maskZIndex = maskObject.maskData.zIndex || 0;
    const itemZIndex = maskZIndex + 1;
    
    // Marquer cette image comme étant l'item du masque
    itemImg.itemData = {
      isItemImage: true,
      itemId: itemId,
      maskId: maskObject.maskData.dbId
    };
    
    itemImg.set({
      zIndex: itemZIndex
    });
    
    
    // Ajouter l'image au canvas
    canvas.add(itemImg);
    
    // Mettre l'image au premier plan par rapport au masque
    canvas.bringToFront(itemImg);
    
    // Forcer le rendu pour s'assurer que l'image est visible
    canvas.renderAll();
    
    // Vérifier que l'image est bien ajoutée
    
    // Sauvegarder la référence dans le masque
    maskObject.itemImage = itemImg;
    
  }, {
    crossOrigin: 'anonymous'
  });
  
  // Gestion d'erreur si l'image ne se charge pas
  setTimeout(() => {
    if (!maskObject.itemImage) {
    }
  }, 3000);
}

// Supprimer toutes les images d'items d'un masque spécifique
function removeAllItemImagesForMask(maskId) {
  
  canvas.getObjects().forEach(obj => {
    if (obj.itemData && obj.itemData.isItemImage && obj.itemData.maskId === maskId) {
      canvas.remove(obj);
    }
  });
  
  // Aussi nettoyer les références dans les masques
  canvas.getObjects().forEach(obj => {
    if (obj.maskData && obj.maskData.isMask && obj.maskData.dbId === maskId) {
      obj.itemImage = null;
    }
  });
}

// Supprimer l'ancienne image d'item s'il y en a une
function removeExistingItemImage(maskObject) {
  if (maskObject.itemImage) {
    canvas.remove(maskObject.itemImage);
    maskObject.itemImage = null;
  }
  
  // Aussi supprimer toute image d'item orpheline
  canvas.getObjects().forEach(obj => {
    if (obj.itemData && obj.itemData.isItemImage && obj.itemData.maskId === maskObject.maskData.dbId) {
      canvas.remove(obj);
    }
  });
}

// Sauvegarder l'association item-masque en base de données
function saveItemMaskAssociation(itemId, maskId) {
  
  // Supprimer visuellement toutes les images d'items de ce masque
  removeAllItemImagesForMask(maskId);
  
  // Créer un objet pour la sauvegarde
  const associationData = {
    action: 'associate_item_mask',
    item_id: itemId,
    mask_id: maskId
  };
  
  // Envoyer la requête au serveur
  fetch(window.location.href, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `action=associate_item_mask&item_id=${itemId}&mask_id=${maskId}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Optionnel : afficher un message de succès
      showSuccessMessage('Item associé au masque avec succès !');
    } else {
      console.error('❌ Erreur lors de la sauvegarde:', data.message);
      alert('Erreur lors de la sauvegarde: ' + data.message);
    }
  })
  .catch(error => {
    console.error('❌ Erreur réseau:', error);
    alert('Erreur réseau lors de la sauvegarde');
  });
}

// Afficher un message de succès temporaire
function showSuccessMessage(message) {
  const successDiv = document.createElement('div');
  successDiv.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: #1a7f1a;
    color: white;
    padding: 15px 20px;
    border-radius: 8px;
    font-size: 16px;
    z-index: 10000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
  `;
  successDiv.textContent = message;
  document.body.appendChild(successDiv);
  
  // Supprimer le message après 3 secondes
  setTimeout(() => {
    if (successDiv.parentNode) {
      successDiv.parentNode.removeChild(successDiv);
    }
  }, 3000);
}

// Événements des boutons de la modale
document.addEventListener('DOMContentLoaded', function() {
  // Bouton Annuler
  document.getElementById('cancelItemBtn').addEventListener('click', closeItemAssociationModal);
  
  // Bouton Confirmer
  document.getElementById('confirmItemBtn').addEventListener('click', confirmItemAssociation);
  
  // Fermer la modale en cliquant à l'extérieur
  document.getElementById('itemAssociationModal').addEventListener('click', function(e) {
    if (e.target === this) {
      closeItemAssociationModal();
    }
  });
  
  // Bouton "Associer Item" dans la toolbar
  document.getElementById('associateItem').addEventListener('click', function() {
    const activeObject = canvas.getActiveObject();
    
    if (!activeObject || !activeObject.maskData || !activeObject.maskData.isMask) {
      alert('Veuillez d\'abord sélectionner un masque');
      return;
    }
    
    // Vérifier si ce masque a déjà un item associé
    if (activeObject.itemImage) {
      const confirmChange = confirm('Ce masque a déjà un item associé. Voulez-vous le remplacer ?');
      if (!confirmChange) {
        return;
      }
    }
    
    openItemAssociationModal(activeObject);
  });
});

// Charger les images d'items pour les masques existants
function loadExistingItemImages() {
  
  // Cette fonction sera appelée après le chargement des masques
  // Elle cherche tous les masques et charge leurs images d'items associées
  const masks = canvas.getObjects().filter(obj => obj.maskData && obj.maskData.isMask && obj.maskData.dbId);
  
  masks.forEach((mask, index) => {
    // Vérifier si ce masque a un item associé
    checkAndLoadItemForMask(mask);
  });
}

// Vérifier et charger l'item associé à un masque
function checkAndLoadItemForMask(maskObject) {
  
  // Faire une requête pour vérifier si ce masque a un item associé
  fetch(window.location.href, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `action=get_item_for_mask&mask_id=${maskObject.maskData.dbId}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success && data.item_id) {
      // Charger l'image de l'item
      addItemImageToMask(maskObject, data.item_id);
    }
  })
  .catch(error => {
    console.error(`❌ Erreur lors de la vérification du masque ${maskObject.maskData.dbId}:`, error);
  });
}

// La gestion de l'état du bouton "Associer Item" est maintenant intégrée dans button-state.js

// Fonction globale pour être appelée depuis save-load.js
window.loadExistingItemImages = loadExistingItemImages;

// Fonction de test manuel (accessible depuis la console)
window.testLoadItems = function() {
  loadExistingItemImages();
};

// Appel automatique après le chargement de la page
document.addEventListener('DOMContentLoaded', function() {
  
  // Attendre que les masques soient chargés (délai court)
  setTimeout(() => {
    if (typeof loadExistingItemImages === 'function') {
      loadExistingItemImages();
    } else {
      console.error('❌ loadExistingItemImages n\'est pas définie');
    }
  }, 500); // Délai court de 500ms
});

// Appel alternatif quand le canvas est prêt
if (typeof canvas !== 'undefined') {
  canvas.on('canvas:ready', function() {
    loadExistingItemImages();
  });
}
