// Script para manejo del formulario de registro

document.addEventListener('DOMContentLoaded', function() {
    const tipoAlumno = document.getElementById('tipoAlumno');
    const tipoTutor = document.getElementById('tipoTutor');
    const camposAlumno = document.getElementById('camposAlumno');
    const camposTutor = document.getElementById('camposTutor');
    const certSi = document.getElementById('certSi');
    const certNo = document.getElementById('certNo');
    const grupoCertificacion = document.getElementById('grupoCertificacion');
    const labelHabilidades = document.getElementById('labelHabilidades');
    const formulario = document.getElementById('registroForm');

    // Manejo de selección de tipo de usuario
    tipoAlumno.addEventListener('change', function() {
        if (this.checked) {
            camposAlumno.classList.remove('hidden');
            camposTutor.classList.add('hidden');
            // Hacer obligatorios los campos de alumno
            setRequiredFields('alumno');
        }
    });

    tipoTutor.addEventListener('change', function() {
        if (this.checked) {
            camposTutor.classList.remove('hidden');
            camposAlumno.classList.add('hidden');
            // Hacer obligatorios los campos de tutor
            setRequiredFields('tutor');
        }
    });

    // Manejo de certificación para tutores
    certSi.addEventListener('change', function() {
        if (this.checked) {
            grupoCertificacion.classList.remove('hidden');
            document.getElementById('documentos_certificacion').required = true;
            labelHabilidades.textContent = 'Detalles de los Documentos de Certificación *';
        }
    });

    certNo.addEventListener('change', function() {
        if (this.checked) {
            grupoCertificacion.classList.add('hidden');
            document.getElementById('documentos_certificacion').required = false;
            labelHabilidades.textContent = 'Explicación de Habilidades *';
        }
    });

    // Envío del formulario
    formulario.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (validarFormulario()) {
            enviarFormulario();
        }
    });
});

// Función para establecer campos obligatorios según el tipo de usuario
function setRequiredFields(tipo) {
    if (tipo === 'alumno') {
        document.getElementById('carrera').required = true;
        document.getElementById('boleta').required = true;
        document.getElementById('numero_celular_alumno').required = true;
        document.getElementById('credencial_horario').required = true;
        
        // Remover required de campos de tutor
        document.getElementById('areas_materias').required = false;
        document.getElementById('nivel_experiencia').required = false;
        document.getElementById('explicacion_habilidades').required = false;
        document.getElementById('horarios_disponibles').required = false;
    } else if (tipo === 'tutor') {
        document.getElementById('areas_materias').required = true;
        document.getElementById('nivel_experiencia').required = true;
        document.getElementById('explicacion_habilidades').required = true;
        document.getElementById('horarios_disponibles').required = true;
        
        // Remover required de campos de alumno
        document.getElementById('carrera').required = false;
        document.getElementById('boleta').required = false;
        document.getElementById('numero_celular_alumno').required = false;
        document.getElementById('credencial_horario').required = false;
    }
}

// Función para mostrar/ocultar contraseña
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const toggle = field.nextElementSibling;
    
    if (field.type === 'password') {
        field.type = 'text';
        toggle.textContent = '🙈';
    } else {
        field.type = 'password';
        toggle.textContent = '👁';
    }
}

// Función de validación del formulario
function validarFormulario() {
    const tipoUsuario = document.querySelector('input[name="tipo_usuario"]:checked');
    const nombreCompleto = document.getElementById('nombre_completo').value.trim();
    const nombreUsuario = document.getElementById('nombre_usuario').value.trim();
    const correo = document.getElementById('correo').value.trim();
    const contrasena = document.getElementById('contrasena').value;
    const confirmarContrasena = document.getElementById('confirmar_contrasena').value;

    // Validar que se haya seleccionado un tipo de usuario
    if (!tipoUsuario) {
        mostrarMensaje('Por favor, selecciona un tipo de usuario.', 'error');
        return false;
    }

    // Validar campos obligatorios
    if (!nombreCompleto || !nombreUsuario || !correo || !contrasena || !confirmarContrasena) {
        mostrarMensaje('Todos los campos obligatorios deben estar llenos.', 'error');
        return false;
    }

    // Validar formato de correo
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(correo)) {
        mostrarMensaje('Por favor, ingresa un correo electrónico válido.', 'error');
        return false;
    }

    // Validar longitud de contraseña
    if (contrasena.length < 8) {
        mostrarMensaje('La contraseña debe tener al menos 8 caracteres.', 'error');
        return false;
    }

    // Validar que las contraseñas coincidan
    if (contrasena !== confirmarContrasena) {
        mostrarMensaje('Las contraseñas no coinciden.', 'error');
        return false;
    }

    // Validaciones específicas según el tipo de usuario
    if (tipoUsuario.value === 'alumno') {
        return validarCamposAlumno();
    } else if (tipoUsuario.value === 'tutor') {
        return validarCamposTutor();
    }

    return true;
}

// Validar campos específicos de alumno
function validarCamposAlumno() {
    const carrera = document.getElementById('carrera').value;
    const boleta = document.getElementById('boleta').value.trim();
    const celular = document.getElementById('numero_celular_alumno').value.trim();
    const credencial = document.getElementById('credencial_horario').files[0];

    if (!carrera) {
        mostrarMensaje('Por favor, selecciona una carrera.', 'error');
        return false;
    }

    if (!boleta || boleta.length !== 10 || !/^\d{10}$/.test(boleta)) {
        mostrarMensaje('La boleta debe tener exactamente 10 dígitos.', 'error');
        return false;
    }

    if (!celular || celular.length !== 10 || !/^\d{10}$/.test(celular)) {
        mostrarMensaje('El número celular debe tener exactamente 10 dígitos.', 'error');
        return false;
    }

    if (!credencial) {
        mostrarMensaje('Por favor, adjunta tu credencial u horario en formato PDF.', 'error');
        return false;
    }

    if (credencial.type !== 'application/pdf') {
        mostrarMensaje('El archivo de credencial debe ser un PDF.', 'error');
        return false;
    }

    return true;
}

// Validar campos específicos de tutor
function validarCamposTutor() {
    const areas = document.getElementById('areas_materias').value.trim();
    const nivel = document.getElementById('nivel_experiencia').value;
    const certificacion = document.querySelector('input[name="tiene_certificacion"]:checked');
    const habilidades = document.getElementById('explicacion_habilidades').value.trim();
    const horarios = document.getElementById('horarios_disponibles').value.trim();
    const telefono = document.getElementById('telefono').value.trim();

    if (!areas) {
        mostrarMensaje('Por favor, describe las áreas o materias de tutoría.', 'error');
        return false;
    }

    if (!nivel) {
        mostrarMensaje('Por favor, selecciona tu nivel de experiencia.', 'error');
        return false;
    }

    if (!certificacion) {
        mostrarMensaje('Por favor, indica si tienes certificación.', 'error');
        return false;
    }

    if (certificacion.value === '1') {
        const documentos = document.getElementById('documentos_certificacion').files[0];
        if (!documentos) {
            mostrarMensaje('Por favor, adjunta tus documentos de certificación.', 'error');
            return false;
        }
        
        const tiposPermitidos = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!tiposPermitidos.includes(documentos.type)) {
            mostrarMensaje('Los documentos de certificación deben ser PDF, DOC o DOCX.', 'error');
            return false;
        }
    }

    if (!habilidades) {
        mostrarMensaje('Por favor, explica tus habilidades y experiencia.', 'error');
        return false;
    }

    if (!horarios) {
        mostrarMensaje('Por favor, indica tus horarios disponibles.', 'error');
        return false;
    }

    // Validar teléfono opcional
    if (telefono && (telefono.length !== 10 || !/^\d{10}$/.test(telefono))) {
        mostrarMensaje('El número de teléfono debe tener exactamente 10 dígitos.', 'error');
        return false;
    }

    return true;
}

// Función para enviar el formulario
function enviarFormulario() {
    const formData = new FormData(document.getElementById('registroForm'));
    
    mostrarMensaje('Procesando registro...', 'info');

    fetch('../php/procesar_registro.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarMensaje(data.message, 'success');
            document.getElementById('registroForm').reset();
            
            // Redirigir después de 2 segundos
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 2000);
        } else {
            mostrarMensaje(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarMensaje('Error al procesar el registro. Por favor, inténtalo de nuevo.', 'error');
    });
}

// Función para mostrar mensajes
function mostrarMensaje(mensaje, tipo) {
    const contenedorMensaje = document.getElementById('mensaje');
    contenedorMensaje.innerHTML = `<div class="message ${tipo}">${mensaje}</div>`;
    
    // Hacer scroll hacia el mensaje
    contenedorMensaje.scrollIntoView({ behavior: 'smooth' });
    
    // Limpiar mensaje después de 5 segundos para mensajes de error
    if (tipo === 'error') {
        setTimeout(() => {
            contenedorMensaje.innerHTML = '';
        }, 5000);
    }
}