<?php
// Démarrer la session avec une durée de vie prolongée
session_start([
    'cookie_lifetime' => 86400 * 7, // 7 jours
    'cookie_secure' => false,        // Mettre à true en production avec HTTPS
    'cookie_httponly' => true,       // Empêche l'accès JavaScript aux cookies
    'cookie_samesite' => 'Strict'    // Protection CSRF
]);

// Connexion à la base de données
require_once __DIR__ . '/db-connection.php';
$dbConnection = getDBConnection();

// Fonction pour formater le nom : Prénom NOM
function formatUserName($firstname, $lastname) {
    return ucfirst(strtolower($firstname)) . ' ' . strtoupper($lastname);
}

// ========== TRAITEMENT DU FORMULAIRE D'ACTIVATION ==========
$error_message = '';
$show_activation_form = false;
$user = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activation_code'])) {
    $activation_code = strtoupper(trim($_POST['activation_code']));
    
    if ($dbConnection) {
        try {
            // Vérifier si le code existe
            $stmt = $dbConnection->prepare("SELECT u.*, g.name as team_name, g.pole_name, g.color as team_color, g.img_path as team_img FROM `users` u LEFT JOIN `groups` g ON u.group_id = g.id WHERE u.activation_code = ?");
            $stmt->execute([$activation_code]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Code valide ! Activer l'utilisateur
                $stmt = $dbConnection->prepare("UPDATE `users` SET has_activated = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Créer le cookie avec le code d'activation (durée : 30 jours)
                setcookie('cluedo_activation', $activation_code, [
                    'expires' => time() + (86400 * 30),
                    'path' => '/',
                    'secure' => false,  // Mettre à true en production avec HTTPS
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
                
                // Créer la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['activation_code'] = $activation_code;
                $_SESSION['has_activated'] = 1;
                
                // Recharger la page pour afficher le jeu
                header('Location: game.php');
                exit;
            } else {
                $error_message = "❌ Code d'activation invalide. Veuillez réessayer.";
                $show_activation_form = true;
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification du code: " . $e->getMessage());
            $error_message = "⚠️ Erreur lors de la vérification. Veuillez réessayer.";
            $show_activation_form = true;
        }
    }
}

// ========== VÉRIFICATION DU COOKIE ET DE LA SESSION ==========
if (!$show_activation_form) {
    // Vérifier si le cookie existe
    $activation_code_cookie = $_COOKIE['cluedo_activation'] ?? null;
    
    if ($activation_code_cookie && $dbConnection) {
        try {
            // Vérifier si le code du cookie existe en base
            $stmt = $dbConnection->prepare("SELECT u.*, g.name as team_name, g.pole_name, g.color as team_color, g.img_path as team_img FROM `users` u LEFT JOIN `groups` g ON u.group_id = g.id WHERE u.activation_code = ? AND u.has_activated = 1");
            $stmt->execute([$activation_code_cookie]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Cookie valide ! Créer/mettre à jour la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['activation_code'] = $activation_code_cookie;
                $_SESSION['has_activated'] = 1;
            } else {
                // Cookie invalide ou utilisateur non activé -> demander le code
                $show_activation_form = true;
                // Supprimer le cookie invalide
                setcookie('cluedo_activation', '', time() - 3600, '/');
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification du cookie: " . $e->getMessage());
            $show_activation_form = true;
        }
    } else {
        // Pas de cookie -> afficher le formulaire
        $show_activation_form = true;
    }
}

// Si on doit afficher le formulaire, on s'arrête ici
if ($show_activation_form) {
    // Le formulaire sera affiché plus bas dans le HTML
} else if (!$user) {
    // Sécurité supplémentaire : si on arrive ici sans utilisateur, redemander le code
    $show_activation_form = true;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cluedo - Jeu en cours</title>
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
            max-width: 1400px;
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

        .game-content {
            background: rgba(42, 42, 42, 0.95);
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.1);
            min-height: 500px;
        }

        .game-title {
            font-size: 2rem;
            color: #fff;
            text-align: center;
            margin-bottom: 30px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
        }

        .game-description {
            font-size: 1.2rem;
            color: #ccc;
            text-align: center;
            line-height: 1.8;
            margin-bottom: 30px;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 40px;
        }

        .action-btn {
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

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .welcome-message {
            background: rgba(102, 126, 234, 0.2);
            border-left: 4px solid <?= htmlspecialchars($user['team_color'] ?? '#888') ?>;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .welcome-message h3 {
            color: #fff;
            margin-bottom: 10px;
        }

        .welcome-message p {
            color: #ccc;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($show_activation_form): ?>
            <!-- ========== FORMULAIRE D'ACTIVATION ========== -->
            <div class="game-content" style="max-width: 600px; margin: 100px auto;">
                <h1 class="game-title">🔐 Activation de votre compte</h1>
                
                <div class="game-description" style="margin-bottom: 40px;">
                    <p>Pour accéder au jeu Cluedo, veuillez entrer votre code d'activation unique.</p>
                    <p style="font-size: 0.9rem; color: #aaa; margin-top: 15px;">Ce code vous a été fourni avec votre invitation au jeu.</p>
                </div>

                <?php if ($error_message): ?>
                    <div style="background: rgba(235, 51, 73, 0.2); border-left: 4px solid #eb3349; padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #ff6b6b;">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="game.php" style="display: flex; flex-direction: column; gap: 20px;">
                    <div>
                        <label for="activation_code" style="display: block; margin-bottom: 10px; font-size: 1rem; color: #fff; font-weight: bold;">
                            Code d'activation
                        </label>
                        <input 
                            type="text" 
                            id="activation_code" 
                            name="activation_code" 
                            placeholder="Ex: A7K9X2"
                            required
                            maxlength="10"
                            style="width: 100%; padding: 15px; font-size: 1.2rem; border: 2px solid #555; border-radius: 8px; background: #1a1a1a; color: #fff; text-transform: uppercase; letter-spacing: 2px; text-align: center; font-weight: bold;"
                            autocomplete="off"
                        />
                    </div>

                    <button type="submit" class="action-btn btn-success" style="width: 100%; margin: 0;">
                        ✅ Activer mon compte
                    </button>
                </form>

                <div style="text-align: center; margin-top: 30px;">
                    <a href="teams.php" style="color: #667eea; text-decoration: none; font-size: 0.95rem;">
                        ← Retour à la page des équipes
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- ========== CONTENU DU JEU (utilisateur activé) ========== -->
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
    </div>
</body>
</html>

