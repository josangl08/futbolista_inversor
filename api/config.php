<?php
/**
 * Configuración para Lead Magnet - Futbolista Inversor
 *
 * Este archivo centraliza la configuración de APIs y URLs
 * para el sistema de captura de leads.
 */

// Evitar acceso directo
if (!defined('LEAD_CAPTURE_LOADED')) {
    http_response_code(403);
    die('Acceso denegado');
}

// Cargar variables de entorno desde .env
function loadEnvIfNotLoaded() {
    // Si ya está cargada, no hacer nada
    if (defined('HOSTINGER_API_KEY')) {
        return;
    }

    $envPath = __DIR__ . '/../.env';
    if (!file_exists($envPath)) {
        error_log("ERROR: Archivo .env no encontrado en: " . $envPath);
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parsear KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $_ENV[$key] = $value;
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
}

// Cargar .env
loadEnvIfNotLoaded();

// ===========================================================================
// HOSTINGER REACH API
// ===========================================================================

// API Key de Hostinger (cargada desde .env)
if (!defined('HOSTINGER_API_KEY')) {
    error_log("ERROR: HOSTINGER_API_KEY no está definida en .env");
    http_response_code(500);
    die('Error de configuración del servidor');
}

// Base URL de la API de Hostinger
define('HOSTINGER_API_BASE_URL', 'https://developers.hostinger.com');

// Endpoint para crear contactos en Reach
define('HOSTINGER_REACH_CONTACTS_ENDPOINT', '/api/reach/v1/contacts');

// ===========================================================================
// TEACHABLE — Dos cursos según segmento
// ===========================================================================

// URL base de la escuela en Teachable
define('TEACHABLE_SCHOOL_URL', 'https://jorge-alonso-s-school.teachable.com');

// Código del cupón 100% descuento (aplica a ambos cursos)
define('TEACHABLE_COUPON_CODE', 'CLASE1CALENTAMIENTO');

// Curso 1: Deportistas (futbolistas + otros deportistas)
// Curso: pre-curso-educacion-financiera | ID curso: 2932779
define('TEACHABLE_DEPORTISTAS_PRODUCT_ID', '2932779');
define('TEACHABLE_CHECKOUT_URL_DEPORTISTAS', TEACHABLE_SCHOOL_URL . '/purchase?product_id=' . TEACHABLE_DEPORTISTAS_PRODUCT_ID . '&coupon_code=' . TEACHABLE_COUPON_CODE);

// Curso 2: Profesionales (resto de personas)
// Curso: clase-0-educacion-financiera | ID curso: 2936467
define('TEACHABLE_PROFESIONALES_PRODUCT_ID', '2936467');
define('TEACHABLE_CHECKOUT_URL_PROFESIONALES', TEACHABLE_SCHOOL_URL . '/purchase?product_id=' . TEACHABLE_PROFESIONALES_PRODUCT_ID . '&coupon_code=' . TEACHABLE_COUPON_CODE);

// ===========================================================================
// CONFIGURACIÓN DE SEGURIDAD
// ===========================================================================

// Rate limiting para leads (menos restrictivo que contacto)
define('LEAD_MAX_ATTEMPTS', 3);
define('LEAD_TIME_WINDOW', 900); // 15 minutos

// Archivo para rate limiting
define('LEAD_RATE_LIMIT_FILE', __DIR__ . '/lead_rate_limit.json');

// Archivo de log
define('LEAD_LOG_FILE', __DIR__ . '/lead_capture.log');

?>
