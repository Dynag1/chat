<?php
// public/api/action.php

require_once __DIR__ . '/../../src/session_config.php';
require_once __DIR__ . '/../../src/Chat.php';
require_once __DIR__ . '/../../src/Security.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

try {
    $chat = new Chat();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate JSON input
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
            exit;
        }
        
        // CSRF validation
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
        if (!Security::validateCSRFToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF validation failed']);
            exit;
        }
        
        // Rate limiting
        $rateLimitKey = 'api_action_' . $userId;
        if (!Security::checkRateLimit($rateLimitKey, 20, 60)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Too many requests. Please slow down.']);
            exit;
        }

        if ($action === 'report') {
            // Validate input
            if (!isset($input['reported_id']) || !isset($input['reason'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }
            
            $reportedId = filter_var($input['reported_id'], FILTER_VALIDATE_INT);
            $reason = Security::sanitizeInput($input['reason']);
            $chatId = isset($input['chat_id']) ? filter_var($input['chat_id'], FILTER_VALIDATE_INT) : null;
            
            if (!$reportedId || empty($reason)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid input']);
                exit;
            }
            
            // Prevent self-reporting
            if ($reportedId === $userId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Cannot report yourself']);
                exit;
            }
            
            if ($chat->reportUser($userId, $reportedId, $reason, $chatId)) {
                echo json_encode(['success' => true, 'message' => 'User reported successfully.']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save report.']);
            }
        } elseif ($action === 'block') {
            // Validate input
            if (!isset($input['blocked_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }
            
            $blockedId = filter_var($input['blocked_id'], FILTER_VALIDATE_INT);
            
            if (!$blockedId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid input']);
                exit;
            }
            
            // Prevent self-blocking
            if ($blockedId === $userId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Cannot block yourself']);
                exit;
            }
            
            $chat->blockUser($userId, $blockedId);
            
            // Also end any active chat with this user
            $activeChat = $chat->getActiveChat($userId);
            if ($activeChat) {
                $partnerId = ($activeChat['user1_id'] == $userId) ? $activeChat['user2_id'] : $activeChat['user1_id'];
                if ($partnerId == $blockedId) {
                    $chat->endChat($activeChat['id']);
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'User blocked successfully.']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
