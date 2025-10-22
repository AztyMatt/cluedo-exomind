<?php
/**
 * Script d'initialisation de la base de données via PHP
 * Alternative au script bash pour éviter les problèmes d'espace disque
 */

// Configuration de la base de données
$host = $_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST');
$dbname = $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE');
$username = $_ENV['MYSQLUSER'] ?? getenv('MYSQLUSER');
$password = $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD');
$port = $_ENV['MYSQLPORT'] ?? getenv('MYSQLPORT');

if (!$host || !$dbname || !$username) {
    error_log("⚠️ Variables d'environnement MySQL manquantes.");
    return null;
}


// Mode automatique : toujours réinitialiser complètement

// Couleurs pour les messages
define('RED', "\033[0;31m");
define('GREEN', "\033[0;32m");
define('YELLOW', "\033[1;33m");
define('BLUE', "\033[0;34m");
define('NC', "\033[0m");

function logMessage($message, $color = NC) {
    echo $color . $message . NC . "\n";
}

function connectToDatabase() {
    global $DB_HOST, $DB_USER, $DB_PASSWORD, $DB_PORT;
    
    $maxRetries = 5;
    $retryCount = 0;
    
    while ($retryCount < $maxRetries) {
        try {
            $pdo = new PDO("mysql:host=$DB_HOST;port=$DB_PORT;charset=utf8mb4", $DB_USER, $DB_PASSWORD);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            logMessage("✓ Connexion à MySQL réussie!", GREEN);
            return $pdo;
        } catch (PDOException $e) {
            $retryCount++;
            if ($retryCount < $maxRetries) {
                logMessage("Tentative $retryCount/$maxRetries... Attente de 2 secondes...", YELLOW);
                sleep(2);
            }
        }
    }
    
    logMessage("✗ Impossible de se connecter à MySQL après $maxRetries tentatives!", RED);
    exit(1);
}

function executeSqlFile($pdo, $filename) {
    if (!file_exists($filename)) {
        logMessage("⚠ Fichier $filename non trouvé (ignoré)", YELLOW);
        return true;
    }
    
    try {
        $sql = file_get_contents($filename);
        
        // Utiliser mysqli pour les fichiers avec des instructions complexes
        if (basename($filename) !== 'init.sql') {
            // Pour les fichiers d'inserts, utiliser mysqli qui gère mieux les instructions multi-lignes
            $mysqli = new mysqli($GLOBALS['DB_HOST'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWORD'], $GLOBALS['DB_NAME'], $GLOBALS['DB_PORT']);
            if ($mysqli->connect_error) {
                throw new Exception("Connexion mysqli échouée: " . $mysqli->connect_error);
            }
            
            $mysqli->set_charset("utf8mb4");
            
            if ($mysqli->multi_query($sql)) {
                do {
                    if ($result = $mysqli->store_result()) {
                        $result->free();
                    }
                } while ($mysqli->next_result());
            }
            
            $mysqli->close();
        } else {
            // Pour init.sql, utiliser PDO normal
            $pdo->exec($sql);
        }
        
        logMessage("✓ $filename exécuté avec succès", GREEN);
        return true;
    } catch (Exception $e) {
        logMessage("✗ Erreur lors de l'exécution de $filename: " . $e->getMessage(), RED);
        return false;
    }
}

// Début du script
logMessage("═══════════════════════════════════════════════════", BLUE);
logMessage("  Initialisation automatique de la base de données", BLUE);
logMessage("═══════════════════════════════════════════════════", BLUE);
logMessage("");

logMessage("Attente de la disponibilité de MySQL...", YELLOW);
$pdo = connectToDatabase();

// Supprimer la base si elle existe déjà (mode automatique)
try {
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='$DB_NAME'");
    if ($stmt->rowCount() > 0) {
        logMessage("Suppression de la base de données existante...", YELLOW);
        $pdo->exec("DROP DATABASE $DB_NAME");
        logMessage("✓ Base de données supprimée", GREEN);
    }
} catch (PDOException $e) {
    // Base n'existe pas, continuer
}

logMessage("[1/3] Création de la base de données '$DB_NAME'...", YELLOW);
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    logMessage("✓ Base de données créée", GREEN);
} catch (PDOException $e) {
    logMessage("✗ Erreur lors de la création de la base: " . $e->getMessage(), RED);
    exit(1);
}

logMessage("[2/3] Création des tables depuis init.sql...", YELLOW);
$pdo->exec("USE $DB_NAME");
if (!executeSqlFile($pdo, 'init.sql')) {
    exit(1);
}

logMessage("[3/3] Insertion des données depuis sql/inserts/...", YELLOW);
$successCount = 0;
$errorCount = 0;

if (is_dir('sql/inserts')) {
    $files = glob('sql/inserts/*.sql');
    foreach ($files as $file) {
        logMessage("  → Exécution de " . basename($file) . "...", BLUE);
        if (executeSqlFile($pdo, $file)) {
            $successCount++;
        } else {
            $errorCount++;
        }
    }
    
    if ($errorCount === 0 && $successCount > 0) {
        logMessage("✓ Tous les fichiers d'inserts ont été exécutés avec succès ($successCount fichier(s))", GREEN);
    } elseif ($successCount === 0) {
        logMessage("⚠ Aucun fichier d'insert trouvé dans sql/inserts", YELLOW);
    } else {
        logMessage("✗ Des erreurs sont survenues: $errorCount fichier(s) en erreur", RED);
        exit(1);
    }
} else {
    logMessage("⚠ Aucun dossier d'inserts trouvé (ignoré)", YELLOW);
}

logMessage("");
logMessage("═══════════════════════════════════════════════════", BLUE);
logMessage("✓ Initialisation de la base de données terminée!", GREEN);
logMessage("═══════════════════════════════════════════════════", BLUE);

exit(0);
