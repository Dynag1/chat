<?php
// src/Admin.php

require_once __DIR__ . '/../conf/conf.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Encryption.php';

class Admin {
    private $pdo;
    private $encryption;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
        $this->encryption = new Encryption();
    }

    public function getReports() {
        $stmt = $this->pdo->prepare("
            SELECT r.*, 
                   u1.username as reporter_username, 
                   u2.username as reported_username,
                   u2.is_blocked as reported_is_blocked,
                   u2.registration_ip as reported_ip
            FROM reports r
            JOIN users u1 ON r.reporter_id = u1.id
            JOIN users u2 ON r.reported_id = u2.id
            ORDER BY r.created_at DESC
        ");
        $stmt->execute();
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reports as &$report) {
            $report['reporter_username'] = $this->encryption->decrypt($report['reporter_username']);
            $report['reported_username'] = $this->encryption->decrypt($report['reported_username']);
            $report['reason'] = $this->encryption->decrypt($report['reason']);
            
            // Decrypt conversation snapshot if present
            if (!empty($report['conversation_snapshot'])) {
                $decrypted = $this->encryption->decrypt($report['conversation_snapshot']);
                $report['conversation'] = json_decode($decrypted, true) ?: [];
            } else {
                $report['conversation'] = [];
            }
        }

        return $reports;
    }

    public function blockUser($userId, $adminId = null, $banIpAndEmail = true) {
        try {
            $this->pdo->beginTransaction();
            
            // Block the user account
            $stmt = $this->pdo->prepare("UPDATE users SET is_blocked = 1 WHERE id = ?");
            $stmt->execute([$userId]);
            
            // If admin wants to ban IP and email
            if ($banIpAndEmail && $adminId) {
                // Get user's IP and email_hash
                $stmt = $this->pdo->prepare("SELECT registration_ip, email_hash FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Ban the IP if available
                    if (!empty($user['registration_ip'])) {
                        $this->banIdentifier('ip', $user['registration_ip'], $userId, $adminId);
                    }
                    
                    // Ban the email hash
                    if (!empty($user['email_hash'])) {
                        $this->banIdentifier('email_hash', $user['email_hash'], $userId, $adminId);
                    }
                }
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error blocking user: " . $e->getMessage());
            return false;
        }
    }
    
    public function banIdentifier($type, $value, $bannedUserId, $adminId, $reason = null) {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT IGNORE INTO banned_identifiers (type, value, banned_user_id, banned_by_admin_id, reason) 
                 VALUES (?, ?, ?, ?, ?)"
            );
            return $stmt->execute([$type, $value, $bannedUserId, $adminId, $reason]);
        } catch (Exception $e) {
            error_log("Error banning identifier: " . $e->getMessage());
            return false;
        }
    }

    public function unblockUser($userId, $unbanIpAndEmail = true) {
        try {
            $this->pdo->beginTransaction();
            
            // Unblock the user account
            $stmt = $this->pdo->prepare("UPDATE users SET is_blocked = 0 WHERE id = ?");
            $stmt->execute([$userId]);
            
            // Remove IP and email bans if requested
            if ($unbanIpAndEmail) {
                // Get user's IP and email_hash
                $stmt = $this->pdo->prepare("SELECT registration_ip, email_hash FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Remove IP ban
                    if (!empty($user['registration_ip'])) {
                        $stmt = $this->pdo->prepare("DELETE FROM banned_identifiers WHERE type = 'ip' AND value = ?");
                        $stmt->execute([$user['registration_ip']]);
                    }
                    
                    // Remove email hash ban
                    if (!empty($user['email_hash'])) {
                        $stmt = $this->pdo->prepare("DELETE FROM banned_identifiers WHERE type = 'email_hash' AND value = ?");
                        $stmt->execute([$user['email_hash']]);
                    }
                }
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error unblocking user: " . $e->getMessage());
            return false;
        }
    }
    
    public static function isIdentifierBanned($type, $value) {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare("SELECT id FROM banned_identifiers WHERE type = ? AND value = ?");
        $stmt->execute([$type, $value]);
        return $stmt->fetch() !== false;
    }

    public function deleteReport($reportId) {
        $stmt = $this->pdo->prepare("DELETE FROM reports WHERE id = ?");
        return $stmt->execute([$reportId]);
    }
}
