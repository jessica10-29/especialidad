<?php
/**
 * enviar_cola_correos.php
 * 
 * Sistema de cola de correos que:
 * - Se ejecuta en background sin timeout
 * - Procesa correos pendientes
 * - Funciona en localhost y servidor público
 * 
 * Uso:
 * - Al enviar un correo, se guarda en DB
 * - Este script procesa la cola periódicamente
 * - Llamar con: curl http://localhost/especialidad/enviar_cola_correos.php?token=SECRET
 * - O desde cron: * * * * * curl -s http://tudominio.com/especialidad/enviar_cola_correos.php?token=SECRET
 */

// Aumentar timeout para procesar múltiples correos
set_time_limit(120);
ini_set('max_execution_time', 120);

// Prevenir que se llame sin token
$tokenSecreto = getenv('MAIL_QUEUE_TOKEN') ?: 'DESARROLLO_LOCAL_2025';
$tokenEnviado = $_GET['token'] ?? $_POST['token'] ?? '';

if ($tokenEnviado !== $tokenSecreto && php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Acceso denegado');
}

require_once 'conexion.php';

// Log de procesos
$logFile = __DIR__ . '/logs/mail-queue.log';
$log = function ($msg) use ($logFile) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND | LOCK_EX);
};

try {
    $log('=== Iniciando procesamiento de cola de correos ===');

    // Crear tabla de cola si no existe
    $checkTable = $conn->query("
        SHOW TABLES LIKE 'mail_queue'
    ");

    if ($checkTable->num_rows === 0) {
        $log('Creando tabla mail_queue...');
        $conn->query("
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
        $log('✓ Tabla mail_queue creada');
    }

    // Obtener correos pendientes
    $query = $conn->prepare("
        SELECT id, email, nombre, asunto, contenido, tipo, datos_json
        FROM mail_queue
        WHERE enviado = 0 
        AND intentos < 3
        ORDER BY creado ASC
        LIMIT 10
    ");

    if (!$query) {
        throw new Exception('Error preparando consulta: ' . $conn->error);
    }

    $query->execute();
    $resultado = $query->get_result();
    $totalPendientes = $resultado->num_rows;

    $log("Encontrados {$totalPendientes} correos pendientes");

    if ($totalPendientes === 0) {
        $log('No hay correos pendientes. Finalizando.');
        if (php_sapi_name() !== 'cli') {
            echo json_encode(['status' => 'ok', 'procesados' => 0]);
        }
        exit;
    }

    require_once 'config_mail.php';

    $procesados = 0;
    $errores = 0;

    while ($correo = $resultado->fetch_assoc()) {
        $idCola = $correo['id'];
        $email = $correo['email'];
        $nombre = $correo['nombre'];
        $asunto = $correo['asunto'];
        $contenido = $correo['contenido'];
        $tipo = $correo['tipo'];

        try {
            $log("Procesando correo #{$idCola} para {$email}");

            // Crear mailer con timeout ampliado para cola
            $mail = obtener_mailer();
            $mail->Timeout = 30; // Timeout de 30 segundos para cola
            $mail->SMTPKeepAlive = true;

            $mail->addAddress($email, $nombre);
            $mail->Subject = $asunto;
            $mail->isHTML(true);
            $mail->Body = $contenido;

            // Intentar envío
            if ($mail->send()) {
                // Marcar como enviado
                $upd = $conn->prepare("
                    UPDATE mail_queue
                    SET enviado = 1, ultimo_intento = NOW()
                    WHERE id = ?
                ");
                $upd->bind_param('i', $idCola);
                $upd->execute();
                $upd->close();

                $log("✓ Correo #{$idCola} enviado exitosamente a {$email}");
                $procesados++;
            } else {
                throw new Exception($mail->ErrorInfo);
            }
        } catch (Exception $e) {
            $errores++;
            $errorMsg = $e->getMessage();

            $log("✗ Error enviando correo #{$idCola}: {$errorMsg}");

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
        }
    }

    $log("=== Procesamiento completado: {$procesados} exitosos, {$errores} con errores ===");

    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'procesados' => $procesados,
            'errores' => $errores,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
} catch (Exception $e) {
    $log("ERROR FATAL: {$e->getMessage()}");
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'mensaje' => $e->getMessage()]);
    }
}
