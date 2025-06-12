<?php
// Configuración para mostrar errores durante desarrollo
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Configurar headers para respuesta JSON
header('Content-Type: application/json');

// Incluir archivo de conexión a la base de datos
require_once 'conexion_db.php';

// Verificar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Obtener y sanitizar datos del formulario
    $tipo_usuario = sanitize_input($_POST['tipo_usuario'] ?? '');
    $nombre_completo = sanitize_input($_POST['nombre_completo'] ?? '');
    $nombre_usuario = sanitize_input($_POST['nombre_usuario'] ?? '');
    $correo = sanitize_input($_POST['correo'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';
    $confirmar_contrasena = $_POST['confirmar_contrasena'] ?? '';

    // Validaciones del lado del servidor
    if (empty($tipo_usuario) || empty($nombre_completo) || empty($nombre_usuario) || 
        empty($correo) || empty($contrasena) || empty($confirmar_contrasena)) {
        throw new Exception('Todos los campos obligatorios deben estar llenos.');
    }

    // Validar formato de correo
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El formato del correo electrónico no es válido.');
    }

    // Validar longitud de contraseña
    if (strlen($contrasena) < 8) {
        throw new Exception('La contraseña debe tener al menos 8 caracteres.');
    }

    // Validar que las contraseñas coincidan
    if ($contrasena !== $confirmar_contrasena) {
        throw new Exception('Las contraseñas no coinciden.');
    }

    // Verificar que el nombre de usuario y correo no existan ya
    $stmt = $conexion->prepare("
        SELECT 'admin' as tipo FROM admins WHERE nombre_usuario = ? OR correo = ?
        UNION
        SELECT 'alumno' as tipo FROM alumnos WHERE nombre_usuario = ? OR correo = ?
        UNION
        SELECT 'tutor' as tipo FROM tutores WHERE nombre_usuario = ? OR correo = ?
    ");
    $stmt->execute([$nombre_usuario, $correo, $nombre_usuario, $correo, $nombre_usuario, $correo]);
    
    if ($stmt->rowCount() > 0) {
        throw new Exception('El nombre de usuario o correo electrónico ya están registrados.');
    }

    // Hashear la contraseña
    $hash_contrasena = password_hash($contrasena, PASSWORD_DEFAULT);

    // Procesar según el tipo de usuario
    if ($tipo_usuario === 'alumno') {
        procesarRegistroAlumno($conexion, $nombre_completo, $nombre_usuario, $correo, $hash_contrasena);
    } elseif ($tipo_usuario === 'tutor') {
        procesarRegistroTutor($conexion, $nombre_completo, $nombre_usuario, $correo, $hash_contrasena);
    } else {
        throw new Exception('Tipo de usuario no válido.');
    }

    echo json_encode(['success' => true, 'message' => 'Registro exitoso. Redirigiendo al login...']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    error_log("Error de base de datos: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor. Inténtalo más tarde.']);
}

/**
 * Función para procesar registro de alumno
 */
function procesarRegistroAlumno($conexion, $nombre_completo, $nombre_usuario, $correo, $hash_contrasena) {
    // Obtener y validar datos específicos de alumno
    $carrera = sanitize_input($_POST['carrera'] ?? '');
    $boleta = sanitize_input($_POST['boleta'] ?? '');
    $numero_celular = sanitize_input($_POST['numero_celular'] ?? '');

    // Validaciones específicas
    if (empty($carrera) || empty($boleta) || empty($numero_celular)) {
        throw new Exception('Todos los campos de alumno son obligatorios.');
    }

    if (!preg_match('/^\d{10}$/', $boleta)) {
        throw new Exception('La boleta debe tener exactamente 10 dígitos.');
    }

    if (!preg_match('/^\d{10}$/', $numero_celular)) {
        throw new Exception('El número celular debe tener exactamente 10 dígitos.');
    }

    // Verificar que la boleta no esté registrada
    $stmt = $conexion->prepare("SELECT id FROM alumnos WHERE boleta = ?");
    $stmt->execute([$boleta]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('La boleta ya está registrada.');
    }

    // Procesar archivo de credencial/horario
    $ruta_credencial = null;
    if (isset($_FILES['credencial_horario']) && $_FILES['credencial_horario']['error'] === UPLOAD_ERR_OK) {
        $ruta_credencial = handle_file_upload($_FILES['credencial_horario'], ['application/pdf'], 'credencial_');
    } else {
        throw new Exception('El archivo de credencial u horario es obligatorio.');
    }

    // Insertar alumno en la base de datos
    $stmt = $conexion->prepare("
        INSERT INTO alumnos (nombre_completo, nombre_usuario, carrera, boleta, numero_celular, 
                           correo, ruta_credencial_horario, hash_contrasena) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $nombre_completo, $nombre_usuario, $carrera, $boleta, 
        $numero_celular, $correo, $ruta_credencial, $hash_contrasena
    ]);
}

/**
 * Función para procesar registro de tutor
 */
function procesarRegistroTutor($conexion, $nombre_completo, $nombre_usuario, $correo, $hash_contrasena) {
    // Obtener y validar datos específicos de tutor
    $areas_materias = sanitize_input($_POST['areas_materias'] ?? '');
    $nivel_experiencia = sanitize_input($_POST['nivel_experiencia'] ?? '');
    $tiene_certificacion = isset($_POST['tiene_certificacion']) ? (int)$_POST['tiene_certificacion'] : null;
    $explicacion_habilidades = sanitize_input($_POST['explicacion_habilidades'] ?? '');
    $telefono = sanitize_input($_POST['telefono'] ?? '');
    $horarios_disponibles = sanitize_input($_POST['horarios_disponibles'] ?? '');

    // Validaciones específicas
    if (empty($areas_materias) || empty($nivel_experiencia) || $tiene_certificacion === null || 
        empty($explicacion_habilidades) || empty($horarios_disponibles)) {
        throw new Exception('Todos los campos obligatorios de tutor deben estar llenos.');
    }

    $niveles_validos = ['Estudiante universitario', 'Licenciado', 'Posgrado', 'Profesor', 'Doctorado'];
    if (!in_array($nivel_experiencia, $niveles_validos)) {
        throw new Exception('Nivel de experiencia no válido.');
    }

    // Validar teléfono opcional
    if (!empty($telefono) && !preg_match('/^\d{10}$/', $telefono)) {
        throw new Exception('El número de teléfono debe tener exactamente 10 dígitos.');
    }

    // Procesar archivo de certificación si es necesario
    $ruta_documentos = null;
    if ($tiene_certificacion === 1) {
        if (isset($_FILES['documentos_certificacion']) && $_FILES['documentos_certificacion']['error'] === UPLOAD_ERR_OK) {
            $tipos_permitidos = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            $ruta_documentos = handle_file_upload($_FILES['documentos_certificacion'], $tipos_permitidos, 'cert_');
        } else {
            throw new Exception('Los documentos de certificación son obligatorios cuando se indica que se tiene certificación.');
        }
    }

    // Insertar tutor en la base de datos
    $stmt = $conexion->prepare("
        INSERT INTO tutores (nombre_completo, nombre_usuario, correo, areas_materias, nivel_experiencia,
                           tiene_certificacion, ruta_documentos_certificacion, explicacion_habilidades,
                           telefono, horarios_disponibles, hash_contrasena) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $nombre_completo, $nombre_usuario, $correo, $areas_materias, $nivel_experiencia,
        $tiene_certificacion, $ruta_documentos, $explicacion_habilidades,
        !empty($telefono) ? $telefono : null, $horarios_disponibles, $hash_contrasena
    ]);
}

/**
 * Función para manejar la subida de archivos
 */
function handle_file_upload($file, $tipos_permitidos, $prefijo = '') {
    // Verificar errores de subida
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir el archivo.');
    }

    // Verificar tipo de archivo
    if (!in_array($file['type'], $tipos_permitidos)) {
        throw new Exception('Tipo de archivo no permitido.');
    }

    // Verificar tamaño (máximo 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        throw new Exception('El archivo es demasiado grande. Máximo 5MB.');
    }

    // Generar nombre único para el archivo
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $nombre_archivo = $prefijo . uniqid() . '_' . time() . '.' . $extension;
    
    // Definir ruta de destino (relativa al script PHP)
    $directorio_uploads = '../uploads/';
    
    // Crear directorio si no existe
    if (!is_dir($directorio_uploads)) {
        if (!mkdir($directorio_uploads, 0755, true)) {
            throw new Exception('No se pudo crear el directorio de uploads.');
        }
    }

    $ruta_destino = $directorio_uploads . $nombre_archivo;

    // Mover archivo subido
    if (!move_uploaded_file($file['tmp_name'], $ruta_destino)) {
        throw new Exception('Error al guardar el archivo.');
    }

    // Retornar ruta relativa para guardar en la base de datos
    return 'uploads/' . $nombre_archivo;
}
?>