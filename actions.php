<?php
session_start();
require 'config.php';
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
header('Content-Type: application/json');

// Vérification d'authentification
check_auth();

$userId = $_SESSION['user_id'];

if (!isset($_POST['action'])) {
    echo json_encode(['success' => false, 'error' => 'Action non spécifiée']);
    exit;
}

$action = $_POST['action'];

try {
    switch ($action) {

        case 'find_partner': {
            // 1. Vérifier si l'utilisateur est déjà dans une paire active
            $stmt = $pdo->prepare("SELECT id, user1_id, user2_id FROM active_pairs WHERE user1_id = ? OR user2_id = ?");
            $stmt->execute([$userId, $userId]);
            $pair = $stmt->fetch();

            if ($pair) {
                $partnerId = ($pair['user1_id'] == $userId) ? $pair['user2_id'] : $pair['user1_id'];
                echo json_encode([
                    'success' => true,
                    'pair_id' => $pair['id'],
                    'partner_id' => $partnerId
                ]);
                exit;
            }

            // 2. Vérifier si l'utilisateur est déjà dans waiting_users
            $stmt = $pdo->prepare("SELECT user_id FROM waiting_users WHERE user_id = ?");
            $stmt->execute([$userId]);
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO waiting_users (user_id) VALUES (?)");
                if (!$stmt->execute([$userId])) {
                    $errorInfo = $stmt->errorInfo();
                    die(json_encode(['success' => false, 'error' => "Erreur insertion waiting_users : " . print_r($errorInfo, true)]));
                }
            }

            // 3. Chercher un autre utilisateur en attente
            $stmt = $pdo->prepare("SELECT user_id FROM waiting_users WHERE user_id != ? ORDER BY created_at ASC LIMIT 1");
            $stmt->execute([$userId]);
            $partner = $stmt->fetchColumn();

            if ($partner) {
                try {
                    $pdo->beginTransaction();

                    // Supprimer les deux utilisateurs de la file d'attente
                    $stmt = $pdo->prepare("DELETE FROM waiting_users WHERE user_id IN (?, ?)");
                    $stmt->execute([$userId, $partner]);

                    // Créer la paire
                    $stmt = $pdo->prepare("INSERT INTO active_pairs (user1_id, user2_id) VALUES (?, ?)");
                    $stmt->execute([$userId, $partner]);
                    $pairId = $pdo->lastInsertId();

                    $pdo->commit();

                    echo json_encode([
                        'success' => true,
                        'pair_id' => $pairId,
                        'partner_id' => $partner
                    ]);
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Erreur de création de la paire']);
                }
            } else {
                // Toujours en attente, pas de partenaire pour l'instant
                echo json_encode(['success' => false, 'message' => 'En attente d\'un partenaire']);
            }
            break;
        }

        case 'send_message': {
            $pairId = $_POST['pair_id'] ?? null;
            $message = trim($_POST['message'] ?? '');

            $maxLength = 500;
            if (mb_strlen($message) > $maxLength) {
                echo json_encode(['success' => false, 'error' => 'Message trop long (max '.$maxLength.' caractères)']);
                exit;
            }

            // Nettoyage du message (supprime les balises HTML)
            $message = strip_tags($message);

            // Optionnel : n'autoriser que certains caractères
            // $message = preg_replace('/[^a-zA-Z0-9 .,!?\'"-]/u', '', $message);

            if (!$pairId || empty($message)) {
                echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
                exit;
            }


            // Vérifier que l'utilisateur fait partie de la paire
            $stmt = $pdo->prepare("SELECT * FROM active_pairs WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
            $stmt->execute([$pairId, $userId, $userId]);
            $pair = $stmt->fetch();

            if (!$pair) {
                echo json_encode(['success' => false, 'error' => 'Vous ne faites pas partie de cette conversation']);
                exit;
            }

            // Insérer le message dans la base
            $encrypted = encrypt_message($message);
            $stmt = $pdo->prepare("INSERT INTO messages (pair_id, sender_id, message) VALUES (?, ?, ?)");
            $stmt->execute([$pairId, $userId, $encrypted]);

            // Récupérer l'ID du destinataire
            $destUserId = ($pair['user1_id'] == $userId) ? $pair['user2_id'] : $pair['user1_id'];

            // Envoi de la notification push au destinataire
            $stmt = $pdo->prepare("SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?");
            $stmt->execute([$destUserId]);
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($subscriptions) {
                $webPush = new \Minishlink\WebPush\WebPush([
                    'VAPID' => [
                        'subject' => $VAPID['subject'],
                        'publicKey' => $VAPID['publicKey'],
                        'privateKey' => $VAPID['privateKey'],
                    ],
                ]);

                $payload = json_encode([
                    'title' => 'Nouveau message',
                    'body' => $message,
                    'url' => '/chat.php'
                ]);

                foreach ($subscriptions as $sub) {
                    $subscription = \Minishlink\WebPush\Subscription::create([
                        'endpoint' => $sub['endpoint'],
                        'publicKey' => $sub['p256dh'],
                        'authToken' => $sub['auth'],
                    ]);
                    $webPush->queueNotification($subscription, $payload);
                }
                foreach ($webPush->flush() as $report) {
                    // Log si besoin
                }
            }

            echo json_encode(['success' => true, 'message' => 'Message envoyé']);
            break;
        }

        case 'leave_chat': {
            $pairId = $_POST['pair_id'] ?? null;

            if (!$pairId) {
                echo json_encode(['success' => false, 'error' => 'Paramètre pair_id manquant']);
                exit;
            }

            // Vérifier que l'utilisateur fait partie de la paire
            $stmt = $pdo->prepare("SELECT * FROM active_pairs WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
            $stmt->execute([$pairId, $userId, $userId]);
            $pair = $stmt->fetch();

            if (!$pair) {
                echo json_encode(['success' => false, 'error' => 'Vous ne faites pas partie de cette conversation']);
                exit;
            }

            // Supprimer la paire active (ce qui supprimera aussi les messages grâce au ON DELETE CASCADE)
            $stmt = $pdo->prepare("DELETE FROM active_pairs WHERE id = ?");
            $stmt->execute([$pairId]);

            echo json_encode(['success' => true, 'message' => 'Vous avez quitté le chat']);
            break;
        }

        case 'report_user': {
            $pairId = $_POST['pair_id'] ?? null;
            $reason = trim($_POST['reason'] ?? '');

            if (!$pairId || empty($reason)) {
                echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
                exit;
            }

            // Récupérer le partenaire à signaler
            $stmt = $pdo->prepare("SELECT user1_id, user2_id FROM active_pairs WHERE id = ?");
            $stmt->execute([$pairId]);
            $pair = $stmt->fetch();

            if (!$pair || ($pair['user1_id'] != $userId && $pair['user2_id'] != $userId)) {
                echo json_encode(['success' => false, 'error' => 'Paire invalide']);
                exit;
            }

            $reportedId = ($pair['user1_id'] == $userId) ? $pair['user2_id'] : $pair['user1_id'];

            // Enregistrement du signalement
            $stmt = $pdo->prepare("INSERT INTO reports (reporter_id, reported_id, reason, pair_id) VALUES (?, ?, ?, ?)");
            $success = $stmt->execute([$userId, $reportedId, $reason, $pairId]);

            // Envoi automatique d'un mail à la modération
            if ($success) {
                $mail = new PHPMailer(true);
                try {
                    $secret = $mailConfig['secret_key'];
                    $block_token = hash('sha256', $reportedId . $secret);
                    $block_link = "https://test.dynag.co/block_user.php?user_id=$reportedId&token=$block_token";

                    // Config SMTP depuis config.php
                    $mail->isSMTP();
                    $mail->Host = $mailConfig['host'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $mailConfig['username'];
                    $mail->Password = $mailConfig['password'];
                    $mail->SMTPSecure = $mailConfig['encryption'];
                    $mail->Port = $mailConfig['port'];

                    $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);

                    // Récupérer tous les emails admin
                    $stmt = $pdo->query("SELECT email FROM users WHERE admin = 1 AND blocked = 0");
                    $adminEmails = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    foreach ($adminEmails as $adminEmail) {
                        $mail->addAddress($adminEmail);
                    }

                    $mail->Subject = 'Nouveau signalement sur Chat Pirate';
                    $mail->Body = "Un utilisateur a signalé un membre.\n\n"
                        . "ID du signaleur : $userId\n"
                        . "ID du signalé : $reportedId\n"
                        . "Pair ID : $pairId\n"
                        . "Raison :\n$reason\n"
                        . "Date : " . date('Y-m-d H:i:s') . "\n"
                        . "Pour bloquer cet utilisateur immédiatement, cliquez ici :\n$block_link\n";

                    $mail->send();
                } catch (Exception $e) {
                    // Log ou gestion d'erreur
                }
            }

            echo json_encode(['success' => $success]);
            break;
        }

        case 'generate_invite_code': {
            $userId = $_SESSION['user_id'];

            function generateInviteCode($length = 10) {
                $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $str = '';
                for ($i = 0; $i < $length; $i++) {
                    $str .= $chars[random_int(0, strlen($chars) - 1)];
                }
                return $str;
            }

            do {
                $invite_code = generateInviteCode(10);
                $stmt = $pdo->prepare("SELECT code FROM invitation_codes WHERE code = ?");
                $stmt->execute([$invite_code]);
            } while ($stmt->fetch());

            $stmt = $pdo->prepare("INSERT INTO invitation_codes (code, user_id, used) VALUES (?, ?, 0)");
            $stmt->execute([$invite_code, $userId]);

            echo json_encode([
                'success' => true,
                'code' => $invite_code
            ]);
            exit;
        }
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur : ' . $e->getMessage()]);
}

// Broadcast
function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

if ($_POST['action'] === 'broadcast' && is_admin()) {
    $msg = trim($_POST['message'] ?? '');
    if ($msg) {
        $stmt = $pdo->prepare("INSERT INTO broadcasts (message, created_at) VALUES (?, NOW())");
        $stmt->execute([$msg]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
if ($_POST['action'] === 'cancel_search' && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("DELETE FROM waiting_users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    exit;
}

function encrypt_message($plaintext) {
    $key = base64_decode(CHAT_ENCRYPTION_KEY);
    $method = 'aes-256-cbc';
    $iv_length = openssl_cipher_iv_length($method);
    $iv = openssl_random_pseudo_bytes($iv_length);
    $ciphertext = openssl_encrypt($plaintext, $method, $key, 0, $iv);
    // On stocke IV + message chiffré (séparés par ::)
    return base64_encode($iv . '::' . $ciphertext);
}




?>
