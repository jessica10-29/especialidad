<?php
// probador_completo.php
// Prueba de envío SMTP usando la configuración real (sin credenciales hardcodeadas).
// Bloqueado en producción para evitar exposición.

require_once __DIR__ . '/config_mail.php';

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

// Restringir a entorno local
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$esLocalIp = in_array($ip, ['127.0.0.1', '::1']) || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.');
if (getenv('APP_ENV') !== 'local' && !$esLocalIp && PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Probador deshabilitado en produccion');
}

echo "<h2>Probador de puertos SMTP (usa secure/mail.env o variables de entorno)</h2>";

function probarPuerto(int $port, string $secureLabel): bool
{
    global $smtp;
    $mail = obtener_mailer();
    $mail->SMTPAutoTLS = false;
    $mail->Port = $port;
    $mail->SMTPSecure = ($secureLabel === 'tls')
        ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
        : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;

    echo "<h3>Probando $secureLabel en puerto $port...</h3>";
    try {
        $mail->addAddress($smtp['FROM_EMAIL']);
        $mail->Subject = "Prueba puerto $port";
        $mail->Body = "Prueba con {$smtp['SMTP_HOST']}:$port ($secureLabel)";
        $mail->send();
        echo "<b style='color:green;'>&#9989; Exito en puerto $port</b><br>";
        return true;
    } catch (\Throwable $e) {
        $msg = htmlspecialchars($mail->ErrorInfo ?: $e->getMessage());
        echo "<span style='color:red;'>&#10060; Fallo: $msg</span><br>";
        return false;
    }
}

$ok587 = probarPuerto(587, 'tls');
echo "<hr>";
$ok465 = probarPuerto(465, 'ssl');

echo "<h2>Conclusión:</h2>";
if ($ok587 || $ok465) {
    echo "<p style='color:green;font-weight:bold;'>Usa el puerto que funcionó en secure/mail.env.</p>";
} else {
    echo "<p style='color:red;font-weight:bold;'>Ninguno respondió. Revisa credenciales o bloqueos del hosting.</p>";
}
