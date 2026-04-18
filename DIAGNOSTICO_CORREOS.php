<?php
/**
 * DIAGNOSTICO_CORREOS.php
 * 
 * Script para diagnosticar por qué los correos no se envían
 * Acceder a: http://localhost/especialidad/DIAGNOSTICO_CORREOS.php?token=DESARROLLO_LOCAL_2025
 */

header('Content-Type: text/html; charset=UTF-8');

// Verificación de token
$tokenSecreto = getenv('MAIL_QUEUE_TOKEN') ?: 'DESARROLLO_LOCAL_2025';
$tokenEnviado = $_GET['token'] ?? $_POST['token'] ?? '';

if ($tokenEnviado !== $tokenSecreto) {
    http_response_code(403);
    die('<h1>🔒 Acceso Denegado</h1>');
}

require_once 'conexion.php';

$diagnosticos = [];
$errores = [];
$advertencias = [];

// ========== TEST 1: Tabla mail_queue existe ==========
$tableExists = $conn->query("SHOW TABLES LIKE 'mail_queue'");
if ($tableExists->num_rows > 0) {
    $diagnosticos[] = ['✅', 'Tabla mail_queue existe', 'success'];
    
    // Contar correos
    $stats = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(enviado) as enviados,
            SUM(CASE WHEN enviado = 0 THEN 1 ELSE 0 END) as pendientes
        FROM mail_queue
    ")->fetch_assoc();
    
    $diagnosticos[] = ['ℹ️', "Cola: {$stats['total']} totales, {$stats['enviados']} enviados, {$stats['pendientes']} pendientes", 'info'];
    
    // Mostrar últimos correos
    $ultimos = $conn->query("
        SELECT id, email, asunto, enviado, intentos, error_mensaje, creado, ultimo_intento
        FROM mail_queue
        ORDER BY creado DESC
        LIMIT 5
    ");
    
    if ($ultimos->num_rows > 0) {
        $diagnosticos[] = ['📧', "Últimos {$ultimos->num_rows} correos en la cola:", 'info'];
    }
} else {
    $errores[] = ['❌', 'Tabla mail_queue NO existe - Se debe crear', 'danger'];
}

// ========== TEST 2: Configuración SMTP ==========
$smtpConfigPath = __DIR__ . '/secure/mail.php';
if (!file_exists($smtpConfigPath)) {
    $errores[] = ['❌', 'Archivo secure/mail.php no existe', 'danger'];
} else {
    $mailConfig = require_once $smtpConfigPath;
    
    // Detectar entorno
    $isLocal = isset($_SERVER['HTTP_HOST']) 
        && (stripos($_SERVER['HTTP_HOST'], 'localhost') !== false 
            || stripos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);
    
    if ($isLocal) {
        $diagnosticos[] = ['ℹ️', 'Entorno LOCAL detectado - Los correos se guardarán en logs/mail-local.log', 'info'];
    } else {
        $diagnosticos[] = ['ℹ️', 'Entorno PRODUCCIÓN detectado - Se intentará enviar por SMTP', 'info'];
        
        // Verificar credenciales SMTP
        $smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $smtpPort = getenv('SMTP_PORT') ?: 587;
        $smtpUser = getenv('SMTP_USER') ?: '';
        $smtpPass = getenv('SMTP_PASS') ?: '';
        
        if (empty($smtpUser) || empty($smtpPass)) {
            $errores[] = ['❌', 'Credenciales SMTP vacías - Define SMTP_USER y SMTP_PASS', 'danger'];
        } else {
            $diagnosticos[] = ['✅', "SMTP_HOST: {$smtpHost}:{$smtpPort}", 'success'];
            $diagnosticos[] = ['✅', "SMTP_USER: " . substr($smtpUser, 0, 5) . '***', 'success'];
            
            // Probar conexión SMTP
            $diagnosticos[] = ['⏳', 'Intentando conectar a SMTP...', 'warning'];
            
            $socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 5);
            if ($socket) {
                fclose($socket);
                $diagnosticos[] = ['✅', "Conexión SMTP exitosa ({$smtpHost}:{$smtpPort})", 'success'];
            } else {
                $errores[] = ['❌', "No se puede conectar a {$smtpHost}:{$smtpPort} - Error: {$errstr}", 'danger'];
            }
        }
    }
}

// ========== TEST 3: Verificar PHPMailer ==========
if (file_exists(__DIR__ . '/PHPMailer/PHPMailer.php')) {
    $diagnosticos[] = ['✅', 'PHPMailer library encontrada', 'success'];
} else {
    $errores[] = ['❌', 'PHPMailer library NO encontrada', 'danger'];
}

// ========== TEST 4: Verificar funciones_mail.php ==========
if (file_exists(__DIR__ . '/funciones_mail.php')) {
    $diagnosticos[] = ['✅', 'funciones_mail.php existe', 'success'];
} else {
    $errores[] = ['❌', 'funciones_mail.php NO existe', 'danger'];
}

// ========== TEST 5: Verificar config_mail.php ==========
if (file_exists(__DIR__ . '/config_mail.php')) {
    $diagnosticos[] = ['✅', 'config_mail.php existe', 'success'];
} else {
    $errores[] = ['❌', 'config_mail.php NO existe', 'danger'];
}

// ========== TEST 6: Verificar Logs ==========
$logPath = __DIR__ . '/logs/mail-queue.log';
if (file_exists($logPath)) {
    $tamanio = filesize($logPath);
    $diagnosticos[] = ['✅', "Log mail-queue.log existe ({$tamanio} bytes)", 'success'];
    
    // Mostrar últimas líneas
    $contenido = file_get_contents($logPath);
    $lineas = array_slice(explode("\n", $contenido), -10);
} else {
    $advertencias[] = ['⚠️', 'Log mail-queue.log no existe aún', 'warning'];
}

// ========== TEST 7: Verificar script de procesamiento ==========
if (file_exists(__DIR__ . '/enviar_cola_correos.php')) {
    $diagnosticos[] = ['✅', 'enviar_cola_correos.php existe', 'success'];
} else {
    $errores[] = ['❌', 'enviar_cola_correos.php NO existe', 'danger'];
}

// ========== TEST 8: Intentar procesar cola ahora ==========
if (isset($_POST['process_now'])) {
    $url = "http://{$_SERVER['HTTP_HOST']}/especialidad/enviar_cola_correos.php?token=" . urlencode($tokenSecreto);
    $context = stream_context_create(['http' => ['timeout' => 30]]);
    $resultado = @file_get_contents($url, false, $context);
    
    if ($resultado) {
        $data = json_decode($resultado, true);
        if ($data['status'] === 'ok') {
            $diagnosticos[] = ['✅', "Cola procesada: {$data['procesados']} enviados, {$data['errores']} errores", 'success'];
        } else {
            $errores[] = ['❌', "Error procesando cola: {$data['mensaje']}", 'danger'];
        }
    } else {
        $errores[] = ['❌', 'No se pudo conectar a enviar_cola_correos.php', 'danger'];
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico Sistema de Correos</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 2rem;
        }
        .subtitle {
            color: #666;
            font-size: 0.95rem;
        }
        .section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.3rem;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .test-item {
            display: flex;
            align-items: flex-start;
            padding: 15px;
            border-left: 4px solid #ddd;
            margin-bottom: 12px;
            border-radius: 4px;
            background: #f9fafb;
        }
        .test-item.success {
            background: #d1fae5;
            border-left-color: #10b981;
        }
        .test-item.danger {
            background: #fee2e2;
            border-left-color: #ef4444;
        }
        .test-item.warning {
            background: #fef3c7;
            border-left-color: #f59e0b;
        }
        .test-item.info {
            background: #dbeafe;
            border-left-color: #3b82f6;
        }
        .test-icon {
            font-size: 1.3rem;
            margin-right: 15px;
            min-width: 30px;
        }
        .test-content {
            flex: 1;
        }
        .test-title {
            font-weight: 600;
            margin-bottom: 4px;
            color: #1f2937;
        }
        .test-detail {
            font-size: 0.9rem;
            color: #666;
            font-family: monospace;
        }
        .logs {
            background: #1f2937;
            color: #d1d5db;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            max-height: 300px;
            overflow-y: auto;
            line-height: 1.5;
        }
        .log-line {
            margin-bottom: 5px;
            word-break: break-all;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 6px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .btn-secondary {
            background: #e5e7eb;
            color: #333;
            margin-left: 10px;
        }
        .btn-secondary:hover {
            background: #d1d5db;
        }
        .btn-group {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        a:hover {
            text-decoration: underline;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th {
            background: #f3f4f6;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e5e7eb;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        tr:hover { background: #f9fafb; }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-enviado {
            background: #d1fae5;
            color: #065f46;
        }
        .status-pendiente {
            background: #fef3c7;
            color: #92400e;
        }
        .status-error {
            background: #fee2e2;
            color: #7f1d1d;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🔧 Diagnóstico del Sistema de Correos</h1>
            <p class="subtitle">Verificación completa de configuración y funcionamiento</p>
        </header>

        <?php if (!empty($errores)): ?>
        <div class="section">
            <h2>❌ Errores Críticos</h2>
            <?php foreach ($errores as $error): ?>
            <div class="test-item <?php echo $error[2]; ?>">
                <div class="test-icon"><?php echo $error[0]; ?></div>
                <div class="test-content">
                    <div class="test-title"><?php echo $error[1]; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="section">
            <h2>📋 Estado del Sistema</h2>
            <?php foreach ($diagnosticos as $diag): ?>
            <div class="test-item <?php echo $diag[2]; ?>">
                <div class="test-icon"><?php echo $diag[0]; ?></div>
                <div class="test-content">
                    <div class="test-title"><?php echo $diag[1]; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($advertencias)): ?>
        <div class="section">
            <h2>⚠️ Advertencias</h2>
            <?php foreach ($advertencias as $adv): ?>
            <div class="test-item <?php echo $adv[2]; ?>">
                <div class="test-icon"><?php echo $adv[0]; ?></div>
                <div class="test-content">
                    <div class="test-title"><?php echo $adv[1]; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (isset($ultimos) && $ultimos->num_rows > 0): ?>
        <div class="section">
            <h2>📧 Correos en la Cola</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>Asunto</th>
                        <th>Estado</th>
                        <th>Intentos</th>
                        <th>Creado</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $ultimos->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $row['id']; ?></td>
                        <td><code><?php echo htmlspecialchars($row['email']); ?></code></td>
                        <td><?php echo htmlspecialchars(substr($row['asunto'], 0, 40)); ?></td>
                        <td>
                            <?php if ($row['enviado']): ?>
                                <span class="status-badge status-enviado">✓ Enviado</span>
                            <?php else: ?>
                                <span class="status-badge status-pendiente">⏳ Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $row['intentos']; ?>/3</td>
                        <td><?php echo substr($row['creado'], 0, 16); ?></td>
                        <td>
                            <?php if ($row['error_mensaje']): ?>
                                <span class="status-badge status-error" title="<?php echo htmlspecialchars($row['error_mensaje']); ?>">❌ Error</span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (isset($lineas) && !empty(array_filter($lineas))): ?>
        <div class="section">
            <h2>📝 Últimas Líneas del Log</h2>
            <div class="logs">
                <?php foreach (array_filter($lineas) as $linea): ?>
                    <div class="log-line"><?php echo htmlspecialchars($linea); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="section">
            <h2>⚡ Acciones Rápidas</h2>
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($tokenEnviado); ?>">
                <button type="submit" name="process_now" class="btn btn-primary">
                    🚀 Procesar Cola Ahora
                </button>
                <a href="admin_cola_correos.php?token=<?php echo urlencode($tokenEnviado); ?>" class="btn btn-secondary">
                    📊 Ver Panel de Cola
                </a>
            </form>
        </div>

        <div class="section">
            <h2>📞 Soluciones Según el Tipo de Problema</h2>
            
            <h3 style="margin-top: 20px; color: #667eea; font-size: 1.1rem;">✅ Si TODO aparece correcto pero los correos NO llegan:</h3>
            <ul style="margin-left: 20px; color: #666; line-height: 1.8;">
                <li><strong>Gmail rechaza la contraseña:</strong>
                    <ol>
                        <li>Abre <a href="https://myaccount.google.com/apppasswords" target="_blank">Google App Passwords</a></li>
                        <li>Genera una nueva contraseña de aplicación</li>
                        <li>Copia en <code>SMTP_PASS</code> en <code>secure/mail.php</code></li>
                        <li>Procesa la cola nuevamente</li>
                    </ol>
                </li>
                <li><strong>Verificar carpeta de SPAM:</strong> Gmail puede marcar como spam las primeras entregas</li>
                <li><strong>Revisar credenciales:</strong> Asegúrate que SMTP_USER y SMTP_PASS sean correctos</li>
            </ul>

            <h3 style="margin-top: 20px; color: #667eea; font-size: 1.1rem;">🔌 Si no hay conexión SMTP:</h3>
            <ul style="margin-left: 20px; color: #666; line-height: 1.8;">
                <li>Abre el puerto 587 (o 465 para SSL) en tu firewall</li>
                <li>Verifica que tu ISP no lo bloquee</li>
                <li>Usa un servidor SMTP alternativo (SendGrid, Mailgun, etc.)</li>
            </ul>

            <h3 style="margin-top: 20px; color: #667eea; font-size: 1.1rem;">🏠 En LOCALHOST:</h3>
            <ul style="margin-left: 20px; color: #666; line-height: 1.8;">
                <li>Los correos se guardan en <code>logs/mail-local.log</code> en lugar de enviarse</li>
                <li>Revisa ese archivo para ver el contenido</li>
                <li>En producción se enviarán por SMTP automáticamente</li>
            </ul>
        </div>
    </div>
</body>
</html>
