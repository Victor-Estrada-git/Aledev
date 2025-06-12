<?php
// php/api/edit_student.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS'); // PUT es alias de POST aquí
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.', 'error_code' => 'UNAUTHORIZED']);
    exit;
}

require_once '../conexion_db.php';
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        handleGetStudent($conexion);
    } elseif ($method === 'POST' || $method === 'PUT') { // PUT se trata como POST
        handleUpdateStudent($conexion);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido', 'error_code' => 'METHOD_NOT_ALLOWED']);
    }
} catch (Exception $e) {
    error_log("Error en edit_student.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage(), 'error_code' => 'INTERNAL_ERROR']);
}

function handleGetStudent($conexion_db_conn) {
    try {
        $studentId = intval($_GET['id'] ?? 0);
        if ($studentId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID de alumno inválido.', 'error_code' => 'INVALID_ID']);
            return;
        }

        $stmt = $conexion_db_conn->prepare("
            SELECT id, nombre_completo, nombre_usuario, carrera, boleta, numero_celular, correo,
                   ruta_credencial_horario, fecha_registro,
                   DATE_FORMAT(fecha_registro, '%d/%m/%Y %H:%i') as fecha_registro_formatted
            FROM alumnos WHERE id = :student_id
        ");
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student) {
            $student['tiene_credencial'] = !empty($student['ruta_credencial_horario']);
            // Aquí podrías añadir más lógica, como el estado de la cuenta si existe.
            // $student['estado_cuenta'] = $student['is_active'] ? 'Activo' : 'Inactivo';
            echo json_encode(['success' => true, 'student' => $student]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Alumno no encontrado', 'error_code' => 'STUDENT_NOT_FOUND']);
        }
    } catch (PDOException $e) {
        throw new Exception('Error en la consulta del estudiante: ' . $e->getMessage());
    }
}

function handleUpdateStudent($conexion_db_conn) {
    try {
        // Los datos vienen de FormData en el JS
        $studentId = intval($_POST['student_id'] ?? 0);
        $nombreCompleto = trim($_POST['nombre_completo'] ?? '');
        $nombreUsuario = trim($_POST['nombre_usuario'] ?? '');
        $carrera = trim($_POST['carrera'] ?? '');
        $boleta = trim($_POST['boleta'] ?? '');
        $numeroCelular = trim($_POST['numero_celular'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        // $nuevaContrasenaEstudiante = trim($_POST['nueva_contrasena_estudiante'] ?? ''); // Si se añade cambio de pass

        // Validaciones básicas
        if ($studentId <= 0) throw new InvalidArgumentException("ID de alumno inválido.", 400);
        $requiredFields = compact('nombreCompleto', 'nombreUsuario', 'carrera', 'boleta', 'numeroCelular', 'correo');
        foreach ($requiredFields as $field => $value) {
            if (empty($value)) throw new InvalidArgumentException("El campo '$field' es obligatorio.", 400);
        }

        $validCareers = ['ISC', 'IIA', 'LCD'];
        if (!in_array($carrera, $validCareers)) throw new InvalidArgumentException("Carrera inválida.", 400);
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException("Formato de correo inválido.", 400);
        if (!preg_match('/^\d{10}$/', $numeroCelular)) throw new InvalidArgumentException("Número de celular debe tener 10 dígitos.", 400);
        if (!preg_match('/^\d{10}$/', $boleta)) throw new InvalidArgumentException("La boleta debe tener 10 dígitos.", 400);

        $checkStmt = $conexion_db_conn->prepare("SELECT id FROM alumnos WHERE id = :student_id");
        $checkStmt->execute([':student_id' => $studentId]);
        if (!$checkStmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Alumno no encontrado', 'error_code' => 'STUDENT_NOT_FOUND']);
            return;
        }

        // Verificar duplicados (excluyendo el estudiante actual)
        $fieldsToValidate = [
            'nombre_usuario' => $nombreUsuario,
            'correo' => $correo,
            'boleta' => $boleta
        ];
        foreach($fieldsToValidate as $field => $value) {
            $duplicateStmt = $conexion_db_conn->prepare("SELECT id FROM alumnos WHERE $field = :value AND id != :student_id");
            $duplicateStmt->execute([':value' => $value, ':student_id' => $studentId]);
            if ($duplicateStmt->fetch()) {
                 http_response_code(409); // Conflict
                 echo json_encode(['success' => false, 'message' => "Ya existe otro alumno con el mismo {$field}.", 'error_code' => 'DUPLICATE_FIELD', 'field' => $field]);
                 return;
            }
        }

        $updateSqlParts = [
            "nombre_completo = :nombre_completo",
            "nombre_usuario = :nombre_usuario",
            "carrera = :carrera",
            "boleta = :boleta",
            "numero_celular = :numero_celular",
            "correo = :correo"
        ];
        $updateParams = [
            ':nombre_completo' => $nombreCompleto,
            ':nombre_usuario' => $nombreUsuario,
            ':carrera' => $carrera,
            ':boleta' => $boleta,
            ':numero_celular' => $numeroCelular,
            ':correo' => $correo,
            ':student_id' => $studentId
        ];

        
        // if (!empty($nuevaContrasenaEstudiante)) {
        //     if(strlen($nuevaContrasenaEstudiante) < 6) throw new InvalidArgumentException("La contraseña debe tener al menos 6 caracteres.", 400);
        //     $updateSqlParts[] = "hash_contrasena = :hash_contrasena";
        //     $updateParams[':hash_contrasena'] = password_hash($nuevaContrasenaEstudiante, PASSWORD_DEFAULT);
        // }
        // Opcional: Actualizar estado de cuenta
        // $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1; // Asumir 1 si no se envía
        // $updateSqlParts[] = "is_active = :is_active";
        // $updateParams[':is_active'] = $is_active;


        $updateSql = "UPDATE alumnos SET " . implode(", ", $updateSqlParts) . " WHERE id = :student_id";
        $updateStmt = $conexion_db_conn->prepare($updateSql);
        $updateStmt->execute($updateParams);

        if ($updateStmt->rowCount() > 0) {
            $getUpdatedStmt = $conexion_db_conn->prepare("SELECT id, nombre_completo, nombre_usuario, carrera, boleta, numero_celular, correo FROM alumnos WHERE id = :student_id");
            $getUpdatedStmt->execute([':student_id' => $studentId]);
            $updatedStudent = $getUpdatedStmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'message' => 'Alumno actualizado correctamente', 'student' => $updatedStudent]);
        } else {
            echo json_encode(['success' => true, 'message' => 'No se realizaron cambios (datos idénticos).', 'no_changes' => true]);
        }

    } catch (InvalidArgumentException $e) {
        http_response_code($e->getCode() == 0 ? 400 : $e->getCode()); // Usar código de la excepción si es válido
        echo json_encode(['success' => false, 'message' => $e->getMessage(), 'error_code' => 'VALIDATION_ERROR']);
    } catch (PDOException $e) {
        throw new Exception('Error en la actualización del estudiante: ' . $e->getMessage());
    }
}
?>