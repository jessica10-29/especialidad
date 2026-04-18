<?php
/**
 * PRUEBA_COLA_CORREOS.php
 * 
 * Script de prueba para verificar que el sistema de cola funciona correctamente
 * Acceder a: http://localhost/especialidad/PRUEBA_COLA_CORREOS.php
 */

header('Content-Type: text/html; charset=UTF-8');

require_once 'conexion.php';
require_once 'funciones_mail.php';

$testEmail = $_POST['email'] ?? 'test@localhost.com';
$mostrarFormulario = true;
$resultados = [];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba Sistema Cola Correos</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        .description {
            color: #666;
            margin-bottom: 30px;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: Arial, sans-serif;
            font-size: 0.95rem;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        button {
            flex: 1;
            padding: 14px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
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
        .btn-secondary {
            background: #e5e7eb;
            color: #333;
        }
        .btn-secondary:hover {
            background: #d1d5db;
        }
        .resultado {
            margin-top: 30px;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .resultado.success {
            background: #d1fae5;
            border-left-color: #10b981;
            color: #065f46;
        }
        .resultado.error {
            background: #fee2e2;
            border-left-color: #ef4444;
            color: #7f1d1d;
        }
        .resultado.info {
            background: #dbeafe;
            border-left-color: #3b82f6;
            color: #1e3a8a;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }
        .stat {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
        }
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        .link-box {
            background: #f9fafb;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
            border: 1px solid #e5e7eb;
        }
        .link-box a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .link-box a:hover {
            text-decoration: underline;
        }
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Prueba Sistema Cola de Correos</h1>
        <p class="description">
            Verifica que el sistema funcione correctamente enviando un correo de prueba a la cola.
        </p>

        <?php
        // Procesar envío de prueba
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
            $email = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
            $nombre = htmlspecialchars($_POST['test_nombre'] ?? 'Usuario de Prueba');
            $asunto = htmlspecialchars($_POST['test_asunto'] ?? 'Correo de Prueba');
            $contenido = htmlspecialchars($_POST['test_contenido'] ?? '<h1>Prueba</h1>');

            if ($email) {
                // Convertir contenido a HTML seguro
                $contenidoHTML = $contenido;

                if (agregar_correo_a_cola($email, $nombre, $asunto, $contenidoHTML, 'otro')) {
                    echo '<div class="resultado success">';
                    echo '<strong>✓ Éxito!</strong><br>';
                    echo "Correo agregado a la cola para <code>{$email}</code><br><br>";
                    echo '<strong>Próximos pasos:</strong><br>';
                    echo '1. Si estás en <strong>localhost</strong>, el correo se guardará en <code>logs/mail-local.log</code><br>';
                    echo '2. Si estás en <strong>servidor público</strong>, se intentará enviar vía SMTP<br>';
                    echo '3. <a href="admin_cola_correos.php?token=DESARROLLO_LOCAL_2025">Revisa el panel de cola</a><br>';
                    echo '</div>';
                } else {
                    echo '<div class="resultado error">';
                    echo '<strong>✗ Error</strong><br>No se pudo agregar el correo a la cola. Revisa los logs.';
                    echo '</div>';
                }
            } else {
                echo '<div class="resultado error">';
                echo '<strong>✗ Email inválido</strong>';
                echo '</div>';
            }

            $mostrarFormulario = true;
        }

        // Obtener estadísticas
        $stats = $conn->query("
            SELECT 
                COUNT(*) as total,
                SUM(enviado) as enviados,
                SUM(CASE WHEN enviado = 0 THEN 1 ELSE 0 END) as pendientes
            FROM mail_queue
        ") ? $conn->query("SELECT COUNT(*) as total, SUM(enviado) as enviados, SUM(CASE WHEN enviado = 0 THEN 1 ELSE 0 END) as pendientes FROM mail_queue")->fetch_assoc() : null;

        if ($stats) {
            echo '<div class="resultado info">';
            echo '<strong>📊 Estado de la Cola</strong>';
            echo '<div class="stats">';
            echo '<div class="stat">';
            echo '<div class="stat-number">' . ($stats['total'] ?? 0) . '</div>';
            echo '<div class="stat-label">Correos Totales</div>';
            echo '</div>';
            echo '<div class="stat">';
            echo '<div class="stat-number" style="color: #10b981;">' . ($stats['enviados'] ?? 0) . '</div>';
            echo '<div class="stat-label">Enviados</div>';
            echo '</div>';
            echo '<div class="stat">';
            echo '<div class="stat-number" style="color: #fbbf24;">' . ($stats['pendientes'] ?? 0) . '</div>';
            echo '<div class="stat-label">Pendientes</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        ?>

        <?php if ($mostrarFormulario): ?>
        <form method="POST">
            <div class="form-group">
                <label for="email">Email de destino:</label>
                <input 
                    type="email" 
                    id="email" 
                    name="test_email" 
                    value="<?php echo htmlspecialchars($testEmail); ?>" 
                    required
                    placeholder="ejemplo@dominio.com"
                >
            </div>

            <div class="form-group">
                <label for="nombre">Nombre del destinatario:</label>
                <input 
                    type="text" 
                    id="nombre" 
                    name="test_nombre" 
                    value="Juan Pérez"
                    placeholder="Juan Pérez"
                >
            </div>

            <div class="form-group">
                <label for="asunto">Asunto del correo:</label>
                <input 
                    type="text" 
                    id="asunto" 
                    name="test_asunto" 
                    value="Correo de Prueba - UnivaliSegura"
                    placeholder="Asunto"
                >
            </div>

            <div class="form-group">
                <label for="contenido">Contenido HTML:</label>
                <textarea 
                    id="contenido" 
                    name="test_contenido" 
                    placeholder="&lt;h1&gt;Hola&lt;/h1&gt;&lt;p&gt;Este es un correo de prueba&lt;/p&gt;"
                >&lt;div style="font-family: Arial, sans-serif; color: #333;"&gt;
    &lt;h2 style="color: #667eea;"&gt;Correo de Prueba&lt;/h2&gt;
    &lt;p&gt;Este es un correo de prueba del sistema de cola.&lt;/p&gt;
    &lt;p&gt;Si recibes este correo, el sistema funciona correctamente.&lt;/p&gt;
    &lt;hr style="margin: 20px 0;"&gt;
    &lt;p style="font-size: 0.85em; color: #999;"&gt;
        Este correo fue enviado desde UnivaliSegura.
    &lt;/p&gt;
&lt;/div&gt;</textarea>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn-primary">🚀 Enviar Correo de Prueba</button>
                <a href="admin_cola_correos.php?token=DESARROLLO_LOCAL_2025" style="text-decoration: none;">
                    <button type="button" class="btn-secondary">📊 Ver Panel de Cola</button>
                </a>
            </div>
        </form>

        <div class="link-box">
            <strong>📚 Documentación:</strong><br>
            <a href="SISTEMA_COLA_CORREOS.md">Ver guía completa del sistema de cola</a><br>
            <br>
            <strong>⚙️ Herramientas:</strong><br>
            <a href="admin_cola_correos.php?token=DESARROLLO_LOCAL_2025">Panel administrativo de la cola</a><br>
            <a href="enviar_cola_correos.php?token=DESARROLLO_LOCAL_2025">Procesar cola manualmente</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
