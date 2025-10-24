<?php
// Connexion à la base de données
require_once __DIR__ . '/db-connection.php';
$dbConnection = getDBConnection();

// Vérifier la connexion avant de continuer
if (!$dbConnection) {
    error_log("Erreur critique: Impossible de se connecter à la base de données");
    die("Erreur de connexion à la base de données");
}

// Calculer le jour du jeu le plus tôt possible pour l'utiliser partout
$gameDay = getGameDay($dbConnection);

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

// Fonction pour déterminer le statut des papiers
function getPapersStatus($papersFound, $totalToFound) {
    if ($papersFound === 0) {
        return 'status-zero';
    } else if ($papersFound >= $totalToFound) {
        return 'status-complete';
    } else {
        return 'status-in-progress';
    }
}

// Fonction pour formater la durée de résolution
function formatDuration($timestampStart, $timestampEnd) {
    if (!$timestampStart || !$timestampEnd) {
        return null;
    }
    
    $start = new DateTime($timestampStart);
    $end = new DateTime($timestampEnd);
    $diff = $start->diff($end);
    
    $hours = $diff->h;
    $minutes = $diff->i;
    $seconds = $diff->s;
    
    $result = '';
    
    if ($hours > 0) {
        $result .= $hours . 'h ';
    }
    
    if ($minutes > 0) {
        $result .= $minutes . 'm ';
    }
    
    if ($seconds > 0 || ($hours == 0 && $minutes == 0)) {
        $result .= $seconds . 's';
    }
    
    return trim($result);
}

// Fonction pour calculer le score basé sur la durée de résolution
function calculateScore($timestampStart, $timestampEnd) {
    if (!$timestampStart || !$timestampEnd) {
        return 0; // Pas de score si pas résolu
    }
    
    $start = new DateTime($timestampStart);
    $end = new DateTime($timestampEnd);
    $diff = $start->diff($end);
    
    // Calculer la durée totale en minutes
    $totalMinutes = ($diff->h * 60) + $diff->i + ($diff->s / 60);
    
    // Score de base : 2000 points
    $baseScore = 2000;
    
    // Pénalité : -100 points par tranche de 15 minutes
    $penaltyPer15Minutes = 100;
    $penaltyMinutes = floor($totalMinutes / 15) * 15; // Arrondir à la tranche de 15 minutes
    $penalty = ($penaltyMinutes / 15) * $penaltyPer15Minutes;
    
    // Calculer le score final
    $finalScore = $baseScore - $penalty;
    
    // Score minimum de 0
    return max(0, $finalScore);
}

// Fonction pour calculer les points du papier doré pour une équipe
function calculateGoldenPaperScore($dbConnection, $teamId, $day) {
    if (!$dbConnection) {
        return 0;
    }
    
    try {
        // Vérifier si cette équipe a trouvé le papier doré pour ce jour
        $query = "
            SELECT COUNT(*) as count
            FROM papers_found_user pf
            INNER JOIN users u ON pf.id_player = u.id
            INNER JOIN papers p ON pf.id_paper = p.id
            WHERE p.paper_type = 1 AND pf.id_day = ? AND u.group_id = ?
        ";
        
        $stmt = $dbConnection->prepare($query);
        $stmt->execute([$day, $teamId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si l'équipe a trouvé le papier doré, ajouter 1500 points
        return ($result && $result['count'] > 0) ? 1500 : 0;
        
    } catch (PDOException $e) {
        error_log("Erreur lors du calcul des points du papier doré: " . $e->getMessage());
        return 0;
    }
}

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

// Vérifier si l'utilisateur est activé via le cookie
$userActivated = false;
$userTeam = null;
$enigmaStatus = null;

$activation_code_cookie = $_COOKIE['cluedo_activation'] ?? null;

if ($activation_code_cookie && $dbConnection) {
    try {
        // Vérifier si le code du cookie existe en base et si l'utilisateur est activé
        $stmt = $dbConnection->prepare("SELECT u.*, g.name as team_name, g.pole_name, g.color as team_color, g.img_path as team_img FROM `users` u LEFT JOIN `groups` g ON u.group_id = g.id WHERE u.activation_code = ? AND u.has_activated = 1");
        $stmt->execute([$activation_code_cookie]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $userActivated = true;
            $userTeam = $user;
            
            // TODO: Récupérer le statut réel de l'énigme depuis la BDD
            // Pour l'instant, on simule : énigme déverrouillée si au moins 5 papiers trouvés
            // À adapter selon votre logique métier
            $enigmaStatus = 'unlocked'; // ou 'locked' selon vos critères
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification du cookie: " . $e->getMessage());
    }
}

// Récupérer le jour sélectionné (par défaut jour 1)
$selectedDay = isset($_GET['day']) ? (int)$_GET['day'] : 1;
$selectedDay = max(1, min(3, $selectedDay)); // Limiter entre 1 et 3

// Récupérer les informations du papier doré pour le jour sélectionné
$goldenPaperInfo = getGoldenPaperInfo($dbConnection, $selectedDay);

// Récupérer tous les groupes avec leurs utilisateurs depuis la base de données
$teams = [];
if ($dbConnection) {
    try {
        $stmt = $dbConnection->prepare("SELECT id, name, pole_name, color, img_path FROM `groups` ORDER BY id ASC");
        $stmt->execute();
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: vérifier les équipes chargées au début
        error_log("Équipes chargées au début: " . count($teams));
        foreach ($teams as $team) {
            error_log("- " . $team['name'] . " (ID: " . $team['id'] . ")");
        }
        
        // Récupérer les utilisateurs pour chaque groupe
        $teamsWithUsers = [];
        foreach ($teams as $team) {
            $stmt = $dbConnection->prepare("SELECT id, firstname, lastname, username, email, has_activated FROM `users` WHERE group_id = ? ORDER BY lastname ASC, firstname ASC");
            $stmt->execute([$team['id']]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Récupérer les objets associés à cette équipe
            $stmt = $dbConnection->prepare("SELECT id, path, title, subtitle, solved FROM `items` WHERE group_id = ? ORDER BY id ASC");
            $stmt->execute([$team['id']]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $team['items'] = $items;
            
            // Ajouter le nombre de papiers trouvés pour chaque utilisateur (EXCLURE les papiers dorés)
            foreach ($users as &$user) {
                // Récupérer le vrai nombre de papiers trouvés depuis papers_found_user (UNIQUEMENT les papiers normaux)
                $stmt = $dbConnection->prepare("
                    SELECT COUNT(*) as count 
                    FROM `papers_found_user` pf
                    INNER JOIN `papers` p ON pf.id_paper = p.id
                    WHERE pf.id_player = ? AND pf.id_day = ? AND p.paper_type = 0
                ");
                $stmt->execute([$user['id'], $selectedDay]);
                $papersCount = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $user['papers_found'] = $papersCount ? (int)$papersCount['count'] : 0;
            }
            
            // Trier les utilisateurs : actifs avec le plus de papiers d'abord, puis inactifs
            usort($users, function($a, $b) {
                // Si l'un est actif et l'autre non, l'actif vient en premier
                if ($a['has_activated'] != $b['has_activated']) {
                    return $b['has_activated'] - $a['has_activated']; // Actifs en premier
                }
                // Si les deux ont le même statut d'activation, trier par nombre de papiers (décroissant)
                return $b['papers_found'] - $a['papers_found'];
            });
            
            $team['users'] = $users;
            
            // Récupérer les infos de papiers depuis total_papers_found_group
            $stmt = $dbConnection->prepare("SELECT total_to_found, total_founded, complete FROM `total_papers_found_group` WHERE id_group = ? AND id_day = ?");
            $stmt->execute([$team['id'], $selectedDay]);
            $paperStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($paperStats) {
                $team['total_to_found'] = $paperStats['total_to_found'];
                $team['papers_found'] = $paperStats['total_founded'];
                $team['complete'] = $paperStats['complete'];
            } else {
                // Valeurs par défaut si pas de données
                $team['total_to_found'] = 10;
                $team['papers_found'] = 0;
                $team['complete'] = false;
            }
            
            // Récupérer le statut de l'énigme depuis la table enigmes avec les timestamps de durée
            $stmt = $dbConnection->prepare("
                SELECT e.status, e.datetime_solved, e.enigm_solution, 
                       esd.timestamp_start, esd.timestamp_end
                FROM `enigmes` e 
                LEFT JOIN `enigm_solutions_durations` esd ON e.id = esd.id_enigm 
                WHERE e.id_group = ? AND e.id_day = ?
            ");
            $stmt->execute([$team['id'], $selectedDay]);
            $enigmaData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($enigmaData) {
                $team['enigma_status'] = (int)$enigmaData['status']; // 0 = à reconstituer, 1 = en cours, 2 = résolue
                $team['datetime_solved'] = $enigmaData['datetime_solved']; // Date de résolution
                $team['timestamp_start'] = $enigmaData['timestamp_start']; // Début du chrono
                $team['timestamp_end'] = $enigmaData['timestamp_end']; // Fin du chrono
                
                // Calculer la durée de résolution
                $team['duration'] = formatDuration($enigmaData['timestamp_start'], $enigmaData['timestamp_end']);
                
                // Calculer le score basé sur la durée de l'énigme
                $enigmaScore = calculateScore($enigmaData['timestamp_start'], $enigmaData['timestamp_end']);
                
                // Ajouter les points du papier doré
                $goldenPaperScore = calculateGoldenPaperScore($dbConnection, $team['id'], $selectedDay);
                
                // Score total = score énigme + points papier doré
                $team['score'] = $enigmaScore + $goldenPaperScore;
                $team['enigma_score'] = $enigmaScore;
                $team['golden_paper_score'] = $goldenPaperScore;
            } else {
                // Valeur par défaut si pas d'énigme
                $team['enigma_status'] = 0;
                $team['datetime_solved'] = null;
                $team['timestamp_start'] = null;
                $team['timestamp_end'] = null;
                $team['duration'] = null;
                
                // Même sans énigme résolue, l'équipe peut avoir des points du papier doré
                $goldenPaperScore = calculateGoldenPaperScore($dbConnection, $team['id'], $selectedDay);
                $team['score'] = $goldenPaperScore;
                $team['enigma_score'] = 0;
                $team['golden_paper_score'] = $goldenPaperScore;
            }
            
            $teamsWithUsers[] = $team;
        }
        
        // Calculer le classement des équipes basé sur le score
        $scoredTeams = [];
        $unscoredTeams = [];
        
        foreach ($teamsWithUsers as $team) {
            if ($team['score'] > 0) {
                // Équipe qui a des points (énigme résolue OU papier doré trouvé)
                $scoredTeams[] = $team;
            } else {
                // Équipe qui n'a aucun point
                $unscoredTeams[] = $team;
            }
        }
        
        // Trier les équipes avec des points par score décroissant (le plus haut score en premier)
        usort($scoredTeams, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Assigner les rangs (1, 2, 3, 4, 5, 6)
        $rank = 1;
        foreach ($scoredTeams as $index => $team) {
            $scoredTeams[$index]['ranking'] = $rank;
            $rank++;
        }
        
        // Les équipes sans points n'ont pas de rang
        foreach ($unscoredTeams as $index => $team) {
            $unscoredTeams[$index]['ranking'] = null;
        }
        
        // Reconstituer la liste des équipes : avec points d'abord (par score), puis sans points
        $teams = array_merge($scoredTeams, $unscoredTeams);
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des équipes: " . $e->getMessage());
    }
}
?>

<?php
// Jour du jeu déjà calculé plus haut dans le fichier
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cluedo - Les Équipes</title>
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
            position: relative;
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

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .ranking-buttons-small {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .ranking-buttons-small .btn-ranking,
        .ranking-buttons-small .btn-individual {
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

        .btn-play {
            background: #4CAF50;
            color: white;
            padding: 18px 50px;
            font-size: 1.3rem;
        }

        .btn-play:hover {
            background: #45a049;
        }

        /* Styles pour la modale */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-content {
            background: linear-gradient(135deg, rgba(58, 58, 58, 0.95) 0%, rgba(30, 30, 30, 0.95) 100%);
            border-radius: 20px;
            padding: 40px;
            max-width: 900px;
            width: 90%;
            position: relative;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.1);
            animation: slideIn 0.3s ease;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            font-size: 24px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255, 77, 77, 0.8);
            transform: rotate(90deg);
        }

        .modal-title {
            font-size: 2rem;
            font-weight: bold;
            color: #f093fb;
            margin-bottom: 20px;
            text-align: center;
            text-shadow: 0 2px 10px rgba(240, 147, 251, 0.5);
        }

        .modal-text {
            font-size: 1.2rem;
            line-height: 1.8;
            color: #eee;
            text-align: left;
        }

        .day-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(240, 240, 250, 0.95) 100%);
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.5);
            z-index: 1000;
            border: 2px solid rgba(150, 150, 150, 0.5);
            backdrop-filter: blur(10px);
            text-align: center;
            width: 200px;
            box-sizing: border-box;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .day-indicator:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.6);
        }

        .day-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(240, 240, 250, 0.98) 100%);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(150, 150, 150, 0.5);
            backdrop-filter: blur(10px);
            margin-top: 10px;
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .day-dropdown.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .day-option {
            padding: 15px 20px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            border-bottom: 1px solid rgba(150, 150, 150, 0.2);
        }

        .day-option:last-child {
            border-bottom: none;
        }

        .day-option:hover {
            background: rgba(150, 150, 150, 0.1);
        }

        .day-option.active {
            background: rgba(102, 126, 234, 0.2);
            color: #667eea;
            font-weight: bold;
        }

        .day-arrow {
            display: inline-block;
            margin-left: 8px;
            transition: transform 0.3s ease;
        }

        .day-indicator.active .day-arrow {
            transform: rotate(180deg);
        }
        
        /* Indicateur de mise à jour en temps réel */
        .live-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(42, 42, 42, 0.95);
            padding: 10px 15px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #fff;
            border: 2px solid rgba(76, 175, 80, 0.5);
        }
        
        .live-pulse {
            width: 8px;
            height: 8px;
            background: #4CAF50;
            border-radius: 50%;
            animation: pulse-live 2s infinite;
        }
        
        @keyframes pulse-live {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.5;
                transform: scale(1.2);
            }
        }

        .header {
            position: fixed;
            top: 20px;
            right: 20px;
            background: <?= htmlspecialchars($userTeam['team_color'] ?? '#2a2a2a') ?>cc;
            border-radius: 12px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.1);
            z-index: 1000;
            backdrop-filter: blur(10px);
            color: white;
            width: 200px;
            box-sizing: border-box;
        }
        
        .header * {
            color: white !important;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, <?= htmlspecialchars($userTeam['team_color'] ?? '#888') ?>, rgba(255,255,255,0.3));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            border: 2px solid <?= htmlspecialchars($userTeam['team_color'] ?? '#888') ?>;
            flex-shrink: 0;
        }

        .user-details h2 {
            font-size: 0.95rem;
            color: #fff;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }

        .user-team {
            font-size: 0.8rem;
            color: <?= htmlspecialchars($userTeam['team_color'] ?? '#888') ?>;
            font-weight: bold;
        }

        .day-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2a2a2a;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 3px;
        }

        .day-objective {
            font-size: 0.9rem;
            color: #3a3a3a;
            font-weight: 600;
        }

        .teams-grid {
            display: flex;
            flex-direction: column;
            gap: 50px;
            padding: 20px;
            max-width: 1600px;
            margin: 0 auto;
        }

        .team-row {
            display: flex;
            gap: 30px;
        }

        .team-row:first-child,
        .team-row:last-child {
            justify-content: space-between;
            align-items: flex-start;
        }

        .team-card {
            background: rgba(58, 58, 58, 0.8);
            border-radius: 20px;
            padding: 0;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            border: 3px solid transparent;
            display: flex;
            flex-direction: column;
            width: 380px;
            height: 420px;
            flex-shrink: 0;
        }

        .team-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }

        .team-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 8px;
            background: var(--team-color);
            opacity: 0.8;
            z-index: 10;
        }

        .team-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, transparent 0%, transparent 40%, rgba(0,0,0,0.7) 70%, rgba(0,0,0,0.9) 100%);
            opacity: 0.8;
            pointer-events: none;
            z-index: 1;
        }

        .team-card-content {
            display: flex;
            width: 100%;
            height: calc(100% - 80px);
            gap: 0;
        }

        .team-left-column {
            width: 40%;
            display: flex;
            flex-direction: column;
            padding: 20px;
            background: #2a2a2a;
        }

        .team-image-container {
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-top: 0;
            margin-bottom: 0;
        }

        .team-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .team-info {
            text-align: center;
        }

        .team-right-column {
            width: 60%;
            display: flex;
            flex-direction: column;
            padding: 20px;
        }

        .team-scrollable {
            height: 300px;
            background: rgba(0, 0, 0, 0.8);
            border-radius: 15px;
            padding: 15px;
            overflow-y: auto;
            backdrop-filter: blur(10px);
            border: 2px solid var(--team-color);
        }

        .team-scrollable::-webkit-scrollbar {
            width: 6px;
        }

        .team-scrollable::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }

        .team-scrollable::-webkit-scrollbar-thumb {
            background: var(--team-color);
            border-radius: 3px;
        }

        .team-scrollable::-webkit-scrollbar-thumb:hover {
            background: var(--team-color);
            opacity: 0.8;
        }

        .user-item {
            margin-bottom: 12px;
            padding: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            transition: all 0.3s ease;
            position: relative;
            padding-left: 20px;
        }

        .user-item.active {
            background: rgba(255, 255, 255, 0.15);
        }

        .user-item.inactive {
            background: rgba(255, 255, 255, 0.05);
        }

        .user-status-icon {
            position: absolute;
            left: 5px;
            top: 50%;
            transform: translateY(-50%);
            width: 10px;
            height: 10px;
            animation: pulse 2s infinite;
            opacity: 1 !important;
            filter: none;
        }

        .user-item.active:hover .user-status-icon {
            animation: pulse 0.5s infinite;
            transform: translateY(-50%) scale(1.2);
        }

        .user-item.inactive:hover .user-status-icon {
            animation: pulse 0.5s infinite;
            transform: translateY(-50%) scale(1.2);
        }

        @keyframes pulse {
            0% {
                transform: translateY(-50%) scale(1);
                opacity: 1;
            }
            50% {
                transform: translateY(-50%) scale(1.1);
                opacity: 0.8;
            }
            100% {
                transform: translateY(-50%) scale(1);
                opacity: 1;
            }
        }

        .user-name {
            font-weight: bold;
            color: #fff;
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-papers-count {
            font-size: 0.75rem;
            background: var(--team-color);
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            /* Couleur de texte adaptative selon la luminosité */
            color: color-mix(in srgb, var(--team-color) 20%, black);
            filter: contrast(2) brightness(0.3);
        }

        /* Pour les couleurs claires, utiliser du texte foncé */
        .team-card[style*="#FFDB58"] .user-papers-count,
        .team-card[style*="#FFFFFF"] .user-papers-count,
        .team-card[style*="#FF69B4"] .user-papers-count {
            color: #333;
            filter: none;
        }

        /* Pour les couleurs foncées, utiliser du texte clair */
        .team-card[style*="#1E3A8A"] .user-papers-count,
        .team-card[style*="#6B8E23"] .user-papers-count,
        .team-card[style*="#8F00FF"] .user-papers-count {
            color: #fff;
            filter: none;
        }

        .team-name {
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--team-color);
            text-shadow: 0 3px 15px rgba(0, 0, 0, 0.9), 0 0 30px rgba(0, 0, 0, 0.7);
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.8));
        }

        .team-pole {
            text-align: center;
            font-size: 0.9rem;
            color: #fff;
            padding: 8px 15px;
            background: rgba(0, 0, 0, 0.9);
            border-radius: 10px;
            border-left: 4px solid var(--team-color);
            backdrop-filter: blur(10px);
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.8);
        }

        .team-status {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 15px 20px;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(10px);
            border-top: 2px solid var(--team-color);
            z-index: 3;
            display: flex;
            justify-content: space-around;
            align-items: center;
            gap: 10px;
        }

        .status-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            flex: 1;
        }

        .status-label {
            font-size: 0.75rem;
            color: #bbb;
            text-transform: uppercase;
            font-weight: 600;
        }

        .status-value {
            font-size: 1.1rem;
            font-weight: bold;
            color: #fff;
        }

        /* Styles pour les compteurs globaux d'équipe - même style que les boutons d'énigme */
        .status-value.status-zero {
            background: linear-gradient(135deg, #eb3349, #f45c43) !important; /* Rouge pour 0 papiers */
            color: white !important;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
        }

        .status-value.status-in-progress {
            background: linear-gradient(135deg, #f2994a, #f2c94c) !important; /* Orange pour en cours */
            color: white !important;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
        }

        .status-value.status-complete {
            background: linear-gradient(135deg, #11998e, #38ef7d) !important; /* Vert pour complet */
            color: white !important;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
        }

        .badge-success {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: white;
        }

        .badge-danger {
            background: linear-gradient(135deg, #eb3349, #f45c43);
            color: white;
        }

        .badge-warning {
            background: linear-gradient(135deg, #f2994a, #f2c94c);
            color: white;
        }
        
        .status-badge:hover {
            transform: scale(1.05);
        }

        /* Styles pour l'encart objets */
        .items-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 2px;
        }

        .status-label-container {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 2px;
        }

        .items-counter {
            font-size: 0.75rem;
            font-weight: bold;
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
            padding: 2px 6px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .item-miniature {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid var(--team-color);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }

        .item-miniature:hover {
            transform: scale(1.5);
            border-color: rgba(255, 255, 255, 0.9);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            z-index: 1000;
            position: relative;
        }

        .item-miniature img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .item-miniature.solved {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            border-color: #38ef7d;
        }

        .item-miniature.solved::after {
            content: '✓';
            position: absolute;
            top: -2px;
            right: -2px;
            background: #4CAF50;
            color: white;
            font-size: 10px;
            font-weight: bold;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid white;
        }

        .team-image-container {
            position: relative;
        }

        .color-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--team-color);
            border: 3px solid #2a2a2a;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
            z-index: 10;
        }

        /* Styles pour les médailles de classement */
        .ranking-medal {
            position: fixed;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.5);
            z-index: 1000;
            border: 3px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(5px);
            animation: medalGlow 2s ease-in-out infinite alternate;
            pointer-events: none;
        }

        /* Styles pour le texte de rang au-dessus de la carte */
        .ranking-text {
            position: fixed;
            font-size: 1.2rem;
            font-weight: bold;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);
            z-index: 1000;
            pointer-events: none;
            letter-spacing: 2px;
            text-transform: uppercase;
            background: rgba(0, 0, 0, 0.7);
            padding: 5px 10px;
            border-radius: 8px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Styles pour l'encart chrono */
        .chrono-text {
            position: fixed;
            font-size: 1.2rem;
            font-weight: bold;
            color: white;
            z-index: 1000;
            pointer-events: none;
            background: rgba(0, 0, 0, 0.7);
            padding: 6px 10px;
            border-radius: 8px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        @keyframes medalGlow {
            0% {
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.5);
            }
            100% {
                box-shadow: 0 8px 25px rgba(255, 215, 0, 0.4), 0 6px 20px rgba(0, 0, 0, 0.5);
            }
        }

        /* Couleurs spécifiques pour chaque rang */
        .medal-1 {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            border-color: #FFD700;
        }

        .medal-2 {
            background: linear-gradient(135deg, #C0C0C0, #A0A0A0);
            border-color: #C0C0C0;
        }

        .medal-3 {
            background: linear-gradient(135deg, #CD7F32, #B8860B);
            border-color: #CD7F32;
        }

        .medal-4 {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            border-color: #4CAF50;
        }

        .medal-5 {
            background: linear-gradient(135deg, #2196F3, #1565C0);
            border-color: #2196F3;
        }

        .medal-6 {
            background: linear-gradient(135deg, #9C27B0, #6A1B9A);
            border-color: #9C27B0;
        }

        /* Effet hover pour les médailles */
        .ranking-medal:hover {
            transform: scale(1.1);
            animation: medalPulse 0.5s ease-in-out infinite alternate;
        }

        @keyframes medalPulse {
            0% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1.15);
            }
        }

        .no-teams {
            text-align: center;
            font-size: 1.5rem;
            color: #aaa;
            padding: 60px 20px;
        }

        /* Conteneur des papiers */
        .papers-container {
            position: fixed;
            top: 20px;
            right: 250px;
            background: rgba(128, 128, 128, 0.2);
            border-radius: 15px;
            padding: 15px;
            z-index: 1000;
            display: flex;
            gap: 15px;
            align-items: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease;
        }

        .papers-container:hover {
            transform: scale(1.02);
        }

        /* Papier doré */
        .golden-paper {
            width: 60px;
            height: 70px;
            background: url('papier_dore.png') no-repeat center center;
            background-size: contain;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 5px;
            box-sizing: border-box;
            transition: transform 0.3s ease;
            filter: drop-shadow(2px 2px 4px rgba(0, 0, 0, 0.3));
            cursor: pointer;
        }

        .golden-paper:hover {
            transform: scale(1.1);
        }

        .golden-paper-text {
            font-family: 'Times New Roman', serif;
            font-size: 0.8rem;
            font-weight: bold;
            color: #B8860B;
            text-align: center;
            line-height: 1.1;
            margin: 0;
        }

        .golden-paper-counter {
            font-family: 'Times New Roman', serif;
            font-size: 1rem;
            font-weight: bold;
            color: #B8860B;
            text-align: center;
            margin-top: 3px;
        }

        /* Papier normal */
        .normal-paper {
            width: 60px;
            height: 70px;
            background: url('papier.png') no-repeat center center;
            background-size: contain;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 5px;
            box-sizing: border-box;
            transform: rotate(-3deg);
            transition: transform 0.3s ease;
            filter: drop-shadow(2px 2px 4px rgba(0, 0, 0, 0.3));
            cursor: pointer;
        }

        .normal-paper:hover {
            transform: rotate(-3deg) scale(1.1);
        }

        .normal-paper-text {
            font-family: 'Times New Roman', serif;
            font-size: 0.8rem;
            font-weight: bold;
            color: #000000;
            text-align: center;
            line-height: 1.1;
            margin: 0;
        }

        .normal-paper-counter {
            font-family: 'Times New Roman', serif;
            font-size: 1rem;
            font-weight: bold;
            color: #000000;
            text-align: center;
            margin-top: 3px;
        }

        /* Conteneur des objets */
        .objects-container {
            position: fixed;
            top: 20px;
            right: 430px;
            background: rgba(128, 128, 128, 0.2);
            border-radius: 15px;
            padding: 10px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease;
            cursor: pointer;
            width: 120px;
            height: 100px;
            box-sizing: border-box;
        }

        .objects-container:hover {
            transform: scale(1.02);
        }

        .objects-icon {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .objects-text {
            font-family: 'Times New Roman', serif;
            font-size: 0.8rem;
            font-weight: bold;
            color: #FFFFFF;
            text-align: center;
            line-height: 1.1;
            margin: 0;
        }

        .objects-counter {
            font-family: 'Times New Roman', serif;
            font-size: 1rem;
            font-weight: bold;
            color: #FFFFFF;
            text-align: center;
            margin-top: 5px;
        }

        /* Styles pour la popup des papiers */
        .papers-popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .papers-popup-overlay.active {
            display: flex;
        }

        .papers-popup-content {
            background: linear-gradient(135deg, rgba(58, 58, 58, 0.95) 0%, rgba(30, 30, 30, 0.95) 100%);
            border-radius: 20px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            position: relative;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.1);
            animation: slideIn 0.3s ease;
            max-height: 80vh;
            overflow-y: auto;
        }

        .papers-popup-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            font-size: 24px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .papers-popup-close:hover {
            background: rgba(255, 77, 77, 0.8);
            transform: rotate(90deg);
        }

        .papers-popup-title {
            font-size: 1.8rem;
            font-weight: bold;
            color: #FFD700;
            margin-bottom: 20px;
            text-align: center;
        }

        .papers-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
        }

        /* Style pour la scrollbar */
        .papers-list::-webkit-scrollbar {
            width: 8px;
        }

        .papers-list::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        .papers-list::-webkit-scrollbar-thumb {
            background: rgba(240, 147, 251, 0.6);
            border-radius: 4px;
        }

        .papers-list::-webkit-scrollbar-thumb:hover {
            background: rgba(240, 147, 251, 0.8);
        }

        .paper-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            border-left: 4px solid var(--team-color);
            transition: all 0.3s ease;
        }

        .paper-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .paper-user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .paper-user-name {
            font-weight: bold;
            color: #fff;
            font-size: 1.1rem;
        }

        .paper-team-name {
            color: var(--team-color);
            font-weight: bold;
            font-size: 0.9rem;
        }

        .paper-datetime {
            color: #bbb;
            font-size: 0.85rem;
            font-style: italic;
        }

        /* Styles spécifiques pour le papier doré */
        .golden-paper-content {
            text-align: center;
            padding: 20px;
        }

        .golden-paper-status {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 10px;
        }

        .golden-paper-not-found {
            color: #ff6b6b;
            background: rgba(255, 107, 107, 0.1);
            border: 2px solid rgba(255, 107, 107, 0.3);
        }

        .golden-paper-found {
            color: #51cf66;
            background: rgba(81, 207, 102, 0.1);
            border: 2px solid rgba(81, 207, 102, 0.3);
        }

        .golden-paper-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid #ffd700;
        }

        .golden-paper-winner {
            font-size: 1.3rem;
            font-weight: bold;
            color: #fff;
            margin-bottom: 8px;
        }

        .golden-paper-team {
            color: #fff;
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 8px;
        }

        .golden-paper-time {
            color: #bbb;
            font-size: 0.95rem;
            font-style: italic;
            margin-bottom: 12px;
        }

        .golden-paper-character {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 20px;
        }

        .golden-paper-character img {
            height: 130px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            border: none;
        }

        .golden-paper-character-info {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .golden-paper-character-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .golden-paper-points {
            color: #fff;
            font-weight: bold;
            font-size: 1.2rem;
            padding: 8px 15px;
            border-radius: 20px;
            text-align: center;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            margin-top: 10px;
        }

        /* Styles pour la popup des objets */
        .objects-popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .objects-popup-overlay.active {
            display: flex;
        }

        .objects-popup-content {
            background: linear-gradient(135deg, rgba(58, 58, 58, 0.95) 0%, rgba(30, 30, 30, 0.95) 100%);
            border-radius: 20px;
            padding: 30px;
            max-width: 700px;
            width: 90%;
            position: relative;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.1);
            animation: slideIn 0.3s ease;
            max-height: 80vh;
            overflow-y: auto;
        }

        .objects-popup-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            font-size: 24px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .objects-popup-close:hover {
            background: rgba(255, 77, 77, 0.8);
            transform: rotate(90deg);
        }

        .objects-popup-title {
            font-size: 1.8rem;
            font-weight: bold;
            color: #FFD700;
            margin-bottom: 20px;
            text-align: center;
        }

        .objects-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .object-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            border-left: 4px solid var(--team-color);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .object-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .object-image {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid var(--team-color);
            flex-shrink: 0;
        }

        .object-info {
            flex: 1;
        }

        .object-name {
            font-weight: bold;
            color: #fff;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .object-subtitle {
            color: #bbb;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .object-user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .object-user-name {
            font-weight: bold;
            color: #fff;
            font-size: 1rem;
        }

        .object-team-name {
            color: var(--team-color);
            font-weight: bold;
            font-size: 0.9rem;
        }

        .object-datetime {
            color: #bbb;
            font-size: 0.85rem;
            font-style: italic;
        }

        @media (max-width: 1400px) {
            .team-row:first-child,
            .team-row:last-child {
                justify-content: center;
                flex-wrap: wrap;
            }
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 2rem;
            }

            .teams-grid {
                gap: 20px;
            }

            .team-row {
                flex-direction: column;
                align-items: center;
            }

            .team-card {
                width: 100%;
                max-width: 380px;
            }

            .team-image-container {
                height: 350px;
            }
        }
    </style>
</head>
<body>
    <!-- Indicateur du jour -->
    <?php 
    $dayLabels = [
        1 => ['number' => 'Jour 1', 'objective' => '🏛️ Scène du crime'],
        2 => ['number' => 'Jour 2', 'objective' => '🔪 Arme du crime'],
        3 => ['number' => 'Jour 3', 'objective' => '🎭 Auteur du crime']
    ];
    $currentDay = $dayLabels[$gameDay];
    ?>
    <div class="day-indicator" id="dayIndicator">
        <div class="day-number"><?= $currentDay['number'] ?> <span class="day-arrow">▼</span></div>
        <div class="day-objective"><?= $currentDay['objective'] ?></div>
        
        <div class="day-dropdown" id="dayDropdown">
            <div class="day-option <?= $gameDay == 1 ? 'active' : '' ?>" data-day="1">
                <div style="font-weight: bold; color: #2a2a2a;">Jour 1</div>
                <div style="font-size: 0.9rem; color: #666;">🏛️ Scène du crime</div>
            </div>
            <div class="day-option <?= $gameDay == 2 ? 'active' : '' ?>" data-day="2">
                <div style="font-weight: bold; color: #2a2a2a;">Jour 2</div>
                <div style="font-size: 0.9rem; color: #666;">🔪 Arme du crime</div>
            </div>
            <div class="day-option <?= $gameDay == 3 ? 'active' : '' ?>" data-day="3">
                <div style="font-weight: bold; color: #2a2a2a;">Jour 3</div>
                <div style="font-size: 0.9rem; color: #666;">🎭 Auteur du crime</div>
            </div>
        </div>
    </div>

    <!-- Informations utilisateur connecté (uniquement si activé) -->
    <?php if ($userActivated && $userTeam): ?>
        <div class="header" style="top: 130px; right: 20px;">
            <div class="user-info">
                <div class="user-avatar">
                    <?php if (!empty($userTeam['team_img']) && file_exists($userTeam['team_img'])): ?>
                        <img src="<?= htmlspecialchars($userTeam['team_img']) ?>" alt="<?= htmlspecialchars($userTeam['team_name']) ?>" style="width: 100%; height: 100%; object-fit: contain; border-radius: 50%;">
                    <?php else: ?>
                        🎮
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <h2><?= htmlspecialchars(formatUserName($userTeam['firstname'], $userTeam['lastname'])) ?></h2>
                    <div class="user-team"><?= htmlspecialchars($userTeam['pole_name'] ?? 'Non assigné') ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Conteneur des objets -->
    <div class="objects-container" id="objectsClickable">
        <div class="objects-icon">📦</div>
        <div class="objects-text">Objets placés</div>
        <div class="objects-counter" id="totalObjectsCounter">0/18</div>
    </div>

    <!-- Conteneur des papiers -->
    <div class="papers-container">
        <!-- Papier doré avec rotation verticale -->
        <div class="golden-paper" id="goldenPaperClickable">
            <div class="golden-paper-text">Papier doré</div>
            <div class="golden-paper-counter" id="goldenPaperCounter">0/1</div>
        </div>
        
        <!-- Papier normal avec compteur dynamique -->
        <div class="normal-paper" id="normalPaperClickable">
            <div class="normal-paper-text">Papiers</div>
            <div class="normal-paper-counter" id="totalPapersCounter">0/47</div>
        </div>
    </div>

    <!-- Popup des papiers trouvés -->
    <div id="papersPopup" class="papers-popup-overlay">
        <div class="papers-popup-content">
            <button class="papers-popup-close" id="closePapersPopup">&times;</button>
            <h2 class="papers-popup-title">📄 Historique des Papiers Trouvés (JOUR <span id="papersPopupDay">1</span>)</h2>
            <p style="font-style: italic; margin: 10px 0; color: #FFFFFF; font-size: 0.9rem;">
                Il y a un ensemble de X papiers répartis sur ce jour. Chaque papier trouvé ne peut plus être trouvé par une autre équipe. Les équipes ne peuvent pas trouver plus de papier que ce que leur quotat leur permet chaque jour (afin d'en laisser pour les autres)
            </p>
            <div class="papers-list" id="papersList">
                <!-- Le contenu sera généré dynamiquement -->
            </div>
        </div>
    </div>

    <!-- Popup du papier doré -->
    <div id="goldenPaperPopup" class="papers-popup-overlay">
        <div class="papers-popup-content">
            <button class="papers-popup-close" id="closeGoldenPaperPopup">&times;</button>
            <h2 class="papers-popup-title">🏆 Papier Doré (JOUR <span id="goldenPaperPopupDay">1</span>)</h2>
            <p style="font-style: italic; margin: 10px 0; color: #FFFFFF; font-size: 0.9rem;">
                Il y a UN seul et unique papier doré qui apparait chaque jour. L'équipe qui le trouve remporte 1500 points. Une fois le papier d'or trouvé chaque jour par une équipe, aucune autre équipe ne peut le trouver.
            </p>
            <div id="goldenPaperContent">
                <!-- Le contenu sera généré dynamiquement -->
            </div>
        </div>
    </div>

    <!-- Popup des objets trouvés -->
    <div id="objectsPopup" class="objects-popup-overlay">
        <div class="objects-popup-content">
            <button class="objects-popup-close" id="closeObjectsPopup">&times;</button>
            <h2 class="objects-popup-title">🎯 Objets Placés</h2>
            <p style="font-style: italic; margin: 10px 0; color: #FFFFFF; font-size: 0.9rem;">
                Les équipes ont chacune 3 objets à placer au bon endroit sur l'ensemble des trois jours. Chaque objet placé rapporte +750 points à son équipe.
            </p>
            <div class="objects-list" id="objectsList">
                <!-- Le contenu sera généré dynamiquement -->
            </div>
        </div>
    </div>

    <!-- Boutons de classement fixes en haut à gauche -->
    <div class="ranking-buttons-fixed">
        <a href="ranking.php" class="game-button btn-ranking btn-ranking-top">🏆 Classement Équipes</a>
        <a href="ranking-individual.php" class="game-button btn-individual btn-ranking-bottom">👤 Classement Individuel</a>
    </div>

    <!-- Indicateur de mise à jour en temps réel -->
    <div class="live-indicator">
        <div class="live-pulse"></div>
        <span>Mise à jour automatique</span>
    </div>
    
    <div class="container">
        <img src="assets/img/logo.png" alt="CLUEDO Tak exomind" class="logo">

        <div class="buttons-container">
            <button id="rulesBtn" class="game-button btn-rules">📋 Règles du jeu</button>
            <a href="game.php" class="game-button btn-play">🔍 JOUER</a>
        </div>

        <!-- Modale des règles -->
        <div id="rulesModal" class="modal-overlay">
            <div class="modal-content">
                <button class="modal-close" id="closeModal">&times;</button>
                <h2 class="modal-title">📖 Règles du jeu</h2>
                <div class="modal-text">
                    <p style="margin-bottom: 20px;">
                        Ce jeu du Cluedo vous est proposé par <b>Exomind</b> et <b>Tak</b>. Il y a au total <b>6 équipes</b>, chacune associée à un pôle d'Exomind et représentée par un personnage emblématique du Cluedo.
                    </p>
                    
                    <p style="margin-bottom: 20px;">
                        <b>🎯 Objectif :</b><br>
                        Chaque jour, les équipes doivent deviner un mot mystère :
                        <br>• <b>Jour 1</b> : la scène du crime 🏛️
                        <br>• <b>Jour 2</b> : l'arme du crime 🔪
                        <br>• <b>Jour 3</b> : l'auteur du crime 🎭
                    </p>
                    
                    <p style="margin-bottom: 20px;">
                        <b>🔍 Comment jouer :</b><br>
                        Pour découvrir le mot du jour, chaque équipe doit d'abord reconstituer une énigme qui a été déchirée en petits papiers. Ces papiers sont cachés dans les bureaux d'Exomind. Une fois l'énigme reconstituée, elle vous donnera la clé pour trouver la réponse !
                    </p>
                    
                    <p style="margin-bottom: 0;">
                        <b>🏆 Classement :</b><br>
                        La meilleure équipe sera celle qui trouve le plus rapidement les mots chaque jour. 
                        <br>Le meilleur joueur individuel sera celui qui collectera le plus rapidement ses papiers.
                    </p>
                </div>
            </div>
        </div>

        <?php if (empty($teamsWithUsers)): ?>
            <div class="no-teams">
                <p>Aucune équipe disponible pour le moment.</p>
            </div>
        <?php else: ?>
            <div class="teams-grid">
                <!-- Ligne 1 : 3 cartes espacées -->
                <div class="team-row">
                    <?php for ($i = 0; $i < 3 && $i < count($teamsWithUsers); $i++): 
                        $team = $teamsWithUsers[$i]; ?>
                        <div class="team-card" style="--team-color: <?= htmlspecialchars($team['color'] ?? '#888') ?>;" data-team-id="<?= $team['id'] ?>">
                            
                            <div class="team-card-content">
                                <!-- Colonne de gauche : Image + Nom + Pôle -->
                                <div class="team-left-column">
                                    <h2 class="team-name"><?= htmlspecialchars($team['name']) ?></h2>
                                    
                                    <div class="team-image-container">
                                        <div class="color-indicator"></div>
                                        <?php if (!empty($team['img_path']) && file_exists($team['img_path'])): ?>
                                            <img src="<?= htmlspecialchars($team['img_path']) ?>" 
                                                 alt="<?= htmlspecialchars($team['name']) ?>" 
                                                 class="team-image">
                                        <?php else: ?>
                                            <div style="font-size: 4rem; color: <?= htmlspecialchars($team['color'] ?? '#888') ?>;">
                                                🎭
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="team-pole"><?= htmlspecialchars($team['pole_name']) ?></div>
                                </div>

                                <!-- Colonne de droite : Liste des joueurs -->
                                <div class="team-right-column">
                                    <div class="team-scrollable">
                                        <?php if (!empty($team['users'])): ?>
                                            <?php foreach ($team['users'] as $user): ?>
                                                <div class="user-item <?= $user['has_activated'] ? 'active' : 'inactive' ?>">
                                                    <img src="assets/img/<?= $user['has_activated'] ? 'activated' : 'unactivated' ?>.svg" 
                                                         alt="<?= $user['has_activated'] ? 'Actif' : 'Inactif' ?>" 
                                                         class="user-status-icon">
                                                    <div class="user-name">
                                                        <span><?= htmlspecialchars(formatUserName($user['firstname'], $user['lastname'])) ?></span>
                                                        <span class="user-papers-count"><?= $user['papers_found'] ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="user-item">
                                                <div class="user-name">Aucun utilisateur</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Section de statut -->
                            <div class="team-status">
                                <div class="status-item">
                                    <div class="status-label-container">
                                        <span class="status-label">Objets</span>
                                        <?php 
                                        $solvedItemsCount = 0;
                                        foreach ($team['items'] as $item) {
                                            if ($item['solved']) $solvedItemsCount++;
                                        }
                                        ?>
                                        <span class="items-counter"><?= $solvedItemsCount ?>/<?= count($team['items']) ?></span>
                                    </div>
                                    <div class="items-container">
                                        <?php foreach ($team['items'] as $item): ?>
                                            <div class="item-miniature <?= $item['solved'] ? 'solved' : '' ?>" title="<?= htmlspecialchars($item['title']) ?> - <?= htmlspecialchars($item['subtitle']) ?>">
                                                <img src="<?= htmlspecialchars($item['path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="status-item">
                                    <span class="status-label">Papiers</span>
                                    <span class="status-value <?= getPapersStatus($team['papers_found'], $team['total_to_found']) ?>">📄 <?= $team['papers_found'] ?> / <?= $team['total_to_found'] ?></span>
                                </div>
                                <div class="status-item">
                                    <span class="status-label">Énigme</span>
                                    <?php if ($team['enigma_status'] == 0): ?>
                                        <span class="status-badge badge-danger">🔒 À reconstituer</span>
                                    <?php elseif ($team['enigma_status'] == 1): ?>
                                        <?php if ($userActivated && $userTeam && $userTeam['group_id'] == $team['id']): ?>
                                            <a href="enigme.php?day=<?= $selectedDay ?>" class="status-badge badge-warning" style="text-decoration: none; cursor: pointer; transition: transform 0.2s;">⏳ Reconstituée/à résoudre</a>
                                        <?php else: ?>
                                            <span class="status-badge badge-warning">⏳ Reconstituée/à résoudre</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if ($userActivated && $userTeam && $userTeam['group_id'] == $team['id']): ?>
                                            <a href="enigme.php?day=<?= $selectedDay ?>" class="status-badge badge-success" style="text-decoration: none; cursor: pointer; transition: transform 0.2s;">✅ Résolue - Voir</a>
                                        <?php else: ?>
                                            <span class="status-badge badge-success">✅ Résolue</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <!-- Ligne 2 : 3 cartes centrées -->
                <?php if (count($teamsWithUsers) > 3): ?>
                    <div class="team-row">
                        <?php for ($i = 3; $i < 6 && $i < count($teamsWithUsers); $i++): 
                            $team = $teamsWithUsers[$i]; ?>
                            <div class="team-card" style="--team-color: <?= htmlspecialchars($team['color'] ?? '#888') ?>;" data-team-id="<?= $team['id'] ?>">
                                
                                <div class="team-card-content">
                                    <!-- Colonne de gauche : Image + Nom + Pôle -->
                                    <div class="team-left-column">
                                        <h2 class="team-name"><?= htmlspecialchars($team['name']) ?></h2>
                                        
                                        <div class="team-image-container">
                                            <div class="color-indicator"></div>
                                            <?php if (!empty($team['img_path']) && file_exists($team['img_path'])): ?>
                                                <img src="<?= htmlspecialchars($team['img_path']) ?>" 
                                                     alt="<?= htmlspecialchars($team['name']) ?>" 
                                                     class="team-image">
                                            <?php else: ?>
                                                <div style="font-size: 4rem; color: <?= htmlspecialchars($team['color'] ?? '#888') ?>;">
                                                    🎭
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="team-pole"><?= htmlspecialchars($team['pole_name']) ?></div>
                                    </div>

                                    <!-- Colonne de droite : Liste des joueurs -->
                                    <div class="team-right-column">
                                        <div class="team-scrollable">
                                            <?php if (!empty($team['users'])): ?>
                                                <?php foreach ($team['users'] as $user): ?>
                                                    <div class="user-item <?= $user['has_activated'] ? 'active' : 'inactive' ?>">
                                                        <img src="assets/img/<?= $user['has_activated'] ? 'activated' : 'unactivated' ?>.svg" 
                                                             alt="<?= $user['has_activated'] ? 'Actif' : 'Inactif' ?>" 
                                                             class="user-status-icon">
                                                        <div class="user-name">
                                                            <span><?= htmlspecialchars(formatUserName($user['firstname'], $user['lastname'])) ?></span>
                                                            <span class="user-papers-count"><?= $user['papers_found'] ?></span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="user-item">
                                                    <div class="user-name">Aucun utilisateur</div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section de statut -->
                                <div class="team-status">
                                    <div class="status-item">
                                        <div class="status-label-container">
                                            <span class="status-label">Objets</span>
                                            <?php 
                                            $solvedItemsCount = 0;
                                            foreach ($team['items'] as $item) {
                                                if ($item['solved']) $solvedItemsCount++;
                                            }
                                            ?>
                                            <span class="items-counter"><?= $solvedItemsCount ?>/<?= count($team['items']) ?></span>
                                        </div>
                                        <div class="items-container">
                                            <?php foreach ($team['items'] as $item): ?>
                                                <div class="item-miniature <?= $item['solved'] ? 'solved' : '' ?>" title="<?= htmlspecialchars($item['title']) ?> - <?= htmlspecialchars($item['subtitle']) ?>">
                                                    <img src="<?= htmlspecialchars($item['path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-label">Papiers</span>
                                        <span class="status-value <?= getPapersStatus($team['papers_found'], $team['total_to_found']) ?>">📄 <?= $team['papers_found'] ?> / <?= $team['total_to_found'] ?></span>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-label">Énigme</span>
                                        <?php if ($team['enigma_status'] == 0): ?>
                                            <span class="status-badge badge-danger">🔒 À reconstituer</span>
                                        <?php elseif ($team['enigma_status'] == 1): ?>
                                            <?php if ($userActivated && $userTeam && $userTeam['group_id'] == $team['id']): ?>
                                                <a href="enigme.php?day=<?= $selectedDay ?>" class="status-badge badge-warning" style="text-decoration: none; cursor: pointer; transition: transform 0.2s;">⏳ Reconstituée/à résoudre</a>
                                            <?php else: ?>
                                                <span class="status-badge badge-warning">⏳ Reconstituée/à résoudre</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($userActivated && $userTeam && $userTeam['group_id'] == $team['id']): ?>
                                                <a href="enigme.php?day=<?= $selectedDay ?>" class="status-badge badge-success" style="text-decoration: none; cursor: pointer; transition: transform 0.2s;">✅ Résolue - Voir</a>
                                            <?php else: ?>
                                                <span class="status-badge badge-success">✅ Résolue</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Utiliser le jour calculé par PHP selon la table current_date
        let currentDay = <?= $gameDay ?>;
        
        // Informations sur l'équipe de l'utilisateur connecté
        window.currentUserTeam = <?= $userActivated && $userTeam ? json_encode($userTeam) : 'null' ?>;
        
        // Gestion de la modale des règles
        const rulesBtn = document.getElementById('rulesBtn');
        const rulesModal = document.getElementById('rulesModal');
        const closeModal = document.getElementById('closeModal');
        const modalContent = document.querySelector('.modal-content');

        // Ouvrir la modale
        rulesBtn.addEventListener('click', () => {
            rulesModal.classList.add('active');
        });

        // Fermer avec le bouton X
        closeModal.addEventListener('click', () => {
            rulesModal.classList.remove('active');
        });

        // Fermer en cliquant en dehors de la modale
        rulesModal.addEventListener('click', (e) => {
            if (e.target === rulesModal) {
                rulesModal.classList.remove('active');
            }
        });

        // Fermer avec la touche Échap
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && rulesModal.classList.contains('active')) {
                rulesModal.classList.remove('active');
            }
        });

        // Gestion du menu déroulant des jours
        const dayIndicator = document.getElementById('dayIndicator');
        const dayDropdown = document.getElementById('dayDropdown');
        const dayOptions = document.querySelectorAll('.day-option');

        // Ouvrir/fermer le menu déroulant
        dayIndicator.addEventListener('click', (e) => {
            e.stopPropagation();
            dayIndicator.classList.toggle('active');
            dayDropdown.classList.toggle('active');
        });

        // Sélectionner un jour
        dayOptions.forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                
                // Retirer la classe active de toutes les options
                dayOptions.forEach(opt => opt.classList.remove('active'));
                
                // Ajouter la classe active à l'option sélectionnée
                option.classList.add('active');
                
                // Mettre à jour l'affichage principal
                const dayNumber = option.querySelector('div:first-child').textContent;
                const dayObjective = option.querySelector('div:last-child').textContent;
                
                const mainDayNumber = dayIndicator.querySelector('.day-number');
                const mainDayObjective = dayIndicator.querySelector('.day-objective');
                
                mainDayNumber.innerHTML = dayNumber + ' <span class="day-arrow">▼</span>';
                mainDayObjective.textContent = dayObjective;
                
                // Fermer le menu
                dayIndicator.classList.remove('active');
                dayDropdown.classList.remove('active');
                
                // Mettre à jour le jour actuel et récupérer les nouvelles données
                currentDay = parseInt(option.dataset.day);
                
                // Mettre à jour les données immédiatement via AJAX (sans changer l'URL)
                updateTeamsData();
            });
        });

        // Fermer le menu en cliquant ailleurs
        document.addEventListener('click', () => {
            dayIndicator.classList.remove('active');
            dayDropdown.classList.remove('active');
        });
        
        // ========== MISE À JOUR EN TEMPS RÉEL AVEC AJAX ==========

        // Fonction pour positionner les médailles par-dessus les cartes
        function positionMedals() {
            // Supprimer toutes les médailles et textes existants
            document.querySelectorAll('.ranking-medal, .ranking-text, .chrono-text').forEach(element => element.remove());
            
            // Parcourir toutes les cartes d'équipe
            document.querySelectorAll('.team-card').forEach(card => {
                const teamId = card.getAttribute('data-team-id');
                const teamData = window.currentTeamsData ? window.currentTeamsData.find(t => t.id == teamId) : null;
                
                if (teamData && teamData.ranking !== null && teamData.ranking !== undefined) {
                    // Obtenir la position de la carte
                    const rect = card.getBoundingClientRect();
                    
                    // Créer la médaille
                    const medal = document.createElement('div');
                    medal.className = `ranking-medal medal-${teamData.ranking}`;
                    medal.textContent = teamData.ranking;
                    medal.id = `medal-${teamId}`;
                    
                    // Positionner la médaille au coin supérieur gauche de la carte
                    medal.style.left = `${rect.left - 10}px`;
                    medal.style.top = `${rect.top - 10}px`;
                    
                    // Ajouter la médaille au body
                    document.body.appendChild(medal);
                }
                
                // Afficher les points si l'énigme est résolue OU si il y a des points du papier doré
                if (teamData && teamData.score > 0) {
                    const rect = card.getBoundingClientRect();
                    
                    // Créer l'encart points
                    const pointsText = document.createElement('div');
                    pointsText.className = 'ranking-text';
                    pointsText.innerHTML = `🏆 ${teamData.score} pts`;
                    pointsText.id = `points-${teamId}`;
                    
                    // Ajouter au body
                    document.body.appendChild(pointsText);
                    
                    // Positionner les points au centre de la carte
                    const pointsWidth = pointsText.offsetWidth;
                    const startX = rect.left + (rect.width / 2) - (pointsWidth / 2);
                    pointsText.style.left = `${startX}px`;
                    pointsText.style.top = `${rect.top - 40}px`;
                    
                    // Afficher le chrono seulement si l'énigme est résolue
                    if (teamData.enigma_status == 2 && teamData.duration) {
                        // Créer l'encart chrono
                        const chronoText = document.createElement('div');
                        chronoText.className = 'chrono-text';
                        chronoText.innerHTML = `⏱️ ${teamData.duration}`;
                        chronoText.id = `chrono-${teamId}`;
                        
                        // Ajouter au body
                        document.body.appendChild(chronoText);
                        
                        // Recalculer les positions pour centrer l'ensemble
                        const chronoWidth = chronoText.offsetWidth;
                        const totalWidth = chronoWidth + pointsWidth + 10; // 10px d'espacement
                        const newStartX = rect.left + (rect.width / 2) - (totalWidth / 2);
                        
                        // Positionner le chrono à gauche
                        chronoText.style.left = `${newStartX}px`;
                        chronoText.style.top = `${rect.top - 40}px`;
                        
                        // Repositionner les points à droite du chrono
                        pointsText.style.left = `${newStartX + chronoWidth + 10}px`;
                    }
                }
            });
        }
        
        // Variables pour le throttling
        let updateTimeout = null;
        let isUpdating = false;
        
        // Fonction pour mettre à jour les positions des médailles et textes lors du scroll/resize
        function updateMedalPositions() {
            if (isUpdating) return;
            
            // Throttling : limiter à 60fps maximum
            if (updateTimeout) {
                clearTimeout(updateTimeout);
            }
            
            updateTimeout = setTimeout(() => {
                isUpdating = true;
                
                // Utiliser requestAnimationFrame pour une meilleure performance
                requestAnimationFrame(() => {
                    document.querySelectorAll('.ranking-medal').forEach(medal => {
                        const teamId = medal.id.replace('medal-', '');
                        const card = document.querySelector(`[data-team-id="${teamId}"]`);
                        
                        if (card) {
                            const rect = card.getBoundingClientRect();
                            medal.style.left = `${rect.left - 10}px`;
                            medal.style.top = `${rect.top - 10}px`;
                        }
                    });
                    
                    // Mettre à jour les positions des chronos et points
                    document.querySelectorAll('.chrono-text').forEach(chrono => {
                        const teamId = chrono.id.replace('chrono-', '');
                        const card = document.querySelector(`[data-team-id="${teamId}"]`);
                        const points = document.getElementById(`points-${teamId}`);
                        
                        if (card) {
                            const rect = card.getBoundingClientRect();
                            
                            if (points) {
                                // Calculer la largeur totale pour centrer l'ensemble
                                const chronoWidth = chrono.offsetWidth;
                                const pointsWidth = points.offsetWidth;
                                const totalWidth = chronoWidth + pointsWidth + 10; // 10px d'espacement
                                const startX = rect.left + (rect.width / 2) - (totalWidth / 2);
                                
                                // Positionner le chrono à gauche
                                chrono.style.left = `${startX}px`;
                                chrono.style.top = `${rect.top - 40}px`;
                                
                                // Positionner les points à droite du chrono
                                points.style.left = `${startX + chronoWidth + 10}px`;
                                points.style.top = `${rect.top - 40}px`;
                            } else {
                                // Si pas de points, centrer juste le chrono
                                chrono.style.left = `${rect.left + (rect.width / 2) - 50}px`;
                                chrono.style.top = `${rect.top - 40}px`;
                            }
                        }
                    });
                    
                    // Mettre à jour les positions des points seuls (sans chrono)
                    document.querySelectorAll('.ranking-text').forEach(points => {
                        const teamId = points.id.replace('points-', '');
                        const card = document.querySelector(`[data-team-id="${teamId}"]`);
                        const chrono = document.getElementById(`chrono-${teamId}`);
                        
                        // Ne repositionner que si il n'y a pas de chrono (points seuls)
                        if (card && !chrono) {
                            const rect = card.getBoundingClientRect();
                            
                            // Centrer les points
                            const pointsWidth = points.offsetWidth;
                            const startX = rect.left + (rect.width / 2) - (pointsWidth / 2);
                            
                            points.style.left = `${startX}px`;
                            points.style.top = `${rect.top - 40}px`;
                        }
                    });
                    
                    isUpdating = false;
                });
            }, 16); // ~60fps
        }
        
        function formatUserName(firstname, lastname) {
            const formattedFirst = firstname.charAt(0).toUpperCase() + firstname.slice(1).toLowerCase();
            const formattedLast = lastname.toUpperCase();
            return formattedFirst + ' ' + formattedLast;
        }
        
        function updateTeamsData() {
            fetch('teams_data_real_time?day=' + currentDay)
                .then(response => response.json())
                .then(data => {
                    if (!data.success || !data.teams) {
                        return;
                    }
                    
                    // Supprimer toutes les médailles et textes existants avant de recréer
                    document.querySelectorAll('.ranking-medal, .ranking-text, .chrono-text').forEach(element => element.remove());
                    
                    // Sauvegarder les données pour les médailles
                    window.currentTeamsData = data.teams;
                    
                    // Calculer le total des papiers trouvés par toutes les équipes
                    updateTotalPapersCounter(data.teams);
                    
                    // Calculer le total des objets trouvés par toutes les équipes
                    updateTotalObjectsCounter(data.teams);
                    
                    // Reconstruire complètement les cartes d'équipe
                    rebuildTeamCards(data.teams);
                    
                    // Positionner les médailles après la reconstruction
                    setTimeout(positionMedals, 100);
                    
                    // Mettre à jour les informations du papier doré pour le jour actuel
                    fetchGoldenPaperInfo();
                })
                .catch(error => {
                    // Erreur silencieuse
                });
        }
        
        // Fonction pour mettre à jour le compteur total des papiers
        function updateTotalPapersCounter(teams) {
            let totalPapersFound = 0;
            let totalPapersToFind = 0;
            
            teams.forEach(team => {
                totalPapersFound += parseInt(team.papers_found) || 0;
                totalPapersToFind += parseInt(team.total_to_found) || 0;
            });
            
            // Mettre à jour l'affichage
            const counterElement = document.getElementById('totalPapersCounter');
            if (counterElement) {
                counterElement.textContent = `${totalPapersFound}/${totalPapersToFind}`;
            }
        }
        
        // Fonction pour récupérer l'historique des papiers trouvés
        function fetchPapersHistory() {
            fetch(`game_recent_papers.php?day=${currentDay}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.papers) {
                        // Trier par datetime décroissant (plus récents en premier)
                        const sortedPapers = data.papers.sort((a, b) => new Date(b.datetime) - new Date(a.datetime));
                        window.papersHistory = sortedPapers;
                    } else {
                        window.papersHistory = [];
                    }
                })
                .catch(error => {
                    window.papersHistory = [];
                });
        }
        
        // Fonction pour afficher la popup des papiers
        function showPapersPopup() {
            const popup = document.getElementById('papersPopup');
            const papersList = document.getElementById('papersList');
            const daySpan = document.getElementById('papersPopupDay');
            
            // Mettre à jour le numéro du jour dans le titre
            daySpan.textContent = currentDay;
            
            if (!window.papersHistory || window.papersHistory.length === 0) {
                papersList.innerHTML = '<div style="text-align: center; color: #bbb; padding: 20px;">Aucun papier trouvé pour le moment.</div>';
            } else {
                let html = '';
                window.papersHistory.forEach(paper => {
                    const datetime = new Date(paper.datetime);
                    const formattedDate = datetime.toLocaleDateString('en-US');
                    const formattedTime = datetime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                    
                    html += `
                        <div class="paper-item" style="background: rgba(255, 255, 255, 0.1); border-radius: 10px; padding: 15px; margin-bottom: 10px; border-left: 4px solid ${paper.team_color || '#666'};">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <div style="font-weight: bold; color: #fff; font-size: 1.1rem;">
                                    ${paper.display_name}
                                </div>
                                <div style="font-size: 0.9rem; color: #ccc;">
                                    ${formattedDate} à ${formattedTime}
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <img src="${paper.team_img || 'assets/img/logo.png'}" alt="${paper.team_name}" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">
                                    <span style="color: #f093fb; font-weight: bold;">${paper.team_name}</span>
                                </div>
                                <div style="color: #FFD700; font-weight: bold;">
                                    ${paper.pole_name}
                                </div>
                            </div>
                        </div>
                    `;
                });
                papersList.innerHTML = html;
            }
            
            popup.classList.add('active');
        }
        
        // Fonction pour fermer la popup des papiers
        function hidePapersPopup() {
            const popup = document.getElementById('papersPopup');
            popup.classList.remove('active');
        }
        
        // Fonction pour récupérer les informations du papier doré
        function fetchGoldenPaperInfo() {
            // Faire un appel AJAX pour récupérer les données du papier doré pour le jour actuel
            fetch(`get-golden-paper.php?day=${currentDay}`)
                .then(response => response.json())
                .then(data => {
                    window.goldenPaperInfo = data;
                    // Mettre à jour l'affichage du papier doré dans l'interface
                    updateGoldenPaperDisplay();
                })
                .catch(error => {
                    console.error('Erreur lors de la récupération du papier doré:', error);
                    window.goldenPaperInfo = null;
                    updateGoldenPaperDisplay();
                });
        }
        
        // Fonction pour mettre à jour l'affichage du papier doré
        function updateGoldenPaperDisplay() {
            const goldenPaperCounter = document.getElementById('goldenPaperCounter');
            
            if (!window.goldenPaperInfo || !window.goldenPaperInfo.found) {
                // Papier doré non trouvé - afficher 0/1
                if (goldenPaperCounter) {
                    goldenPaperCounter.textContent = '0/1';
                }
            } else {
                // Papier doré trouvé - afficher 1/1
                if (goldenPaperCounter) {
                    goldenPaperCounter.textContent = '1/1';
                }
            }
        }
        
        // Fonction pour obtenir l'image du personnage selon le nom de l'équipe
        function getCharacterImage(teamName) {
            const characterMap = {
                'Colonel Moutarde': 'assets/img/colonnel_moutarde.png',
                'Madame Leblanc': 'assets/img/madame_leblanc.png',
                'Madame Pervenche': 'assets/img/madame_pervenche.png',
                'Mademoiselle Rose': 'assets/img/mademoiselle_rose.png',
                'Professeur Violet': 'assets/img/professeur_violet.png',
                'Révérend Olive': 'assets/img/reverend_olive.png'
            };
            
            return characterMap[teamName] || 'assets/img/logo.png'; // Image par défaut si pas trouvé
        }

        // Fonction pour afficher la popup du papier doré
        function showGoldenPaperPopup() {
            const popup = document.getElementById('goldenPaperPopup');
            const content = document.getElementById('goldenPaperContent');
            const daySpan = document.getElementById('goldenPaperPopupDay');
            
            // Mettre à jour le numéro du jour dans le titre
            daySpan.textContent = currentDay;
            
            if (!window.goldenPaperInfo || !window.goldenPaperInfo.found) {
                // Papier doré non trouvé
                content.innerHTML = `
                    <div class="golden-paper-content">
                        <div class="golden-paper-status golden-paper-not-found">
                            ❌ Le papier doré n'a pas été trouvé pour le jour ${currentDay}
                        </div>
                    </div>
                `;
            } else {
                // Papier doré trouvé
                const datetime = new Date(window.goldenPaperInfo.datetime);
                const formattedDate = datetime.toLocaleDateString('en-US');
                const formattedTime = datetime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                
                content.innerHTML = `
                    <div class="golden-paper-content">
                        <div class="golden-paper-status golden-paper-found">
                            ✅ Le papier doré a été trouvé pour le jour ${currentDay}
                        </div>
                        <div class="golden-paper-character">
                            <div class="golden-paper-character-info" style="background-color: ${window.goldenPaperInfo.team_color || 'rgba(255, 255, 255, 0.1)'};">
                                <img src="${getCharacterImage(window.goldenPaperInfo.team_name)}" alt="${window.goldenPaperInfo.team_name}" />
                                <div class="golden-paper-character-details">
                                    <div class="golden-paper-winner">${formatUserName(window.goldenPaperInfo.firstname, window.goldenPaperInfo.lastname)}</div>
                                    <div class="golden-paper-team">${window.goldenPaperInfo.team_name}</div>
                                    <div class="golden-paper-time">📅 ${formattedDate} à ${formattedTime}</div>
                                    <div class="golden-paper-points">
                                        🏆 +1500 points pour l'équipe
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            popup.classList.add('active');
        }
        
        // Fonction pour fermer la popup du papier doré
        function hideGoldenPaperPopup() {
            const popup = document.getElementById('goldenPaperPopup');
            popup.classList.remove('active');
        }
        
        // Fonction pour récupérer les objets trouvés
        function fetchObjectsHistory() {
            fetch(`game_recent_objects.php?day=${currentDay}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.objects) {
                        // Trier par datetime décroissant (plus récents en premier)
                        const sortedObjects = data.objects.sort((a, b) => new Date(b.datetime) - new Date(a.datetime));
                        window.objectsHistory = sortedObjects;
                    } else {
                        window.objectsHistory = [];
                    }
                })
                .catch(error => {
                    window.objectsHistory = [];
                });
        }
        
        // Fonction pour mettre à jour le compteur total des objets
        function updateTotalObjectsCounter(teams) {
            let totalObjectsFound = 0;
            const totalObjectsToFind = 18; // Total fixe de 18 objets
            
            teams.forEach(team => {
                if (team.items && team.items.length > 0) {
                    team.items.forEach(item => {
                        if (item.solved) totalObjectsFound++;
                    });
                }
            });
            
            // Mettre à jour l'affichage
            const counterElement = document.getElementById('totalObjectsCounter');
            if (counterElement) {
                counterElement.textContent = `${totalObjectsFound}/${totalObjectsToFind}`;
            }
        }
        
        // Fonction pour afficher la popup des objets
        function showObjectsPopup() {
            const popup = document.getElementById('objectsPopup');
            const objectsList = document.getElementById('objectsList');
            
            if (!window.objectsHistory || window.objectsHistory.length === 0) {
                objectsList.innerHTML = '<div style="text-align: center; color: #bbb; padding: 20px;">Personne n\'a encore réussi à placer un objet.</div>';
            } else {
                let html = '';
                window.objectsHistory.forEach(object => {
                    const datetime = new Date(object.datetime);
                    const formattedDate = datetime.toLocaleDateString('en-US');
                    const formattedTime = datetime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                    
                    html += `
                        <div class="object-item" style="--team-color: ${object.team_color || '#888'}">
                            <img src="${object.path}" alt="${object.title}" class="object-image">
                            <div class="object-info">
                                <div class="object-name">${object.title}</div>
                                <div class="object-subtitle">${object.subtitle}</div>
                                <div class="object-user-info">
                                    <span class="object-user-name">${formatUserName(object.firstname, object.lastname)}</span>
                                    <span class="object-team-name">${object.team_name}</span>
                                </div>
                                <div class="object-datetime">📅 ${formattedDate} à ${formattedTime}</div>
                            </div>
                        </div>
                    `;
                });
                objectsList.innerHTML = html;
            }
            
            popup.classList.add('active');
        }
        
        // Fonction pour fermer la popup des objets
        function hideObjectsPopup() {
            const popup = document.getElementById('objectsPopup');
            popup.classList.remove('active');
        }
        
        function rebuildTeamCards(teams) {
            
            // Trouver le conteneur principal des équipes
            const teamsContainer = document.querySelector('.teams-grid');
            if (!teamsContainer) {
                return;
            }
            
            // Vider le conteneur
            teamsContainer.innerHTML = '';
            
            // Diviser les équipes en deux lignes (3 + 3)
            const firstRowTeams = teams.slice(0, 3);
            const secondRowTeams = teams.slice(3, 6);
            
            // Créer la première ligne
            if (firstRowTeams.length > 0) {
                const firstRow = createTeamRow(firstRowTeams);
                teamsContainer.appendChild(firstRow);
            }
            
            // Créer la deuxième ligne
            if (secondRowTeams.length > 0) {
                const secondRow = createTeamRow(secondRowTeams);
                teamsContainer.appendChild(secondRow);
            }
        }
        
        function createTeamRow(teams) {
            const row = document.createElement('div');
            row.className = 'team-row';
            
            teams.forEach(team => {
                const teamCard = createTeamCard(team);
                row.appendChild(teamCard);
            });
            
            return row;
        }
        
        // Fonction pour déterminer le statut des papiers
        function getPapersStatus(papersFound, totalToFound) {
            if (papersFound === 0) {
                return 'status-zero';
            } else if (papersFound >= totalToFound) {
                return 'status-complete';
            } else {
                return 'status-in-progress';
            }
        }

        function createTeamCard(team) {
            const card = document.createElement('div');
            card.className = 'team-card';
            card.style.setProperty('--team-color', team.color || '#888');
            card.setAttribute('data-team-id', team.id);
            
            // Image de l'équipe
            const teamImage = team.img_path && team.img_path !== '' ? 
                `<img src="${team.img_path}" alt="${team.name}" class="team-image">` :
                `<div style="font-size: 4rem; color: ${team.color || '#888'};">🎭</div>`;
            
            // Utilisateurs
            let usersHTML = '';
            if (team.users && team.users.length > 0) {
                team.users.forEach(user => {
                    const isActive = user.has_activated == 1;
                    const statusClass = isActive ? 'active' : 'inactive';
                    const statusIcon = isActive ? 'activated' : 'unactivated';
                    
                    usersHTML += `
                        <div class="user-item ${statusClass}">
                            <img src="assets/img/${statusIcon}.svg" 
                                 alt="${isActive ? 'Actif' : 'Inactif'}" 
                                 class="user-status-icon">
                            <div class="user-name">
                                <span>${formatUserName(user.firstname, user.lastname)}</span>
                                <span class="user-papers-count">${user.papers_found}</span>
                            </div>
                        </div>
                    `;
                });
            } else {
                usersHTML = '<div class="user-item"><div class="user-name">Aucun utilisateur</div></div>';
            }
            
            // Objets de l'équipe
            let itemsLabelHTML = '';
            let itemsMiniaturesHTML = '';
            if (team.items && team.items.length > 0) {
                // Compter les objets résolus
                let solvedItemsCount = 0;
                team.items.forEach(item => {
                    if (item.solved) solvedItemsCount++;
                });
                
                // Créer le label avec compteur
                itemsLabelHTML = `
                    <div class="status-label-container">
                        <span class="status-label">Objets</span>
                        <span class="items-counter">${solvedItemsCount}/${team.items.length}</span>
                    </div>
                `;
                
                // Créer les miniatures
                team.items.forEach(item => {
                    const solvedClass = item.solved ? 'solved' : '';
                    itemsMiniaturesHTML += `
                        <div class="item-miniature ${solvedClass}" title="${item.title} - ${item.subtitle}">
                            <img src="${item.path}" alt="${item.title}">
                        </div>
                    `;
                });
            }
            
            // Statut de l'énigme
            let enigmaBadge = '';
            const isUserTeam = window.currentUserTeam && window.currentUserTeam.group_id == team.id;
            
            if (team.enigma_status == 0) {
                enigmaBadge = '<span class="status-badge badge-danger">🔒 À reconstituer</span>';
            } else if (team.enigma_status == 1) {
                if (isUserTeam) {
                    enigmaBadge = `<a href="enigme.php?day=${currentDay}" class="status-badge badge-warning" style="text-decoration: none; cursor: pointer; transition: transform 0.2s;">⏳ Reconstituée/à résoudre</a>`;
                } else {
                    enigmaBadge = '<span class="status-badge badge-warning">⏳ Reconstituée/à résoudre</span>';
                }
            } else {
                if (isUserTeam) {
                    enigmaBadge = `<a href="enigme.php?day=${currentDay}" class="status-badge badge-success" style="text-decoration: none; cursor: pointer; transition: transform 0.2s;">✅ Résolue - Voir</a>`;
                } else {
                    enigmaBadge = '<span class="status-badge badge-success">✅ Résolue</span>';
                }
            }
            
            // Déterminer le statut des papiers pour l'équipe
            const teamPapersStatus = getPapersStatus(team.papers_found, team.total_to_found);
            
            card.innerHTML = `
                
                <div class="team-card-content">
                    <!-- Colonne de gauche : Image + Nom + Pôle -->
                    <div class="team-left-column">
                        <h2 class="team-name">${team.name}</h2>
                        
                        <div class="team-image-container">
                            <div class="color-indicator"></div>
                            ${teamImage}
                        </div>
                        
                        <div class="team-pole">${team.pole_name}</div>
                    </div>

                    <!-- Colonne de droite : Liste des joueurs -->
                    <div class="team-right-column">
                        <div class="team-scrollable">
                            ${usersHTML}
                        </div>
                    </div>
                </div>

                <!-- Section de statut -->
                <div class="team-status">
                    <div class="status-item">
                        ${itemsLabelHTML}
                        <div class="items-container">
                            ${itemsMiniaturesHTML}
                        </div>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Papiers</span>
                        <span class="status-value ${teamPapersStatus}">📄 ${team.papers_found} / ${team.total_to_found}</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Énigme</span>
                        ${enigmaBadge}
                    </div>
                </div>
            `;
            
            return card;
        }
        
        // Gérer le bouton retour du navigateur
        window.addEventListener('popstate', (event) => {
            if (event.state && event.state.day) {
                currentDay = event.state.day;
                
                // Mettre à jour l'affichage du sélecteur de jour
                dayOptions.forEach(opt => {
                    if (parseInt(opt.dataset.day) === currentDay) {
                        opt.classList.add('active');
                        const dayNumber = opt.querySelector('div:first-child').textContent;
                        const dayObjective = opt.querySelector('div:last-child').textContent;
                        
                        const mainDayNumber = dayIndicator.querySelector('.day-number');
                        const mainDayObjective = dayIndicator.querySelector('.day-objective');
                        
                        mainDayNumber.innerHTML = dayNumber + ' <span class="day-arrow">▼</span>';
                        mainDayObjective.textContent = dayObjective;
                    } else {
                        opt.classList.remove('active');
                    }
                });
                
                // Mettre à jour les données
                updateTeamsData();
            }
        });
        
        // Event listeners pour la popup des papiers
        const normalPaperClickable = document.getElementById('normalPaperClickable');
        const papersPopup = document.getElementById('papersPopup');
        const closePapersPopup = document.getElementById('closePapersPopup');
        
        // Event listeners pour la popup du papier doré
        const goldenPaperClickable = document.getElementById('goldenPaperClickable');
        const goldenPaperPopup = document.getElementById('goldenPaperPopup');
        const closeGoldenPaperPopup = document.getElementById('closeGoldenPaperPopup');
        
        // Event listeners pour la popup des objets
        const objectsClickable = document.getElementById('objectsClickable');
        const objectsPopup = document.getElementById('objectsPopup');
        const closeObjectsPopup = document.getElementById('closeObjectsPopup');
        
        // Ouvrir la popup au clic sur le papier normal
        normalPaperClickable.addEventListener('click', () => {
            showPapersPopup();
        });
        
        // Ouvrir la popup au clic sur le papier doré
        goldenPaperClickable.addEventListener('click', () => {
            showGoldenPaperPopup();
        });
        
        // Ouvrir la popup au clic sur les objets
        objectsClickable.addEventListener('click', () => {
            showObjectsPopup();
        });
        
        // Fermer la popup des papiers avec le bouton X
        closePapersPopup.addEventListener('click', () => {
            hidePapersPopup();
        });
        
        // Fermer la popup du papier doré avec le bouton X
        closeGoldenPaperPopup.addEventListener('click', () => {
            hideGoldenPaperPopup();
        });
        
        // Fermer la popup des objets avec le bouton X
        closeObjectsPopup.addEventListener('click', () => {
            hideObjectsPopup();
        });
        
        // Fermer la popup des papiers en cliquant en dehors
        papersPopup.addEventListener('click', (e) => {
            if (e.target === papersPopup) {
                hidePapersPopup();
            }
        });
        
        // Fermer la popup du papier doré en cliquant en dehors
        goldenPaperPopup.addEventListener('click', (e) => {
            if (e.target === goldenPaperPopup) {
                hideGoldenPaperPopup();
            }
        });
        
        // Fermer la popup des objets en cliquant en dehors
        objectsPopup.addEventListener('click', (e) => {
            if (e.target === objectsPopup) {
                hideObjectsPopup();
            }
        });
        
        // Fermer les popups avec la touche Échap
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (papersPopup.classList.contains('active')) {
                    hidePapersPopup();
                }
                if (goldenPaperPopup.classList.contains('active')) {
                    hideGoldenPaperPopup();
                }
                if (objectsPopup.classList.contains('active')) {
                    hideObjectsPopup();
                }
            }
        });
        
        // Lancer la première mise à jour immédiatement
        updateTeamsData();
        
        // Récupérer l'historique des papiers au chargement
        fetchPapersHistory();
        
        // Récupérer les infos du papier doré au chargement
        fetchGoldenPaperInfo();
        
        // Récupérer l'historique des objets au chargement
        fetchObjectsHistory();
        
        // Puis mettre à jour toutes les 10 secondes
        setInterval(updateTeamsData, 10000);
        
        // Mettre à jour l'historique des papiers toutes les 30 secondes
        setInterval(fetchPapersHistory, 30000);
        
        // Les infos du papier doré sont maintenant chargées directement depuis PHP
        
        // Mettre à jour l'historique des objets toutes les 30 secondes
        setInterval(fetchObjectsHistory, 30000);
        
        // Mettre à jour les positions des médailles lors du scroll et resize
        window.addEventListener('scroll', updateMedalPositions);
        window.addEventListener('resize', updateMedalPositions);
        
        // Positionner les médailles au chargement initial
        setTimeout(positionMedals, 500);
    </script>
</body>
</html>

