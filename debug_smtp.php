<?php
// debug_smtp.php - Diagnóstico profundo de conexión Gmail
require_once 'config_mail.php';

echo "<h2>Iniciando diagnóstico de correo...</h2>";

try {
    $mail = obtener_mailer();
    $mail->SMTPDebug = 3; // Nivel máximo de detalle

    // Capturar el output del debug
    $mail->Debugoutput = function ($str, $level) {
        echo "<pre>DEBUG: $str</pre>";
    };

    echo "<h3>1. Intentando conexión...</h3>";
    $mail->addAddress('plataforma.unicali@gmail.com'); // Probar enviándose a sí mismo
    $mail->Subject = 'Diagnóstico SMTP';
    $mail->Body    = 'Prueba de diagnóstico';

    if ($mail->send()) {
        echo "<h3 style='color:green;'>✅ ÉXITO: El correo se envió correctamente.</h3>";
    }
} catch (Exception $e) {
    echo "<h3 style='color:red;'>❌ ERROR DE AUTENTICACIÓN</h3>";
    echo "<p>El servidor respondió: <b>" . $e->getMessage() . "</b></p>";

    echo "<h4>Posibles causas:</h4>";
    echo "<ul>
        <li><b>Bloqueo de Google:</b> Google detectó un inicio de sesión desde el servidor de InfinityFree y lo bloqueó. Revisa tu correo de Gmail para ver si tienes un aviso de security alert.</li>
        <li><b>Contraseña de aplicación incorrecta:</b> Asegúrate de que no haya espacios al pegar la clave en config_mail.php.</li>
        <li><b>Restricción de Hosting:</b> InfinityFree a veces limita las conexiones SMTP externas.</li>
    </ul>";
}

echo "<hr><p><a href='recover_password.php'>Volver</a></p>";
