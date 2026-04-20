<?php
/**
 * funciones_mail.php
 * 
 * Funciones auxiliares para manejo de correos con cola
 */

/**
 * Agregar correo a la cola de envío
 * 
 * @param string $email - Email del destinatario
 * @param string $nombre - Nombre del destinatario
 * @param string $asunto - Asunto del correo
 * @param string $contenido - HTML del correo
 * @param string $tipo - Tipo de correo (reset_password, notificacion, etc)
 * @param array $datosJson - Datos adicionales en JSON
 * @return bool - True si se guardó en cola, False si error
 */
function agregar_correo_a_cola($email, $nombre, $asunto, $contenido, $tipo = 'otro', $datosJson = [])
{
    global $conn;

    // Validar email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("Email inválido en cola: {$email}");
        return false;
    }

    // Detectar entorno
    $isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) 
        || stripos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;

    // En localhost: guardar en log en lugar de cola
    if ($isLocal && getenv('SMTP_FORCE_PROD') !== '1') {
        $logPath = __DIR__ . '/logs/mail-local.log';
        $payload = "---- " . date('Y-m-d H:i:s') . " ----\n"
            . "TO: {$email}\n"
            . "NOMBRE: {$nombre}\n"
            . "SUBJECT: {$asunto}\n"
            . "TIPO: {$tipo}\n\n"
            . "CONTENIDO HTML:\n{$contenido}\n\n";
        
        @file_put_contents($logPath, $payload, FILE_APPEND | LOCK_EX);
        error_log("Correo capturado en localhost: {$email}");
        return true;
    }

    // En producción: guardar en cola real
    try {
        $datosJson = json_encode($datosJson);
        
        $stmt = $conn->prepare("
            INSERT INTO mail_queue (email, nombre, asunto, contenido, tipo, datos_json)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            error_log("Error preparando inserción en mail_queue: " . $conn->error);
            return false;
        }

        $stmt->bind_param('ssssss', $email, $nombre, $asunto, $contenido, $tipo, $datosJson);

        if (!$stmt->execute()) {
            error_log("Error insertando en mail_queue: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $stmt->close();
        error_log("Correo agregado a cola: {$email}");
        return true;
    } catch (Exception $e) {
        error_log("Excepción en agregar_correo_a_cola: " . $e->getMessage());
        return false;
    }
}

/**
 * Procesar la cola de correos inmediatamente (para testing)
 * @return array - Resultado del procesamiento
 */
function procesar_cola_correos_inmediatamente()
{
    $token = obtener_token_mantenimiento();
    if ($token === '' || (!es_peticion_local() && token_mantenimiento_inseguro($token))) {
        return ['status' => 'error', 'mensaje' => 'MAIL_QUEUE_TOKEN no esta configurado de forma segura.'];
    }

    $baseUrl = rtrim(construir_base_url(), '/');
    $resultado = @file_get_contents(
        $baseUrl . '/enviar_cola_correos.php?token=' . urlencode($token),
        false,
        stream_context_create([
            'http' => ['timeout' => 30]
        ])
    );

    if ($resultado === false) {
        return ['status' => 'error', 'mensaje' => 'No se pudo procesar la cola'];
    }

    return json_decode($resultado, true) ?: ['status' => 'error', 'mensaje' => 'Respuesta inválida'];
}

/**
 * Enviar correo de recuperación de contraseña en cola
 */
function enviar_correo_recuperacion($email, $nombre, $link)
{
    $nombre = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
    $email = filter_var($email, FILTER_VALIDATE_EMAIL);

    if (!$email) {
        return false;
    }

    $asunto = 'Recuperación de contraseña - UnivaliSegura';
    
    $contenido = "
        <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto;'>
            <table width='100%' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px;'>
                <tr>
                    <td style='text-align: center; color: white;'>
                        <h1 style='margin: 0;'>UnivaliSegura</h1>
                        <p style='margin: 5px 0 0; opacity: 0.9;'>Portal Educativo Seguro</p>
                    </td>
                </tr>
            </table>

            <div style='padding: 30px; background: white; border: 1px solid #e0e0e0;'>
                <h2 style='color: #667eea; margin-top: 0;'>Recuperar tu contraseña</h2>
                <p>Hola <strong>{$nombre}</strong>,</p>
                
                <p>Has solicitado restablecer tu contraseña en <strong>UnivaliSegura</strong>. Haz clic en el botón de abajo para continuar:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$link}' style='
                        display: inline-block;
                        padding: 14px 32px;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        text-decoration: none;
                        border-radius: 8px;
                        font-weight: bold;
                        font-size: 16px;
                    '>
                        Cambiar Contraseña
                    </a>
                </div>

                <div style='background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0; font-size: 0.9em; color: #666;'>
                        <strong>Nota importante:</strong><br>
                        • Este enlace vencerá en 1 hora<br>
                        • Si no solicitaste este cambio, ignora este correo<br>
                        • Nunca compartir este enlace con otros
                    </p>
                </div>

                <hr style='border: 0; border-top: 1px solid #e0e0e0; margin: 20px 0;'>
                
                <p style='font-size: 0.85em; color: #999;'>
                    Este correo fue enviado automáticamente. Respuestas a este correo no serán revisadas.<br>
                    <a href='" . htmlspecialchars(rtrim(construir_base_url(), '/'), ENT_QUOTES, 'UTF-8') . "' style='color: #667eea; text-decoration: none;'>Visita nuestro portal</a>
                </p>
            </div>
        </div>
    ";

    return agregar_correo_a_cola($email, $nombre, $asunto, $contenido, 'reset_password', ['link' => $link]);
}
