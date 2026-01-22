<?php
/**
 * Lead Capture Endpoint - Futbolista Inversor
 *
 * Este endpoint procesa el formulario de lead magnet,
 * envía los datos a Hostinger Reach y redirige a Teachable.
 *
 * Flujo:
 * 1. Recibe nombre + email del formulario
 * 2. Valida y aplica medidas anti-spam
 * 3. Envía contacto a Hostinger Reach API
 * 4. Retorna URL de Teachable con cupón auto-aplicado
 */

// Marcar que el archivo de configuración puede ser cargado
define('LEAD_CAPTURE_LOADED', true);

// Cargar configuración
require_once __DIR__ . '/config.php';

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0); // 0 en producción
ini_set('log_errors', 1);
ini_set('error_log', LEAD_LOG_FILE);

// Headers de seguridad y JSON
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// ===========================================================================
// FUNCIONES AUXILIARES
// ===========================================================================

/**
 * Sanitizar inputs
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validar email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Rate limiting para leads
 */
function checkLeadRateLimit($ip) {
    $limitFile = LEAD_RATE_LIMIT_FILE;
    $maxAttempts = LEAD_MAX_ATTEMPTS;
    $timeWindow = LEAD_TIME_WINDOW;

    $limits = [];
    if (file_exists($limitFile)) {
        $limits = json_decode(file_get_contents($limitFile), true);
    }

    $currentTime = time();

    // Limpiar entradas antiguas
    foreach ($limits as $key => $data) {
        if ($currentTime - $data['time'] > $timeWindow) {
            unset($limits[$key]);
        }
    }

    // Verificar límite
    if (isset($limits[$ip])) {
        if ($limits[$ip]['count'] >= $maxAttempts) {
            return false;
        }
        $limits[$ip]['count']++;
    } else {
        $limits[$ip] = ['count' => 1, 'time' => $currentTime];
    }

    file_put_contents($limitFile, json_encode($limits));
    return true;
}

/**
 * Verificar honeypot
 */
function checkHoneypot($value) {
    return empty($value);
}

/**
 * Verificar timestamp (mínimo 2 segundos para leads)
 */
function checkTimestamp($timestamp) {
    if (empty($timestamp) || !is_numeric($timestamp)) {
        return false;
    }

    $currentTime = round(microtime(true) * 1000);
    $timeDiff = ($currentTime - $timestamp) / 1000;

    // Mínimo 2 segundos (más permisivo), máximo 1 hora
    return $timeDiff >= 2 && $timeDiff <= 3600;
}

/**
 * Enviar contacto a Hostinger Reach API
 */
function sendToHostingerReach($nombre, $email) {
    $url = HOSTINGER_API_BASE_URL . HOSTINGER_REACH_CONTACTS_ENDPOINT;

    // Preparar payload según documentación de Hostinger API
    $payload = [
        'email' => $email,
        'name' => $nombre,
        // Tags y custom fields opcionales
        'tags' => REACH_TAGS,
        'custom_fields' => [
            'source' => 'website-lead-magnet',
            'form_type' => 'clase-uno',
            'date_captured' => date('Y-m-d H:i:s'),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown'
        ]
    ];

    // Configurar cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . HOSTINGER_API_KEY,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    // Ejecutar request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Log de debug
    error_log("Hostinger API Request - Email: {$email}, HTTP Code: {$httpCode}");

    if ($curlError) {
        error_log("Hostinger API cURL Error: {$curlError}");
        return ['success' => false, 'error' => 'Connection error', 'details' => $curlError];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        error_log("Hostinger API Success - Contacto creado: {$email}");
        return ['success' => true, 'response' => json_decode($response, true)];
    } else {
        error_log("Hostinger API Error - Code: {$httpCode}, Response: {$response}");
        return ['success' => false, 'error' => 'API error', 'http_code' => $httpCode, 'response' => $response];
    }
}

/**
 * Logging de eventos
 */
function logEvent($event, $data) {
    $logEntry = date('Y-m-d H:i:s') . " | {$event} | " . json_encode($data) . "\n";
    file_put_contents(LEAD_LOG_FILE, $logEntry, FILE_APPEND);
}

// ===========================================================================
// PROCESAMIENTO DE LA REQUEST
// ===========================================================================

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener IP del cliente
$clientIP = $_SERVER['REMOTE_ADDR'];

// Verificar rate limiting
if (!checkLeadRateLimit($clientIP)) {
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => 'Demasiados intentos. Por favor, espera unos minutos.',
        'rateLimit' => true
    ]);
    logEvent('RATE_LIMIT', ['ip' => $clientIP]);
    exit;
}

// Obtener datos del formulario
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

// Soportar tanto JSON como form-data
$nombre = isset($data['nombre']) ? sanitizeInput($data['nombre']) : (isset($_POST['nombre']) ? sanitizeInput($_POST['nombre']) : '');
$email = isset($data['email']) ? sanitizeInput($data['email']) : (isset($_POST['email']) ? sanitizeInput($_POST['email']) : '');
$honeypot = isset($data['website']) ? $data['website'] : (isset($_POST['website']) ? $_POST['website'] : '');
$timestamp = isset($data['form_timestamp']) ? $data['form_timestamp'] : (isset($_POST['form_timestamp']) ? $_POST['form_timestamp'] : '');

// Validación anti-spam básica
if (!checkHoneypot($honeypot)) {
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => 'Error de validación. Por favor, recarga la página.',
        'spam_detected' => true
    ]);
    logEvent('SPAM_HONEYPOT', ['ip' => $clientIP, 'email' => $email]);
    exit;
}

if (!checkTimestamp($timestamp)) {
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => 'Error de validación. Por favor, intenta de nuevo.',
        'spam_detected' => true
    ]);
    logEvent('SPAM_TIMESTAMP', ['ip' => $clientIP, 'email' => $email, 'timestamp' => $timestamp]);
    exit;
}

// Validaciones de campos
$errors = [];

if (empty($nombre) || strlen($nombre) < 2) {
    $errors['nombre'] = 'El nombre debe tener al menos 2 caracteres';
}

if (strlen($nombre) > 100) {
    $errors['nombre'] = 'El nombre es demasiado largo';
}

if (empty($email) || !validateEmail($email)) {
    $errors['email'] = 'Email inválido';
}

if (!empty($errors)) {
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'errors' => $errors,
        'message' => 'Por favor, corrige los errores en el formulario'
    ]);
    logEvent('VALIDATION_ERROR', ['ip' => $clientIP, 'errors' => $errors]);
    exit;
}

// ===========================================================================
// ENVIAR A HOSTINGER REACH
// ===========================================================================

$apiResult = sendToHostingerReach($nombre, $email);

if (!$apiResult['success']) {
    // Error al enviar a Hostinger, pero no bloquear al usuario
    // Registrar error y continuar con redirección a Teachable
    error_log("ERROR: No se pudo añadir contacto a Hostinger Reach - Email: {$email}");
    logEvent('API_ERROR', [
        'ip' => $clientIP,
        'email' => $email,
        'error' => $apiResult['error'],
        'http_code' => $apiResult['http_code'] ?? 'N/A'
    ]);

    // IMPORTANTE: Aún así retornamos success para no bloquear al usuario
    // El usuario debe poder acceder a Teachable aunque falle Hostinger
    echo json_encode([
        'success' => true,
        'message' => 'Redirigiendo a tu clase gratuita...',
        'redirectUrl' => TEACHABLE_CHECKOUT_URL,
        'warning' => 'partial_success' // Para logging interno
    ]);
    exit;
}

// ===========================================================================
// ÉXITO COMPLETO
// ===========================================================================

logEvent('SUCCESS', [
    'ip' => $clientIP,
    'nombre' => $nombre,
    'email' => $email,
    'hostinger_success' => true
]);

echo json_encode([
    'success' => true,
    'message' => '¡Perfecto! Redirigiendo a tu clase gratuita...',
    'redirectUrl' => TEACHABLE_CHECKOUT_URL
]);

?>
