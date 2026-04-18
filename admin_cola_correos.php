<?php
/**
 * admin_cola_correos.php
 * 
 * Panel de administración para la cola de correos
 * Accede a: http://localhost/especialidad/admin_cola_correos.php?token=DESARROLLO_LOCAL_2025
 */

header('Content-Type: text/html; charset=UTF-8');

// Verificación de token
$tokenSecreto = getenv('MAIL_QUEUE_TOKEN') ?: 'DESARROLLO_LOCAL_2025';
$tokenEnviado = $_GET['token'] ?? $_POST['token'] ?? '';

if ($tokenEnviado !== $tokenSecreto) {
    http_response_code(403);
    die('<h1>Acceso Denegado</h1>');
}

require_once 'conexion.php';

// Crear tabla si no existe
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

$action = $_GET['action'] ?? 'list';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'retry') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->query("UPDATE mail_queue SET enviado = 0, intentos = 0 WHERE id = $id");
        header('Location: ?token=' . urlencode($tokenEnviado));
        exit;
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->query("DELETE FROM mail_queue WHERE id = $id");
        header('Location: ?token=' . urlencode($tokenEnviado));
        exit;
    } elseif ($action === 'process_now') {
        // Procesar cola inmediatamente
        $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $url = "{$protocolo}://{$host}/especialidad/enviar_cola_correos.php?token=" . urlencode($tokenSecreto);
        
        $context = stream_context_create(['http' => ['timeout' => 30]]);
        $result = @file_get_contents($url, false, $context);
        $resultado = json_decode($result, true);
        $mensaje = $resultado['status'] === 'ok' 
            ? "Procesados {$resultado['procesados']} correos"
            : "Error: {$resultado['mensaje']}";
    }
}

// Obtener estadísticas
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(enviado) as enviados,
        SUM(CASE WHEN enviado = 0 THEN 1 ELSE 0 END) as pendientes
    FROM mail_queue
")->fetch_assoc();

// Obtener correos pendientes
$pendientes = $conn->query("
    SELECT * FROM mail_queue 
    WHERE enviado = 0 
    ORDER BY creado DESC 
    LIMIT 20
");

// Obtener correos con error
$errores = $conn->query("
    SELECT * FROM mail_queue 
    WHERE enviado = 0 AND intentos >= 3
    ORDER BY ultimo_intento DESC 
    LIMIT 20
");

// Obtener correos enviados recientemente
$enviados = $conn->query("
    SELECT * FROM mail_queue 
    WHERE enviado = 1 
    ORDER BY ultimo_intento DESC 
    LIMIT 10
");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Cola de Correos - UnivaliSegura</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        h1 { font-size: 2rem; margin-bottom: 10px; }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .stat-card {
            background: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h2 {
            font-size: 1.5rem;
            color: #667eea;
            margin-bottom: 20px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .btn-success {
            background: #10b981;
            color: white;
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
            border-bottom: 2px solid #e5e7eb;
            font-weight: 600;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        tr:hover { background: #f9fafb; }
        .status-enviado {
            background: #d1fae5;
            color: #065f46;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .status-pendiente {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .status-error {
            background: #fee2e2;
            color: #7f1d1d;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .email-cell {
            font-family: monospace;
            font-size: 0.9rem;
        }
        .error-text {
            color: #dc2626;
            font-size: 0.85rem;
            max-width: 400px;
            word-break: break-word;
        }
        .mensaje {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .mensaje.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        .empty {
            text-align: center;
            color: #999;
            padding: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📧 Panel de Cola de Correos</h1>
            <p>Administración del sistema de envío de correos en cola</p>
            
            <div class="stats">
                <div class="stat-card">
                    <span class="stat-number"><?php echo $stats['total'] ?? 0; ?></span>
                    <span class="stat-label">Correos Totales</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number" style="color: #10b981;"><?php echo $stats['enviados'] ?? 0; ?></span>
                    <span class="stat-label">Enviados</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number" style="color: #fbbf24;"><?php echo $stats['pendientes'] ?? 0; ?></span>
                    <span class="stat-label">Pendientes</span>
                </div>
            </div>
        </header>

        <?php if (!empty($mensaje)): ?>
        <div class="mensaje success">
            ✓ <?php echo htmlspecialchars($mensaje); ?>
        </div>
        <?php endif; ?>

        <!-- SECCIÓN: ACCIONES RÁPIDAS -->
        <div class="section">
            <h2>⚡ Acciones Rápidas</h2>
            <form method="POST" style="display: flex; gap: 10px;">
                <input type="hidden" name="action" value="process_now">
                <button type="submit" class="btn btn-success">Procesar Cola Ahora</button>
                <a href="?token=<?php echo urlencode($tokenEnviado); ?>" class="btn btn-primary">Actualizar</a>
            </form>
        </div>

        <!-- SECCIÓN: CORREOS PENDIENTES -->
        <?php if ($pendientes->num_rows > 0): ?>
        <div class="section">
            <h2>⏳ Correos Pendientes (<?php echo $pendientes->num_rows; ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Asunto</th>
                        <th>Tipo</th>
                        <th>Intentos</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $pendientes->fetch_assoc()): ?>
                    <tr>
                        <td class="email-cell"><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars(substr($row['asunto'], 0, 50)); ?></td>
                        <td><span class="status-pendiente"><?php echo $row['tipo']; ?></span></td>
                        <td><?php echo $row['intentos']; ?>/3</td>
                        <td><?php echo substr($row['creado'], 0, 16); ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="action" value="retry">
                                <button type="submit" class="btn btn-primary" onclick="return confirm('¿Reintentar?')">Reintentar</button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('¿Eliminar?')">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- SECCIÓN: CORREOS CON ERROR -->
        <?php if ($errores && $errores->num_rows > 0): ?>
        <div class="section">
            <h2>❌ Correos con Error (<?php echo $errores->num_rows; ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Asunto</th>
                        <th>Error</th>
                        <th>Último Intento</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $errores->fetch_assoc()): ?>
                    <tr>
                        <td class="email-cell"><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars(substr($row['asunto'], 0, 40)); ?></td>
                        <td><div class="error-text"><?php echo htmlspecialchars($row['error_mensaje'] ?? 'N/A'); ?></div></td>
                        <td><?php echo $row['ultimo_intento'] ? substr($row['ultimo_intento'], 0, 16) : 'N/A'; ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('¿Eliminar?')">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- SECCIÓN: CORREOS ENVIADOS RECIENTEMENTE -->
        <?php if ($enviados && $enviados->num_rows > 0): ?>
        <div class="section">
            <h2>✅ Correos Enviados Recientemente (<?php echo $enviados->num_rows; ?>)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Asunto</th>
                        <th>Tipo</th>
                        <th>Enviado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $enviados->fetch_assoc()): ?>
                    <tr>
                        <td class="email-cell"><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars(substr($row['asunto'], 0, 50)); ?></td>
                        <td><span class="status-enviado"><?php echo $row['tipo']; ?></span></td>
                        <td><?php echo substr($row['ultimo_intento'], 0, 16); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (!$pendientes->num_rows && !$errores->num_rows && !$enviados->num_rows): ?>
        <div class="section empty">
            <h2>📭 No hay correos en la cola</h2>
            <p>La cola de correos está vacía. ¡Excelente!</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
