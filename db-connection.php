<?php
/**
 * Fichier de connexion à la base de données MySQL
 */

function getDBConnection() {
    $host = 'db';
    $dbname = 'cluedo';
    $username = 'admin';
    $password = 'hall0w33n';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        error_log("Connexion MySQL réussie à la base '$dbname' sur l'hôte '$host'");
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erreur de connexion MySQL: " . $e->getMessage());
        return null;
    }
}