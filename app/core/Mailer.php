<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

class Mailer {

    public static function send($to, $subject, $body) {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            // UPDATE THESE WITH YOUR OWN GMAIL ACCOUNT CREDENTIALS
            $mail->Username = 'diosojhonlloyd0@gmail.com';  // CHANGE THIS
            $mail->Password = 'lvws bfil vrmn bihf';      // CHANGE THIS
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('diosojhonlloyd0@gmail.com', 'ISKOLar System');
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log("Mailer Error: " . $mail->ErrorInfo);
            // Return false instead of dying so the application can handle the error gracefully
            return false;
        }
    }
    
    /**
     * Get last error message for debugging
     */
    public static function getLastError($to, $subject, $body) {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'diosojhonlloyd0@gmail.com';
            $mail->Password = 'lvws bfil vrmn bihf';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('diosojhonlloyd0@gmail.com', 'ISKOLar System');
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->send();
            return "Email sent successfully";

        } catch (Exception $e) {
            return "Email Error: " . $mail->ErrorInfo;
        }
    }
}
