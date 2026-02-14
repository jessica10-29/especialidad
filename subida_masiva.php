<?php
// subida_masiva.php - Módulo Profesional de Calificación Masiva mediante CSV
require_once 'conexion.php';
verificar_sesion();
verificar_rol('profesor');

$profesor_id = $_SESSION['usuario_id'];
$mensaje = '';

// 1. Lógica para descargar la plantilla (CSV Dinámico)
if (isset($_GET['descargar_plantilla']) && isset($_GET['materia'])) {
    $mid = (int)$_GET['materia'];

    // Obtener Periodo Actual de forma segura (Self-Healing)
    $p_actual_id = obtener_periodo_actual();

    // Verificar que la materia pertenezca al profesor
    $check = $conn->query("SELECT nombre FROM materias WHERE id = $mid AND profesor_id = $profesor_id");
    if ($check && $check->num_rows > 0) {
        $materia = $check->fetch_assoc();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Plantilla_Notas_' . str_replace(' ', '_', $materia['nombre']) . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID_ESTUDIANTE', 'IDENTIFICACION', 'NOMBRE_COMPLETO', 'CALIFICACION', 'OBSERVACION']);

        $estudiantes = $conn->query("SELECT u.id, u.nombre, u.identificacion 
                                     FROM matriculas m 
                                     JOIN usuarios u ON m.estudiante_id = u.id 
                                     WHERE m.materia_id = $mid AND m.periodo_id = $p_actual_id ORDER BY u.nombre ASC");

        while ($row = $estudiantes->fetch_assoc()) {
            fputcsv($output, [$row['id'], $row['identificacion'], $row['nombre'], '', '']);
        }
        fclose($output);
        exit;
    }
}

// 2. Lógica para procesar el archivo subido (Vista Previa)
$preview_data = [];
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['archivo_notas'])) {
    $mid = (int)$_POST['materia_id'];
    $corte = $_POST['corte'];
    $file = $_FILES['archivo_notas'];

    if ($file['error'] == 0) {
        $handle = fopen($file['tmp_name'], "r");

        // Detectar delimitador (Coma o Punto y Coma)
        $first_line = fgets($handle);
        $delimiter = (strpos($first_line, ';') !== false) ? ';' : ',';
        rewind($handle);

        fgetcsv($handle, 1000, $delimiter); // Saltar encabezado

        while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
            if (count($data) < 4) continue; // Saltear lineas vacias o mal formadas
            $preview_data[] = [
                'id' => $data[0],
                'identificacion' => $data[1],
                'nombre' => $data[2],
                'nota' => $data[3],
                'obs' => isset($data[4]) ? $data[4] : '',
                'error' => (float)$data[3] > 5.0 || (float)$data[3] < 0 || (!is_numeric($data[3]) && $data[3] !== '') ? 'Rango inválido (0-5)' : ''
            ];
        }
        fclose($handle);
    }
}

// 3. Lógica para guardar definitivamente
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirmar_subida'])) {
    $mid = (int)$_POST['materia_id'];
    $corte = $_POST['corte'];
    $estudiantes_ids = $_POST['est_ids'];
    $notas = $_POST['notas'];
    $observaciones = $_POST['observaciones'];

    $conn->begin_transaction();
    try {
        foreach ($estudiantes_ids as $index => $eid) {
            $nota_val = (float)$notas[$index];
            $obs_val = $observaciones[$index];

            // Obtener Periodo Actual para el guardado de forma segura (Self-Healing)
            $p_act_id = obtener_periodo_actual();

            // Obtener matricula_id filtrando por periodo actual
            $mat = $conn->query("SELECT id FROM matriculas WHERE estudiante_id = $eid AND materia_id = $mid AND periodo_id = $p_act_id")->fetch_assoc();
            if ($mat) {
                $matricula_id = $mat['id'];

                // Verificar si ya existe nota para ese corte
                $check = $conn->query("SELECT id FROM notas WHERE matricula_id = $matricula_id AND corte = '$corte'");
                if ($check && $check->num_rows > 0) {
                    $conn->query("UPDATE notas SET valor = $nota_val, observacion = '$obs_val' WHERE matricula_id = $matricula_id AND corte = '$corte'");
                } else {
                    $conn->query("INSERT INTO notas (matricula_id, corte, valor, observacion) VALUES ($matricula_id, '$corte', $nota_val, '$obs_val')");
                }

                // Recalcular promedio de la materia para este estudiante (Trello)
                include_once 'funciones_academicas.php';
                $sql_n = "SELECT corte, valor FROM notas WHERE matricula_id = $matricula_id";
                $res_n = $conn->query($sql_n);
                $suma = 0;
                while ($n = $res_n->fetch_assoc()) {
                    if ($n['corte'] == 'Corte 1') $suma += $n['valor'] * 0.2;
                    if ($n['corte'] == 'Corte 2') $suma += $n['valor'] * 0.2;
                    if ($n['corte'] == 'Corte 3') $suma += $n['valor'] * 0.2;
                    if ($n['corte'] == 'Examen Final') $suma += $n['valor'] * 0.3;
                    if ($n['corte'] == 'Seguimiento') $suma += $n['valor'] * 0.1;
                }
                $conn->query("UPDATE matriculas SET promedio = $suma WHERE id = $matricula_id");
            }
        }
        $conn->commit();
        header("Location: gestion_notas.php?msg=bulk_ok");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "Error al procesar: " . $e->getMessage();
    }
}

$materias = $conn->query("SELECT * FROM materias WHERE profesor_id = $profesor_id");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subida Masiva de Notas - Unicali</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .step-bubble {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            border: 1px solid var(--glass-border);
        }

        .step-active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: scale(1.1);
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.4);
        }

        .data-error {
            background: rgba(244, 63, 94, 0.1);
            border-left: 3px solid #fb7185;
        }
    </style>
</head>

<body>
    <div class="background-mesh"></div>
    <div class="dashboard-grid">
        <aside class="sidebar">
            <div class="logo-area" style="margin-bottom: 40px; text-align: center;">
                <i class="fa-solid fa-graduation-cap logo-icon" style="font-size: 2rem; color: var(--primary);"></i>
                <h3 style="color: white; margin-top: 10px;">Unicali<span style="color: var(--primary);">Docente</span></h3>
            </div>
            <nav>
                <a href="dashboard_profesor.php" class="nav-link"><i class="fa-solid fa-house"></i> Inicio</a>
                <a href="gestion_notas.php" class="nav-link"><i class="fa-solid fa-arrow-left"></i> Volver</a>
            </nav>
        </aside>

        <main class="main-content">
            <header style="margin-bottom: 30px;">
                <h1 class="text-gradient">Carga Masiva de Calificaciones</h1>
                <p class="text-muted">Procesa cientos de notas en segundos mediante archivos CSV</p>
            </header>

            <!-- Pasos del Proceso -->
            <div style="display: flex; justify-content: space-around; margin-bottom: 40px; position: relative;">
                <div style="position: absolute; top: 17px; left: 10%; right: 10%; height: 2px; background: rgba(255,255,255,0.05); z-index: -1;"></div>
                <div style="text-align: center;">
                    <div class="step-bubble <?php echo empty($preview_data) ? 'step-active' : ''; ?>">1</div>
                    <p style="font-size: 0.75rem; margin-top: 8px;" class="text-muted">Preparar</p>
                </div>
                <div style="text-align: center;">
                    <div class="step-bubble <?php echo !empty($preview_data) ? 'step-active' : ''; ?>">2</div>
                    <p style="font-size: 0.75rem; margin-top: 8px;" class="text-muted">Vista Previa</p>
                </div>
                <div style="text-align: center;">
                    <div class="step-bubble">3</div>
                    <p style="font-size: 0.75rem; margin-top: 8px;" class="text-muted">Finalizar</p>
                </div>
            </div>

            <?php if (empty($preview_data)): ?>
                <!-- PASO 1: Configuración y Carga -->
                <div class="card glass-panel fade-in" style="max-width: 700px; margin: 0 auto;">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="input-group">
                            <label class="input-label">1. Selecciona la Materia</label>
                            <select name="materia_id" id="materia_sel" class="input-field" required onchange="updateTemplateLink()">
                                <option value="">-- Elige un curso --</option>
                                <?php
                                $materias->data_seek(0);
                                while ($m = $materias->fetch_assoc()): ?>
                                    <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['nombre']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="input-group">
                            <label class="input-label">2. Tipo de Evaluación</label>
                            <select name="corte" class="input-field" required>
                                <option value="Corte 1">Parcial 1 (20%)</option>
                                <option value="Corte 2">Parcial 2 (20%)</option>
                                <option value="Corte 3">Quices y Talleres (20%)</option>
                                <option value="Examen Final">Evaluación Final (30%)</option>
                                <option value="Seguimiento">Seguimiento Docente (10%)</option>
                            </select>
                        </div>

                        <div style="background: rgba(99, 102, 241, 0.05); padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 1px dashed var(--primary);">
                            <p style="font-size: 0.85rem; margin-bottom: 15px;"><i class="fa-solid fa-circle-info" style="color: var(--primary);"></i> Descarga la plantilla oficial para que el sistema reconozca tus estudiantes:</p>
                            <a id="btn-plantilla" href="#" class="btn btn-outline" style="pointer-events: none; opacity: 0.5;">
                                <i class="fa-solid fa-download"></i> Descargar Plantilla .CSV
                            </a>
                        </div>

                        <div class="input-group">
                            <label class="input-label">3. Sube el archivo completado</label>
                            <input type="file" name="archivo_notas" class="input-field" accept=".csv" required style="padding: 10px;">
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; height: 50px;">
                            Procesar y Ver Vista Previa <i class="fa-solid fa-magnifying-glass-chart" style="margin-left: 8px;"></i>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- PASO 2: Vista Previa y Confirmación -->
                <div class="card glass-panel fade-in">
                    <h3 style="margin-bottom: 20px;"><i class="fa-solid fa-eye" style="color: var(--primary);"></i> Vista Previa de Datos</h3>
                    <form method="POST">
                        <input type="hidden" name="materia_id" value="<?php echo $mid; ?>">
                        <input type="hidden" name="corte" value="<?php echo $corte; ?>">

                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Identificación</th>
                                        <th>Nombre Completo</th>
                                        <th style="width: 100px;">Nota</th>
                                        <th>Observación</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $con_errores = false;
                                    foreach ($preview_data as $row):
                                        if ($row['error']) $con_errores = true;
                                    ?>
                                        <tr class="<?php echo $row['error'] ? 'data-error' : ''; ?>">
                                            <input type="hidden" name="est_ids[]" value="<?php echo $row['id']; ?>">
                                            <td><?php echo $row['identificacion']; ?></td>
                                            <td style="font-weight: 600;"><?php echo $row['nombre']; ?></td>
                                            <td>
                                                <input type="number" step="0.1" name="notas[]" value="<?php echo $row['nota']; ?>" class="input-field" style="margin: 0; padding: 5px; text-align: center;">
                                            </td>
                                            <td>
                                                <input type="text" name="observaciones[]" value="<?php echo $row['obs']; ?>" class="input-field" style="margin: 0; padding: 5px;">
                                            </td>
                                            <td>
                                                <?php if ($row['error']): ?>
                                                    <span style="color: #fb7185; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $row['error']; ?></span>
                                                <?php else: ?>
                                                    <span style="color: #34d399; font-size: 0.75rem;"><i class="fa-solid fa-check"></i> Listo</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div style="margin-top: 30px; display: flex; gap: 15px;">
                            <a href="subida_masiva.php" class="btn btn-outline" style="flex: 1;">Cancelar y Volver</a>
                            <button type="submit" name="confirmar_subida" class="btn btn-primary" style="flex: 2; height: 50px;" <?php echo $con_errores ? 'disabled' : ''; ?>>
                                <i class="fa-solid fa-cloud-arrow-up"></i> Confirmar y Guardar todas las Notas
                            </button>
                        </div>
                        <?php if ($con_errores): ?>
                            <p style="color: #fb7185; font-size: 0.8rem; margin-top: 10px; text-align: center;">Corrige las notas marcadas en rojo antes de proceder.</p>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function updateTemplateLink() {
            var mid = document.getElementById('materia_sel').value;
            var btn = document.getElementById('btn-plantilla');
            if (mid) {
                btn.href = 'subida_masiva.php?descargar_plantilla=1&materia=' + mid;
                btn.style.opacity = '1';
                btn.style.pointerEvents = 'auto';
            } else {
                btn.href = '#';
                btn.style.opacity = '0.5';
                btn.style.pointerEvents = 'none';
            }
        }
    </script>
</body>

</html>