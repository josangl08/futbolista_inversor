<?php
/**
 * Pre-Checkout Email Capture Endpoint - Futbolista Inversor
 *
 * Este endpoint captura email y nombre antes de redirigir a Teachable
 * para comprar un curso. Los datos se almacenan para enviar email
 * de confirmación después de la compra.
 *
 * Flujo:
 * 1. Recibe nombre + email + tier del modal pre-checkout
 * 2. Valida y aplica medidas anti-spam
 * 3. Almacena en data/pre-checkout-emails.json
 * 4. Retorna success para que JS redirija a Teachable
 */

// Marcar que el archivo puede ser cargado
define('PRE_CHECKOUT_CAPTURE_LOADED', true);

// Cargar configuración
require_once __DIR__ . '/config.php';

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0); // 0 en producción
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../data/pre-checkout.log');

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
 * Rate limiting con flock para evitar race conditions
 */
function checkPreCheckoutRateLimit($ip) {
    $limitFile = __DIR__ . '/../data/pre-checkout-rate-limit.json';
    $maxAttempts = 3;
    $timeWindow = 900;

    $fp = fopen($limitFile, 'c+');
    if (!$fp) return true;

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return true;
    }

    $content = stream_get_contents($fp);
    $limits = json_decode($content, true) ?: [];

    $currentTime = time();
    foreach ($limits as $key => $data) {
        if ($currentTime - $data['time'] > $timeWindow) {
            unset($limits[$key]);
        }
    }

    $allowed = true;
    if (isset($limits[$ip])) {
        if ($limits[$ip]['count'] >= $maxAttempts) {
            $allowed = false;
        } else {
            $limits[$ip]['count']++;
        }
    } else {
        $limits[$ip] = ['count' => 1, 'time' => $currentTime];
    }

    if ($allowed) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($limits));
    }

    flock($fp, LOCK_UN);
    fclose($fp);
    return $allowed;
}

/**
 * Verificar honeypot
 */
function checkHoneypot($value) {
    return empty($value);
}

/**
 * Verificar timestamp (mínimo 3 segundos para compras)
 */
function checkTimestamp($timestamp) {
    if (empty($timestamp) || !is_numeric($timestamp)) {
        return false;
    }

    $currentTime = round(microtime(true) * 1000);
    $timeDiff = ($currentTime - $timestamp) / 1000;

    // Mínimo 3 segundos, máximo 1 hora
    return $timeDiff >= 3 && $timeDiff <= 3600;
}

/**
 * Logging de eventos
 */
function logEvent($event, $data) {
    $logFile = __DIR__ . '/../data/pre-checkout.log';
    $logEntry = date('Y-m-d H:i:s') . " | {$event} | " . json_encode($data) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
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

// Validar Content-Type: protección CSRF para endpoints JSON
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') === false) {
    http_response_code(415);
    echo json_encode(['success' => false, 'message' => 'Content-Type no válido']);
    exit;
}

// Obtener IP del cliente
$clientIP = $_SERVER['REMOTE_ADDR'];

// Verificar rate limiting
if (!checkPreCheckoutRateLimit($clientIP)) {
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => 'Demasiados intentos. Por favor, espera unos minutos.',
        'rateLimit' => true
    ]);
    logEvent('RATE_LIMIT', ['ip' => $clientIP]);
    exit;
}

// Obtener datos del formulario (JSON)
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

// Extraer y sanitizar datos
$nombre = isset($data['nombre']) ? sanitizeInput($data['nombre']) : '';
$email = isset($data['email']) ? sanitizeInput($data['email']) : '';
$tier = isset($data['tier']) ? sanitizeInput($data['tier']) : '';
$product_id = isset($data['product_id']) ? sanitizeInput($data['product_id']) : '';
$honeypot = isset($data['empresa']) ? $data['empresa'] : '';
$timestamp = isset($data['timestamp']) ? $data['timestamp'] : '';

// Validación anti-spam: honeypot
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

// Validación anti-spam: timestamp
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

// Validar tier
$validTiers = ['basic', 'premium', 'elite'];
if (empty($tier) || !in_array($tier, $validTiers)) {
    $errors['tier'] = 'Tier inválido';
}

// Validar product_id
$validProductIds = ['6589884', '6597569', '6601525'];
if (empty($product_id) || !in_array($product_id, $validProductIds)) {
    $errors['product_id'] = 'Producto inválido';
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
// ALMACENAR EN ARCHIVO JSON
// ===========================================================================

$dataFile = __DIR__ . '/../data/pre-checkout-emails.json';

// Leer datos existentes
$storedData = ['emails' => []];
if (file_exists($dataFile)) {
    $content = file_get_contents($dataFile);
    $storedData = json_decode($content, true);
    if (!is_array($storedData) || !isset($storedData['emails'])) {
        $storedData = ['emails' => []];
    }
}

// Crear nueva entrada
$newEntry = [
    'email' => $email,
    'nombre' => $nombre,
    'tier' => $tier,
    'timestamp' => time(),
    'product_id' => $product_id,
    'purchase_completed' => false,
    'sale_id' => null,
    'ip' => $clientIP
];

// Añadir a la lista
$storedData['emails'][] = $newEntry;

// Escribir con file locking para evitar race conditions
$fp = fopen($dataFile, 'w');
if ($fp) {
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($storedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        flock($fp, LOCK_UN);
        fclose($fp);
    } else {
        fclose($fp);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error al guardar los datos. Inténtalo de nuevo.'
        ]);
        logEvent('FILE_LOCK_ERROR', ['ip' => $clientIP, 'email' => $email]);
        exit;
    }
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar los datos. Inténtalo de nuevo.'
    ]);
    logEvent('FILE_OPEN_ERROR', ['ip' => $clientIP, 'email' => $email]);
    exit;
}

// ===========================================================================
// ÉXITO
// ===========================================================================

logEvent('SUCCESS', [
    'ip' => $clientIP,
    'nombre' => $nombre,
    'email' => $email,
    'tier' => $tier,
    'product_id' => $product_id
]);

echo json_encode([
    'success' => true,
    'message' => 'Datos guardados. Redirigiendo a Teachable...'
]);

?>
