<?php
// Connexion à la base de données
require_once __DIR__ . '/db-connection.php';
$dbConnection = getDBConnection();

// Fonction pour formater le nom : Prénom NOM
function formatUserName($firstname, $lastname) {
    // Formater : première lettre majuscule pour le prénom, tout en majuscules pour le nom
    $formattedFirstName = ucfirst(strtolower($firstname));
    $formattedLastName = strtoupper($lastname);
    
    return $formattedFirstName . ' ' . $formattedLastName;
}

// Récupérer tous les groupes avec leurs utilisateurs depuis la base de données
$teams = [];
if ($dbConnection) {
    try {
        $stmt = $dbConnection->prepare("SELECT id, name, pole_name, color, img_path FROM `groups` ORDER BY id ASC");
        $stmt->execute();
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Récupérer les utilisateurs pour chaque groupe
        $teamsWithUsers = [];
        foreach ($teams as $team) {
            $stmt = $dbConnection->prepare("SELECT firstname, lastname, username, email, has_activated FROM `users` WHERE group_id = ? ORDER BY lastname ASC, firstname ASC");
            $stmt->execute([$team['id']]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ajouter le nombre de papiers trouvés pour chaque utilisateur (simulation)
            foreach ($users as &$user) {
                $user['papers_found'] = rand(0, 5); // Simulation : 0 à 5 papiers par utilisateur
            }
            $team['users'] = $users;
            
            // Compter les papiers trouvés (simulation pour l'exemple) - valeur fixe basée sur l'ID
            $team['papers_found'] = ($team['id'] * 2) % 10;
            
            // Statut de l'énigme (simulation)
            $team['enigma_unlocked'] = $team['papers_found'] >= 5; // Énigme débloquée si au moins 5 papiers
            $team['enigma_solved'] = $team['papers_found'] >= 8; // Énigme résolue si au moins 8 papiers
            
            $teamsWithUsers[] = $team;
        }
        $teams = $teamsWithUsers;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des équipes: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cluedo - Les Équipes</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: url('assets/img/cluedo_background.webp');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: #eee;
            min-height: 100vh;
            padding: 40px 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .logos-container {
            text-align: center;
            margin-bottom: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
        }

        .game-description {
            text-align: center;
            color: #fff;
            font-size: 1.4rem;
            font-weight: bold;
            margin-bottom: 40px;
            padding: 0 20px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }

        .logo {
            width: auto;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
        }

        .logo:hover {
            transform: scale(1.05);
            transition: transform 0.3s ease;
        }

        .logo-exomind {
            height: 120px;
        }

        .logo-tak {
            height: 60px;
        }

        .buttons-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin: 30px auto;
            flex-wrap: wrap;
        }

        .game-button {
            padding: 15px 40px;
            font-size: 1.2rem;
            font-weight: bold;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .game-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        .game-button:active {
            transform: translateY(-1px);
        }

        .btn-rules {
            background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%);
            color: white;
        }

        .btn-rules:hover {
            background: linear-gradient(135deg, #f2c94c 0%, #f2994a 100%);
        }

        .btn-play {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .btn-play:hover {
            background: linear-gradient(135deg, #38ef7d 0%, #11998e 100%);
        }

        /* Styles pour la modale */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-content {
            background: linear-gradient(135deg, rgba(58, 58, 58, 0.95) 0%, rgba(30, 30, 30, 0.95) 100%);
            border-radius: 20px;
            padding: 40px;
            max-width: 900px;
            width: 90%;
            position: relative;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.1);
            animation: slideIn 0.3s ease;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            font-size: 24px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255, 77, 77, 0.8);
            transform: rotate(90deg);
        }

        .modal-title {
            font-size: 2rem;
            font-weight: bold;
            color: #f093fb;
            margin-bottom: 20px;
            text-align: center;
            text-shadow: 0 2px 10px rgba(240, 147, 251, 0.5);
        }

        .modal-text {
            font-size: 1.2rem;
            line-height: 1.8;
            color: #eee;
            text-align: left;
        }

        .day-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(240, 240, 250, 0.95) 100%);
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            z-index: 100;
            border: 2px solid rgba(150, 150, 150, 0.5);
            backdrop-filter: blur(10px);
            text-align: center;
        }

        .day-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2a2a2a;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 5px;
        }

        .day-objective {
            font-size: 1.1rem;
            color: #3a3a3a;
            font-weight: 600;
        }

        .teams-grid {
            display: flex;
            flex-direction: column;
            gap: 50px;
            padding: 20px;
            max-width: 1600px;
            margin: 0 auto;
        }

        .team-row {
            display: flex;
            gap: 30px;
        }

        .team-row:first-child,
        .team-row:last-child {
            justify-content: space-between;
            align-items: flex-start;
        }

        .team-card {
            background: rgba(58, 58, 58, 0.8);
            border-radius: 20px;
            padding: 0;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            border: 3px solid transparent;
            display: flex;
            flex-direction: column;
            width: 380px;
            flex-shrink: 0;
        }

        .team-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }

        .team-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 8px;
            background: var(--team-color);
            opacity: 0.8;
            z-index: 10;
        }

        .team-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, transparent 0%, transparent 40%, rgba(0,0,0,0.7) 70%, rgba(0,0,0,0.9) 100%);
            opacity: 0.8;
            pointer-events: none;
            z-index: 1;
        }

        .team-card-content {
            display: flex;
            width: 100%;
            height: calc(100% - 60px);
            gap: 0;
        }

        .team-left-column {
            width: 40%;
            display: flex;
            flex-direction: column;
            padding: 20px;
            background: #2a2a2a;
        }

        .team-image-container {
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-top: 0;
            margin-bottom: 0;
        }

        .team-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .team-info {
            text-align: center;
        }

        .team-right-column {
            width: 60%;
            display: flex;
            flex-direction: column;
            padding: 20px;
        }

        .team-scrollable {
            height: 400px;
            background: rgba(0, 0, 0, 0.8);
            border-radius: 15px;
            padding: 15px;
            overflow-y: auto;
            backdrop-filter: blur(10px);
            border: 2px solid var(--team-color);
        }

        .team-scrollable::-webkit-scrollbar {
            width: 6px;
        }

        .team-scrollable::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }

        .team-scrollable::-webkit-scrollbar-thumb {
            background: var(--team-color);
            border-radius: 3px;
        }

        .team-scrollable::-webkit-scrollbar-thumb:hover {
            background: var(--team-color);
            opacity: 0.8;
        }

        .user-item {
            margin-bottom: 12px;
            padding: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            transition: all 0.3s ease;
            position: relative;
            padding-left: 20px;
        }

        .user-item.active {
            background: rgba(255, 255, 255, 0.15);
        }

        .user-item.inactive {
            background: rgba(255, 255, 255, 0.05);
        }

        .user-status-icon {
            position: absolute;
            left: 5px;
            top: 50%;
            transform: translateY(-50%);
            width: 10px;
            height: 10px;
            animation: pulse 2s infinite;
            opacity: 1 !important;
            filter: none;
        }

        .user-item.active:hover .user-status-icon {
            animation: pulse 0.5s infinite;
            transform: translateY(-50%) scale(1.2);
        }

        .user-item.inactive:hover .user-status-icon {
            animation: pulse 0.5s infinite;
            transform: translateY(-50%) scale(1.2);
        }

        @keyframes pulse {
            0% {
                transform: translateY(-50%) scale(1);
                opacity: 1;
            }
            50% {
                transform: translateY(-50%) scale(1.1);
                opacity: 0.8;
            }
            100% {
                transform: translateY(-50%) scale(1);
                opacity: 1;
            }
        }

        .user-name {
            font-weight: bold;
            color: #fff;
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-papers-count {
            font-size: 0.75rem;
            background: var(--team-color);
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            /* Couleur de texte adaptative selon la luminosité */
            color: color-mix(in srgb, var(--team-color) 20%, black);
            filter: contrast(2) brightness(0.3);
        }

        /* Pour les couleurs claires, utiliser du texte foncé */
        .team-card[style*="#FFDB58"] .user-papers-count,
        .team-card[style*="#FFFFFF"] .user-papers-count,
        .team-card[style*="#FF69B4"] .user-papers-count {
            color: #333;
            filter: none;
        }

        /* Pour les couleurs foncées, utiliser du texte clair */
        .team-card[style*="#1E3A8A"] .user-papers-count,
        .team-card[style*="#6B8E23"] .user-papers-count,
        .team-card[style*="#8F00FF"] .user-papers-count {
            color: #fff;
            filter: none;
        }

        .team-name {
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--team-color);
            text-shadow: 0 3px 15px rgba(0, 0, 0, 0.9), 0 0 30px rgba(0, 0, 0, 0.7);
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.8));
        }

        .team-pole {
            text-align: center;
            font-size: 0.9rem;
            color: #fff;
            padding: 8px 15px;
            background: rgba(0, 0, 0, 0.9);
            border-radius: 10px;
            border-left: 4px solid var(--team-color);
            backdrop-filter: blur(10px);
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.8);
        }

        .team-status {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 15px 20px;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(10px);
            border-top: 2px solid var(--team-color);
            z-index: 3;
            display: flex;
            justify-content: space-around;
            align-items: center;
            gap: 10px;
        }

        .status-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            flex: 1;
        }

        .status-label {
            font-size: 0.75rem;
            color: #bbb;
            text-transform: uppercase;
            font-weight: 600;
        }

        .status-value {
            font-size: 1.1rem;
            font-weight: bold;
            color: #fff;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-success {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
        }

        .badge-danger {
            background: linear-gradient(135deg, #eb3349, #f45c43);
            color: white;
        }

        .badge-warning {
            background: linear-gradient(135deg, #f2994a, #f2c94c);
            color: #000;
        }

        .color-indicator {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--team-color);
            border: 3px solid #2a2a2a;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
            z-index: 10;
        }

        .no-teams {
            text-align: center;
            font-size: 1.5rem;
            color: #aaa;
            padding: 60px 20px;
        }

        @media (max-width: 1400px) {
            .team-row:first-child,
            .team-row:last-child {
                justify-content: center;
                flex-wrap: wrap;
            }
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 2rem;
            }

            .teams-grid {
                gap: 20px;
            }

            .team-row {
                flex-direction: column;
                align-items: center;
            }

            .team-card {
                width: 100%;
                max-width: 380px;
            }

            .team-image-container {
                height: 350px;
            }
        }
    </style>
</head>
<body>
    <!-- Indicateur du jour -->
    <div class="day-indicator">
        <div class="day-number">Jour 1</div>
        <div class="day-objective">🏛️ Scène du crime</div>
    </div>

    <div class="container">
        <div class="logos-container">
            <img src="assets/img/exomind_logo_blanc.png" alt="Exomind Logo" class="logo logo-exomind">
            <img src="assets/img/logo_tak.svg" alt="TAK Logo" class="logo logo-tak">
        </div>

        <div class="buttons-container">
            <button id="rulesBtn" class="game-button btn-rules">📖 Règles du jeu</button>
            <a href="index.php" class="game-button btn-play">🎮 Jouer</a>
        </div>

        <!-- Modale des règles -->
        <div id="rulesModal" class="modal-overlay">
            <div class="modal-content">
                <button class="modal-close" id="closeModal">&times;</button>
                <h2 class="modal-title">📖 Règles du jeu</h2>
                <div class="modal-text">
                    <p style="margin-bottom: 20px;">
                        Ce jeu du Cluedo vous est proposé par <b>Exomind</b> et <b>Tak</b>. Il y a au total <b>6 équipes</b>, chacune associée à un pôle d'Exomind et représentée par un personnage emblématique du Cluedo.
                    </p>
                    
                    <p style="margin-bottom: 20px;">
                        <b>🎯 Objectif :</b><br>
                        Chaque jour, les équipes doivent deviner un mot mystère :
                        <br>• <b>Jour 1</b> : la scène du crime 🏛️
                        <br>• <b>Jour 2</b> : l'arme du crime 🔪
                        <br>• <b>Jour 3</b> : l'auteur du crime 🎭
                    </p>
                    
                    <p style="margin-bottom: 20px;">
                        <b>🔍 Comment jouer :</b><br>
                        Pour découvrir le mot du jour, chaque équipe doit d'abord reconstituer une énigme qui a été déchirée en petits papiers. Ces papiers sont cachés dans les bureaux d'Exomind. Une fois l'énigme reconstituée, elle vous donnera la clé pour trouver la réponse !
                    </p>
                    
                    <p style="margin-bottom: 0;">
                        <b>🏆 Classement :</b><br>
                        La meilleure équipe sera celle qui trouve le plus rapidement les mots chaque jour. 
                        <br>Le meilleur joueur individuel sera celui qui collectera le plus rapidement ses papiers.
                    </p>
                </div>
            </div>
        </div>

        <?php if (empty($teams)): ?>
            <div class="no-teams">
                <p>Aucune équipe disponible pour le moment.</p>
            </div>
        <?php else: ?>
            <div class="teams-grid">
                <!-- Ligne 1 : 3 cartes espacées -->
                <div class="team-row">
                    <?php for ($i = 0; $i < 3 && $i < count($teams); $i++): 
                        $team = $teams[$i]; ?>
                        <div class="team-card" style="--team-color: <?= htmlspecialchars($team['color'] ?? '#888') ?>;">
                            <div class="color-indicator"></div>
                            
                            <div class="team-card-content">
                                <!-- Colonne de gauche : Image + Nom + Pôle -->
                                <div class="team-left-column">
                                    <h2 class="team-name"><?= htmlspecialchars($team['name']) ?></h2>
                                    
                                    <div class="team-image-container">
                                        <?php if (!empty($team['img_path']) && file_exists($team['img_path'])): ?>
                                            <img src="<?= htmlspecialchars($team['img_path']) ?>" 
                                                 alt="<?= htmlspecialchars($team['name']) ?>" 
                                                 class="team-image">
                                        <?php else: ?>
                                            <div style="font-size: 4rem; color: <?= htmlspecialchars($team['color'] ?? '#888') ?>;">
                                                🎭
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="team-pole"><?= htmlspecialchars($team['pole_name']) ?></div>
                                </div>

                                <!-- Colonne de droite : Liste des joueurs -->
                                <div class="team-right-column">
                                    <div class="team-scrollable">
                                        <?php if (!empty($team['users'])): ?>
                                            <?php foreach ($team['users'] as $user): ?>
                                                <div class="user-item <?= $user['has_activated'] ? 'active' : 'inactive' ?>">
                                                    <img src="assets/img/<?= $user['has_activated'] ? 'activated' : 'unactivated' ?>.svg" 
                                                         alt="<?= $user['has_activated'] ? 'Actif' : 'Inactif' ?>" 
                                                         class="user-status-icon">
                                                    <div class="user-name">
                                                        <span><?= htmlspecialchars(formatUserName($user['firstname'], $user['lastname'])) ?></span>
                                                        <span class="user-papers-count"><?= $user['papers_found'] ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="user-item">
                                                <div class="user-name">Aucun utilisateur</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Section de statut -->
                            <div class="team-status">
                                <div class="status-item">
                                    <span class="status-label">Papiers</span>
                                    <span class="status-value">📄 <?= $team['papers_found'] ?> / 10</span>
                                </div>
                                <div class="status-item">
                                    <span class="status-label">Énigme</span>
                                    <?php if ($team['enigma_unlocked']): ?>
                                        <span class="status-badge badge-success">🔓 Débloquée</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-danger">🔒 Verrouillée</span>
                                    <?php endif; ?>
                                </div>
                                <div class="status-item">
                                    <span class="status-label">Résolution</span>
                                    <?php if ($team['enigma_solved']): ?>
                                        <span class="status-badge badge-success">✅ Résolue</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-warning">⏳ En cours</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <!-- Ligne 2 : 3 cartes centrées -->
                <?php if (count($teams) > 3): ?>
                    <div class="team-row">
                        <?php for ($i = 3; $i < 6 && $i < count($teams); $i++): 
                            $team = $teams[$i]; ?>
                            <div class="team-card" style="--team-color: <?= htmlspecialchars($team['color'] ?? '#888') ?>;">
                                <div class="color-indicator"></div>
                                
                                <div class="team-card-content">
                                    <!-- Colonne de gauche : Image + Nom + Pôle -->
                                    <div class="team-left-column">
                                        <h2 class="team-name"><?= htmlspecialchars($team['name']) ?></h2>
                                        
                                        <div class="team-image-container">
                                            <?php if (!empty($team['img_path']) && file_exists($team['img_path'])): ?>
                                                <img src="<?= htmlspecialchars($team['img_path']) ?>" 
                                                     alt="<?= htmlspecialchars($team['name']) ?>" 
                                                     class="team-image">
                                            <?php else: ?>
                                                <div style="font-size: 4rem; color: <?= htmlspecialchars($team['color'] ?? '#888') ?>;">
                                                    🎭
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="team-pole"><?= htmlspecialchars($team['pole_name']) ?></div>
                                    </div>

                                    <!-- Colonne de droite : Liste des joueurs -->
                                    <div class="team-right-column">
                                        <div class="team-scrollable">
                                            <?php if (!empty($team['users'])): ?>
                                                <?php foreach ($team['users'] as $user): ?>
                                                    <div class="user-item <?= $user['has_activated'] ? 'active' : 'inactive' ?>">
                                                        <img src="assets/img/<?= $user['has_activated'] ? 'activated' : 'unactivated' ?>.svg" 
                                                             alt="<?= $user['has_activated'] ? 'Actif' : 'Inactif' ?>" 
                                                             class="user-status-icon">
                                                        <div class="user-name">
                                                            <span><?= htmlspecialchars(formatUserName($user['firstname'], $user['lastname'])) ?></span>
                                                            <span class="user-papers-count"><?= $user['papers_found'] ?></span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="user-item">
                                                    <div class="user-name">Aucun utilisateur</div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section de statut -->
                                <div class="team-status">
                                    <div class="status-item">
                                        <span class="status-label">Papiers</span>
                                        <span class="status-value">📄 <?= $team['papers_found'] ?> / 10</span>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-label">Énigme</span>
                                        <?php if ($team['enigma_unlocked']): ?>
                                            <span class="status-badge badge-success">🔓 Débloquée</span>
                                        <?php else: ?>
                                            <span class="status-badge badge-danger">🔒 Verrouillée</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-label">Résolution</span>
                                        <?php if ($team['enigma_solved']): ?>
                                            <span class="status-badge badge-success">✅ Résolue</span>
                                        <?php else: ?>
                                            <span class="status-badge badge-warning">⏳ En cours</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Gestion de la modale des règles
        const rulesBtn = document.getElementById('rulesBtn');
        const rulesModal = document.getElementById('rulesModal');
        const closeModal = document.getElementById('closeModal');
        const modalContent = document.querySelector('.modal-content');

        // Ouvrir la modale
        rulesBtn.addEventListener('click', () => {
            rulesModal.classList.add('active');
        });

        // Fermer avec le bouton X
        closeModal.addEventListener('click', () => {
            rulesModal.classList.remove('active');
        });

        // Fermer en cliquant en dehors de la modale
        rulesModal.addEventListener('click', (e) => {
            if (e.target === rulesModal) {
                rulesModal.classList.remove('active');
            }
        });

        // Fermer avec la touche Échap
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && rulesModal.classList.contains('active')) {
                rulesModal.classList.remove('active');
            }
        });
    </script>
</body>
</html>

