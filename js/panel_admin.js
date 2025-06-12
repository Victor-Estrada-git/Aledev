// Archivo: js/panel_admin.js
// (Esta es la versión mejorada que incluye checkAdminSession y otras correcciones)

// Global variables
let currentTutorPage = 1; //
let currentStudentPage = 1; //
let currentCategoryPage = 1; 
const itemsPerPage = 10; //

let tutorStatusChartInstance = null; //
let registrationChartInstance = null; //

document.addEventListener('DOMContentLoaded', function() { //
    initializeApp(); //
});

async function checkAdminSession() { //
    try {
        const response = await fetch('../php/api/admin_profile.php'); //
        if (response.status === 401) { //
            showToast('Acceso no autorizado. Redirigiendo al login...', 'error'); //
            setTimeout(() => { //
                window.location.href = '../html/index.html'; // Ruta a tu login HTML
            }, 2000);
            return false; //
        }
        // Intentar parsear como JSON solo si la respuesta no es 401 y parece OK
        if (!response.ok) { // Captura otros errores HTTP (404, 500, etc.)
             console.error('Error al verificar la sesión (HTTP):', response.status, response.statusText); //
            // Intenta obtener más detalles si es posible
            let errorText = `Error del servidor: ${response.status} ${response.statusText}`;
            try {
                const errorBody = await response.text(); // Leer como texto para no fallar si no es JSON
                console.error('Cuerpo de la respuesta de error:', errorBody);
                 // Si el errorBody es HTML (común en errores PHP), el JSON.parse fallará y caerá en el catch de abajo
            } catch (e) {
                // No hacer nada si no se puede leer el cuerpo o ya es un error
            }
            showToast(errorText, 'error'); //
            return false; //
        }
        
        const data = await response.json(); // Esto fallará si la respuesta es HTML de error PHP
        
        if (data.success) { //
            return true; //
        } else {
            showToast(data.message || 'Sesión inválida. Redirigiendo...', 'error'); //
             setTimeout(() => { //
                window.location.href = '../html/index.html'; //
            }, 2000);
            return false; //
        }
    } catch (error) { // Este catch se activa si response.json() falla (porque la respuesta no es JSON) o por error de red
        console.error('Error de red o parseo JSON al verificar la sesión:', error); //
        showToast('Error de red al verificar la sesión. El servidor podría no estar respondiendo correctamente.', 'error'); //
        return false; //
    }
}

async function initializeApp() { //
    showLoading(); //
    const hasSession = await checkAdminSession(); //
    if (!hasSession) { //
        hideLoading(); //
        return; //
    }
    setupNavigation(); //
    loadDashboardData(); //
    setupEventListeners(); //
    loadProfileDataForWelcome(); //
    // hideLoading() se llama dentro de loadDashboardData o la última función de carga
}

async function loadProfileDataForWelcome() { //
    try {
        const response = await fetch('../php/api/admin_profile.php'); //
        const data = await response.json(); //
        if (data.success && data.profile) { //
            document.getElementById('welcome-admin-name').textContent = `Bienvenido, ${data.profile.nombre_completo.split(' ')[0]}`; //
        } else if (data.success && data.admin) { 
             document.getElementById('welcome-admin-name').textContent = `Bienvenido, ${data.admin.nombre_completo.split(' ')[0]}`; //
        }
    } catch (error) {
        console.error('Error loading admin name for welcome:', error); //
    }
}

function setupNavigation() { //
    const navLinks = document.querySelectorAll('.nav-link'); //
    const sections = document.querySelectorAll('.content-section'); //

    navLinks.forEach(link => { //
        link.addEventListener('click', (event) => { //
            if (link.getAttribute('href') === '../php/logout.php') { //
                return; // Permite la navegación normal para el logout
            }
            event.preventDefault(); //

            navLinks.forEach(l => l.classList.remove('active')); //
            sections.forEach(s => s.classList.remove('active')); //

            link.classList.add('active'); //
            const targetSectionId = link.getAttribute('data-section'); //
            const targetSectionElement = document.getElementById(targetSectionId); //
            if (targetSectionElement) { //
                 targetSectionElement.classList.add('active'); //
            } else {
                console.error(`Section with ID ${targetSectionId} not found.`); //
                return;
            }

            switch(targetSectionId) { //
                case 'dashboard': //
                    loadDashboardData(); //
                    break;
                case 'tutors': //
                    loadTutors(); //
                    break;
                case 'students': //
                    loadStudents(); //
                    break;
                case 'content': //
                    loadCategories(); //
                    break;
                case 'profile': //
                    loadProfile(); //
                    break;
            }
        });
    });
}

function setupEventListeners() { //
    document.getElementById('tutor-search').addEventListener('input', debounce(() => loadTutors(1), 300)); //
    document.getElementById('status-filter').addEventListener('change', () => loadTutors(1)); //
    document.getElementById('student-search').addEventListener('input', debounce(() => loadStudents(1), 300)); //
    document.getElementById('career-filter').addEventListener('change', () => loadStudents(1)); //
    document.getElementById('profile-form').addEventListener('submit', handleProfileSubmit); //
    document.getElementById('status-form').addEventListener('submit', handleStatusChange); //
    
    const categoryForm = document.getElementById('category-form'); //
    if (categoryForm) { //
        categoryForm.addEventListener('submit', handleCategorySubmit); //
    }
    
    const editStudentForm = document.getElementById('edit-student-form'); //
    if (editStudentForm) { //
        editStudentForm.addEventListener('submit', handleEditStudentSubmit); //
    }
}

async function loadDashboardData() { //
    try {
        showLoading(); //
        const response = await fetch('../php/api/dashboard_stats.php'); //
        const data = await response.json(); //

        if (data.success) { //
            updateDashboardStats(data.stats); //
            if (data.charts) { //
                if (data.charts.tutorStatus) { //
                    createTutorStatusChart(data.charts.tutorStatus); //
                }
                if (data.charts.monthlyRegistrations) { //
                     createRegistrationChart(data.charts.monthlyRegistrations); //
                }
            }
        } else {
            showToast(data.message || 'Error al cargar datos del dashboard', 'error'); //
        }
    } catch (error) {
        console.error('Error loading dashboard:', error); //
        showToast('Error de conexión al cargar el dashboard', 'error'); //
    } finally {
        hideLoading(); //
    }
}

function updateDashboardStats(stats) { //
    document.getElementById('total-students').textContent = stats.totalStudents || 0; //
    document.getElementById('total-tutors').textContent = stats.totalTutors || 0; //
    document.getElementById('pending-tutors').textContent = stats.pendingTutors || 0; //
    document.getElementById('approved-tutors').textContent = stats.approvedTutors || 0; //
}

function createTutorStatusChart(tutorStatusData) { //
    const ctx = document.getElementById('tutorStatusChart').getContext('2d'); //
    if (tutorStatusChartInstance) { //
        tutorStatusChartInstance.destroy(); //
    }
    tutorStatusChartInstance = new Chart(ctx, { //
        type: 'doughnut', //
        data: { //
            labels: ['Aprobados', 'Pendientes', 'Rechazados'], //
            datasets: [{ //
                label: 'Estado de Tutores', //
                data: [ //
                    tutorStatusData.aprobado || 0, //
                    tutorStatusData.pendiente || 0, //
                    tutorStatusData.rechazado || 0 //
                ],
                backgroundColor: [ //
                    'rgba(16, 185, 129, 0.8)', 
                    'rgba(245, 158, 11, 0.8)', 
                    'rgba(239, 68, 68, 0.8)'  
                ],
                borderColor: [ //
                    'rgba(16, 185, 129, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(239, 68, 68, 1)'
                ],
                borderWidth: 1 //
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom', // Asegúrate que esté abajo
                    labels: {
                        padding: 20 // Aumenta este valor para más espacio debajo de la leyenda
                    }
                },
                title: {
                    display: true,
                    text: 'Estado de Tutores'
                }
            }
        }
    });
}

function createRegistrationChart(monthlyRegistrationsData) { //
    const ctx = document.getElementById('registrationChart').getContext('2d'); //
    const labels = monthlyRegistrationsData.map(item => item.mes); //
    const studentData = monthlyRegistrationsData.map(item => item.alumnos); //
    const tutorData = monthlyRegistrationsData.map(item => item.tutores); //

    if (registrationChartInstance) { //
        registrationChartInstance.destroy(); //
    }
    registrationChartInstance = new Chart(ctx, { //
        type: 'line', //
        data: { //
            labels: labels, //
            datasets: [{ //
                label: 'Alumnos Registrados', //
                data: studentData, //
                borderColor: 'rgba(79, 70, 229, 0.8)', 
                backgroundColor: 'rgba(79, 70, 229, 0.2)', 
                fill: true, //
                tension: 0.1 //
            }, {
                label: 'Tutores Registrados', //
                data: tutorData, //
                borderColor: 'rgba(16, 185, 129, 0.8)', 
                backgroundColor: 'rgba(16, 185, 129, 0.2)', 
                fill: true, //
                tension: 0.1 //
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { // AÑADIR ESTA SECCIÓN
                padding: {
                    bottom: 20 // Agrega padding en la parte inferior del área de la gráfica. Ajusta el valor.
                    // puedes añadir top, left, right también si es necesario:
                    // top: 10,
                    // left: 10,
                    // right: 10
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                },
                x: { // Asegúrate de que las etiquetas del eje X tengan espacio
                    ticks: {
                        autoSkipPadding: 20, // Aumenta el padding para evitar que se salten etiquetas si hay muchas
                        maxRotation: 0, // Puedes rotarlas si son muy largas: 45
                        minRotation: 0
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                },
                title: {
                    display: true,
                    text: 'Registros Mensuales'
                }
            }
        }
    });
}

async function loadTutors(page = 1) { //
    try {
        showLoading(); //
        currentTutorPage = page; //
        const search = document.getElementById('tutor-search').value; //
        const status = document.getElementById('status-filter').value; //
        
        const params = new URLSearchParams({ //
            page: currentTutorPage, //
            limit: itemsPerPage, //
            search: search, //
            status: status //
        });

        const response = await fetch(`../php/api/tutors.php?${params}`); //
        const data = await response.json(); //

        if (data.success) { //
            renderTutorsTable(data.tutors); //
            renderPagination('tutors-pagination', data.pagination, loadTutors); //
        } else {
            showToast(data.message || 'Error al cargar tutores', 'error'); //
        }
    } catch (error) {
        console.error('Error loading tutors:', error); //
        showToast('Error de conexión al cargar tutores', 'error'); //
    } finally {
        hideLoading(); //
    }
}

function renderTutorsTable(tutors) { //
    const tbody = document.querySelector('#tutors-table tbody'); //
    tbody.innerHTML = ''; //

    if (!tutors || tutors.length === 0) { //
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No se encontraron tutores.</td></tr>'; //
        return; //
    }

    tutors.forEach(tutor => { //
        const row = document.createElement('tr'); //
        row.innerHTML = `
            <td>${tutor.id}</td>
            <td>${tutor.nombre_completo}</td>
            <td>${tutor.correo}</td>
            <td>${truncateText(tutor.areas_materias || 'N/A', 30)}</td>
            <td>${formatDate(tutor.fecha_registro)}</td>
            <td>
                <span class="status-badge status-${tutor.estado_registro.toLowerCase()}">
                    ${capitalizeFirst(tutor.estado_registro)}
                </span>
            </td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-info btn-sm" onclick="viewTutorDetail(${tutor.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${tutor.estado_registro === 'pendiente' ? `
                        <button class="btn btn-success btn-sm" onclick="openStatusModal(${tutor.id}, 'aprobado')">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="openStatusModal(${tutor.id}, 'rechazado')">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : ''}
                </div>
            </td>
        `; //
        tbody.appendChild(row); //
    });
}

async function loadStudents(page = 1) { //
    try {
        showLoading(); //
        currentStudentPage = page; //
        const search = document.getElementById('student-search').value; //
        const career = document.getElementById('career-filter').value; //
        
        const params = new URLSearchParams({ //
            page: currentStudentPage, //
            limit: itemsPerPage, //
            search: search, //
            career: career //
        });

        const response = await fetch(`../php/api/students.php?${params}`); //
        const data = await response.json(); //

        if (data.success) { //
            renderStudentsTable(data.students); //
            renderPagination('students-pagination', data.pagination, loadStudents); //
        } else {
            showToast(data.message || 'Error al cargar alumnos', 'error'); //
        }
    } catch (error) {
        console.error('Error loading students:', error); //
        showToast('Error de conexión al cargar alumnos', 'error'); //
    } finally {
        hideLoading(); //
    }
}

function renderStudentsTable(students) { //
    const tbody = document.querySelector('#students-table tbody'); //
    tbody.innerHTML = ''; //

    if (!students || students.length === 0) { //
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No se encontraron alumnos.</td></tr>'; //
        return; //
    }

    students.forEach(student => { //
        const row = document.createElement('tr'); //
        row.innerHTML = `
            <td>${student.id}</td>
            <td>${student.nombre_completo}</td>
            <td>${student.boleta}</td>
            <td>${student.carrera}</td>
            <td>${student.correo}</td>
            <td>${formatDate(student.fecha_registro)}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-info btn-sm" onclick="viewStudentDetail(${student.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="openEditStudentModal(${student.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </td>
        `; //
        tbody.appendChild(row); //
    });
}

async function loadCategories(page = 1) { //
    try {
        showLoading(); //
        currentCategoryPage = page; //
        const params = new URLSearchParams({ //
            page: currentCategoryPage, //
            limit: itemsPerPage  //
        });

        const response = await fetch(`../php/api/categories.php?${params}`); //
        const data = await response.json(); //

        if (data.success) { //
            renderCategoriesTable(data.categories); //
            if (data.pagination) { //
                renderPagination('categories-pagination', data.pagination, loadCategories); //
            } else {
                 document.getElementById('categories-pagination').innerHTML = ''; //
            }
        } else {
            showToast(data.message || 'Error al cargar categorías', 'error'); //
        }
    } catch (error) {
        console.error('Error loading categories:', error); //
        showToast('Error de conexión al cargar categorías', 'error'); //
    } finally {
        hideLoading(); //
    }
}

function renderCategoriesTable(categories) { //
    const tbody = document.querySelector('#categories-table tbody'); //
    tbody.innerHTML = ''; //

    if (!categories || categories.length === 0) { //
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No se encontraron categorías.</td></tr>'; //
        return; //
    }

    categories.forEach(category => { //
        const row = document.createElement('tr'); //
        row.innerHTML = `
            <td>${category.id}</td>
            <td>${category.nombre}</td>
            <td>${truncateText(category.descripcion || 'N/A', 50)}</td>
            <td>${formatDate(category.fecha_creacion_formatted || category.fecha_creacion)}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-primary btn-sm" onclick="openCategoryModal(${category.id}, '${escapeSingleQuotes(category.nombre)}', '${escapeSingleQuotes(category.descripcion || '')}')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="deleteCategory(${category.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `; //
        tbody.appendChild(row); //
    });
}
// Helper function to escape single quotes for JavaScript string literals within HTML attributes
function escapeSingleQuotes(str) {
    return str.replace(/'/g, "\\'");
}


function openCategoryModal(id = null, name = '', description = '') { //
    const modal = document.getElementById('category-modal'); //
    const title = document.getElementById('category-modal-title'); //
    const form = document.getElementById('category-form'); //
    const idField = document.getElementById('category-id'); //
    const nameField = document.getElementById('category-name'); //
    const descriptionField = document.getElementById('category-description'); //
    const submitBtn = document.getElementById('category-submit-btn'); //

    form.reset(); //
    idField.value = id || ''; //
    nameField.value = name; //
    descriptionField.value = description; //

    if (id) { //
        title.textContent = 'Editar Categoría'; //
        submitBtn.textContent = 'Actualizar Categoría'; //
    } else {
        title.textContent = 'Nueva Categoría'; //
        submitBtn.textContent = 'Crear Categoría'; //
    }
    modal.style.display = 'block'; //
}


function closeCategoryModal() { //
    document.getElementById('category-modal').style.display = 'none'; //
    document.getElementById('category-form').reset(); //
}

async function handleCategorySubmit(event) { //
    event.preventDefault(); //
    
    const categoryId = document.getElementById('category-id').value; //
    const categoryName = document.getElementById('category-name').value; //
    const categoryDescription = document.getElementById('category-description').value; //

    const method = categoryId ? 'PUT' : 'POST'; //
    const payload = { //
        nombre: categoryName, //
        descripcion: categoryDescription //
    };
    if (categoryId) { //
        payload.id = categoryId; //
    }

    try {
        showLoading(); //
        const response = await fetch('../php/api/categories.php', { //
            method: method, //
            headers: { //
                'Content-Type': 'application/json' //
            },
            body: JSON.stringify(payload) //
        });

        const data = await response.json(); //

        if (data.success) { //
            showToast(data.message || (categoryId ? 'Categoría actualizada' : 'Categoría creada'), 'success'); //
            closeCategoryModal(); //
            loadCategories(categoryId ? currentCategoryPage : 1); // Recargar, ir a pág 1 si es nueva
        } else {
            showToast(data.message || 'Error al guardar categoría', 'error'); //
        }
    } catch (error) {
        console.error('Error saving category:', error); //
        showToast('Error de conexión al guardar categoría', 'error'); //
    } finally {
        hideLoading(); //
    }
}

async function deleteCategory(categoryId) { //
    if (!confirm('¿Estás seguro de que quieres eliminar esta categoría? Esta acción no se puede deshacer.')) { //
        return; //
    }

    try {
        showLoading(); //
        const response = await fetch('../php/api/categories.php', { //
            method: 'DELETE', //
            headers: { //
                'Content-Type': 'application/json' //
            },
            body: JSON.stringify({ id: categoryId }) //
        });
        const data = await response.json(); //
        if (data.success) { //
            showToast('Categoría eliminada correctamente', 'success'); //
            loadCategories(currentCategoryPage); //
        } else {
            showToast(data.message || 'Error al eliminar categoría', 'error'); //
        }
    } catch (error) {
        console.error('Error deleting category:', error); //
        showToast('Error de conexión al eliminar categoría', 'error'); //
    } finally {
        hideLoading(); //
    }
}

async function viewTutorDetail(tutorId) { //
    try {
        showLoading(); //
        const response = await fetch(`../php/api/tutor_detail.php?id=${tutorId}`); //
        const data = await response.json(); //

        if (data.success) { //
            renderTutorDetail(data.tutor); //
            document.getElementById('tutor-modal').style.display = 'block'; //
        } else {
            showToast(data.message || 'Error al cargar detalle del tutor', 'error'); //
        }
    } catch (error) {
        console.error('Error loading tutor detail:', error); //
        showToast('Error de conexión al cargar detalle del tutor', 'error'); //
    } finally {
        hideLoading(); //
    }
}

function renderTutorDetail(tutor) { //
    const content = document.getElementById('tutor-detail-content'); //
    let areasHTML = 'No especificado'; //
    if (tutor.areas_materias_array && tutor.areas_materias_array.length > 0) { //
        areasHTML = `<ul>${tutor.areas_materias_array.map(area => `<li>${area}</li>`).join('')}</ul>`; //
    } else if (tutor.areas_materias) { //
        areasHTML = tutor.areas_materias; //
    }

    let horariosHTML = 'No especificado'; //
    if (tutor.horarios_disponibles_array && tutor.horarios_disponibles_array.length > 0) { //
         horariosHTML = `<ul>${tutor.horarios_disponibles_array.map(h => `<li>${h.dia || h}: ${h.horas || ''}</li>`).join('')}</ul>`; //
    } else if (tutor.horarios_disponibles) { //
        horariosHTML = tutor.horarios_disponibles; //
    }

    content.innerHTML = `
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
            <div>
                <h3 style="color: #4f46e5; margin-bottom: 1rem;">Información Personal</h3>
                <p><strong>ID:</strong> ${tutor.id}</p>
                <p><strong>Nombre Completo:</strong> ${tutor.nombre_completo}</p>
                <p><strong>Usuario:</strong> ${tutor.nombre_usuario || 'N/A'}</p>
                <p><strong>Correo:</strong> ${tutor.correo}</p>
                <p><strong>Teléfono:</strong> ${tutor.telefono || 'N/A'}</p>
                <p><strong>Fecha de Registro:</strong> ${tutor.fecha_registro_formateada || formatDate(tutor.fecha_registro)}</p>
            </div>
            <div>
                <h3 style="color: #4f46e5; margin-bottom: 1rem;">Información Académica y Estado</h3>
                <p><strong>Nivel de Experiencia:</strong> ${tutor.nivel_experiencia_texto || tutor.nivel_experiencia}</p>
                <p><strong>Tiene Certificación:</strong> ${tutor.tiene_certificacion_texto || (tutor.tiene_certificacion ? 'Sí' : 'No')}</p>
                <p><strong>Documentos de Certificación:</strong> ${tutor.ruta_documentos_certificacion ? `<a href="${tutor.ruta_documentos_certificacion.startsWith('http') ? '' : '../'}${tutor.ruta_documentos_certificacion}" target="_blank">Ver Documentos</a>` : 'No subidos'}</p>
                <p><strong>Estado:</strong> 
                    <span class="status-badge status-${(tutor.estado_registro || '').toLowerCase()}">
                        ${capitalizeFirst(tutor.estado_registro_texto || tutor.estado_registro)}
                    </span>
                </p>
                ${tutor.fecha_aprobacion_formateada ? `<p><strong>Fecha de Procesamiento:</strong> ${tutor.fecha_aprobacion_formateada}</p>` : ''}
                ${tutor.admin_aprobador_nombre ? `<p><strong>Procesado por:</strong> ${tutor.admin_aprobador_nombre} (${tutor.admin_aprobador_usuario || ''})</p>` : ''}
            </div>
        </div>
        <div style="margin-bottom: 2rem;">
            <h3 style="color: #4f46e5; margin-bottom: 1rem;">Áreas y Materias</h3>
            <div>${areasHTML}</div>
        </div>
        <div style="margin-bottom: 2rem;">
            <h3 style="color: #4f46e5; margin-bottom: 1rem;">Explicación de Habilidades</h3>
            <p>${tutor.explicacion_habilidades || 'No especificado'}</p>
        </div>
        <div style="margin-bottom: 2rem;">
            <h3 style="color: #4f46e5; margin-bottom: 1rem;">Horarios Disponibles</h3>
            <div>${horariosHTML}</div>
        </div>
        ${tutor.motivo_rechazo ? `
            <div style="margin-bottom: 2rem;">
                <h3 style="color: #ef4444; margin-bottom: 1rem;">Motivo de Rechazo</h3>
                <p style="color: #dc2626;">${tutor.motivo_rechazo}</p>
            </div>
        ` : ''}
        <div style="display: flex; gap: 1rem; justify-content: flex-end;">
            ${tutor.estado_registro === 'pendiente' ? `
                <button class="btn btn-success" onclick="openStatusModal(${tutor.id}, 'aprobado')">
                    <i class="fas fa-check"></i> Aprobar
                </button>
                <button class="btn btn-danger" onclick="openStatusModal(${tutor.id}, 'rechazado')">
                    <i class="fas fa-times"></i> Rechazar
                </button>
            ` : ''}
            <button class="btn btn-secondary" onclick="closeTutorModal()">Cerrar</button>
        </div>
    `; //
}

function closeTutorModal() { //
    document.getElementById('tutor-modal').style.display = 'none'; //
}

async function viewStudentDetail(studentId) { //
    try {
        showLoading(); //
        const response = await fetch(`../php/api/student_detail.php?id=${studentId}`); //
        const data = await response.json(); //

        if (data.success) { //
            renderStudentDetail(data.student); //
            document.getElementById('student-modal').style.display = 'block'; //
        } else {
            showToast(data.message || 'Error al cargar detalle del alumno', 'error'); //
        }
    } catch (error) {
        console.error('Error loading student detail:', error); //
        showToast('Error de conexión al cargar detalle del alumno', 'error'); //
    } finally {
        hideLoading(); //
    }
}

function renderStudentDetail(student) { //
    const content = document.getElementById('student-detail-content'); //
    content.innerHTML = `
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
            <div>
                <h3 style="color: #4f46e5; margin-bottom: 1rem;">Información Personal</h3>
                <p><strong>ID:</strong> ${student.id}</p>
                <p><strong>Nombre Completo:</strong> ${student.nombre_completo}</p>
                <p><strong>Usuario:</strong> ${student.nombre_usuario}</p>
                <p><strong>Correo:</strong> ${student.correo}</p>
                <p><strong>Teléfono:</strong> ${student.numero_celular}</p>
                <p><strong>Fecha de Registro:</strong> ${student.fecha_registro_formateada || formatDate(student.fecha_registro)}</p>
            </div>
            <div>
                <h3 style="color: #4f46e5; margin-bottom: 1rem;">Información Académica</h3>
                <p><strong>Carrera:</strong> ${student.carrera_completa || student.carrera}</p>
                <p><strong>Boleta:</strong> ${student.boleta}</p>
                <p><strong>Credencial/Horario:</strong> ${student.ruta_credencial_horario ? `<a href="${student.ruta_credencial_horario.startsWith('http') ? '' : '../'}${student.ruta_credencial_horario}" target="_blank">Ver Documento</a>` : 'No subido'}</p>

            </div>
        </div>
        <div style="display: flex; gap: 1rem; justify-content: flex-end;">
            <button class="btn btn-primary" onclick="openEditStudentModal(${student.id})">
                <i class="fas fa-edit"></i> Editar Alumno
            </button>
            <button class="btn btn-secondary" onclick="closeStudentModal()">Cerrar</button>
        </div>
    `; //
}

function closeStudentModal() { //
    document.getElementById('student-modal').style.display = 'none'; //
}

async function openEditStudentModal(studentId) { //
    try {
        showLoading(); //
        const response = await fetch(`../php/api/edit_student.php?id=${studentId}`);  //
        const data = await response.json(); //

        if (data.success && data.student) { //
            document.getElementById('edit-student-id').value = data.student.id; //
            document.getElementById('edit-student-name').value = data.student.nombre_completo; //
            document.getElementById('edit-student-username').value = data.student.nombre_usuario; //
            document.getElementById('edit-student-career').value = data.student.carrera; //
            document.getElementById('edit-student-boleta').value = data.student.boleta; //
            document.getElementById('edit-student-phone').value = data.student.numero_celular; //
            document.getElementById('edit-student-email').value = data.student.correo; //
            document.getElementById('edit-student-modal').style.display = 'block'; //
        } else {
            showToast(data.message || 'Error al cargar datos del alumno para editar', 'error'); //
        }
    } catch (error) {
        console.error('Error loading student for edit:', error); //
        showToast('Error de conexión al cargar datos del alumno', 'error'); //
    } finally {
        hideLoading(); //
    }
}

function closeEditStudentModal() { //
    document.getElementById('edit-student-modal').style.display = 'none'; //
    document.getElementById('edit-student-form').reset(); //
}

async function handleEditStudentSubmit(event) { //
    event.preventDefault(); //
    
    try {
        showLoading(); //
        const form = document.getElementById('edit-student-form'); //
        const formData = new FormData(form); //

        const response = await fetch('../php/api/edit_student.php', { //
            method: 'POST', //
            body: formData //
        });

        const data = await response.json(); //

        if (data.success) { //
            showToast('Alumno actualizado correctamente', 'success'); //
            closeEditStudentModal(); //
            loadStudents(currentStudentPage); //
        } else {
            showToast(data.message || 'Error al actualizar alumno', 'error'); //
        }
    } catch (error) {
        console.error('Error updating student:', error); //
        showToast('Error de conexión al actualizar alumno', 'error'); //
    } finally {
        hideLoading(); //
    }
}

function openStatusModal(tutorId, newStatus) { //
    document.getElementById('tutor-id-status').value = tutorId; //
    document.getElementById('new-status-val').value = newStatus; //
    
    const modal = document.getElementById('status-modal'); //
    const title = document.getElementById('status-modal-title'); //
    const reasonGroup = document.getElementById('rejection-reason-group'); //
    const confirmBtn = document.getElementById('confirm-status-btn'); //
    const reasonTextarea = document.getElementById('rejection-reason'); //

    reasonTextarea.value = ''; //

    if (newStatus === 'aprobado') { //
        title.textContent = `Aprobar Tutor (ID: ${tutorId})`; //
        reasonGroup.style.display = 'none'; //
        confirmBtn.textContent = 'Aprobar'; //
        confirmBtn.className = 'btn btn-success'; //
        reasonTextarea.required = false; //
    } else if (newStatus === 'rechazado') { //
        title.textContent = `Rechazar Tutor (ID: ${tutorId})`; //
        reasonGroup.style.display = 'block'; //
        confirmBtn.textContent = 'Rechazar'; //
        confirmBtn.className = 'btn btn-danger'; //
        reasonTextarea.required = true; //
    }

    modal.style.display = 'block'; //
}

function closeStatusModal() { //
    document.getElementById('status-modal').style.display = 'none'; //
    document.getElementById('status-form').reset(); //
}

async function handleStatusChange(event) { //
    event.preventDefault(); //

    const tutorId = document.getElementById('tutor-id-status').value; //
    const newStatus = document.getElementById('new-status-val').value; //
    const rejectionReason = document.getElementById('rejection-reason').value; //

    if (newStatus === 'rechazado' && !rejectionReason.trim()) { //
        showToast('El motivo de rechazo es obligatorio.', 'error'); //
        return; //
    }
    
    try {
        showLoading(); //
        const payload = { //
            tutor_id: tutorId, //
            new_status: newStatus, //
            rejection_reason: rejectionReason  //
        };

        const response = await fetch('../php/api/change_tutor_status.php', { //
            method: 'POST', //
            headers: { //
                'Content-Type': 'application/json' //
            },
            body: JSON.stringify(payload) //
        });

        const data = await response.json(); //

        if (data.success) { //
            showToast('Estado del tutor actualizado correctamente', 'success'); //
            closeStatusModal(); //
            closeTutorModal(); //
            loadTutors(currentTutorPage); //
            loadDashboardData(); //
        } else {
            showToast(data.message || 'Error al actualizar estado del tutor', 'error'); //
        }
    } catch (error) {
        console.error('Error changing tutor status:', error); //
        showToast('Error de conexión al actualizar estado del tutor', 'error'); //
    } finally {
        hideLoading(); //
    }
}

async function loadProfile() { //
    try {
        showLoading(); //
        const response = await fetch('../php/api/admin_profile.php'); //
        const data = await response.json(); //

        if (data.success) { //
            const profileData = data.profile || data.admin;  //
            if (profileData) { //
                document.getElementById('profile-name').value = profileData.nombre_completo || ''; //
                document.getElementById('profile-username').value = profileData.nombre_usuario || ''; //
                document.getElementById('profile-email').value = profileData.correo || ''; //
            } else {
                 showToast('No se pudieron cargar los datos del perfil.', 'error'); //
            }
        } else {
             showToast(data.message || 'Error al cargar perfil', 'error'); //
        }
    } catch (error) {
        console.error('Error loading profile:', error); //
        showToast('Error de conexión al cargar perfil', 'error'); //
    } finally {
        hideLoading(); //
    }
}

async function handleProfileSubmit(event) { //
    event.preventDefault(); //
    
    const password = document.getElementById('profile-password').value; //
    const confirmPassword = document.getElementById('profile-password-confirm').value; //

    if (password && password !== confirmPassword) { //
        showToast('Las contraseñas no coinciden', 'error'); //
        return; //
    }

    try {
        showLoading(); //
        const form = document.getElementById('profile-form'); //
        const formData = new FormData(form); //
        
        formData.delete('profile-password-confirm');  //
        if (!password) { //
            formData.delete('nueva_contrasena'); // El name en el HTML es nueva_contrasena
        }

        const response = await fetch('../php/api/update_admin_profile.php', {  //
            method: 'POST', //
            body: formData //
        });

        const data = await response.json(); //

        if (data.success) { //
            showToast('Perfil actualizado correctamente', 'success'); //
            document.getElementById('profile-password').value = ''; //
            document.getElementById('profile-password-confirm').value = ''; //
            loadProfileDataForWelcome(); //
        } else {
            showToast(data.message || 'Error al actualizar perfil', 'error'); //
        }
    } catch (error) {
        console.error('Error updating profile:', error); //
        showToast('Error de conexión al actualizar perfil', 'error'); //
    } finally {
        hideLoading(); //
    }
}

function renderPagination(containerId, paginationData, loadFunction) { //
    const container = document.getElementById(containerId); //
    container.innerHTML = ''; //

    if (!paginationData || paginationData.totalPages <= 1) return; //

    const { currentPage, totalPages } = paginationData; //

    if (currentPage > 1) { //
        const prevBtn = document.createElement('button'); //
        prevBtn.innerHTML = '&laquo;'; //
        prevBtn.onclick = () => loadFunction(currentPage - 1); //
        container.appendChild(prevBtn); //
    }

    let startPage = Math.max(1, currentPage - 2); //
    let endPage = Math.min(totalPages, currentPage + 2); //

    if (currentPage <= 3) { //
        endPage = Math.min(totalPages, 5); //
    }
    if (currentPage > totalPages - 3) { //
        startPage = Math.max(1, totalPages - 4); //
    }
    
    if (startPage > 1) { //
        const firstBtn = document.createElement('button'); //
        firstBtn.textContent = '1'; //
        firstBtn.onclick = () => loadFunction(1); //
        container.appendChild(firstBtn); //
        if (startPage > 2) { //
            const dots = document.createElement('span'); //
            dots.textContent = '...'; //
            dots.style.padding = '0.5rem 0.75rem'; //
            container.appendChild(dots); //
        }
    }

    for (let i = startPage; i <= endPage; i++) { //
        const pageBtn = document.createElement('button'); //
        pageBtn.textContent = i; //
        pageBtn.onclick = () => loadFunction(i); //
        if (i === currentPage) { //
            pageBtn.classList.add('active'); //
        }
        container.appendChild(pageBtn); //
    }

    if (endPage < totalPages) { //
        if (endPage < totalPages - 1) { //
            const dots = document.createElement('span'); //
            dots.textContent = '...'; //
            dots.style.padding = '0.5rem 0.75rem'; //
            container.appendChild(dots); //
        }
        const lastBtn = document.createElement('button'); //
        lastBtn.textContent = totalPages; //
        lastBtn.onclick = () => loadFunction(totalPages); //
        container.appendChild(lastBtn); //
    }

    if (currentPage < totalPages) { //
        const nextBtn = document.createElement('button'); //
        nextBtn.innerHTML = '&raquo;'; //
        nextBtn.onclick = () => loadFunction(currentPage + 1); //
        container.appendChild(nextBtn); //
    }
}

function formatDate(dateString) { //
    if (!dateString) return 'N/A'; //
    try {
        const date = new Date(dateString.replace(' ', 'T')); //
        if (isNaN(date.getTime())) return dateString; //

        return date.toLocaleDateString('es-ES', { //
            year: 'numeric', //
            month: '2-digit', //
            day: '2-digit' //
        });
    } catch (e) {
        return dateString; //
    }
}

function truncateText(text, maxLength) { //
    if (typeof text !== 'string') return ''; //
    return text.length > maxLength ? text.substring(0, maxLength) + '...' : text; //
}

function capitalizeFirst(string) { //
    if (typeof string !== 'string' || string.length === 0) return ''; //
    return string.charAt(0).toUpperCase() + string.slice(1).toLowerCase(); //
}

function debounce(func, wait) { //
    let timeout; //
    return function executedFunction(...args) { //
        const later = () => { //
            clearTimeout(timeout); //
            func(...args); //
        };
        clearTimeout(timeout); //
        timeout = setTimeout(later, wait); //
    };
}

function showLoading() { //
    const loadingElement = document.getElementById('loading'); //
    if (loadingElement) loadingElement.style.display = 'flex'; //
}

function hideLoading() { //
    const loadingElement = document.getElementById('loading'); //
    if (loadingElement) loadingElement.style.display = 'none'; //
}

function showToast(message, type = 'success') { //
    const toast = document.getElementById('toast'); //
    if (!toast) return; //

    toast.textContent = message; //
    toast.className = `toast toast-${type} show`; //
    
    setTimeout(() => { //
        toast.classList.remove('show'); //
    }, 3000);
}

window.onclick = function(event) { //
    const modals = document.querySelectorAll('.modal'); //
    modals.forEach(modal => { //
        if (event.target === modal) { //
            if (modal.id === 'tutor-modal') closeTutorModal(); //
            else if (modal.id === 'student-modal') closeStudentModal(); //
            else if (modal.id === 'edit-student-modal') closeEditStudentModal(); //
            else if (modal.id === 'category-modal') closeCategoryModal(); //
            else if (modal.id === 'status-modal') closeStatusModal(); //
            else modal.style.display = 'none'; //
        }
    });
}