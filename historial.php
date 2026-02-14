<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('estudiante');

$estudiante_id = $_SESSION['usuario_id'];

// Obtener todas las materias y calcular promedios con los nuevos pesos
$sql = "SELECT m.nombre, m.codigo, 
        (SELECT SUM(valor * CASE 
            WHEN corte='Corte 1' THEN 0.2 
            WHEN corte='Corte 2' THEN 0.2 
            WHEN corte='Corte 3' THEN 0.2 
            WHEN corte='Examen Final' THEN 0.3 
            WHEN corte='Seguimiento' THEN 0.1 
            ELSE 0 END) 
         FROM notas n 
         JOIN matriculas mat ON n.matricula_id = mat.id 
         WHERE mat.materia_id = m.id AND mat.estudiante_id = $estudiante_id) as promedio
        FROM materias m
        JOIN matriculas mat ON m.id = mat.materia_id
        WHERE mat.estudiante_id = $estudiante_id";

$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial Académico - Unicali</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
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
                <a href="ver_asistencia.php" class="nav-link">
                    <i class="fa-solid fa-calendar-check"></i> Mis Asistencias
                </a>
                <a href="ver_notas.php" class="nav-link">
                    <i class="fa-solid fa-chart-line"></i> Mis Notas
                </a>
                <a href="historial.php" class="nav-link active">
                    <i class="fa-solid fa-receipt"></i> Historial Académico
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
                    <h1 class="text-gradient">Mi Trayectoria Académica</h1>
                    <p class="text-muted">Evolución de calificaciones y promedio general acumulado</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button onclick="window.print()" class="btn btn-outline">
                        <i class="fa-solid fa-print"></i> Imprimir
                    </button>
                    <a href="generar_documento.php?tipo=estudio" target="_blank" class="btn btn-primary" style="background: #fbbf24; color: #1e293b; border: none;">
                        <i class="fa-solid fa-certificate"></i> Certificado Oficial (Matrícula)
                    </a>
                    <a href="pdf.php" target="_blank" class="btn btn-outline">
                        <i class="fa-solid fa-file-pdf"></i> Reporte de Notas
                    </a>
                </div>
            </header>

            <!-- Gráfico de Evolución (Trello) -->
            <div class="card glass-panel fade-in" style="margin-bottom: 30px; padding: 25px;">
                <h3 style="margin-bottom: 20px;"><i class="fa-solid fa-chart-line" style="color: var(--primary);"></i> Evolución de Promedio</h3>
                <div style="height: 300px;">
                    <canvas id="progresoChart"></canvas>
                </div>
            </div>

            <div class="card glass-panel fade-in" style="padding: 0; overflow: hidden;">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Asignatura</th>
                                <th>Promedio Final</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($res && $res->num_rows > 0): while ($row = $res->fetch_assoc()):
                                    $prom = number_format((float)$row['promedio'], 1);
                                    $aprobado = $prom >= 3;
                            ?>
                                    <tr>
                                        <td><code style="background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 4px; font-size: 0.8rem;"><?php echo htmlspecialchars($row['codigo']); ?></code></td>
                                        <td style="font-weight: 500;"><?php echo htmlspecialchars($row['nombre']); ?></td>
                                        <td>
                                            <span style="font-weight: 700; color: <?php echo $aprobado ? '#34d399' : '#fb7185'; ?>;">
                                                <?php echo $prom; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="background: <?php echo $aprobado ? 'rgba(52, 211, 153, 0.1)' : 'rgba(244, 63, 94, 0.1)'; ?>; 
                                                 color: <?php echo $aprobado ? '#34d399' : '#fb7185'; ?>; 
                                                 padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">
                                                <?php echo $aprobado ? 'Aprobado' : 'Reprobado'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile;
                            else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 40px;">
                                        <i class="fa-solid fa-box-open" style="font-size: 2rem; opacity: 0.2; display: block; margin-bottom: 10px;"></i>
                                        <p class="text-muted">No tienes materias inscritas para mostrar en el historial.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    <script>
        const ctx = document.getElementById('progresoChart').getContext('2d');

        <?php
        $labels = [];
        $data = [];
        $res->data_seek(0);
        while ($row = $res->fetch_assoc()) {
            $labels[] = $row['nombre'];
            $data[] = number_format((float)$row['promedio'], 1);
        }
        ?>

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Promedio por Materia',
                    data: <?php echo json_encode($data); ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#6366f1',
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 5,
                        grid: {
                            color: 'rgba(255,255,255,0.05)'
                        },
                        ticks: {
                            color: '#94a3b8'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#94a3b8'
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>