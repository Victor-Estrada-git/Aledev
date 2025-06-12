<?php
// Archivo: php/procesar_login.php

// RECOMENDACIÓN: En producción, gestiona los errores de forma más controlada.
// ini_set('display_errors', 0);
// error_reporting(E_ALL);
// ini_set('log_errors', 1);
// ini_set('error_log', '/ruta/a/tu/servidor/php-error.log');

session_start(); //

header('Content-Type: application/json'); //

require_once 'conexion_db.php'; // Define $conexion

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { //
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Método no permitido. Se esperaba POST.']);
    exit;
}

try {
    $usuario_input = trim($_POST['usuario'] ?? ''); //
    $contrasena_input = $_POST['contrasena'] ?? ''; //

    if (empty($usuario_input) || empty($contrasena_input)) { //
        http_response_code(400); 
        echo json_encode(['success' => false, 'message' => 'Por favor, completa todos los campos.']); //
        exit;
    }

    $user_data = verificarCredenciales($conexion, $usuario_input, $contrasena_input); //

    if ($user_data) { //
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user_data['id']; //
        $_SESSION['user_type'] = $user_data['tipo']; //
        $_SESSION['nombre_usuario'] = $user_data['nombre_usuario']; //
        $_SESSION['nombre_completo'] = $user_data['nombre_completo'] ?? $user_data['nombre_usuario']; //

        if ($user_data['tipo'] === 'admin') { //
            $_SESSION['admin_id'] = $user_data['id']; 
        }

        $redirect_url = ''; //
        switch ($user_data['tipo']) { //
            case 'admin': //
                $redirect_url = '../html/panel_admin.html'; //
                break;
            case 'alumno': //
                $redirect_url = '../html/panel_alumno.html'; // Cambiado a .html
                break;
            case 'tutor': //
                $redirect_url = '../html/panel_tutor.html';  // Cambiado a .html
                break;
            default:
                $redirect_url = '../html/index.html'; 
        }

        http_response_code(200); 
        echo json_encode([ //
            'success' => true, //
            'message' => 'Inicio de sesión exitoso. Redirigiendo...', //
            'redirect_url' => $redirect_url, //
            'user_type' => $user_data['tipo']
        ]);

    } else {
        http_response_code(401); 
        echo json_encode(['success' => false, 'message' => 'Credenciales incorrectas. Por favor, verifica tu usuario y contraseña.']); //
    }

} catch (Exception $e) { //
    if (http_response_code() === 200) { 
        http_response_code(400); 
    }
    error_log("Excepción en login (procesar_login.php): " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]); //
} catch (PDOException $e) { //
    error_log("Error de base de datos en login (procesar_login.php): " . $e->getMessage()); //
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor. No se pudo procesar tu solicitud.']); //
}

function verificarCredenciales(PDO $db_conn, string $usuario_login, string $contrasena_login) { //
    $stmt_admin = $db_conn->prepare("SELECT id, nombre_usuario, nombre_completo, hash_contrasena FROM admins WHERE nombre_usuario = :usuario_login OR correo = :usuario_login"); //
    $stmt_admin->execute([':usuario_login' => $usuario_login]);
    $admin = $stmt_admin->fetch(PDO::FETCH_ASSOC); //

    if ($admin && password_verify($contrasena_login, $admin['hash_contrasena'])) { //
        return [ //
            'id' => $admin['id'], //
            'tipo' => 'admin', //
            'nombre_usuario' => $admin['nombre_usuario'], //
            'nombre_completo' => $admin['nombre_completo'] 
        ];
    }

    $stmt_alumno = $db_conn->prepare("SELECT id, nombre_usuario, nombre_completo, hash_contrasena FROM alumnos WHERE nombre_usuario = :usuario_login OR correo = :usuario_login"); //
    $stmt_alumno->execute([':usuario_login' => $usuario_login]);
    $alumno = $stmt_alumno->fetch(PDO::FETCH_ASSOC); //

    if ($alumno && password_verify($contrasena_login, $alumno['hash_contrasena'])) { //
        return [ //
            'id' => $alumno['id'], //
            'tipo' => 'alumno', //
            'nombre_usuario' => $alumno['nombre_usuario'], //
            'nombre_completo' => $alumno['nombre_completo'] //
        ];
    }

    $stmt_tutor = $db_conn->prepare("SELECT id, nombre_usuario, nombre_completo, hash_contrasena, estado_registro, motivo_rechazo FROM tutores WHERE nombre_usuario = :usuario_login OR correo = :usuario_login"); //
    $stmt_tutor->execute([':usuario_login' => $usuario_login]);
    $tutor = $stmt_tutor->fetch(PDO::FETCH_ASSOC); //

    if ($tutor && password_verify($contrasena_login, $tutor['hash_contrasena'])) { //
        switch ($tutor['estado_registro']) { //
            case 'aprobado': //
                return [ //
                    'id' => $tutor['id'], //
                    'tipo' => 'tutor', //
                    'nombre_usuario' => $tutor['nombre_usuario'], //
                    'nombre_completo' => $tutor['nombre_completo'] //
                ];
            case 'pendiente': //
                http_response_code(403); 
                throw new Exception('Tu registro como tutor aún está en revisión. Por favor, espera la aprobación.'); //
            case 'rechazado': //
                $mensaje_rechazo = 'Tu registro como tutor ha sido rechazado.'; //
                if (!empty($tutor['motivo_rechazo'])) { //
                    $mensaje_rechazo .= ' Motivo: ' . htmlspecialchars($tutor['motivo_rechazo'], ENT_QUOTES, 'UTF-8'); //
                }
                $mensaje_rechazo .= ' Contacta al administrador para más información.'; //
                http_response_code(403); 
                throw new Exception($mensaje_rechazo); //
            default: //
                 http_response_code(500); 
                throw new Exception('El estado de tu registro como tutor no es válido. Contacta al administrador.'); //
        }
    }
    return false; //
}
?>