<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('admin');

$mensaje = '';

// Procesar Formulario de Creación/Edición
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['guardar_periodo'])) {
        $nombre = limpiar_dato($_POST['nombre']);
        $inicio = $_POST['fecha_inicio'];
        $fin = $_POST['fecha_fin'];
        $limite = $_POST['limite_notas'];
        $estado = $_POST['estado'];

        // Validación de superposición (Trello)
        $check = $conn->query("SELECT id FROM periodos WHERE estado = 'activo' AND (
            ('$inicio' BETWEEN fecha_inicio AND fecha_fin) OR 
            ('$fin' BETWEEN fecha_inicio AND fecha_fin)
        )");

        if ($check->num_rows > 0 && !isset($_POST['id'])) {
            $mensaje = '<div class="alert alert-danger">⚠️ Error: Las fechas se superponen con un periodo activo existente.</div>';
        } else {
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $id = (int)$_POST['id'];
                $stmt = $conn->prepare("UPDATE periodos SET nombre=?, fecha_inicio=?, fecha_fin=?, limite_notas=?, estado=? WHERE id=?");
                $stmt->bind_param("sssssi", $nombre, $inicio, $fin, $limite, $estado, $id);
            } else {
                $stmt = $conn->prepare("INSERT INTO periodos (nombre, fecha_inicio, fecha_fin, limite_notas, estado) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $nombre, $inicio, $fin, $limite, $estado);
            }

            if ($stmt->execute()) {
                $mensaje = '<div class="alert alert-success">✅ Periodo guardado correctamente.</div>';
            }
        }
    }

    if (isset($_POST['set_actual'])) {
        $id = (int)$_POST['id'];
        $conn->query("UPDATE configuracion SET valor = '$id' WHERE clave = 'periodo_actual_id'");
        $mensaje = '<div class="alert alert-success">✅ Periodo actual actualizado correctamente.</div>';
    }
}

// Obtener Periodo Actual ID de forma segura (Self-Healing)
$periodo_actual_id = obtener_periodo_actual();

$periodos = $conn->query("SELECT * FROM periodos ORDER BY fecha_inicio DESC");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Configuración de Periodos - Universidad</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-danger {
            background: rgba(244, 63, 94, 0.1);
            color: #fb7185;
            border: 1px solid rgba(244, 63, 114, 0.2);
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-activo {
            background: rgba(52, 211, 153, 0.1);
            color: #34d399;
        }

        .status-inactivo {
            background: rgba(244, 63, 94, 0.1);
            color: #fb7185;
        }
    </style>
</head>

<body>
    <div class="background-mesh"></div>
    <div class="dashboard-grid">
        <aside class="sidebar">
            <div class="logo-area" style="text-align: center; margin-bottom: 30px;">
                <i class="fa-solid fa-calendar-days" style="font-size: 2.5rem; color: var(--primary);"></i>
                <h3 style="color: white; margin-top: 10px;">Admin<span style="color: var(--primary);">Periodos</span></h3>
            </div>
            <nav>
                <a href="dashboard_admin.php" class="nav-link"><i class="fa-solid fa-house"></i> Inicio</a>
                <a href="admin_materias.php" class="nav-link"><i class="fa-solid fa-book"></i> Materias</a>
                <a href="admin_periodos.php" class="nav-link active"><i class="fa-solid fa-clock"></i> Periodos</a>
                <a href="logout.php" class="nav-link" style="margin-top:auto;"><i class="fa-solid fa-power-off"></i> Salir</a>
            </nav>
        </aside>

        <main class="main-content">
            <header style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 class="text-gradient">Periodos Académicos</h1>
                    <p class="text-muted">Configura semestres, trimestres y fechas límite de notas.</p>
                </div>
                <button onclick="nuevoPeriodo()" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Nuevo Periodo
                </button>
            </header>

            <?php echo $mensaje; ?>

            <div id="form-periodo" class="card glass-panel fade-in" style="display: none; margin-bottom: 30px;">
                <h3 id="form-title" style="margin-bottom: 20px;">Crear Nuevo Periodo</h3>
                <form method="POST">
                    <input type="hidden" name="id" id="p-id">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="input-group">
                            <label class="input-label">Nombre del Periodo</label>
                            <input type="text" name="nombre" id="p-nombre" class="input-field" placeholder="Ej: Semestre 1 - 2024" required>
                        </div>
                        <div class="input-group">
                            <label class="input-label">Estado</label>
                            <select name="estado" id="p-estado" class="input-field">
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label class="input-label">Fecha Inicio</label>
                            <input type="date" name="fecha_inicio" id="p-inicio" class="input-field" required>
                        </div>
                        <div class="input-group">
                            <label class="input-label">Fecha Fin</label>
                            <input type="date" name="fecha_fin" id="p-fin" class="input-field" required>
                        </div>
                        <div class="input-group" style="grid-column: span 2;">
                            <label class="input-label">Fecha Límite para Notas (Opcional)</label>
                            <input type="date" name="limite_notas" id="p-limite" class="input-field">
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                        <button type="submit" name="guardar_periodo" class="btn btn-primary">Guardar Configuración</button>
                        <button type="button" onclick="cerrarForm()" class="btn btn-outline">Cancelar</button>
                    </div>
                </form>
            </div>

            <div class="card glass-panel fade-in" style="padding: 0; overflow: hidden;">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Periodo</th>
                                <th>Rango de Fechas</th>
                                <th>Límite Notas</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($p = $periodos->fetch_assoc()):
                                $es_actual = ($p['id'] == $periodo_actual_id);
                            ?>
                                <tr style="<?php echo $es_actual ? 'background: rgba(99, 102, 241, 0.05);' : ''; ?>">
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <strong><?php echo htmlspecialchars($p['nombre']); ?></strong>
                                            <?php if ($es_actual): ?>
                                                <span class="badge" style="background: var(--primary); font-size: 0.6rem;">ACTUAL</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-muted">
                                        <?php echo date('d M, Y', strtotime($p['fecha_inicio'])); ?> -
                                        <?php echo date('d M, Y', strtotime($p['fecha_fin'])); ?>
                                    </td>
                                    <td>
                                        <?php echo $p['limite_notas'] ? date('d M, Y', strtotime($p['limite_notas'])) : '<span class="text-muted">No definida</span>'; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $p['estado']; ?>">
                                            <?php echo $p['estado']; ?>
                                        </span>
                                    </td>
                                    <td style="display: flex; gap: 8px;">
                                        <button onclick='editarPeriodo(<?php echo json_encode($p); ?>)' class="btn" style="padding: 8px; background: rgba(255,255,255,0.05);">
                                            <i class="fa-solid fa-edit" style="color: var(--primary);"></i>
                                        </button>

                                        <?php if (!$es_actual): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                <button type="submit" name="set_actual" class="btn" title="Definir como periodo actual" style="padding: 8px; background: rgba(255,255,255,0.05);">
                                                    <i class="fa-solid fa-star" style="color: #fbbf24;"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <input type="hidden" name="nuevo_estado" value="<?php echo $p['estado'] == 'activo' ? 'inactivo' : 'activo'; ?>">
                                            <button type="submit" name="cambiar_estado" class="btn" style="padding: 8px; background: rgba(255,255,255,0.05);">
                                                <i class="fa-solid fa-power-off" style="color: <?php echo $p['estado'] == 'activo' ? '#fb7185' : '#34d399'; ?>;"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        function nuevoPeriodo() {
            document.getElementById('form-periodo').style.display = 'block';
            document.getElementById('form-title').innerText = 'Crear Nuevo Periodo';
            document.getElementById('p-id').value = '';
            document.getElementById('p-nombre').value = '';
            document.getElementById('p-inicio').value = '';
            document.getElementById('p-fin').value = '';
            document.getElementById('p-limite').value = '';
            document.getElementById('p-estado').value = 'activo';
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        function editarPeriodo(p) {
            document.getElementById('form-periodo').style.display = 'block';
            document.getElementById('form-title').innerText = 'Editar Periodo: ' + p.nombre;
            document.getElementById('p-id').value = p.id;
            document.getElementById('p-nombre').value = p.nombre;
            document.getElementById('p-inicio').value = p.fecha_inicio;
            document.getElementById('p-fin').value = p.fecha_fin;
            document.getElementById('p-limite').value = p.limite_notas;
            document.getElementById('p-estado').value = p.estado;
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        function cerrarForm() {
            document.getElementById('form-periodo').style.display = 'none';
        }
    </script>
</body>

</html>