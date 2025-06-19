document.addEventListener('DOMContentLoaded', () => {
    // Asignación de Eventos Principales
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            showSection(tab.getAttribute('data-section'));
        });
    });

    // Referencias a los elementos del formulario de búsqueda de tutores
    const formBuscarTutor = document.getElementById('form-buscar-tutor');
    const searchMateriaInput = document.getElementById('search-materia');
    const searchSemestreSelect = document.getElementById('search-semestre');
    const btnClearSearch = document.getElementById('btn-clear-search');

    document.getElementById('form-perfil')?.addEventListener('submit', actualizarPerfil);
    
    // Modificado: El evento submit ahora llama a buscarTutores sin parámetros directos,
    // ya que los valores se obtendrán dentro de la función.
    formBuscarTutor?.addEventListener('submit', (event) => {
        event.preventDefault(); // Prevenir el envío del formulario
        const materia = searchMateriaInput.value.trim();
        const semestre = searchSemestreSelect.value;
        buscarTutores(materia, semestre);
    });

    // Nuevo: Evento para el botón de limpiar filtros
    btnClearSearch?.addEventListener('click', () => {
        searchMateriaInput.value = ''; // Limpiar campo de materia
        searchSemestreSelect.value = ''; // Resetear select a "Todos los semestres"
        buscarTutores('', ''); // Realizar una búsqueda sin filtros (mostrar todos)
    });

    document.getElementById('form-queja')?.addEventListener('submit', enviarQueja);
    document.getElementById('form-agendar-cita')?.addEventListener('submit', agendarCita);
    
    document.querySelector('#agendarCitaModal .close-button')?.addEventListener('click', () => {
        document.getElementById('agendarCitaModal').style.display = 'none';
    });

    // Carga inicial (asegúrate de que esta sea la sección activa por defecto al cargar)
    showSection('profile'); 
});

function showSection(sectionId) {
    document.querySelectorAll('.content-section').forEach(section => {
        section.style.display = 'none';
    });
    document.getElementById(sectionId).style.display = 'block';

    // Lógica para cargar datos específicos de cada sección
    switch (sectionId) {
        case 'profile': 
            cargarDatosIniciales(); 
            break;
        case 'search': 
            // Cargar tutores al entrar a la sección de búsqueda (sin filtros iniciales)
            buscarTutores('', ''); 
            break; 
        case 'appointments': 
            cargarCitas(); 
            break;
        case 'wallet': 
            cargarBilletera(); 
            break;
        case 'complaints': 
            cargarQuejas();
            cargarDatosFormularioQueja();
            break;
        case 'recommendations': // ¡NUEVO CASO PARA RECOMENDACIONES!
            loadRecommendedTutors();
            break;
    }
}

// Función Fetch Genérica
async function fetchData(endpoint, options = {}) {
    try {
        // Asumiendo que todos los endpoints PHP están en ../php/
        const response = await fetch(`../php/${endpoint}`, options); 
        const result = await response.json();
        if (!response.ok || (result.success !== undefined && !result.success)) {
            // Manejo de errores más robusto, incluyendo cuando 'success' es explícitamente false
            throw new Error(result.message || `Error HTTP: ${response.status} en ${endpoint}`);
        }
        return result.data; // Devuelve solo la propiedad 'data' si existe
    } catch (error) {
        console.error('Error en fetchData:', error);
        // Evitar alert en algunos errores para una mejor UX, pero mostrarlo en otros
        // if (!error.message.includes('No autorizado') && !error.message.includes('No hay')) {
        //     alert(`Error: ${error.message}`);
        // }
        return null; // Retorna null en caso de error
    }
}


// Funciones de Carga y Renderizado existentes...
async function cargarDatosIniciales() {
    const data = await fetchData('panel_alumno.php?accion=get_datos_iniciales');
    if (!data) return;
    const { perfil, estadisticas } = data;
    const carreras = { 'ISC': 'Ingeniería en Sistemas Computacionales', 'IIA': 'Ingeniería en Inteligencia Artificial', 'LCD': 'Licenciatura en Ciencia de Datos' };
    const semestres = { 1: "1er", 2: "2do", 3: "3er", 4: "4to", 5: "5to", 6: "6to", 7: "7mo", 8: "8vo", 9: "9no", 10: "10mo" }; // Añadido 10mo semestre
    document.getElementById('user-header-name').textContent = perfil.nombre_completo;
    document.getElementById('user-header-details').textContent = `${perfil.carrera} • ${semestres[perfil.semestre] || perfil.semestre} Semestre`;
    document.getElementById('profile-nombre').value = perfil.nombre_completo;
    document.getElementById('profile-correo').value = perfil.correo;
    document.getElementById('profile-carrera').value = carreras[perfil.carrera] || perfil.carrera;
    document.getElementById('profile-semestre').value = `${semestres[perfil.semestre] || perfil.semestre} Semestre`;
    document.getElementById('profile-boleta').value = perfil.boleta;
    document.getElementById('profile-telefono').value = perfil.numero_celular;
    document.getElementById('stat-horas').textContent = estadisticas.horas_tutoria || 0;
    document.getElementById('stat-materias').textContent = estadisticas.materias_estudiadas || 0;
    document.getElementById('stat-calificacion').textContent = estadisticas.calificacion_promedio || 'N/A';
    document.getElementById('stat-tutores').textContent = estadisticas.tutores_diferentes || 0;
}

async function cargarCitas() {
    const citas = await fetchData('panel_alumno.php?accion=get_citas');
    if (!citas) return;
    const pendientesContainer = document.getElementById('citas-pendientes-container');
    const proximasContainer = document.getElementById('citas-proximas-container');
    const historialContainer = document.getElementById('citas-historial-container');
    pendientesContainer.innerHTML = '<h4><i class="fas fa-clock"></i> Solicitudes Pendientes</h4>';
    proximasContainer.innerHTML = '<h4><i class="fas fa-calendar-check"></i> Próximas Citas</h4>';
    historialContainer.innerHTML = '<h4><i class="fas fa-history"></i> Historial de Citas</h4>';
    let has = { pendientes: false, proximas: false, historial: false };

    citas.forEach(cita => {
        const fecha = new Date(cita.fecha_hora).toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' });
        const hora = new Date(cita.fecha_hora).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        let html = '';
        switch (cita.estado) {
            case 'pendiente':
                has.pendientes = true;
                html = `<div class="appointment-card status-pending"><div><strong>${cita.materia_tema}</strong> con ${cita.tutor_nombre}</div><small><i class="fas fa-calendar"></i> ${fecha} - ${hora}</small></div>`;
                pendientesContainer.innerHTML += html;
                break;
            case 'confirmada':
                has.proximas = true;
                html = `<div class="appointment-card status-confirmed"><div><strong>${cita.materia_tema}</strong> con ${cita.tutor_nombre}</div><small><i class="fas fa-calendar"></i> ${fecha} - ${hora}</small><div class="actions"><button class="btn btn-secondary btn-sm"><i class="fas fa-video"></i> Unirse</button></div></div>`;
                proximasContainer.innerHTML += html;
                break;
            default: // completada, cancelada_*, rechazada
                has.historial = true;
                const statusClass = `status-${cita.estado.split('_')[0]}`;
                const statusText = cita.estado.replace('_', ' ').replace(/(^\w|\s\w)/g, m => m.toUpperCase());
                html = `<div class="appointment-card ${statusClass}"><div><strong>${cita.materia_tema}</strong> con ${cita.tutor_nombre} <span class="status-badge">${statusText}</span></div><small><i class="fas fa-calendar"></i> ${fecha} - ${hora}</small></div>`;
                historialContainer.innerHTML += html;
                break;
        }
    });

    if (!has.pendientes) pendientesContainer.innerHTML += '<p>No tienes solicitudes pendientes.</p>';
    if (!has.proximas) proximasContainer.innerHTML += '<p>No tienes citas próximas.</p>';
    if (!has.historial) historialContainer.innerHTML += '<p>Aún no tienes un historial de citas.</p>';
}

async function cargarBilletera() {
    const data = await fetchData('panel_alumno.php?accion=get_billetera');
    if (!data) return;
    document.getElementById('saldo-billetera').textContent = `$${data.saldo}`;
    const container = document.getElementById('transacciones-container');
    container.innerHTML = '';
    if (data.transacciones.length > 0) {
        data.transacciones.forEach(trans => {
            const fecha = new Date(trans.fecha).toLocaleString('es-ES');
            const esPositiva = trans.tipo === 'recarga' || trans.tipo === 'reembolso';
            container.innerHTML += `<div class="transaction-item ${esPositiva ? 'income' : 'expense'}"><div><div class="description">${trans.descripcion || trans.tipo}</div><div class="date">${fecha}</div></div><div class="amount">${esPositiva ? '+' : '-'}$${parseFloat(trans.monto).toFixed(2)}</div></div>`;
        });
    } else {
        container.innerHTML = '<p>No hay transacciones recientes.</p>';
    }
}

async function cargarQuejas() {
    const quejas = await fetchData('panel_alumno.php?accion=get_quejas');
    if (!quejas) return;
    const container = document.getElementById('quejas-container');
    container.innerHTML = '';
    if (quejas.length > 0) {
        quejas.forEach(queja => {
            container.innerHTML += `<div class="complaint-card"><div class="header"><h4>${queja.asunto}</h4><span class="status-badge">${queja.estado}</span></div><p><strong>Tutor:</strong> ${queja.tutor_nombre}</p><p class="description">${queja.descripcion}</p>${queja.resolucion ? `<div class="resolution"><strong>Resolución:</strong> ${queja.resolucion}</div>` : ''}</div>`;
        });
    } else {
        container.innerHTML = '<p>No has presentado ninguna queja.</p>';
    }
}

// Modificado: Ahora buscarTutores recibe parámetros materia y semestre
async function buscarTutores(materia = '', semestre = '') {
    const container = document.getElementById('tutor-results-container');
    container.innerHTML = '<p class="placeholder-text">Buscando...</p>';

    // Construir la URL con los parámetros de búsqueda
    // Solo añadir el parámetro si tiene un valor (no vacío)
    let queryParams = [];
    if (materia) {
        queryParams.push(`materia=${encodeURIComponent(materia)}`);
    }
    if (semestre) { // El valor "" para 'Todos los semestres' no se enviará
        queryParams.push(`semestre=${encodeURIComponent(semestre)}`);
    }

    const queryString = queryParams.length > 0 ? `&${queryParams.join('&')}` : '';
    console.log(`Buscando con materia: "${materia}", semestre: "${semestre}"`); // Para depuración

    const tutores = await fetchData(`panel_alumno.php?accion=buscar_tutores${queryString}`);
    
    container.innerHTML = '';
    if (tutores && tutores.length > 0) {
        tutores.forEach(tutor => {
            // Asegurarse de que el avatar se muestre correctamente, si tutor.nombre_completo está vacío, usar un valor predeterminado
            const initials = tutor.nombre_completo ? tutor.nombre_completo.match(/\b(\w)/g)?.join('').slice(0,2) : '??';
            // Mostrar el semestre del tutor si está disponible
            const tutorSemestre = tutor.semestre_imparte ? `<p>Semestre: ${tutor.semestre_imparte}</p>` : '';

            container.innerHTML += `
                <div class="tutor-card">
                    <div class="tutor-header">
                        <div class="tutor-avatar">${initials}</div>
                        <div class="tutor-info">
                            <h4>${tutor.nombre_completo}</h4>
                            <p>${tutor.nivel_experiencia}</p>
                            ${tutorSemestre} <div class="tags">${tutor.areas_materias.split(',').map(m => `<span class="tag">${m.trim()}</span>`).join('')}</div>
                        </div>
                        <div class="tutor-rate">
                            <div class="donation">Donativo Sugerido</div>
                            <div class="amount">$${parseFloat(tutor.donativo_sugerido_hr).toFixed(2)}/hr</div>
                        </div>
                    </div>
                    <div class="tutor-actions">
                        <button class="btn btn-primary btn-agendar" data-tutor-id="${tutor.id}" data-tutor-nombre="${tutor.nombre_completo}"><i class="fas fa-calendar-plus"></i> Agendar Cita</button>
                    </div>
                </div>`;
        });
        document.querySelectorAll('.btn-agendar').forEach(button => button.addEventListener('click', abrirModalAgendar));
    } else {
        container.innerHTML = '<p class="placeholder-text">No se encontraron tutores con ese criterio.</p>';
    }
}

async function cargarDatosFormularioQueja() {
    const data = await fetchData(`panel_alumno.php?accion=get_datos_queja`);
    if (!data) return;
    const tutorSelect = document.getElementById('queja-tutor');
    const citaSelect = document.getElementById('queja-cita');
    tutorSelect.innerHTML = '<option value="">Selecciona un tutor</option>';
    data.tutores.forEach(tutor => { tutorSelect.innerHTML += `<option value="${tutor.id}">${tutor.nombre_completo}</option>`; });
    citaSelect.innerHTML = '<option value="">Selecciona una cita (opcional)</option>';
    data.citas.forEach(cita => {
        const fecha = new Date(cita.fecha_hora).toLocaleString('es-ES');
        citaSelect.innerHTML += `<option value="${cita.id}">${cita.materia_tema} - ${fecha}</option>`;
    });
}

// Funciones de Envío de Formularios
async function actualizarPerfil(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    // Cambiando la acción para que sea específica para actualizar perfil
    formData.append('accion', 'update_perfil'); 
    // Asegúrate de que la URL para fetchData sea correcta para tu panel_alumno.php
    const response = await fetchData('panel_alumno.php', { method: 'POST', body: formData }); 
    alert(response ? 'Perfil actualizado' : 'Error al actualizar');
    if (response) cargarDatosIniciales();
}

function abrirModalAgendar(event) {
    const button = event.currentTarget;
    document.getElementById('modal-tutor-name').textContent = `Agendar Cita con ${button.dataset.tutorNombre}`;
    document.getElementById('modal-tutor-id').value = button.dataset.tutorId;
    document.getElementById('agendarCitaModal').style.display = 'flex';
}

async function agendarCita(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('accion', 'agendar_cita'); // Asegúrate de que la acción sea correcta para tu backend
    
    const response = await fetch('../php/panel_alumno.php', { method: 'POST', body: formData });
    const result = await response.json();
    alert(result.message);

    if (result.success) {
        form.reset();
        document.getElementById('agendarCitaModal').style.display = 'none';
        // Recargar citas para ver la nueva solicitud
        document.querySelector('.nav-tab[data-section="appointments"]').click(); 
    }
}

async function enviarQueja(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('accion', 'enviar_queja'); // Asegúrate de que la acción sea correcta para tu backend
    
    const response = await fetch('../php/panel_alumno.php', { method: 'POST', body: formData });
    const result = await response.json();
    alert(result.message);

    if (result.success) {
        form.reset();
        cargarQuejas();
    }
}


// --- NUEVA FUNCIÓN PARA CARGAR TUTORES RECOMENDADOS ---
async function loadRecommendedTutors() {
    const recommendationsContainer = document.getElementById('recommended-tutors-container');
    recommendationsContainer.innerHTML = '<p class="placeholder-text">Cargando recomendaciones...</p>'; // Mensaje de carga

    try {
        // Llama al nuevo script PHP específico para recomendaciones
        // Asegúrate de que este archivo exista en ../php/get_recommended_tutors.php
        const tutors = await fetchData('get_recommended_tutors.php'); 

        if (!tutors || tutors.length === 0) {
            recommendationsContainer.innerHTML = '<p class="placeholder-text">No hay tutores recomendados por el momento.</p>';
            return;
        }

        // Limpiar el contenedor antes de añadir nuevos tutores
        recommendationsContainer.innerHTML = ''; 

        // Iterar sobre los tutores y crear sus tarjetas
        tutors.forEach(tutor => {
            const tutorCard = document.createElement('div');
            tutorCard.classList.add('tutor-card'); // Reutiliza la clase de tarjeta de tutor

            // Asegurarse de que el avatar se muestre correctamente
            const initials = (tutor.nombre && tutor.apellido) ? `${tutor.nombre.charAt(0)}${tutor.apellido.charAt(0)}` : '??';
            // Formatear calificación si existe
            const calificacionHTML = tutor.calificacion_promedio ? 
                `<p>Calificación: <span style="font-weight: bold; color: #ffc107;"><i class="fas fa-star"></i> ${parseFloat(tutor.calificacion_promedio).toFixed(1)}</span></p>` : 
                `<p>Calificación: N/A</p>`;

            tutorCard.innerHTML = `
                <div class="tutor-header">
                    <div class="tutor-avatar">${initials}</div>
                    <div class="tutor-info">
                        <h4>${tutor.nombre} ${tutor.apellido}</h4>
                        <p>${tutor.carrera || 'Carrera no especificada'}</p>
                        <p>Especialidad: ${tutor.especialidad || 'No especificada'}</p>
                        <p>Experiencia: ${tutor.experiencia || 'No especificada'}</p>
                        ${calificacionHTML}
                    </div>
                </div>
                <div class="tutor-actions">
                    <button class="btn btn-primary btn-agendar" data-tutor-id="${tutor.id}" data-tutor-nombre="${tutor.nombre} ${tutor.apellido}"><i class="fas fa-calendar-plus"></i> Agendar Cita</button>
                </div>
            `;
            recommendationsContainer.appendChild(tutorCard);
        });

        // Re-añadir el event listener para los botones "Agendar Cita" para los tutores recomendados
        document.querySelectorAll('#recommended-tutors-container .btn-agendar').forEach(button => {
            button.addEventListener('click', abrirModalAgendar);
        });

    } catch (error) {
        console.error('Error al cargar tutores recomendados:', error);
        recommendationsContainer.innerHTML = '<p class="placeholder-text error-message">No se pudieron cargar las recomendaciones. Intenta de nuevo más tarde.</p>';
    }
}