<?php
// Archivo: php/api/students.php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') { // Verificación de sesión admin
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion_db.php'; // Define $conexion

// Validar y sanitizar parámetros de entrada
$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$limit = max(1, min(100, isset($_GET['limit']) ? (int)$_GET['limit'] : 10));
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$career = isset($_GET['career']) ? trim($_GET['career']) : '';

try {
    $where = [];
    $params = [];

    // Filtro de búsqueda mejorado
    if (!empty($search)) { // Solo añadir si $search no está vacío
        $where[] = "(alumnos.nombre_completo LIKE :search_term OR alumnos.correo LIKE :search_term OR alumnos.boleta LIKE :search_term OR alumnos.nombre_usuario LIKE :search_term)";
        $params[':search_term'] = "%$search%";
    }

    // Filtro por carrera
    if (!empty($career) && $career !== 'todas' && in_array($career, ['ISC', 'IIA', 'LCD'])) {
        $where[] = "alumnos.carrera = :career_filter"; // Usar alias de tabla si es necesario en JOINs futuros
        $params[':career_filter'] = $career;
    }

    $whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

    // Contar total de estudiantes para paginación
    $countSql = "SELECT COUNT(*) FROM alumnos $whereClause";
    $stmt_count = $conexion->prepare($countSql); // CAMBIO: $conn a $conexion
    $stmt_count->execute($params); // Pasar todos los parámetros de filtro
    $totalStudents = $stmt_count->fetchColumn();
    $totalPages = ($limit > 0) ? ceil($totalStudents / $limit) : 0;

    // Crear parámetros para la consulta principal, incluyendo limit y offset
    $queryParams = $params; // Clonar params para no afectar el conteo
    $queryParams[':limit_val'] = $limit;
    $queryParams[':offset_val'] = $offset;

    // Obtener estudiantes con información adicional
    $sql = "SELECT
                id,
                nombre_completo,
                nombre_usuario,
                boleta,
                carrera,
                correo,
                numero_celular,
                fecha_registro,
                DATE_FORMAT(fecha_registro, '%d/%m/%Y') as fecha_registro_formateada,
                CASE
                    WHEN ruta_credencial_horario IS NOT NULL AND ruta_credencial_horario != ''
                    THEN 'Sí'
                    ELSE 'No'
                END as tiene_credencial
            FROM alumnos
            $whereClause
            ORDER BY fecha_registro DESC
            LIMIT :limit_val OFFSET :offset_val"; // Usar placeholders para limit y offset

    $stmt_students = $conexion->prepare($sql); // CAMBIO: $conn a $conexion
    // Bind de limit y offset como enteros
    foreach ($queryParams as $key => &$val) { // Pasar por referencia para bindValue
        if ($key === ':limit_val' || $key === ':offset_val') {
            $stmt_students->bindValue($key, intval($val), PDO::PARAM_INT);
        } else {
            $stmt_students->bindValue($key, $val);
        }
    }
    unset($val); // Romper la referencia
    
    $stmt_students->execute();
    $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

    // Mejorar presentación de datos (nombres completos de carreras)
    $carreras_map = [
        'ISC' => 'Ingeniería en Sistemas Computacionales',
        'IIA' => 'Ingeniería en Inteligencia Artificial',
        'LCD' => 'Licenciatura en Ciencia de Datos'
    ];

    foreach ($students as &$student_item) { // Usar nombre de variable diferente para evitar confusión
        $student_item['carrera_completa'] = $carreras_map[$student_item['carrera']] ?? $student_item['carrera'];
    }
    unset($student_item); // Romper la referencia

    // Obtener estadísticas adicionales (para el conjunto filtrado, si es necesario)
    // Nota: array_slice para los parámetros de statsStmt podría no ser necesario si no se añaden más después
    $statsSql = "
        SELECT
            COUNT(*) as total_estudiantes,
            SUM(CASE WHEN carrera = 'ISC' THEN 1 ELSE 0 END) as total_isc,
            SUM(CASE WHEN carrera = 'IIA' THEN 1 ELSE 0 END) as total_iia,
            SUM(CASE WHEN carrera = 'LCD' THEN 1 ELSE 0 END) as total_lcd,
            SUM(CASE WHEN ruta_credencial_horario IS NOT NULL AND ruta_credencial_horario != '' THEN 1 ELSE 0 END) as con_credencial
        FROM alumnos
        $whereClause
    ";
    $statsStmt = $conexion->prepare($statsSql); // CAMBIO: $conn a $conexion
    $statsStmt->execute($params); // Usar los mismos parámetros de filtro que para el conteo
    $stats_data = $statsStmt->fetch(PDO::FETCH_ASSOC); // Renombrar para evitar conflicto

    echo json_encode([
        'success' => true,
        'students' => $students,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => intval($totalStudents), // Asegurar que es int
            'itemsPerPage' => $limit,
            // 'hasNextPage' => $page < $totalPages, // Ya esperado por panel_admin.js
            // 'hasPrevPage' => $page > 1      // Ya esperado por panel_admin.js
        ],
        'stats' => $stats_data, // Datos de las estadísticas
        'filters' => [
            'search' => $search,
            'career' => $career
        ],
        'timestamp' => time()
    ]);

} catch (PDOException $e) {
    error_log("Error de BD en students.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos al obtener alumnos.']);
} catch (Exception $e) {
    error_log("Error general en students.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor al obtener alumnos.']);
}
?>