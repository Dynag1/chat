<?php
// src/User.php

require_once __DIR__ . '/../conf/conf.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Encryption.php';
require_once __DIR__ . '/Email.php';

class User {
    private $pdo;
    private $encryption;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
        $this->encryption = new Encryption();
    }

    public function register($username, $email, $password) {
        // Check if email already exists (using hash)
        $emailHash = hash('sha256', $email);
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email_hash = ?");
        $stmt->execute([$emailHash]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email déjà enregistré.'];
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $encryptedUsername = $this->encryption->encrypt($username);
        $encryptedEmail = $this->encryption->encrypt($email);
        
        // Generate verification token
        $verificationToken = bin2hex(random_bytes(32));
        $verificationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $this->pdo->prepare(
            "INSERT INTO users (username, email, email_hash, password_hash, verification_token, verification_expires) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        
        if ($stmt->execute([
            $encryptedUsername, 
            $encryptedEmail, 
            $emailHash, 
            $passwordHash,
            $verificationToken,
            $verificationExpires
        ])) {
            // Send verification email
            $emailService = new Email();
            error_log("Attempting to send verification email to $email");
            $emailSent = $emailService->sendVerificationEmail($email, $verificationToken);
            error_log("Email sent result: " . ($emailSent ? 'true' : 'false'));
            
            if ($emailSent) {
                return [
                    'success' => true, 
                    'message' => 'Inscription réussie ! Un email de vérification a été envoyé à votre adresse.'
                ];
            } else {
                return [
                    'success' => true, 
                    'message' => 'Inscription réussie ! Cependant, l\'email de vérification n\'a pas pu être envoyé. Contactez l\'administrateur.'
                ];
            }
        }
        return ['success' => false, 'message' => 'Échec de l\'inscription.'];
    }

    public function login($email, $password) {
        $emailHash = hash('sha256', $email);
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email_hash = ?");
        $stmt->execute([$emailHash]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['is_blocked']) {
                return ['success' => false, 'message' => 'Compte bloqué.'];
            }
            
            // Check email verification (if column exists in database)
            if (isset($user['email_verified']) && !$user['email_verified']) {
                return [
                    'success' => false, 
                    'message' => 'Veuillez vérifier votre email avant de vous connecter. Consultez votre boîte de réception.',
                    'email_not_verified' => true
                ];
            }
            
            // Decrypt username for session
            $username = $this->encryption->decrypt($user['username']);
            return [
                'success' => true, 
                'user' => [
                    'id' => $user['id'], 
                    'username' => $username,
                    'is_admin' => $user['is_admin'] ?? 0
                ]
            ];
        }
        return ['success' => false, 'message' => 'Identifiants invalides.'];
    }

    public function getUser($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if ($user) {
            $user['username'] = $this->encryption->decrypt($user['username']);
            $user['email'] = $this->encryption->decrypt($user['email']);
            unset($user['password_hash']);
            unset($user['email_hash']);
        }
        return $user;
    }

    public function verifyPassword($userId, $password) {
        $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            return true;
        }
        return false;
    }

    public function updateEmail($userId, $newEmail) {
        // Check if email already exists
        $emailHash = hash('sha256', $newEmail);
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email_hash = ? AND id != ?");
        $stmt->execute([$emailHash, $userId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Cet email est déjà utilisé.'];
        }

        $encryptedEmail = $this->encryption->encrypt($newEmail);
        
        // Generate new verification token
        $verificationToken = bin2hex(random_bytes(32));
        $verificationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $this->pdo->prepare("UPDATE users SET email = ?, email_hash = ?, email_verified = 0, verification_token = ?, verification_expires = ? WHERE id = ?");
        
        if ($stmt->execute([$encryptedEmail, $emailHash, $verificationToken, $verificationExpires, $userId])) {
            // Send verification email
            $emailService = new Email();
            $emailService->sendVerificationEmail($newEmail, $verificationToken);
            
            return ['success' => true, 'message' => 'Email mis à jour. Veuillez vérifier votre nouvelle adresse.'];
        }
        return ['success' => false, 'message' => 'Erreur lors de la mise à jour de l\'email.'];
    }

    public function updatePassword($userId, $newPassword) {
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        
        if ($stmt->execute([$passwordHash, $userId])) {
            return ['success' => true, 'message' => 'Mot de passe mis à jour avec succès.'];
        }
        return ['success' => false, 'message' => 'Erreur lors de la mise à jour du mot de passe.'];
    }

    public function deleteAccount($userId) {
        try {
            $this->pdo->beginTransaction();

            // Delete messages
            $stmt = $this->pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?");
            $stmt->execute([$userId, $userId]);

            // Delete blocks
            $stmt = $this->pdo->prepare("DELETE FROM blocked_users WHERE user_id = ? OR blocked_user_id = ?");
            $stmt->execute([$userId, $userId]);
            
            // Delete reports
            $stmt = $this->pdo->prepare("DELETE FROM reports WHERE reporter_id = ? OR reported_id = ?");
            $stmt->execute([$userId, $userId]);

            // Delete user
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Compte supprimé définitivement.'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => 'Erreur lors de la suppression du compte : ' . $e->getMessage()];
        }
    }

    public function initiatePasswordReset($email) {
        // Check if email exists
        $emailHash = hash('sha256', $email);
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email_hash = ?");
        $stmt->execute([$emailHash]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $this->pdo->prepare("UPDATE users SET verification_token = ?, verification_expires = ? WHERE id = ?");
            if ($stmt->execute([$token, $expires, $user['id']])) {
                $emailService = new Email();
                $emailService->sendPasswordResetEmail($email, $token);
                return true;
            }
        }
        // Return true even if user not found to prevent enumeration
        return true; 
    }

    public function verifyResetToken($token) {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE verification_token = ? AND verification_expires > NOW()");
        $stmt->execute([$token]);
        return $stmt->fetch() ? true : false;
    }

    public function resetPasswordWithToken($token, $newPassword) {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE verification_token = ? AND verification_expires > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
            
            // Update password and clear token, also verify email implicitly
            $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ?, verification_token = NULL, verification_expires = NULL, email_verified = 1 WHERE id = ?");
            
            if ($stmt->execute([$passwordHash, $user['id']])) {
                return ['success' => true, 'message' => 'Mot de passe réinitialisé avec succès.'];
            }
        }
        return ['success' => false, 'message' => 'Lien de réinitialisation invalide ou expiré.'];
    }
}
