<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-site-verification" content="SVIicerYWpM6cI470jTzP_uXRhxALyHrG7rhtqQuKf8" />
    <title>Unicali Segura | Portal Educativo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="icon" type="image/png" href="favicon.png?v=3">
    <link rel="shortcut icon" href="favicon.ico?v=3">
    <link rel="apple-touch-icon" href="favicon.png?v=3">
    <link rel="icon" type="image/png" href="favicon.png?v=3">
    <link rel="shortcut icon" href="favicon.ico?v=3">
    <link rel="apple-touch-icon" href="favicon.png?v=3">
</head>

<body>
    <div class="background-mesh"></div>

    <div class="login-container">
        <div class="glass-panel login-box fade-in" style="max-width: 600px;">
            <div class="logo-area" style="margin-bottom: 40px;">
                <i class="fa-solid fa-graduation-cap logo-large"></i>
                <h1 style="font-size: 3rem; line-height: 1; margin-bottom: 10px;">Unicali<span
                        class="text-gradient">Segura</span></h1>
                <p class="text-muted">La evolución de la gestión académica universitaria.</p>
            </div>

            <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; text-align: left; margin-bottom: 40px;">
                <a href="login.php?rol=profesor" class="glass-panel"
                    style="padding: 30px 20px; text-decoration: none; color: inherit; transition: var(--transition); border: 1px solid rgba(255,255,255,0.05);">
                    <div
                        style="background: rgba(99, 102, 241, 0.1); width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                        <i class="fa-solid fa-chalkboard-user" style="font-size: 1.5rem; color: var(--primary);"></i>
                    </div>
                    <h3 style="margin-bottom: 8px;">Docentes</h3>
                    <p class="text-muted" style="font-size: 0.8rem;">Gestiona tus clases, notas y asistencia en un solo
                        lugar.</p>
                </a>

                <a href="login.php?rol=estudiante" class="glass-panel"
                    style="padding: 30px 20px; text-decoration: none; color: inherit; transition: var(--transition); border: 1px solid rgba(255,255,255,0.05);">
                    <div
                        style="background: rgba(6, 182, 212, 0.1); width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                        <i class="fa-solid fa-user-graduate" style="font-size: 1.5rem; color: var(--secondary);"></i>
                    </div>
                    <h3 style="margin-bottom: 8px;">Estudiantes</h3>
                    <p class="text-muted" style="font-size: 0.8rem;">Consulta tus notas, historial y progreso académico
                        diario.</p>
                </a>
            </div>

            <div style="border-top: 1px solid var(--glass-border); padding-top: 30px;">
                <p class="text-muted" style="margin-bottom: 20px;">¿Aún no tienes acceso a la plataforma?</p>
                <a href="registro.php" class="btn btn-primary" style="width: 100%;">
                    Empezar Ahora <i class="fa-solid fa-arrow-right"></i>
                </a>

                <div class="security-badge" style="margin-top: 30px;">
                    <i class="fa-solid fa-lock"></i>
                    <span>Portal Seguro Unicali (Certificado SSL Activo)</span>
                </div>
            </div>
        </div>
    </div>

    <style>
        .glass-panel:hover {
            transform: translateY(-5px);
            border-color: var(--primary) !important;
            background: rgba(255, 255, 255, 0.03);
        }
    </style>
</body>

</html>
