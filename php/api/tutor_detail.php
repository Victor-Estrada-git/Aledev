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

$tutor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    if (!$tutor_id || $tutor_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de tutor no válido']);
        exit;
    }

    // Consulta mejorada con JOIN para obtener información del admin aprobador
    $stmt = $conexion->prepare("
        SELECT 
            t.*,
            DATE_FORMAT(t.fecha_registro, '%d/%m/%Y %H:%i') as fecha_registro_formateada,
            DATE_FORMAT(t.fecha_aprobacion, '%d/%m/%Y %H:%i') as fecha_aprobacion_formateada,
            a.nombre_completo as admin_aprobador_nombre,
            a.nombre_usuario as admin_aprobador_usuario
        FROM tutores t
        LEFT JOIN admins a ON t.admin_aprobador = a.id
        WHERE t.id = ?
    ");
    
    $stmt->execute([$tutor_id]);
    $tutor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tutor) {
        // Mejorar presentación de datos
        $estados = [
            'pendiente' => 'Pendiente de Aprobación',
            'aprobado' => 'Aprobado',
            'rechazado' => 'Rechazado'
        ];
        
        $niveles = [
            'Estudiante universitario' => 'Estudiante Universitario',
            'Licenciado' => 'Licenciado',
            'Posgrado' => 'Posgrado',
            'Profesor' => 'Profesor',
            'Doctorado' => 'Doctorado'
        ];
        
        $tutor['estado_registro_texto'] = $estados[$tutor['estado_registro']] ?? $tutor['estado_registro'];
        $tutor['nivel_experiencia_texto'] = $niveles[$tutor['nivel_experiencia']] ?? $tutor['nivel_experiencia'];
        $tutor['tiene_certificacion_texto'] = $tutor['tiene_certificacion'] ? 'Sí' : 'No';
        $tutor['tiene_documentos'] = !empty($tutor['ruta_documentos_certificacion']);
        
        // Procesar áreas/materias si están en formato JSON o separadas por comas
        if (!empty($tutor['areas_materias'])) {
            $areas = json_decode($tutor['areas_materias'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($areas)) {
                $tutor['areas_materias_array'] = $areas;
            } else {
                $tutor['areas_materias_array'] = array_map('trim', explode(',', $tutor['areas_materias']));
            }
        } else {
            $tutor['areas_materias_array'] = [];
        }
        
        // Procesar horarios disponibles si están en formato JSON
        if (!empty($tutor['horarios_disponibles'])) {
            $horarios = json_decode($tutor['horarios_disponibles'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($horarios)) {
                $tutor['horarios_disponibles_array'] = $horarios;
            } else {
                $tutor['horarios_disponibles_array'] = [$tutor['horarios_disponibles']];
            }
        } else {
            $tutor['horarios_disponibles_array'] = [];
        }
        
        echo json_encode([
            'success' => true, 
            'tutor' => $tutor,
            'timestamp' => time()
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Tutor no encontrado']);
    }
    
} catch (PDOException $e) {
    error_log("Error en tutor_detail.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos']);
} catch (Exception $e) {
    error_log("Error general en tutor_detail.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>