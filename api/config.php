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

// ===========================================================================
// HOSTINGER REACH API
// ===========================================================================

// API Key de Hostinger (obtenida desde Settings > General)
define('HOSTINGER_API_KEY', 'zNHESktyjzikWcRiN9S9Aqo0a6dQj7UeClGutj3Cecd291a1');

// Base URL de la API de Hostinger
define('HOSTINGER_API_BASE_URL', 'https://developers.hostinger.com');

// Endpoint para crear contactos en Reach
define('HOSTINGER_REACH_CONTACTS_ENDPOINT', '/api/reach/v1/contacts');

// ===========================================================================
// TEACHABLE
// ===========================================================================

// URL base de tu escuela en Teachable
define('TEACHABLE_SCHOOL_URL', 'https://jorge-alonso-s-school.teachable.com');

// ID del producto "Clase Uno"
define('TEACHABLE_PRODUCT_ID', '6607492');

// Código del cupón 100% descuento
define('TEACHABLE_COUPON_CODE', 'CLASE1CALENTAMIENTO');

// URL completa de checkout con cupón auto-aplicado
define('TEACHABLE_CHECKOUT_URL', TEACHABLE_SCHOOL_URL . '/purchase?product_id=' . TEACHABLE_PRODUCT_ID . '&coupon_code=' . TEACHABLE_COUPON_CODE);

// ===========================================================================
// CONFIGURACIÓN DE TAGS Y METADATOS
// ===========================================================================

// Tags para añadir a contactos en Hostinger Reach
define('REACH_TAGS', ['lead-magnet', 'clase-uno', 'website-form']);

// Nombre de la lista o segmento (para logging)
define('REACH_LIST_NAME', 'Lead Magnet - Clase Uno');

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
