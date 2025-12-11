<?php
// src/Email.php

require_once __DIR__ . '/../conf/conf.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class Email {
    private $smtpHost;
    private $smtpPort;
    private $smtpUser;
    private $smtpPass;
    private $fromEmail;
    private $fromName;

    public function __construct() {
        $this->smtpHost = SMTP_HOST;
        $this->smtpPort = SMTP_PORT;
        $this->smtpUser = SMTP_USER;
        $this->smtpPass = SMTP_PASS;
        $this->fromEmail = SMTP_FROM;
        $this->fromName = SMTP_FROM_NAME;
    }

    public function sendVerificationEmail($toEmail, $token) {
        $verificationUrl = APP_URL . '/verify.php?token=' . urlencode($token);
        
        $subject = "Vérifiez votre adresse email - Atypi Chat";
        
        $htmlBody = $this->getVerificationEmailTemplate($verificationUrl);
        
        $textBody = "Bienvenue sur Atypi Chat !\n\n" .
                   "Veuillez vérifier votre adresse email en cliquant sur le lien suivant :\n" .
                   $verificationUrl . "\n\n" .
                   "Ce lien expire dans 24 heures.\n\n" .
                   "Si vous n'avez pas créé de compte, ignorez cet email.";

        return $this->send($toEmail, $subject, $htmlBody, $textBody);
    }

    public function sendPasswordResetEmail($toEmail, $token) {
        $resetUrl = APP_URL . '/reset_password.php?token=' . urlencode($token);
        
        $subject = "Réinitialisation de votre mot de passe - Atypi Chat";
        
        $htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #6c5ce7; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
        .button { display: inline-block; padding: 12px 30px; background: #6c5ce7; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Atypi Chat</h1>
        </div>
        <div class="content">
            <h2>Mot de passe oublié ?</h2>
            <p>Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.</p>
            <p>Pour choisir un nouveau mot de passe, cliquez sur le bouton ci-dessous :</p>
            <p style="text-align: center;">
                <a href="' . htmlspecialchars($resetUrl) . '" class="button">Réinitialiser mon mot de passe</a>
            </p>
            <p>Ou copiez ce lien dans votre navigateur :</p>
            <p style="word-break: break-all; background: white; padding: 10px; border-radius: 4px;">
                ' . htmlspecialchars($resetUrl) . '
            </p>
            <p><strong>Ce lien expire dans 1 heure.</strong></p>
            <p>Si vous n\'avez pas demandé cette réinitialisation, ignorez simplement cet email.</p>
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' Atypi Chat. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>';

        $textBody = "Réinitialisation de mot de passe - Atypi Chat\n\n" .
                   "Pour réinitialiser votre mot de passe, cliquez sur le lien suivant :\n" .
                   $resetUrl . "\n\n" .
                   "Ce lien expire dans 1 heure.\n\n" .
                   "Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.";

        return $this->send($toEmail, $subject, $htmlBody, $textBody);
    }

    public function sendContactEmail($fromEmail, $fromName, $subject, $message) {
        $adminEmail = SMTP_USER; // Send to the configured SMTP user (contact@bavarder.eu)
        
        $htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #6c5ce7; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Nouveau message de contact</h1>
        </div>
        <div class="content">
            <p><strong>De :</strong> ' . htmlspecialchars($fromName) . ' (' . htmlspecialchars($fromEmail) . ')</p>
            <p><strong>Sujet :</strong> ' . htmlspecialchars($subject) . '</p>
            <hr>
            <p>' . nl2br(htmlspecialchars($message)) . '</p>
        </div>
        <div class="footer">
            <p>Envoyé via le formulaire de contact Atypi Chat</p>
        </div>
    </div>
</body>
</html>';

        $textBody = "Nouveau message de contact\n\n" .
                   "De : $fromName ($fromEmail)\n" .
                   "Sujet : $subject\n\n" .
                   "Message :\n$message";

        // Note: We are sending FROM the system email but setting Reply-To to the user's email
        // This is better for deliverability than trying to spoof the From address
        return $this->send($adminEmail, "Contact: $subject", $htmlBody, $textBody, $fromEmail);
    }

    private function getVerificationEmailTemplate($verificationUrl) {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #6c5ce7; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
        .button { display: inline-block; padding: 12px 30px; background: #6c5ce7; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Atypi Chat</h1>
        </div>
        <div class="content">
            <h2>Bienvenue !</h2>
            <p>Merci de vous être inscrit sur Atypi Chat.</p>
            <p>Pour activer votre compte, veuillez cliquer sur le bouton ci-dessous :</p>
            <p style="text-align: center;">
                <a href="' . htmlspecialchars($verificationUrl) . '" class="button">Vérifier mon email</a>
            </p>
            <p>Ou copiez ce lien dans votre navigateur :</p>
            <p style="word-break: break-all; background: white; padding: 10px; border-radius: 4px;">
                ' . htmlspecialchars($verificationUrl) . '
            </p>
            <p><strong>Ce lien expire dans 24 heures.</strong></p>
            <p>Si vous n\'avez pas créé de compte, ignorez cet email.</p>
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' Atypi Chat. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>';
    }

    private function send($to, $subject, $htmlBody, $textBody, $replyTo = null) {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output if needed
            // $mail->Debugoutput = 'error_log';
            
            $mail->isSMTP();
            $mail->Host       = $this->smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->smtpUser;
            $mail->Password   = $this->smtpPass;
            
            // Auto-configure encryption based on port
            if ($this->smtpPort == 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($this->smtpPort == 587) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Default fallback
            }
            
            $mail->Port       = $this->smtpPort;
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 10; // Timeout in seconds

            // Recipients
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);
            
            if ($replyTo) {
                $mail->addReplyTo($replyTo);
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }
}
