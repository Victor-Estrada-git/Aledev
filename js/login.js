// Archivo: js/login.js

document.addEventListener('DOMContentLoaded', function() {
    const formulario = document.getElementById('loginForm'); //
    if (formulario) {
        formulario.addEventListener('submit', function(e) { //
            e.preventDefault(); //
            
            if (validarFormulario()) { //
                enviarFormulario(); //
            }
        });
    } else {
        console.error("El formulario de login (loginForm) no se encontró en el DOM.");
    }

    // Añadir listeners a los campos para limpiar mensajes al escribir
    const userInput = document.getElementById('usuario');
    const passInput = document.getElementById('contrasena');
    if (userInput) userInput.addEventListener('input', limpiarMensaje);
    if (passInput) passInput.addEventListener('input', limpiarMensaje);

});

// Función para mostrar/ocultar contraseña
function togglePassword(fieldId) { //
    const field = document.getElementById(fieldId); //
    const toggle = field.nextElementSibling; //
    
    if (field.type === 'password') { //
        field.type = 'text'; //
        toggle.textContent = '🙈'; //
    } else {
        field.type = 'password'; //
        toggle.textContent = '👁'; //
    }
}

// Función de validación del formulario
function validarFormulario() { //
    const usuario = document.getElementById('usuario').value.trim(); //
    const contrasena = document.getElementById('contrasena').value; //

    if (!usuario || !contrasena) { //
        mostrarMensaje('Por favor, completa todos los campos.', 'error'); //
        return false; //
    }
    return true; //
}

// Función para enviar el formulario
function enviarFormulario() { //
    const form = document.getElementById('loginForm'); //
    const formData = new FormData(form); //
    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonText = submitButton.textContent;

    mostrarMensaje('Iniciando sesión...', 'info'); //
    submitButton.disabled = true;
    submitButton.textContent = 'Procesando...';

    fetch('../php/procesar_login.php', { // // ASEGÚRATE QUE ESTA RUTA ES CORRECTA
        method: 'POST', //
        body: formData //
    })
    .then(response => {
        // Guardamos el status para usarlo después si response.json() falla
        const status = response.status;
        if (!response.ok) { //
            // Intentamos leer el cuerpo del error como JSON si es posible
            return response.json().catch(() => {
                // Si no es JSON, creamos un error con el texto de estado o un mensaje genérico
                throw new Error(`Error del servidor: ${status} ${response.statusText || 'No se pudo procesar la respuesta.'}`);
            }).then(errorData => {
                // Si es JSON, usamos el mensaje del servidor, si no, el error que creamos
                const errorMessage = errorData.message || `Error del servidor: ${status} ${response.statusText || 'No se pudo procesar la respuesta.'}`;
                throw new Error(errorMessage);
            });
        }
        return response.json(); //
    })
    .then(data => {
        if (data.success) { //
            mostrarMensaje(data.message, 'success'); //
            setTimeout(() => {
                window.location.href = data.redirect_url; //
            }, 1500); // Un poco más de tiempo para leer el mensaje de éxito
        } else {
            // El PHP ya debería haber establecido un código de error HTTP, pero mostramos su mensaje
            mostrarMensaje(data.message || 'Error desconocido al iniciar sesión.', 'error'); //
        }
    })
    .catch(error => {
        console.error('Error en fetch:', error); //
        // El error.message ahora contendrá el mensaje del JSON de error si estaba disponible
        mostrarMensaje(error.message || 'Error al conectar con el servidor. Por favor, inténtalo de nuevo más tarde.', 'error'); //
    })
    .finally(() => {
        submitButton.disabled = false;
        submitButton.textContent = originalButtonText;
    });
}

// Función para mostrar mensajes
function mostrarMensaje(mensaje, tipo) { //
    const contenedorMensaje = document.getElementById('mensaje'); //
    if (contenedorMensaje) {
        contenedorMensaje.innerHTML = `<div class="message ${tipo}">${mensaje}</div>`; //
        
        if (tipo === 'error' || tipo === 'info') { // Limpiar info también
            setTimeout(() => {
                if (contenedorMensaje.firstChild && contenedorMensaje.firstChild.textContent === mensaje) {
                     contenedorMensaje.innerHTML = ''; //
                }
            }, 5000); //
        }
    }
}

// Función para limpiar mensajes al escribir
function limpiarMensaje() {
    const contenedorMensaje = document.getElementById('mensaje');
    if (contenedorMensaje && contenedorMensaje.innerHTML !== '') {
        // Solo limpia si el mensaje no es de éxito (ya que éxito lleva a redirección)
        if (!contenedorMensaje.querySelector('.success')) {
            contenedorMensaje.innerHTML = '';
        }
    }
}