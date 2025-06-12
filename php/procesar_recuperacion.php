<?php
require_once 'conexion_db.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Error desconocido.'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = isset($_POST['correo']) ? trim($_POST['correo']) : '';

    if (empty($correo) || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Por favor, ingresa un correo electrónico válido.';
        echo json_encode($response);
        $conn->close();
        exit();
    }

    $email_exists = false;

    // Comprobar en admins
    $stmt = $conn->prepare("SELECT id FROM admins WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) $email_exists = true;
    $stmt->close();

    // Comprobar en alumnos si no se encontró
    if (!$email_exists) {
        $stmt = $conn->prepare("SELECT id FROM alumnos WHERE correo = ?");
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $email_exists = true;
        $stmt->close();
    }

    // Comprobar en tutores si no se encontró
    if (!$email_exists) {
        $stmt = $conn->prepare("SELECT id FROM tutores WHERE correo = ?");
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $email_exists = true;
        $stmt->close();
    }

    if ($email_exists) {
        $response['success'] = true;
        // MENSAJE IMPORTANTE: NO SE ENVÍA LA CONTRASEÑA REAL.
        // ESTO ES UNA SIMULACIÓN. En un sistema real, aquí se generaría un token
        // y se enviaría un correo con un enlace para *restablecer* la contraseña.
        // Nunca se debe enviar la contraseña (hasheada o no) por correo.
        $response['message'] = "Si '$correo' está registrado, se han enviado (simulado) instrucciones para restablecer tu contraseña. Por favor, revisa tu bandeja de entrada (y spam).";
        
        // Aquí iría la lógica para generar un token y enviar el correo REAL.
        // Ejemplo: generar_token_y_enviar_correo_reset($correo, $conn);

    } else {
        // Mensaje genérico para no revelar si un correo existe o no (puede ser una preferencia de seguridad)
        // O un mensaje más directo si se prefiere:
        // $response['message'] = "El correo electrónico no fue encontrado en nuestros registros.";
         $response['message'] = "Si '$correo' está registrado, se han enviado (simulado) instrucciones para restablecer tu contraseña. Por favor, revisa tu bandeja de entrada (y spam).";
    }

} else {
    $response['message'] = 'Método de solicitud no válido.';
}

$conn->close();
echo json_encode($response);
?>