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

// Promedio evolutivo por periodo academico (ponderado por corte)
// Usa LEFT JOIN para no perder matrículas sin periodo asignado
$sql_periodos = "SELECT 
    COALESCE(p.nombre, 'Sin periodo asignado') AS periodo,
    COALESCE(p.fecha_inicio, '1900-01-01') AS fecha_inicio,
    ROUND(AVG(COALESCE(mp.promedio,0)),2) AS promedio_periodo,
    COUNT(*) AS cursos
FROM (
    SELECT mat.id AS matricula_id, mat.periodo_id,
           SUM(COALESCE(n.valor,0) * CASE
                WHEN n.corte='Corte 1' THEN 0.2
                WHEN n.corte='Corte 2' THEN 0.2
                WHEN n.corte='Corte 3' THEN 0.2
                WHEN n.corte='Examen Final' THEN 0.3
                WHEN n.corte='Seguimiento' THEN 0.1
                ELSE 0 END) AS promedio
    FROM matriculas mat
    LEFT JOIN notas n ON n.matricula_id = mat.id
    WHERE mat.estudiante_id = $estudiante_id
    GROUP BY mat.id, mat.periodo_id
) mp
LEFT JOIN periodos p ON p.id = mp.periodo_id
GROUP BY p.id, p.nombre, p.fecha_inicio
ORDER BY COALESCE(p.fecha_inicio, '1900-01-01') ASC";

$res_periodos = $conn->query($sql_periodos);
$periodo_labels = [];
$periodo_data = [];
$periodo_cursos = [];
$hay_periodos = false;
if ($res_periodos && $res_periodos->num_rows > 0) {
    while ($row = $res_periodos->fetch_assoc()) {
        $periodo_labels[] = $row['periodo'];
        $periodo_data[] = (float)$row['promedio_periodo'];
        $periodo_cursos[] = (int)$row['cursos'];
        $hay_periodos = true;
    }
} else {
    // Fallback para mostrar mensaje en la grafica
    $periodo_labels = ['Sin datos'];
    $periodo_data = [0];
    $periodo_cursos = [0];
}
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
                <h3 style="margin-bottom: 20px;"><i class="fa-solid fa-chart-line" style="color: var(--primary);"></i> Evolución por Período</h3>
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
        (async function () {
            const labels = <?php echo json_encode($periodo_labels); ?>;
            const data = <?php echo json_encode($periodo_data); ?>;
            const cursos = <?php echo json_encode($periodo_cursos); ?>;
            const hasData = <?php echo $hay_periodos ? 'true' : 'false'; ?>;

            const container = document.getElementById('progresoChart').parentElement;
            const canvas = document.getElementById('progresoChart');

            // Cargar Chart.js con doble CDN de respaldo
            async function ensureChart() {
                if (typeof Chart !== 'undefined') return;
                const urls = [
                    'https://cdn.jsdelivr.net/npm/chart.js',
                    'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js'
                ];
                for (const url of urls) {
                    try {
                        await new Promise((resolve, reject) => {
                            const s = document.createElement('script');
                            s.src = url;
                            s.onload = () => resolve();
                            s.onerror = () => reject();
                            document.head.appendChild(s);
                        });
                        if (typeof Chart !== 'undefined') return;
                    } catch (e) {
                        continue;
                    }
                }
                throw new Error('Chart.js no disponible');
            }

            try {
                await ensureChart();
            } catch (err) {
                const msg = document.createElement('div');
                msg.style.cssText = "color:#94a3b8;font-weight:600;text-align:center;padding:20px;";
                msg.textContent = "No se pudo cargar el gráfico (Chart.js no disponible). Revisa la conexión o habilita el CDN.";
                container.appendChild(msg);
                console.error(err);
                return;
            }

            if (!hasData || !labels.length) {
                const msg = document.createElement('div');
                msg.style.cssText = "color:#94a3b8;font-weight:600;text-align:center;padding:12px;";
                msg.textContent = "Sin datos de periodos. Asigna periodo a tus matrículas o registra notas.";
                container.appendChild(msg);
            }

            const ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels.length ? labels : ['Sin datos'],
                    datasets: [{
                        label: 'Promedio por periodo',
                        data: data.length ? data : [0],
                        borderColor: '#34d399',
                        backgroundColor: 'rgba(52, 211, 153, 0.12)',
                        borderWidth: 3,
                        tension: 0.35,
                        fill: true,
                        pointBackgroundColor: '#0f172a',
                        pointBorderColor: '#34d399',
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                afterLabel: function (context) {
                                    return 'Cursos en periodo: ' + (cursos[context.dataIndex] || 0);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            suggestedMax: 5,
                            max: 5,
                            grid: { color: 'rgba(255,255,255,0.05)' },
                            ticks: { color: '#94a3b8' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#94a3b8' }
                        }
                    }
                }
            });

            console.debug('Periodos labels/data:', labels, data, 'cursos:', cursos);
        })();
    </script>
</body>

</html>
