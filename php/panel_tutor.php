<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'tutor') {
    header("Location: ../html/index.html");
    exit();
}
$nombre_usuario_logueado = isset($_SESSION['nombre_usuario']) ? htmlspecialchars($_SESSION['nombre_usuario']) : 'Tutor';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Tutor</title>
</head>
<body>
    <h1>Bienvenido a tu Panel, <?php echo $nombre_usuario_logueado; ?>!</h1>
    <p>Contenido específico para tutores.</p>
    <a href="logout.php">Cerrar Sesión</a>
</body>
</html>