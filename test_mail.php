<?php
require_once 'config_mail.php';

try {
    $mail = obtener_mailer();

    // ⚠️ Usa un correo REAL tuyo para la prueba
    $mail->addAddress('seguraordonezpaola@gmail.com');

    $mail->Subject = 'PRUEBA UNICALI';
    $mail->Body    = 'Esto es una prueba de correo electrónico desde la Plataforma UNICALI.';

    $mail->send();
    echo '✅ Correo enviado correctamente';

} catch (Exception $e) {
    echo '❌ Error al enviar el correo: ' . $mail->ErrorInfo;
}
