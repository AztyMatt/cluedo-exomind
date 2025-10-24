<?php
// Page de classement individuel avec rafraîchissement automatique via AJAX
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cluedo - Classement Individuel</title>
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

        .btn-play {
            background: #073545;
            color: white;
            padding: 18px 50px;
            font-size: 1.3rem;
        }

        .btn-play:hover {
            background: #0a4a5e;
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

        .rank-position {
            font-weight: bold;
            font-size: 1.1rem;
            color: #ff6b35;
            text-align: center;
            vertical-align: middle;
            padding: 8px;
        }

        .rank-1 { color: #ffd700; }
        .rank-2 { color: #c0c0c0; }
        .rank-3 { color: #cd7f32; }

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
        .rank-position.rank-1 {
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

        .rank-position.rank-2 {
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

        .rank-position.rank-3 {
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

        .player-name {
            font-weight: bold;
            color: #073545;
        }

        .group-info {
            font-size: 0.8rem;
            color: #666;
            font-style: italic;
        }

        .points {
            font-weight: bold;
            color: #4CAF50;
        }

        .day-points {
            font-weight: bold;
            color: #2196F3;
        }

        .items-count {
            font-weight: bold;
            color: #9C27B0;
        }

        .items-bonus {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .items-number {
            font-weight: bold;
            color: #9C27B0;
        }

        .bonus-points {
            background: #4CAF50;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            white-space: nowrap;
        }

        .day-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .golden-paper-info {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
        }

        .golden-paper-text {
            color: #FFD700;
            font-weight: bold;
        }

        .normal-paper-text {
            color: #6B7280;
            font-weight: bold;
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

        .normal-bonus-points {
            background: #4B5563;
            color: white;
            padding: 3px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            white-space: nowrap;
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

        @media (max-width: 1200px) {
            .ranking-table {
                font-size: 0.8rem;
            }
            
            .ranking-table th,
            .ranking-table td {
                padding: 8px 4px;
            }
        }

        @media (max-width: 768px) {
            .ranking-table {
                font-size: 0.7rem;
            }
            
            .ranking-table th,
            .ranking-table td {
                padding: 6px 2px;
            }
            
            .player-name {
                font-size: 0.8rem;
            }
            
            .group-info {
                font-size: 0.7rem;
            }
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

        <h1 class="ranking-title">🏆 Classement Individuel</h1>

        <div class="ranking-content">
            <!-- Indicateur de chargement -->
            <div id="loading-indicator" style="text-align: center; color: white; font-size: 1.2rem; padding: 40px;">
                <div style="display: inline-block; animation: spin 1s linear infinite; font-size: 2rem;">⏳</div>
                <br>Chargement du classement...
            </div>
            
            <!-- Statistiques -->
            <div id="stats-container" style="text-align: center; color: white; font-size: 1.1rem; margin-bottom: 20px; display: none;">
                📊 Nb joueurs (TAK & Exo) : <span id="total-players">0</span> | 
                ✅ Joueurs activés : <span id="activated-players">0</span>
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
                        <th>🏆</th>
                        <th>Points</th>
                        <th>Joueur</th>
                        <th>Personnage & Pôle</th>
                        <th>Jour 1</th>
                        <th>Jour 2</th>
                        <th>Jour 3</th>
                        <th>Objets</th>
                        <th>Total papiers trouvés</th>
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
                const response = await fetch('ajax_classement_individuel.php');
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
            const { players, total_players, activated_players } = data;
            
            // Mettre à jour les statistiques
            document.getElementById('total-players').textContent = total_players;
            document.getElementById('activated-players').textContent = activated_players;
            
            // Générer le contenu du tableau
            const tbody = document.getElementById('ranking-tbody');
            tbody.innerHTML = '';
            
            if (players.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="no-data">Aucun joueur trouvé</td></tr>';
            } else {
                players.forEach(player => {
                    const row = createPlayerRow(player);
                    tbody.appendChild(row);
                });
            }
            
            // Afficher les éléments
            document.getElementById('loading-indicator').style.display = 'none';
            document.getElementById('stats-container').style.display = 'block';
            document.getElementById('ranking-table').style.display = 'table';
        }

        // Fonction pour calculer les points des papiers normaux par jour
        function calculateNormalPapersPoints(dayPoints, papersCount) {
            if (papersCount === 0) return 0;
            
            // Calculer les points moyens par papier
            const avgPointsPerPaper = Math.round(dayPoints / papersCount);
            return avgPointsPerPaper * papersCount;
        }

        // Fonction pour créer une ligne de joueur
        function createPlayerRow(player) {
            const row = document.createElement('tr');
            
            // Déterminer la classe de rang et de médaille
            let rankClass = '';
            let medalClass = '';
            
            if (player.rank === 1) {
                rankClass = 'rank-1';
                medalClass = 'medal-1';
            } else if (player.rank === 2) {
                rankClass = 'rank-2';
                medalClass = 'medal-2';
            } else if (player.rank === 3) {
                rankClass = 'rank-3';
                medalClass = 'medal-3';
            }
            
            // Ajouter la classe de médaille à la ligne
            if (medalClass) {
                row.classList.add(medalClass);
            }
            
            // Calculer les points totaux
            const totalPoints = player.day1_points + player.day2_points + player.day3_points + player.items_bonus_points;
            
            // Calculer les points des papiers normaux par jour
            const day1NormalPoints = calculateNormalPapersPoints(player.day1_points - (player.golden_papers_day1 * 1000), player.day1_papers_count);
            const day2NormalPoints = calculateNormalPapersPoints(player.day2_points - (player.golden_papers_day2 * 1000), player.day2_papers_count);
            const day3NormalPoints = calculateNormalPapersPoints(player.day3_points - (player.golden_papers_day3 * 1000), player.day3_papers_count);
            
            row.innerHTML = `
                <td class="rank-position ${rankClass}">
                    ${player.total_points > 0 ? player.rank : '-'}
                </td>
                <td class="points">
                    ${totalPoints.toLocaleString()}
                </td>
                <td class="player-name">
                    ${player.formatted_name}
                    ${!player.has_activated ? '<br><small style="color: #ff6b35;">⚠️ Non activé</small>' : ''}
                </td>
                <td class="group-info">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                        ${player.img_path ? `<img src="${player.img_path}" alt="${player.group_name}" style="width: 40px; border-radius: 8px; object-fit: cover;">` : ''}
                        <div>
                            ${player.group_name}<br>
                            <small>${player.pole_name}</small>
                        </div>
                    </div>
                </td>
                <td class="day-points">
                    <div class="day-content">
                        ${player.day1_points.toLocaleString()}
                        ${player.day1_papers_count > 0 ? `<div class="golden-paper-info"><span class="normal-paper-text">${player.day1_papers_count} papier(s)</span><span class="normal-bonus-points">+${day1NormalPoints.toLocaleString()} pts</span></div>` : ''}
                        ${player.golden_papers_day1 > 0 ? `<div class="golden-paper-info"><span class="golden-paper-text-dark">${player.golden_papers_day1} papier(s) en or</span><span class="golden-bonus-points">+${(player.golden_papers_day1 * 1500).toLocaleString()} pts</span></div>` : ''}
                    </div>
                </td>
                <td class="day-points">
                    <div class="day-content">
                        ${player.day2_points.toLocaleString()}
                        ${player.day2_papers_count > 0 ? `<div class="golden-paper-info"><span class="normal-paper-text">${player.day2_papers_count} papier(s)</span><span class="normal-bonus-points">+${day2NormalPoints.toLocaleString()} pts</span></div>` : ''}
                        ${player.golden_papers_day2 > 0 ? `<div class="golden-paper-info"><span class="golden-paper-text-dark">${player.golden_papers_day2} papier(s) en or</span><span class="golden-bonus-points">+${(player.golden_papers_day2 * 1500).toLocaleString()} pts</span></div>` : ''}
                    </div>
                </td>
                <td class="day-points">
                    <div class="day-content">
                        ${player.day3_points.toLocaleString()}
                        ${player.day3_papers_count > 0 ? `<div class="golden-paper-info"><span class="normal-paper-text">${player.day3_papers_count} papier(s)</span><span class="normal-bonus-points">+${day3NormalPoints.toLocaleString()} pts</span></div>` : ''}
                        ${player.golden_papers_day3 > 0 ? `<div class="golden-paper-info"><span class="golden-paper-text-dark">${player.golden_papers_day3} papier(s) en or</span><span class="golden-bonus-points">+${(player.golden_papers_day3 * 1500).toLocaleString()} pts</span></div>` : ''}
                    </div>
                </td>
                <td class="items-count">
                    <div class="items-bonus">
                        <span class="items-number">${player.items_count}</span>
                        ${player.items_count > 0 ? `<span class="bonus-points">+${player.items_bonus_points.toLocaleString()} pts</span>` : ''}
                    </div>
                </td>
                <td class="items-count">
                    ${player.total_papers_found}
                </td>
            `;
            
            return row;
        }

        // Fonction pour afficher l'erreur
        function showError() {
            document.getElementById('loading-indicator').style.display = 'none';
            document.getElementById('stats-container').style.display = 'none';
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
