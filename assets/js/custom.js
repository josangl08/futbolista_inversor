/**
 * Custom JavaScript para Futbolista Inversor
 *
 * Contiene:
 * 1. Configuración de fechas del curso
 * 2. Click handler para mostrar título de temas en móviles
 * 3. AJAX handler para formulario de contacto
 * 4. Countdown timer y actualización de fechas
 */

// ============================================================================
// 1. CONFIGURACIÓN DE FECHAS DEL CURSO (CAMBIAR SOLO AQUÍ)
// ============================================================================

const COURSE_CONFIG = {
	// Fecha de inicio del curso
	courseStartDate: '1 de Marzo de 2026',

	// Periodo de inscripciones
	enrollmentOpenDate: '15 de Febrero de 2026',
	enrollmentCloseDate: '26 de Febrero de 2026',

	// Fecha y hora exacta de cierre (para countdown)
	enrollmentEndDateTime: new Date('2026-02-28T23:59:59').getTime(),

	// Mes de próxima apertura (para sticky bar cuando está cerrado)
	nextOpeningMonth: 'Marzo'
};

// ============================================================================
// 2. CLICK HANDLER PARA MOSTRAR TÍTULO DE TEMAS (MÓVILES)
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

// ============================================================================
// STICKY BAR FUNCTIONALITY
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
	const stickyBar = document.getElementById('stickyBar');

	if (!stickyBar) {
		console.error('Sticky bar element not found!');
		return;
	}

	console.log('Sticky bar element found:', stickyBar);

	// Check if user has closed the bar (using localStorage)
	const closedData = localStorage.getItem('stickyBarClosed');

	if (closedData) {
		const closedTime = parseInt(closedData);
		const now = new Date().getTime();
		const oneDay = 24 * 60 * 60 * 1000; // 1 día en milisegundos

		if (now - closedTime < oneDay) {
			console.log('Sticky bar hidden - closed less than 1 day ago');
			return; // Don't show if closed within 1 day
		} else {
			// Ha pasado más de 1 día, eliminar el registro
			localStorage.removeItem('stickyBarClosed');
			console.log('Sticky bar 1-day period expired, showing again');
		}
	}

	console.log('Setting up scroll listener for sticky bar');

	// Show sticky bar after 3 seconds of scroll
	let hasScrolled = false;
	let scrollTimer;

	// Function to adjust sticky bar position relative to footer
	function adjustStickyBarPosition() {
		if (stickyBar.style.display !== 'block') return;

		const footer = document.getElementById('footer');
		if (!footer) return;

		const footerRect = footer.getBoundingClientRect();
		const windowHeight = window.innerHeight;

		// If footer is visible in viewport
		if (footerRect.top < windowHeight) {
			// Calculate how much to push up the sticky bar
			const overlap = windowHeight - footerRect.top;
			stickyBar.style.bottom = overlap + 'px';
		} else {
			// Footer not visible, keep sticky bar at bottom
			stickyBar.style.bottom = '0px';
		}
	}

	window.addEventListener('scroll', function() {
		// Adjust sticky bar position on scroll
		adjustStickyBarPosition();

		if (hasScrolled) return;

		clearTimeout(scrollTimer);
		scrollTimer = setTimeout(function() {
			console.log('Current scroll position:', window.scrollY);
			if (window.scrollY > 300) { // Show after scrolling 300px
				console.log('Showing sticky bar');
				stickyBar.style.display = 'block';
				hasScrolled = true;
				adjustStickyBarPosition(); // Adjust position when first shown
			}
		}, 3000); // Wait 3 seconds after scroll stops
	});

	// Also adjust on window resize
	window.addEventListener('resize', adjustStickyBarPosition);
});

// Close sticky bar and save to localStorage
function closeStickyBar() {
	const stickyBar = document.getElementById('stickyBar');
	if (stickyBar) {
		stickyBar.style.animation = 'slideDown 0.3s ease-out';
		setTimeout(function() {
			stickyBar.style.display = 'none';
		}, 300);

		// Save current timestamp to localStorage (for 1 day duration)
		const now = new Date().getTime();
		localStorage.setItem('stickyBarClosed', now.toString());
		console.log('Sticky bar closed, will reappear in 1 day');
	}
}

// Cookie helper functions
function setCookie(name, value, days) {
	const date = new Date();
	date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
	const expires = "expires=" + date.toUTCString();
	document.cookie = name + "=" + value + ";" + expires + ";path=/";
}

function getCookie(name) {
	const nameEQ = name + "=";
	const ca = document.cookie.split(';');
	for (let i = 0; i < ca.length; i++) {
		let c = ca[i];
		while (c.charAt(0) === ' ') c = c.substring(1, c.length);
		if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
	}
	return null;
}

// ============================================================================
// ACTUALIZAR FECHAS EN EL DOM
// ============================================================================

function updateCourseDates() {
	console.log('🔄 Actualizando fechas del curso...', COURSE_CONFIG);

	// Actualizar fecha de inicio del curso en banner OPEN
	const courseStartOpen = document.querySelector('#enrollmentBanner.open .course-start-date');
	if (courseStartOpen) {
		courseStartOpen.textContent = COURSE_CONFIG.courseStartDate;
		console.log('✅ Fecha actualizada en banner OPEN:', COURSE_CONFIG.courseStartDate);
	} else {
		console.log('⚠️ No se encontró banner OPEN');
	}

	// Actualizar fecha de inicio del curso en banner CLOSED
	const courseStartClosed = document.querySelector('#enrollmentBanner.closed .course-start-date');
	if (courseStartClosed) {
		courseStartClosed.textContent = COURSE_CONFIG.courseStartDate;
		console.log('✅ Fecha actualizada en banner CLOSED:', COURSE_CONFIG.courseStartDate);
	} else {
		console.log('⚠️ No se encontró banner CLOSED');
	}

	// Actualizar periodo de inscripciones en banner CLOSED
	const enrollmentPeriod = document.querySelector('#enrollmentBanner.closed .enrollment-period');
	if (enrollmentPeriod) {
		enrollmentPeriod.textContent = `${COURSE_CONFIG.enrollmentOpenDate} al ${COURSE_CONFIG.enrollmentCloseDate}`;
		console.log('✅ Periodo actualizado en banner CLOSED');
	}

	// Actualizar mes de próxima apertura en sticky bar
	const stickyBarMonth = document.querySelector('.sticky-bar .next-opening-month');
	if (stickyBarMonth) {
		stickyBarMonth.textContent = COURSE_CONFIG.nextOpeningMonth;
		console.log('✅ Mes actualizado en sticky bar:', COURSE_CONFIG.nextOpeningMonth);
	} else {
		console.log('⚠️ No se encontró sticky bar');
	}

	// Actualizar fecha de próxima cohorte en lead-magnet
	const nextCohortDate = document.querySelector('.next-cohort-date');
	if (nextCohortDate) {
		// Extraer mes y año de la fecha de inicio del curso (ej: "1 de Marzo de 2026" -> "Marzo 2026")
		const dateMatch = COURSE_CONFIG.courseStartDate.match(/de\s+(\w+)\s+de\s+(\d{4})/);
		if (dateMatch) {
			nextCohortDate.textContent = `${dateMatch[1]} ${dateMatch[2]}`;
			console.log('✅ Fecha de cohorte actualizada:', `${dateMatch[1]} ${dateMatch[2]}`);
		}
	} else {
		console.log('⚠️ No se encontró next-cohort-date');
	}

	console.log('✅ Actualización de fechas completada');
}

// Ejecutar al cargar la página
document.addEventListener('DOMContentLoaded', updateCourseDates);

// ============================================================================
// COUNTDOWN TIMER (Enrollment Open State)
// ============================================================================

function updateCountdown() {
	const now = new Date().getTime();
	const distance = COURSE_CONFIG.enrollmentEndDateTime - now;

	// If countdown is over, switch to CLOSED state
	if (distance < 0) {
		const banner = document.getElementById('enrollmentBanner');
		if (banner) {
			banner.classList.remove('open');
			banner.classList.add('closed');
		}
		return;
	}

	// Calculate time units
	const days = Math.floor(distance / (1000 * 60 * 60 * 24));
	const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
	const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));

	// Update DOM
	const daysEl = document.getElementById('days');
	const hoursEl = document.getElementById('hours');
	const minutesEl = document.getElementById('minutes');

	if (daysEl) daysEl.textContent = days.toString().padStart(2, '0');
	if (hoursEl) hoursEl.textContent = hours.toString().padStart(2, '0');
	if (minutesEl) minutesEl.textContent = minutes.toString().padStart(2, '0');
}

// Run countdown if banner is in OPEN state
document.addEventListener('DOMContentLoaded', function() {
	const banner = document.getElementById('enrollmentBanner');
	if (banner && banner.classList.contains('open')) {
		updateCountdown();
		setInterval(updateCountdown, 60000); // Update every minute
	}
});

// ============================================================================
// FORM SUBMISSION HANDLER - Lead Magnet
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
	// Lead Magnet Form (Main section)
	const leadMagnetForm = document.getElementById('leadMagnetForm');
	if (leadMagnetForm) {
		handleLeadForm(leadMagnetForm);
	}
});

function handleLeadForm(form) {
	form.addEventListener('submit', function(e) {
		e.preventDefault();

		const formData = new FormData(form);
		const nombre = formData.get('nombre');
		const email = formData.get('email');

		// Disable button
		const submitBtn = form.querySelector('button[type="submit"]');
		const originalHTML = submitBtn.innerHTML;
		submitBtn.disabled = true;
		submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Procesando...';

		// TODO: Send to Hostinger Reach API
		// For now, simulate success and redirect to Teachable
		setTimeout(function() {
			// Construct Teachable URL with auto-enrollment coupon
			const teachableURL = 'https://jorge-alonso-s-school.teachable.com/p/clase-cero?coupon=CLASE-CERO-2026';

			// Show success message
			alert('¡Perfecto! Redirigiendo a tu Clase Cero...');

			// Redirect to Teachable
			window.location.href = teachableURL;

			// Reset button (in case redirect fails)
			submitBtn.disabled = false;
			submitBtn.innerHTML = originalHTML;
		}, 1000);
	});
}
