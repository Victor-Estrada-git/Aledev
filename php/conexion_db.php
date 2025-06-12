<?php
    // Archivo: php/conexion_db.php
    $servidor = "localhost";
    $usuario = "root";
    $contrasena = ""; // Asumiendo que no tienes contraseña para root en XAMPP/desarrollo
    $nombre_bd = "comunidad_web";

    // Variable global para la conexión
    $conexion = null; 

    try {
        // Crear conexión PDO
        $conexion = new PDO("mysql:host=$servidor;dbname=$nombre_bd;charset=utf8mb4", $usuario, $contrasena);
        
        // Establecer el modo de error PDO a excepción para capturar errores SQL
        $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Establecer el modo de obtención por defecto a asociativo
        $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Opcional: $conexion->exec("set names utf8mb4"); // No es estrictamente necesario con charset en DSN, pero no daña.
        
    } catch(PDOException $e) {
        // Registrar el error crítical
        error_log("CRITICAL: Error de conexión a la base de datos (conexion_db.php): " . $e->getMessage());
        
        // En un script API, no deberías usar die() con HTML.
        // El script que incluye este archivo debería verificar si $conexion es null o capturar la excepción.
        // Si este script es llamado directamente (no debería serlo), puedes enviar un error JSON.
        if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) { // Evitar salida si es incluido
            header('Content-Type: application/json');
            http_response_code(503); // Service Unavailable
            echo json_encode([
                'success' => false,
                'message' => 'Error crítico: No se pudo conectar a la base de datos.',
                'error_code' => 'DB_CONNECTION_FAILED_FATAL'
            ]);
            exit;
        }
        // Si es incluido, $conexion permanecerá null y el script que lo incluye debería manejarlo.
        // O, mejor aún, la excepción PDO::ERRMODE_EXCEPTION ya debería haber detenido el new PDO.
        // La re-lanzamos o permitimos que se propague si el script incluyente tiene su try-catch.
        // Por simplicidad para este contexto, si falla aquí, los scripts que lo incluyan
        // tendrán $conexion = null y fallarán al intentar usarlo, lo cual su try-catch debería manejar.
    }

    /**
     * Función para sanitizar entrada de datos (Ejemplo básico)
     * Para SQL, las sentencias preparadas son la mejor protección.
     * Para salida HTML, htmlspecialchars es clave.
     */
    function sanitize_input($data) { //
        $data = trim($data); //
        $data = stripslashes($data); //
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8'); // Previene XSS si se va a mostrar en HTML
        return $data; //
    }
?>