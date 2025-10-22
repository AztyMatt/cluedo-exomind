<?php
/**
 * Fichier de connexion à la base de données MySQL
 */

function getDBConnection() {
    $host = $_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST');
    $dbname = $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE');
    $username = $_ENV['MYSQLUSER'] ?? getenv('MYSQLUSER');
    $password = $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD');
    $port = $_ENV['MYSQLPORT'] ?? getenv('MYSQLPORT');

    if (!$host || !$dbname || !$username) {
        error_log("⚠️ Variables d'environnement MySQL manquantes.");
        return null;
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