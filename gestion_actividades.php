<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('profesor');

$profesor_id = $_SESSION['usuario_id'];
$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['crear_actividad'])) {
        $materia_id = (int)$_POST['materia_id'];
        $titulo = limpiar_dato($_POST['titulo']);
        $desc = limpiar_dato($_POST['descripcion']);
        $fecha = $_POST['fecha_limite'];

        $stmt = $conn->prepare("INSERT INTO actividades (materia_id, titulo, descripcion, fecha_limite) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $materia_id, $titulo, $desc, $fecha);
        if ($stmt->execute()) {
            $mensaje = '<div class="alert-success" style="background: rgba(16, 185, 129, 0.1); color: #34d399; padding: 12px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(16, 185, 129, 0.2);"><i class="fa-solid fa-check-circle"></i> Actividad creada con éxito.</div>';
        } else {
            $mensaje = '<div class="alert-error" style="background: rgba(244, 63, 94, 0.1); color: #fb7185; padding: 12px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(244, 63, 114, 0.2);"><i class="fa-solid fa-circle-exclamation"></i> Error: ' . $conn->error . '</div>';
        }
    }

    if (isset($_POST['eliminar_actividad'])) {
        $id = (int)$_POST['actividad_id'];
        $conn->query("DELETE FROM actividades WHERE id = $id AND materia_id IN (SELECT id FROM materias WHERE profesor_id = $profesor_id)");
        $mensaje = '<div class="alert-success" style="background: rgba(16, 185, 129, 0.1); color: #34d399; padding: 12px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(16, 185, 129, 0.2);"><i class="fa-solid fa-trash"></i> Actividad eliminada.</div>';
    }
}

$materias = $conn->query("SELECT id, nombre, codigo FROM materias WHERE profesor_id = $profesor_id");
$materias_list = [];
while ($m = $materias->fetch_assoc()) {
    $materias_list[] = $m;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Actividades - Docentes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
    <div class="background-mesh"></div>
    <div class="dashboard-grid">
        <aside class="sidebar">
            <div class="logo-area" style="text-align: center; margin-bottom: 40px;">
                <i class="fa-solid fa-graduation-cap logo-icon" style="font-size: 2rem; color: var(--primary);"></i>
                <h3 style="color: white; margin-top: 10px;">Unicali<span style="color: var(--primary);">Docente</span></h3>
            </div>
            <nav>
                <a href="dashboard_profesor.php" class="nav-link"><i class="fa-solid fa-house"></i> Inicio</a>
                <a href="gestion_notas.php" class="nav-link"><i class="fa-solid fa-user-pen"></i> Gestión Notas</a>
                <a href="gestion_actividades.php" class="nav-link active"><i class="fa-solid fa-tasks"></i> Actividades</a>
                <a href="logout.php" class="nav-link" style="margin-top: auto; color: #f43f5e;"><i class="fa-solid fa-right-from-bracket"></i> Salir</a>
            </nav>
        </aside>

        <main class="main-content">
            <header style="margin-bottom: 30px;">
                <h1 class="text-gradient">Gestión de Actividades</h1>
                <p class="text-muted">Asigna trabajos y revisa entregas de tus estudiantes</p>
            </header>

            <?php echo $mensaje; ?>

            <div class="card glass-panel fade-in" style="margin-bottom: 30px;">
                <h3>Crear Nueva Actividad</h3>
                <form method="POST" style="margin-top: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="input-group">
                            <label class="input-label">Materia</label>
                            <select name="materia_id" class="input-field" required>
                                <?php foreach ($materias_list as $m): ?>
                                    <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label class="input-label">Fecha Límite</label>
                            <input type="datetime-local" name="fecha_limite" class="input-field" required>
                        </div>
                    </div>
                    <div class="input-group">
                        <label class="input-label">Título de la Actividad</label>
                        <input type="text" name="titulo" class="input-field" placeholder="Ej: Taller de Algoritmos" required>
                    </div>
                    <div class="input-group">
                        <label class="input-label">Instrucciones</label>
                        <textarea name="descripcion" class="input-field" rows="3" placeholder="Describe los requisitos del trabajo..."></textarea>
                    </div>
                    <button type="submit" name="crear_actividad" class="btn btn-primary">Publicar Actividad</button>
                </form>
            </div>

            <h2 style="margin-bottom: 20px;">Actividades Existentes</h2>
            <div style="display: grid; gap: 20px;">
                <?php foreach ($materias_list as $m): 
                    $mid = $m['id'];
                    $actividades = $conn->query("SELECT * FROM actividades WHERE materia_id = $mid ORDER BY created_at DESC");
                ?>
                    <div class="card glass-panel fade-in">
                        <h4 style="color: var(--primary); margin-bottom: 15px;"><?php echo htmlspecialchars($m['nombre']); ?></h4>
                        <?php if ($actividades && $actividades->num_rows > 0): ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Título</th>
                                            <th>Límite</th>
                                            <th>Entregas</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($act = $actividades->fetch_assoc()): 
                                            $aid = $act['id'];
                                            $res_e = $conn->query("SELECT COUNT(*) as c FROM entregas WHERE actividad_id = $aid");
                                            $c_entregas = $res_e->fetch_assoc()['c'];
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($act['titulo']); ?></td>
                                                <td><?php echo date('d M, Y H:i', strtotime($act['fecha_limite'])); ?></td>
                                                <td align="center">
                                                    <a href="ver_entregas.php?actividad=<?php echo $aid; ?>" class="badge" style="background: rgba(99,102,241,0.1); color: var(--primary); text-decoration: none;">
                                                        <?php echo $c_entregas; ?> entregas
                                                    </a>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar actividad?');">
                                                        <input type="hidden" name="actividad_id" value="<?php echo $aid; ?>">
                                                        <button type="submit" name="eliminar_actividad" class="btn" style="color: #fb7185;"><i class="fa-solid fa-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted" style="font-size: 0.85rem;">No hay actividades creadas.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</body>
</html>
