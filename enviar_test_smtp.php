<?php
/**
 * enviar_test_smtp.php
 * 
 * Procesa el envío de prueba SMTP
 */

header('Content-Type: application/json; charset=UTF-8');

require_once 'conexion.php';
exigir_herramienta_local('La prueba SMTP');
require_once 'config_mail.php';

try {
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $asunto = htmlspecialchars($_POST['asunto'] ?? 'Correo de Prueba');
    $contenido = $_POST['contenido'] ?? '';

    if (!$email) {
        throw new Exception('Email inválido');
    }

    // Crear mailer
    $mail = obtener_mailer();
    $mail->SMTPDebug = 0;
    $mail->Timeout = 15;

    $mail->addAddress($email, 'Usuario de Prueba');
    $mail->Subject = $asunto;
    $mail->isHTML(true);
    $mail->Body = $contenido;
    $mail->AltBody = strip_tags($contenido);

    // Intentar envío
    if (@$mail->send()) {
        echo json_encode([
            'status' => 'ok',
            'email' => $email,
            'mensaje' => 'Correo enviado correctamente'
        ]);
    } else {
        throw new Exception($mail->ErrorInfo);
    }
} catch (Exception $e) {
    http_response_code(400);

    // Analizar tipo de error
    $error = $e->getMessage();
    $detalle = '';

    if (stripos($error, 'authenticate') !== false || stripos($error, 'failed') !== false) {
        $detalle = 'Error de autenticación. Verifica SMTP_USER y SMTP_PASS en secure/mail.php';
    } elseif (stripos($error, 'connect') !== false) {
        $detalle = 'No se pudo conectar al servidor SMTP. Verifica SMTP_HOST y SMTP_PORT';
    } elseif (stripos($error, 'tls') !== false || stripos($error, 'ssl') !== false) {
        $detalle = 'Error de seguridad TLS/SSL. Verifica la configuración SMTP_SECURE';
    }

    echo json_encode([
        'status' => 'error',
        'email' => $_POST['email'] ?? '',
        'mensaje' => $error,
        'detalle' => $detalle
    ]);
}
