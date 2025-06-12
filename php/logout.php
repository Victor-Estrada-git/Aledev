<?php
// =============================================================================
// Archivo: logout.php
// Descripción: Cierra la sesión del usuario y redirige al inicio.
// =============================================================================

session_start();

// Destruir todas las variables de sesión.
$_SESSION = array();

// Si se desea destruir la sesión completamente, borre también la cookie de sesión.
// Nota: ¡Esto destruirá la sesión, y no solo los datos de la sesión!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión.
session_destroy();

// Redirigir al usuario a la página de inicio de sesión o a la página principal.
// Ajusta la ruta según la ubicación de tu archivo index.html o login.
// Si tu panel_admin.html está en /html/ y logout.php está en /php/,
// podrías querer redirigir a ../html/index.html o una página de login.
header("Location: ../html/index.html"); // Ajusta esta ruta
exit;
?>