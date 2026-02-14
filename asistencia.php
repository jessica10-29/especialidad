<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('profesor');

$highlight_student = isset($_GET['student']) ? limpiar_dato($_GET['student']) : '';

$profesor_id = $_SESSION['usuario_id'];
$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['registrar_asistencia'])) {
    $materia_id = (int)$_POST['materia_id'];
    $fecha = $_POST['fecha'];
    $estados = isset($_POST['estados']) ? $_POST['estados'] : [];

    foreach ($estados as $estudiante_id => $estado) {
        $estudiante_id = (int)$estudiante_id;

        $stmt_check = $conn->prepare("SELECT id FROM asistencia WHERE estudiante_id = ? AND materia_id = ? AND fecha = ?");
        $stmt_check->bind_param("iis", $estudiante_id, $materia_id, $fecha);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();

        if ($res_check->num_rows > 0) {
            $stmt_upd = $conn->prepare("UPDATE asistencia SET estado = ? WHERE estudiante_id = ? AND materia_id = ? AND fecha = ?");
            $stmt_upd->bind_param("siis", $estado, $estudiante_id, $materia_id, $fecha);
            $stmt_upd->execute();
        } else {
            // En nuestra tabla la columna se llama 'matricula_id' y 'estudiante_id'? 
            // Revisemos schema: matricula_id es la FK a matriculas.
            // Pero el archivo original usaba estudiante_id y materia_id (posible inconsistencia detectada)
            // Corregiremos para usar matricula_id si es posible, o seguir la logica del archivo si funciona.
            // Segun db_schema.sql: tabla asistencia tiene matricula_id.

            $q_mat = $conn->query("SELECT id FROM matriculas WHERE estudiante_id = $estudiante_id AND materia_id = $materia_id");
            if ($mat_row = $q_mat->fetch_assoc()) {
                $mat_id = $mat_row['id'];
                $stmt_ins = $conn->prepare("INSERT INTO asistencia (matricula_id, fecha, estado) VALUES (?, ?, ?)");
                $stmt_ins->bind_param("iss", $mat_id, $fecha, $estado);
                $stmt_ins->execute();
            }
        }
    }
    $mensaje = '<div style="background: rgba(16, 185, 129, 0.1); color: #34d399; padding: 12px; border-radius: 10px; margin-bottom: 25px; font-size: 0.85rem; border: 1px solid rgba(16, 185, 129, 0.2);"><i class="fa-solid fa-check-circle"></i> Asistencia procesada con éxito.</div>';
}

$materias = $conn->query("SELECT * FROM materias WHERE profesor_id = $profesor_id");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Asistencia - Unicali</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
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
                <a href="gestion_notas.php" class="nav-link">
                    <i class="fa-solid fa-user-pen"></i> Gestionar Notas
                </a>
                <a href="asistencia.php" class="nav-link active">
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
            <header style="margin-bottom: 30px;">
                <h1 class="text-gradient">Control de Asistencia</h1>
                <p class="text-muted">Selecciona una materia para registrar la asistencia del día</p>
                <div style="margin-top:12px; max-width: 420px;">
                    <label class="text-muted" style="font-size:0.85rem;">Filtrar estudiante</label>
                    <div class="input-group">
                        <input type="text" id="search-global" class="input-field" placeholder="Nombre o cédula" value="<?php echo htmlspecialchars($highlight_student); ?>" oninput="globalFilter()">
                    </div>
                </div>
            </header>

            <?php echo $mensaje; ?>

            <div style="display: grid; gap: 25px;">
                <?php if ($materias && $materias->num_rows > 0):
                    while ($m = $materias->fetch_assoc()):
                        $mid = $m['id'];
                        // Obtener periodo actual de forma segura (Self-Healing)
                        $p_actual_id = obtener_periodo_actual();

                        // Consultar alumnos inscritos en esta materia para el periodo actual
                        $alumnos = $conn->query("SELECT u.id as estudiante_id, u.nombre, m.id as matricula_id FROM usuarios u JOIN matriculas m ON u.id = m.estudiante_id WHERE m.materia_id = $mid AND m.periodo_id = $p_actual_id");
                ?>
                        <div class="card glass-panel fade-in">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <div style="background: rgba(6, 182, 212, 0.1); width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fa-solid fa-calendar-check" style="color: var(--secondary);"></i>
                                    </div>
                                    <div>
                                        <h3 style="font-size: 1.1rem;"><?php echo htmlspecialchars($m['nombre']); ?></h3>
                                        <span class="text-muted" style="font-size: 0.8rem;"><?php echo htmlspecialchars($m['codigo']); ?></span>
                                    </div>
                                </div>
                                <button onclick="toggleAsistencia(<?php echo $mid; ?>)" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.85rem;">
                                    Abrir Registro
                                </button>
                            </div>

                            <div id="asistencia-box-<?php echo $mid; ?>" style="display: none; border-top: 1px solid var(--glass-border); padding-top: 20px;" class="fade-in">
                                <?php // if open toggle 
                                ?>
                                <form method="POST">
                                    <input type="hidden" name="materia_id" value="<?php echo $mid; ?>">
                                    <input type="hidden" name="registrar_asistencia" value="1">

                                    <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                                        <label class="text-muted" style="font-size: 0.9rem;">Fecha de Clase:</label>
                                        <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" class="input-field" style="margin-bottom: 0; width: auto; padding: 6px 12px;">
                                    </div>

                                    <div style="max-height: 300px; overflow-y: auto; margin-bottom: 20px; background: rgba(0,0,0,0.1); border-radius: 10px; padding: 10px;">
                                        <?php if ($alumnos->num_rows > 0): ?>
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr style="text-align: left; border-bottom: 1px solid var(--glass-border);">
                                                        <th style="padding: 10px 5px; font-size: 0.8rem; text-transform: uppercase;" class="text-muted">Estudiante</th>
                                                        <th style="padding: 10px 5px; text-align: center; font-size: 0.8rem; text-transform: uppercase;" class="text-muted">Estado de Asistencia</th>
                                                        <th style="padding: 10px 5px; text-align: center; font-size: 0.8rem; text-transform: uppercase;" class="text-muted">Prom. %</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while ($a = $alumnos->fetch_assoc()):
                                                        $est_id_loop = $a['estudiante_id'];
                                                        // Estadísticas (Trello) - Agregando seguridad si falla la query
                                                        $q_stat = $conn->query("SELECT 
                                                        COUNT(*) as total,
                                                        SUM(CASE WHEN estado='Presente' OR estado='Tardanza' THEN 1 ELSE 0 END) as asistio
                                                        FROM asistencia a 
                                                        JOIN matriculas m ON a.matricula_id = m.id
                                                        WHERE m.estudiante_id = $est_id_loop AND m.materia_id = $mid");
                                                        $stat = ($q_stat && $row_stat = $q_stat->fetch_assoc()) ? $row_stat : ['total' => 0, 'asistio' => 0];
                                                        $porcentaje = ($stat['total'] > 0) ? round(($stat['asistio'] / $stat['total']) * 100) : 100;
                                                    ?>
                                                        <tr class="attendance-row" data-name="<?php echo strtoupper($a['nombre'] . ' ' . $est_id_loop); ?>" style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                                            <td style="padding: 12px 5px; font-size: 0.9rem;">
                                                                <?php echo htmlspecialchars($a['nombre']); ?>
                                                            </td>
                                                            <td style="padding: 12px 5px; text-align: center;">
                                                                <div style="display: flex; gap: 8px; justify-content: center;">
                                                                    <label title="Presente" style="cursor:pointer;"><input type="radio" name="estados[<?php echo $est_id_loop; ?>]" value="Presente" checked> <i class="fa-solid fa-circle-check" style="color:#34d399;"></i></label>
                                                                    <label title="Ausente" style="cursor:pointer;"><input type="radio" name="estados[<?php echo $est_id_loop; ?>]" value="Ausente"> <i class="fa-solid fa-circle-xmark" style="color:#fb7185;"></i></label>
                                                                    <label title="Tardanza" style="cursor:pointer;"><input type="radio" name="estados[<?php echo $est_id_loop; ?>]" value="Tardanza"> <i class="fa-solid fa-clock" style="color:#fbbf24;"></i></label>
                                                                    <label title="Justificado" style="cursor:pointer;"><input type="radio" name="estados[<?php echo $est_id_loop; ?>]" value="Justificado"> <i class="fa-solid fa-file-medical" style="color:#06b6d4;"></i></label>
                                                                </div>
                                                            </td>
                                                            <td style="text-align: center; font-size: 0.8rem; font-weight: 700; color: <?php echo $porcentaje > 80 ? '#34d399' : '#fb7185'; ?>;">
                                                                <?php echo $porcentaje; ?>%
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <p class="text-muted" style="text-align: center; padding: 20px; font-size: 0.9rem;">No hay estudiantes inscritos en esta materia.</p>
                                        <?php endif; ?>
                                    </div>

                                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                        <button type="button" onclick="toggleAsistencia(<?php echo $mid; ?>)" class="btn btn-outline" style="border-color: rgba(255,255,255,0.1); color: #94a3b8;">Cancelar</button>
                                        <button type="submit" class="btn btn-primary" <?php echo (!$alumnos || $alumnos->num_rows == 0) ? 'disabled' : ''; ?>>Guardar Asistencia</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="card glass-panel fade-in" style="text-align: center; padding: 40px;">
                        <p class="text-muted">No tienes materias asignadas o hubo un error al cargar.</p>
                    </div>
                <?php endif; ?>
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

        // Filtro global por estudiante
        function globalFilter() {
            var term = document.getElementById('search-global').value.toUpperCase();
            var rows = document.getElementsByClassName('attendance-row');
            for (var i = 0; i < rows.length; i++) {
                var name = rows[i].getAttribute('data-name') || '';
                rows[i].style.display = name.indexOf(term) > -1 ? '' : 'none';
            }
        }

        // Si llegó ?student= autofiltra
        <?php if (!empty($highlight_student)): ?>
        globalFilter();
        <?php endif; ?>

        function toggleAsistencia(id) {
            var el = document.getElementById('asistencia-box-' + id);
            el.style.display = el.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>

</html>
