<?php
// php/api/update_admin_profile.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado', 'error_code' => 'UNAUTHORIZED']);
    exit;
}

require_once '../conexion_db.php';

$admin_id = $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido', 'error_code' => 'METHOD_NOT_ALLOWED']);
    exit;
}

// Obtener datos del POST (form-data)
$nombre_completo = trim($_POST['nombre_completo'] ?? '');
$nombre_usuario = trim($_POST['nombre_usuario'] ?? '');
$correo = trim($_POST['correo'] ?? '');
$nueva_contrasena = $_POST['nueva_contrasena'] ?? ''; // El JS lo envía como 'nueva_contrasena'

try {
    $conexion->beginTransaction();

    // Validaciones
    if (empty($nombre_completo) || empty($nombre_usuario) || empty($correo)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nombre completo, nombre de usuario y correo son requeridos.', 'error_code' => 'MISSING_FIELDS']);
        exit;
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Formato de correo inválido.', 'error_code' => 'INVALID_EMAIL']);
        exit;
    }

    // Verificar unicidad de nombre_usuario
    $stmt = $conexion->prepare("SELECT id FROM admins WHERE nombre_usuario = :nombre_usuario AND id != :admin_id");
    $stmt->execute([':nombre_usuario' => $nombre_usuario, ':admin_id' => $admin_id]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'El nombre de usuario ya está en uso.', 'error_code' => 'USERNAME_EXISTS']);
        exit;
    }

    // Verificar unicidad de correo
    $stmt = $conexion->prepare("SELECT id FROM admins WHERE correo = :correo AND id != :admin_id");
    $stmt->execute([':correo' => $correo, ':admin_id' => $admin_id]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'El correo electrónico ya está en uso.', 'error_code' => 'EMAIL_EXISTS']);
        exit;
    }

    $sql_update_parts = [
        "nombre_completo = :nombre_completo",
        "nombre_usuario = :nombre_usuario",
        "correo = :correo"
    ];
    $params = [
        ':nombre_completo' => $nombre_completo,
        ':nombre_usuario' => $nombre_usuario,
        ':correo' => $correo,
        ':admin_id' => $admin_id
    ];

    if (!empty($nueva_contrasena)) {
        if (strlen($nueva_contrasena) < 8) { // Ejemplo de validación de longitud
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 8 caracteres.', 'error_code' => 'PASSWORD_TOO_SHORT']);
            exit;
        }
        $hash_contrasena = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
        $sql_update_parts[] = "hash_contrasena = :hash_contrasena";
        $params[':hash_contrasena'] = $hash_contrasena;
    }

    $sql = "UPDATE admins SET " . implode(", ", $sql_update_parts) . " WHERE id = :admin_id";
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        $conexion->commit();
        // Actualizar nombre en sesión si cambió, para el saludo.
        if ($_SESSION['admin_id'] == $admin_id) { // Doble check
             // Es mejor que el JS recargue los datos del perfil para el saludo si es necesario
        }
        echo json_encode(['success' => true, 'message' => 'Perfil actualizado correctamente.']);
    } else {
        $conexion->rollBack(); // No es estrictamente necesario un rollback si no hubo error, pero sí si no hubo cambios.
        echo json_encode(['success' => true, 'message' => 'No se realizaron cambios o los datos son idénticos.', 'no_changes' => true]);
    }

} catch (PDOException $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    error_log("Error de BD en update_admin_profile.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos.', 'error_code' => 'DATABASE_ERROR']);
} catch (Exception $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    error_log("Error general en update_admin_profile.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage(), 'error_code' => 'INTERNAL_SERVER_ERROR']);
}
?>