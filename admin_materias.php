<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('admin');

$mensaje = '';

// Procesar Formulario de Materias (Trello Card 9)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['guardar_materia'])) {
        $nombre = limpiar_dato($_POST['nombre']);
        $codigo = limpiar_dato($_POST['codigo']);
        $descripcion = limpiar_dato($_POST['descripcion']);
        $profesor_id = (int)$_POST['profesor_id'];
        $estado = $_POST['estado'];

        // Validación de código único
        $check = $conn->query("SELECT id FROM materias WHERE codigo = '$codigo'" . (isset($_POST['id']) ? " AND id != " . (int)$_POST['id'] : ""));

        if ($check->num_rows > 0) {
            $mensaje = '<div class="alert alert-danger">⚠️ Error: El código de materia ya existe.</div>';
        } else {
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $id = (int)$_POST['id'];
                $stmt = $conn->prepare("UPDATE materias SET nombre=?, codigo=?, descripcion=?, profesor_id=?, estado=? WHERE id=?");
                $stmt->bind_param("sssisi", $nombre, $codigo, $descripcion, $profesor_id, $estado, $id);
            } else {
                $stmt = $conn->prepare("INSERT INTO materias (nombre, codigo, descripcion, profesor_id, estado) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssis", $nombre, $codigo, $descripcion, $profesor_id, $estado);
            }

            if ($stmt->execute()) {
                $mensaje = '<div class="alert alert-success">✅ Materia actualizada correctamente.</div>';
            }
        }
    }

    if (isset($_POST['eliminar_materia'])) {
        $id = (int)$_POST['id'];
        // Eliminación lógica por seguridad
        $conn->query("UPDATE materias SET estado = 'inactivo' WHERE id = $id");
        $mensaje = '<div class="alert alert-danger">Materia desactivada correctamente.</div>';
    }
}

$profesores = $conn->query("SELECT id, nombre FROM usuarios WHERE rol = 'profesor' ORDER BY nombre ASC");
$materias = $conn->query("SELECT m.*, u.nombre as profesor_nombre FROM materias m LEFT JOIN usuarios u ON m.profesor_id = u.id ORDER BY m.nombre ASC");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Administración de Materias - Universidad</title>
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
            font-size: 0.7rem;
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
                <i class="fa-solid fa-graduation-cap" style="font-size: 2.5rem; color: var(--primary);"></i>
                <h3 style="color: white; margin-top: 10px;">Unicali<span style="color: var(--primary);">Gestión</span></h3>
            </div>
            <nav>
                <a href="dashboard_admin.php" class="nav-link"><i class="fa-solid fa-house"></i> Inicio</a>
                <a href="admin_materias.php" class="nav-link active"><i class="fa-solid fa-book"></i> Materias</a>
                <a href="admin_periodos.php" class="nav-link"><i class="fa-solid fa-clock"></i> Periodos</a>
                <a href="logout.php" class="nav-link" style="margin-top:auto;"><i class="fa-solid fa-power-off"></i> Salir</a>
            </nav>
        </aside>

        <main class="main-content">
            <header style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 class="text-gradient">Oferta Académica</h1>
                    <p class="text-muted">Crea, edita y asigna responsables a los cursos de la universidad.</p>
                </div>
                <button onclick="nuevaMateria()" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Crear Materia
                </button>
            </header>

            <?php echo $mensaje; ?>

            <div id="form-materia" class="card glass-panel fade-in" style="display: none; margin-bottom: 30px;">
                <h3 id="form-title" style="margin-bottom: 20px;">Nueva Materia</h3>
                <form method="POST">
                    <input type="hidden" name="id" id="m-id">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="input-group">
                            <label class="input-label">Nombre de la Materia</label>
                            <input type="text" name="nombre" id="m-nombre" class="input-field" placeholder="Ej: Cálculo Integral" required>
                        </div>
                        <div class="input-group">
                            <label class="input-label">Código Único</label>
                            <input type="text" name="codigo" id="m-codigo" class="input-field" placeholder="Ej: MAT-101" required>
                        </div>
                        <div class="input-group">
                            <label class="input-label">Docente Responsable</label>
                            <select name="profesor_id" id="m-profe" class="input-field" required>
                                <option value="">-- Seleccionar Docente --</option>
                                <?php while ($prof = $profesores->fetch_assoc()): ?>
                                    <option value="<?php echo $prof['id']; ?>"><?php echo htmlspecialchars($prof['nombre']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label class="input-label">Estado</label>
                            <select name="estado" id="m-estado" class="input-field">
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                        <div class="input-group" style="grid-column: span 2;">
                            <label class="input-label">Descripción</label>
                            <textarea name="descripcion" id="m-desc" class="input-field" rows="3" placeholder="Breve descripción del curso..."></textarea>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                        <button type="submit" name="guardar_materia" class="btn btn-primary">Guardar Materia</button>
                        <button type="button" onclick="cerrarForm()" class="btn btn-outline">Cancelar</button>
                    </div>
                </form>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                <?php while ($m = $materias->fetch_assoc()): ?>
                    <div class="card glass-panel fade-in" style="display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                                <span class="badge" style="background: rgba(99, 102, 241, 0.1); color: var(--primary);"><?php echo htmlspecialchars($m['codigo']); ?></span>
                                <span class="status-badge status-<?php echo $m['estado']; ?>"><?php echo $m['estado']; ?></span>
                            </div>
                            <h3 style="margin-bottom: 10px;"><?php echo htmlspecialchars($m['nombre']); ?></h3>
                            <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 15px;">
                                <i class="fa-solid fa-chalkboard-user" style="color: var(--primary); margin-right: 5px;"></i>
                                <?php echo htmlspecialchars($m['profesor_nombre'] ?: 'Sin asignar'); ?>
                            </p>
                            <p style="font-size: 0.8rem; opacity: 0.7; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 20px;">
                                <?php echo htmlspecialchars($m['descripcion'] ?: 'Sin descripción disponible.'); ?>
                            </p>
                        </div>
                        <div style="display: flex; gap: 10px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 15px;">
                            <button onclick='editarMateria(<?php echo json_encode($m); ?>)' class="btn btn-outline" style="flex: 1; font-size: 0.8rem;">
                                <i class="fa-solid fa-edit"></i> Editar
                            </button>
                            <form method="POST" onsubmit="return confirm('¿Seguro que deseas desactivar esta materia?')" style="flex: 1;">
                                <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                <button type="submit" name="eliminar_materia" class="btn" style="width: 100%; font-size: 0.8rem; background: rgba(244, 63, 94, 0.05); color: #fb7185; border: 1px solid rgba(244,63,114,0.1);">
                                    <i class="fa-solid fa-trash-can"></i> Desactivar
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </main>
    </div>

    <script>
        function nuevaMateria() {
            document.getElementById('form-materia').style.display = 'block';
            document.getElementById('form-title').innerText = 'Crear Nueva Materia';
            document.getElementById('m-id').value = '';
            document.getElementById('m-nombre').value = '';
            document.getElementById('m-codigo').value = '';
            document.getElementById('m-desc').value = '';
            document.getElementById('m-profe').value = '';
            document.getElementById('m-estado').value = 'activo';
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        function editarMateria(m) {
            document.getElementById('form-materia').style.display = 'block';
            document.getElementById('form-title').innerText = 'Editar Materia: ' + m.nombre;
            document.getElementById('m-id').value = m.id;
            document.getElementById('m-nombre').value = m.nombre;
            document.getElementById('m-codigo').value = m.codigo;
            document.getElementById('m-desc').value = m.descripcion;
            document.getElementById('m-profe').value = m.profesor_id ? m.profesor_id : '';
            document.getElementById('m-estado').value = m.estado;
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        function cerrarForm() {
            document.getElementById('form-materia').style.display = 'none';
        }
    </script>
</body>

</html>