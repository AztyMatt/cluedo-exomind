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
            // Mettre à jour le status de l'énigme à 2 (résolue)
            $stmt = $dbConnection->prepare("UPDATE `enigmes` SET status = 2, solved = TRUE, datetime_solved = NOW() WHERE id_group = ? AND id_day = ?");
            $stmt->execute([$user['group_id'], $dayId]);
            
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
            // Vérifier si tous les papiers ont été trouvés pour ce jour
            $stmt = $dbConnection->prepare("SELECT total_to_found, total_founded FROM `total_papers_found_group` WHERE id_group = ? AND id_day = ?");
            $stmt->execute([$user['group_id'], $selectedDay]);
            $paperStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$paperStats || $paperStats['total_founded'] < $paperStats['total_to_found']) {
                $found = $paperStats ? $paperStats['total_founded'] : 0;
                $total = $paperStats ? $paperStats['total_to_found'] : 0;
                $error_message = "🔒 Votre équipe n'a pas encore trouvé tous les papiers pour ce jour.<br>Papiers trouvés : <strong>$found / $total</strong><br><br>Continuez à chercher !";
                $show_error = true;
            } else {
                // Récupérer l'énigme pour ce jour et cette équipe
                $stmt = $dbConnection->prepare("SELECT enigm_label, enigm_solution, status FROM `enigmes` WHERE id_group = ? AND id_day = ?");
                $stmt->execute([$user['group_id'], $selectedDay]);
                $enigma = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$enigma) {
                    $error_message = "❌ Aucune énigme n'a été configurée pour votre équipe et ce jour.";
                    $show_error = true;
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
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.1);
            margin-top: 100px;
        }

        .enigma-title {
            font-size: 2.5rem;
            color: #fff;
            text-align: center;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
        }

        .enigma-subtitle {
            font-size: 1.2rem;
            color: <?= htmlspecialchars($user['team_color'] ?? '#888') ?>;
            text-align: center;
            margin-bottom: 40px;
            font-weight: bold;
        }

        .enigma-text {
            font-size: 1.3rem;
            color: #eee;
            text-align: center;
            line-height: 2;
            margin-bottom: 50px;
            padding: 30px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            border-left: 4px solid <?= htmlspecialchars($user['team_color'] ?? '#888') ?>;
        }

        .solution-container {
            margin-top: 40px;
        }

        .solution-label {
            font-size: 1.5rem;
            color: #fff;
            text-align: center;
            margin-bottom: 30px;
            font-weight: bold;
        }

        .solution-boxes {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .letter-box {
            width: 60px;
            height: 80px;
            background: rgba(255, 255, 255, 0.1);
            border: 3px solid <?= htmlspecialchars($user['team_color'] ?? '#888') ?>;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
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

        .error-container {
            background: rgba(235, 51, 73, 0.2);
            border-left: 4px solid #eb3349;
            padding: 30px;
            border-radius: 12px;
            margin-top: 100px;
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
            margin-bottom: 30px;
            padding: 15px;
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
                <div class="day-indicator">
                    <h3><?= $currentDay['number'] ?></h3>
                    <p><?= $currentDay['objective'] ?></p>
                </div>

                <h1 class="enigma-title">🎭 Énigme de votre équipe</h1>
                <div class="enigma-subtitle"><?= htmlspecialchars($user['team_name']) ?> - <?= htmlspecialchars($user['pole_name']) ?></div>

                <div class="enigma-text">
                    <?= nl2br(htmlspecialchars($enigma['enigm_label'])) ?>
                </div>

                <div class="solution-container">
                    <div class="solution-label">💡 Entrez votre solution :</div>
                    <div class="solution-boxes">
                        <?php 
                        $solutionLength = strlen($enigma['enigm_solution']);
                        for ($i = 0; $i < $solutionLength; $i++): 
                        ?>
                            <div class="letter-box" data-index="<?= $i ?>">
                                <input 
                                    type="text" 
                                    maxlength="1" 
                                    class="letter-input"
                                    data-index="<?= $i ?>"
                                    style="width: 100%; height: 100%; background: transparent; border: none; text-align: center; font-size: 2.5rem; font-weight: bold; color: #fff; text-transform: uppercase; outline: none;"
                                    autocomplete="off"
                                />
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 40px;">
                    <button id="validateBtn" class="back-btn" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); cursor: pointer; border: none;">
                        ✅ Valider la solution
                    </button>
                </div>

                <div style="text-align: center; margin-top: 20px;">
                    <a href="game.php" class="back-btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        ← Retour au jeu
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$show_error && $enigma): ?>
    <script>
        const solution = <?= json_encode($enigma['enigm_solution']) ?>;
        const inputs = document.querySelectorAll('.letter-input');
        const validateBtn = document.getElementById('validateBtn');

        // Gérer la navigation entre les cases
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value.toUpperCase();
                e.target.value = value;

                // Passer à la case suivante automatiquement
                if (value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });

            // Gérer la touche retour arrière
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });
        });

        // Validation de la solution
        validateBtn.addEventListener('click', () => {
            let userSolution = '';
            inputs.forEach(input => {
                userSolution += input.value.toUpperCase();
            });

            if (userSolution.length !== solution.length) {
                alert('⚠️ Veuillez remplir toutes les cases !');
                return;
            }

            if (userSolution === solution.toUpperCase()) {
                // Solution correcte !
                alert('🎉 Bravo ! La solution est correcte !\n\nL\'énigme a été résolue.');
                
                // TODO: Envoyer au serveur pour mettre à jour le status de l'énigme à 2
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=solve_enigma&day=' + <?= $selectedDay ?>
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        window.location.href = 'teams.php?day=' + <?= $selectedDay ?>;
                    }
                });
            } else {
                // Mauvaise solution
                alert('❌ Solution incorrecte.\n\nRéessayez !');
                
                // Vider les cases
                inputs.forEach(input => input.value = '');
                inputs[0].focus();
            }
        });

        // Focus automatique sur la première case
        if (inputs.length > 0) {
            inputs[0].focus();
        }
    </script>
    <?php endif; ?>
</body>
</html>

