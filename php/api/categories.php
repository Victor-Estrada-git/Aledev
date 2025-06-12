<?php
// php/api/categories.php
// =============================================================================
// API: categories.php
// Descripción: Gestión de categorías (CRUD operations)
// =============================================================================

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Considera restringir esto en producción
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Agrega Authorization si usas tokens

// Manejo de solicitudes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Verificar autorización de administrador
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Acceso no autorizado. Se requiere autenticación de administrador.',
        'error_code' => 'UNAUTHORIZED'
    ]);
    exit;
}

require_once '../conexion_db.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetCategories($conexion); // Pasando $conexion en lugar de $conexion
            break;
        case 'POST':
            handleCreateCategory($conexion);
            break;
        case 'PUT':
            handleUpdateCategory($conexion);
            break;
        case 'DELETE':
            handleDeleteCategory($conexion);
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Método no permitido',
                'error_code' => 'METHOD_NOT_ALLOWED'
            ]);
    }
} catch (Exception $e) {
    error_log("Error en categories.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor: ' . $e->getMessage(), // Más detalle para el log
        'error_code' => 'INTERNAL_ERROR'
    ]);
}

// =============================================================================
// FUNCIONES DE MANEJO
// =============================================================================

function handleGetCategories($conexion_db_conn) { // Renombrado para evitar confusión
    try {
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(5, intval($_GET['limit']))) : 10; // JS usa 10
        $offset = ($page - 1) * $limit;

        // Obtener total de categorías
        $countStmt = $conexion_db_conn->query("SELECT COUNT(*) FROM categorias");
        $totalCategories = $countStmt->fetchColumn();

        // Obtener categorías con paginación
        $stmt = $conexion_db_conn->prepare("
            SELECT
                id,
                nombre,
                descripcion,
                fecha_creacion,
                DATE_FORMAT(fecha_creacion, '%d/%m/%Y %H:%i') as fecha_creacion_formatted
            FROM categorias
            ORDER BY fecha_creacion DESC
            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'categories' => $categories,
            'pagination' => [
                'currentPage' => $page, // JS espera currentPage
                'totalPages' => ceil($totalCategories / $limit),
                'totalItems' => intval($totalCategories), // JS espera totalItems
                'itemsPerPage' => intval($limit) // JS espera itemsPerPage
            ]
        ]);

    } catch (PDOException $e) {
        // Lanzar excepción para que sea capturada por el manejador global
        throw new Exception('Error en la consulta de categorías: ' . $e->getMessage());
    }
}

function handleCreateCategory($conexion_db_conn) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        // Fallback a $_POST si el input JSON es nulo o no contiene los datos (útil para FormData también)
        $nombre = trim($input['nombre'] ?? $_POST['nombre'] ?? '');
        $descripcion = trim($input['descripcion'] ?? $_POST['descripcion'] ?? '');

        // Validaciones
        if (empty($nombre)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El nombre de la categoría es obligatorio', 'error_code' => 'MISSING_NAME']);
            return;
        }

        if (strlen($nombre) > 100) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El nombre de la categoría no puede exceder 100 caracteres', 'error_code' => 'NAME_TOO_LONG']);
            return;
        }

        // Verificar si ya existe una categoría con el mismo nombre
        $checkStmt = $conexion_db_conn->prepare("SELECT id FROM categorias WHERE nombre = :nombre");
        $checkStmt->bindParam(':nombre', $nombre);
        $checkStmt->execute();

        if ($checkStmt->fetch()) {
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'message' => 'Ya existe una categoría con este nombre', 'error_code' => 'DUPLICATE_NAME']);
            return;
        }

        // Insertar nueva categoría
        $stmt = $conexion_db_conn->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (:nombre, :descripcion)");
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->execute();

        $categoryId = $conexion_db_conn->lastInsertId();

        // Obtener la categoría recién creada para devolverla (opcional, pero bueno para el frontend)
        $getNewCatStmt = $conexion_db_conn->prepare("SELECT id, nombre, descripcion, DATE_FORMAT(fecha_creacion, '%d/%m/%Y %H:%i') as fecha_creacion_formatted FROM categorias WHERE id = :id");
        $getNewCatStmt->bindParam(':id', $categoryId, PDO::PARAM_INT);
        $getNewCatStmt->execute();
        $newCategory = $getNewCatStmt->fetch(PDO::FETCH_ASSOC);


        http_response_code(201); // Created
        echo json_encode([
            'success' => true,
            'message' => 'Categoría creada correctamente',
            'category' => $newCategory // Devolver la categoría creada
        ]);

    } catch (PDOException $e) {
        throw new Exception('Error en la creación de categoría: ' . $e->getMessage());
    }
}

function handleUpdateCategory($conexion_db_conn) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        $id = intval($input['id'] ?? 0);
        $nombre = trim($input['nombre'] ?? '');
        $descripcion = trim($input['descripcion'] ?? '');

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID de categoría inválido', 'error_code' => 'INVALID_ID']);
            return;
        }

        if (empty($nombre)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El nombre de la categoría es obligatorio', 'error_code' => 'MISSING_NAME']);
            return;
        }
         if (strlen($nombre) > 100) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El nombre no puede exceder 100 caracteres', 'error_code' => 'NAME_TOO_LONG']);
            return;
        }


        // Verificar si la categoría existe
        $checkStmt = $conexion_db_conn->prepare("SELECT id FROM categorias WHERE id = :id");
        $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $checkStmt->execute();

        if (!$checkStmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Categoría no encontrada', 'error_code' => 'CATEGORY_NOT_FOUND']);
            return;
        }

        // **Mejora: Verificar si el nuevo nombre ya existe en OTRA categoría**
        $checkDuplicateNameStmt = $conexion_db_conn->prepare("SELECT id FROM categorias WHERE nombre = :nombre AND id != :id");
        $checkDuplicateNameStmt->bindParam(':nombre', $nombre);
        $checkDuplicateNameStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $checkDuplicateNameStmt->execute();
        if ($checkDuplicateNameStmt->fetch()) {
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'message' => 'Ya existe otra categoría con este nombre.', 'error_code' => 'DUPLICATE_NAME_ON_UPDATE']);
            return;
        }


        // Actualizar categoría
        $stmt = $conexion_db_conn->prepare("UPDATE categorias SET nombre = :nombre, descripcion = :descripcion WHERE id = :id");
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
             // Obtener la categoría actualizada para devolverla
            $getUpdatedCatStmt = $conexion_db_conn->prepare("SELECT id, nombre, descripcion, DATE_FORMAT(fecha_creacion, '%d/%m/%Y %H:%i') as fecha_creacion_formatted FROM categorias WHERE id = :id");
            $getUpdatedCatStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $getUpdatedCatStmt->execute();
            $updatedCategory = $getUpdatedCatStmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'message' => 'Categoría actualizada correctamente',
                'category' => $updatedCategory
            ]);
        } else {
            echo json_encode([
                'success' => true, // O false si se considera un error no haber cambios
                'message' => 'No se realizaron cambios (datos idénticos o categoría no encontrada).',
                'no_changes' => true
            ]);
        }


    } catch (PDOException $e) {
        throw new Exception('Error en la actualización de categoría: ' . $e->getMessage());
    }
}

function handleDeleteCategory($conexion_db_conn) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        // Permitir obtener ID de GET para pruebas simples, pero priorizar JSON body
        $id = intval($input['id'] ?? $_GET['id'] ?? 0);


        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID de categoría inválido', 'error_code' => 'INVALID_ID']);
            return;
        }

        // Verificar si la categoría existe
        $checkStmt = $conexion_db_conn->prepare("SELECT id FROM categorias WHERE id = :id");
        $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $checkStmt->execute();

        if (!$checkStmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Categoría no encontrada', 'error_code' => 'CATEGORY_NOT_FOUND']);
            return;
        }

        // Opcional: Implementar borrado lógico (soft delete)
        // Por ejemplo, añadir una columna `is_deleted` a la tabla `categorias`
        // $stmt = $conexion_db_conn->prepare("UPDATE categorias SET is_deleted = 1, deleted_at = NOW() WHERE id = :id");

        // Borrado físico (actual)
        // Considerar si esta categoría está siendo usada por otras tablas (FK constraints)
        // Si hay FK, la eliminación podría fallar o tener efectos en cascada.
        // Se podría añadir una verificación aquí antes de eliminar.
        $stmt = $conexion_db_conn->prepare("DELETE FROM categorias WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Categoría eliminada correctamente']);
        } else {
            // Esto podría pasar si justo después del check, otro request la eliminó.
            http_response_code(404); // O 500 si se considera un error inesperado
            echo json_encode(['success' => false, 'message' => 'No se pudo eliminar la categoría o ya había sido eliminada.', 'error_code' => 'DELETE_FAILED']);
        }

    } catch (PDOException $e) {
        // Manejar error de FK constraint (ej. si la categoría está en uso y no se puede eliminar)
        if ($e->getCode() == '23000') { // Código de error SQLSTATE para violación de integridad
            http_response_code(409); // Conflict
             echo json_encode(['success' => false, 'message' => 'No se puede eliminar la categoría porque está siendo utilizada.', 'error_code' => 'CATEGORY_IN_USE']);
        } else {
            throw new Exception('Error en la eliminación de categoría: ' . $e->getMessage());
        }
    }
}
?>