<?php
// debug_smtp.php - Diagnostico de conexion SMTP usando la configuracion actual.
// Acceso limitado a entorno local para no exponer datos en produccion.

require_once __DIR__ . '/config_mail.php';

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$esLocal = in_array($ip, ['127.0.0.1', '::1']) ||
    str_starts_with($ip, '10.') ||
    str_starts_with($ip, '192.168.') ||
    preg_match('/^172\\.(1[6-9]|2[0-9]|3[01])\\./', $ip);

if (getenv('APP_ENV') !== 'local' && !$esLocal && PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Diagnostico SMTP deshabilitado en produccion.');
}

echo "<h2>Iniciando diagnostico de correo...</h2>";

try {
    $mail = obtener_mailer();
    $mail->SMTPDebug = 3; // Nivel maximo de detalle

    // Capturar el output del debug
    $mail->Debugoutput = function ($str, $level) {
        echo "<pre>DEBUG: " . htmlspecialchars($str) . "</pre>";
    };

    echo "<h3>1. Intentando conexion...</h3>";
    $mail->addAddress($smtp['FROM_EMAIL']); // Probar enviandose a si mismo
    $mail->Subject = 'Diagnostico SMTP';
    $mail->Body    = 'Prueba de diagnostico';

    if ($mail->send()) {
        echo "<h3 style='color:green;'>&#9989; EXITO: El correo se envio correctamente.</h3>";
    }
} catch (Exception $e) {
    echo "<h3 style='color:red;'>&#10060; ERROR DE AUTENTICACION</h3>";
    echo "<p>El servidor respondio: <b>" . htmlspecialchars($e->getMessage()) . "</b></p>";

    echo "<h4>Posibles causas:</h4>";
    echo "<ul>
        <li><b>Bloqueo de proveedor SMTP:</b> revisa alertas de seguridad.</li>
        <li><b>Contraseña de aplicacion incorrecta:</b> verifica secure/mail.env.</li>
        <li><b>Hosting restringe SMTP saliente:</b> prueba otro puerto o proveedor.</li>
    </ul>";
}

echo "<hr><p><a href='recover_password.php'>Volver</a></p>";
