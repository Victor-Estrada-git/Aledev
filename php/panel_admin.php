<?php
session_start();

// Verificar si el usuario está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    // Si no, redirigir a la página de login
    header("Location: ../html/index.html");
    header();
    exit();
}

// Si llegamos aquí, el usuario está logueado como admin
$nombre_usuario_logueado = isset($_SESSION['nombre_usuario']) ? htmlspecialchars($_SESSION['nombre_usuario']) : 'Admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administrador</title>
</head>
<body>
    <header>
        <div>
            <img src="assets/placeholder_logo.png" alt="Logo Comunidad" width="50" height="50" style="vertical-align: middle;">
            <span style="font-size: 24px; margin-left: 10px;">Comunidad Web - Panel Admin</span>
        </div>
        <hr>
    </header>

    <h1>Bienvenido al Panel de Administrador, <?php echo $nombre_usuario_logueado; ?>!</h1>
    <p>Aquí podrás gestionar usuarios, configuraciones, etc.</p>
    
    <nav>
        <ul>
            <li><a href="#">Gestionar Alumnos</a></li>
            <li><a href="#">Gestionar Tutores</a></li>
            <li><a href="logout.php">Cerrar Sesión</a></li>
        </ul>
    </nav>

    <footer>
        <hr>
        <p>&copy; 2025 Comunidad Web.</p>
    </footer>
</body>
</html>