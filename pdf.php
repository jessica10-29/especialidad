<?php
// pdf.php - Certificado Académico Oficial de Alta Gama
require_once 'conexion.php';
verificar_sesion();

// Configuración de Errores (Normal)
ini_set('display_errors', 0);
error_reporting(E_ALL);

$id = $_SESSION['usuario_id'];
$fecha_reporte = date('d/m/Y');

// Obtener datos completos del estudiante
$sql_user = "SELECT * FROM usuarios WHERE id = $id";
$res_user = $conn->query($sql_user);
$user_data = ($res_user && $res_user->num_rows > 0) ? $res_user->fetch_assoc() : null;

$nombre = $user_data['nombre'] ?? obtener_nombre_usuario();
$identificacion = $user_data['identificacion'] ?? 'No registrada';
$programa = $user_data['programa_academico'] ?? 'No registrado';
$semestre = $user_data['semestre'] ?? 'N/A';
$codigo_est = $user_data['codigo_estudiantil'] ?? str_pad($id, 8, "0", STR_PAD_LEFT);

// Filtro por materia si viene en la URL
$where_materia = "";
$materia_nombre_display = "CERTIFICADO DE CALIFICACIONES";
if (isset($_GET['materia'])) {
    $m_input = limpiar_dato($_GET['materia']);
    $where_materia = " AND m.nombre = '$m_input'";
    $materia_nombre_display = "REPORT ACADÉMICO: " . htmlspecialchars($_GET['materia']);
}

// Pesos del sistema de 5 cortes
$pesos_sql = "SUM(valor * CASE 
    WHEN corte='Corte 1' THEN 0.2 
    WHEN corte='Corte 2' THEN 0.2 
    WHEN corte='Corte 3' THEN 0.2 
    WHEN corte='Examen Final' THEN 0.3 
    WHEN corte='Seguimiento' THEN 0.1 
    ELSE 0 END)";

// Consulta SQL de Notas con GROUP BY y filtro
$sql = "SELECT m.nombre, m.codigo, ($pesos_sql) as promedio
        FROM materias m
        JOIN matriculas mat ON m.id = mat.materia_id
        LEFT JOIN notas n ON mat.id = n.matricula_id
        WHERE mat.estudiante_id = $id $where_materia
        GROUP BY m.id, m.nombre, m.codigo";

$res = $conn->query($sql);

if (!$res) {
    die("Error en la consulta: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado_Oficial_<?php echo $id; ?></title>
    <link rel="icon" type="image/png" href="favicon.png?v=3">
    <link rel="shortcut icon" href="favicon.ico?v=3">
    <link rel="apple-touch-icon" href="favicon.png?v=3">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=EB+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e293b;
            --secondary: #b45309;
            /* Gold/Amber */
            --accent: #1e3a8a;
            /* Deep Navy */
            --bg-report: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-gold: #d4af37;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f1f5f9;
            color: var(--text-main);
            padding: 40px 20px;
            font-family: 'EB Garamond', serif;
        }

        /* --- UI Controls --- */
        .controls {
            max-width: 900px;
            margin: 0 auto 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            border: none;
        }

        .btn-print {
            background: var(--primary);
            color: white;
        }

        .btn-back {
            color: var(--text-muted);
            background: white;
            border: 1px solid #e2e8f0;
        }

        /* --- Certificate Paper --- */
        .page {
            width: 210mm;
            min-height: 297mm;
            max-width: 900px;
            margin: 0 auto;
            background: var(--bg-report);
            padding: 60px 80px;
            position: relative;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 15px solid transparent;
            border-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><path d="M0,0 L100,0 L100,100 L0,100 Z" fill="none" stroke="%23d4af37" stroke-width="2"/></svg>') 15 stretch;
        }

        /* Watermark */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            font-weight: 900;
            color: rgba(0, 0, 0, 0.02);
            pointer-events: none;
            z-index: 0;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .header {
            text-align: center;
            margin-bottom: 50px;
            border-bottom: 2px solid var(--secondary);
            padding-bottom: 30px;
            position: relative;
            z-index: 1;
        }

        .inst-title {
            font-family: 'Cinzel', serif;
            font-size: 38px;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: 4px;
            margin-bottom: 5px;
        }

        .inst-subtitle {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            color: var(--secondary);
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .doc-title {
            font-size: 24px;
            font-weight: 700;
            margin: 40px 0 20px;
            color: var(--primary);
            text-decoration: underline;
            text-underline-offset: 8px;
        }

        /* Information Area */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 40px;
            font-size: 18px;
            border: 1px solid #f1f5f9;
            padding: 25px;
            border-radius: 4px;
            background: #fff;
            position: relative;
            z-index: 1;
        }

        .info-item b {
            color: var(--secondary);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 12px;
            text-transform: uppercase;
            display: block;
            margin-bottom: 4px;
        }

        /* Table Area */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 50px;
            position: relative;
            z-index: 1;
        }

        th {
            background: #f8fafc;
            color: var(--primary);
            padding: 15px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1.5px solid #e2e8f0;
        }

        td {
            padding: 15px;
            border: 1px solid #e2e8f0;
            font-size: 16px;
        }

        .grade-val {
            font-weight: 700;
            font-size: 18px;
            color: var(--secondary);
        }

        /* Grading System Info */
        .grading-info {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 20px;
            font-style: italic;
            border-left: 3px solid var(--secondary);
            padding-left: 15px;
        }

        /* Signatures */
        .footer-area {
            margin-top: 80px;
            display: flex;
            justify-content: space-around;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .sig-box {
            width: 220px;
        }

        .sig-line {
            border-top: 1.5px solid var(--primary);
            margin-bottom: 10px;
        }

        .sig-name {
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
        }

        .sig-pos {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* QR Section */
        .qr-section {
            position: absolute;
            bottom: 60px;
            left: 80px;
            display: flex;
            align-items: center;
            gap: 15px;
            opacity: 0.8;
        }

        #bg-seal {
            position: absolute;
            bottom: -50px;
            right: -50px;
            width: 300px;
            height: 300px;
            opacity: 0.03;
            pointer-events: none;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .controls {
                display: none;
            }

            .page {
                box-shadow: none;
                border: 15px solid #d4af37 !important;
                margin: 0;
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <div class="controls">
        <a href="dashboard_estudiante.php" class="btn btn-back">← Regresar</a>
        <button onclick="window.print()" class="btn btn-print">Imprimir Certificado Profesional</button>
    </div>

    <div class="pdf-viewport" style="width: 100%; overflow-x: auto; padding-bottom: 40px;">
        <div class="page">
            <div class="watermark">UNICALI SEGURA</div>

            <div class="header">
                <h1 class="inst-title">UNICALI SEGURA</h1>
                <p class="inst-subtitle">INSTITUCIÓN DE EDUCACIÓN SUPERIOR</p>
            </div>

            <div style="text-align: center;">
                <p style="font-size: 14px; text-transform: uppercase; letter-spacing: 2px;">La Secretaría de Registro y Control Académico</p>
                <h2 class="doc-title"><?php echo $materia_nombre_display; ?></h2>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <b>Nombre del Estudiante</b>
                    <?php echo strtoupper(htmlspecialchars($nombre)); ?>
                </div>
                <div class="info-item">
                    <b>Identificación / Cédula</b>
                    <?php echo htmlspecialchars($identificacion); ?>
                </div>
                <div class="info-item">
                    <b>Programa Académico</b>
                    <?php echo strtoupper(htmlspecialchars($programa)); ?>
                </div>
                <div class="info-item">
                    <b>Semestre / Periodo</b>
                    <?php echo htmlspecialchars($semestre); ?> / AÑO 2024
                </div>
                <div class="info-item">
                    <b>Código Estudiantil</b>
                    <?php echo htmlspecialchars($codigo_est); ?>
                </div>
                <div class="info-item">
                    <b>Fecha de Generación</b>
                    <?php echo $fecha_reporte; ?>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Asignatura</th>
                        <th style="width: 150px; text-align: center;">Calificación Final</th>
                        <th style="width: 150px; text-align: center;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($res && $res->num_rows > 0): while ($row = $res->fetch_assoc()):
                            $prom = $row['promedio'] ? number_format((float)$row['promedio'], 1) : '0.0';
                            $status = ($prom >= 3.0) ? 'APROBADO' : 'REPROBADO';
                    ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700;"><?php echo htmlspecialchars($row['nombre']); ?></div>
                                    <div style="font-size: 12px; color: #64748b; font-family: 'Plus Jakarta Sans'; font-weight: 500;"><?php echo htmlspecialchars($row['codigo']); ?> • CRÉDITOS: 3</div>
                                </td>
                                <td align="center">
                                    <span class="grade-val"><?php echo $prom; ?></span>
                                </td>
                                <td align="center">
                                    <b style="color: <?php echo ($prom >= 3.0) ? '#166534' : '#991b1b'; ?>; font-size: 12px; font-family: 'Plus Jakarta Sans';"><?php echo $status; ?></b>
                                </td>
                            </tr>
                        <?php endwhile;
                    else: ?>
                        <tr>
                            <td colspan="3" align="center" style="padding: 60px; font-style: italic;">No se registran actividades académicas en el periodo actual.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="grading-info">
                <b>Nota:</b> El sistema de calificación se basa en una escala de 0.0 a 5.0. La nota aprobatoria mínima es de 3.0.
                Los promedios se calculan mediante ponderación de 5 cortes académicos (20%, 20%, 20%, 30%, 10%).
            </div>

            <div class="footer-area">
                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div class="sig-name">Luis Alberto Rivera</div>
                    <div class="sig-pos">Rector de la Institución</div>
                </div>
                <div class="sig-box" style="margin-top: 20px;">
                    <p style="font-size: 40px; font-family: 'Cinzel'; color: var(--secondary); opacity: 0.3;">SELLO</p>
                </div>
                <div class="sig-box">
                    <div class="sig-line"></div>
                    <div class="sig-name">Maria Fernanda Gomez</div>
                    <div class="sig-pos">Secretaria Académica</div>
                </div>
            </div>

            <!-- Espacio Flexible para empujar el QR al final si el contenido es corto -->
            <div style="flex-grow: 1; min-height: 50px;"></div>

            <!-- Verification QR - Refactorizado para máxima compatibilidad -->
            <div class="qr-section-container" style="display: flex; justify-content: flex-start; align-items: center; gap: 25px; border: 2px solid #f1f5f9; padding: 20px; border-radius: 16px; background: #fafafa; margin-top: 50px; position: relative; z-index: 10;">
                <?php
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $folio_id = str_pad($id, 8, "0", STR_PAD_LEFT);
                $verify_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/verificar.php?folio=UC-" . $folio_id;
                // Usando QRServer API (más moderna y rápida)
                $qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($verify_url);
                ?>
                <div style="background: white; padding: 10px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); min-width: 140px; min-height: 140px; display: flex; align-items: center; justify-content: center; border: 1px solid #eee;">
                    <img src="<?php echo $qr_api; ?>" width="120" height="120" alt="QR de Verificación"
                        style="display: block;"
                        crossorigin="anonymous"
                        onload="this.style.opacity='1'"
                        onerror="this.parentElement.innerHTML='<div style=\'font-size:10px;color:red;text-align:center\'>Error al cargar QR<br>Verifique internet</div>'">
                </div>
                <div style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 11px; line-height: 1.5; color: var(--primary);">
                    <div style="background: var(--secondary); color: white; display: inline-block; padding: 2px 8px; border-radius: 4px; font-weight: 800; font-size: 9px; margin-bottom: 8px; letter-spacing: 1px;">SISTEMA DE VERIFICACIÓN OFICIAL</div><br>
                    <b style="font-size: 14px; display: block; margin-bottom: 2px;">FOLIO No: UC-<?php echo $folio_id; ?></b>
                    <span style="color: var(--text-muted); font-style: italic;">Este documento cuenta con firma digital y código de validación único.<br>
                        Para verificar la autenticidad, escanee el código QR con cualquier dispositivo móvil.<br>
                        <b>Unicali Segura - Registro y Control Académico.</b></span>
                </div>
            </div>

            <!-- Decorative Seal SVG -->
            <svg id="bg-seal" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <circle cx="50" cy="50" r="45" fill="none" stroke="currentColor" stroke-width="2" />
                <path d="M50 15 L60 35 L80 35 L65 50 L75 70 L50 60 L25 70 L35 50 L20 35 L40 35 Z" fill="currentColor" />
            </svg>
        </div>
    </div>

</body>

</html>