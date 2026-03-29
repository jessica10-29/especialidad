<?php
/**
 * validar_impresion.php - Utilidad para verificar que el sistema de impresión esté funcionando correctamente
 */
require_once 'conexion.php';
verificar_sesion();

$rol = $_SESSION['rol'] ?? '';

// Verificar permisos
if ($rol !== 'profesor' && $rol !== 'estudiante' && $rol !== 'admin') {
    die('Acceso denegado.');
}

$usuario_id = (int)$_SESSION['usuario_id'];
$validaciones = [];

// 1. Verificar conexión a BD
try {
    $q = $conn->query("SELECT 1 FROM usuarios LIMIT 1");
    $validaciones['bd_conexion'] = $q ? 'OK ✓' : 'FALLO ✗';
} catch (Exception $e) {
    $validaciones['bd_conexion'] = 'ERROR: ' . $e->getMessage();
}

// 2. Verificar carpeta de QR
$qr_dir = __DIR__ . '/uploads/qr';
$qr_dir_exists = is_dir($qr_dir);
if (!$qr_dir_exists) {
    @mkdir($qr_dir, 0755, true);
    $qr_dir_exists = is_dir($qr_dir);
}
$validaciones['carpeta_qr'] = $qr_dir_exists ? 'OK ✓' : 'FALLO - No se puede crear: ' . $qr_dir;

// 3. Verificar permisos de escritura en carpeta de QR
$test_file = $qr_dir . '/test_' . time() . '.txt';
$can_write = @file_put_contents($test_file, 'test');
if ($can_write) {
    @unlink($test_file);
    $validaciones['permisos_qr'] = 'OK ✓';
} else {
    $validaciones['permisos_qr'] = 'FALLO - No se puede escribir en: ' . $qr_dir;
}

// 4. Verificar carpeta de uploads general
$uploads_dir = __DIR__ . '/uploads';
$validaciones['carpeta_uploads'] = is_dir($uploads_dir) ? 'OK ✓' : 'FALLO ✗';

// 5. Verificar funciones necesarias
$funciones_requeridas = [
    'obtener_periodo_actual' => function_exists('obtener_periodo_actual'),
    'descargar_con_timeout' => function_exists('descargar_con_timeout'),
    'url_verificacion' => function_exists('url_verificacion'),
    'limpiar_dato' => function_exists('limpiar_dato'),
];

foreach ($funciones_requeridas as $func => $existe) {
    $validaciones["funcion_$func"] = $existe ? 'OK ✓' : 'FALLO ✗';
}

// 6. Verificar tablas de base de datos
$tablas_requeridas = ['usuarios', 'materias', 'notas', 'matriculas', 'periodos', 'configuracion'];
$tablas_ok = [];
foreach ($tablas_requeridas as $tabla) {
    $q = $conn->query("SHOW TABLES LIKE '$tabla'");
    $tablas_ok[$tabla] = ($q && $q->num_rows > 0) ? 'OK ✓' : 'FALLO ✗';
}
$validaciones['tablas_bd'] = $tablas_ok;

// 7. Verificar período actual
$periodo_actual = null;
try {
    $per = obtener_periodo_actual();
    $validaciones['periodo_actual'] = 'ID: ' . $per . ' ✓';
    $periodo_actual = $per;
} catch (Exception $e) {
    $validaciones['periodo_actual'] = 'ERROR: ' . $e->getMessage();
}

// 8. Verificar datos del usuario actual
$q_user = $conn->query("SELECT id, nombre, rol, codigo_estudiantil FROM usuarios WHERE id = $usuario_id LIMIT 1");
$user_data = ($q_user && $q_user->num_rows > 0) ? $q_user->fetch_assoc() : null;
$validaciones['usuario_actual'] = $user_data ? ('OK: ' . htmlspecialchars($user_data['nombre'])) : 'FALLO ✗';

// 9. Verificar archivos de impresión
$archivos_impresion = [
    'pdf.php' => __DIR__ . '/pdf.php',
    'reporte_notas_pdf.php' => __DIR__ . '/reporte_notas_pdf.php',
    'generar_documento.php' => __DIR__ . '/generar_documento.php',
];

foreach ($archivos_impresion as $nombre => $ruta) {
    $existe = file_exists($ruta);
    $validaciones["archivo_$nombre"] = $existe ? 'OK ✓' : 'FALLO - No existe: ' . $ruta;
}

// 10. Verificar disponibilidad de APIs de QR
$validaciones['api_qr'] = 'Verificando...';
$qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=test";
$qr_test = descargar_con_timeout($qr_api, 3);
if ($qr_test !== null && strlen($qr_test) > 0) {
    $validaciones['api_qr'] = 'OK ✓ (QRServer disponible)';
} else {
    $validaciones['api_qr'] = 'Fallback: Google Charts';
}

// Mostrar resultados
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validación del Sistema de Impresión</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            padding: 20px;
            color: #1e293b;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            color: #1e293b;
            margin-bottom: 30px;
            border-bottom: 2px solid #b45309;
            padding-bottom: 12px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section h2 {
            font-size: 16px;
            color: #b45309;
            text-transform: uppercase;
            margin-bottom: 12px;
            letter-spacing: 1px;
        }
        .check-item {
            padding: 10px 15px;
            margin-bottom: 8px;
            background: #f8fafc;
            border-left: 4px solid #cbd5e1;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .check-item.ok {
            border-left-color: #16a34a;
            background: #f0fdf4;
        }
        .check-item.error {
            border-left-color: #dc2626;
            background: #fef2f2;
        }
        .check-item.warning {
            border-left-color: #f59e0b;
            background: #fffbeb;
        }
        .check-label {
            font-weight: 600;
        }
        .check-value {
            color: #64748b;
            font-size: 12px;
        }
        .status-ok { color: #16a34a; font-weight: 700; }
        .status-error { color: #dc2626; font-weight: 700; }
        .status-warning { color: #f59e0b; font-weight: 700; }
        .button-group {
            margin-top: 30px;
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #1e293b;
            color: white;
        }
        .btn-primary:hover {
            background: #0f172a;
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #1e293b;
        }
        .btn-secondary:hover {
            background: #cbd5e1;
        }
        .summary {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #b45309;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        table td {
            padding: 8px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        table tr:last-child td {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Validación del Sistema de Impresión</h1>

        <div class="summary">
            <strong>Usuario:</strong> <?php echo htmlspecialchars($user_data['nombre'] ?? 'Desconocido'); ?>
            (<?php echo ucfirst($rol); ?>) <br>
            <strong>Fecha de validación:</strong> <?php echo date('d/m/Y H:i:s'); ?>
        </div>

        <!-- Conexión y Base de Datos -->
        <div class="section">
            <h2>📊 Base de Datos</h2>
            <div class="check-item <?php echo strpos($validaciones['bd_conexion'], 'OK') !== false ? 'ok' : 'error'; ?>">
                <span class="check-label">Conexión a BD</span>
                <span class="check-value <?php echo strpos($validaciones['bd_conexion'], 'OK') !== false ? 'status-ok' : 'status-error'; ?>">
                    <?php echo htmlspecialchars($validaciones['bd_conexion']); ?>
                </span>
            </div>
            <div class="check-item <?php echo strpos($validaciones['periodo_actual'], 'OK') === false && strpos($validaciones['periodo_actual'], 'ID') !== false ? 'ok' : 'error'; ?>">
                <span class="check-label">Período Actual</span>
                <span class="check-value <?php echo strpos($validaciones['periodo_actual'], 'ID') !== false ? 'status-ok' : 'status-error'; ?>">
                    <?php echo htmlspecialchars($validaciones['periodo_actual']); ?>
                </span>
            </div>

            <h3 style="margin-top: 15px; margin-bottom: 10px; font-size: 13px; color: #64748b;">Tablas Requeridas:</h3>
            <table>
                <?php foreach ($validaciones['tablas_bd'] as $tabla => $estado): ?>
                    <tr>
                        <td style="width: 50%;"><?php echo $tabla; ?></td>
                        <td class="<?php echo strpos($estado, 'OK') !== false ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $estado; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Sistema de Archivos -->
        <div class="section">
            <h2>📁 Sistema de Archivos</h2>
            <div class="check-item <?php echo strpos($validaciones['carpeta_uploads'], 'OK') !== false ? 'ok' : 'error'; ?>">
                <span class="check-label">Carpeta /uploads</span>
                <span class="check-value <?php echo strpos($validaciones['carpeta_uploads'], 'OK') !== false ? 'status-ok' : 'status-error'; ?>">
                    <?php echo htmlspecialchars($validaciones['carpeta_uploads']); ?>
                </span>
            </div>
            <div class="check-item <?php echo strpos($validaciones['carpeta_qr'], 'OK') !== false ? 'ok' : 'error'; ?>">
                <span class="check-label">Carpeta /uploads/qr</span>
                <span class="check-value <?php echo strpos($validaciones['carpeta_qr'], 'OK') !== false ? 'status-ok' : 'status-error'; ?>">
                    <?php echo htmlspecialchars($validaciones['carpeta_qr']); ?>
                </span>
            </div>
            <div class="check-item <?php echo strpos($validaciones['permisos_qr'], 'OK') !== false ? 'ok' : 'error'; ?>">
                <span class="check-label">Permisos de Escritura (QR)</span>
                <span class="check-value <?php echo strpos($validaciones['permisos_qr'], 'OK') !== false ? 'status-ok' : 'status-error'; ?>">
                    <?php echo htmlspecialchars($validaciones['permisos_qr']); ?>
                </span>
            </div>
        </div>

        <!-- Archivos de Impresión -->
        <div class="section">
            <h2>🖨️ Archivos de Impresión</h2>
            <?php foreach ($archivos_impresion as $nombre => $ruta): ?>
                <div class="check-item <?php echo strpos($validaciones["archivo_$nombre"], 'OK') !== false ? 'ok' : 'error'; ?>">
                    <span class="check-label"><?php echo $nombre; ?></span>
                    <span class="check-value <?php echo strpos($validaciones["archivo_$nombre"], 'OK') !== false ? 'status-ok' : 'status-error'; ?>">
                        <?php echo strpos($validaciones["archivo_$nombre"], 'OK') !== false ? 'Presente ✓' : 'Falta ✗'; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Funciones PHP -->
        <div class="section">
            <h2>⚙️ Funciones Requeridas</h2>
            <?php foreach ($funciones_requeridas as $func => $existe): ?>
                <div class="check-item <?php echo $existe ? 'ok' : 'error'; ?>">
                    <span class="check-label"><?php echo $func . '()'; ?></span>
                    <span class="check-value <?php echo $existe ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $existe ? 'Disponible ✓' : 'Falta ✗'; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- APIs Externas -->
        <div class="section">
            <h2>🌐 APIs Externas</h2>
            <div class="check-item <?php echo strpos($validaciones['api_qr'], 'OK') !== false ? 'ok' : 'warning'; ?>">
                <span class="check-label">Generador de Códigos QR</span>
                <span class="check-value <?php echo strpos($validaciones['api_qr'], 'OK') !== false ? 'status-ok' : 'status-warning'; ?>">
                    <?php echo htmlspecialchars($validaciones['api_qr']); ?>
                </span>
            </div>
        </div>

        <!-- Botones de Acción -->
        <div class="button-group">
            <a href="javascript:location.reload();" class="btn btn-primary">🔄 Actualizar Validación</a>
            <?php if ($rol === 'estudiante'): ?>
                <a href="pdf.php" class="btn btn-secondary">📄 Ir a Mi Certificado</a>
            <?php elseif ($rol === 'profesor'): ?>
                <a href="gestion_notas.php" class="btn btn-secondary">📊 Mis Materias</a>
            <?php endif; ?>
            <a href="javascript:history.back();" class="btn btn-secondary">← Atrás</a>
        </div>
    </div>
</body>
</html>
