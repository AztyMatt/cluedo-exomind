<?php
// Connexion à la base de données
require_once __DIR__ . '/../db-connection.php';
$dbConnection = getDBConnection();

// Fonctions pour gérer la date courante
function createCurrentDateTable($dbConnection) {
    try {
        $query = "CREATE TABLE IF NOT EXISTS `current_date` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `date` date NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $stmt = $dbConnection->prepare($query);
        $stmt->execute();
        
        // Insérer la date par défaut
        $query = "INSERT INTO current_date (id, date) VALUES (1, CURDATE())";
        $stmt = $dbConnection->prepare($query);
        $stmt->execute();
        
        return true;
    } catch (PDOException $e) {
        error_log("Erreur createCurrentDateTable: " . $e->getMessage());
        return false;
    }
}

function getCurrentDate($dbConnection) {
    global $debugInfo;
    
    try {
        $query = "SELECT `date` FROM `current_date` WHERE `id` = 1";
        $stmt = $dbConnection->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $debugInfo[] = "getCurrentDate - Requête exécutée";
        $debugInfo[] = "getCurrentDate - Résultat: " . ($result ? print_r($result, true) : 'NULL');
        
        if ($result && isset($result['date'])) {
            $debugInfo[] = "getCurrentDate - Date trouvée: " . $result['date'];
            return $result['date'];
        }
        
        $debugInfo[] = "getCurrentDate - Aucune date trouvée, utilisation de la date par défaut";
        
        // Si pas de résultat, créer la table et réessayer
        if (createCurrentDateTable($dbConnection)) {
            return date('Y-m-d');
        }
        
        return date('Y-m-d'); // Date par défaut si pas de données
    } catch (PDOException $e) {
        $debugInfo[] = "getCurrentDate - Erreur PDO: " . $e->getMessage();
        // Essayer de créer la table si elle n'existe pas
        if (strpos($e->getMessage(), "doesn't exist") !== false) {
            $debugInfo[] = "getCurrentDate - Table n'existe pas, création en cours...";
            createCurrentDateTable($dbConnection);
        }
        return date('Y-m-d');
    }
}

function updateCurrentDate($dbConnection, $newDate) {
    global $debugInfo;
    
    if (!$dbConnection) {
        $debugInfo[] = "updateCurrentDate: Pas de connexion à la base de données";
        return false;
    }
    
    try {
        // Valider la date
        $dateObj = DateTime::createFromFormat('Y-m-d', $newDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $newDate) {
            $debugInfo[] = "updateCurrentDate: Date invalide: " . $newDate;
            return false;
        }
        
        $debugInfo[] = "Date validée: " . $newDate;
        
        // Vérifier d'abord si l'enregistrement existe
        $checkQuery = "SELECT COUNT(*) as count FROM `current_date` WHERE `id` = 1";
        $checkStmt = $dbConnection->prepare($checkQuery);
        $checkStmt->execute();
        $count = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
        $debugInfo[] = "Nombre d'enregistrements avec id=1: " . $count;
        
        if ($count > 0) {
            // Mettre à jour l'enregistrement existant
            $query = "UPDATE `current_date` SET `date` = :date, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = 1";
            $stmt = $dbConnection->prepare($query);
            $stmt->bindParam(':date', $newDate);
            $stmt->execute();
            $debugInfo[] = "UPDATE exécuté, lignes affectées: " . $stmt->rowCount();
        } else {
            // Insérer un nouvel enregistrement
            $debugInfo[] = "Aucun enregistrement trouvé, insertion d'un nouvel enregistrement";
            $query = "INSERT INTO `current_date` (`id`, `date`) VALUES (1, :date)";
            $stmt = $dbConnection->prepare($query);
            $stmt->bindParam(':date', $newDate);
            $stmt->execute();
            $debugInfo[] = "INSERT exécuté, lignes affectées: " . $stmt->rowCount();
        }
        
        $debugInfo[] = "updateCurrentDate: Succès pour la date: " . $newDate;
        return true;
    } catch (PDOException $e) {
        $debugInfo[] = "Erreur PDO: " . $e->getMessage();
        $debugInfo[] = "Query: " . $query;
        $debugInfo[] = "Date: " . $newDate;
        
        // Si la table n'existe pas, la créer et réessayer
        if (strpos($e->getMessage(), "doesn't exist") !== false) {
            $debugInfo[] = "Table n'existe pas, création en cours...";
            if (createCurrentDateTable($dbConnection)) {
                // Réessayer l'insertion
                try {
                    $query = "INSERT INTO current_date (id, date) VALUES (1, :date)";
                    $stmt = $dbConnection->prepare($query);
                    $stmt->bindParam(':date', $newDate);
                    $result = $stmt->execute();
                    $debugInfo[] = "Réessai après création table: " . ($result ? "succès" : "échec");
                    return $result;
                } catch (PDOException $e2) {
                    $debugInfo[] = "Erreur lors du réessai: " . $e2->getMessage();
                }
            }
        }
        return false;
    }
}

// Traitement de la mise à jour de la date
$message = '';
$debugInfo = [];

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_date') {
    $debugInfo[] = "POST reçu - action: " . $_POST['action'];
    $debugInfo[] = "POST current_date: " . (isset($_POST['current_date']) ? $_POST['current_date'] : 'non défini');
    
    if (isset($_POST['current_date']) && !empty($_POST['current_date'])) {
        if (!$dbConnection) {
            $debugInfo[] = "Erreur: Pas de connexion à la base de données";
            $message = '<div class="alert alert-danger">Erreur de connexion à la base de données.</div>';
        } else {
            $debugInfo[] = "Connexion DB OK, tentative de mise à jour de la date: " . $_POST['current_date'];
            $result = updateCurrentDate($dbConnection, $_POST['current_date']);
            $debugInfo[] = "Résultat updateCurrentDate: " . ($result ? "SUCCÈS" : "ÉCHEC");
            
            if ($result) {
                $message = '<div class="alert alert-success">Date mise à jour avec succès !</div>';
            } else {
                $message = '<div class="alert alert-danger">Erreur lors de la mise à jour de la date.</div>';
            }
        }
    } else {
        $debugInfo[] = "Erreur: Date vide ou non définie";
        $message = '<div class="alert alert-danger">Veuillez sélectionner une date valide.</div>';
    }
}

// Récupérer la date courante
$currentDate = getCurrentDate($dbConnection);
$debugInfo[] = "Date récupérée de la DB: " . $currentDate;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de la Date - Cluedo</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 16px;
        }
        
        input[type="date"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        
        input[type="date"]:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }
        
        .btn {
            width: 100%;
            background: #28a745;
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn:hover {
            background: #218838;
        }
        
        .btn:active {
            transform: translateY(1px);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #6c757d;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }
        
        .back-link a:hover {
            color: #495057;
        }
        
        .current-date {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .current-date strong {
            color: #495057;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📅 Gestion de la Date</h1>
        
        <div class="current-date">
            <strong>Date actuelle :</strong> <?php echo date('d/m/Y', strtotime($currentDate)); ?>
        </div>
        
        <?php echo $message; ?>
        
        <?php if (!empty($debugInfo)): ?>
        <div class="debug-info" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <h3 style="margin-top: 0; color: #495057; font-size: 16px;">🔍 Informations de Debug :</h3>
            <ul style="margin: 0; padding-left: 20px; font-family: monospace; font-size: 12px; color: #6c757d;">
                <?php foreach ($debugInfo as $info): ?>
                    <li><?php echo htmlspecialchars($info); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="action" value="update_date">
            
            <div class="form-group">
                <label for="current_date">Nouvelle date du jeu :</label>
                <input type="date" id="current_date" name="current_date" value="<?php echo htmlspecialchars($currentDate); ?>" required>
            </div>
            
            <button type="submit" class="btn">
                ✅ Mettre à jour la date
            </button>
        </form>
        
        <div class="back-link">
            <a href="index.php">← Retour à l'éditeur</a>
        </div>
    </div>
</body>
</html>
