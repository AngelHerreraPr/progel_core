<?php
// Progel_core/config/mailer.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

// =========================================================================
// CONFIGURACIÓN SMTP (Ajusta estos datos con tu proveedor de correo)
// =========================================================================
define('SMTP_HOST', 'smtp.office365.com'); // Ej: smtp.office365.com (Outlook/Office 365) o smtp.gmail.com (Gmail)
define('SMTP_PORT', 587);                 // 587 (TLS) o 465 (SSL)
define('SMTP_SECURE', PHPMailer::ENCRYPTION_STARTTLS); // STARTTLS o SMTPS
define('SMTP_USER', 'Angel.herrera@progel.com.mx');        // Correo emisor
define('SMTP_PASS', 'Empresa01@');          // Contraseña o Contraseña de Aplicación
define('MAIL_FROM_NAME', 'Sistema de Alertas - PROGEL'); // Nombre visible

/**
 * Función global para enviar correos usando PHPMailer
 * 
 * @param array|string $destinatarios Correo o array de correos destino
 * @param string $asunto Asunto del correo
 * @param string $cuerpoHTML Contenido en HTML
 * @return bool True si se envió, False si falló
 */
function enviarCorreoAlerta($destinatarios, $asunto, $cuerpoHTML) {
    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // Remitente
        $mail->setFrom(SMTP_USER, MAIL_FROM_NAME);

        // Destinatarios
        if (is_array($destinatarios)) {
            foreach ($destinatarios as $email) {
                if (!empty(trim($email))) {
                    $mail->addAddress(trim($email));
                }
            }
        } else {
            $mail->addAddress(trim($destinatarios));
        }

        // Contenido
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpoHTML;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error PHPMailer: " . $mail->ErrorInfo);
        return false;
    }
}

