<?php
// config_mail.php - Configuración centralizada para el envío de correos

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/psr_log_stub.php';
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

// Cargar credenciales SMTP externas
$smtpConfigPath = __DIR__ . '/secure/mail.php';
if (!file_exists($smtpConfigPath)) {
    die('Falta secure/mail.php con las credenciales SMTP.');
}
$smtp = require $smtpConfigPath;
if (!is_array($smtp)) {
    throw new RuntimeException('La configuracion SMTP no devolvio un arreglo valido.');
}

$smtp = array_merge(
    [
        'SMTP_HOST' => '',
        'SMTP_PORT' => 587,
        'SMTP_SECURE' => 'tls',
        'SMTP_USER' => '',
        'SMTP_PASS' => '',
        'FROM_EMAIL' => '',
        'FROM_NAME' => 'Plataforma UNICALI',
        'SMTP_DEBUG' => 0,
        'TIMEOUT' => 25,
        'ALLOW_SELF_SIGNED' => false,
    ],
    $smtp
);

if ($smtp['FROM_EMAIL'] === '' && $smtp['SMTP_USER'] !== '') {
    $smtp['FROM_EMAIL'] = $smtp['SMTP_USER'];
}

if ($smtp['FROM_EMAIL'] === '') {
    throw new RuntimeException('No se definio FROM_EMAIL en la configuracion SMTP.');
}
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

    $secureMode = strtolower((string) $smtp['SMTP_SECURE']);
    if ($secureMode === 'ssl' || $secureMode === 'smtps') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->Port = $smtp['SMTP_PORT'];
    $mail->SMTPAutoTLS = false;
    $mail->Timeout = $smtp['TIMEOUT'];

    // Configuración para saltar errores de certificados SSL
    // Solo desactivar verificacion de certificados si se solicita explicitamente (ej. entorno local)
    if (!empty($smtp['ALLOW_SELF_SIGNED'])) {
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ];
    }

    // Configuración del remitente
    $mail->setFrom($smtp['FROM_EMAIL'], $smtp['FROM_NAME']);

    return $mail;
}
