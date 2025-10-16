// ========== INITIALISATION ==========

// Variables injectées depuis PHP (voir cluedo.php)
// - roomImages : liste des images dans /rooms
// - paperDataUrl : data URL de l'image papier.png

// Initialiser le sélecteur d'images
initRoomSelector();

// Afficher le bouton de changement de pièce au démarrage (mode editor par défaut)
const changeRoomBtn = document.getElementById('changeRoomBtn');
if (changeRoomBtn) changeRoomBtn.style.display = 'block';

