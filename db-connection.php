<?php
/**
 * Fichier de connexion à la base de données MySQL
 */

function getDBConnection() {
    $host = $_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST') ?? 'db';
    $dbname = $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?? 'cluedo';
    $username = $_ENV['MYSQLUSER'] ?? getenv('MYSQLUSER') ?? 'cluedo_user';
    $password = $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?? 'cluedo_password';
    $port = $_ENV['MYSQLPORT'] ?? getenv('MYSQLPORT') ?? '3306';

    // Utiliser des valeurs par défaut si les variables d'environnement ne sont pas définies
    if (!$host || !$dbname || !$username) {
        error_log("⚠️ Variables d'environnement MySQL manquantes, utilisation des valeurs par défaut.");
        $host = 'db';
        $dbname = 'cluedo';
        $username = 'cluedo_user';
        $password = 'cluedo_password';
        $port = '3306';
    }    
    
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        error_log("Connexion MySQL réussie à la base '$dbname' sur l'hôte '$host:$port'");
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erreur de connexion MySQL: " . $e->getMessage());
        return null;
    }
}