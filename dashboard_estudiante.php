<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('estudiante');

$estudiante_id = $_SESSION['usuario_id'];
$nombre_estudiante = obtener_nombre_usuario();

// Obtener Periodo Actual de forma segura (Self-Healing)
$p_actual_id = obtener_periodo_actual();

$sql_matriculas = "SELECT m.nombre, m.codigo, u.nombre as profesor, mat.promedio 
                   FROM matriculas mat 
                   JOIN materias m ON mat.materia_id = m.id 
                   JOIN usuarios u ON m.profesor_id = u.id 
                   WHERE mat.estudiante_id = $estudiante_id AND mat.periodo_id = $p_actual_id";
$res_matriculas = $conn->query($sql_matriculas);

// Calcular Promedio General Acumulado (Trello)
$sql_general = "SELECT AVG(promedio) as promedio_general FROM matriculas WHERE estudiante_id = $estudiante_id";
$res_general = $conn->query($sql_general);
$prom_general_val = 0;
if ($res_general && $row_gen = $res_general->fetch_assoc()) {
    $prom_general_val = number_format((float)$row_gen['promedio_general'], 1);
}

// Obtener datos para tarjetas de resumen
$total_materias = $res_matriculas->num_rows;
$res_ganando = $conn->query("SELECT COUNT(*) as count FROM matriculas WHERE estudiante_id = $estudiante_id AND promedio >= 3.0");
$materias_ganando = $res_ganando->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial Académico - Unicali</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="icon" type="image/png" href="favicon.png?v=3">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <h3 style="color: white; margin-top: 10px;">Unicali<span style="color: var(--primary);">Estudiante</span></h3>
            </div>
            <nav>
                <a href="dashboard_estudiante.php" class="nav-link active">
                    <i class="fa-solid fa-house"></i> Inicio
                </a>
                <a href="generar_documento.php?tipo=estudio" target="_blank" class="nav-link" style="color: #fbbf24; font-weight: 700;">
                    <i class="fa-solid fa-certificate"></i> Certificado Oficial
                </a>
                <a href="ver_asistencia.php" class="nav-link">
                    <i class="fa-solid fa-calendar-check"></i> Mis Asistencias
                </a>
                <a href="ver_notas.php" class="nav-link">
                    <i class="fa-solid fa-chart-line"></i> Mis Notas
                </a>
                <a href="historial.php" class="nav-link">
                    <i class="fa-solid fa-receipt"></i> Historial Académico
                </a>
                <a href="historial.php" class="nav-link">
                    <i class="fa-solid fa-receipt"></i> Historial Pago
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
            <!-- 🏆 SECCIÓN DE CERTIFICADOS SUPREMA (IMPULSADA) -->
            <!-- 🏆 SECCIÓN DE CERTIFICADOS SUPREMA (IMPULSADA) -->
            <div class="card glass-panel fade-in responsive-banner-card" style="background: linear-gradient(135deg, rgba(251, 191, 36, 0.25) 0%, rgba(180, 83, 9, 0.15) 100%); border: 4px solid #fbbf24; margin-bottom: 40px; padding: 40px; position: relative; overflow: hidden; box-shadow: 0 0 60px rgba(251, 191, 36, 0.3);">
                <div class="responsive-hidden-icon" style="position: absolute; top: -30px; right: -30px; font-size: 15rem; color: rgba(251, 191, 36, 0.1); transform: rotate(15deg); pointer-events: none;">
                    <i class="fa-solid fa-scroll"></i>
                </div>
                <div class="responsive-flex-column" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 30px; position: relative; z-index: 1;">
                    <div style="flex: 1; min-width: 300px;">
                        <span style="background: #fbbf24; color: #1e293b; padding: 6px 20px; border-radius: 50px; font-size: 0.8rem; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; display: inline-block; margin-bottom: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.3);">¡NUEVO! Trámite Disponible</span>
                        <h2 class="responsive-text-lg" style="color: #fbbf24; margin-bottom: 15px; font-size: 2.5rem; font-family: 'Space Grotesk', sans-serif; text-shadow: 0 2px 10px rgba(0,0,0,0.5);">Certificado de Estudio</h2>
                        <p class="responsive-text-md" style="color: white; font-size: 1.2rem; line-height: 1.6; opacity: 1; font-weight: 500;">
                            Descarga tu certificación oficial de matrícula con código QR y firma digital.
                        </p>
                    </div>
                    <div class="responsive-btn-container" style="text-align: center;">
                        <a href="generar_documento.php?tipo=estudio" target="_blank" class="btn" style="background: #fbbf24; color: #1e293b; padding: 25px 50px; font-size: 1.4rem; font-weight: 950; border-radius: 20px; box-shadow: 0 20px 40px rgba(251, 191, 36, 0.5); text-transform: uppercase; letter-spacing: 1px; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); border: none; display: flex; align-items: center; gap: 15px; cursor: pointer;">
                            <i class="fa-solid fa-file-pdf" style="font-size: 2rem;"></i> GENERAR CERTIFICADO
                        </a>
                        <p style="margin-top: 15px; font-size: 0.9rem; color: #fbbf24; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Documento PDF Válido</p>
                    </div>
                </div>
            </div>

            <header style="margin-bottom: 35px;">
                <div class="responsive-header" style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <h1 class="text-gradient responsive-text-xl" style="font-size: 2.2rem;">Hola, <?php echo explode(' ', htmlspecialchars($nombre_estudiante))[0]; ?> 👋</h1>
                        <p class="text-muted">Estado actual de tu formación académica en Unicali.</p>
                    </div>
                    <div class="responsive-btn-header" style="display: flex; gap: 10px;">
                        <a href="pdf.php" target="_blank" class="btn btn-outline" style="background: rgba(255,255,255,0.05);">
                            <i class="fa-solid fa-file-pdf"></i> Reporte de Notas
                        </a>
                    </div>
                </div>
            </header>

            <!-- Grid de Estadísticas (Trello: Resumen de promedio) -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 40px;">
                <div class="card glass-panel fade-in" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(167, 139, 250, 0.1) 100%); border: 1px solid rgba(99, 102, 241, 0.2);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <p class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Promedio General</p>
                            <h2 style="font-size: 2.5rem; margin: 10px 0; color: <?php echo $prom_general_val >= 3 ? '#34d399' : '#fb7185'; ?>;"><?php echo $prom_general_val; ?></h2>
                            <p style="font-size: 0.75rem; opacity: 0.6;"><i class="fa-solid fa-arrow-trend-up"></i> Rendimiento Actual</p>
                        </div>
                        <div style="width: 60px; height: 60px; background: rgba(99, 102, 241, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-star-half-stroke" style="color: var(--primary); font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>

                <div class="card glass-panel fade-in">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <p class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Materias Activas</p>
                            <h2 style="font-size: 2rem; margin: 10px 0;"><?php echo $total_materias; ?></h2>
                            <p style="font-size: 0.75rem; color: #34d399;"><i class="fa-solid fa-circle-check"></i> <?php echo $materias_ganando; ?> Aprobadas</p>
                        </div>
                        <div style="width: 60px; height: 60px; background: rgba(16, 185, 129, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-book-open-reader" style="color: #34d399; font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>

                <div class="card glass-panel fade-in">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <p class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Puntualidad</p>
                            <h2 style="font-size: 2rem; margin: 10px 0;">95%</h2>
                            <p style="font-size: 0.75rem; opacity: 0.6;"><i class="fa-solid fa-clock"></i> Último mes</p>
                        </div>

                    </div>
                    <div style="width: 60px; height: 60px; background: rgba(6, 182, 212, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-calendar-check" style="color: #06b6d4; font-size: 1.5rem;"></i>
                    </div>
                </div>
            </div>

            <div class="responsive-layout-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: start;">

                <div>
                    <h3 style="margin-bottom: 20px;"><i class="fa-solid fa-bookmark" style="color: var(--primary);"></i> Mis Materias Inscritas (Progreso)</h3>
                    <div style="display: grid; grid-template-columns: 1fr; gap: 15px;">
                        <?php
                        $res_matriculas->data_seek(0);
                        if ($res_matriculas && $res_matriculas->num_rows > 0) {
                            while ($row = $res_matriculas->fetch_assoc()) {
                                // Calcular progreso visual: Cuantas notas tiene de 5 posibles
                                $mid = $row['codigo']; // Usamos codigo o ID
                                // Necesitamos el ID real de matricula para contar notas
                                $res_m_id = $conn->query("SELECT id FROM matriculas WHERE estudiante_id = $estudiante_id AND materia_id = (SELECT id FROM materias WHERE codigo = '" . $row['codigo'] . "')");
                                $m_id_real = ($res_m_id && $res_m_id->num_rows > 0) ? $res_m_id->fetch_assoc()['id'] : 0;

                                $num_notas = 0;
                                if ($m_id_real > 0) {
                                    $q_count = $conn->query("SELECT COUNT(*) as count FROM notas WHERE matricula_id = $m_id_real");
                                    $num_notas = $q_count->fetch_assoc()['count'];
                                }
                                $progreso = ($num_notas / 5) * 100;

                                echo '<div class="card glass-panel fade-in" style="padding: 20px;">';
                                echo '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">';
                                echo '<div>';
                                echo '<span style="font-size: 0.7rem; font-weight: 700; color: var(--primary); background: rgba(99,102,241,0.1); padding: 3px 8px; border-radius: 4px; text-transform: uppercase;">' . htmlspecialchars($row['codigo']) . '</span>';
                                echo '<h4 style="margin-top: 5px; font-size: 1.1rem;">' . htmlspecialchars($row['nombre']) . '</h4>';
                                echo '<p class="text-muted" style="font-size: 0.75rem;">Docente: ' . htmlspecialchars($row['profesor']) . '</p>';
                                echo '</div>';
                                echo '<div style="text-align: right;">';
                                echo '<span style="font-size: 1.2rem; font-weight: 800; color: ' . ($row['promedio'] >= 3 ? '#34d399' : '#fb7185') . ';">' . number_format($row['promedio'], 1) . '</span>';
                                echo '<p style="font-size: 0.6rem; opacity: 0.5; margin-top: -3px;">Nota Actual</p>';
                                echo '</div>';
                                echo '</div>';

                                // Trello: Progreso por materia
                                echo '<div style="margin-bottom: 10px;">';
                                echo '<div style="display: flex; justify-content: space-between; font-size: 0.75rem; margin-bottom: 5px;">';
                                echo '<span>Avance del Curso</span>';
                                echo '<span>' . round($progreso) . '%</span>';
                                echo '</div>';
                                echo '<div style="height: 6px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden;">';
                                echo '<div style="width: ' . $progreso . '%; height: 100%; background: var(--primary); transition: width 1s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 0 10px var(--primary);"></div>';
                                echo '</div>';
                                echo '</div>';

                                echo '<div style="display: flex; gap: 10px; margin-top: 15px;">';
                                echo '<a href="ver_notas.php?materia=' . urlencode($row['nombre']) . '" class="btn btn-outline" style="flex: 1; font-size: 0.75rem; padding: 8px;">Ver Detalle</a>';
                                echo '<a href="pdf.php?materia=' . urlencode($row['nombre']) . '" target="_blank" class="btn btn-outline" style="width: 40px; padding: 0; display: flex; align-items: center; justify-content: center;"><i class="fa-solid fa-file-pdf"></i></a>';
                                echo '</div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="card glass-panel" style="text-align: center; padding: 40px;">';
                            echo '<p class="text-muted">No tienes materias inscritas.</p>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Barra Lateral Derecha: Acciones Rápidas (Trello: Acceso rápido) -->
                <div>
                    <h3 style="margin-bottom: 20px;"><i class="fa-solid fa-bolt" style="color: #fbbf24;"></i> Herramientas</h3>

                    <!-- 👤 Tarjeta de Perfil (MOVIDA AL PRINCIPIO) -->
                    <div class="card glass-panel fade-in" style="margin-bottom: 20px; text-align: center; padding: 25px; border: 1px solid rgba(99, 102, 241, 0.2);">
                        <div style="width: 80px; height: 80px; margin: 0 auto 15px; background: url('<?php echo $_SESSION['foto'] ?? 'default_avatar.png'; ?>'); background-size: cover; border-radius: 50%; border: 3px solid var(--primary);"></div>
                        <h4><?php echo htmlspecialchars($nombre_estudiante); ?></h4>
                        <p class="text-muted" style="font-size: 0.75rem; color: var(--primary);">Estudiante de Pregrado</p>
                        <a href="perfil.php" class="btn btn-primary" style="margin-top: 15px; width: 100%; font-size: 0.8rem; height: 40px; justify-content: center; font-weight: 700;">
                            <i class="fa-solid fa-user-pen" style="margin-right: 8px;"></i> Editar Perfil
                        </a>
                    </div>

                    <a href="generar_documento.php?tipo=estudio" target="_blank" class="card glass-panel fade-in" style="display: flex; gap: 15px; align-items: center; text-decoration: none; padding: 20px; transition: transform 0.2s; border: 1px solid rgba(251, 191, 36, 0.3);">
                        <div style="background: rgba(251, 191, 36, 0.1); width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-certificate" style="color: #fbbf24;"></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 2px;">Certificado de Estudio</h4>
                            <p class="text-muted" style="font-size: 0.7rem;">Documento oficial de matrícula.</p>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="font-size: 0.8rem; opacity: 0.3;"></i>
                    </a>

                    <a href="historial.php" class="card glass-panel fade-in" style="display: flex; gap: 15px; align-items: center; text-decoration: none; padding: 20px; margin-top: 15px; transition: transform 0.2s;">
                        <div style="background: rgba(99, 102, 241, 0.1); width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-receipt" style="color: var(--primary);"></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 2px;">Historial Académico</h4>
                            <p class="text-muted" style="font-size: 0.7rem;">Certificados y periodos previos.</p>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="font-size: 0.8rem; opacity: 0.3;"></i>
                    </a>

                    <a href="observaciones.php" class="card glass-panel fade-in" style="display: flex; gap: 15px; align-items: center; text-decoration: none; padding: 20px; margin-top: 15px; transition: transform 0.2s;">
                        <div style="background: rgba(6, 182, 212, 0.1); width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-comment-medical" style="color: #06b6d4;"></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 0.9rem; margin-bottom: 2px;">Observaciones Docentes</h4>
                            <p class="text-muted" style="font-size: 0.7rem;">Feedback de tus profesores.</p>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="font-size: 0.8rem; opacity: 0.3;"></i>
                    </a>
                </div>
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