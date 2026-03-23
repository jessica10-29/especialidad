<?php
// generar_documento.php - Generador de Documentos Profesionales de Alta Gama
require_once 'conexion.php';
verificar_sesion();

$doc_type = $_GET['tipo'] ?? 'estudio'; // estudio, recomendacion, respuesta
$user_id = $_SESSION['usuario_id'];
$rol_sesion = $_SESSION['rol'] ?? '';

// Permitir a profesores generar certificados para sus estudiantes (no para otros)
if ($rol_sesion === 'profesor' && isset($_GET['usuario_id'])) {
    $solicitado = (int) $_GET['usuario_id'];
    // Validar que el estudiante esté en alguna materia del profesor
    $prof_id = (int) $_SESSION['usuario_id'];
    $q_val = $conn->query("SELECT 1 FROM matriculas m JOIN materias mat ON m.materia_id = mat.id WHERE m.estudiante_id = $solicitado AND mat.profesor_id = $prof_id LIMIT 1");
    if ($q_val && $q_val->num_rows > 0) {
        $user_id = $solicitado;
    }
}
// Los estudiantes siempre usan su propio ID (ignora cualquier parámetro externo)
$user_id = ($rol_sesion === 'estudiante') ? (int)$_SESSION['usuario_id'] : (int)$user_id;

// Obtener datos del usuario
$q_user = $conn->query("SELECT * FROM usuarios WHERE id = $user_id");
$user = $q_user->fetch_assoc() ?: [];
$user_defaults = [
    'nombre' => 'Usuario',
    'identificacion' => 'N/A',
    'codigo_estudiantil' => 'N/A',
    'programa_academico' => 'Formación General',
    'semestre' => 'N/A',
    'rol' => 'estudiante',
];
$user = array_merge($user_defaults, $user);
$user_nombre = $user['nombre'];
$rol_usuario = strtolower($user['rol'] ?? 'estudiante');
$es_docente = $rol_usuario === 'profesor';

// Generación automática de Código Estudiantil si no existe o es N/A
if (empty($user['codigo_estudiantil']) || $user['codigo_estudiantil'] == 'N/A') {
    $current_year = date('Y');
    // Formato: UC-AÑO-ID (rellenado con ceros a la izquierda para que tenga 4 dígitos)
    $nuevo_codigo = "UC-" . $current_year . "-" . str_pad($user_id, 4, "0", STR_PAD_LEFT);

    // Actualizar en la base de datos para que sea permanente
    $conn->query("UPDATE usuarios SET codigo_estudiantil = '$nuevo_codigo' WHERE id = $user_id");

    // Refrescar la variable $user para que el documento muestre el nuevo código
    $user['codigo_estudiantil'] = $nuevo_codigo;
}

// Obtener datos del banco institucional (Trello integration)
$res_banco = $conn->query("SELECT valor FROM configuracion WHERE clave = 'inst_banco_nombre'");
$banco_nom = ($res_banco && $res_banco->num_rows > 0) ? $res_banco->fetch_assoc()['valor'] : 'Banco Institucional';
$res_cuenta = $conn->query("SELECT valor FROM configuracion WHERE clave = 'inst_banco_cuenta'");
$banco_cta = ($res_cuenta && $res_cuenta->num_rows > 0) ? $res_cuenta->fetch_assoc()['valor'] : '000-000000-00';

$meses = ['January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'];
$fecha_esp = date('d') . ' de ' . $meses[date('F')] . ' de ' . date('Y');

// Folio sincronizado con verificar.php (UC-ID)
$folio = "UC-" . $user_id;

// Promedio general del estudiante
$promedio_estudiante = 'N/D';
$q_prom = $conn->query("SELECT AVG(n.valor) AS prom FROM notas n JOIN matriculas m ON n.matricula_id = m.id WHERE m.estudiante_id = $user_id");
if ($q_prom && ($pr = $q_prom->fetch_assoc()) && $pr['prom'] !== null) {
    $promedio_estudiante = number_format((float)$pr['prom'], 2);
}

$verify_url = url_verificacion($folio);
// Proveedor principal (qrserver) y fallback a Google Charts si falla la carga
$qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=170x170&data=" . urlencode($verify_url);
$qr_fallback = "https://chart.googleapis.com/chart?chs=170x170&cht=qr&chl=" . urlencode($verify_url) . "&choe=UTF-8";

// ===== Generar y servir archivo QR local para impresión =====
$qr_dir = __DIR__ . '/uploads/qr';
if (!is_dir($qr_dir)) {
    @mkdir($qr_dir, 0755, true);
}
$qr_filename = $folio . '.png';
$qr_local_path = $qr_dir . '/' . $qr_filename;
$qr_public_path = '/uploads/qr/' . $qr_filename;
$qr_src = $qr_fallback; // fallback por defecto
$qr_data_uri = null;
$qr_binary = null;

// Intentar generar un QR fresco (evita cache con URL antigua)
$qr_sources = [$qr_api_url, $qr_fallback];
foreach ($qr_sources as $srcTry) {
    $qr_binary = descargar_con_timeout($srcTry, 5);
    if ($qr_binary !== null && strlen($qr_binary) > 0) {
        @file_put_contents($qr_local_path, $qr_binary); // actualiza archivo local
        break;
    }
}

// Si no se pudo descargar, usar archivo previo si existe
$qr_binary = ($qr_binary === null && file_exists($qr_local_path))
    ? @file_get_contents($qr_local_path)
    : $qr_binary;

if ($qr_binary === false) {
    $qr_binary = null;
}

// Si tenemos binario, usar data URI (ideal para impresión y escaneo)
if ($qr_binary !== null) {
    $qr_data_uri = 'data:image/png;base64,' . base64_encode($qr_binary);
    $qr_src = $qr_data_uri;
} elseif (file_exists($qr_local_path)) {
    $qr_src = $qr_public_path . '?v=' . filemtime($qr_local_path);
}

// Código de barras con datos esenciales del estudiante
$barcode_data = "UNICALI|ID:{$user_id}|NOMBRE:{$user['nombre']}|DOC:{$user['identificacion']}|COD:{$user['codigo_estudiantil']}|PROG:{$user['programa_academico']}";
$barcode_url = "https://bwipjs-api.metafloor.com/?bcid=code128&text=" . urlencode($barcode_data) . "&includetext&scale=2&background=ffffff";
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>CERTIF_<?php echo strtoupper($user_nombre); ?>_<?php echo $folio; ?></title>
    <link rel="icon" type="image/png" href="/favicon.png?v=3">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&family=Playfair+Display:ital,wght@0,700;1,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e293b;
            --secondary: #64748b;
            --accent: #b45309;
            /* Gold/Bronze accent */
            --bg-white: #ffffff;
        }

        @page {
            size: letter portrait;
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f0f2f5;
            font-family: 'Montserrat', sans-serif;
            color: var(--primary);
            width: 100%;
            height: 100%;
        }

        .certificate-container {
            width: 215.9mm;
            max-width: 215.9mm;
            min-height: 250mm;
            max-height: 270mm;
            height: auto;
            margin: 0 auto;
            position: relative;
            background: var(--bg-white);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            /* padding ajustado para que todo quepa en una hoja */
            padding: 1.1cm 1.4cm 1.0cm 1.4cm;
            overflow: hidden;
            page-break-inside: avoid;
            page-break-after: avoid;
        }

        /* 🏆 BORDE DE LUJO */
        .outer-border {
            position: absolute;
            top: 20px;
            bottom: 20px;
            left: 20px;
            right: 20px;
            border: 2px solid var(--accent);
            pointer-events: none;
            z-index: 100;
        }

        .inner-border {
            position: absolute;
            top: 30px;
            bottom: 30px;
            left: 30px;
            right: 30px;
            border: 6px double var(--primary);
            pointer-events: none;
            z-index: 100;
        }

        .corner-ornament {
            position: absolute;
            width: 50px;
            /* Reducido un poco */
            height: 50px;
            /* Reducido un poco */
            border: 2px solid var(--accent);
            z-index: 101;
        }

        .top-left {
            top: 15px;
            left: 15px;
            border-right: none;
            border-bottom: none;
        }

        .top-right {
            top: 15px;
            right: 15px;
            border-left: none;
            border-bottom: none;
        }

        .bottom-left {
            bottom: 15px;
            left: 15px;
            border-right: none;
            border-top: none;
        }

        .bottom-right {
            bottom: 15px;
            right: 15px;
            border-left: none;
            border-top: none;
        }

        /* 🌊 WATERMARK */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 140pt;
            /* Reducido un poco */
            color: rgba(0, 0, 0, 0.03);
            font-family: 'Playfair Display', serif;
            pointer-events: none;
            white-space: nowrap;
            z-index: 0;
        }

        .content {
            position: relative;
            z-index: 5;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            text-align: center;
            max-width: 145mm;
            width: 100%;
            margin: 0 auto;
            padding: 0.12cm 0.30cm 0.4cm;
        }

        .header {
            margin-bottom: 12px;
            /* Reducido de 30px */
        }

        .university-name {
            font-family: 'Playfair Display', serif;
            font-size: 28pt;
            /* Reducido de 32pt */
            font-weight: 700;
            letter-spacing: 2px;
            color: var(--primary);
            text-transform: uppercase;
        }

        .subtitle {
            font-size: 9.5pt;
            /* Reducido */
            letter-spacing: 4px;
            color: var(--accent);
            margin-top: 5px;
            font-weight: 600;
        }

        .doc-title {
            font-family: 'Playfair Display', serif;
            font-size: 20pt;
            color: var(--primary);
            margin: 10px 0;
            font-style: italic;
            position: relative;
            display: inline-block;
        }

        .doc-title::after {
            content: "";
            position: absolute;
            left: 10%;
            right: 10%;
            bottom: -5px;
            height: 1px;
            background: var(--accent);
        }

        .body-text {
            text-align: justify;
            font-size: 11.5pt;
            line-height: 1.4;
            padding: 0 0.32cm;
            margin-bottom: 6px;
            flex-grow: 1;
        }

        .body-text b {
            color: var(--primary);
            font-weight: 700;
        }

        .date-location {
            font-size: 10.8pt;
            margin-bottom: 14px;
            font-style: italic;
        }

        .signature-section {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            margin-bottom: 12px;
            gap: 60px;
            z-index: 10;
        }

        .signature-block {
            width: 180px;
            text-align: center;
        }

        .sig-line {
            border-top: 1px solid var(--primary);
            margin-bottom: 8px;
        }

        .sig-name {
            font-weight: 700;
            font-size: 10.5pt;
            /* Reducido */
            text-transform: uppercase;
            white-space: nowrap;
        }

        .sig-title {
            font-size: 9pt;
            color: var(--secondary);
            font-weight: 600;
        }

        /* 📜 SELLO Y QR - Ahora en flujo relativo para no solapar */
        .bottom-verification {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 6px;
            padding: 0 0.4cm;
            margin-top: 2px;
            margin-bottom: 2px;
            max-width: 140mm;
            width: 100%;
            margin-left: auto;
            margin-right: auto;
        }

        .qr-area {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            max-width: 140mm;
        }

        .barcode-area {
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 8pt;
            color: var(--primary);
            text-align: center;
        }

        .barcode-img {
            width: 210px;
            max-width: 100%;
            height: auto;
            border: 1px solid #d1d5db;
            padding: 6px;
            background: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .qr-image {
            width: 140px;
            height: 140px;
            border: 1.5px solid var(--accent);
            padding: 5px;
            background: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            image-rendering: crisp-edges;
        }

        .qr-link {
            font-size: 9pt;
            color: var(--accent);
            text-decoration: underline;
            word-break: break-all;
        }

        .qr-link:hover {
            color: #8a3f08;
        }

        .qr-info {
            font-size: 7.8pt;
            color: var(--primary);
            line-height: 1.35;
            max-width: 125mm;
            word-wrap: break-word;
            word-break: break-word;
            text-align: center;
        }

        .digital-seal {
            width: 100px;
            /* Reducido */
            height: 100px;
            /* Reducido */
            border: 4px double var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: var(--accent);
            font-weight: 700;
            font-size: 7pt;
            text-align: center;
            transform: rotate(-10deg);
            background: rgba(180, 83, 9, 0.03);
        }

        .seal-inner {
            border: 1px solid var(--accent);
            width: 85px;
            height: 85px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .footer {
            font-size: 8pt;
            color: var(--secondary);
            border-top: 1px solid #eee;
            padding-top: 6px;
            margin-top: 6px;
        }

        @media print {
            html, body {
                width: 215.9mm;
                height: auto;
                margin: 0 auto;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                font-size: 10.5pt;
            }

            body {
                background: white;
                margin: 0;
            }

            .certificate-container {
                margin: 0;
                box-shadow: none;
                width: 210mm;
                height: auto;
                min-height: 250mm;
                max-height: 270mm;
                overflow: hidden;
                page-break-inside: avoid;
                page-break-after: avoid;
            }

            .no-print {
                display: none;
            }

            .content,
            .bottom-verification {
                page-break-inside: avoid;
            }

            * {
                page-break-inside: avoid;
            }
        }

        /* UI CONTROLS (ocultos) */
        .no-print {
            display: none !important;
        }
    </style>
</head>

<body>
    <div class="certificate-container">
        <div class="outer-border"></div>
        <div class="inner-border"></div>
        <div class="corner-ornament top-left"></div>
        <div class="corner-ornament top-right"></div>
        <div class="corner-ornament bottom-left"></div>
        <div class="corner-ornament bottom-right"></div>

        <div class="watermark">UNICALI</div>

        <div class="content">
            <div class="header">
                <div class="university-name">Unicali Segura</div>
                <div class="subtitle">Excelencia Académica y Seguridad</div>
                <div style="font-size: 7pt; margin-top: 10px; color: var(--secondary); letter-spacing: 1px;">PERSONERÍA JURÍDICA No. 1234 - VIGILADO MINISTERIO DE EDUCACIÓN</div>
            </div>

            <h1 class="doc-title">
                <?php echo ($doc_type == 'estudio') ? 'Certificación Académica' : 'Carta de Recomendación'; ?>
            </h1>

            <div class="body-text">
                <?php if ($doc_type == 'estudio'): ?>
                    <p style="text-align:center; margin-bottom: 30px; letter-spacing: 2px; font-weight: 600;">HACE CONSTAR QUE:</p>
                    <?php if ($es_docente): ?>
                        <p>El(la) docente <b><?php echo strtoupper($user_nombre); ?></b>, identificado(a) con documento de identidad No. <b><?php echo $user['identificacion'] ?? 'N/A'; ?></b> y código institucional <b><?php echo $user['codigo_estudiantil'] ?? 'N/A'; ?></b>, se encuentra formalmente vinculado(a) a esta institución en calidad de profesor(a), adscrito(a) a las unidades académicas que le sean asignadas.</p>
                        <br>
                        <p>Durante su ejercicio profesional ha cumplido con los estándares académicos y administrativos definidos por la institución, manteniendo un desempeño satisfactorio en sus funciones docentes.</p>
                    <?php else: ?>
                        <p>El(la) estudiante <b><?php echo strtoupper($user_nombre); ?></b>, identificado(a) con documento de identidad No. <b><?php echo $user['identificacion'] ?? 'N/A'; ?></b> y código estudiantil <b><?php echo $user['codigo_estudiantil'] ?? 'N/A'; ?></b>, se encuentra formalmente vinculado(a) a esta institución en calidad de estudiante regular del programa académico de <b><?php echo $user['programa_academico'] ?? 'Formación General'; ?></b>, cursando el semestre <b><?php echo $user['semestre'] ?? 'N/A'; ?></b>.</p>
                        <br>
                        <p>Durante el periodo académico vigente, el(la) mencionado(a) ha cumplido satisfactoriamente con los requisitos académicos y administrativos exigidos por la normatividad institucional, manteniendo un registro académico activo y de excelente comportamiento.</p>
                    <?php endif; ?>
                    <br>
                    <p>La presente certificación se expide a solicitud del interesado(a), para los fines que estime convenientes.</p>
                <?php else: ?>
                    <p>A QUIEN PUEDA INTERESAR,</p><br>
                    <p>Por medio de la presente, me permito recomendar ampliamente a <b><?php echo strtoupper($user_nombre); ?></b>, quien durante su permanencia en <b>UNICALI SEGURA</b> ha demostrado ser una persona íntegra, con un alto sentido de responsabilidad y compromiso profesional.</p><br>
                    <p>Su capacidad analítica y su facilidad para el trabajo colaborativo le han permitido destacar en su área de formación. Estoy plenamente convencido(a) de que sus competencias y valores serán de gran aporte para cualquier organización donde decida desempeñarse.</p><br>
                    <p>Atentamente,</p>
                <?php endif; ?>
            </div>

            <div class="date-location">
                Expedido en la ciudad de Santiago de Cali, el <?php echo $fecha_esp; ?>.
            </div>

            <div class="signature-section">
                <div class="signature-block">
                    <div style="height: 60px; display: flex; align-items: center; justify-content: center;">
                        <!-- Placeholder para firma digital -->
                        <span style="font-family: 'Playfair Display', serif; font-style: italic; opacity: 0.1; font-size: 24pt;">Secretaria</span>
                    </div>
                    <div class="sig-line"></div>
                    <div class="sig-name">MARIA FERNANDA GOMEZ</div>
                    <div class="sig-title">Secretaria Académica</div>
                </div>

                <div class="signature-block">
                    <div style="height: 60px; display: flex; align-items: center; justify-content: center;">
                        <!-- Placeholder para firma digital -->
                        <span style="font-family: 'Playfair Display', serif; font-style: italic; opacity: 0.1; font-size: 24pt;">Rectoría</span>
                    </div>
                    <div class="sig-line"></div>
                    <div class="sig-name">LUIS ALBERTO RIVERA</div>
                    <div class="sig-title">Rector Institucional</div>
                </div>
            </div>

            <div class="bottom-verification">
                <div class="qr-area">
                    <img src="<?php echo $qr_src; ?>" alt="QR Verification" class="qr-image" onerror="this.src='<?php echo $qr_fallback; ?>'">
                    
                    <div class="qr-info">
                        <div style="font-weight: 800; letter-spacing: 1px;">FOLIO DE VERIFICACIÓN: <?php echo $folio; ?></div>
                        <div><?php echo $es_docente ? 'Docente' : 'Estudiante'; ?>: <strong><?php echo strtoupper($user_nombre); ?></strong></div>
                        <div>Rol: <?php echo $es_docente ? 'Profesor' : 'Estudiante'; ?> | Programa: <?php echo htmlspecialchars($user['programa_academico']); ?><?php if (!$es_docente): ?> | Semestre: <?php echo htmlspecialchars($user['semestre']); ?><?php endif; ?></div>
                        <div>ID: <?php echo htmlspecialchars($user['identificacion']); ?> | Código: <?php echo htmlspecialchars($user['codigo_estudiantil']); ?></div>
                        <?php if (!$es_docente): ?>
                            <div>Desempeño promedio: <strong><?php echo $promedio_estudiante; ?></strong> / 5.0</div>
                        <?php endif; ?>
                        <div style="font-size: 7.5pt; color: var(--secondary);">Escanea el c\u00f3digo para validar en l\u00ednea.</div>
                    </div>
                </div>

                <div class="digital-seal">
                    <div class="seal-inner">
                        <div style="margin-bottom: 2px;">CERTIFICADO</div>
                        <div style="font-size: 10pt; color: var(--primary);">ORIGINAL</div>
                        <div style="margin-top: 2px;">UNICALI</div>
                    </div>
                </div>
            </div>

            <div class="footer">
                Sede Administrativa: Calle 5 No. 12-34, Cali - Colombia | Pbx: (602) 123 4567<br>
                Soporte: admisiones@unicalisegura.edu.co | www.unicalisegura.edu.co
            </div>
        </div>
    </div>
</body>

</html>
