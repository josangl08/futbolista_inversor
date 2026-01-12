/**
 * Custom JavaScript para Futbolista Inversor
 *
 * Contiene:
 * 1. Click handler para mostrar título de temas en móviles
 * 2. AJAX handler para formulario de contacto
 */

// ============================================================================
// 1. CLICK HANDLER PARA MOSTRAR TÍTULO DE TEMAS (MÓVILES)
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
	// Seleccionar todos los elementos .work
	const workItems = document.querySelectorAll('.work');

	workItems.forEach(function(item) {
		// Agregar evento click
		item.addEventListener('click', function(e) {
			// Si ya está activo, lo desactivamos
			if (this.classList.contains('active')) {
				this.classList.remove('active');
			} else {
				// Desactivar todos los demás primero
				workItems.forEach(function(otherItem) {
					otherItem.classList.remove('active');
				});
				// Activar el actual
				this.classList.add('active');
			}
		});
	});

	// Cerrar al hacer click fuera
	document.addEventListener('click', function(e) {
		if (!e.target.closest('.work')) {
			workItems.forEach(function(item) {
				item.classList.remove('active');
			});
		}
	});
});


// ============================================================================
// 2. AJAX HANDLER PARA FORMULARIO DE CONTACTO
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
	const form = document.querySelector('#contact form');

	// Verificar que el formulario existe antes de continuar
	if (!form) {
		console.warn('Formulario de contacto no encontrado');
		return;
	}

	const modal = new bootstrap.Modal(document.getElementById('modalFormulario'));
	const modalTitulo = document.getElementById('modalTitulo');
	const modalMensaje = document.getElementById('modalMensaje');
	const modalHeader = document.getElementById('modalFormularioHeader');
	const modalIcon = document.getElementById('modalIcon');

	// Establecer timestamp cuando se carga el formulario (anti-spam)
	const timestampField = document.getElementById('form_timestamp');
	if (timestampField) {
		timestampField.value = Date.now();
	}

	form.addEventListener('submit', function(e) {
		e.preventDefault(); // Prevenir envío tradicional

		// Limpiar mensajes de error previos
		document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
		document.querySelectorAll('.invalid-feedback').forEach(el => el.remove());

		// Recoger datos del formulario
		const formData = new FormData(form);

		// Deshabilitar botón de envío
		const submitBtn = form.querySelector('button[type="submit"]');
		const originalText = submitBtn.innerHTML;
		submitBtn.disabled = true;
		submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';

		// Enviar con AJAX (usando jQuery que ya está cargado)
		$.ajax({
			url: 'process-form.php',
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			dataType: 'json',
			success: function(response) {
				if (response.success) {
					// ÉXITO - Mostrar modal verde
					modalHeader.classList.remove('bg-danger');
					modalHeader.classList.add('bg-success', 'text-white');
					modalIcon.className = 'fas fa-check-circle me-2';
					modalTitulo.textContent = '¡Mensaje Enviado!';
					modalMensaje.textContent = response.message || 'Tu mensaje ha sido enviado correctamente. Nos pondremos en contacto contigo pronto.';

					// Limpiar formulario
					form.reset();

					// Mostrar modal
					modal.show();
				} else {
					// ERROR DE VALIDACIÓN
					modalHeader.classList.remove('bg-success');
					modalHeader.classList.add('bg-danger', 'text-white');
					modalIcon.className = 'fas fa-exclamation-triangle me-2';
					modalTitulo.textContent = 'Error en el Formulario';

					// Mostrar errores
					if (response.errors && Object.keys(response.errors).length > 0) {
						let errorHTML = '<ul class="mb-0">';
						for (let field in response.errors) {
							errorHTML += '<li>' + response.errors[field] + '</li>';

							// Marcar campo con error
							const input = form.querySelector('[name="' + field + '"]');
							if (input) {
								input.classList.add('is-invalid');
								const feedback = document.createElement('div');
								feedback.className = 'invalid-feedback d-block';
								feedback.textContent = response.errors[field];
								input.parentNode.appendChild(feedback);
							}
						}
						errorHTML += '</ul>';
						modalMensaje.innerHTML = errorHTML;
					} else {
						modalMensaje.textContent = response.message || 'Hubo un error al procesar tu mensaje.';
					}

					modal.show();
				}
			},
			error: function(xhr, status, error) {
				// ERROR DE RED O SERVIDOR
				console.error('Error AJAX:', status, error);
				console.error('Response:', xhr.responseText);
				console.error('Status Code:', xhr.status);

				modalHeader.classList.remove('bg-success');
				modalHeader.classList.add('bg-danger', 'text-white');
				modalIcon.className = 'fas fa-exclamation-triangle me-2';
				modalTitulo.textContent = 'Error de Conexión';

				if (xhr.status === 429) {
					modalMensaje.textContent = 'Has enviado demasiados mensajes. Por favor, espera unos minutos.';
				} else if (xhr.status === 0) {
					modalMensaje.textContent = 'No se puede conectar con el servidor. Asegúrate de que el servidor web esté ejecutándose (usa "php -S localhost:8000" en la terminal).';
				} else {
					modalMensaje.textContent = 'No se pudo conectar con el servidor. Por favor, intenta de nuevo más tarde. (Error ' + xhr.status + ')';
				}

				modal.show();
			},
			complete: function() {
				// Reactivar botón
				submitBtn.disabled = false;
				submitBtn.innerHTML = originalText;
			}
		});
	});
});
