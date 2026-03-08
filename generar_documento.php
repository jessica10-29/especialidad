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

// Generar URL de Verificación
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_path = $dir === '/' ? '' : $dir;
$verify_url = "{$protocol}://{$host}{$base_path}/verificar.php?folio=" . rawurlencode($folio);
// Proveedor principal (qrserver) y fallback a Google Charts si falla la carga
$qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=170x170&data=" . urlencode($verify_url);
$qr_fallback = "https://chart.googleapis.com/chart?chs=170x170&cht=qr&chl=" . urlencode($verify_url) . "&choe=UTF-8";

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
            size: letter;
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
            height: 279.4mm;
            margin: 0 auto;
            position: relative;
            background: var(--bg-white);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            padding: 1.8cm 2cm 1.6cm 2cm;
            overflow: hidden;
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
            max-width: 170mm;
            width: 100%;
            margin: 0 auto;
            padding: 0 0.4cm;
        }

        .header {
            margin-bottom: 20px;
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
            font-size: 24pt;
            /* Reducido de 28pt */
            color: var(--primary);
            margin: 15px 0;
            /* Reducido */
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
            font-size: 13pt;
            line-height: 1.65;
            padding: 0 0.35cm;
            margin-bottom: 12px;
            flex-grow: 1;
        }

        .body-text b {
            color: var(--primary);
            font-weight: 700;
        }

        .date-location {
            font-size: 11pt;
            margin-bottom: 30px;
            /* Reducido significativamente */
            font-style: italic;
        }

        .signature-section {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            margin-bottom: 30px;
            /* Reducido */
            gap: 100px;
            /* Reducido */
            z-index: 10;
        }

        .signature-block {
            width: 220px;
            /* Reducido */
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
            gap: 12px;
            padding: 0 0.3cm;
            margin-top: 4px;
            margin-bottom: 6px;
        }

        .qr-area {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            max-width: 520px;
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
            width: 170px;
            height: 170px;
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
            font-size: 8.4pt;
            color: var(--primary);
            line-height: 1.45;
            max-width: 500px;
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
            padding-top: 15px;
            margin-top: 20px;
        }

        @media print {
            body {
                background: white;
            }

            .certificate-container {
                margin: 0;
                box-shadow: none;
            }

            .no-print {
                display: none;
            }
        }

        /* UI CONTROLS */
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-print {
            background: var(--primary);
            color: white;
        }

        .btn-close {
            background: #f1f5f9;
            color: var(--secondary);
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
    </style>
</head>

<body>
    <div class="no-print">
        <button class="btn btn-print" onclick="window.print()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" />
                <path d="M6 14h12v8H6z" />
            </svg>
            Imprimir Certificado
        </button>
        <button class="btn btn-close" onclick="window.close()">Cerrar</button>
    </div>

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
                    <img src="<?php echo $qr_api_url; ?>" alt="QR Verification" class="qr-image" onerror="this.src='<?php echo $qr_fallback; ?>'">
                    <a class="qr-link" href="<?php echo $verify_url; ?>" target="_blank" rel="noopener">Abrir verificacion en el navegador</a>
                    <div class="qr-info">
                        <div style="font-weight: 800; letter-spacing: 1px;">FOLIO DE VERIFICACIÓN: <?php echo $folio; ?></div>
                        <div><?php echo $es_docente ? 'Docente' : 'Estudiante'; ?>: <strong><?php echo strtoupper($user_nombre); ?></strong></div>
                        <div>Rol: <?php echo $es_docente ? 'Profesor' : 'Estudiante'; ?> | Programa: <?php echo htmlspecialchars($user['programa_academico']); ?><?php if (!$es_docente): ?> | Semestre: <?php echo htmlspecialchars($user['semestre']); ?><?php endif; ?></div>
                        <div>ID: <?php echo htmlspecialchars($user['identificacion']); ?> | Código: <?php echo htmlspecialchars($user['codigo_estudiantil']); ?></div>
                        <?php if (!$es_docente): ?>
                            <div>Desempeño promedio: <strong><?php echo $promedio_estudiante; ?></strong> / 5.0</div>
                        <?php endif; ?>
                        <div style="font-size: 7.5pt; color: var(--secondary);">Escanea o visita: <?php echo htmlspecialchars($verify_url); ?></div>
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
