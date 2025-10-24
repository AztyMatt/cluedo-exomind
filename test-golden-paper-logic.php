<?php
// Script de test pour vérifier la logique des papiers dorés
require_once __DIR__ . '/db-connection.php';

// Fonction pour récupérer les informations des papiers dorés selon la logique demandée
function getGoldenPaperInfo($dbConnection, $day) {
    if (!$dbConnection) {
        return null;
    }
    
    try {
        // Sélectionner tous les IDs des papiers dorés (trouvés ou pas) en tri ID ascendant
        // La logique : paper_type = 1 correspond au papier doré
        // Pour le jour 1 : premier ID trouvé avec paper_type = 1
        // Pour le jour 2 : deuxième ID trouvé avec paper_type = 1  
        // Pour le jour 3 : troisième ID trouvé avec paper_type = 1
        
        $query = "
            SELECT 
                pf.id,
                pf.id_player,
                pf.id_day,
                pf.created_at as datetime,
                u.firstname,
                u.lastname,
                g.name as team_name,
                g.color as team_color
            FROM papers_found_user pf
            INNER JOIN users u ON pf.id_player = u.id
            INNER JOIN `groups` g ON u.group_id = g.id
            INNER JOIN papers p ON pf.id_paper = p.id
            WHERE p.paper_type = 1 AND pf.id_day = ?
            ORDER BY pf.id ASC
            LIMIT 1
        ";
        
        $stmt = $dbConnection->prepare($query);
        $stmt->execute([$day]); // Chercher le papier doré trouvé pour le jour spécifique
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Papier doré trouvé
            return [
                'found' => true,
                'id' => $result['id'],
                'id_player' => $result['id_player'],
                'id_day' => $result['id_day'],
                'datetime' => $result['datetime'],
                'firstname' => $result['firstname'],
                'lastname' => $result['lastname'],
                'team_name' => $result['team_name'],
                'team_color' => $result['team_color']
            ];
        } else {
            // Papier doré non trouvé
            return [
                'found' => false,
                'day' => $day
            ];
        }
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du papier doré: " . $e->getMessage());
        return [
            'found' => false,
            'day' => $day,
            'error' => 'Erreur lors de la récupération des données'
        ];
    }
}

// Connexion à la base de données
$dbConnection = getDBConnection();

if (!$dbConnection) {
    die("Erreur de connexion à la base de données");
}

echo "<h1>Test de la logique des papiers dorés</h1>\n";

// Tester pour les 3 jours
for ($day = 1; $day <= 3; $day++) {
    echo "<h2>Jour $day</h2>\n";
    
    $goldenPaperInfo = getGoldenPaperInfo($dbConnection, $day);
    
    if ($goldenPaperInfo) {
        if ($goldenPaperInfo['found']) {
            echo "<p style='color: green;'>✅ Papier doré trouvé !</p>\n";
            echo "<ul>\n";
            echo "<li>ID: " . htmlspecialchars($goldenPaperInfo['id']) . "</li>\n";
            echo "<li>Joueur: " . htmlspecialchars($goldenPaperInfo['firstname'] . ' ' . $goldenPaperInfo['lastname']) . "</li>\n";
            echo "<li>Équipe: " . htmlspecialchars($goldenPaperInfo['team_name']) . "</li>\n";
            echo "<li>Date/Heure: " . htmlspecialchars($goldenPaperInfo['datetime']) . "</li>\n";
            echo "</ul>\n";
        } else {
            echo "<p style='color: red;'>❌ Papier doré non trouvé</p>\n";
        }
    } else {
        echo "<p style='color: red;'>❌ Erreur lors de la récupération des données</p>\n";
    }
    
    echo "<hr>\n";
}

// Afficher tous les papiers dorés trouvés pour vérification
echo "<h2>Vérification : Tous les papiers dorés trouvés (triés par ID)</h2>\n";

try {
    $query = "
        SELECT 
            pf.id,
            pf.id_player,
            pf.id_day,
            pf.created_at as datetime,
            u.firstname,
            u.lastname,
            g.name as team_name,
            p.id as paper_id
        FROM papers_found_user pf
        INNER JOIN users u ON pf.id_player = u.id
        INNER JOIN `groups` g ON u.group_id = g.id
        INNER JOIN papers p ON pf.id_paper = p.id
        WHERE p.paper_type = 1
        ORDER BY pf.id ASC
    ";
    
    $stmt = $dbConnection->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($results) {
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>Position</th><th>ID</th><th>Paper ID</th><th>Joueur</th><th>Équipe</th><th>Jour</th><th>Date/Heure</th><th>Correspond au jour ?</th></tr>\n";
        
        $position = 1;
        foreach ($results as $result) {
            $correspondAuJour = ($position == $result['id_day']) ? "✅ OUI" : "❌ NON (Position $position, Jour {$result['id_day']})";
            echo "<tr>\n";
            echo "<td>$position</td>\n";
            echo "<td>" . htmlspecialchars($result['id']) . "</td>\n";
            echo "<td>" . htmlspecialchars($result['paper_id']) . "</td>\n";
            echo "<td>" . htmlspecialchars($result['firstname'] . ' ' . $result['lastname']) . "</td>\n";
            echo "<td>" . htmlspecialchars($result['team_name']) . "</td>\n";
            echo "<td>" . htmlspecialchars($result['id_day']) . "</td>\n";
            echo "<td>" . htmlspecialchars($result['datetime']) . "</td>\n";
            echo "<td>$correspondAuJour</td>\n";
            echo "</tr>\n";
            $position++;
        }
        
        echo "</table>\n";
        
        echo "<h3>Analyse de la logique :</h3>\n";
        echo "<ul>\n";
        echo "<li>Position 1 (premier papier doré trouvé) → Jour 1</li>\n";
        echo "<li>Position 2 (deuxième papier doré trouvé) → Jour 2</li>\n";
        echo "<li>Position 3 (troisième papier doré trouvé) → Jour 3</li>\n";
        echo "</ul>\n";
        
    } else {
        echo "<p>Aucun papier doré trouvé dans la base de données.</p>\n";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Erreur lors de la récupération de tous les papiers dorés: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

?>
