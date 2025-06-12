<?php
// php/api/change_tutor_status.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../conexion_db.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin' || !isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.', 'error_code' => 'UNAUTHORIZED']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido. Se esperaba POST.', 'error_code' => 'METHOD_NOT_ALLOWED']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $tutorId = intval($input['tutor_id'] ?? $_POST['tutor_id'] ?? 0);
    $newStatus = trim($input['new_status'] ?? $_POST['new_status'] ?? '');
    $rejectionReason = trim($input['rejection_reason'] ?? $_POST['rejection_reason'] ?? '');
    $adminId = intval($_SESSION['admin_id']);

    // Validaciones
    if ($tutorId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de tutor inválido.', 'error_code' => 'INVALID_TUTOR_ID']);
        exit;
    }

    $validStatuses = ['aprobado', 'rechazado', 'pendiente']; // 'pendiente' también es un estado al que se puede volver
    if (!in_array($newStatus, $validStatuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Estado inválido. Válidos: ' . implode(', ', $validStatuses), 'error_code' => 'INVALID_STATUS']);
        exit;
    }

    if ($newStatus === 'rechazado' && empty($rejectionReason)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El motivo del rechazo es obligatorio.', 'error_code' => 'MISSING_REJECTION_REASON']);
        exit;
    }

    $checkStmt = $conexion->prepare("SELECT id, nombre_completo, correo, estado_registro FROM tutores WHERE id = :tutor_id");
    $checkStmt->bindParam(':tutor_id', $tutorId, PDO::PARAM_INT);
    $checkStmt->execute();
    $tutorData = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$tutorData) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Tutor no encontrado.', 'error_code' => 'TUTOR_NOT_FOUND']);
        exit;
    }

    if ($tutorData['estado_registro'] === $newStatus) {
        echo json_encode(['success' => true, 'message' => 'El tutor ya tiene el estado solicitado.', 'current_status' => $newStatus]);
        exit;
    }

    $conexion->beginTransaction();

    $fecha_procesamiento = date('Y-m-d H:i:s'); // Fecha actual para el procesamiento

    $sql = "UPDATE tutores SET estado_registro = :new_status, admin_aprobador = :admin_id"; // Admin que realiza el cambio
    $params = [
        ':new_status' => $newStatus,
        ':admin_id' => $adminId, // Registrar siempre quién hizo el cambio
        ':tutor_id' => $tutorId
    ];

    switch ($newStatus) {
        case 'aprobado':
            $sql .= ", fecha_aprobacion = :fecha_procesamiento, motivo_rechazo = NULL";
            $params[':fecha_procesamiento'] = $fecha_procesamiento;
            break;
        case 'rechazado':
            // Para 'rechazado', 'fecha_aprobacion' se podría mantener como NULL o usar una columna 'fecha_rechazo' o 'fecha_ultima_modificacion_estado'
            // Tu script original lo ponía a NULL, lo cual es correcto si 'fecha_aprobacion' es solo para aprobaciones.
            $sql .= ", motivo_rechazo = :rejection_reason, fecha_aprobacion = NULL"; // Limpiar fecha_aprobacion
            $params[':rejection_reason'] = $rejectionReason;
            break;
        case 'pendiente': // Si se revierte a pendiente
            $sql .= ", motivo_rechazo = NULL, fecha_aprobacion = NULL";
            break;
    }
    // Podríamos añadir una columna "fecha_ultima_modificacion_estado" que siempre se actualice.
    // $sql .= ", fecha_ultima_modificacion_estado = :fecha_procesamiento";
    // $params[':fecha_procesamiento'] = $fecha_procesamiento;


    $sql .= " WHERE id = :tutor_id";

    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        $log_table_exists = true; // Asumir que existe, o verificar
        if ($log_table_exists) { // Opcional: verificar si la tabla logs_cambios_tutores existe
            try {
                $logStmt = $conexion->prepare("
                    INSERT INTO logs_cambios_tutores
                    (tutor_id, admin_id, estado_anterior, estado_nuevo, motivo_rechazo, fecha_cambio)
                    VALUES (:tutor_id, :admin_id, :estado_anterior, :estado_nuevo, :motivo_rechazo, :fecha_cambio)
                ");
                $logStmt->execute([
                    ':tutor_id' => $tutorId,
                    ':admin_id' => $adminId,
                    ':estado_anterior' => $tutorData['estado_registro'],
                    ':estado_nuevo' => $newStatus,
                    ':motivo_rechazo' => ($newStatus === 'rechazado' ? $rejectionReason : null),
                    ':fecha_cambio' => $fecha_procesamiento
                ]);
            } catch (PDOException $e) {
                // Si el log es CRÍTICO, deberías hacer rollback y fallar la operación.
                // $conexion->rollBack();
                // error_log("CRÍTICO: No se pudo registrar en logs_cambios_tutores - " . $e->getMessage());
                // http_response_code(500);
                // echo json_encode(['success' => false, 'message' => 'Error crítico al registrar el cambio.', 'error_code' => 'LOGGING_FAILED']);
                // exit;
                
                // Si el log NO es crítico (como en tu script original):
                error_log("Advertencia: No se pudo registrar en logs_cambios_tutores - " . $e->getMessage());
            }
        }
        // Aquí se podría implementar el envío de notificaciones por email al tutor.

        $conexion->commit();

        $adminInfoStmt = $conexion->prepare("SELECT nombre_completo FROM admins WHERE id = :admin_id");
        $adminInfoStmt->execute([':admin_id' => $adminId]);
        $adminInfo = $adminInfoStmt->fetch(PDO::FETCH_ASSOC);

        $response = [
            'success' => true,
            'message' => 'Estado del tutor actualizado correctamente.',
            'tutor_info' => [
                'id' => $tutorId,
                'nombre' => $tutorData['nombre_completo'],
                'correo' => $tutorData['correo'],
                'estado_anterior' => $tutorData['estado_registro'],
                'estado_nuevo' => $newStatus,
                'admin_responsable' => $adminInfo['nombre_completo'] ?? 'Desconocido',
                'fecha_procesamiento' => date('d/m/Y H:i', strtotime($fecha_procesamiento))
            ],
            'timestamp' => $fecha_procesamiento // Usar la fecha de procesamiento consistente
        ];

        if ($newStatus === 'rechazado') {
            $response['tutor_info']['motivo_rechazo'] = $rejectionReason;
        }

        echo json_encode($response);

    } else {
        $conexion->rollBack();
        // Podría ser que no hubo error, sino que no se afectaron filas (ej. condición WHERE no coincidió después de todo)
        http_response_code(500); // o 400 si es más un error de lógica de no encontrar/actualizar
        echo json_encode(['success' => false, 'message' => 'No se pudo actualizar el estado del tutor o no hubo cambios.', 'error_code' => 'UPDATE_FAILED_NO_ROWS']);
    }

} catch (PDOException $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    error_log("Error de BD en change_tutor_status.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos.', 'error_code' => 'DATABASE_ERROR']);
} catch (Exception $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    error_log("Error general en change_tutor_status.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage(), 'error_code' => 'INTERNAL_SERVER_ERROR']);
}
?>