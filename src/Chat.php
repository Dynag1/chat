<?php
// src/Chat.php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Encryption.php';

class Chat {
    private $pdo;
    private $encryption;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
        $this->encryption = new Encryption();
    }

    public function setStatus($userId, $status) {
        $stmt = $this->pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$status, $userId]);
    }

    public function findMatch($userId) {
        // Look for someone else searching
        // Use transaction to prevent race conditions
        $this->pdo->beginTransaction();

        try {
            // Check if I am already in a chat (matched by someone else while I was waiting)
            $stmt = $this->pdo->prepare("SELECT status FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $currentUser = $stmt->fetch();

            if ($currentUser['status'] === 'in_chat') {
                // I have been matched!
                $this->pdo->commit();
                $activeChat = $this->getActiveChat($userId);
                if ($activeChat) {
                    // We need partner ID
                    $partnerId = ($activeChat['user1_id'] == $userId) ? $activeChat['user2_id'] : $activeChat['user1_id'];
                    return ['success' => true, 'chat_id' => $activeChat['id'], 'partner_id' => $partnerId];
                }
            }

            // First, ensure we are 'searching'
            $stmt = $this->pdo->prepare("UPDATE users SET status = 'searching' WHERE id = ?");
            $stmt->execute([$userId]);

            // Get current user's intent
            $stmt = $this->pdo->prepare("SELECT intent FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userIntent = $stmt->fetchColumn();

            // Determine target intent
            $targetIntent = '';
            if ($userIntent === 'discuter') {
                $targetIntent = 'discuter';
            } elseif ($userIntent === 'aider') {
                $targetIntent = 'besoin_aide';
            } elseif ($userIntent === 'besoin_aide') {
                $targetIntent = 'aider';
            }

            // Select a user who is searching and not me
            // AND who has the matching intent
            // AND who I have not blocked
            // AND who has not blocked me
            // FOR UPDATE to lock the row
            $sql = "
                SELECT id FROM users 
                WHERE status = 'searching' 
                AND intent = ?
                AND id != ? 
                AND id NOT IN (SELECT blocked_user_id FROM blocked_users WHERE user_id = ?)
                AND id NOT IN (SELECT user_id FROM blocked_users WHERE blocked_user_id = ?)
                LIMIT 1 FOR UPDATE
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$targetIntent, $userId, $userId, $userId]);
            $partner = $stmt->fetch();

            if ($partner) {
                // Found a match!
                $partnerId = $partner['id'];

                // Create chat
                $stmt = $this->pdo->prepare("INSERT INTO chats (user1_id, user2_id, status) VALUES (?, ?, 'active')");
                $stmt->execute([$userId, $partnerId]);
                $chatId = $this->pdo->lastInsertId();

                // Update statuses
                $update = $this->pdo->prepare("UPDATE users SET status = 'in_chat' WHERE id IN (?, ?)");
                $update->execute([$userId, $partnerId]);

                $this->pdo->commit();
                return ['success' => true, 'chat_id' => $chatId, 'partner_id' => $partnerId];
            } else {
                // No match yet
                $this->pdo->commit();
                return ['success' => false, 'message' => 'Waiting for partner...'];
            }
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Find Match Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error finding match.'];
        }
    }

    public function getActiveChat($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM chats WHERE (user1_id = ? OR user2_id = ?) AND status = 'active' LIMIT 1");
        $stmt->execute([$userId, $userId]);
        return $stmt->fetch();
    }

    public function sendMessage($chatId, $senderId, $content) {
        $encryptedContent = $this->encryption->encrypt($content);
        $stmt = $this->pdo->prepare("INSERT INTO messages (chat_id, sender_id, content) VALUES (?, ?, ?)");
        return $stmt->execute([$chatId, $senderId, $encryptedContent]);
    }

    public function getMessages($chatId, $lastId = 0) {
        $stmt = $this->pdo->prepare("SELECT * FROM messages WHERE chat_id = ? AND id > ? ORDER BY id ASC");
        $stmt->execute([$chatId, $lastId]);
        $messages = $stmt->fetchAll();

        foreach ($messages as &$msg) {
            $msg['content'] = $this->encryption->decrypt($msg['content']);
        }
        return $messages;
    }

    public function endChat($chatId) {
        $stmt = $this->pdo->prepare("UPDATE chats SET status = 'ended' WHERE id = ?");
        $stmt->execute([$chatId]);

        // Delete messages associated with this chat
        $stmt = $this->pdo->prepare("DELETE FROM messages WHERE chat_id = ?");
        $stmt->execute([$chatId]);

        // Get users to reset status
        $stmt = $this->pdo->prepare("SELECT user1_id, user2_id FROM chats WHERE id = ?");
        $stmt->execute([$chatId]);
        $chat = $stmt->fetch();

        if ($chat) {
            $update = $this->pdo->prepare("UPDATE users SET status = 'online' WHERE id IN (?, ?)");
            $update->execute([$chat['user1_id'], $chat['user2_id']]);
        }
        
        return ['success' => true];
    }

    /**
     * End chat after a report WITHOUT deleting messages (for admin review)
     */
    public function endChatAfterReport($chatId) {
        // Use 'ended' status (compatible with ENUM), messages are NOT deleted
        $stmt = $this->pdo->prepare("UPDATE chats SET status = 'ended' WHERE id = ?");
        $stmt->execute([$chatId]);

        // Get users to reset status - DON'T delete messages
        $stmt = $this->pdo->prepare("SELECT user1_id, user2_id FROM chats WHERE id = ?");
        $stmt->execute([$chatId]);
        $chat = $stmt->fetch();

        if ($chat) {
            $update = $this->pdo->prepare("UPDATE users SET status = 'online' WHERE id IN (?, ?)");
            $update->execute([$chat['user1_id'], $chat['user2_id']]);
            return [
                'success' => true,
                'user1_id' => $chat['user1_id'],
                'user2_id' => $chat['user2_id']
            ];
        }
        
        return ['success' => true];
    }

    public function reportUser($reporterId, $reportedId, $reason, $chatId = null) {
        try {
            $encryptedReason = $this->encryption->encrypt($reason);
            
            // Get conversation snapshot if chatId is provided
            $conversationSnapshot = null;
            if ($chatId) {
                error_log("Getting messages for chat_id: $chatId");
                $messages = $this->getAllChatMessages($chatId);
                error_log("Found " . count($messages) . " messages");
                if (!empty($messages)) {
                    $conversationSnapshot = $this->encryption->encrypt(json_encode($messages, JSON_UNESCAPED_UNICODE));
                    error_log("Conversation snapshot created, length: " . strlen($conversationSnapshot));
                }
            } else {
                error_log("No chat_id provided for report");
            }
            
            // Check if columns exist (for backwards compatibility)
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM reports LIKE 'conversation_snapshot'");
            $stmt->execute();
            $hasConversationColumn = $stmt->fetch() !== false;
            
            if ($hasConversationColumn) {
                $stmt = $this->pdo->prepare("INSERT INTO reports (reporter_id, reported_id, reason, conversation_snapshot, chat_id) VALUES (?, ?, ?, ?, ?)");
                $result = $stmt->execute([$reporterId, $reportedId, $encryptedReason, $conversationSnapshot, $chatId]);
            } else {
                // Fallback if columns don't exist yet
                error_log("Warning: conversation_snapshot column doesn't exist. Run the migration.");
                $stmt = $this->pdo->prepare("INSERT INTO reports (reporter_id, reported_id, reason) VALUES (?, ?, ?)");
                $result = $stmt->execute([$reporterId, $reportedId, $encryptedReason]);
            }
            
            error_log("Report saved: " . ($result ? 'success' : 'failed'));
            return $result;
        } catch (Exception $e) {
            error_log("Error in reportUser: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAllChatMessages($chatId) {
        $stmt = $this->pdo->prepare("SELECT m.*, u.username FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.chat_id = ? ORDER BY m.id ASC");
        $stmt->execute([$chatId]);
        $messages = $stmt->fetchAll();
        
        $result = [];
        foreach ($messages as $msg) {
            $result[] = [
                'sender_id' => $msg['sender_id'],
                'sender_name' => $this->encryption->decrypt($msg['username']),
                'content' => $this->encryption->decrypt($msg['content']),
                'created_at' => $msg['created_at']
            ];
        }
        return $result;
    }
    
    public function blockUser($userId, $blockedUserId) {
        // Check if already blocked
        $stmt = $this->pdo->prepare("SELECT id FROM blocked_users WHERE user_id = ? AND blocked_user_id = ?");
        $stmt->execute([$userId, $blockedUserId]);
        if ($stmt->fetch()) {
            return true; // Already blocked
        }
        
        $stmt = $this->pdo->prepare("INSERT INTO blocked_users (user_id, blocked_user_id) VALUES (?, ?)");
        return $stmt->execute([$userId, $blockedUserId]);
    }
}
