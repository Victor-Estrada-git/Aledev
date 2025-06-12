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

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    if (!$student_id || $student_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de alumno no válido']);
        exit;
    }

    // Consulta mejorada con campos específicos y manejo de datos
    $stmt = $conexion->prepare("
        SELECT 
            id,
            nombre_completo,
            nombre_usuario,
            carrera,
            boleta,
            numero_celular,
            correo,
            ruta_credencial_horario,
            fecha_registro,
            DATE_FORMAT(fecha_registro, '%d/%m/%Y %H:%i') as fecha_registro_formateada
        FROM alumnos 
        WHERE id = ?
    ");
    
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        // Convertir carrera a nombre completo para mejor presentación
        $carreras = [
            'ISC' => 'Ingeniería en Sistemas Computacionales',
            'IIA' => 'Ingeniería en Inteligencia Artificial',
            'LCD' => 'Licenciatura en Ciencia de Datos'
        ];
        
        $student['carrera_completa'] = $carreras[$student['carrera']] ?? $student['carrera'];
        
        // Verificar si tiene credencial/horario subido
        $student['tiene_credencial'] = !empty($student['ruta_credencial_horario']);
        
        echo json_encode([
            'success' => true, 
            'student' => $student,
            'timestamp' => time()
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Alumno no encontrado']);
    }
    
} catch (PDOException $e) {
    error_log("Error en student_detail.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos']);
} catch (Exception $e) {
    error_log("Error general en student_detail.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>