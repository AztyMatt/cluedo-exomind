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
  
  console.log(`✅ Item ${selectedItemId} associé au masque ${currentMaskForItemAssociation.maskData.dbId}`);
}

// Ajouter l'image de l'item au masque
function addItemImageToMask(maskObject, itemId) {
  console.log(`🎯 Ajout de l'image de l'item ${itemId} au masque ${maskObject.maskData.dbId}`);
  
  // Supprimer l'ancienne image d'item s'il y en a une
  removeExistingItemImage(maskObject);
  
  // Créer l'image de l'item
  const imageUrl = `/assets/img/items/${itemId}.png`;
  console.log(`🖼️ Tentative de chargement de l'image: ${imageUrl}`);
  
  fabric.Image.fromURL(imageUrl, (itemImg) => {
    console.log(`📸 Image de l'item ${itemId} chargée avec succès`);
    console.log(`📐 Dimensions originales de l'image:`, itemImg.width, 'x', itemImg.height);
    
    // Calculer la position et la taille pour centrer l'image sur le masque
    const maskBounds = maskObject.getBoundingRect();
    const itemSize = Math.min(maskBounds.width, maskBounds.height) * 1.5; // 150% de la taille du masque (encore plus grand)
    
    console.log(`📏 Masque bounds:`, maskBounds);
    console.log(`📏 Masque position:`, maskObject.left, maskObject.top);
    console.log(`📏 Taille item calculée:`, itemSize);
    console.log(`📐 Dimensions image originale:`, itemImg.width, 'x', itemImg.height);
    
    // Calculer les nouvelles dimensions
    const scaleX = itemSize / itemImg.width;
    const scaleY = itemSize / itemImg.height;
    
    console.log(`🔍 Scale calculé:`, scaleX, 'x', scaleY);
    
    // Utiliser la position du masque plutôt que ses bounds
    const centerX = maskObject.left + (maskObject.width * maskObject.scaleX) / 2;
    const centerY = maskObject.top + (maskObject.height * maskObject.scaleY) / 2;
    
    console.log(`🎯 Centre du masque:`, centerX, centerY);
    
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
    
    console.log(`📍 Position finale:`, itemImg.left, itemImg.top);
    console.log(`📏 Taille finale:`, itemImg.width * scaleX, 'x', itemImg.height * scaleY);
    
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
    
    console.log(`🎨 Z-index masque: ${maskZIndex}, Z-index item: ${itemZIndex}`);
    
    // Ajouter l'image au canvas
    canvas.add(itemImg);
    
    // Mettre l'image au premier plan par rapport au masque
    canvas.bringToFront(itemImg);
    
    // Forcer le rendu pour s'assurer que l'image est visible
    canvas.renderAll();
    
    // Vérifier que l'image est bien ajoutée
    console.log(`🔍 Objets sur le canvas:`, canvas.getObjects().length);
    console.log(`🎯 Image ajoutée avec ID:`, itemImg.id);
    
    // Sauvegarder la référence dans le masque
    maskObject.itemImage = itemImg;
    
    console.log(`✅ Image de l'item ${itemId} ajoutée au masque avec succès`);
  }, {
    crossOrigin: 'anonymous'
  });
  
  // Gestion d'erreur si l'image ne se charge pas
  setTimeout(() => {
    if (!maskObject.itemImage) {
      console.error(`❌ Échec du chargement de l'image ${imageUrl}`);
      console.log(`🔍 Vérifiez que le fichier existe: ${imageUrl}`);
    }
  }, 3000);
}

// Supprimer toutes les images d'items d'un masque spécifique
function removeAllItemImagesForMask(maskId) {
  console.log(`🗑️ Suppression de toutes les images d'items pour le masque ${maskId}`);
  
  canvas.getObjects().forEach(obj => {
    if (obj.itemData && obj.itemData.isItemImage && obj.itemData.maskId === maskId) {
      console.log(`🗑️ Suppression de l'image d'item ${obj.itemData.itemId} du masque ${maskId}`);
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
    console.log(`🗑️ Suppression de l'ancienne image d'item du masque ${maskObject.maskData.dbId}`);
    canvas.remove(maskObject.itemImage);
    maskObject.itemImage = null;
  }
  
  // Aussi supprimer toute image d'item orpheline
  canvas.getObjects().forEach(obj => {
    if (obj.itemData && obj.itemData.isItemImage && obj.itemData.maskId === maskObject.maskData.dbId) {
      console.log(`🗑️ Suppression d'une image d'item orpheline pour le masque ${maskObject.maskData.dbId}`);
      canvas.remove(obj);
    }
  });
}

// Sauvegarder l'association item-masque en base de données
function saveItemMaskAssociation(itemId, maskId) {
  console.log(`💾 Sauvegarde de l'association item ${itemId} -> masque ${maskId}`);
  
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
      console.log('✅ Association sauvegardée avec succès');
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
  console.log('🔍 Chargement des images d\'items pour les masques existants...');
  
  // Cette fonction sera appelée après le chargement des masques
  // Elle cherche tous les masques et charge leurs images d'items associées
  const masks = canvas.getObjects().filter(obj => obj.maskData && obj.maskData.isMask && obj.maskData.dbId);
  console.log(`📋 ${masks.length} masques trouvés`);
  
  masks.forEach((mask, index) => {
    console.log(`🎭 Masque ${index + 1}: ID ${mask.maskData.dbId}`);
    // Vérifier si ce masque a un item associé
    checkAndLoadItemForMask(mask);
  });
}

// Vérifier et charger l'item associé à un masque
function checkAndLoadItemForMask(maskObject) {
  console.log(`🔍 Vérification de l'item pour le masque ID: ${maskObject.maskData.dbId}`);
  
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
    console.log(`📡 Réponse pour masque ${maskObject.maskData.dbId}:`, data);
    if (data.success && data.item_id) {
      console.log(`✅ Item ${data.item_id} trouvé pour le masque ${maskObject.maskData.dbId}`);
      // Charger l'image de l'item
      addItemImageToMask(maskObject, data.item_id);
    } else {
      console.log(`ℹ️ Aucun item associé au masque ${maskObject.maskData.dbId}`);
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
  console.log('🧪 Test manuel du chargement des items...');
  loadExistingItemImages();
};

// Appel automatique après le chargement de la page
document.addEventListener('DOMContentLoaded', function() {
  console.log('📋 item-association.js chargé');
  
  // Attendre que les masques soient chargés (délai court)
  setTimeout(() => {
    console.log('🚀 Chargement des images d\'items...');
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
    console.log('🎨 Canvas prêt, chargement immédiat des images d\'items...');
    loadExistingItemImages();
  });
}
