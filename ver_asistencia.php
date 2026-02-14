<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('estudiante');

$estudiante_id = $_SESSION['usuario_id'];
$nombre_estudiante = obtener_nombre_usuario();

// Obtener resumen de asistencia por materia
$sql_resumen = "SELECT m.nombre, m.codigo,
                SUM(CASE WHEN a.estado = 'Presente' THEN 1 ELSE 0 END) as presentes,
                SUM(CASE WHEN a.estado = 'Ausente' THEN 1 ELSE 0 END) as ausentes,
                SUM(CASE WHEN a.estado = 'Justificado' THEN 1 ELSE 0 END) as justificados,
                COUNT(a.id) as total_clases
                FROM matriculas mat
                JOIN materias m ON mat.materia_id = m.id
                LEFT JOIN asistencia a ON mat.id = a.matricula_id
                WHERE mat.estudiante_id = $estudiante_id
                GROUP BY m.id";
$res_resumen = $conn->query($sql_resumen);

// Obtener detalle de asistencia si se selecciona una materia
$materia_id_filtro = isset($_GET['materia']) ? (int)$_GET['materia'] : null;
$detalle = null;
if ($materia_id_filtro) {
    $sql_detalle = "SELECT a.fecha, a.estado 
                    FROM asistencia a
                    JOIN matriculas m ON a.matricula_id = m.id
                    WHERE m.estudiante_id = $estudiante_id AND m.materia_id = $materia_id_filtro
                    ORDER BY a.fecha DESC";
    $detalle = $conn->query($sql_detalle);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistencia | Unicali</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .asistencia-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-presente {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .badge-ausente {
            background: rgba(244, 63, 94, 0.1);
            color: #f43f5e;
        }

        .badge-justificado {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-bar {
            height: 100%;
            background: var(--primary);
            border-radius: 10px;
            transition: width 0.5s ease;
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
                <h3 style="color: white; margin-top: 10px;">Unicali<span style="color: var(--primary);">Estudiante</span></h3>
            </div>
            <nav>
                <a href="dashboard_estudiante.php" class="nav-link">
                    <i class="fa-solid fa-house"></i> Inicio
                </a>
                <a href="generar_documento.php?tipo=estudio" target="_blank" class="nav-link" style="color: #fbbf24; font-weight: 700;">
                    <i class="fa-solid fa-certificate"></i> Certificado Oficial
                </a>
                <a href="ver_asistencia.php" class="nav-link active">
                    <i class="fa-solid fa-calendar-check"></i> Mis Asistencias
                </a>
                <a href="ver_notas.php" class="nav-link">
                    <i class="fa-solid fa-chart-line"></i> Mis Notas
                </a>
                <a href="historial.php" class="nav-link">
                    <i class="fa-solid fa-receipt"></i> Historial Académico
                </a>
                <a href="observaciones.php" class="nav-link">
                    <i class="fa-solid fa-comment-dots"></i> Observaciones
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
            <header style="margin-bottom: 40px;">
                <h1 class="text-gradient">Control de Asistencia</h1>
                <p class="text-muted">Consulta tu puntualidad y cumplimiento por asignatura.</p>
            </header>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
                <?php if ($res_resumen && $res_resumen->num_rows > 0): while ($row = $res_resumen->fetch_assoc()):
                        $pct = ($row['total_clases'] > 0) ? round(($row['presentes'] / $row['total_clases']) * 100) : 0;
                ?>
                        <div class="card glass-panel fade-in">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                                <div>
                                    <h3 style="font-size: 1.1rem;"><?php echo htmlspecialchars($row['nombre']); ?></h3>
                                    <p class="text-muted" style="font-size: 0.8rem;"><?php echo htmlspecialchars($row['codigo']); ?></p>
                                </div>
                                <div style="text-align: right;">
                                    <span style="font-size: 1.5rem; font-weight: 800; color: <?php echo $pct >= 80 ? '#10b981' : ($pct >= 60 ? '#f59e0b' : '#f43f5e'); ?>"><?php echo $pct; ?>%</span>
                                    <p class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 700;">Asistencia</p>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center; background: rgba(255,255,255,0.03); border-radius: 12px; padding: 15px;">
                                <div>
                                    <span style="display: block; font-size: 1.2rem; font-weight: 700; color: #10b981;"><?php echo $row['presentes']; ?></span>
                                    <span style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase;">Presencias</span>
                                </div>
                                <div>
                                    <span style="display: block; font-size: 1.2rem; font-weight: 700; color: #f43f5e;"><?php echo $row['ausentes']; ?></span>
                                    <span style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase;">Faltas</span>
                                </div>
                                <div>
                                    <span style="display: block; font-size: 1.2rem; font-weight: 700; color: #f59e0b;"><?php echo $row['justificados']; ?></span>
                                    <span style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase;">Justif.</span>
                                </div>
                            </div>

                            <div class="progress-bar-container">
                                <div class="progress-bar" style="width: <?php echo $pct; ?>%; background: <?php echo $pct >= 80 ? '#10b981' : ($pct >= 60 ? '#f59e0b' : '#f43f5e'); ?>"></div>
                            </div>
                        </div>
                    <?php endwhile;
                else: ?>
                    <div class="card glass-panel" style="grid-column: 1/-1; text-align: center; padding: 60px;">
                        <i class="fa-solid fa-calendar-xmark" style="font-size: 3rem; opacity: 0.1; margin-bottom: 20px; display: block;"></i>
                        <p class="text-muted">Aún no se han registrado asistencias para tus materias.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($detalle && $detalle->num_rows > 0): ?>
                <!-- Aquí podrías expandir para ver fechas exactas si el usuario elige una materia -->
            <?php endif; ?>
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
</body>

</html>