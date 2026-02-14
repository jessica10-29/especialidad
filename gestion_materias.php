<?php
require_once 'conexion.php';
verificar_sesion();
verificar_rol('profesor');

$profesor_id = $_SESSION['usuario_id'];
$mensaje = '';

// Lógica para eliminar materia (Opcional, con precaución)
if (isset($_GET['eliminar'])) {
    $materia_id = (int)$_GET['eliminar'];
    // Verificar si la materia pertenece al profesor antes de borrar
    $check = $conn->query("SELECT id FROM materias WHERE id = $materia_id AND profesor_id = $profesor_id");
    if ($check->num_rows > 0) {
        $conn->query("DELETE FROM materias WHERE id = $materia_id");
        $mensaje = '<div style="background: rgba(16, 185, 129, 0.1); color: #34d399; padding: 12px; border-radius: 10px; margin-bottom: 25px; font-size: 0.85rem; border: 1px solid rgba(16, 185, 129, 0.2);"><i class="fa-solid fa-check-circle"></i> Materia eliminada correctamente.</div>';
    }
}

// Obtener periodo actual para filtrar cuentas
$p_actual_id = obtener_periodo_actual();

// Obtener todas las materias del profesor con conteo filtrado por periodo actual (Trello)
$sql = "SELECT id, nombre, codigo, descripcion, 
        (SELECT COUNT(*) FROM matriculas WHERE materia_id = materias.id AND periodo_id = $p_actual_id) as alumnos_cuenta 
        FROM materias WHERE profesor_id = $profesor_id";
$res_materias = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Materias - Unicali Segura</title>
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
                <a href="gestion_materias.php" class="nav-link active">
                    <i class="fa-solid fa-book"></i> Mis Materias
                </a>
                <a href="gestion_notas.php" class="nav-link">
                    <i class="fa-solid fa-user-pen"></i> Gestionar Notas
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
            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <div>
                    <h1 class="text-gradient">Mis Materias</h1>
                    <p class="text-muted">Administra los cursos asignados a tu cuenta</p>
                </div>
                <a href="crear_materia.php" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Nueva Materia
                </a>
            </header>

            <?php echo $mensaje; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
                <?php if ($res_materias && $res_materias->num_rows > 0): while ($m = $res_materias->fetch_assoc()): ?>
                        <div class="card glass-panel fade-in">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                                <div style="background: rgba(99, 102, 241, 0.1); width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fa-solid fa-bookmark" style="color: var(--primary);"></i>
                                </div>
                                <span class="text-muted" style="font-size: 0.8rem; font-weight: 600; padding: 4px 10px; background: rgba(255,255,255,0.05); border-radius: 20px; border: 1px solid var(--glass-border);">
                                    <?php echo htmlspecialchars($m['codigo']); ?>
                                </span>
                            </div>

                            <h3 style="margin-bottom: 10px;"><?php echo htmlspecialchars($m['nombre']); ?></h3>
                            <p class="text-muted" style="font-size: 0.85rem; height: 40px; overflow: hidden; margin-bottom: 20px;">
                                <?php echo htmlspecialchars($m['descripcion'] ? $m['descripcion'] : 'Sin descripción adicional.'); ?>
                            </p>

                            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px; padding: 12px; background: rgba(0,0,0,0.1); border-radius: 10px;">
                                <i class="fa-solid fa-users" style="color: var(--secondary);"></i>
                                <div>
                                    <div style="font-weight: 700; font-size: 1rem; color: white;"><?php echo $m['alumnos_cuenta']; ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">Estudiantes inscritos</div>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <a href="editar_notas.php?materia=<?php echo $m['id']; ?>" class="btn btn-outline" style="font-size: 0.8rem; padding: 10px;">
                                    <i class="fa-solid fa-pen-to-square"></i> Editar
                                </a>
                                <a href="#" onclick="confirmarEliminar(<?php echo $m['id']; ?>)" class="btn" style="background: rgba(244, 63, 94, 0.1); color: #fb7185; font-size: 0.8rem; border: 1px solid rgba(244, 63, 114, 0.2);">
                                    <i class="fa-solid fa-trash"></i> Borrar
                                </a>
                            </div>
                        </div>
                    <?php endwhile;
                else: ?>
                    <div class="card glass-panel" style="grid-column: 1 / -1; text-align: center; padding: 60px;">
                        <i class="fa-solid fa-book-open" style="font-size: 3rem; opacity: 0.2; margin-bottom: 20px;"></i>
                        <h3>Aún no tienes materias creadas</h3>
                        <p class="text-muted" style="margin-bottom: 30px;">Comienza creando tu primer curso académico para gestionar notas y asistencia.</p>
                        <a href="crear_materia.php" class="btn btn-primary">
                            <i class="fa-solid fa-plus"></i> Crear Mi Primera Materia
                        </a>
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

        function confirmarEliminar(id) {
            if (confirm('¿Estás seguro de que deseas eliminar esta materia? Esta acción no se puede deshacer y borrará todos los datos relacionados.')) {
                window.location.href = 'gestion_materias.php?eliminar=' + id;
            }
        }
    </script>
</body>

</html>