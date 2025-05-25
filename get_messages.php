<?php
session_start();
require 'config.php';
check_auth();

$pairId = intval($_GET['pair_id'] ?? 0);
function decrypt_message($encrypted) {
    $key = base64_decode(CHAT_ENCRYPTION_KEY);
    $method = 'aes-256-cbc';
    $decoded = base64_decode($encrypted);
    list($iv, $ciphertext) = explode('::', $decoded, 2);
    return openssl_decrypt($ciphertext, $method, $key, 0, $iv);
}
if (!$pairId) {
    echo json_encode(['success' => false, 'error' => 'pair_id manquant']);
    exit;
}

// Vérifier si la paire existe toujours
$stmt = $pdo->prepare("SELECT COUNT(*) FROM active_pairs WHERE id = ?");
$stmt->execute([$pairId]);
$pairExists = $stmt->fetchColumn() > 0;

if (!$pairExists) {
    echo json_encode(['success' => false, 'ended' => true, 'messages' => []]);
    exit;
}

// Récupérer les messages broadcast récents
$broadcasts = $pdo->query("SELECT * FROM broadcasts WHERE created_at > NOW() - INTERVAL 60 MINUTE")->fetchAll(PDO::FETCH_ASSOC);

// Correction ici : $last_id au lieu de $lastId
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$stmt = $pdo->prepare("
    SELECT m.* 
    FROM messages m 
    WHERE m.pair_id = ? AND m.id > ? 
    ORDER BY m.id ASC
");
$stmt->execute([$pairId, $last_id]); // Utilisation de $last_id
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($messages as &$msg) {
    try {
        $msg['message'] = decrypt_message($msg['message']);
    } catch (Exception $e) {
        $msg['message'] = '[Message chiffré corrompu]';
    }
}
echo json_encode(['success' => true, 'messages' => $messages, 'broadcasts' => $broadcasts]);

exit;



?>
