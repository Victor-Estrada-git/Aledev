document.getElementById('recuperoForm').addEventListener('submit', function(event) {
            event.preventDefault();
            const correo = document.getElementById('correo_recupero').value;
            const mensajeDiv = document.getElementById('mensajeRecupero');
            mensajeDiv.textContent = '';

            if (!correo) {
                mensajeDiv.style.color = 'red';
                mensajeDiv.textContent = 'Por favor, ingresa tu correo.';
                return;
            }

            const formData = new FormData();
            formData.append('correo', correo);

            fetch('procesar_recuperacion.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                mensajeDiv.style.color = data.success ? 'green' : 'red';
                mensajeDiv.textContent = data.message;
                if(data.success) {
                    document.getElementById('recuperoForm').reset();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mensajeDiv.style.color = 'red';
                mensajeDiv.textContent = 'Ocurrió un error al procesar tu solicitud.';
            });
        });