<?php
// Script de migration pour ajouter le champ paper_type
require_once 'db-connection.php';

try {
    $dbConnection = getDBConnection();
    
    if (!$dbConnection) {
        die("Erreur de connexion à la base de données");
    }
    
    echo "🔄 Exécution de la migration pour ajouter paper_type...\n";
    
    // Vérifier si la colonne existe déjà
    $stmt = $dbConnection->prepare("SHOW COLUMNS FROM `papers` LIKE 'paper_type'");
    $stmt->execute();
    $columnExists = $stmt->fetch();
    
    if ($columnExists) {
        echo "✅ La colonne paper_type existe déjà\n";
    } else {
        // Ajouter la colonne
        $sql = "ALTER TABLE `papers` ADD COLUMN `paper_type` TINYINT NOT NULL DEFAULT 0 COMMENT '0=blanc, 1=doré' AFTER `z_index`";
        $dbConnection->exec($sql);
        echo "✅ Colonne paper_type ajoutée\n";
    }
    
    // Mettre à jour les papiers existants
    $stmt = $dbConnection->prepare("UPDATE `papers` SET `paper_type` = 0 WHERE `paper_type` IS NULL");
    $stmt->execute();
    $affectedRows = $stmt->rowCount();
    echo "✅ $affectedRows papiers mis à jour avec paper_type = 0\n";
    
    // Vérifier le résultat
    $stmt = $dbConnection->prepare("SELECT COUNT(*) as total FROM `papers`");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "📊 Total de papiers en base : " . $result['total'] . "\n";
    
    echo "🎉 Migration terminée avec succès !\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur lors de la migration : " . $e->getMessage() . "\n";
}
?>
