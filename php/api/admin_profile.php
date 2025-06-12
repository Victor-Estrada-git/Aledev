<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// php/api/admin_profile.php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate'); // Cache control

// Verificar que el usuario sea admin y tengamos su ID
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin' || !isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Acceso no autorizado. Se requiere autenticación de administrador.',
        'error_code' => 'UNAUTHORIZED'
    ]);
    exit;
}

require_once '../conexion_db.php'; // Asegúrate que esta ruta es correcta

try {
    $adminId = intval($_SESSION['admin_id']);

    if ($adminId <= 0) {
        throw new InvalidArgumentException('ID de administrador inválido');
    }

    // Obtener información del admin
    $stmt = $conexion->prepare("
        SELECT
            id,
            nombre_completo,
            nombre_usuario,
            correo,
            fecha_registro,
            DATE_FORMAT(fecha_registro, '%d/%m/%Y %H:%i') as fecha_registro_formateada
        FROM admins
        WHERE id = :admin_id
    ");
    $stmt->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->execute();
    $admin_profile = $stmt->fetch(PDO::FETCH_ASSOC); // Renombrado para claridad con JS

    if (!$admin_profile) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Administrador no encontrado', 'error_code' => 'ADMIN_NOT_FOUND']);
        exit;
    }

    // Obtener estadísticas de actividad del admin
    $stmt_activity = $conexion->prepare("
        SELECT
            COUNT(CASE WHEN estado_registro = 'aprobado' THEN 1 END) as aprobaciones_realizadas,
            COUNT(CASE WHEN estado_registro = 'rechazado' THEN 1 END) as rechazos_realizados,
            MAX(CASE WHEN estado_registro IN ('aprobado', 'rechazado') THEN fecha_aprobacion ELSE NULL END) as ultima_actividad_fecha
        FROM tutores
        WHERE admin_aprobador = :admin_id
    ");
    $stmt_activity->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt_activity->execute();
    $admin_activity = $stmt_activity->fetch(PDO::FETCH_ASSOC);

    if ($admin_activity['ultima_actividad_fecha']) {
        $admin_activity['ultima_actividad_formateada'] = date('d/m/Y H:i', strtotime($admin_activity['ultima_actividad_fecha']));
    } else {
        $admin_activity['ultima_actividad_formateada'] = 'Sin actividad registrada';
    }

    // Obtener últimas acciones realizadas
    $stmt_actions = $conexion->prepare("
        SELECT
            t.nombre_completo as tutor_nombre,
            t.estado_registro,
            COALESCE(t.fecha_aprobacion, t.fecha_registro) as fecha_accion, /* Usar fecha_registro si fecha_aprobacion es NULL */
            DATE_FORMAT(COALESCE(t.fecha_aprobacion, t.fecha_registro), '%d/%m/%Y %H:%i') as fecha_accion_formateada,
            t.motivo_rechazo
        FROM tutores t
        WHERE t.admin_aprobador = :admin_id AND t.estado_registro IN ('aprobado', 'rechazado')
        ORDER BY COALESCE(t.fecha_aprobacion, t.fecha_registro) DESC
        LIMIT 5
    ");
    $stmt_actions->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt_actions->execute();
    $ultimas_acciones = $stmt_actions->fetchAll(PDO::FETCH_ASSOC);

    // Calcular tiempo como admin
    $fecha_registro_dt = new DateTime($admin_profile['fecha_registro']);
    $fecha_actual_dt = new DateTime();
    $diferencia = $fecha_actual_dt->diff($fecha_registro_dt);

    $tiempo_como_admin_str = '';
    if ($diferencia->y > 0) $tiempo_como_admin_str .= $diferencia->y . ' año' . ($diferencia->y > 1 ? 's' : '') . ' ';
    if ($diferencia->m > 0) $tiempo_como_admin_str .= $diferencia->m . ' mes' . ($diferencia->m > 1 ? 'es' : '') . ' ';
    if ($diferencia->d > 0) $tiempo_como_admin_str .= $diferencia->d . ' día' . ($diferencia->d > 1 ? 's' : '') . ' ';
    if (empty(trim($tiempo_como_admin_str))) $tiempo_como_admin_str = 'Menos de un día';


    echo json_encode([
        'success' => true,
        'profile' => $admin_profile, // JS espera 'profile'
        'activity_stats' => $admin_activity,
        'recent_actions' => $ultimas_acciones,
        'time_as_admin' => trim($tiempo_como_admin_str),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos de entrada inválidos: ' . $e->getMessage(), 'error_code' => 'INVALID_INPUT']);
} catch (PDOException $e) {
    error_log("Error de BD en admin_profile.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos.', 'error_code' => 'DATABASE_ERROR']);
} catch (Exception $e) {
    error_log("Error general en admin_profile.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.', 'error_code' => 'INTERNAL_SERVER_ERROR']);
}
?>