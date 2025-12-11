<?php
// public/api/chat.php

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

// Maximum message length
define('MAX_MESSAGE_LENGTH', 1000);

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
        
        if ($action === 'send_message') {
            // Rate limiting for messages
            $rateLimitKey = 'send_message_' . $userId;
            if (!Security::checkRateLimit($rateLimitKey, 30, 60)) {
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'Too many messages. Please slow down.']);
                exit;
            }
            
            // Validate input
            if (!isset($input['chat_id']) || !isset($input['content'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }
            
            $chatId = filter_var($input['chat_id'], FILTER_VALIDATE_INT);
            $content = trim($input['content']);
            
            // Validate message content
            if (!$chatId || empty($content)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid input']);
                exit;
            }
            
            if (strlen($content) > MAX_MESSAGE_LENGTH) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Message too long']);
                exit;
            }
            
            // Verify user is in this chat
            $activeChat = $chat->getActiveChat($userId);
            if ($activeChat && $activeChat['id'] == $chatId) {
                $success = $chat->sendMessage($chatId, $userId, $content);
                echo json_encode(['success' => $success]);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Chat not active']);
            }
            
        } elseif ($action === 'end_chat') {
            $chatId = isset($input['chat_id']) ? filter_var($input['chat_id'], FILTER_VALIDATE_INT) : null;
            
            // Verify ownership
            $activeChat = $chat->getActiveChat($userId);
            
            if ($activeChat) {
                // If chatId is provided, check it matches
                if (!$chatId || $activeChat['id'] == $chatId) {
                    $chat->endChat($activeChat['id']);
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Chat ID mismatch']);
                }
            } else {
                // Chat already ended or doesn't exist
                echo json_encode(['success' => true, 'message' => 'No active chat']);
            }
            
        } elseif ($action === 'find_match') {
            // Rate limiting for match finding
            $rateLimitKey = 'find_match_' . $userId;
            if (!Security::checkRateLimit($rateLimitKey, 60, 60)) {
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'Too many match requests. Please wait.']);
                exit;
            }
            
            $forceNew = $input['force_new'] ?? false;
            
            if ($forceNew) {
                // End current chat if any
                $activeChat = $chat->getActiveChat($userId);
                if ($activeChat) {
                    $chat->endChat($activeChat['id']);
                }
            }
            
            $result = $chat->findMatch($userId);
            echo json_encode($result);
            
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($action === 'get_messages') {
            // Validate input
            if (!isset($_GET['chat_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing chat_id']);
                exit;
            }
            
            $chatId = filter_var($_GET['chat_id'], FILTER_VALIDATE_INT);
            $lastId = isset($_GET['last_id']) ? filter_var($_GET['last_id'], FILTER_VALIDATE_INT) : 0;
            
            if (!$chatId || $lastId === false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                exit;
            }
            
            // Verify user is in this chat
            $activeChat = $chat->getActiveChat($userId);
            if ($activeChat && $activeChat['id'] == $chatId) {
                $messages = $chat->getMessages($chatId, $lastId);
                echo json_encode(['success' => true, 'messages' => $messages]);
            } else {
                echo json_encode(['success' => false, 'status' => 'ended']);
            }
            
        } elseif ($action === 'check_status') {
            // Check if we have an active chat
            $activeChat = $chat->getActiveChat($userId);
            if ($activeChat) {
                // Calculate partner ID
                $partnerId = ($activeChat['user1_id'] == $userId) ? $activeChat['user2_id'] : $activeChat['user1_id'];
                echo json_encode(['success' => true, 'status' => 'in_chat', 'chat_id' => $activeChat['id'], 'partner_id' => $partnerId]);
            } else {
                echo json_encode(['success' => true, 'status' => 'idle']);
            }
            
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    error_log('Chat API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
