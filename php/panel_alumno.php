<?php
session_start();
header('Content-Type: application/json');

// Verificar que el usuario sea un alumno y esté logueado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'alumno') {
    http_response_code(401); // No autorizado
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado. Por favor, inicie sesión.']);
    exit;
}

require_once 'conexion_db.php'; // Incluye la conexión a la BD ($conexion)

if ($conexion === null) {
    http_response_code(503); // Servicio no disponible
    echo json_encode(['success' => false, 'message' => 'Error crítico de conexión a la base de datos.']);
    exit;
}

$alumno_id = $_SESSION['user_id'];
$request_method = $_SERVER['REQUEST_METHOD'];
$action = '';

if ($request_method === 'GET') {
    $action = $_GET['accion'] ?? '';
} elseif ($request_method === 'POST') {
    $action = $_POST['accion'] ?? '';
}

try {
    switch ($action) {
        // --- Acciones GET ---
        case 'get_datos_iniciales':
            $stmt_perfil = $conexion->prepare("SELECT nombre_completo, correo, carrera, semestre, boleta, numero_celular FROM alumnos WHERE id = ?");
            $stmt_perfil->execute([$alumno_id]);
            $perfil = $stmt_perfil->fetch(PDO::FETCH_ASSOC);

            $stmt_stats = $conexion->prepare("SELECT (SELECT COUNT(*) FROM citas WHERE alumno_id = ? AND estado = 'completada') as horas_tutoria, (SELECT COUNT(DISTINCT materia_tema) FROM citas WHERE alumno_id = ? AND estado = 'completada') as materias_estudiadas, (SELECT AVG(calificacion) FROM calificaciones WHERE alumno_id = ?) as calificacion_promedio, (SELECT COUNT(DISTINCT tutor_id) FROM citas WHERE alumno_id = ?) as tutores_diferentes");
            $stmt_stats->execute([$alumno_id, $alumno_id, $alumno_id, $alumno_id]);
            $estadisticas = $stmt_stats->fetch(PDO::FETCH_ASSOC);
            
            $estadisticas['horas_tutoria'] = (int) $estadisticas['horas_tutoria'];
            $estadisticas['materias_estudiadas'] = (int) $estadisticas['materias_estudiadas'];
            $estadisticas['calificacion_promedio'] = number_format((float)$estadisticas['calificacion_promedio'], 1);
            $estadisticas['tutores_diferentes'] = (int) $estadisticas['tutores_diferentes'];

            echo json_encode(['success' => true, 'data' => ['perfil' => $perfil, 'estadisticas' => $estadisticas]]);
            break;

        case 'get_citas':
            $stmt = $conexion->prepare("SELECT c.id, c.tutor_id, t.nombre_completo as tutor_nombre, c.fecha_hora, c.tipo_solicitado as tipo, c.materia_tema, c.estado, c.notas_alumno as detalles FROM citas c JOIN tutores t ON c.tutor_id = t.id WHERE c.alumno_id = ? ORDER BY c.fecha_hora DESC");
            $stmt->execute([$alumno_id]);
            $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $citas]);
            break;

        case 'get_billetera':
             $conexion->prepare("INSERT IGNORE INTO billeteras (alumno_id, saldo) VALUES (?, 0.00)")->execute([$alumno_id]);
            $stmt_saldo = $conexion->prepare("SELECT saldo FROM billeteras WHERE alumno_id = ?");
            $stmt_saldo->execute([$alumno_id]);
            $saldo = $stmt_saldo->fetchColumn();
            $stmt_trans = $conexion->prepare("SELECT t.tipo, t.monto, t.descripcion, t.fecha FROM transacciones t JOIN billeteras b ON t.billetera_id = b.id WHERE b.alumno_id = ? ORDER BY t.fecha DESC LIMIT 20");
            $stmt_trans->execute([$alumno_id]);
            $transacciones = $stmt_trans->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => ['saldo' => number_format($saldo, 2), 'transacciones' => $transacciones]]);
            break;
        
        case 'get_quejas':
            $stmt = $conexion->prepare("SELECT q.asunto, q.descripcion, q.estado, q.resolucion, q.fecha_creacion as fecha, t.nombre_completo as tutor_nombre FROM quejas q LEFT JOIN tutores t ON q.destinatario_id = t.id AND q.destinatario_tipo = 'tutor' WHERE q.remitente_id = ? AND q.remitente_tipo = 'alumno' ORDER BY q.fecha_creacion DESC");
            $stmt->execute([$alumno_id]);
            $quejas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $quejas]);
            break;

        case 'buscar_tutores':
            $materia = trim($_GET['materia'] ?? '');
            $params = [];
            $sql = "SELECT id, nombre_completo, nivel_experiencia, areas_materias, donativo_sugerido_hr, tipo_tutoria, max_tamano_grupo FROM tutores WHERE estado_registro = 'aprobado'";
            
            if (!empty($materia)) {
                $sql .= " AND areas_materias LIKE ?";
                $params[] = "%" . $materia . "%";
            }
            
            $stmt = $conexion->prepare($sql);
            $stmt->execute($params);
            $tutores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $tutores]);
            break;

        case 'get_datos_queja':
            $stmt_tutores = $conexion->prepare("SELECT DISTINCT t.id, t.nombre_completo FROM tutores t JOIN citas c ON t.id = c.tutor_id WHERE c.alumno_id = ? ORDER BY t.nombre_completo ASC");
            $stmt_tutores->execute([$alumno_id]);
            $tutores = $stmt_tutores->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt_citas = $conexion->prepare("SELECT c.id, c.materia_tema, c.fecha_hora, t.nombre_completo as tutor_nombre FROM citas c JOIN tutores t ON c.tutor_id = t.id WHERE c.alumno_id = ? AND c.estado IN ('completada', 'confirmada', 'cancelada_tutor') ORDER BY c.fecha_hora DESC");
            $stmt_citas->execute([$alumno_id]);
            $citas = $stmt_citas->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => ['tutores' => $tutores, 'citas' => $citas]]);
            break;

        // --- Acciones POST ---
        case 'update_perfil':
            $telefono = filter_var($_POST['telefono'], FILTER_SANITIZE_STRING);
            if (strlen($telefono) == 10 && ctype_digit($telefono)) {
                $stmt = $conexion->prepare("UPDATE alumnos SET numero_celular = ? WHERE id = ?");
                $stmt->execute([$telefono, $alumno_id]);
                echo json_encode(['success' => true, 'message' => 'Perfil actualizado correctamente.']);
            } else {
                 echo json_encode(['success' => false, 'message' => 'El número de teléfono no es válido.']);
            }
            break;

        case 'agendar_cita':
            $tutor_id = (int)$_POST['tutor_id'];
            $fecha_hora = trim($_POST['fecha_hora']);
            $materia = trim(filter_var($_POST['materia_tema'], FILTER_SANITIZE_STRING));
            $notas = trim(filter_var($_POST['notas_alumno'], FILTER_SANITIZE_STRING));
            $tipo = trim($_POST['tipo_solicitado']);

            if (empty($tutor_id) || empty($fecha_hora) || empty($materia) || empty($tipo)) {
                throw new Exception('Faltan datos para agendar la cita.');
            }
            
            $stmt = $conexion->prepare("INSERT INTO citas (alumno_id, tutor_id, fecha_hora, materia_tema, notas_alumno, tipo_solicitado, estado) VALUES (?, ?, ?, ?, ?, ?, 'pendiente')");
            $stmt->execute([$alumno_id, $tutor_id, $fecha_hora, $materia, $notas, $tipo]);

            echo json_encode(['success' => true, 'message' => '¡Solicitud de cita enviada! Recibirás una notificación cuando el tutor la confirme.']);
            break;

        case 'enviar_queja':
            $destinatario_id = (int)$_POST['destinatario_id'];
            $cita_id = !empty($_POST['cita_id']) ? (int)$_POST['cita_id'] : null;
            $asunto = trim(filter_var($_POST['asunto'], FILTER_SANITIZE_STRING));
            $descripcion = trim(filter_var($_POST['descripcion'], FILTER_SANITIZE_STRING));
            
            if (empty($destinatario_id) || empty($asunto) || empty($descripcion)) {
                throw new Exception('Faltan datos para enviar la queja. El tutor, asunto y descripción son obligatorios.');
            }

            $stmt = $conexion->prepare("INSERT INTO quejas (remitente_id, remitente_tipo, destinatario_id, destinatario_tipo, cita_id, asunto, descripcion) VALUES (?, 'alumno', ?, 'tutor', ?, ?, ?)");
            $stmt->execute([$alumno_id, $destinatario_id, $cita_id, $asunto, $descripcion]);

            echo json_encode(['success' => true, 'message' => 'Queja enviada correctamente. El equipo administrativo la revisará a la brevedad.']);
            break;

        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Acción no encontrada.']);
            break;
    }
} catch (Exception $e) {
    error_log("Error en panel_alumno.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error Interno: '.$e->getMessage()]);
}
?>