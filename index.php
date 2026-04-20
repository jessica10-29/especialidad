<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="google-site-verification" content="SVIicerYWpM6cI470jTzP_uXRhxALyHrG7rhtqQuKf8" />
    <meta name="description" content="UnivaliSegura - Portal Educativo Seguro">
    <meta name="theme-color" content="#2563eb">
    <title>Unicali Segura | Portal Educativo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="icon" type="image/png" href="/favicon.png?v=3">
    <link rel="shortcut icon" href="/favicon.ico?v=3">
    <link rel="apple-touch-icon" href="/favicon.png?v=3">
</head>

<body class="home-page">
    <div class="background-mesh"></div>

    <div class="login-container">
        <div class="glass-panel login-box fade-in" style="max-width: 600px;">
            <div class="logo-area" style="margin-bottom: 40px; text-align: center;">
                <i class="fa-solid fa-graduation-cap logo-large"></i>
                <h1 style="font-size: 3rem; line-height: 1; margin-bottom: 10px;">Unicali<span
                        class="text-gradient">Segura</span></h1>
                <p class="text-muted">La evolución de la gestión académica universitaria.</p>
            </div>

            <div class="cta-grid"
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; text-align: left; margin-bottom: 40px;">
                <a href="login.php?rol=profesor" class="glass-panel cta-card cta-prof">
                    <div class="cta-icon cta-icon-prof">
                        <i class="fa-solid fa-chalkboard-user"></i>
                    </div>
                    <h3>Docentes</h3>
                    <p class="text-muted">Gestiona tus clases, notas y asistencia en un solo lugar.</p>
                </a>

                <a href="login.php?rol=estudiante" class="glass-panel cta-card cta-est">
                    <div class="cta-icon cta-icon-est">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                    <h3>Estudiantes</h3>
                    <p class="text-muted">Consulta tus notas, historial y progreso académico diario.</p>
                </a>
            </div>

            <div style="border-top: 1px solid var(--glass-border); padding-top: 30px;">
                <p class="text-muted" style="margin-bottom: 20px;">¿Aún no tienes acceso a la plataforma?</p>
                <a href="registro.php" class="btn btn-primary" style="width: 100%;">
                    Empezar Ahora <i class="fa-solid fa-arrow-right"></i>
                </a>

                <div class="security-badge" style="margin-top: 30px;">
                    <i class="fa-solid fa-lock"></i>
                    <span><?php echo !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'Portal Seguro Unicali (HTTPS activo)' : 'Portal Seguro Unicali'; ?></span>
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
        
        /* Asegurar que Font Awesome se carga correctamente */
        i[class*="fa-"] {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
    </style>
    <script>
        // Prevenir cambios de memoria en cache
        if (window.history.forward(1)) {
            window.history.forward(-1);
        }
        // Garantizar que la página se renderice correctamente en Chrome
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.opacity = '1';
        });
    </script>
</body>

</html>
