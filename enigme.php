<?php
// Démarrer la session
session_start([
    'cookie_lifetime' => 86400 * 7,
    'cookie_secure' => false,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict'
]);

// Connexion à la base de données
require_once __DIR__ . '/db-connection.php';
$dbConnection = getDBConnection();

// ========== API POUR RÉSOUDRE L'ÉNIGME ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'solve_enigma') {
    $dayId = $_POST['day'] ?? 1;
    $playerId = $_SESSION['user_id'] ?? null;
    
    if (!$playerId || !$dbConnection) {
        echo json_encode(['success' => false, 'message' => 'Non authentifié']);
        exit;
    }
    
    try {
        // Récupérer le groupe du joueur
        $stmt = $dbConnection->prepare("SELECT group_id FROM `users` WHERE id = ?");
        $stmt->execute([$playerId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['group_id']) {
            // Mettre à jour le status de l'énigme à 2 (résolue) avec l'ID du solveur
            $stmt = $dbConnection->prepare("UPDATE `enigmes` SET status = 2, solved = TRUE, datetime_solved = NOW(), solved_by_user_id = ? WHERE id_group = ? AND id_day = ?");
            $stmt->execute([$playerId, $user['group_id'], $dayId]);
            
            // CHRONOMÉTRAGE : Arrêter le chrono quand l'énigme est résolue
            $stmt = $dbConnection->prepare("SELECT id FROM `enigmes` WHERE id_group = ? AND id_day = ?");
            $stmt->execute([$user['group_id'], $dayId]);
            $enigma = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($enigma) {
                // Mettre à jour timestamp_end pour arrêter le chrono
                $stmt = $dbConnection->prepare("UPDATE `enigm_solutions_durations` SET timestamp_end = NOW() WHERE id_enigm = ? AND timestamp_end IS NULL");
                $stmt->execute([$enigma['id']]);
                
                if ($stmt->rowCount() > 0) {
                    error_log("⏱️ Chrono arrêté pour l'énigme ID " . $enigma['id'] . " (équipe " . $user['group_id'] . ", jour " . $dayId . ")");
                }
            }
            
            // Mettre à jour complete dans total_papers_found_group
            $stmt = $dbConnection->prepare("UPDATE `total_papers_found_group` SET complete = TRUE WHERE id_group = ? AND id_day = ?");
            $stmt->execute([$user['group_id'], $dayId]);
            
            echo json_encode(['success' => true, 'message' => 'Énigme résolue']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Groupe non trouvé']);
        }
    } catch (PDOException $e) {
        error_log("Erreur résolution énigme: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
    exit;
}

// Fonction pour formater le nom : Prénom NOM
function formatUserName($firstname, $lastname) {
    return ucfirst(strtolower($firstname)) . ' ' . strtoupper($lastname);
}

// Variables
$error_message = '';
$show_error = false;
$user = null;
$enigma = null;
$paperStats = null;
$selectedDay = isset($_GET['day']) ? (int)$_GET['day'] : 1;
$selectedDay = max(1, min(3, $selectedDay));
$targetTeamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : null;
$isViewingOtherTeam = $targetTeamId !== null;

// Vérifier le cookie
$activation_code_cookie = $_COOKIE['cluedo_activation'] ?? null;

if (!$activation_code_cookie || !$dbConnection) {
    $error_message = "❌ Vous devez être connecté pour accéder à cette page.";
    $show_error = true;
} else {
    try {
        // Vérifier si le code du cookie existe en base
        $stmt = $dbConnection->prepare("SELECT u.*, g.id as group_id, g.name as team_name, g.pole_name, g.color as team_color, g.img_path as team_img FROM `users` u LEFT JOIN `groups` g ON u.group_id = g.id WHERE u.activation_code = ? AND u.has_activated = 1");
        $stmt->execute([$activation_code_cookie]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !$user['group_id']) {
            $error_message = "❌ Utilisateur non trouvé ou non assigné à une équipe.";
            $show_error = true;
        } else {
            // Déterminer quelle équipe consulter
            $teamIdToCheck = $isViewingOtherTeam ? $targetTeamId : $user['group_id'];
            
            if ($isViewingOtherTeam) {
                // Vérifier que l'équipe cible existe
                $stmt = $dbConnection->prepare("SELECT id, name, pole_name, color, img_path FROM `groups` WHERE id = ?");
                $stmt->execute([$targetTeamId]);
                $targetTeam = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$targetTeam) {
                    $error_message = "❌ Équipe non trouvée.";
                    $show_error = true;
                } else {
                    // Récupérer l'énigme de l'équipe cible (seulement si résolue)
                    $stmt = $dbConnection->prepare("
                        SELECT e.enigm_label, e.enigm_solution, e.status, e.datetime_solved, 
                               u.firstname as solver_firstname, u.lastname as solver_lastname
                        FROM `enigmes` e 
                        LEFT JOIN `users` u ON e.solved_by_user_id = u.id 
                        WHERE e.id_group = ? AND e.id_day = ? AND e.status = 2
                    ");
                    $stmt->execute([$targetTeamId, $selectedDay]);
                    $enigma = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$enigma) {
                        $error_message = "🔒 Cette équipe n'a pas encore résolu son énigme pour ce jour.";
                        $show_error = true;
                    } else {
                        // Utiliser les infos de l'équipe cible pour l'affichage
                        $user['team_name'] = $targetTeam['name'];
                        $user['pole_name'] = $targetTeam['pole_name'];
                        $user['team_color'] = $targetTeam['color'];
                        $user['team_img'] = $targetTeam['img_path'];
                        
                        // Préparer les informations du solveur
                        $solverInfo = null;
                        if ($enigma['status'] == 2 && $enigma['datetime_solved'] && $enigma['solver_firstname']) {
                            $solverInfo = [
                                'firstname' => $enigma['solver_firstname'],
                                'lastname' => $enigma['solver_lastname']
                            ];
                        }
                    }
                }
            } else {
                // Mode normal : vérifier les papiers de l'utilisateur
                $stmt = $dbConnection->prepare("SELECT total_to_found, total_founded FROM `total_papers_found_group` WHERE id_group = ? AND id_day = ?");
                $stmt->execute([$user['group_id'], $selectedDay]);
                $paperStats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$paperStats || $paperStats['total_founded'] < $paperStats['total_to_found']) {
                    $found = $paperStats ? $paperStats['total_founded'] : 0;
                    $total = $paperStats ? $paperStats['total_to_found'] : 0;
                    $error_message = "🔒 Votre équipe n'a pas encore trouvé tous les papiers pour ce jour.<br>Papiers trouvés : <strong>$found / $total</strong><br><br>Continuez à chercher !";
                    $show_error = true;
                } else {
                    // Récupérer l'énigme pour ce jour et cette équipe avec les infos du solveur
                    $stmt = $dbConnection->prepare("
                        SELECT e.enigm_label, e.enigm_solution, e.status, e.datetime_solved, 
                               u.firstname as solver_firstname, u.lastname as solver_lastname
                        FROM `enigmes` e 
                        LEFT JOIN `users` u ON e.solved_by_user_id = u.id 
                        WHERE e.id_group = ? AND e.id_day = ?
                    ");
                    $stmt->execute([$user['group_id'], $selectedDay]);
                    $enigma = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Préparer les informations du solveur
                    $solverInfo = null;
                    if ($enigma && $enigma['status'] == 2 && $enigma['datetime_solved'] && $enigma['solver_firstname']) {
                        $solverInfo = [
                            'firstname' => $enigma['solver_firstname'],
                            'lastname' => $enigma['solver_lastname']
                        ];
                    }
                    
                    if (!$enigma) {
                        $error_message = "❌ Aucune énigme n'a été configurée pour votre équipe et ce jour.";
                        $show_error = true;
                    }
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification: " . $e->getMessage());
        $error_message = "⚠️ Erreur lors de la récupération des données.";
        $show_error = true;
    }
}

// Libellés des jours
$dayLabels = [
    1 => ['number' => 'Jour 1', 'objective' => '🏛️ Scène du crime'],
    2 => ['number' => 'Jour 2', 'objective' => '🔪 Arme du crime'],
    3 => ['number' => 'Jour 3', 'objective' => '🎭 Auteur du crime']
];
$currentDay = $dayLabels[$selectedDay];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cluedo - Énigme du <?= $currentDay['number'] ?></title>
    
    <!-- Canvas Confetti pour les feux d'artifice -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    
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
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(42, 42, 42, 0.95);
            border-radius: 12px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.1);
            z-index: 1000;
            backdrop-filter: blur(10px);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, <?= htmlspecialchars($user['team_color'] ?? '#888') ?>, rgba(255,255,255,0.3));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            border: 2px solid <?= htmlspecialchars($user['team_color'] ?? '#888') ?>;
            flex-shrink: 0;
        }

        .user-details h2 {
            font-size: 0.95rem;
            color: #fff;
            margin-bottom: 3px;
            white-space: nowrap;
        }

        .user-team {
            font-size: 0.8rem;
            color: <?= htmlspecialchars($user['team_color'] ?? '#888') ?>;
            font-weight: bold;
        }

        .enigma-container {
            background: rgba(42, 42, 42, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.1);
            margin-top: 20px;
        }

        .enigma-title {
            font-size: 2rem;
            color: #fff;
            text-align: center;
            margin-bottom: 8px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
        }

        .enigma-subtitle {
            font-size: 1rem;
            color: <?= htmlspecialchars($user['team_color'] ?? '#888') ?>;
            text-align: center;
            margin-bottom: 25px;
            font-weight: bold;
        }

        .enigma-text {
            font-size: 1.1rem;
            color: #000;
            text-align: center;
            line-height: 1.6;
            margin-bottom: 30px;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            border-left: 4px solid <?= htmlspecialchars($user['team_color'] ?? '#888') ?>;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .solution-container {
            margin-top: 25px;
        }

        .solution-label {
            font-size: 1.3rem;
            color: #fff;
            text-align: center;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .solution-boxes {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .letter-box {
            width: 50px;
            height: 65px;
            background: rgba(255, 255, 255, 0.1);
            border: 3px solid <?= htmlspecialchars($user['team_color'] ?? '#888') ?>;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: #fff;
            text-transform: uppercase;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
            transition: all 0.3s ease;
        }

        .letter-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.6);
            background: rgba(255, 255, 255, 0.2);
        }

        /* Styles pour les cases résolues */
        .letter-box.solved {
            border-color: #4CAF50 !important;
            background: rgba(76, 175, 80, 0.2) !important;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4) !important;
        }

        .letter-box.solved:hover {
            transform: none;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4) !important;
            background: rgba(76, 175, 80, 0.2) !important;
        }

        /* Styles pour le bouton désactivé */
        .back-btn.disabled {
            background: #666 !important;
            cursor: not-allowed !important;
            opacity: 0.6;
        }

        .back-btn.disabled:hover {
            transform: none !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3) !important;
        }

        .error-container {
            background: rgba(235, 51, 73, 0.2);
            border-left: 4px solid #eb3349;
            padding: 30px;
            border-radius: 12px;
            margin-top: 20px;
            text-align: center;
        }

        .error-container h2 {
            font-size: 2rem;
            color: #ff6b6b;
            margin-bottom: 20px;
        }

        .error-container p {
            font-size: 1.2rem;
            color: #eee;
            line-height: 1.8;
        }

        .back-btn {
            display: inline-block;
            margin-top: 30px;
            padding: 15px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: bold;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .back-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        .day-indicator {
            text-align: center;
            margin-bottom: 20px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            border: 2px solid <?= htmlspecialchars($user['team_color'] ?? '#888') ?>;
        }

        .day-indicator h3 {
            font-size: 1.8rem;
            color: <?= htmlspecialchars($user['team_color'] ?? '#888') ?>;
            margin-bottom: 5px;
        }

        .day-indicator p {
            font-size: 1.2rem;
            color: #ccc;
        }

        /* Animation d'erreur pour les cases */
        .letter-box.error {
            animation: shakeError 0.5s ease-in-out;
            border-color: #ff4444 !important;
            background: rgba(255, 68, 68, 0.2) !important;
            box-shadow: 0 4px 15px rgba(255, 68, 68, 0.4) !important;
        }

        @keyframes shakeError {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        /* Animation d'erreur pour le conteneur */
        .solution-container.error {
            animation: containerShake 0.6s ease-in-out;
        }

        @keyframes containerShake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-8px); }
            20%, 40%, 60%, 80% { transform: translateX(8px); }
        }

        /* Styles pour le bouton désactivé */
        .back-btn.disabled {
            background: #666 !important;
            cursor: not-allowed !important;
            opacity: 0.6;
        }

        .back-btn.disabled:hover {
            transform: none !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3) !important;
        }

        /* Message d'erreur temporaire */
        .error-message {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 68, 68, 0.95);
            color: white;
            padding: 20px 40px;
            border-radius: 15px;
            font-size: 1.2rem;
            font-weight: bold;
            z-index: 10000;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.7);
            animation: errorPopup 0.3s ease;
        }

        @keyframes errorPopup {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        /* Message de succès temporaire */
        .success-message {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(76, 175, 80, 0.95);
            color: white;
            padding: 20px 40px;
            border-radius: 15px;
            font-size: 1.2rem;
            font-weight: bold;
            z-index: 10000;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.7);
            animation: successPopup 0.3s ease;
        }

        @keyframes successPopup {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }
    </style>
</head>
<body>
    <?php if ($user): ?>
        <div class="header">
            <div class="user-info">
                <div class="user-avatar">
                    <?php if (!empty($user['team_img']) && file_exists($user['team_img'])): ?>
                        <img src="<?= htmlspecialchars($user['team_img']) ?>" alt="<?= htmlspecialchars($user['team_name']) ?>" style="width: 100%; height: 100%; object-fit: contain; border-radius: 50%;">
                    <?php else: ?>
                        🎮
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <h2><?= htmlspecialchars(formatUserName($user['firstname'], $user['lastname'])) ?></h2>
                    <div class="user-team"><?= htmlspecialchars($user['pole_name'] ?? 'Non assigné') ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="container">
        <?php if ($show_error): ?>
            <!-- ========== MESSAGE D'ERREUR ========== -->
            <div class="error-container">
                <h2>Accès refusé</h2>
                <p><?= $error_message ?></p>
                <a href="game.php" class="back-btn">← Retour au jeu</a>
            </div>
        <?php else: ?>
            <!-- ========== AFFICHAGE DE L'ÉNIGME ========== -->
            <div class="enigma-container">
                <div style="display: flex; align-items: center; margin-bottom: 25px;">
                    <div style="width: 45%;">
                        <div class="day-indicator" style="margin-bottom: 0;">
                            <h3><?= $currentDay['number'] ?></h3>
                            <p><?= $currentDay['objective'] ?></p>
                        </div>
                    </div>
                    <div style="width: 10%; display: flex; justify-content: center;">
                        <?php if (!empty($user['team_img']) && file_exists($user['team_img'])): ?>
                            <img src="<?= htmlspecialchars($user['team_img']) ?>" alt="<?= htmlspecialchars($user['team_name']) ?>" style="width: 120px; height: 120px; object-fit: contain; border-radius: 50%;">
                        <?php endif; ?>
                    </div>
                    <div style="width: 45%; text-align: right;">
                        <h1 class="enigma-title" style="margin-bottom: 8px; font-size: 1.8rem;">
                            <?php if ($isViewingOtherTeam): ?>
                                👀 Énigme résolue de l'équipe
                            <?php else: ?>
                                🎭 Énigme de votre équipe
                            <?php endif; ?>
                        </h1>
                        <div class="enigma-subtitle" style="margin-bottom: 0; font-size: 1rem;">
                            <?= htmlspecialchars($user['team_name']) ?> - <?= htmlspecialchars($user['pole_name']) ?>
                        </div>
                    </div>
                </div>

                <div class="enigma-text">
                    « <?= nl2br(htmlspecialchars($enigma['enigm_label'])) ?> »
                </div>

                <div class="solution-container">
                    <?php if ($enigma['status'] == 2 || $isViewingOtherTeam): ?>
                        <div class="solution-label">🎉 Solution trouvée !</div>
                        <?php if ($solverInfo && $enigma['datetime_solved']): ?>
                            <div style="text-align: center; margin-bottom: 20px; font-size: 1rem; color: #4CAF50;">
                                ✅ Résolu par <strong><?= htmlspecialchars(formatUserName($solverInfo['firstname'], $solverInfo['lastname'])) ?></strong><br>
                                📅 Le <?= date('d/m/Y à H:i', strtotime($enigma['datetime_solved'])) ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="solution-label">💡 Entrez votre solution :</div>
                    <?php endif; ?>
                    <div class="solution-boxes">
                        <?php 
                        $solutionLength = strlen($enigma['enigm_solution']);
                        $isSolved = $enigma['status'] == 2 || $isViewingOtherTeam;
                        for ($i = 0; $i < $solutionLength; $i++): 
                            $letter = $isSolved ? strtoupper($enigma['enigm_solution'][$i]) : '';
                        ?>
                            <div class="letter-box <?= $isSolved ? 'solved' : '' ?>" data-index="<?= $i ?>">
                                <?php if ($isSolved): ?>
                                    <span style="font-size: 2.5rem; font-weight: bold; color: #fff; text-transform: uppercase;">
                                        <?= htmlspecialchars($letter) ?>
                                    </span>
                                <?php else: ?>
                                    <input 
                                        type="text" 
                                        maxlength="1" 
                                        class="letter-input"
                                        data-index="<?= $i ?>"
                                        style="width: 100%; height: 100%; background: transparent; border: none; text-align: center; font-size: 2.5rem; font-weight: bold; color: #fff; text-transform: uppercase; outline: none;"
                                        autocomplete="off"
                                    />
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <?php if ($enigma['status'] != 2 && !$isViewingOtherTeam): ?>
                    <div style="text-align: center; margin-top: 40px;">
                        <button id="validateBtn" class="back-btn disabled" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); cursor: not-allowed; border: none;" disabled>
                            ✅ Valider la solution
                        </button>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; margin-top: 40px;">
                        <button class="back-btn disabled" style="cursor: not-allowed;">
                            <?php if ($isViewingOtherTeam): ?>
                                👀 Énigme résolue par cette équipe
                            <?php else: ?>
                                ✅ Solution validée
                            <?php endif; ?>
                        </button>
                    </div>
                <?php endif; ?>

                <div style="text-align: center; margin-top: 20px;">
                    <a href="game.php" class="back-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        ← Retour au jeu
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$show_error && $enigma && $enigma['status'] != 2): ?>
    <script>
        const solution = <?= json_encode($enigma['enigm_solution']) ?>;
        const inputs = document.querySelectorAll('.letter-input');
        const validateBtn = document.getElementById('validateBtn');
        const solutionContainer = document.querySelector('.solution-container');

        // Fonction pour vérifier si toutes les cases sont remplies
        function checkAllFieldsFilled() {
            let allFilled = true;
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    allFilled = false;
                }
            });
            return allFilled;
        }

        // Fonction pour mettre à jour l'état du bouton
        function updateButtonState() {
            const allFilled = checkAllFieldsFilled();
            if (allFilled) {
                validateBtn.classList.remove('disabled');
                validateBtn.disabled = false;
            } else {
                validateBtn.classList.add('disabled');
                validateBtn.disabled = true;
            }
        }

        // Fonction pour afficher un message d'erreur
        function showErrorMessage(message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.textContent = message;
            document.body.appendChild(errorDiv);
            
            // Supprimer après 3 secondes
            setTimeout(() => {
                if (errorDiv.parentNode) {
                    errorDiv.parentNode.removeChild(errorDiv);
                }
            }, 3000);
        }

        // Fonction pour afficher un message de succès
        function showSuccessMessage(message) {
            const successDiv = document.createElement('div');
            successDiv.className = 'success-message';
            successDiv.textContent = message;
            document.body.appendChild(successDiv);
            
            // Supprimer après 2 secondes
            setTimeout(() => {
                if (successDiv.parentNode) {
                    successDiv.parentNode.removeChild(successDiv);
                }
            }, 2000);
        }

        // Fonction pour lancer les feux d'artifice
        function launchFireworks() {
            const duration = 5000;
            const animationEnd = Date.now() + duration;
            const defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 25000 };
            
            function randomInRange(min, max) {
                return Math.random() * (max - min) + min;
            }
            
            const interval = setInterval(function() {
                const timeLeft = animationEnd - Date.now();
                
                if (timeLeft <= 0) {
                    return clearInterval(interval);
                }
                
                const particleCount = 50 * (timeLeft / duration);
                
                // Confettis depuis le centre
                confetti(Object.assign({}, defaults, {
                    particleCount,
                    origin: { x: 0.5, y: 0.5 }
                }));
                
                // Confettis depuis la gauche
                confetti(Object.assign({}, defaults, {
                    particleCount,
                    origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 }
                }));
                
                // Confettis depuis la droite
                confetti(Object.assign({}, defaults, {
                    particleCount,
                    origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 }
                }));
            }, 250);
        }

        // Fonction pour animer les erreurs
        function animateError() {
            // Ajouter la classe d'erreur au conteneur
            solutionContainer.classList.add('error');
            
            // Ajouter la classe d'erreur à toutes les cases
            inputs.forEach(input => {
                input.parentElement.classList.add('error');
            });
            
            // Supprimer les classes d'erreur après l'animation
            setTimeout(() => {
                solutionContainer.classList.remove('error');
                inputs.forEach(input => {
                    input.parentElement.classList.remove('error');
                });
            }, 600);
        }

        // Gérer la navigation entre les cases
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value.toUpperCase();
                e.target.value = value;

                // Passer à la case suivante automatiquement
                if (value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
                
                // Mettre à jour l'état du bouton
                updateButtonState();
            });

            // Gérer la touche retour arrière
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                }
                
                // Mettre à jour l'état du bouton
                updateButtonState();
            });
        });

        // Validation de la solution
        validateBtn.addEventListener('click', () => {
            let userSolution = '';
            inputs.forEach(input => {
                userSolution += input.value.toUpperCase();
            });

            if (userSolution.length !== solution.length) {
                showErrorMessage('⚠️ Veuillez remplir toutes les cases !');
                animateError();
                return;
            }

            if (userSolution === solution.toUpperCase()) {
                // Solution correcte !
                showSuccessMessage('🎉 Bravo ! La solution est correcte !');
                
                // Lancer les feux d'artifice
                launchFireworks();
                
                // Envoyer au serveur pour mettre à jour le status de l'énigme à 2
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=solve_enigma&day=' + <?= $selectedDay ?>
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        // Attendre un peu pour que l'utilisateur profite des feux d'artifice
                        setTimeout(() => {
                            window.location.href = 'teams.php?day=' + <?= $selectedDay ?>;
                        }, 3000);
                    }
                });
            } else {
                // Mauvaise solution
                showErrorMessage('❌ Solution incorrecte. Réessayez !');
                animateError();
                
                // Vider les cases
                inputs.forEach(input => input.value = '');
                inputs[0].focus();
                updateButtonState();
            }
        });

        // Initialiser l'état du bouton
        updateButtonState();

        // Focus automatique sur la première case
        if (inputs.length > 0) {
            inputs[0].focus();
        }
    </script>
    <?php endif; ?>
</body>
</html>

