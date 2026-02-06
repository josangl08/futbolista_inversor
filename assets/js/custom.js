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
	// ==========================================
	// CONTROL PRINCIPAL - Cambiar solo esta línea cuando abras/cierres inscripciones
	// ==========================================
	enrollmentStatus: 'open', // 'open' o 'closed'

	// Fecha de inicio del curso
	courseStartDate: '1 de Marzo de 2026',

	// Periodo de inscripciones
	enrollmentOpenDate: '13 de Febrero de 2026',
	enrollmentCloseDate: '28 de Febrero de 2026',

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
					// ÉXITO - Mostrar modal con borde verde
					modalHeader.classList.remove('border-error');
					modalHeader.classList.add('border-success');
					modalIcon.className = 'fas fa-check-circle me-2 icon-success';
					modalTitulo.textContent = '¡Mensaje Enviado!';
					modalMensaje.textContent = response.message || 'Tu mensaje ha sido enviado correctamente. Nos pondremos en contacto contigo pronto.';

					// Limpiar formulario
					form.reset();

					// Mostrar modal
					modal.show();
				} else {
					// ERROR DE VALIDACIÓN
					modalHeader.classList.remove('border-success');
					modalHeader.classList.add('border-error');
					modalIcon.className = 'fas fa-exclamation-triangle me-2 icon-error';
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

				modalHeader.classList.remove('border-success');
				modalHeader.classList.add('border-error');
				modalIcon.className = 'fas fa-exclamation-triangle me-2 icon-error';
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
	const courseStartOpen = document.querySelector('#enrollmentBannerOpen .course-start-date');
	if (courseStartOpen) {
		courseStartOpen.textContent = COURSE_CONFIG.courseStartDate;
		console.log('✅ Fecha actualizada en banner OPEN:', COURSE_CONFIG.courseStartDate);
	} else {
		console.log('⚠️ No se encontró banner OPEN');
	}

	// Actualizar fecha de inicio del curso en banner CLOSED
	const courseStartClosed = document.querySelector('#enrollmentBannerClosed .course-start-date');
	if (courseStartClosed) {
		courseStartClosed.textContent = COURSE_CONFIG.courseStartDate;
		console.log('✅ Fecha actualizada en banner CLOSED:', COURSE_CONFIG.courseStartDate);
	} else {
		console.log('⚠️ No se encontró banner CLOSED');
	}

	// Actualizar fechas de inscripciones en banner CLOSED (por separado para mejor legibilidad)
	const enrollmentOpenDate = document.querySelector('#enrollmentBannerClosed .enrollment-open-date');
	const enrollmentCloseDate = document.querySelector('#enrollmentBannerClosed .enrollment-close-date');

	if (enrollmentOpenDate) {
		enrollmentOpenDate.textContent = COURSE_CONFIG.enrollmentOpenDate;
		console.log('✅ Fecha de apertura actualizada en banner CLOSED:', COURSE_CONFIG.enrollmentOpenDate);
	} else {
		console.log('⚠️ No se encontró fecha de apertura');
	}

	if (enrollmentCloseDate) {
		enrollmentCloseDate.textContent = COURSE_CONFIG.enrollmentCloseDate;
		console.log('✅ Fecha de cierre actualizada en banner CLOSED:', COURSE_CONFIG.enrollmentCloseDate);
	} else {
		console.log('⚠️ No se encontró fecha de cierre');
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

	// Actualizar fecha de apertura en modal de inscripciones cerradas
	const modalEnrollmentDate = document.querySelector('#modalInscripcionesCerradas .enrollment-open-date');
	if (modalEnrollmentDate) {
		modalEnrollmentDate.textContent = COURSE_CONFIG.enrollmentOpenDate;
		console.log('✅ Fecha de apertura actualizada en modal');
	} else {
		console.log('⚠️ No se encontró modal enrollment-open-date');
	}

	console.log('✅ Actualización de fechas completada');
}

// Ejecutar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
	updateCourseDates();
	initEnrollmentState();

	// Configurar botón del modal de inscripciones cerradas
	const btnModalGetClass = document.getElementById('btnModalGetClass');
	if (btnModalGetClass) {
		btnModalGetClass.addEventListener('click', function() {
			// Obtener instancia del modal
			const modalElement = document.getElementById('modalInscripcionesCerradas');
			const modal = bootstrap.Modal.getInstance(modalElement);

			if (modal) {
				// Cerrar el modal
				modal.hide();

				// Esperar a que el modal termine de cerrarse, luego hacer scroll
				modalElement.addEventListener('hidden.bs.modal', function() {
					const leadMagnetSection = document.getElementById('lead-magnet');
					if (leadMagnetSection) {
						leadMagnetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
						console.log('✅ Redirigiendo a #lead-magnet desde botón del modal');
					}
				}, { once: true });
			}
		});
		console.log('✅ Botón del modal configurado');
	}

	// Configurar botón del modal de confirmación de lead
	const btnAccederClase = document.getElementById('btnAccederClase');
	if (btnAccederClase) {
		btnAccederClase.addEventListener('click', function() {
			// Obtener URL guardada
			const redirectUrl = window.teachableRedirectUrl;

			if (redirectUrl) {
				console.log('✅ Redirigiendo a Teachable:', redirectUrl);
				window.location.href = redirectUrl;
			} else {
				console.error('❌ No se encontró URL de redirección');
				alert('Error al redirigir. Por favor, revisa tu email para acceder al enlace.');
			}
		});
		console.log('✅ Botón de acceso a clase configurado');
	}
});

// ============================================================================
// COUNTDOWN TIMER (Enrollment Open State)
// ============================================================================

function updateCountdown() {
	const now = new Date().getTime();
	const distance = COURSE_CONFIG.enrollmentEndDateTime - now;

	const banner = document.getElementById('enrollmentBannerOpen');

	if (!banner || distance < 0) {
		// Countdown expirado - cambiar a CLOSED automáticamente
		console.log('⏰ Countdown expirado - Cambiando a estado CLOSED');

		// Cambiar estado en la configuración
		COURSE_CONFIG.enrollmentStatus = 'closed';

		// Reiniciar el sistema de inscripciones
		initEnrollmentState();

		return;
	}

	// Calcular tiempo restante
	const days = Math.floor(distance / (1000 * 60 * 60 * 24));
	const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
	const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));

	// Actualizar DOM
	const daysEl = document.getElementById('days');
	const hoursEl = document.getElementById('hours');
	const minutesEl = document.getElementById('minutes');

	if (daysEl) daysEl.textContent = String(days).padStart(2, '0');
	if (hoursEl) hoursEl.textContent = String(hours).padStart(2, '0');
	if (minutesEl) minutesEl.textContent = String(minutes).padStart(2, '0');
}

function initCountdown() {
	// Solo iniciar countdown si inscripciones están abiertas
	if (COURSE_CONFIG.enrollmentStatus === 'open') {
		updateCountdown();
		setInterval(updateCountdown, 60000); // Actualizar cada minuto
		console.log('⏰ Countdown iniciado');
	}
}

// ============================================================================
// SISTEMA DE GESTIÓN DE ESTADO DE INSCRIPCIONES
// ============================================================================

function initEnrollmentState() {
	console.log('🎯 Iniciando sistema de inscripciones...', COURSE_CONFIG.enrollmentStatus);

	const status = COURSE_CONFIG.enrollmentStatus;
	const bannerClosed = document.getElementById('enrollmentBannerClosed');
	const bannerOpen = document.getElementById('enrollmentBannerOpen');

	// Mostrar banner apropiado
	if (status === 'open') {
		if (bannerOpen) {
			bannerOpen.style.display = 'block';
			console.log('✅ Banner OPEN mostrado');
			// Iniciar countdown si está abierto
			initCountdown();
		}
		if (bannerClosed) {
			bannerClosed.style.display = 'none';
		}
	} else {
		if (bannerClosed) {
			bannerClosed.style.display = 'block';
			console.log('✅ Banner CLOSED mostrado');
		}
		if (bannerOpen) {
			bannerOpen.style.display = 'none';
		}
	}

	// Configurar comportamiento de botones de compra
	configurePurchaseButtons(status);

	// Actualizar texto del sticky bar según estado
	updateStickyBarText(status);
}

function configurePurchaseButtons(status) {
	// Seleccionar los 3 botones de compra en pricing section
	const purchaseButtons = document.querySelectorAll('#pricing .btn-inverse');

	console.log(`🔘 Configurando ${purchaseButtons.length} botones de compra para estado: ${status}`);

	if (status === 'closed') {
		purchaseButtons.forEach((button, index) => {
			button.addEventListener('click', function(e) {
				e.preventDefault(); // Prevenir navegación a Teachable
				console.log(`🚫 Botón ${index + 1} bloqueado - Inscripciones cerradas`);

				// Mostrar modal
				const modal = new bootstrap.Modal(document.getElementById('modalInscripcionesCerradas'));
				modal.show();

				// Cuando el modal se cierre, scroll a #lead-magnet
				const modalElement = document.getElementById('modalInscripcionesCerradas');
				modalElement.addEventListener('hidden.bs.modal', function() {
					// Scroll suave a lead-magnet
					const leadMagnetSection = document.getElementById('lead-magnet');
					if (leadMagnetSection) {
						leadMagnetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
					}
				}, { once: true }); // Solo ejecutar una vez
			});
		});
	} else {
		// Si está OPEN, asegurar que los botones funcionen normalmente
		purchaseButtons.forEach((button, index) => {
			console.log(`✅ Botón ${index + 1} habilitado - Inscripciones abiertas`);
			// No necesitamos hacer nada, los hrefs funcionarán por defecto
		});
	}
}

function updateStickyBarText(status) {
	const stickyBarMainText = document.getElementById('stickyBarMainText');
	const stickyBarSubText = document.getElementById('stickyBarSubText');

	if (!stickyBarMainText || !stickyBarSubText) {
		console.log('⚠️ No se encontraron elementos del sticky bar');
		return;
	}

	if (status === 'open') {
		// Estado ABIERTO: Solo cambiar texto (mantener icono de regalo)
		stickyBarMainText.textContent = 'Inscripciones abiertas.';
		stickyBarSubText.textContent = 'Accede a la primera Clase de manera Gratuita';
		console.log('✅ Sticky bar actualizado para estado OPEN');
	} else {
		// Estado CERRADO: Texto de próxima apertura (mantener icono de regalo)
		stickyBarMainText.innerHTML = `Próxima apertura en ${COURSE_CONFIG.nextOpeningMonth}.`;
		stickyBarSubText.textContent = 'Únete a la lista prioritaria y llévate la primera Clase de Regalo.';
		console.log('✅ Sticky bar actualizado para estado CLOSED');
	}
}


// ============================================================================
// FUNCIÓN: Toggle campo "Otra profesión"
// ============================================================================

function toggleOtroProfesionField(select) {
	const otroContainer = document.getElementById("otroProfesionContainer");
	const otroProfesionInput = document.getElementById("otraProfesion");

	if (select.value === "otro") {
		otroContainer.style.display = "block";
		otroProfesionInput.required = true;
	} else {
		otroContainer.style.display = "none";
		otroProfesionInput.required = false;
		otroProfesionInput.value = "";
	}
}
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
	// Inicializar timestamp cuando se carga el formulario
	const timestampField = form.querySelector('#leadFormTimestamp');
	if (timestampField) {
		timestampField.value = Date.now();
		console.log('✅ Timestamp del formulario inicializado');
	}

	form.addEventListener('submit', async function(e) {
		e.preventDefault();

		const formData = new FormData(form);
		const nombre = formData.get('nombre');
		const email = formData.get('email');
		const tipoUsuario = formData.get('tipo_usuario');
		const otraProfesion = formData.get('otra_profesion');
		const honeypot = formData.get('website');
		const timestamp = formData.get('form_timestamp');

		// Disable button
		const submitBtn = form.querySelector('button[type="submit"]');
		const originalHTML = submitBtn.innerHTML;
		submitBtn.disabled = true;
		submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Procesando...';

		try {
			// Enviar a backend PHP
			const response = await fetch('/api/lead-capture.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({
					nombre: nombre,
					email: email,
					tipo_usuario: tipoUsuario,
					otra_profesion: otraProfesion,
					website: honeypot,
					form_timestamp: timestamp
				})
			});

			const result = await response.json();

			if (result.success) {
				console.log('✅ Lead capturado correctamente:', email);

				// Cambiar estado del botón
				submitBtn.innerHTML = '<i class="fa fa-check me-2"></i> ¡Registrado!';

				// Guardar URL de redirección para el modal
				window.teachableRedirectUrl = result.redirectUrl;

				// Mostrar modal de confirmación
				const modalElement = document.getElementById('modalLeadConfirmation');
				const modal = new bootstrap.Modal(modalElement);
				modal.show();

				console.log('✅ Modal de confirmación mostrado');
			} else {
				// Error de validación o spam
				console.error('❌ Error al capturar lead:', result.message);

				if (result.rateLimit) {
					alert('Has intentado registrarte demasiadas veces. Por favor, espera unos minutos.');
				} else if (result.errors) {
					// Mostrar errores de validación
					let errorMsg = 'Por favor, corrige los siguientes errores:\n';
					for (const field in result.errors) {
						errorMsg += '- ' + result.errors[field] + '\n';
					}
					alert(errorMsg);
				} else {
					alert(result.message || 'Hubo un problema. Por favor, intenta de nuevo.');
				}

				// Restaurar botón
				submitBtn.disabled = false;
				submitBtn.innerHTML = originalHTML;
			}
		} catch (error) {
			console.error('❌ Error de conexión:', error);
			alert('Error de conexión. Por favor, verifica tu internet e intenta de nuevo.');

			// Restaurar botón
			submitBtn.disabled = false;
			submitBtn.innerHTML = originalHTML;
		}
	});
}

// ===========================================================================
// PRE-CHECKOUT MODAL - CAPTURA DE EMAIL ANTES DE COMPRA
// ===========================================================================

/**
 * Interceptar clicks en botones de compra y mostrar modal
 */
document.addEventListener('DOMContentLoaded', function() {
	const comprarButtons = document.querySelectorAll('.comprar-btn[data-tier]');

	comprarButtons.forEach(button => {
		button.addEventListener('click', function(e) {
			e.preventDefault();

			// Obtener datos del botón
			const tier = this.dataset.tier;
			const productId = this.dataset.productId;
			const teachableUrl = this.dataset.teachableUrl;

			// Validar que tenemos los datos necesarios
			if (!tier || !productId || !teachableUrl) {
				console.error('Error: Faltan datos en el botón de compra');
				alert('Error al procesar la solicitud. Por favor, recarga la página.');
				return;
			}

			// Poblar campos ocultos del modal
			document.getElementById('preCheckoutTier').value = tier;
			document.getElementById('preCheckoutProductId').value = productId;
			document.getElementById('preCheckoutTeachableUrl').value = teachableUrl;
			document.getElementById('preCheckoutTimestamp').value = Date.now();

			// Mostrar modal
			const modalElement = document.getElementById('modalPreCheckout');
			if (modalElement) {
				const modal = new bootstrap.Modal(modalElement);
				modal.show();
			} else {
				console.error('Error: Modal pre-checkout no encontrado');
			}
		});
	});

	// Manejar envío del formulario pre-checkout
	const preCheckoutForm = document.getElementById('formPreCheckout');
	if (preCheckoutForm) {
		preCheckoutForm.addEventListener('submit', async function(e) {
			e.preventDefault();

			const submitBtn = this.querySelector('button[type="submit"]');
			const originalHTML = submitBtn.innerHTML;

			// Validaciones del lado del cliente
			const nombre = document.getElementById('preCheckoutNombre').value.trim();
			const email = document.getElementById('preCheckoutEmail').value.trim();

			if (nombre.length < 2) {
				alert('Por favor, introduce tu nombre completo (mínimo 2 caracteres)');
				document.getElementById('preCheckoutNombre').focus();
				return;
			}

			if (!email || !email.includes('@')) {
				alert('Por favor, introduce un email válido');
				document.getElementById('preCheckoutEmail').focus();
				return;
			}

			// Deshabilitar botón y mostrar loading
			submitBtn.disabled = true;
			submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Procesando...';

			try {
				// Preparar datos
				const formData = {
					email: email,
					nombre: nombre,
					tier: document.getElementById('preCheckoutTier').value,
					product_id: document.getElementById('preCheckoutProductId').value,
					timestamp: document.getElementById('preCheckoutTimestamp').value,
					empresa: this.querySelector('[name="empresa"]').value // honeypot
				};

				// Enviar a PHP endpoint
				const response = await fetch('api/pre-checkout-capture.php', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json'
					},
					body: JSON.stringify(formData)
				});

				const result = await response.json();

				if (result.success) {
					// Guardar en localStorage como backup
					try {
						localStorage.setItem('preCheckoutEmail', formData.email);
						localStorage.setItem('preCheckoutNombre', formData.nombre);
						localStorage.setItem('preCheckoutTier', formData.tier);
						localStorage.setItem('preCheckoutTimestamp', Date.now());
					} catch (e) {
						// localStorage no disponible, no es crítico
						console.warn('localStorage no disponible:', e);
					}

					// Redirigir a Teachable
					const teachableUrl = document.getElementById('preCheckoutTeachableUrl').value;
					window.location.href = teachableUrl;

				} else {
					// Error en el servidor
					alert(result.message || 'Error al procesar. Por favor, inténtalo de nuevo.');

					// Restaurar botón
					submitBtn.disabled = false;
					submitBtn.innerHTML = originalHTML;
				}

			} catch (error) {
				console.error('Error en pre-checkout:', error);
				alert('Error de conexión. Por favor, verifica tu internet e intenta de nuevo.');

				// Restaurar botón
				submitBtn.disabled = false;
				submitBtn.innerHTML = originalHTML;
			}
		});
	}
});
