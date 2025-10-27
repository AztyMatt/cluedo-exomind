<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error.log');

ini_set('memory_limit', '-1');
ini_set('max_execution_time', '0');

require_once __DIR__ . '/../db-connection.php';
$dbConnection = getDBConnection();

function formatName($name) {
    $name = strtolower($name);
    return ucwords($name, " \t\r\n\f\v'\-");
}

$smtp_host = 'ssl0.ovh.net';
$smtp_port = 465;
$smtp_username = 'jeu@exomind.digital';
$smtp_password = 'Exomind55@';
$from_email = 'jeu@exomind.digital';
$from_name = 'Exomind TAK';

$subject = '🎃 Cluedo Exomind - Défi d\'Halloween TAK 2025';

$results = [];
$total_sent = 0;
$total_failed = 0;

$phpmailer_path = __DIR__ . '/../vendor/PHPMailer/PHPMailer/PHPMailer.php';
if (!file_exists($phpmailer_path)) {
    die(json_encode(['success' => false, 'message' => 'PHPMailer non installé. Les fichiers doivent être dans vendor/PHPMailer/PHPMailer/']));
}

require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

try {
    $stmt = $dbConnection->prepare("SELECT id, firstname, lastname, email, activation_code FROM users WHERE has_sent_activation_code = 0 ORDER BY id ASC");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $index = 0;
    foreach ($users as $user) {
        if ($index > 0) {
            sleep(15);
        }
        
        $to_email = $user['email'];
        $firstname = formatName($user['firstname']);
        $lastname = formatName($user['lastname']);
        $activation_code = $user['activation_code'];
        
        $body = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="text-align: center; margin-bottom: 30px;">
        <h1 style="color: #FF6B35; margin: 0; font-size: 32px;">🎃 CLUEDO EXOMIND TAK</h1>
        <p style="color: #666; margin-top: 5px; font-size: 18px;">Défi d\'Halloween 2025</p>
    </div>
    
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 10px; color: white; margin-bottom: 30px;">
        <h2 style="margin: 0 0 10px 0; font-size: 24px;color:black;">Bonjour '.$firstname.' '.$lastname.' !</h2>
        <p style="margin: 0; font-size: 16px;color:black;">Tu es invité(e) à participer au Cluedo Exomind TAK, un événement sur 3 jours !</p>
    </div>
    
    <div style="margin-bottom: 25px;">
        <h3 style="color: #FF6B35; font-size: 20px; margin-bottom: 10px;">🎮 QU\'EST-CE QUE C\'EST ?</h3>
        <p style="margin: 0;">Un Cluedo Web interactif à jouer en équipe (6 équipes correspondant aux 6 pôles d\'Exomind & TAK).</p>
    </div>
    
    <div style="margin-bottom: 25px;">
        <h3 style="color: #667eea; font-size: 20px; margin-bottom: 10px;">🎯 L\'OBJECTIF</h3>
        <p style="margin: 0;">Permettre à ton équipe de reconstituer et de résoudre les énigmes !</p>
    </div>
    
    <div style="margin-bottom: 25px;">
        <h3 style="color: #FF6B35; font-size: 20px; margin-bottom: 10px;">📅 DURÉE</h3>
        <p style="margin-bottom: 10px;">Le jeu se déroule sur 3 jours :</p>
        <ul style="margin: 0; padding-left: 20px;">
            <li>Lundi 27 octobre</li>
            <li>Mardi 28 octobre</li>
            <li>Mercredi 29 octobre</li>
        </ul>
        <p style="margin-top: 10px; margin-bottom: 0;">Chaque jour, il faut reconstituer les énigmes en trouvant les papiers dans les bureaux d\'Exomind.</p>
    </div>
    
    <div style="margin-bottom: 25px;">
        <h3 style="color: #667eea; font-size: 20px; margin-bottom: 10px;">🎁 LES BONUS</h3>
        <p style="margin: 0;">Il y a des bonus avec les objets à placer et le papier doré à trouver !</p>
    </div>
    
    <div style="background: #f8f9fa; border: 2px solid #FF6B35; border-radius: 10px; padding: 20px; text-align: center; margin-bottom: 30px;">
        <h3 style="color: #FF6B35; margin-top: 0; font-size: 20px;">🔑 TON CODE D\'ACTIVATION</h3>
        <div style="font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 5px; margin: 10px 0;">'.$activation_code.'</div>
    </div>
    
    <div style="background: #f8f9fa; border: 2px solid #667eea; border-radius: 10px; padding: 20px; text-align: center; margin-bottom: 30px;">
        <h3 style="color: #667eea; margin-top: 0; font-size: 20px;">🎮 POUR JOUER</h3>
        <p style="margin: 10px 0; font-size: 16px; color: #333;">Pour jouer, ouvre ton navigateur Web et entre cette URL :</p>
        <p style="margin: 0; font-size: 18px; font-weight: bold; color: #667eea;">cluedo.exomind.tech</p>
    </div>
    
    <div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 10px;">
        <p style="margin: 0; font-size: 16px;">Bonne chance et à très bientôt !</p>
        <p style="margin: 10px 0 0 0; color: #666; font-size: 14px;">L\'équipe Exomind</p>
    </div>
</body>
</html>';
        
        $body = mb_convert_encoding($body, 'UTF-8', 'auto');
        
        try {
            $mail = new PHPMailer(true);
            
            $mail->isSMTP();
            $mail->Host = $smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_username;
            $mail->Password = $smtp_password;
            
            if ($smtp_port == 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            $mail->Port = $smtp_port;
            $mail->CharSet = 'UTF-8';
            
            $mail->XMailer = 'PHP Exomind';
            $mail->Priority = 3;
            $mail->MessageID = '<' . md5(time() . uniqid()) . '@exomind.fr>';
            $mail->addCustomHeader('X-Priority', '3');
            $mail->addCustomHeader('X-MSMail-Priority', 'Normal');
            $mail->addCustomHeader('Importance', 'Normal');
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to_email);
            $mail->addReplyTo($from_email, $from_name);
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);
            
            $mail->send();

            // Mettre à jour le flag has_sent_activation_code à true
            $updateStmt = $dbConnection->prepare("UPDATE users SET has_sent_activation_code = 1 WHERE id = :id");
            $updateStmt->execute([':id' => $user['id']]);

            $results[] = [
                'email' => $to_email,
                'name' => "$firstname $lastname",
                'success' => true,
                'message' => 'Envoyé avec succès'
            ];
            $total_sent++;
            $index++;
            
            error_log("✅ Email envoyé à $firstname $lastname ($to_email)");
            
            // Echo après chaque mail envoyé
            echo "✅ Email envoyé avec succès à $firstname $lastname ($to_email)\n";
            flush();
            ob_flush();
            
        } catch (Exception $e) {
            $results[] = [
                'email' => $to_email,
                'name' => "$firstname $lastname",
                'success' => false,
                'message' => $e->getMessage()
            ];
            $total_failed++;
            $index++;
            
            error_log("❌ Erreur pour $firstname $lastname ($to_email): " . $e->getMessage());
        }
    }
    
    $result = [
        'success' => true,
        'message' => "Envoi terminé : $total_sent envoyés, $total_failed échecs",
        'total_sent' => $total_sent,
        'total_failed' => $total_failed,
        'details' => $results
    ];
    
} catch (Exception $e) {
    $result = [
        'success' => false,
        'message' => "Erreur lors de la récupération des utilisateurs: " . $e->getMessage()
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
