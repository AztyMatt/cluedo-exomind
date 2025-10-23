<?php
// Script pour générer automatiquement les fichiers SQL d'insertion
// à partir des données sauvegardées en base de données

require_once 'db-connection.php';

try {
    $dbConnection = getDBConnection();
    
    if (!$dbConnection) {
        die("Erreur de connexion à la base de données");
    }
    
    echo "🔄 Génération des fichiers SQL d'insertion...\n";
    
    // ========== GÉNÉRATION PAPERS.SQL ==========
    echo "📄 Génération de data/papers.sql...\n";
    
    $stmt = $dbConnection->prepare("
        SELECT p.id, p.photo_id, p.position_left, p.position_top, p.scale_x, p.scale_y, p.angle, p.z_index, p.paper_type, ph.filename
        FROM papers p 
        LEFT JOIN photos ph ON p.photo_id = ph.id 
        ORDER BY p.id ASC
    ");
    $stmt->execute();
    $papers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $papersSql = "SET NAMES utf8mb4;\n\n";
    $papersSql .= "INSERT INTO `papers` (`id`, `photo_id`, `position_left`, `position_top`, `scale_x`, `scale_y`, `angle`, `z_index`, `paper_type`, `created_at`, `updated_at`) VALUES\n";
    
    $paperValues = [];
    foreach ($papers as $paper) {
        $paperValues[] = sprintf(
            "(%d, %d, %.10f, %.10f, %.6f, %.6f, %.6f, %d, %d, '%s', '%s')",
            $paper['id'],
            $paper['photo_id'],
            $paper['position_left'],
            $paper['position_top'],
            $paper['scale_x'],
            $paper['scale_y'],
            $paper['angle'],
            $paper['z_index'],
            $paper['paper_type'],
            $paper['created_at'] ?? date('Y-m-d H:i:s'),
            $paper['updated_at'] ?? date('Y-m-d H:i:s')
        );
    }
    
    $papersSql .= implode(",\n", $paperValues) . ";\n";
    
    // Sauvegarder le fichier papers.sql
    file_put_contents('data/papers.sql', $papersSql);
    echo "✅ data/papers.sql généré avec " . count($papers) . " papiers\n";
    
    // ========== GÉNÉRATION MASKS.SQL ==========
    echo "🎭 Génération de data/masks.sql...\n";
    
    $stmt = $dbConnection->prepare("
        SELECT m.id, m.photo_id, m.original_points, m.curve_handles, m.position_left, m.position_top, m.z_index, ph.filename
        FROM masks m 
        LEFT JOIN photos ph ON m.photo_id = ph.id 
        ORDER BY m.id ASC
    ");
    $stmt->execute();
    $masks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $masksSql = "SET NAMES utf8mb4;\n\n";
    $masksSql .= "INSERT INTO `masks` (`id`, `photo_id`, `original_points`, `curve_handles`, `position_left`, `position_top`, `z_index`, `created_at`, `updated_at`) VALUES\n";
    
    $maskValues = [];
    foreach ($masks as $mask) {
        $maskValues[] = sprintf(
            "(%d, %d, '%s', '%s', %.10f, %.10f, %d, '%s', '%s')",
            $mask['id'],
            $mask['photo_id'],
            addslashes($mask['original_points']),
            addslashes($mask['curve_handles']),
            $mask['position_left'],
            $mask['position_top'],
            $mask['z_index'],
            $mask['created_at'] ?? date('Y-m-d H:i:s'),
            $mask['updated_at'] ?? date('Y-m-d H:i:s')
        );
    }
    
    $masksSql .= implode(",\n", $maskValues) . ";\n";
    
    // Sauvegarder le fichier masks.sql
    file_put_contents('data/masks.sql', $masksSql);
    echo "✅ data/masks.sql généré avec " . count($masks) . " masques\n";
    
    // ========== GÉNÉRATION ARROWS.SQL ==========
    echo "➡️ Génération de data/arrows.sql...\n";
    
    $stmt = $dbConnection->prepare("
        SELECT a.id, a.photo_id, a.target_photo_id, a.position_left, a.position_top, a.angle, a.active, a.free_placement, ph.filename, tph.filename as target_filename
        FROM arrows a 
        LEFT JOIN photos ph ON a.photo_id = ph.id 
        LEFT JOIN photos tph ON a.target_photo_id = tph.id 
        ORDER BY a.id ASC
    ");
    $stmt->execute();
    $arrows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $arrowsSql = "SET NAMES utf8mb4;\n\n";
    $arrowsSql .= "INSERT INTO `arrows` (`id`, `photo_id`, `target_photo_id`, `position_left`, `position_top`, `angle`, `active`, `free_placement`, `created_at`, `updated_at`) VALUES\n";
    
    $arrowValues = [];
    foreach ($arrows as $arrow) {
        $arrowValues[] = sprintf(
            "(%d, %d, %s, %.10f, %.10f, %.6f, %d, %d, '%s', '%s')",
            $arrow['id'],
            $arrow['photo_id'],
            $arrow['target_photo_id'] ? $arrow['target_photo_id'] : 'NULL',
            $arrow['position_left'],
            $arrow['position_top'],
            $arrow['angle'],
            $arrow['active'] ? 1 : 0,
            $arrow['free_placement'] ? 1 : 0,
            $arrow['created_at'] ?? date('Y-m-d H:i:s'),
            $arrow['updated_at'] ?? date('Y-m-d H:i:s')
        );
    }
    
    $arrowsSql .= implode(",\n", $arrowValues) . ";\n";
    
    // Sauvegarder le fichier arrows.sql
    file_put_contents('data/arrows.sql', $arrowsSql);
    echo "✅ data/arrows.sql généré avec " . count($arrows) . " flèches\n";
    
    // ========== RÉSUMÉ ==========
    echo "\n🎉 Génération terminée avec succès !\n";
    echo "📊 Résumé :\n";
    echo "   - " . count($papers) . " papiers (dont " . count(array_filter($papers, function($p) { return $p['paper_type'] == 1; })) . " dorés)\n";
    echo "   - " . count($masks) . " masques\n";
    echo "   - " . count($arrows) . " flèches\n";
    echo "\n📁 Fichiers générés :\n";
    echo "   - data/papers.sql\n";
    echo "   - data/masks.sql\n";
    echo "   - data/arrows.sql\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur lors de la génération : " . $e->getMessage() . "\n";
}
?>
