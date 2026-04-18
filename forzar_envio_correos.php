<?php
/**
 * forzar_envio_correos.php
 * 
 * Script para forzar el envío de correos pendientes
 * Útil cuando el procesador automático no funciona
 * 
 * Uso:
 * - Desde terminal/cron: php forzar_envio_correos.php
 * - Desde navegador: http://localhost/especialidad/forzar_envio_correos.php?token=DESARROLLO_LOCAL_2025
 */

set_time_limit(300); // 5 minutos
ini_set('max_execution_time', 300);

// Permitir acceso desde CLI o con token
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json; charset=UTF-8');
    
    $tokenSecreto = getenv('MAIL_QUEUE_TOKEN') ?: 'DESARROLLO_LOCAL_2025';
    $tokenEnviado = $_GET['token'] ?? $_POST['token'] ?? '';
    
    if ($tokenEnviado !== $tokenSecreto) {
        http_response_code(403);
        die(json_encode(['status' => 'error', 'mensaje' => 'Acceso denegado']));
    }
}

require_once 'conexion.php';
require_once 'config_mail.php';

// Log
$logFile = __DIR__ . '/logs/mail-force-send.log';
$log = function($msg) use ($logFile) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND | LOCK_EX);
    echo php_sapi_name() === 'cli' ? "[{$timestamp}] {$msg}\n" : '';
};

try {
    $log('=== INICIANDO ENVÍO FORZADO DE CORREOS ===');

    // Crear tabla si no existe
    $createTable = $conn->query("
        CREATE TABLE IF NOT EXISTS mail_queue (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            nombre VARCHAR(255),
            asunto VARCHAR(255) NOT NULL,
            contenido LONGTEXT NOT NULL,
            tipo ENUM('reset_password', 'cambio_contraseña', 'notificacion', 'otro') DEFAULT 'otro',
            datos_json JSON,
            enviado TINYINT DEFAULT 0,
            intentos INT DEFAULT 0,
            ultimo_intento DATETIME,
            error_mensaje TEXT,
            creado DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX(enviado, creado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if ($createTable === false) {
        $log("Error creando tabla: {$conn->error}");
    }

    // Obtener correos pendientes
    $query = $conn->prepare("
        SELECT id, email, nombre, asunto, contenido, tipo, datos_json
        FROM mail_queue
        WHERE enviado = 0 
        AND intentos < 3
        ORDER BY creado ASC
        LIMIT 20
    ");

    if (!$query) {
        throw new Exception("Error preparando consulta: {$conn->error}");
    }

    $query->execute();
    $resultado = $query->get_result();
    $totalPendientes = $resultado->num_rows;

    $log("Encontrados {$totalPendientes} correos pendientes");

    if ($totalPendientes === 0) {
        $log('✓ No hay correos pendientes');
        if (php_sapi_name() !== 'cli') {
            echo json_encode(['status' => 'ok', 'procesados' => 0, 'mensaje' => 'No hay correos pendientes']);
        }
        exit;
    }

    $procesados = 0;
    $errores_count = 0;

    while ($correo = $resultado->fetch_assoc()) {
        $idCola = $correo['id'];
        $email = $correo['email'];
        $nombre = $correo['nombre'];
        $asunto = $correo['asunto'];
        $contenido = $correo['contenido'];
        $tipo = $correo['tipo'];

        try {
            $log("Procesando correo #{$idCola} para {$email}");

            // Crear mailer
            $mail = obtener_mailer();
            $mail->Timeout = 30;
            $mail->SMTPKeepAlive = false;

            // Limpiar direcciones previas
            $mail->clearAllRecipients();
            $mail->clearReplyTos();
            $mail->clearAttachments();
            $mail->clearCustomHeaders();

            $mail->addAddress($email, $nombre);
            $mail->Subject = $asunto;
            $mail->isHTML(true);
            $mail->Body = $contenido;
            $mail->AltBody = strip_tags($contenido);

            // Intentar envío
            if (@$mail->send()) {
                // Marcar como enviado
                $upd = $conn->prepare("
                    UPDATE mail_queue
                    SET enviado = 1, ultimo_intento = NOW()
                    WHERE id = ?
                ");
                $upd->bind_param('i', $idCola);
                $upd->execute();
                $upd->close();

                $log("✓ Correo #{$idCola} ENVIADO a {$email}");
                $procesados++;
            } else {
                throw new Exception($mail->ErrorInfo);
            }
        } catch (Exception $e) {
            $errores_count++;
            $errorMsg = $e->getMessage();
            $log("✗ Error correo #{$idCola}: {$errorMsg}");

            // Actualizar intentos
            $upd = $conn->prepare("
                UPDATE mail_queue
                SET intentos = intentos + 1,
                    ultimo_intento = NOW(),
                    error_mensaje = ?
                WHERE id = ?
            ");
            $upd->bind_param('si', $errorMsg, $idCola);
            $upd->execute();
            $upd->close();

            // Si alcanzó 3 intentos, marcar como error definitivo
            $stmt = $conn->prepare("SELECT intentos FROM mail_queue WHERE id = ?");
            $stmt->bind_param('i', $idCola);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($res['intentos'] >= 3) {
                $log("⚠️ Correo #{$idCola} marcado como error definitivo (3 intentos)");
            }
        }
    }

    $log("=== COMPLETADO: {$procesados} enviados, {$errores_count} con error ===");

    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'status' => 'ok',
            'procesados' => $procesados,
            'errores' => $errores_count,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
} catch (Exception $e) {
    $log("ERROR FATAL: {$e->getMessage()}");
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['status' => 'error', 'mensaje' => $e->getMessage()]);
    }
}
