<?php
// Script de débogage pour les objets placés
require_once __DIR__ . '/db-connection.php';
$dbConnection = getDBConnection();

if (!$dbConnection) {
    die("Erreur de connexion à la base de données");
}

echo "<h1>Débogage des objets placés</h1>";

try {
    // Test 1: Vérifier si la table items existe et contient des données
    echo "<h2>1. Vérification de la table items</h2>";
    $query1 = "SELECT COUNT(*) as total FROM items";
    $stmt1 = $dbConnection->prepare($query1);
    $stmt1->execute();
    $result1 = $stmt1->fetch(PDO::FETCH_ASSOC);
    echo "Total items: " . $result1['total'] . "<br>";
    
    // Test 2: Vérifier les items résolus
    echo "<h2>2. Items résolus</h2>";
    $query2 = "SELECT COUNT(*) as solved FROM items WHERE solved = 1";
    $stmt2 = $dbConnection->prepare($query2);
    $stmt2->execute();
    $result2 = $stmt2->fetch(PDO::FETCH_ASSOC);
    echo "Items résolus: " . $result2['solved'] . "<br>";
    
    // Test 3: Vérifier les items avec datetime_solved
    echo "<h2>3. Items avec datetime_solved</h2>";
    $query3 = "SELECT COUNT(*) as with_datetime FROM items WHERE solved = 1 AND datetime_solved IS NOT NULL";
    $stmt3 = $dbConnection->prepare($query3);
    $stmt3->execute();
    $result3 = $stmt3->fetch(PDO::FETCH_ASSOC);
    echo "Items avec datetime_solved: " . $result3['with_datetime'] . "<br>";
    
    // Test 4: Vérifier les utilisateurs
    echo "<h2>4. Vérification des utilisateurs</h2>";
    $query4 = "SELECT COUNT(*) as users FROM users";
    $stmt4 = $dbConnection->prepare($query4);
    $stmt4->execute();
    $result4 = $stmt4->fetch(PDO::FETCH_ASSOC);
    echo "Total utilisateurs: " . $result4['users'] . "<br>";
    
    // Test 5: Vérifier les groupes
    echo "<h2>5. Vérification des groupes</h2>";
    $query5 = "SELECT COUNT(*) as groups FROM `groups`";
    $stmt5 = $dbConnection->prepare($query5);
    $stmt5->execute();
    $result5 = $stmt5->fetch(PDO::FETCH_ASSOC);
    echo "Total groupes: " . $result5['groups'] . "<br>";
    
    // Test 6: Requête complète avec gestion d'erreur
    echo "<h2>6. Test de la requête complète</h2>";
    $query6 = "
        SELECT 
            i.id,
            i.path,
            i.title,
            i.subtitle,
            i.datetime_solved as datetime,
            u.firstname,
            u.lastname,
            g.name as team_name,
            g.color as team_color
        FROM items i
        INNER JOIN users u ON i.id_solved_user = u.id
        INNER JOIN groups g ON u.group_id = g.id
        WHERE i.solved = 1 
        AND i.datetime_solved IS NOT NULL
        ORDER BY i.datetime_solved DESC
    ";
    
    $stmt6 = $dbConnection->prepare($query6);
    $stmt6->execute();
    $objects = $stmt6->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Nombre d'objets trouvés: " . count($objects) . "<br>";
    
    if (count($objects) > 0) {
        echo "<h3>Objets trouvés:</h3>";
        foreach ($objects as $obj) {
            echo "- " . $obj['title'] . " par " . $obj['firstname'] . " " . $obj['lastname'] . " (" . $obj['team_name'] . ") le " . $obj['datetime'] . "<br>";
        }
    } else {
        echo "Aucun objet trouvé.<br>";
        
        // Test 7: Vérifier les items résolus sans JOIN
        echo "<h2>7. Items résolus sans JOIN</h2>";
        $query7 = "
            SELECT 
                i.id,
                i.title,
                i.solved,
                i.id_solved_user,
                i.datetime_solved
            FROM items i
            WHERE i.solved = 1
        ";
        
        $stmt7 = $dbConnection->prepare($query7);
        $stmt7->execute();
        $items = $stmt7->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Items résolus (sans JOIN): " . count($items) . "<br>";
        foreach ($items as $item) {
            echo "- ID: " . $item['id'] . ", Titre: " . $item['title'] . ", User ID: " . $item['id_solved_user'] . ", Datetime: " . $item['datetime_solved'] . "<br>";
        }
    }
    
} catch (PDOException $e) {
    echo "Erreur PDO: " . $e->getMessage() . "<br>";
    echo "Code d'erreur: " . $e->getCode() . "<br>";
}
?>
