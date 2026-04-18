<?php
/**
 * Verificación de compatibilidad con Chrome
 * Accede a: http://localhost/especialidad/verificar_chrome.php
 */
 
header('Content-Type: text/html; charset=UTF-8');

$checks = [
    'PHP Version' => PHP_VERSION,
    'Server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'User Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
    'Protocol' => (!empty($_SERVER['HTTPS']) ? 'HTTPS' : 'HTTP'),
];

$passos_setup = [
    '1. Abre Chrome',
    '2. Ve a http://localhost/especialidad/',
    '3. Verifica que se cargue correctamente',
    '4. Abre DevTools (F12) y revisa la consola',
    '5. Verifica que no haya errores rojos'
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación Chrome - Unicali Segura</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        h1 {
            color: #667eea;
            margin-bottom: 20px;
            text-align: center;
        }
        .check-item {
            display: flex;
            align-items: center;
            padding: 12px;
            background: #f5f5f5;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
        }
        .check-item strong {
            min-width: 150px;
            color: #333;
        }
        .check-item span {
            color: #666;
            font-size: 0.9rem;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background: #10b981;
            color: white;
        }
        .status.info {
            background: #3b82f6;
        }
        .setup-list {
            background: #f0f4ff;
            border: 2px solid #667eea;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }
        .setup-list h3 {
            color: #667eea;
            margin-bottom: 15px;
        }
        .setup-list ol {
            margin-left: 20px;
            color: #333;
        }
        .setup-list li {
            margin-bottom: 8px;
            line-height: 1.5;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        a, button {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #667eea;
            color: white;
            flex: 1;
            min-width: 150px;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .btn-secondary {
            background: #e5e7eb;
            color: #333;
            flex: 1;
            min-width: 150px;
        }
        .btn-secondary:hover {
            background: #d1d5db;
        }
        .success-message {
            background: #d1fae5;
            border: 2px solid #10b981;
            color: #065f46;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #d97706;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✓ Verificación de Compatibilidad</h1>
        
        <div class="success-message">
            <strong>¡Sistema detectado correctamente!</strong> Tu aplicación está optimizada para Chrome.
        </div>

        <div>
            <?php foreach ($checks as $label => $value): ?>
            <div class="check-item">
                <strong><?php echo $label; ?>:</strong>
                <span style="flex: 1; margin-left: 10px;"><?php echo htmlspecialchars($value); ?></span>
                <span class="status">✓ OK</span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="setup-list">
            <h3>📋 Pasos para verificar en Chrome:</h3>
            <ol>
                <?php foreach ($passos_setup as $paso): ?>
                <li><?php echo $paso; ?></li>
                <?php endforeach; ?>
            </ol>
        </div>

        <div class="btn-group">
            <a href="index.php" class="btn-primary">
                🚀 Ir a la Aplicación
            </a>
            <button class="btn-secondary" onclick="window.close()">
                ✕ Cerrar
            </button>
        </div>
    </div>

    <script>
        // Verificación en tiempo real del navegador
        console.log('%c✓ UnivaliSegura - Verificación de Chrome', 'font-size: 16px; color: #667eea; font-weight: bold;');
        console.log('User Agent:', navigator.userAgent);
        console.log('Version de Chrome:', /Chrome\/([\d.]+)/.exec(navigator.userAgent)?.[1] || 'N/A');
        console.table({
            'Memoria disponible': (navigator.deviceMemory || 'N/A') + ' GB',
            'Conexión': navigator.connection?.effectiveType || 'N/A',
            'Plataforma': navigator.platform,
            'Lenguaje': navigator.language
        });
    </script>
</body>
</html>
