<?php
// reporte_notas_pdf.php - Reporte Profesional de Calificaciones para Docentes
require_once 'conexion.php';
verificar_sesion();
verificar_rol('profesor');

if (!isset($_GET['materia'])) {
    die("ID de materia no proporcionado.");
}

$materia_id = (int)$_GET['materia'];
$profesor_id = $_SESSION['usuario_id'];

// Obtener datos de la materia
$stmt = $conn->prepare("SELECT * FROM materias WHERE id = ? AND profesor_id = ?");
$stmt->bind_param("ii", $materia_id, $profesor_id);
$stmt->execute();
$materia = $stmt->get_result()->fetch_assoc();

if (!$materia) {
    die("No tienes permiso para ver este reporte o la materia no existe.");
}

// Obtener periodo actual de forma segura (Self-Healing)
$p_actual_id = obtener_periodo_actual();

// Obtener alumnos y notas filtrados por periodo actual
$sql = "SELECT u.nombre, u.email, u.codigo_estudiantil, m.id as matricula_id
        FROM usuarios u
        JOIN matriculas m ON u.id = m.estudiante_id
        WHERE m.materia_id = $materia_id AND m.periodo_id = $p_actual_id
        ORDER BY u.nombre ASC";
$alumnos = $conn->query($sql);

$fecha_reporte = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte_<?php echo str_replace(' ', '_', $materia['nombre']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e293b;
            --accent: #b45309;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--primary);
            margin: 0;
            padding: 40px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid var(--accent);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .univ-name {
            font-family: 'Cinzel', serif;
            font-size: 28px;
            letter-spacing: 3px;
            margin: 0;
        }

        .report-title {
            text-transform: uppercase;
            font-size: 14px;
            letter-spacing: 2px;
            color: var(--accent);
            margin-top: 5px;
        }

        .info-box {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 50px;
        }

        th {
            background: #1e293b;
            color: white;
            padding: 12px;
            font-size: 11px;
            text-transform: uppercase;
        }

        td {
            border: 1px solid #e2e8f0;
            padding: 12px;
            font-size: 12px;
        }

        .promedio {
            font-weight: 700;
            color: var(--accent);
        }

        .footer {
            margin-top: 100px;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #1e293b;
            width: 200px;
            padding-top: 10px;
            font-size: 12px;
            font-weight: 700;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #1e293b; color: white; border: none; border-radius: 5px; cursor: pointer;">
            Descargar / Imprimir Reporte
        </button>
    </div>

    <div class="header">
        <h1 class="univ-name">UNICALI SEGURA</h1>
        <p class="report-title">Reporte Consolidado de Calificaciones</p>
    </div>

    <div class="info-box">
        <div>
            <strong>Materia:</strong> <?php echo htmlspecialchars($materia['nombre']); ?><br>
            <strong>Código:</strong> <?php echo htmlspecialchars($materia['codigo']); ?>
        </div>
        <div style="text-align: right;">
            <strong>Docente:</strong> <?php echo htmlspecialchars($_SESSION['nombre']); ?><br>
            <strong>Fecha:</strong> <?php echo $fecha_reporte; ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Estudiante</th>
                <th>Corte 1 (20%)</th>
                <th>Corte 2 (20%)</th>
                <th>Corte 3 (20%)</th>
                <th>Final (30%)</th>
                <th>Seg. (10%)</th>
                <th>Definitiva</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($al = $alumnos->fetch_assoc()):
                $mid = $al['matricula_id'];
                $q_notas = $conn->query("SELECT corte, valor FROM notas WHERE matricula_id = $mid");
                $notas = [];
                while ($n = $q_notas->fetch_assoc()) $notas[$n['corte']] = $n['valor'];

                $def = ($notas['Corte 1'] ?? 0) * 0.2 + ($notas['Corte 2'] ?? 0) * 0.2 + ($notas['Corte 3'] ?? 0) * 0.2 + ($notas['Examen Final'] ?? 0) * 0.3 + ($notas['Seguimiento'] ?? 0) * 0.1;
            ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($al['nombre']); ?></strong><br>
                        <small><?php echo htmlspecialchars($al['codigo_estudiantil']); ?></small>
                    </td>
                    <td align="center"><?php echo $notas['Corte 1'] ?? 'N/A'; ?></td>
                    <td align="center"><?php echo $notas['Corte 2'] ?? 'N/A'; ?></td>
                    <td align="center"><?php echo $notas['Corte 3'] ?? 'N/A'; ?></td>
                    <td align="center"><?php echo $notas['Examen Final'] ?? 'N/A'; ?></td>
                    <td align="center"><?php echo $notas['Seguimiento'] ?? 'N/A'; ?></td>
                    <td align="center" class="promedio"><?php echo number_format($def, 1); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="footer">
        <div class="signature-box">
            <div class="signature-line">Firma del Docente</div>
            <div style="font-size: 10px; color: #64748b;">C.C. ___________________</div>
        </div>
        <div class="signature-box">
            <div class="signature-line">Sello Registro y Control</div>
            <div style="font-size: 10px; color: #64748b;">Folio Académico 2024</div>
        </div>
    </div>

    <script>
        // Si el usuario viene directamente a imprimir
        // window.onload = function() { window.print(); }
    </script>
</body>

</html>