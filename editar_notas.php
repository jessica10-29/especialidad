<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('profesor');

if (!isset($_GET['materia'])) {
    header("Location: gestion_notas.php");
    exit();
}

$materia_id = (int)$_GET['materia'];
$profesor_id = $_SESSION['usuario_id'];
$tiene_columna_periodo = false;
$col_check = $conn->query("SHOW COLUMNS FROM matriculas LIKE 'periodo'");
if ($col_check && $col_check->num_rows > 0) {
    $tiene_columna_periodo = true;
}

// Obtener Periodo Actual de forma segura (Self-Healing)
$p_actual_id = obtener_periodo_actual();

// Verificar propiedad de la materia con seguridad
$check = $conn->query("SELECT * FROM materias WHERE id = $materia_id AND profesor_id = $profesor_id");
if ($check && $check->num_rows > 0) {
    $materia = $check->fetch_assoc();
} else {
    die("No tienes permiso para ver esta materia o no existe.");
}

// Verificar periodo de edición usando la nueva lógica robusta
$edicion_activa = es_periodo_habil($materia_id) ? '1' : '0';

$mensaje = '';
if ($edicion_activa == '0') {
    $mensaje = '<div style="background: rgba(244, 63, 94, 0.1); color: #fb7185; padding: 12px; border-radius: 10px; margin-bottom: 25px; font-size: 0.85rem; border: 1px solid rgba(244, 63, 114, 0.2);"><i class="fa-solid fa-lock"></i> El periodo de edición de notas está cerrado. Si necesitas corregir algo, solicita una apertura extraordinaria.</div>';
}

// Guardar Notas
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // FIX: Actualizar periodo si se solicita
    if (isset($_POST['fix_period_enrollment'])) {
        $mid = (int)$_POST['matricula_id'];
        // Obtener nombre del periodo actual
        $nom_p = $conn->query("SELECT nombre FROM periodos WHERE id = $p_actual_id")->fetch_assoc()['nombre'];
        $set_periodo = $tiene_columna_periodo ? ", periodo = '$nom_p'" : '';
        $conn->query("UPDATE matriculas SET periodo_id = $p_actual_id $set_periodo WHERE id = $mid");
        $mensaje = '<div style="background: rgba(16, 185, 129, 0.1); color: #34d399; padding: 12px; border-radius: 10px; margin-bottom: 25px; font-size: 0.85rem; border: 1px solid rgba(16, 185, 129, 0.2);"><i class="fa-solid fa-check-circle"></i> Estudiante movido al periodo actual.</div>';
    }

    if ($edicion_activa == '1' && isset($_POST['notas'])) {
        foreach ($_POST['notas'] as $matricula_id => $cortes) {
            $notas_finales = [];
            foreach ($cortes as $corte_nombre => $datos) {
                $valor = $datos['valor'];
                $obs = $conn->real_escape_string($datos['obs']);

                if ($valor !== '') {
                    if ($valor < 0 || $valor > 5) {
                        $mensaje = '<div style="background: rgba(244, 63, 94, 0.1); color: #fb7185; padding: 12px; border-radius: 10px; margin-bottom: 25px; font-size: 0.85rem; border: 1px solid rgba(244, 63, 114, 0.2);">Error: Notas deben estar entre 0.0 y 5.0</div>';
                        continue;
                    }

                    $sql_check = "SELECT id, valor, observacion FROM notas WHERE matricula_id = $matricula_id AND corte = '$corte_nombre'";
                    $exists = $conn->query($sql_check);

                    if ($exists->num_rows > 0) {
                        $nota_data = $exists->fetch_assoc();
                        $nid = $nota_data['id'];
                        $v_ant = $nota_data['valor'];
                        $o_ant = $nota_data['observacion'];

                        // Solo registrar si hubo cambios reales
                        if ($v_ant != $valor || $o_ant != $obs) {
                            $conn->query("UPDATE notas SET valor = '$valor', observacion = '$obs' WHERE id = $nid");
                            log_cambio_nota($nid, $v_ant, $valor, $o_ant, $obs, 'Actualización regular');
                        }
                    } else {
                        $conn->query("INSERT INTO notas (matricula_id, corte, valor, observacion) VALUES ($matricula_id, '$corte_nombre', '$valor', '$obs')");
                        $nuevo_id = $conn->insert_id;
                        log_cambio_nota($nuevo_id, null, $valor, null, $obs, 'Primer ingreso de nota');
                    }
                    // Actualizar la nota en el array para el cálculo del promedio
                    $notas_finales[$corte_nombre] = $valor;
                } else {
                    // Si el valor está vacío, se considera 0 para el cálculo del promedio
                    $notas_finales[$corte_nombre] = 0;
                }
            }
            // Actualizar promedio en la tabla matriculas (Para Trello)
            $prom_final = ($notas_finales['Corte 1'] ?? 0) * 0.2 +
                ($notas_finales['Corte 2'] ?? 0) * 0.2 +
                ($notas_finales['Corte 3'] ?? 0) * 0.2 +
                ($notas_finales['Examen Final'] ?? 0) * 0.3 +
                ($notas_finales['Seguimiento'] ?? 0) * 0.1;

            $conn->query("UPDATE matriculas SET promedio = $prom_final WHERE id = $matricula_id");
        }
        $mensaje = '<div style="background: rgba(16, 185, 129, 0.1); color: #34d399; padding: 12px; border-radius: 10px; margin-bottom: 25px; font-size: 0.85rem; border: 1px solid rgba(16, 185, 129, 0.2);"><i class="fa-solid fa-check-circle"></i> Calificaciones actualizadas y promedios calculados.</div>';
    }
}

// Prefiltro por nombre (llega desde dashboard o gestión)
$student_filter = isset($_GET['student']) ? trim($_GET['student']) : '';

// Obtener estudiantes (Universal - Sin filtro estricto de periodo)
$sql_estudiantes = "SELECT u.nombre, u.email, m.id as matricula_id, m.promedio, m.periodo_id, p.nombre as nombre_periodo 
                    FROM matriculas m 
                    JOIN usuarios u ON m.estudiante_id = u.id 
                    LEFT JOIN periodos p ON m.periodo_id = p.id
                    WHERE m.materia_id = $materia_id
                    ORDER BY u.nombre";
$res_estudiantes = $conn->query($sql_estudiantes);

// Eliminamos la lógica de "otros periodos" porque ahora mostramos a TODOS en la tabla principal.
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calificar: <?php echo htmlspecialchars($materia['nombre']); ?> - Unicali</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .input-nota {
            width: 65px !important;
            padding: 8px 4px !important;
            text-align: center;
            margin-bottom: 5px !important;
            font-weight: 700;
            border-radius: 8px !important;
            font-size: 0.9rem !important;
        }

        .input-obs {
            width: 100% !important;
            font-size: 0.75rem !important;
            padding: 6px !important;
            margin-bottom: 0 !important;
            border-radius: 8px !important;
            resize: none;
        }

        th {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            white-space: nowrap;
        }

        td {
            vertical-align: top;
            padding: 15px 10px !important;
        }
    </style>
</head>

<body>
    <div class="mobile-toggle" id="side-toggle">
        <i class="fa-solid fa-bars"></i>
    </div>
    <div class="mobile-overlay" id="mobile-overlay"></div>
    <div class="background-mesh"></div>
    <div class="dashboard-grid">
        <aside class="sidebar">
            <div class="logo-area" style="margin-bottom: 40px; text-align: center;">
                <i class="fa-solid fa-graduation-cap logo-icon" style="font-size: 2rem; color: var(--primary);"></i>
                <h3 style="color: white; margin-top: 10px;">Unicali<span style="color: var(--primary);">Docente</span></h3>
            </div>
            <nav>
                <a href="dashboard_profesor.php" class="nav-link">
                    <i class="fa-solid fa-house"></i> Inicio
                </a>
                <a href="gestion_materias.php" class="nav-link">
                    <i class="fa-solid fa-book"></i> Mis Materias
                </a>
                <a href="gestion_notas.php" class="nav-link active">
                    <i class="fa-solid fa-user-pen"></i> Gestionar Notas
                </a>
                <a href="asistencia.php" class="nav-link">
                    <i class="fa-solid fa-clipboard-user"></i> Asistencia
                </a>
                <a href="perfil.php" class="nav-link">
                    <i class="fa-solid fa-gear"></i> Configuración
                </a>
                <a href="logout.php" class="nav-link" style="margin-top: auto; color: #f43f5e;">
                    <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <header style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                <div>
                    <h1 class="text-gradient">
                        <?php echo htmlspecialchars($materia['nombre']); ?>
                        <div style="display: inline-block; vertical-align: middle; margin-left: 10px;">
                            <?php echo obtener_estado_edicion($materia_id); ?>
                        </div>
                    </h1>
                    <p class="text-muted">Gestión de Calificaciones • <?php echo htmlspecialchars($materia['codigo']); ?></p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="gestion_notas.php" class="btn btn-outline">
                        <i class="fa-solid fa-arrow-left"></i> Volver
                    </a>
                    <button type="button" onclick="document.getElementById('form-notas').submit()" class="btn btn-primary" <?php echo ($edicion_activa == '0') ? 'disabled' : ''; ?>>
                        <i class="fa-solid fa-save"></i> Guardar Todo
                    </button>
                </div>
            </header>

            <?php echo $mensaje; ?>

            <?php if ($edicion_activa == '0'): ?>
                <div style="text-align: center; margin-bottom: 30px;">
                    <a href="solicitar_apertura.php?materia=<?php echo $materia_id; ?>" class="btn btn-primary" style="background: var(--accent); color: white;">
                        <i class="fa-solid fa-envelope-open-text"></i> Solicitar Apertura Extraordinaria
                    </a>
                </div>
            <?php endif; ?>

            <div style="margin-bottom: 25px; display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
                <div class="search-container" style="flex-grow: 1; position: relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--primary); opacity: 0.5;"></i>
                    <input type="text" id="prof-search" class="input-field" placeholder="Buscar estudiante, código u observación..."
                        onkeyup="filterTable()" style="padding-left: 45px; width: 100%;">
                </div>
            </div>

            <div class="card glass-panel fade-in" style="padding: 0; overflow: hidden;">
                <form method="POST" id="form-notas">
                    <div class="table-container" style="overflow-x: auto;">
                        <table style="width: 100%; min-width: 1000px;">
                            <thead>
                                <tr>
                                    <th style="width: 250px; padding-left: 20px;">Estudiante</th>
                                    <th>Parcial 1<br><small>(20%)</small></th>
                                    <th>Parcial 2<br><small>(20%)</small></th>
                                    <th>Quices/Talleres<br><small>(20%)</small></th>
                                    <th>Eva. Final<br><small>(30%)</small></th>
                                    <th>Seg. Docente<br><small>(10%)</small></th>
                                    <th style="background: rgba(99, 102, 241, 0.1); color: var(--primary);">Promedio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($res_estudiantes && $res_estudiantes->num_rows > 0):
                                    // Mapeo Trello
                                    $map_cortes = [
                                        'Parcial 1' => 'Corte 1',
                                        'Parcial 2' => 'Corte 2',
                                        'Quices/Talleres' => 'Corte 3',
                                        'Eva. Final' => 'Examen Final',
                                        'Seg. Docente' => 'Seguimiento'
                                    ];
                                    // Obtener límite de observación
                                    $res_lim = $conn->query("SELECT valor FROM configuracion WHERE clave = 'limite_observacion'");
                                    $limite_obs = ($res_lim && $res_lim->num_rows > 0) ? (int)$res_lim->fetch_assoc()['valor'] : 250;

                                    // Helper para inputs
                                    function renderInput($mid, $corte, $notas_db, $edicion_activa, $limite)
                                    {
                                        $val = isset($notas_db[$corte]) ? $notas_db[$corte]['valor'] : '';
                                        $obs = isset($notas_db[$corte]) ? $notas_db[$corte]['observacion'] : '';
                                        $readonly = ($edicion_activa == '0') ? 'readonly' : '';
                                        $input_id = $mid . '_' . str_replace(' ', '', $corte);

                                        echo '<div style="display: flex; flex-direction: column; align-items: center; gap: 5px;">
                                                <input type="number" step="0.1" min="0" max="5" 
                                                       name="notas[' . $mid . '][' . $corte . '][valor]" 
                                                       value="' . $val . '" 
                                                       class="input-field input-nota" 
                                                       placeholder="0.0" ' . $readonly . ' style="width: 70px; text-align: center; font-weight: bold;">
                                                <textarea id="obs-' . $input_id . '" 
                                                          name="notas[' . $mid . '][' . $corte . '][obs]" 
                                                          class="input-field input-obs" 
                                                          placeholder="Obs..." 
                                                          rows="1" ' . $readonly . ' 
                                                          maxlength="' . $limite . '"
                                                          onkeyup="updateCharCount(\'' . $input_id . '\', ' . $limite . ')"
                                                          style="font-size: 0.75rem; width: 120px;">' . htmlspecialchars($obs) . '</textarea>
                                                <small id="count-' . $input_id . '" class="text-muted" style="font-size: 0.6rem; opacity: 0.7;">' . strlen($obs) . '/' . $limite . '</small>
                                              </div>';
                                    }

                                    while ($est = $res_estudiantes->fetch_assoc()):
                                        $mid = $est['matricula_id'];
                                        $notas_db = [];
                                        $q_notas = $conn->query("SELECT corte, valor, observacion FROM notas WHERE matricula_id = $mid");
                                        while ($n = $q_notas->fetch_assoc()) {
                                            $notas_db[$n['corte']] = $n;
                                        }
                                ?>
                                        <tr>
                                            <td style="padding-left: 20px;">
                                                <div style="font-weight: 600; font-size: 0.9rem;"><?php echo htmlspecialchars($est['nombre']); ?></div>
                                                <div class="text-muted" style="font-size: 0.7rem;">
                                                    <?php echo htmlspecialchars($est['email']); ?>
                                                    <?php if ($est['periodo_id'] != $p_actual_id): ?>
                                                        <br>
                                                        <span class="badge" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 5px; margin-top: 2px;">
                                                            <i class="fa-solid fa-clock-rotate-left"></i> <?php echo htmlspecialchars($est['nombre_periodo'] ?? 'Periodo ' . $est['periodo_id']); ?>
                                                            <form method="POST" style="display:inline;">
                                                                <input type="hidden" name="matricula_id" value="<?php echo $mid; ?>">
                                                                <button type="submit" name="fix_period_enrollment" value="1" title="Mover al Periodo Actual" style="background:none; border:none; cursor:pointer; color: #f59e0b; padding: 0;">
                                                                    <i class="fa-solid fa-repeat"></i>
                                                                </button>
                                                            </form>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php renderInput($mid, 'Corte 1', $notas_db, $edicion_activa, $limite_obs); ?></td>
                                            <td><?php renderInput($mid, 'Corte 2', $notas_db, $edicion_activa, $limite_obs); ?></td>
                                            <td><?php renderInput($mid, 'Corte 3', $notas_db, $edicion_activa, $limite_obs); ?></td>
                                            <td><?php renderInput($mid, 'Examen Final', $notas_db, $edicion_activa, $limite_obs); ?></td>
                                            <td><?php renderInput($mid, 'Seguimiento', $notas_db, $edicion_activa, $limite_obs); ?></td>
                                            <td style="background: rgba(99, 102, 241, 0.03); text-align: center;">
                                                <?php
                                                $p = ($notas_db['Corte 1']['valor'] ?? 0) * 0.2 + ($notas_db['Corte 2']['valor'] ?? 0) * 0.2 + ($notas_db['Corte 3']['valor'] ?? 0) * 0.2 + ($notas_db['Examen Final']['valor'] ?? 0) * 0.3 + ($notas_db['Seguimiento']['valor'] ?? 0) * 0.1;
                                                ?>
                                                <div id="prom-<?php echo $mid; ?>" class="promedio-badge" style="font-weight: 800; font-size: 1.1rem; color: <?php echo $p >= 3 ? '#34d399' : '#fb7185'; ?>;">
                                                    <?php echo number_format($p, 1); ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile;
                                else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 40px;">
                                            <i class="fa-solid fa-users-slash" style="font-size: 3rem; color: #334155; margin-bottom: 20px;"></i>
                                            <p class="text-muted" style="margin-bottom: 20px;">No hay estudiantes inscritos en esta materia.</p>
                                            <a href="gestion_notas.php?materia=<?php echo $materia_id; ?>" class="btn btn-primary">
                                                <i class="fa-solid fa-user-plus"></i> Inscribir Estudiantes
                                            </a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>
        const btn = document.getElementById('side-toggle');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('mobile-overlay');

        const toggleMenu = () => {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
            const icon = btn.querySelector('i');
            if (sidebar.classList.contains('active')) {
                icon.classList.replace('fa-bars', 'fa-xmark');
            } else {
                icon.classList.replace('fa-xmark', 'fa-bars');
            }
        };

        btn.onclick = toggleMenu;
        overlay.onclick = toggleMenu;

        function filterTable() {
            var input = document.getElementById("prof-search");
            var filter = input.value.toUpperCase();
            var table = document.querySelector("table");
            var tr = table.getElementsByTagName("tr");
            for (var i = 1; i < tr.length; i++) {
                var text = tr[i].innerText.toUpperCase();
                if (text.indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }

        function updateCharCount(id, max) {
            var area = document.getElementById('obs-' + id);
            var count = document.getElementById('count-' + id);
            count.innerText = area.value.length + '/' + max;
            if (area.value.length >= max * 0.9) {
                count.style.color = '#fb7185';
            } else {
                count.style.color = '#94a3b8';
            }
        }

        // Lógica de cálculo de promedios en tiempo real (Trello)
        document.querySelectorAll('.input-nota').forEach(input => {
            input.addEventListener('input', function() {
                const tr = this.closest('tr');
                const mid = tr.querySelector('.promedio-badge').id.split('-')[1];
                const inputs = tr.querySelectorAll('.input-nota');

                const c1 = parseFloat(inputs[0].value) || 0;
                const c2 = parseFloat(inputs[1].value) || 0;
                const c3 = parseFloat(inputs[2].value) || 0;
                const f = parseFloat(inputs[3].value) || 0;
                const s = parseFloat(inputs[4].value) || 0;

                const promedio = (c1 * 0.2) + (c2 * 0.2) + (c3 * 0.2) + (f * 0.3) + (s * 0.1);
                const display = document.getElementById('prom-' + mid);

                display.innerText = promedio.toFixed(1);
                display.style.color = promedio >= 3.0 ? '#34d399' : '#fb7185';

                // Efecto de pulso en el cambio
                display.style.transform = 'scale(1.2)';
                setTimeout(() => display.style.transform = 'scale(1)', 200);
            });
        });
    </script>
</body>

</html>
