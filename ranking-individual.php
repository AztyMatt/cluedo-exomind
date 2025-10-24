<?php
// Connexion à la base de données
require_once __DIR__ . '/db-connection.php';
$dbConnection = getDBConnection();

// Vérifier la connexion avant de continuer
if (!$dbConnection) {
    error_log("Erreur critique: Impossible de se connecter à la base de données");
    die("Erreur de connexion à la base de données");
}

// Fonction pour calculer le jour du jeu basé sur la date courante
function getGameDay($dbConnection) {
    if (!$dbConnection) {
        error_log("Erreur: Connexion à la base de données échouée dans getGameDay()");
        return 1; // Retourner jour 1 par défaut
    }
    
    try {
        // Récupérer la date courante de la base de données
        $query = "SELECT `date` FROM `current_date` WHERE `id` = 1";
        $stmt = $dbConnection->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && isset($result['date'])) {
            $currentDate = new DateTime($result['date']);
        } else {
            // Si pas de date en base, utiliser la date actuelle
            $currentDate = new DateTime();
        }
        
        // Date de référence : 27 octobre 2025 = Jour 1
        $referenceDate = new DateTime('2025-10-27');
        
        // Calculer la différence en jours
        $diff = $currentDate->diff($referenceDate);
        $daysDiff = $diff->days;
        
        // Si la date courante est avant le 27/10/2025, retourner jour 1
        if ($currentDate < $referenceDate) {
            return 1;
        }
        
        // Calculer le jour : 27/10 = jour 1, 28/10 = jour 2, 29/10 = jour 3
        $gameDay = $daysDiff + 1;
        
        // Limiter à jour 3 maximum, sinon retourner jour 1
        if ($gameDay > 3) {
            return 1;
        }
        
        return $gameDay;
        
    } catch (Exception $e) {
        // En cas d'erreur, retourner jour 1 par défaut
        return 1;
    }
}

// Fonction pour formater le nom : Prénom NOM
function formatUserName($firstname, $lastname) {
    // Formater : première lettre majuscule pour le prénom, tout en majuscules pour le nom
    $formattedFirstName = ucfirst(strtolower($firstname));
    $formattedLastName = strtoupper($lastname);
    
    return $formattedFirstName . ' ' . $formattedLastName;
}

// Calculer le jour du jeu
$gameDay = getGameDay($dbConnection);

// TODO: Ajouter ici la logique pour récupérer les données individuelles
// et calculer le classement individuel selon vos besoins

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

        .coming-soon {
            text-align: center;
            font-size: 1.5rem;
            color: #ff6b35;
            padding: 60px 20px;
            background: rgba(255, 107, 53, 0.1);
            border-radius: 15px;
            border: 2px dashed rgba(255, 107, 53, 0.3);
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
            <div class="coming-soon">
                🚧 Classement Individuel - En cours de développement 🚧
                <br><br>
                <small>Cette page sera bientôt disponible avec le classement des joueurs individuels.</small>
            </div>
        </div>
    </div>

    <script>
        // Page de classement individuel - pas de boutons de navigation supplémentaires
    </script>
</body>
</html>
