<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../conexion_db.php';

try {
    // Estadísticas generales
    $stats = [];
    
    // Estadísticas de estudiantes
    $stmt = $conexion->prepare("
        SELECT 
            COUNT(*) as total_estudiantes,
            COUNT(CASE WHEN carrera = 'ISC' THEN 1 END) as estudiantes_isc,
            COUNT(CASE WHEN carrera = 'IIA' THEN 1 END) as estudiantes_iia,
            COUNT(CASE WHEN carrera = 'LCD' THEN 1 END) as estudiantes_lcd,
            COUNT(CASE WHEN ruta_credencial_horario IS NOT NULL AND ruta_credencial_horario != '' THEN 1 END) as estudiantes_con_credencial,
            COUNT(CASE WHEN DATE(fecha_registro) = CURDATE() THEN 1 END) as registros_hoy,
            COUNT(CASE WHEN WEEK(fecha_registro) = WEEK(CURDATE()) AND YEAR(fecha_registro) = YEAR(CURDATE()) THEN 1 END) as registros_semana,
            COUNT(CASE WHEN MONTH(fecha_registro) = MONTH(CURDATE()) AND YEAR(fecha_registro) = YEAR(CURDATE()) THEN 1 END) as registros_mes
        FROM alumnos
    ");
    $stmt->execute();
    $stats['estudiantes'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Estadísticas de tutores
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_tutores,
            COUNT(CASE WHEN estado_registro = 'pendiente' THEN 1 END) as tutores_pendientes,
            COUNT(CASE WHEN estado_registro = 'aprobado' THEN 1 END) as tutores_aprobados,
            COUNT(CASE WHEN estado_registro = 'rechazado' THEN 1 END) as tutores_rechazados,
            COUNT(CASE WHEN tiene_certificacion = 1 THEN 1 END) as tutores_certificados,
            COUNT(CASE WHEN DATE(fecha_registro) = CURDATE() THEN 1 END) as solicitudes_hoy,
            COUNT(CASE WHEN WEEK(fecha_registro) = WEEK(CURDATE()) AND YEAR(fecha_registro) = YEAR(CURDATE()) THEN 1 END) as solicitudes_semana,
            COUNT(CASE WHEN MONTH(fecha_registro) = MONTH(CURDATE()) AND YEAR(fecha_registro) = YEAR(CURDATE()) THEN 1 END) as solicitudes_mes
        FROM tutores
    ");
    $stmt->execute();
    $stats['tutores'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Tutores por nivel de experiencia
    $stmt = $conn->prepare("
        SELECT 
            nivel_experiencia,
            COUNT(*) as cantidad,
            COUNT(CASE WHEN estado_registro = 'aprobado' THEN 1 END) as aprobados
        FROM tutores 
        GROUP BY nivel_experiencia
        ORDER BY cantidad DESC
    ");
    $stmt->execute();
    $stats['tutores_por_nivel'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estudiantes por carrera (con porcentajes)
    $stats['estudiantes']['porcentaje_isc'] = $stats['estudiantes']['total_estudiantes'] > 0 ? 
        round(($stats['estudiantes']['estudiantes_isc'] / $stats['estudiantes']['total_estudiantes']) * 100, 1) : 0;
    $stats['estudiantes']['porcentaje_iia'] = $stats['estudiantes']['total_estudiantes'] > 0 ? 
        round(($stats['estudiantes']['estudiantes_iia'] / $stats['estudiantes']['total_estudiantes']) * 100, 1) : 0;
    $stats['estudiantes']['porcentaje_lcd'] = $stats['estudiantes']['total_estudiantes'] > 0 ? 
        round(($stats['estudiantes']['estudiantes_lcd'] / $stats['estudiantes']['total_estudiantes']) * 100, 1) : 0;
    
    // Tutores por estado (con porcentajes)
    $stats['tutores']['porcentaje_pendientes'] = $stats['tutores']['total_tutores'] > 0 ? 
        round(($stats['tutores']['tutores_pendientes'] / $stats['tutores']['total_tutores']) * 100, 1) : 0;
    $stats['tutores']['porcentaje_aprobados'] = $stats['tutores']['total_tutores'] > 0 ? 
        round(($stats['tutores']['tutores_aprobados'] / $stats['tutores']['total_tutores']) * 100, 1) : 0;
    $stats['tutores']['porcentaje_rechazados'] = $stats['tutores']['total_tutores'] > 0 ? 
        round(($stats['tutores']['tutores_rechazados'] / $stats['tutores']['total_tutores']) * 100, 1) : 0;
    
    // Actividad reciente (últimos registros)
    $stmt = $conn->prepare("
        SELECT 
            'estudiante' as tipo,
            nombre_completo,
            correo,
            carrera as detalle,
            fecha_registro
        FROM alumnos 
        ORDER BY fecha_registro DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $actividad_estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->prepare("
        SELECT 
            'tutor' as tipo,
            nombre_completo,
            correo,
            estado_registro as detalle,
            fecha_registro
        FROM tutores 
        ORDER BY fecha_registro DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $actividad_tutores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combinar y ordenar actividad reciente
    $actividad_reciente = array_merge($actividad_estudiantes, $actividad_tutores);
    usort($actividad_reciente, function($a, $b) {
        return strtotime($b['fecha_registro']) - strtotime($a['fecha_registro']);
    });
    $stats['actividad_reciente'] = array_slice($actividad_reciente, 0, 10);
    
    // Formatear fechas en actividad reciente
    foreach ($stats['actividad_reciente'] as &$actividad) {
        $actividad['fecha_registro_formateada'] = date('d/m/Y H:i', strtotime($actividad['fecha_registro']));
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'timestamp' => time()
    ]);
    
} catch (PDOException $e) {
    error_log("Error en admin_dashboard_stats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos']);
} catch (Exception $e) {
    error_log("Error general en admin_dashboard_stats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>