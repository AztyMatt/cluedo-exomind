# 🎯 Cluedo - Application de navigation interactive

Application web pour créer et naviguer dans un environnement de photos interconnectées avec des zones masquées, des papiers et des flèches de navigation.

## 🚀 Fonctionnalités

- **Mode Éditeur** : Créez et modifiez des masques, papiers et flèches
- **Mode Joueur** : Naviguez dans l'environnement créé
- **Sauvegarde automatique** : Toutes les modifications sont sauvegardées automatiquement
- **Base de données MySQL** : Stockage persistant de toutes les données

## ⌨️ Commandes Clavier

### 🔧 Mode Placement d'Objets

#### Placement de Papier
- **Échap** : Annuler le placement du papier en cours

#### Placement de Flèche
- **Échap** : Annuler le placement de la flèche en cours
- **← Gauche** : Rotation de -15° de la flèche
- **→ Droite** ou **R** : Rotation de +15° de la flèche

### ✏️ Mode Lasso (Création de Masques)

- **Échap** : 
  - En mode traçage : Annuler le tracé en cours
  - En mode édition : Valider les modifications et créer le masque
- **Double-clic** : Fermer le polygone et créer le masque automatiquement
- **Clic près du premier point** : Fermer le polygone et créer le masque

### 🎯 Manipulation de Flèches (Mode Éditeur)

- **C** : Changer la destination de la flèche sélectionnée
- **X** : Basculer le mode déplacement libre
  - **Premier appui sur X** : Active le mode libre
    - La flèche peut être déplacée librement verticalement
    - L'état est **sauvegardé automatiquement**
    - La flèche reste en mode libre même après désélection
  - **Deuxième appui sur X** : Désactive le mode libre
    - La flèche revient sur la ligne contrainte
    - L'état est **sauvegardé automatiquement**
    - La flèche reste contrainte jusqu'au prochain appui sur X

### 📦 Manipulation d'Objets Sélectionnés

#### Déplacement
- **↑ Haut** : Déplacer vers le haut (1 pixel)
- **↓ Bas** : Déplacer vers le bas (1 pixel)
- **← Gauche** : Déplacer vers la gauche (1 pixel)
- **→ Droite** : Déplacer vers la droite (1 pixel)
- **Shift + Flèches** : Déplacement rapide (10 pixels)

#### Suppression
- **Delete** ou **Backspace** : Supprimer l'objet sélectionné

### 🎮 Navigation (Mode Joueur)

- **Clic simple sur flèche** : Naviguer vers la photo de destination

### 🎮 Navigation (Mode Éditeur)

- **Shift + Clic sur flèche** : Naviguer vers la photo de destination

## 🖱️ Interactions Souris

### Mode Éditeur

- **Clic gauche** : Sélectionner un objet
- **Clic + Glisser** : Déplacer un objet sélectionné
- **Molette** : Zoom avant/arrière
- **Alt + Glisser** : Pan (déplacer la vue)
- **Clic sur poignées de rotation** : Pivoter l'objet

### Mode Lasso

- **Clic** : Placer un point du polygone
- **Glisser un point** : Déplacer un sommet (en mode édition)
- **Glisser une poignée** : Ajuster la courbe d'un segment (en mode édition)

### Mode Placement d'Objets

- **Déplacement souris** : Prévisualiser la position
- **Clic** : Confirmer le placement

## 🔄 Auto-Pan (Mode Lasso)

Pendant le tracé ou l'édition d'un masque, approchez votre souris des bords de l'écran pour faire défiler automatiquement la vue.

## 🎨 Boutons de l'Interface

### Barre d'Outils Principale

- **📄 Ajouter Papier** : Active le mode placement de papier
- **➡️ Ajouter Flèche** : Active le mode placement de flèche
- **🖊️ Mode Lasso** : Active/Désactive le mode tracé de masque
- **✏️ Modifier le tracé** : Éditer un masque existant sélectionné
- **⬆️ Premier plan** : Amener l'objet sélectionné au premier plan
- **⬇️ Arrière plan** : Envoyer l'objet sélectionné à l'arrière plan

### Barre d'Outils Inférieure

- **🎮 Player Mode / Editor Mode** : Basculer entre mode joueur et éditeur
- **📷 Changer de pièce** : Sélectionner une autre photo de fond (mode éditeur uniquement)

## 💾 Sauvegarde

**Automatique** : Toutes les modifications sont sauvegardées automatiquement après 1 seconde d'inactivité.

Un indicateur 💾 apparaît brièvement en haut à gauche lors de la sauvegarde.

## 🗄️ Structure de la Base de Données

### Tables Principales

- **photos** : Liste des photos/pièces
- **papers** : Papiers placés sur les photos
- **masks** : Zones masquées avec tracés personnalisés
- **arrows** : Flèches de navigation entre photos
  - `free_placement` : Indique si la flèche est en mode libre (true) ou contrainte (false)

### Tables Utilisateurs (futurs)

- **groups** : Groupes d'utilisateurs
- **users** : Utilisateurs du système

## 🛠️ Installation

1. Configurer la base de données MySQL
2. Importer le schéma avec `init.sql`
3. Configurer la connexion dans `db-connection.php`
4. Placer les images dans le dossier `rooms/`
5. Lancer avec Docker Compose : `docker-compose up`

## 📝 Notes Techniques

### Contraintes de Placement des Flèches

- **Position verticale par défaut** : Les flèches sont contraintes à 200px du bas de l'image
- **Position horizontale** : Contraintes entre 200px des bords gauche et droit
- **Mode libre** : Activable avec la touche **X** pour un placement sans contraintes

### Z-Index

- **Flèches** : Z-index fixe de 1000 (toujours au-dessus)
- **Papiers et Masques** : Z-index basé sur l'ordre de création

### Formats d'Images Supportés

JPG, JPEG, PNG, GIF, WebP

---

**Version** : 1.0  
**Auteur** : Projet Cluedo  
**Dernière mise à jour** : Octobre 2024

