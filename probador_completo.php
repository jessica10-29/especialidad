<?php
// probador_completo.php - Prueba exhaustiva de puertos SMTP
require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$user = 'plataforma.unicali@gmail.com';
$pass = 'llhueuzspoktpzol';

echo "<h2>Probador de Puertos Gmail</h2>";

function probar($port, $secure, $desc)
{
    global $user, $pass;
    echo "<h3>Probando: $desc (Puerto $port)...</h3>";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = $pass;
        $mail->SMTPSecure = $secure;
        $mail->Port = $port;
        $mail->Timeout = 10;

        $mail->setFrom($user, 'Test');
        $mail->addAddress($user);
        $mail->Subject = "Prueba Puerto $port";
        $mail->Body = "Prueba exitosa en puerto $port";

        if ($mail->send()) {
            echo "<b style='color:green;'>✅ ÉXITO en puerto $port!</b><br>";
            return true;
        }
    } catch (Exception $e) {
        echo "<span style='color:red;'>❌ FALLÓ: " . $mail->ErrorInfo . "</span><br>";
    }
    return false;
}

$ok587 = probar(587, PHPMailer::ENCRYPTION_STARTTLS, "STARTTLS");
echo "<hr>";
$ok465 = probar(465, PHPMailer::ENCRYPTION_SMTPS, "SSL/TLS");

echo "<h2>Conclusión:</h2>";
if ($ok587 || $ok465) {
    echo "<p style='color:green; font-weight:bold;'>Uno de los puertos funcionó. Usa esa configuración en config_mail.php.</p>";
} else {
    echo "<p style='color:red; font-weight:bold;'>Ninguno funcionó. Google está bloqueando la conexión o el host prohíbe el envío.</p>";
    echo "<h3>REVISA ESTO EN TU GMAIL:</h3>";
    echo "1. Ve a <a href='https://myaccount.google.com/notifications' target='_blank'>Notificaciones de Seguridad</a>.<br>";
    echo "2. ¿Ves un aviso de 'Inicio de sesión bloqueado'? Dale a 'SÍ, FUI YO'.<br>";
    echo "3. Asegúrate de tener la Verificación en 2 pasos ACTIVA en Gmail.";
}
