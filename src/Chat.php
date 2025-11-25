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

            // Select a user who is searching and not me
            // AND who I have not blocked
            // AND who has not blocked me
            // FOR UPDATE to lock the row
            $sql = "
                SELECT id FROM users 
                WHERE status = 'searching' 
                AND id != ? 
                AND id NOT IN (SELECT blocked_user_id FROM blocked_users WHERE user_id = ?)
                AND id NOT IN (SELECT user_id FROM blocked_users WHERE blocked_user_id = ?)
                LIMIT 1 FOR UPDATE
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId, $userId, $userId]);
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

    public function reportUser($reporterId, $reportedId, $reason) {
        $encryptedReason = $this->encryption->encrypt($reason);
        $stmt = $this->pdo->prepare("INSERT INTO reports (reporter_id, reported_id, reason) VALUES (?, ?, ?)");
        return $stmt->execute([$reporterId, $reportedId, $encryptedReason]);
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
