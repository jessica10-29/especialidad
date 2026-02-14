<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('profesor');

$profesor_id = $_SESSION['usuario_id'];
$nombre_profesor = obtener_nombre_usuario();
$foto_profesor = obtener_foto_usuario($_SESSION['foto'] ?? null);
$mensaje_flash = '';
$periodo_actual_id = obtener_periodo_actual();
$tiene_columna_periodo = false;
$res_col = $conn->query("SHOW COLUMNS FROM matriculas LIKE 'periodo'");
if ($res_col && $res_col->num_rows > 0) {
    $tiene_columna_periodo = true;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['inscribir_rapido'])) {
    $est_id = (int)($_POST['estudiante_id'] ?? 0);
    $materia_id = (int)($_POST['materia_id'] ?? 0);

    // Validar que la materia pertenezca al profesor
    $own = $conn->query("SELECT nombre, codigo FROM materias WHERE id = $materia_id AND profesor_id = $profesor_id");
    if (!$own || $own->num_rows === 0) {
        $mensaje_flash = '<div class=\"alert alert-danger\" style=\"background: rgba(244, 63, 94, 0.12); color: #fb7185; padding: 10px 14px; border-radius: 10px; margin-bottom: 15px; border:1px solid rgba(244,63,94,0.2);\">No puedes inscribir en una materia que no te pertenece.</div>';
    } else {
        $periodo_nom = '';
        $res_p = $conn->query("SELECT nombre FROM periodos WHERE id = $periodo_actual_id");
        if ($res_p && $rowp = $res_p->fetch_assoc()) {
            $periodo_nom = $rowp['nombre'];
        }

        // Evitar duplicados en el periodo vigente
        $dup = $conn->query("SELECT id FROM matriculas WHERE estudiante_id = $est_id AND materia_id = $materia_id AND periodo_id = $periodo_actual_id");
        if ($dup && $dup->num_rows > 0) {
            $mensaje_flash = '<div class=\"alert alert-warning\" style=\"background: rgba(245, 158, 11, 0.12); color: #f59e0b; padding: 10px 14px; border-radius: 10px; margin-bottom: 15px; border:1px solid rgba(245,158,11,0.25);\">El estudiante ya está inscrito en esta materia en el periodo actual.</div>';
        } else {
            if ($tiene_columna_periodo) {
                $ok = $conn->query("INSERT INTO matriculas (estudiante_id, materia_id, periodo, periodo_id) VALUES ($est_id, $materia_id, '$periodo_nom', $periodo_actual_id)");
            } else {
                $ok = $conn->query("INSERT INTO matriculas (estudiante_id, materia_id, periodo_id) VALUES ($est_id, $materia_id, $periodo_actual_id)");
            }
            if ($ok) {
                $mensaje_flash = '<div class=\"alert alert-success\" style=\"background: rgba(16, 185, 129, 0.12); color: #22c55e; padding: 10px 14px; border-radius: 10px; margin-bottom: 15px; border:1px solid rgba(16,185,129,0.25);\">Estudiante inscrito en la materia y listo para calificar.</div>';
            } else {
                $mensaje_flash = '<div class=\"alert alert-danger\" style=\"background: rgba(244, 63, 94, 0.12); color: #fb7185; padding: 10px 14px; border-radius: 10px; margin-bottom: 15px; border:1px solid rgba(244,63,94,0.2);\">Error al inscribir: ' . htmlspecialchars($conn->error) . '</div>';
            }
        }
    }
}

$sql_materias = "SELECT COUNT(*) as total FROM materias WHERE profesor_id = $profesor_id";
$res_materias = $conn->query($sql_materias);
$total_materias = $res_materias->fetch_assoc()['total'];
$materias_profesor = [];
$res_lista_m = $conn->query("SELECT id, nombre, codigo FROM materias WHERE profesor_id = $profesor_id ORDER BY nombre");
if ($res_lista_m) {
    while ($m = $res_lista_m->fetch_assoc()) {
        $materias_profesor[] = $m;
    }
}

$sql_alumnos = "SELECT COUNT(DISTINCT estudiante_id) as total 
                FROM matriculas m 
                JOIN materias mat ON m.materia_id = mat.id 
                WHERE mat.profesor_id = $profesor_id";
$res_alumnos = $conn->query($sql_alumnos);
$total_alumnos = ($res_alumnos) ? $res_alumnos->fetch_assoc()['total'] : 0;

$estudiantes_pendientes = $conn->query("
    SELECT u.id, u.nombre, u.email, u.programa_academico, u.semestre, u.foto, u.created_at
    FROM usuarios u
    WHERE u.rol = 'estudiante'
      AND u.id NOT IN (
        SELECT DISTINCT m.estudiante_id
        FROM matriculas m
        JOIN materias mat ON m.materia_id = mat.id
        WHERE mat.profesor_id = $profesor_id
      )
    ORDER BY u.created_at DESC
    LIMIT 10
");

$estudiantes = $conn->query("
    SELECT u.id, u.nombre, u.email, u.programa_academico, u.semestre, u.foto,
           COUNT(DISTINCT m.materia_id) AS cursos,
           ROUND(AVG(m.promedio),2) AS promedio,
           SUM(CASE WHEN a.estado IN ('Presente','Tardanza') THEN 1 ELSE 0 END) AS asistio,
           COUNT(a.id) AS total_asist
    FROM usuarios u
    JOIN matriculas m ON u.id = m.estudiante_id
    JOIN materias mat ON m.materia_id = mat.id
    LEFT JOIN asistencia a ON a.matricula_id = m.id
    WHERE mat.profesor_id = $profesor_id
    GROUP BY u.id, u.nombre, u.email, u.programa_academico, u.semestre, u.foto
    ORDER BY u.nombre ASC
    LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Docente - Unicali Segura</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="icon" type="image/png" href="favicon.png?v=3">
    <link rel="shortcut icon" href="favicon.ico?v=3">
    <link rel="apple-touch-icon" href="favicon.png?v=3">
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
                <h3 style="color: white; margin-top: 10px;">Unicali Segura<span style="color: var(--primary);">Docente</span></h3>
            </div>

            <nav>
                <a href="dashboard_profesor.php" class="nav-link active">
                    <i class="fa-solid fa-house"></i> Inicio
                </a>
                <a href="gestion_materias.php" class="nav-link">
                    <i class="fa-solid fa-book"></i> Mis Materias
                </a>
                <a href="gestion_notas.php" class="nav-link">
                    <i class="fa-solid fa-user-pen"></i> Gestionar Notas
                </a>
                <a href="asistencia.php" class="nav-link">
                    <i class="fa-solid fa-clipboard-user"></i> Asistencia
                </a>
                <a href="generar_documento.php?tipo=estudio" target="_blank" class="nav-link" style="color: #fbbf24; font-weight: 700;">
                    <i class="fa-solid fa-certificate"></i> Certificado Oficial
                </a>
                <a href="perfil.php" class="nav-link">
                    <i class="fa-solid fa-gear"></i> Configuración
                </a>
                <a href="logout.php" class="nav-link" style="margin-top: auto; color: var(--danger);">
                    <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <div>
                    <h1 class="text-gradient">Hola, Profe. <?php echo htmlspecialchars($nombre_profesor); ?></h1>
                    <p class="text-muted">Resumen de tu actividad académica</p>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: url('<?php echo htmlspecialchars($foto_profesor); ?>') center/cover; border: 2px solid var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.15);"></div>
                </div>
            </header>
            <?php echo $mensaje_flash; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="card stat-card glass-panel">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                        <i class="fa-solid fa-book-open"></i>
                    </div>
                    <div>
                        <h3 style="font-size: 2rem;"><?php echo $total_materias; ?></h3>
                        <p class="text-muted">Materias Activas</p>
                    </div>
                </div>

                <div class="card stat-card glass-panel">
                    <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--info);">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <h3 style="font-size: 2rem;"><?php echo $total_alumnos; ?></h3>
                        <p class="text-muted">Estudiantes Totales</p>
                    </div>
                </div>
            </div>

            <h2 style="margin-bottom: 20px;">Acciones Rápidas</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <a href="gestion_notas.php" class="card glass-panel" style="text-decoration: none; color: inherit; text-align: center;">
                    <i class="fa-solid fa-pen-to-square" style="font-size: 2.5rem; color: var(--accent); margin-bottom: 15px;"></i>
                    <h4>Subir Notas</h4>
                    <p class="text-muted" style="font-size: 0.8rem;">Calificar cortes y parciales</p>
                </a>

                <a href="gestion_notas.php#inscribir" class="card glass-panel" style="text-decoration: none; color: inherit; text-align: center;">
                    <i class="fa-solid fa-user-plus" style="font-size: 2.5rem; color: #22c55e; margin-bottom: 15px;"></i>
                    <h4>Inscribir Estudiantes</h4>
                    <p class="text-muted" style="font-size: 0.8rem;">Asigna alumnos a tus materias</p>
                </a>

                <a href="crear_materia.php" class="card glass-panel" style="text-decoration: none; color: inherit; text-align: center;">
                    <i class="fa-solid fa-plus-circle" style="font-size: 2.5rem; color: var(--primary); margin-bottom: 15px;"></i>
                    <h4>Nueva Materia</h4>
                    <p class="text-muted" style="font-size: 0.8rem;">Crear curso académico</p>
                </a>

                <a href="generar_documento.php?tipo=estudio" target="_blank" class="card glass-panel" style="text-decoration: none; color: inherit; text-align: center; border: 1px solid rgba(251, 191, 36, 0.3);">
                    <i class="fa-solid fa-certificate" style="font-size: 2.5rem; color: #fbbf24; margin-bottom: 15px;"></i>
                    <h4>Certificado</h4>
                    <p class="text-muted" style="font-size: 0.8rem;">Descargar documento oficial</p>
                </a>
            </div>

            <!-- Estudiantes registrados sin inscribir en tus materias -->
            <div style="margin-top: 30px;">
                <h2 style="margin-bottom: 12px;">Estudiantes recién registrados (sin asignar)</h2>
                <?php if ($estudiantes_pendientes && $estudiantes_pendientes->num_rows > 0): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px;">
                        <?php while ($p = $estudiantes_pendientes->fetch_assoc()):
                            $foto_p = obtener_foto_usuario($p['foto']);
                        ?>
                            <div class="card glass-panel" style="display:flex; gap:10px; align-items:center; padding:12px;">
                                <img src="<?php echo htmlspecialchars($foto_p); ?>" alt="avatar" style="width:48px; height:48px; border-radius:50%; object-fit:cover; border:1px solid rgba(255,255,255,0.08);">
                                <div style="flex:1;">
                                    <div style="font-weight:700;"><?php echo htmlspecialchars($p['nombre']); ?></div>
                                    <div class="text-muted" style="font-size:0.82rem;"><?php echo htmlspecialchars($p['email']); ?></div>
                                    <div style="display:flex; gap:6px; flex-wrap:wrap; font-size:0.75rem; margin-top:4px;">
                                        <?php if (!empty($p['programa_academico'])): ?>
                                            <span class="badge" style="background: rgba(99,102,241,0.12); color: var(--primary); border:1px solid rgba(99,102,241,0.18); padding:2px 6px; border-radius:6px;"><?php echo htmlspecialchars($p['programa_academico']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($p['semestre'])): ?>
                                            <span class="badge" style="background: rgba(16,185,129,0.12); color: #22c55e; border:1px solid rgba(16,185,129,0.18); padding:2px 6px; border-radius:6px;">Sem <?php echo htmlspecialchars($p['semestre']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (count($materias_profesor) > 0): ?>
                                    <form method="POST" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                                        <input type="hidden" name="inscribir_rapido" value="1">
                                        <input type="hidden" name="estudiante_id" value="<?php echo (int)$p['id']; ?>">
                                        <select name="materia_id" class="input-field" style="flex:1; min-width:140px; padding:6px 8px; background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.08); border-radius:8px;">
                                            <?php foreach ($materias_profesor as $mat_op): ?>
                                                <option value="<?php echo $mat_op['id']; ?>"><?php echo htmlspecialchars($mat_op['nombre']); ?> (<?php echo htmlspecialchars($mat_op['codigo']); ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-outline" style="font-size:0.78rem; padding:6px 10px;">Inscribir</button>
                                    </form>
                                <?php else: ?>
                                    <div style="font-size:0.78rem; color:#94a3b8;">Crea una materia para inscribir.</div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="card glass-panel" style="padding: 12px;">No hay estudiantes nuevos pendientes de asignar.</div>
                <?php endif; ?>
            </div>

            <div style="margin-top: 35px;">
                <h2 style="margin-bottom: 15px;">Mis estudiantes</h2>
                <?php if ($estudiantes && $estudiantes->num_rows > 0): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
                        <?php while ($e = $estudiantes->fetch_assoc()):
                            $foto = obtener_foto_usuario($e['foto']);
                            $prom = is_null($e['promedio']) ? '–' : number_format((float)$e['promedio'], 2);
                            $totalAsist = (int)$e['total_asist'];
                            $asistio = (int)$e['asistio'];
                            $pctAsist = $totalAsist > 0 ? round(($asistio / $totalAsist) * 100) : 100;
                        ?>
                            <div class="card glass-panel fade-in" style="display: flex; gap: 12px; align-items: center; padding: 14px;">
                                <img src="<?php echo htmlspecialchars($foto); ?>" alt="avatar" style="width: 56px; height: 56px; border-radius: 12px; object-fit: cover; border: 1px solid rgba(255,255,255,0.08);">
                                <div style="flex:1;">
                                    <div style="font-weight: 700;"><?php echo htmlspecialchars($e['nombre']); ?></div>
                                    <div class="text-muted" style="font-size: 0.82rem;"><?php echo htmlspecialchars($e['email']); ?></div>
                                    <div style="display:flex; gap:8px; flex-wrap: wrap; margin-top:6px; font-size: 0.78rem;">
                                        <?php if (!empty($e['programa_academico'])): ?>
                                            <span class="badge" style="background: rgba(99,102,241,0.12); color: var(--primary); border:1px solid rgba(99,102,241,0.25); padding:3px 8px; border-radius: 8px;"><?php echo htmlspecialchars($e['programa_academico']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($e['semestre'])): ?>
                                            <span class="badge" style="background: rgba(16,185,129,0.12); color: #22c55e; border:1px solid rgba(16,185,129,0.25); padding:3px 8px; border-radius: 8px;">Sem <?php echo htmlspecialchars($e['semestre']); ?></span>
                                        <?php endif; ?>
                                        <span class="badge" style="background: rgba(251,191,36,0.12); color: #f59e0b; border:1px solid rgba(251,191,36,0.25); padding:3px 8px; border-radius: 8px;"><?php echo (int)$e['cursos']; ?> materias contigo</span>
                                    </div>
                                    <div style="margin-top:8px; display:grid; grid-template-columns: 1fr 1fr; gap:8px; font-size:0.78rem;">
                                        <div style="background: rgba(99,102,241,0.07); border:1px solid rgba(99,102,241,0.15); border-radius:8px; padding:6px 8px;">
                                            <div style="color: var(--primary); font-weight:700;">Promedio</div>
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <span style="font-weight:800; font-size:0.95rem;"><?php echo $prom; ?></span>
                                                <?php if ($prom !== '–'): ?>
                                                    <span class="badge" style="background: <?php echo ($prom>=3?'rgba(16,185,129,.12)':'rgba(244,63,94,.12)'); ?>; color: <?php echo ($prom>=3?'#22c55e':'#f87171'); ?>; border:1px solid rgba(255,255,255,0.08);">/5</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div style="background: rgba(16,185,129,0.07); border:1px solid rgba(16,185,129,0.12); border-radius:8px; padding:6px 8px;">
                                            <div style="color: #10b981; font-weight:700;">Asistencia</div>
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <span style="font-weight:800; font-size:0.95rem;"><?php echo $pctAsist; ?>%</span>
                                                <div style="flex:1; height:8px; background: rgba(255,255,255,0.08); border-radius:999px; overflow:hidden;">
                                                    <div style="width: <?php echo $pctAsist; ?>%; height:100%; background: linear-gradient(90deg, #22c55e, #10b981);"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display:flex; gap:10px; margin-top:10px; flex-wrap:wrap;">
                                        <a href="gestion_notas.php?student=<?php echo urlencode($e['nombre']); ?>" class="btn btn-outline" style="font-size:0.78rem; padding:8px 12px; border-color: rgba(99,102,241,0.35); color: var(--primary);">
                                            <i class="fa-solid fa-pen-to-square"></i> Calificar
                                        </a>
                                        <a href="asistencia.php?student=<?php echo urlencode($e['nombre']); ?>" class="btn btn-outline" style="font-size:0.78rem; padding:8px 12px; border-color: rgba(16,185,129,0.35); color: #10b981;">
                                            <i class="fa-solid fa-clipboard-user"></i> Asistencia
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="card glass-panel" style="padding: 18px;">Aún no tienes estudiantes inscritos en tus materias.</div>
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
    </script>
    <a href="generar_documento.php?tipo=estudio" target="_blank" class="fab-cert" title="Descargar Certificado de Estudio">
        <i class="fa-solid fa-file-pdf"></i>
    </a>
</body>

</html>
