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

    public function blockUser($userId) {
        $stmt = $this->pdo->prepare("UPDATE users SET is_blocked = 1 WHERE id = ?");
        return $stmt->execute([$userId]);
    }

    public function unblockUser($userId) {
        $stmt = $this->pdo->prepare("UPDATE users SET is_blocked = 0 WHERE id = ?");
        return $stmt->execute([$userId]);
    }

    public function deleteReport($reportId) {
        $stmt = $this->pdo->prepare("DELETE FROM reports WHERE id = ?");
        return $stmt->execute([$reportId]);
    }
}
