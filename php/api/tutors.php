<?php
// Archivo: php/api/tutors.php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Asegurarse de que se verifica la sesión del admin correctamente.
// El panel_admin.js espera 'admin_id' y 'user_type' === 'admin'
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion_db.php'; // Este archivo define $conexion

// Validar y sanitizar parámetros de entrada
$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$limit = max(1, min(100, isset($_GET['limit']) ? (int)$_GET['limit'] : 10));
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

try {
    $where = [];
    $params = []; // Usar array asociativo para named placeholders

    // Filtro de búsqueda
    if (!empty($search)) {
        // Usar alias 't' para los campos de la tabla tutores
        $where[] = "(t.nombre_completo LIKE :search_term OR t.correo LIKE :search_term OR t.nombre_usuario LIKE :search_term OR t.areas_materias LIKE :search_term)";
        $params[':search_term'] = "%$search%";
    }

    // Filtro por estado
    if (!empty($status) && $status !== 'todos' && in_array($status, ['pendiente', 'aprobado', 'rechazado'])) {
        $where[] = "t.estado_registro = :status_filter";
        $params[':status_filter'] = $status;
    }

    $whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

    // Contar total de tutores para paginación
    // Asegurarse de usar el alias 't' si $whereClause lo necesita
    $countSql = "SELECT COUNT(*) FROM tutores t $whereClause";
    $stmt_count = $conexion->prepare($countSql); // Usar $conexion
    $stmt_count->execute($params); // Pasar los parámetros de filtro
    $totalTutors = $stmt_count->fetchColumn();
    $totalPages = ($limit > 0) ? ceil($totalTutors / $limit) : 0;

    // Crear parámetros para la consulta principal, incluyendo limit y offset
    $queryParams = $params; 
    // $queryParams[':limit_val'] = $limit; // Se usarán bindValue abajo para PDO::PARAM_INT
    // $queryParams[':offset_val'] = $offset;


    // Obtener tutores con información adicional y datos del admin aprobador
    $sql = "SELECT
                t.id,
                t.nombre_completo,
                t.nombre_usuario,
                t.correo,
                t.areas_materias,
                t.nivel_experiencia,
                t.tiene_certificacion,
                t.telefono,
                t.fecha_registro,
                t.estado_registro,
                t.fecha_aprobacion,
                t.motivo_rechazo,
                DATE_FORMAT(t.fecha_registro, '%d/%m/%Y') as fecha_registro_formateada,
                DATE_FORMAT(t.fecha_aprobacion, '%d/%m/%Y %H:%i') as fecha_aprobacion_formateada, /* Añadida hora */
                a.nombre_completo as admin_aprobador_nombre, /* Nombre del admin que aprobó */
                CASE
                    WHEN t.ruta_documentos_certificacion IS NOT NULL AND t.ruta_documentos_certificacion != ''
                    THEN 'Sí'
                    ELSE 'No'
                END as tiene_documentos
            FROM tutores t
            LEFT JOIN admins a ON t.admin_aprobador = a.id /* Join con admins para obtener nombre del aprobador */
            $whereClause
            ORDER BY
                CASE t.estado_registro
                    WHEN 'pendiente' THEN 1
                    WHEN 'aprobado' THEN 2
                    WHEN 'rechazado' THEN 3
                    ELSE 4
                END,
                t.fecha_registro DESC
            LIMIT :limit_val OFFSET :offset_val"; // Placeholders para limit y offset

    $stmt_tutors = $conexion->prepare($sql); // Usar $conexion

    // Bind de los parámetros de filtro
    foreach ($queryParams as $key => $val) {
        $stmt_tutors->bindValue($key, $val);
    }
    // Bind de limit y offset como enteros
    $stmt_tutors->bindValue(':limit_val', $limit, PDO::PARAM_INT);
    $stmt_tutors->bindValue(':offset_val', $offset, PDO::PARAM_INT);

    $stmt_tutors->execute();
    $tutors = $stmt_tutors->fetchAll(PDO::FETCH_ASSOC);

    // Mejorar presentación de datos (textos para estado, nivel, etc.)
    $estados_map = [
        'pendiente' => 'Pendiente',
        'aprobado' => 'Aprobado',
        'rechazado' => 'Rechazado'
    ];

    $niveles_map = [
        'Estudiante universitario' => 'Estudiante Universitario',
        'Licenciado' => 'Licenciado',
        'Posgrado' => 'Posgrado',
        'Profesor' => 'Profesor',
        'Doctorado' => 'Doctorado'
    ];

    foreach ($tutors as &$tutor_item) {
        $tutor_item['estado_registro_texto'] = $estados_map[$tutor_item['estado_registro']] ?? $tutor_item['estado_registro'];
        $tutor_item['nivel_experiencia_texto'] = $niveles_map[$tutor_item['nivel_experiencia']] ?? $tutor_item['nivel_experiencia'];
        $tutor_item['tiene_certificacion_texto'] = $tutor_item['tiene_certificacion'] ? 'Sí' : 'No';

        // Procesar áreas/materias para resumen (similar a como lo tenías)
        if (!empty($tutor_item['areas_materias'])) {
            $areas_decoded = @json_decode($tutor_item['areas_materias'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($areas_decoded)) {
                $tutor_item['areas_resumen'] = implode(', ', array_slice($areas_decoded, 0, 2));
                $tutor_item['total_areas'] = count($areas_decoded);
            } else {
                $areas_array = array_map('trim', explode(',', $tutor_item['areas_materias']));
                $tutor_item['areas_resumen'] = implode(', ', array_slice($areas_array, 0, 2));
                $tutor_item['total_areas'] = count($areas_array);
            }
            if ($tutor_item['total_areas'] > 2) {
                $tutor_item['areas_resumen'] .= '...';
            }
        } else {
            $tutor_item['areas_resumen'] = 'No especificado';
            $tutor_item['total_areas'] = 0;
        }
    }
    unset($tutor_item); // Romper referencia

    // Obtener estadísticas adicionales (para el conjunto filtrado)
    // Quitar LIMIT y OFFSET de los parámetros para la consulta de estadísticas globales
    $statsParams = $params; // Usar los parámetros de filtro originales sin limit/offset

    $statsSql = "
        SELECT
            COUNT(*) as total_tutores, /* Alias para el JS */
            SUM(CASE WHEN estado_registro = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado_registro = 'aprobado' THEN 1 ELSE 0 END) as aprobados,
            SUM(CASE WHEN estado_registro = 'rechazado' THEN 1 ELSE 0 END) as rechazados,
            SUM(CASE WHEN tiene_certificacion = 1 THEN 1 ELSE 0 END) as con_certificacion
        FROM tutores t /* Alias 't' para consistencia con $whereClause */
        $whereClause
    ";
    $statsStmt = $conexion->prepare($statsSql); // Usar $conexion
    $statsStmt->execute($statsParams); // Ejecutar con los parámetros de filtro
    $stats_data = $statsStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'tutors' => $tutors,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => intval($totalTutors),
            'itemsPerPage' => $limit
            // 'hasNextPage' y 'hasPrevPage' los puede calcular el JS si los necesita
        ],
        'stats' => $stats_data,
        'filters' => [
            'search' => $search,
            'status' => $status
        ],
        'timestamp' => time()
    ]);

} catch (PDOException $e) {
    error_log("Error de BD en tutors.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos al obtener tutores.']);
} catch (Exception $e) {
    error_log("Error general en tutors.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor al obtener tutores.']);
}
?>