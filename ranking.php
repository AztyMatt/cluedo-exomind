<?php
// Page de classement par équipes avec rafraîchissement automatique via AJAX
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cluedo - Classement</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: url('assets/img/background.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: #eee;
            min-height: 100vh;
            padding: 20px 20px 40px 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
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
            max-width: 400px;
            width: 100%;
            height: auto;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
            display: block;
            margin: 0 auto 20px auto;
        }

        .logo:hover {
            transform: scale(1.05);
            transition: transform 0.3s ease;
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
            background: #ffdf29;
            color: #073545;
            padding: 18px 50px;
            font-size: 1.3rem;
        }

        .btn-rules:hover {
            background: #f4d03f;
        }

        .btn-ranking {
            background: #ff6b35;
            color: white;
            padding: 10px 25px;
            font-size: 0.9rem;
        }

        .btn-ranking:hover {
            background: #e55a2b;
        }

        .btn-individual {
            background: #4CAF50;
            color: white;
            padding: 10px 25px;
            font-size: 0.9rem;
        }

        .btn-individual:hover {
            background: #45a049;
        }

        .ranking-buttons {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .ranking-buttons-fixed {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .ranking-buttons-fixed .btn-ranking,
        .ranking-buttons-fixed .btn-individual {
            padding: 8px 20px;
            font-size: 0.8rem;
            min-width: 120px;
        }

        .btn-ranking-top {
            border-radius: 12px 12px 0 0;
            margin-bottom: 0;
        }

        .btn-ranking-bottom {
            border-radius: 0 0 12px 12px;
            margin-top: 0;
        }

        .btn-play {
            background: #073545;
            color: white;
            padding: 18px 50px;
            font-size: 1.3rem;
        }

        .btn-play:hover {
            background: #0a4a5e;
        }

        .ranking-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: bold;
            color: #fff;
            margin: 40px 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }

        .ranking-content {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 15px;
            padding: 30px;
            margin-top: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .ranking-table tr {
            transition: all 0.3s ease;
        }

        .ranking-table tr.updating {
            background: rgba(255, 107, 53, 0.2);
        }

        .ranking-table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            margin-top: 20px;
        }

        .ranking-table th {
            background: linear-gradient(135deg, #073545, #0a4a5e);
            color: white;
            padding: 15px 10px;
            text-align: center;
            font-weight: bold;
            font-size: 0.9rem;
            border-bottom: 2px solid #ff6b35;
        }

        .ranking-table td {
            padding: 12px 8px;
            text-align: center;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            color: #333;
            font-size: 0.85rem;
        }

        .ranking-table tr:nth-child(even) {
            background: rgba(0, 0, 0, 0.02);
        }

        .ranking-table tr:hover {
            background: rgba(255, 107, 53, 0.1);
            transform: scale(1.01);
            transition: all 0.2s ease;
        }

        .team-name-cell {
            text-align: left;
            padding: 8px 10px;
            max-width: 650px;
            width: 650px;
        }

        .team-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .team-character-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: contain;
            background-color: rgba(255, 255, 255, 0.1);
        }

        .team-details {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .team-character-name {
            font-weight: bold;
            color: #073545;
            font-size: 0.9rem;
            text-align: left;
        }

        .team-pole-name {
            color: #666;
            font-size: 0.8rem;
            font-style: italic;
            text-align: left;
        }

        .team-members-list {
            color: #333;
            font-size: 0.75rem;
            margin-top: 2px;
            line-height: 1.2;
            text-align: left;
        }

        .points-cell {
            font-weight: bold;
            font-size: 1.1rem;
            color: #ff6b35;
        }

        .objects-cell {
            text-align: left;
            padding: 8px 12px;
            min-width: 200px;
        }

        .object-item {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
            font-size: 0.9rem;
            padding: 2px 0;
        }

        .object-item:last-child {
            margin-bottom: 0;
        }

        .object-icon {
            margin-right: 6px;
            font-size: 0.9rem;
        }

        .object-icon.found {
            color: #4CAF50;
        }

        .object-icon.not-found {
            color: #f44336;
        }

        .object-miniature {
            width: 30px;
            height: 30px;
            margin-right: 8px;
            border-radius: 5px;
            object-fit: cover;
        }

        .object-name {
            color: #333;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .object-points {
            margin-left: 4px;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: bold;
            min-width: 40px;
            text-align: center;
        }

        .object-points.found {
            background: rgba(76, 175, 80, 0.3);
            color: #4CAF50;
            border: 1px solid rgba(76, 175, 80, 0.5);
        }

        .object-points.not-found {
            background: rgba(244, 67, 54, 0.3);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.5);
        }

        .day-cell {
            font-size: 0.9rem;
            padding: 12px 8px;
        }

        .day-cell > div {
            margin-bottom: 8px;
        }

        .day-cell > div:last-child {
            margin-bottom: 0;
        }

        .day-enigma-status {
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: bold;
            text-align: center;
        }

        .day-enigma-text {
            font-size: 0.8rem;
            margin-bottom: 4px;
            text-align: center;
        }

        .day-enigma-text.resolved {
            color: #4CAF50;
            font-weight: bold;
        }

        .day-enigma-text.not-resolved {
            color: #f44336;
            font-weight: bold;
        }

        .day-points {
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            color: #000;
        }

        .day-enigma-status.resolved {
            background: rgba(76, 175, 80, 0.3);
            color: #4CAF50;
            border: 1px solid rgba(76, 175, 80, 0.5);
        }

        .day-enigma-status.not-resolved {
            background: rgba(244, 67, 54, 0.3);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.5);
        }

        .day-complete {
            color: #4CAF50;
            font-weight: bold;
        }

        .day-incomplete {
            color: #ff9800;
        }

        .day-not-started {
            color: #f44336;
        }

        .rank-cell {
            font-weight: bold;
            font-size: 1.1rem;
            color: #ff6b35;
            text-align: center;
            vertical-align: middle;
            padding: 8px;
        }

        #ranking-tbody > tr > td:first-child {
            margin-top: 40px;
        }

        .rank-1 { color: #ffd700; }
        .rank-2 { color: #c0c0c0; }
        .rank-3 { color: #cd7f32; }
        .rank-dash { color: #999; font-style: italic; }

        /* Styles de médaille pour les lignes */
        .ranking-table tr.medal-1 {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.1), rgba(255, 215, 0, 0.05)) !important;
            border-left: 4px solid #ffd700;
        }

        .ranking-table tr.medal-2 {
            background: linear-gradient(135deg, rgba(192, 192, 192, 0.1), rgba(192, 192, 192, 0.05)) !important;
            border-left: 4px solid #c0c0c0;
        }

        .ranking-table tr.medal-3 {
            background: linear-gradient(135deg, rgba(205, 127, 50, 0.1), rgba(205, 127, 50, 0.05)) !important;
            border-left: 4px solid #cd7f32;
        }

        /* Style des numéros de médaille */
        .rank-cell.rank-1 {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #000;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(255, 215, 0, 0.3);
            border: 2px solid #ffb300;
            margin: 0 auto;
        }

        .rank-cell.rank-2 {
            background: linear-gradient(135deg, #c0c0c0, #e8e8e8);
            color: #000;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(192, 192, 192, 0.3);
            border: 2px solid #a0a0a0;
            margin: 0 auto;
        }

        .rank-cell.rank-3 {
            background: linear-gradient(135deg, #cd7f32, #daa520);
            color: #fff;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(205, 127, 50, 0.3);
            border: 2px solid #8b4513;
            margin: 0 auto;
        }

        .golden-paper-info {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
        }

        .golden-paper-text-dark {
            color: #B8860B;
            font-weight: bold;
        }

        .golden-bonus-points {
            background: #FFD700;
            color: #333;
            padding: 3px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .ranking-title {
                font-size: 2rem;
            }

            .ranking-content {
                margin-top: 40px;
                padding: 20px;
            }

            .buttons-container {
                gap: 15px;
            }

            .game-button {
                padding: 12px 30px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Boutons de classement fixes en haut à gauche -->
    <div class="ranking-buttons-fixed">
        <a href="ranking.php" class="game-button btn-ranking btn-ranking-top">🏆 Classement Équipes</a>
        <a href="ranking-individual.php" class="game-button btn-individual btn-ranking-bottom">👤 Classement Individuel</a>
    </div>

    <div class="container">
        <a href="index.php">
            <img src="assets/img/logo.png" alt="CLUEDO Tak exomind" class="logo">
        </a>

        <h1 class="ranking-title">🏆 Classement par équipes</h1>

        <div class="ranking-content">
            <!-- Indicateur de chargement -->
            <div id="loading-indicator" style="text-align: center; color: white; font-size: 1.2rem; padding: 40px;">
                <div style="display: inline-block; animation: spin 1s linear infinite; font-size: 2rem;">⏳</div>
                <br>Chargement du classement...
            </div>
            
            <!-- Message d'erreur -->
            <div id="error-message" class="no-data" style="display: none;">
                <h3>❌ Erreur de chargement</h3>
                <p>Impossible de charger les données du classement.</p>
                <button onclick="loadRankingData()" style="margin-top: 10px; padding: 10px 20px; background: #ff6b35; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    🔄 Réessayer
                </button>
            </div>
            
            <!-- Tableau de classement -->
            <table id="ranking-table" class="ranking-table" style="display: none;">
                <thead>
                    <tr>
                        <th>Classement</th>
                        <th>Points</th>
                        <th>Équipe</th>
                        <th>Objets</th>
                        <th>Jour 1</th>
                        <th>Jour 2</th>
                        <th>Jour 3</th>
                    </tr>
                </thead>
                <tbody id="ranking-tbody">
                    <!-- Le contenu sera généré dynamiquement -->
                </tbody>
            </table>
        </div>
    </div>

    <script>
        let refreshInterval;
        let isUpdating = false;

        // Fonction pour charger les données du classement
        async function loadRankingData() {
            if (isUpdating) return;
            
            isUpdating = true;
            
            try {
                const response = await fetch('ajax_classement_equipes.php');
                const data = await response.json();
                
                if (data.success) {
                    updateRankingTable(data);
                    hideError();
                } else {
                    showError();
                }
            } catch (error) {
                console.error('Erreur lors du chargement:', error);
                showError();
            } finally {
                isUpdating = false;
            }
        }

        // Fonction pour mettre à jour le tableau de classement
        function updateRankingTable(data) {
            const { teams } = data;
            
            // Générer le contenu du tableau
            const tbody = document.getElementById('ranking-tbody');
            tbody.innerHTML = '';
            
            if (teams.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="no-data">Aucune équipe trouvée</td></tr>';
            } else {
                teams.forEach(team => {
                    const row = createTeamRow(team);
                    tbody.appendChild(row);
                });
            }
            
            // Afficher les éléments
            document.getElementById('loading-indicator').style.display = 'none';
            document.getElementById('ranking-table').style.display = 'table';
        }

        // Fonction pour créer une ligne d'équipe
        function createTeamRow(team) {
            const row = document.createElement('tr');
            
            // Déterminer la classe de rang et de médaille
            let rankClass = '';
            let medalClass = '';
            let rankDisplay = team.rank;
            
            if (team.rank === '-') {
                rankClass = 'rank-dash';
            } else if (team.rank === 1) {
                rankClass = 'rank-1';
                medalClass = 'medal-1';
            } else if (team.rank === 2) {
                rankClass = 'rank-2';
                medalClass = 'medal-2';
            } else if (team.rank === 3) {
                rankClass = 'rank-3';
                medalClass = 'medal-3';
            }
            
            // Ajouter la classe de médaille à la ligne
            if (medalClass) {
                row.classList.add(medalClass);
            }
            
            // Générer les objets
            let objectsHtml = '';
            if (team.items && team.items.length > 0) {
                team.items.forEach(item => {
                    const statusClass = item.solved ? 'found' : 'not-found';
                    const statusIcon = item.solved ? '✓' : '✗';
                    const points = item.solved ? '500 pts' : '0 pts';
                    
                    objectsHtml += `
                        <div class="object-item">
                            <span class="object-icon ${statusClass}">${statusIcon}</span>
                            <img src="${item.path}" alt="${item.title}" class="object-miniature" title="${item.title} - ${item.subtitle}">
                            <span class="object-name">${item.title}</span>
                            <span class="object-points ${statusClass}">${points}</span>
                        </div>
                    `;
                });
            } else {
                objectsHtml = '<span style="color: #666; font-style: italic;">Aucun objet</span>';
            }
            
            // Générer les jours
            const daysHtml = [1, 2, 3].map(day => {
                const status = team[`day_${day}_enigma_status`];
                const score = team[`day_${day}_enigma_score`];
                const scoreDisplay = team[`day_${day}_enigma_score_display`] || Math.ceil(score);
                const goldenPapers = team[`day_${day}_golden_papers`] || 0;
                const isResolved = status === 2;
                const statusClass = isResolved ? 'resolved' : 'not-resolved';
                const statusText = isResolved ? 'Énigme résolue' : 'Énigme non résolue';
                const enigmaPoints = isResolved ? scoreDisplay : 0;
                const goldenPoints = goldenPapers * 1500;
                const totalPoints = enigmaPoints + goldenPoints;
                
                return `
                    <td class="day-cell">
                        <div class="day-points">${totalPoints} pts</div>
                        ${goldenPapers > 0 ? `<div class="golden-paper-info"><span class="golden-paper-text-dark">${goldenPapers} papier(s) en or</span><span class="golden-bonus-points">+${goldenPoints.toLocaleString()} pts</span></div>` : ''}
                        <div class="day-enigma-text ${statusClass}">${statusText}</div>
                    </td>
                `;
            }).join('');
            
            // Image de l'équipe
            const teamImage = team.img_path ? 
                `<img src="${team.img_path}" alt="${team.name}" class="team-character-image">` :
                `<div class="team-character-image" style="background: ${team.color || '#888'}; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">🎭</div>`;
            
            row.innerHTML = `
                <td class="rank-cell ${rankClass}">${rankDisplay}</td>
                <td class="points-cell">${team.total_points_display || Math.ceil(team.total_points)} pts</td>
                <td class="team-name-cell">
                    <div class="team-info">
                        ${teamImage}
                        <div class="team-details">
                            <div class="team-character-name">${team.name}</div>
                            <div class="team-pole-name">${team.pole_name}</div>
                            ${team.members_list ? `<div class="team-members-list">${team.members_list}</div>` : ''}
                        </div>
                    </div>
                </td>
                <td class="objects-cell">
                    ${objectsHtml}
                </td>
                ${daysHtml}
            `;
            
            return row;
        }

        // Fonction pour afficher l'erreur
        function showError() {
            document.getElementById('loading-indicator').style.display = 'none';
            document.getElementById('ranking-table').style.display = 'none';
            document.getElementById('error-message').style.display = 'block';
        }

        // Fonction pour masquer l'erreur
        function hideError() {
            document.getElementById('error-message').style.display = 'none';
        }

        // Fonction pour démarrer le rafraîchissement automatique
        function startAutoRefresh() {
            // Charger immédiatement
            loadRankingData();
            
            // Puis toutes les 10 secondes
            refreshInterval = setInterval(loadRankingData, 10000);
        }

        // Fonction pour arrêter le rafraîchissement automatique
        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
            }
        }

        // Démarrer le rafraîchissement automatique au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            startAutoRefresh();
        });

        // Arrêter le rafraîchissement quand la page n'est plus visible
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopAutoRefresh();
            } else {
                startAutoRefresh();
            }
        });

        // Arrêter le rafraîchissement avant de quitter la page
        window.addEventListener('beforeunload', function() {
            stopAutoRefresh();
        });
    </script>
</body>
</html>
