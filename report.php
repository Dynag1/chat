<?php
require 'config.php';
check_auth();

header('Content-Type: application/json');

$reporter_id = $_SESSION['user_id'];
$reported_id = isset($_POST['reported_id']) ? intval($_POST['reported_id']) : 0;
$reason = trim($_POST['reason'] ?? '');

if (!$reported_id || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes.']);
    exit;
}

// Enregistrement dans la base (exemple avec PDO)
$stmt = $pdo->prepare("INSERT INTO reports (reporter_id, reported_id, reason, created_at) VALUES (?, ?, ?, NOW())");
$ok = $stmt->execute([$reporter_id, $reported_id, $reason]);

echo json_encode(['success' => $ok]);
