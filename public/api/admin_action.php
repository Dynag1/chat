<?php
require_once __DIR__ . '/../../src/session_config.php';
require_once __DIR__ . '/../../src/Admin.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$admin = new Admin();

try {
    switch ($action) {
        case 'block':
            if (!isset($input['user_id'])) throw new Exception('User ID required');
            $success = $admin->blockUser($input['user_id']);
            echo json_encode(['success' => $success]);
            break;
            
        case 'unblock':
            if (!isset($input['user_id'])) throw new Exception('User ID required');
            $success = $admin->unblockUser($input['user_id']);
            echo json_encode(['success' => $success]);
            break;
            
        case 'dismiss':
            if (!isset($input['report_id'])) throw new Exception('Report ID required');
            $success = $admin->deleteReport($input['report_id']);
            echo json_encode(['success' => $success]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
