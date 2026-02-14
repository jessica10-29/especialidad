<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('profesor');

$profesor_id = $_SESSION['usuario_id'];
$mensaje = '';
$tiene_columna_periodo = false;
$res_col_periodo = $conn->query("SHOW COLUMNS FROM matriculas LIKE 'periodo'");
if ($res_col_periodo && $res_col_periodo->num_rows > 0) {
    $tiene_columna_periodo = true;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $periodo_id_act = obtener_periodo_actual();
    // Obtener nombre para el LOG
    $res_p_nom = $conn->query("SELECT nombre FROM periodos WHERE id = $periodo_id_act");
    $periodo_nom_act = ($res_p_nom && $row_n = $res_p_nom->fetch_assoc()) ? $row_n['nombre'] : '2024-1';

    if (isset($_POST['asignar_multiple'])) {
        $estudiantes_ids = $_POST['estudiantes_seleccionados'] ?? [];
        $materia_id = (int)$_POST['materia_id'];
        $exito = 0;
        $duplicados = 0;

        foreach ($estudiantes_ids as $est_id) {
            $est_id = (int)$est_id;
            $check = $conn->query("SELECT id FROM matriculas WHERE estudiante_id = $est_id AND materia_id = $materia_id AND periodo_id = $periodo_id_act");
            if ($check && $check->num_rows == 0) {
                if ($tiene_columna_periodo) {
                    $ok = $conn->query("INSERT INTO matriculas (estudiante_id, materia_id, periodo, periodo_id) VALUES ($est_id, $materia_id, '$periodo_nom_act', $periodo_id_act)");
                } else {
                    $ok = $conn->query("INSERT INTO matriculas (estudiante_id, materia_id, periodo_id) VALUES ($est_id, $materia_id, $periodo_id_act)");
                }
                if ($ok) {
                    $exito++;
                }
            } else {
                $duplicados++;
            }
        }
        $mensaje = '<div style="background: rgba(16, 185, 129, 0.1); color: #34d399; padding: 12px; border-radius: 10px; margin-bottom: 25px; font-size: 0.85rem; border: 1px solid rgba(16, 185, 129, 0.2);"><i class="fa-solid fa-check-circle"></i> ' . $exito . ' estudiantes inscritos en ' . $periodo_nom_act . '.</div>';
    }

    if (isset($_POST['remover'])) {
        $mid = (int)$_POST['matricula_id'];
        $conn->query("DELETE FROM matriculas WHERE id = $mid");
        $mensaje = '<div style="background: rgba(244, 63, 94, 0.1); color: #fb7185; padding: 12px; border-radius: 10px; margin-bottom: 25px; font-size: 0.85rem; border: 1px solid rgba(244, 63, 114, 0.2);"><i class="fa-solid fa-user-minus"></i> Inscripción removida con éxito.</div>';
    }

    // FIX: Lógica para mover estudiante al periodo actual
    if (isset($_POST['actualizar_periodo'])) {
        $mid = (int)$_POST['matricula_id'];
        // Obtener nombre del periodo actual para mantener consistencia
        $nom_p = $conn->query("SELECT nombre FROM periodos WHERE id = $periodo_id_act")->fetch_assoc()['nombre'];

        $set_periodo = $tiene_columna_periodo ? ", periodo = '$nom_p'" : '';
        $conn->query("UPDATE matriculas SET periodo_id = $periodo_id_act $set_periodo WHERE id = $mid");
        $mensaje = '<div style="background: rgba(16, 185, 129, 0.1); color: #34d399; padding: 12px; border-radius: 10px; margin-bottom: 25px; font-size: 0.85rem; border: 1px solid rgba(16, 185, 129, 0.2);"><i class="fa-solid fa-check-circle"></i> Estudiante movido al periodo actual (' . $nom_p . '). Ahora aparecerá en calificaciones.</div>';
    }
}

// Obtener periodo actual global con el nuevo sistema de seguridad (Self-Healing)
$periodo_global_id = obtener_periodo_actual();
// Obtener nombre para mostrar en UI
$res_p_global = $conn->query("SELECT nombre FROM periodos WHERE id = $periodo_global_id");
$p_global_act = ($res_p_global && $row_g = $res_p_global->fetch_assoc()) ? $row_g : ['nombre' => '2024-1'];

// Obtener todos los estudiantes para el buscador (Trello)
$estudiantes_all = $conn->query("SELECT id, nombre, email, identificacion, foto FROM usuarios WHERE rol = 'estudiante' ORDER BY nombre ASC");
$lista_est = [];
while ($e = $estudiantes_all->fetch_assoc()) {
    $lista_est[] = $e;
}
$pendientes = $conn->query("
    SELECT COUNT(*) as total FROM usuarios u
    WHERE u.rol = 'estudiante'
      AND u.id NOT IN (
          SELECT DISTINCT m.estudiante_id
          FROM matriculas m
          JOIN materias mat ON m.materia_id = mat.id
          WHERE mat.profesor_id = $profesor_id
      )
");
$pendientes_total = ($pendientes && $rowp = $pendientes->fetch_assoc()) ? (int)$rowp['total'] : 0;

$materias = $conn->query("SELECT * FROM materias WHERE profesor_id = $profesor_id");
$materias_list = [];
while ($m = $materias->fetch_assoc()) {
    $materias_list[] = $m;
}
$highlight_student = isset($_GET['student']) ? limpiar_dato($_GET['student']) : '';

// Verificar si hay estudiantes en el sistema para diagnóstico
$hay_estudiantes = (count($lista_est) > 0);
if (!$hay_estudiantes && empty($mensaje)) {
    $mensaje = '<div style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; padding: 12px; border-radius: 10px; margin-bottom: 25px; font-size: 0.85rem; border: 1px solid rgba(245, 158, 11, 0.2);"><i class="fa-solid fa-circle-exclamation"></i> No hay estudiantes con el rol "estudiante" registrados en el sistema. <a href="registro.php" style="color: #f59e0b; font-weight: bold; text-decoration: underline;">Registrar nuevo estudiante aquí</a></div>';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Académica - Docentes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
</head>

<body>
    <div class="background-mesh"></div>
    <div class="mobile-toggle" id="side-toggle">
        <i class="fa-solid fa-bars"></i>
    </div>
    <div class="mobile-overlay" id="mobile-overlay"></div>
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
                <a href="gestion_actividades.php" class="nav-link">
                    <i class="fa-solid fa-tasks"></i> Gestión de Trabajos
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
            <header style="margin-bottom: 30px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <div>
                    <h1 class="text-gradient">Gestión Académica</h1>
                    <p class="text-muted">Inscribe alumnos y califica tus cursos activos</p>
                </div>
                <?php if ($pendientes_total > 0): ?>
                    <span class="badge" style="background: rgba(244,114,182,0.12); color:#ec4899; border:1px solid rgba(244,114,182,0.25); padding:8px 12px; border-radius: 999px; font-weight:700;">
                        <?php echo $pendientes_total; ?> estudiante(s) nuevos sin asignar
                    </span>
                <?php endif; ?>
            </header>

            <?php echo $mensaje; ?>

            <div id="inscribir"></div>

            <div style="display: grid; gap: 20px;">
                <?php foreach ($materias_list as $m):
                    $mid = $m['id'];
                    $q_c = $conn->query("SELECT COUNT(*) as c FROM matriculas WHERE materia_id = $mid AND periodo_id = $periodo_global_id");
                    $count = ($q_c && $row_c = $q_c->fetch_assoc()) ? $row_c['c'] : 0;
                ?>
                    <div class="card glass-panel fade-in">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                            <div style="display: flex; align-items: center; gap: 20px;">
                                <div style="background: rgba(99, 102, 241, 0.1); width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fa-solid fa-users-viewfinder" style="color: var(--primary); font-size: 1.2rem;"></i>
                                </div>
                                <div>
                                    <h3 style="margin-bottom: 4px;"><?php echo htmlspecialchars($m['nombre']); ?> <span class="text-muted" style="font-size:0.8rem; font-weight: 500;">(<?php echo htmlspecialchars($m['codigo']); ?>)</span></h3>
                                    <p class="text-muted"><i class="fa-solid fa-user-graduate" style="margin-right: 5px;"></i> <?php echo $count; ?> Estudiantes inscritos</p>
                                </div>
                            </div>
                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <button onclick="mostrarAsignar(<?php echo $mid; ?>)" class="btn btn-outline" style="font-size: 0.85rem; min-width: 140px;">
                                    <i class="fa-solid fa-users-gear"></i> Gestionar Alumnos
                                </button>
                                <a href="subida_masiva.php?materia=<?php echo $mid; ?>" class="btn btn-outline" style="font-size: 0.85rem; color: #a855f7; border-color: rgba(168, 85, 247, 0.3);">
                                    <i class="fa-solid fa-file-csv"></i> Subida Masiva
                                </a>
                                <a href="editar_notas.php?materia=<?php echo $mid; ?>" class="btn btn-primary" style="font-size: 0.85rem; background: var(--primary);">
                                    <i class="fa-solid fa-pen-nib"></i> Calificar
                                </a>
                                <a href="reporte_notas_pdf.php?materia=<?php echo $mid; ?>" target="_blank" class="btn btn-outline" style="font-size: 0.85rem; border-color: #ef4444; color: #f87171; width: 45px;"><i class="fa-solid fa-file-pdf"></i></a>
                            </div>
                        </div>

                        <!-- Panel de Gestión de Alumnos Avanzado (Trello) -->
                        <div id="form-asignar-<?php echo $mid; ?>" style="display: none; margin-top: 25px; padding: 25px; background: rgba(255,255,255,0.02); border-radius: 15px; border: 1px solid var(--glass-border);" class="fade-in">
                            <div class="responsive-layout-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: start;">
                                <!-- Columna de Inscripción Multiple (Trello) -->
                                <div>
                                    <h4 style="margin-bottom: 15px; font-size: 1rem;"><i class="fa-solid fa-user-plus" style="margin-right: 8px;"></i> Inscribir Estudiantes</h4>
                                    <form method="POST">
                                        <input type="hidden" name="materia_id" value="<?php echo $mid; ?>">
                                        <input type="hidden" name="asignar_multiple" value="1">

                                        <div class="input-group">
                                            <input type="text" id="search-<?php echo $mid; ?>" class="input-field" placeholder="Filtrar por nombre o ID..." onkeyup="filterStudents(<?php echo $mid; ?>)">
                                        </div>

                                        <div id="list-<?php echo $mid; ?>" style="max-height: 250px; overflow-y: auto; background: rgba(0,0,0,0.2); border-radius: 12px; border: 1px solid var(--glass-border); padding: 5px;">
                                            <?php if ($hay_estudiantes): ?>
                                                <?php foreach ($lista_est as $est): ?>
                                                    <label class="student-item" style="padding: 10px 15px; display: flex; align-items: center; gap: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); cursor: pointer; transition: background 0.2s;">
                                                        <input type="checkbox" name="estudiantes_seleccionados[]" value="<?php echo $est['id']; ?>" style="width: 18px; height: 18px; accent-color: var(--primary);">
                                                        <?php $foto_sel = obtener_foto_usuario($est['foto']); ?>
                                                        <img src="<?php echo htmlspecialchars($foto_sel); ?>" alt="avatar" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(255,255,255,0.08);">
                                                        <div style="flex: 1;">
                                                            <div style="font-size: 0.85rem; font-weight: 600; color: white;"><?php echo htmlspecialchars($est['nombre']); ?></div>
                                                            <div style="font-size: 0.7rem; opacity: 0.5;"><?php echo htmlspecialchars($est['identificacion'] ?: $est['email']); ?></div>
                                                        </div>
                                                    </label>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div style="padding: 20px; text-align: center; color: #94a3b8; font-size: 0.85rem;">
                                                    <i class="fa-solid fa-user-slash" style="font-size: 1.5rem; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                                                    No hay estudiantes registrados.<br>
                                                    <a href="registro.php" style="color: var(--primary); margin-top: 10px; display: inline-block;">Ir a Registro</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 15px; height: 45px;">
                                            <i class="fa-solid fa-user-check"></i> Inscribir Seleccionados
                                        </button>
                                    </form>
                                </div>

                                <!-- Columna de Inscritos Actuales -->
                                <div>
                                    <h4 style="margin-bottom: 15px; font-size: 1rem;"><i class="fa-solid fa-user-check" style="margin-right: 8px;"></i> Alumnos Inscritos (Todos los Periodos)</h4>
                                    <div style="max-height: 250px; overflow-y: auto;">
                                        <?php
                                        // FIX: Mostrar TODOS los inscritos independientemente del periodo para evitar "desapariciones"
                                        $inscritos = $conn->query("SELECT m.id as mat_id, u.nombre, u.identificacion, u.foto, m.periodo_id, p.nombre as nombre_periodo 
                                                                 FROM matriculas m 
                                                                 JOIN usuarios u ON m.estudiante_id = u.id 
                                                                 LEFT JOIN periodos p ON m.periodo_id = p.id
                                                                 WHERE m.materia_id = $mid 
                                                                 ORDER BY u.nombre ASC");

                                        if ($inscritos && $inscritos->num_rows > 0):
                                            while ($ins = $inscritos->fetch_assoc()):
                                                $es_periodo_incorrecto = ($ins['periodo_id'] != $periodo_global_id);
                                        ?>
                                                <div id="mat-<?php echo $ins['mat_id']; ?>" style="padding: 10px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); <?php echo $es_periodo_incorrecto ? 'background: rgba(245, 158, 11, 0.1); border-left: 3px solid #f59e0b;' : ''; ?>">
                                                    <div style="display:flex; align-items:center; gap:10px;">
                                                        <?php $foto_ins = obtener_foto_usuario($ins['foto']); ?>
                                                        <img src="<?php echo htmlspecialchars($foto_ins); ?>" alt="avatar" style="width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(255,255,255,0.08);">
                                                        <div>
                                                            <div style="font-size: 0.85rem;"><?php echo htmlspecialchars($ins['nombre']); ?></div>
                                                            <div style="font-size: 0.7rem; opacity: 0.4;">
                                                                <?php echo $ins['identificacion']; ?>
                                                                <?php if ($es_periodo_incorrecto): ?>
                                                                    <span style="color: #f59e0b; font-weight: bold; margin-left: 5px;">
                                                                        <i class="fa-solid fa-triangle-exclamation"></i> En <?php echo $ins['nombre_periodo'] ?: 'Periodo ID: ' . $ins['periodo_id']; ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div style="display: flex; gap: 5px; align-items:center;">
                                                        <?php if ($es_periodo_incorrecto): ?>
                                                            <form method="POST">
                                                                <input type="hidden" name="matricula_id" value="<?php echo $ins['mat_id']; ?>">
                                                                <button type="submit" name="actualizar_periodo" value="1" class="btn" style="color: #f59e0b; padding: 5px; font-size: 0.9rem;" title="Mover al Periodo Actual">
                                                                    <i class="fa-solid fa-repeat"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <form method="POST">
                                                            <input type="hidden" name="matricula_id" value="<?php echo $ins['mat_id']; ?>">
                                                            <button type="submit" name="remover" value="1" class="btn" style="color: #fb7185; padding: 5px; font-size: 0.9rem;" title="Remover Estudiante">
                                                                <i class="fa-solid fa-user-xmark"></i>
                                                            </button>
                                                        </form>
                                                        <a href="editar_notas.php?materia=<?php echo $mid; ?>#mat-<?php echo $ins['mat_id']; ?>" class="btn btn-outline" style="padding:5px 8px; font-size:0.85rem; border-color: rgba(99,102,241,0.3); color: var(--primary);" title="Calificar">
                                                            <i class="fa-solid fa-pen-to-square"></i>
                                                        </a>
                                                        <a href="asistencia.php?student=<?php echo urlencode($ins['nombre']); ?>" class="btn btn-outline" style="padding:5px 8px; font-size:0.85rem; border-color: rgba(16,185,129,0.3); color: #10b981;" title="Pasar asistencia">
                                                            <i class="fa-solid fa-clipboard-user"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php endwhile;
                                        else: ?>
                                            <p class="text-muted" style="font-size: 0.85rem; text-align: center; padding: 20px;">No hay alumnos inscritos en ningún periodo.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <button type="button" onclick="mostrarAsignar(<?php echo $mid; ?>)" class="btn btn-outline" style="width: 100%; margin-top: 20px;">Cerrar Panel de Gestión</button>
                        </div>
                    </div>
                <?php endforeach; ?>
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

        function mostrarAsignar(id) {
            var el = document.getElementById('form-asignar-' + id);
            if (el.style.display === 'none') {
                el.style.display = 'block';
            } else {
                el.style.display = 'none';
            }
        }

        // Filtrado dinámico de estudiantes (Trello)
        function filterStudents(mid) {
            var input = document.getElementById('search-' + mid);
            var filter = input.value.toUpperCase();
            var list = document.getElementById('list-' + mid);
            var items = list.getElementsByClassName('student-item');

            for (var i = 0; i < items.length; i++) {
                var text = items[i].innerText.toUpperCase();
                if (text.indexOf(filter) > -1) {
                    items[i].style.display = "";
                } else {
                    items[i].style.display = "none";
                }
            }
        }

        // Auto-filtro si llega ?student= en la URL
        const highlightStudent = "<?php echo addslashes($highlight_student); ?>";
        const mids = [<?php echo implode(',', array_column($materias_list, 'id')); ?>];
        if (highlightStudent) {
            mids.forEach(mid => {
                const input = document.getElementById('search-' + mid);
                if (input) {
                    input.value = highlightStudent;
                    filterStudents(mid);
                }
            });
            if (location.hash !== '#inscribir') {
                location.hash = '#inscribir';
            }
        }
    </script>
</body>

</html>
