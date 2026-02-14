<?php
// config_mail.php - Configuración centralizada para el envío de correos

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

// Cargar credenciales SMTP externas
$smtpConfigPath = __DIR__ . '/secure/mail.php';
if (!file_exists($smtpConfigPath)) {
    die('Falta secure/mail.php con las credenciales SMTP.');
}
$smtp = require $smtpConfigPath;
// Ajustamos timeout de sockets al valor configurado para evitar esperas eternas en conexiones bloqueadas.
if (isset($smtp['TIMEOUT'])) {
    ini_set('default_socket_timeout', (string) $smtp['TIMEOUT']);
}

/**
 * Crea y configura una instancia de PHPMailer
 * @return PHPMailer
 */
function obtener_mailer()
{
    global $smtp; // ✅ CAMBIO CLAVE (antes usabas $GLOBALS)

    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';

    // === CONFIGURACIÓN SMTP ===
    $mail->isSMTP();
    $mail->SMTPDebug = $smtp['SMTP_DEBUG'];
    $mail->Host = $smtp['SMTP_HOST'];
    $mail->SMTPAuth = true;

    $mail->Username = $smtp['SMTP_USER'];
    $mail->Password = $smtp['SMTP_PASS'];

    $mail->SMTPSecure = ($smtp['SMTP_SECURE'] === 'tls')
        ? PHPMailer::ENCRYPTION_STARTTLS
        : PHPMailer::ENCRYPTION_SMTPS;

    $mail->Port = $smtp['SMTP_PORT'];
    $mail->SMTPAutoTLS = false;
    $mail->Timeout = $smtp['TIMEOUT'];

    // Configuración para saltar errores de certificados SSL
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true
        ]
    ];

    // Configuración del remitente
    $mail->setFrom($smtp['FROM_EMAIL'], $smtp['FROM_NAME']);

    return $mail;
}
