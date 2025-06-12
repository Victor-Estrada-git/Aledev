<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once '../conexion_db.php';

$tutor_id = isset($_POST['tutor_id']) ? (int)$_POST['tutor_id'] : 0;
$accion = isset($_POST['accion']) ? trim($_POST['accion']) : '';
$motivo_rechazo = isset($_POST['motivo_rechazo']) ? trim($_POST['motivo_rechazo']) : '';
$admin_id = $_SESSION['admin_id'];

try {
    // Validaciones
    if (!$tutor_id || $tutor_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de tutor no válido']);
        exit;
    }
    
    if (!in_array($accion, ['aprobar', 'rechazar'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        exit;
    }
    
    if ($accion === 'rechazar' && empty($motivo_rechazo)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El motivo de rechazo es requerido']);
        exit;
    }
    
    // Verificar que el tutor existe y está pendiente
    $stmt = $conexion->prepare("SELECT id, nombre_completo, correo, estado_registro FROM tutores WHERE id = ?");
    $stmt->execute([$tutor_id]);
    $tutor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tutor) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Tutor no encontrado']);
        exit;
    }
    
    if ($tutor['estado_registro'] !== 'pendiente') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El tutor ya ha sido procesado anteriormente']);
        exit;
    }
    
    // Iniciar transacción
    $conexion->beginTransaction();
    
    try {
        if ($accion === 'aprobar') {
            // Aprobar tutor
            $stmt = $conexion->prepare("
                UPDATE tutores 
                SET estado_registro = 'aprobado', 
                    fecha_aprobacion = NOW(), 
                    admin_aprobador = ?,
                    motivo_rechazo = NULL
                WHERE id = ?
            ");
            $stmt->execute([$admin_id, $tutor_id]);
            
            $mensaje = 'Tutor aprobado correctamente';
            $nuevo_estado = 'aprobado';
            
        } else {
            // Rechazar tutor
            $stmt = $conexion->prepare("
                UPDATE tutores 
                SET estado_registro = 'rechazado', 
                    motivo_rechazo = ?,
                    admin_aprobador = ?,
                    fecha_aprobacion = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$motivo_rechazo, $admin_id, $tutor_id]);
            
            $mensaje = 'Tutor rechazado correctamente';
            $nuevo_estado = 'rechazado';
        }
        
        // Verificar que se actualizó
        if ($stmt->rowCount() === 0) {
            throw new Exception('No se pudo actualizar el estado del tutor');
        }
        
        // Confirmar transacción
        $conexion->commit();
        
        // Obtener información del admin para el log
        $stmt = $conexion->prepare("SELECT nombre_completo FROM admins WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Preparar respuesta
        $response = [
            'success' => true,
            'message' => $mensaje,
            'tutor' => [
                'id' => $tutor_id,
                'nombre_completo' => $tutor['nombre_completo'],
                'correo' => $tutor['correo'],
                'estado_anterior' => 'pendiente',
                'estado_nuevo' => $nuevo_estado,
                'admin_aprobador' => $admin['nombre_completo'] ?? 'Desconocido',
                'fecha_procesamiento' => date('d/m/Y H:i')
            ],
            'timestamp' => time()
        ];
        
        if ($accion === 'rechazar') {
            $response['tutor']['motivo_rechazo'] = $motivo_rechazo;
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        $conexion->rollback();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Error en approve_reject_tutor.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos']);
} catch (Exception $e) {
    error_log("Error general en approve_reject_tutor.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>