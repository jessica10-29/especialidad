<?php
/**
 * test_smtp.php
 * 
 * Prueba DIRECTA de conexión SMTP
 * Intenta enviar un correo directamente sin cola
 */

header('Content-Type: text/html; charset=UTF-8');

require_once 'conexion.php';
require_once 'config_mail.php';

$isLocal = isset($_SERVER['HTTP_HOST']) 
    && (stripos($_SERVER['HTTP_HOST'], 'localhost') !== false 
        || stripos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test SMTP Directo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        .subtitle {
            color: #999;
            margin-bottom: 20px;
        }
        .section {
            margin: 25px 0;
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .section h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        .config-item {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.9rem;
        }
        .config-label {
            font-weight: bold;
            color: #667eea;
        }
        .config-value {
            color: #333;
            margin-left: 10px;
        }
        .masked {
            background: #ddd;
            padding: 2px 6px;
            border-radius: 3px;
            color: #999;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #333;
        }
        input, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: Arial, sans-serif;
            font-size: 0.9rem;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        button {
            padding: 12px 24px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        button:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 6px;
            display: none;
        }
        .result.show { display: block; }
        .result.success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }
        .result.error {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #7f1d1d;
        }
        .result.info {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            color: #1e3a8a;
        }
        .loader {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f4f6;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 3px;
            color: #d97706;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>🧪 Test SMTP Directo</h1>
            <p class="subtitle">Prueba de envío sin cola - para diagnosticar problemas</p>

            <?php if ($isLocal): ?>
            <div class="warning">
                <strong>⚠️ Estás en LOCALHOST</strong><br>
                En localhost los correos se guardan en <code>logs/mail-local.log</code> en lugar de enviarse por SMTP.
                <br><br>
                Para probar SMTP real, cambia a tu servidor público o define <code>SMTP_FORCE_PROD=1</code>
            </div>
            <?php endif; ?>

            <div class="section">
                <h2>📊 Configuración Actual</h2>

                <div class="config-item">
                    <span class="config-label">Entorno:</span>
                    <span class="config-value">
                        <?php echo $isLocal ? '📱 LOCALHOST' : '🌐 PRODUCCIÓN'; ?>
                    </span>
                </div>

                <div class="config-item">
                    <span class="config-label">SMTP_HOST:</span>
                    <span class="config-value">
                        <?php echo htmlspecialchars(getenv('SMTP_HOST') ?: 'smtp.gmail.com'); ?>
                    </span>
                </div>

                <div class="config-item">
                    <span class="config-label">SMTP_PORT:</span>
                    <span class="config-value">
                        <?php echo htmlspecialchars(getenv('SMTP_PORT') ?: '587'); ?>
                    </span>
                </div>

                <div class="config-item">
                    <span class="config-label">SMTP_USER:</span>
                    <span class="config-value">
                        <?php 
                        $user = getenv('SMTP_USER');
                        echo $user 
                            ? htmlspecialchars(substr($user, 0, 5)) . '<span class="masked">****</span>' 
                            : '(vacío)'; 
                        ?>
                    </span>
                </div>

                <div class="config-item">
                    <span class="config-label">SMTP_PASS:</span>
                    <span class="config-value">
                        <?php echo getenv('SMTP_PASS') ? '✓ Definido' : '❌ Vacío'; ?>
                    </span>
                </div>

                <div class="config-item">
                    <span class="config-label">FROM_EMAIL:</span>
                    <span class="config-value">
                        <?php echo htmlspecialchars(getenv('FROM_EMAIL') ?: 'no definido'); ?>
                    </span>
                </div>
            </div>

            <div class="section">
                <h2>📧 Enviar Correo de Prueba</h2>

                <form id="testForm">
                    <div class="form-group">
                        <label>Email de destino:</label>
                        <input type="email" name="email" value="test@gmail.com" required>
                    </div>

                    <div class="form-group">
                        <label>Asunto:</label>
                        <input type="text" name="asunto" value="Correo de Prueba SMTP" required>
                    </div>

                    <div class="form-group">
                        <label>Cuerpo del mensaje:</label>
                        <textarea name="contenido" required rows="6" placeholder="&lt;h1&gt;Hola&lt;/h1&gt;&lt;p&gt;Este es un correo de prueba&lt;/p&gt;"
                        >&lt;div style="font-family: Arial, sans-serif; color: #333;"&gt;
    &lt;h2 style="color: #667eea;"&gt;Correo de Prueba SMTP&lt;/h2&gt;
    &lt;p&gt;Si recibes este correo, el SMTP funciona correctamente.&lt;/p&gt;
    &lt;hr style="margin: 20px 0;"&gt;
    &lt;p style="font-size: 0.85em; color: #999;"&gt;Enviado desde UnivaliSegura&lt;/p&gt;
&lt;/div&gt;</textarea>
                    </div>

                    <button type="submit" onclick="enviarPrueba(event)">
                        🚀 Enviar Correo de Prueba
                    </button>
                </form>

                <div id="resultado" class="result"></div>
            </div>

            <div class="section">
                <h2>❓ Si el test falla...</h2>
                <ol style="margin-left: 20px; color: #333; line-height: 1.8;">
                    <li>
                        <strong>Error de autenticación:</strong><br>
                        Regenera tu contraseña de Gmail:
                        <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color: #667eea;">
                            myaccount.google.com/apppasswords
                        </a>
                    </li>
                    <li style="margin-top: 10px;">
                        <strong>No hay conexión SMTP:</strong><br>
                        Verifica que el puerto 587 esté abierto en tu firewall
                    </li>
                    <li style="margin-top: 10px;">
                        <strong>Estás en localhost:</strong><br>
                        Los correos se guardan en <code>logs/mail-local.log</code> en lugar de enviarse
                    </li>
                </ol>
            </div>
        </div>
    </div>

    <script>
        async function enviarPrueba(e) {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const originalText = btn.innerHTML;
            const resultado = document.getElementById('resultado');

            btn.disabled = true;
            btn.innerHTML = '<span class="loader"></span>Enviando...';
            resultado.classList.remove('show', 'success', 'error', 'info');

            try {
                const formData = new FormData(document.getElementById('testForm'));
                const response = await fetch('enviar_test_smtp.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                resultado.classList.add('show');
                if (data.status === 'ok') {
                    resultado.classList.add('success');
                    resultado.innerHTML = `
                        <strong>✅ Éxito!</strong><br>
                        El correo se envió correctamente a <code>${data.email}</code><br>
                        Revisa tu bandeja de entrada (o spam)
                    `;
                } else {
                    resultado.classList.add('error');
                    resultado.innerHTML = `
                        <strong>❌ Error:</strong><br>
                        ${data.mensaje}
                    `;
                    if (data.detalle) {
                        resultado.innerHTML += `<br><br><small>${data.detalle}</small>`;
                    }
                }
            } catch (error) {
                resultado.classList.add('show', 'error');
                resultado.innerHTML = `
                    <strong>❌ Error de conexión:</strong><br>
                    ${error.message}
                `;
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    </script>
</body>
</html>
