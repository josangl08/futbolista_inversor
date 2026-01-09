<?php
/**
 * Procesador de Formulario de Contacto - Futbolista Inversor
 *
 * Este script procesa el formulario de contacto del sitio web
 * y envía los datos por email usando PHPMailer.
 *
 * IMPORTANTE: Debes configurar PHPMailer en tu servidor Hostinger
 * Tutorial: https://www.hostinger.com/es/tutoriales/enviar-emails-usando-php-mail
 */

// Cargar variables de entorno desde archivo .env
function loadEnv($path = '.env') {
    if (!file_exists($path)) {
        error_log("ERROR: Archivo .env no encontrado en: " . $path);
        die('Error de configuración del servidor. Contacta al administrador.');
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorar comentarios y líneas vacías
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parsear línea con formato KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Guardar en $_ENV y definir como constante
            $_ENV[$key] = $value;
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
}

// Cargar configuración desde .env
loadEnv(__DIR__ . '/.env');

// Configuración de errores (desactivar en producción)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Cambiar a 0 en producción
ini_set('log_errors', 1);
ini_set('error_log', 'form_errors.log');

// Headers de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Iniciar sesión para CSRF token (descomentar cuando implementes sesiones)
// session_start();

/**
 * Función para sanitizar inputs
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Función para validar email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Rate limiting básico usando archivo
 * Limita a 5 envíos por IP cada 15 minutos
 */
function checkRateLimit($ip) {
    $limitFile = 'rate_limit.json';
    $maxAttempts = 5;
    $timeWindow = 900; // 15 minutos en segundos

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

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Método no permitido');
}

// Obtener IP del cliente
$clientIP = $_SERVER['REMOTE_ADDR'];

// Verificar rate limiting
if (!checkRateLimit($clientIP)) {
    http_response_code(429);
    die('Demasiados intentos. Por favor, espera 15 minutos.');
}

// Validar CSRF token (descomentar cuando implementes sesiones)
/*
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    die('Token CSRF inválido');
}
*/

// Obtener y sanitizar datos del formulario
$nombre = isset($_POST['nombre']) ? sanitizeInput($_POST['nombre']) : '';
$email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
$mensaje = isset($_POST['mensaje']) ? sanitizeInput($_POST['mensaje']) : '';

// Validaciones
$errors = [];

if (empty($nombre) || strlen($nombre) < 2) {
    $errors[] = 'El nombre debe tener al menos 2 caracteres';
}

if (empty($email) || !validateEmail($email)) {
    $errors[] = 'Email inválido';
}

if (empty($mensaje) || strlen($mensaje) < 10) {
    $errors[] = 'El mensaje debe tener al menos 10 caracteres';
}

// Si hay errores, retornar
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// ===========================================================================
// CONFIGURACIÓN DE EMAIL - CONFIGURACIÓN PARA HOSTINGER
// ===========================================================================

// Opción 1: Usar PHPMailer (RECOMENDADO)
// IMPORTANTE: Debes instalar PHPMailer primero en Hostinger
// Tutorial: https://www.hostinger.com/es/tutoriales/enviar-emails-usando-php-mail
/*
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // O la ruta donde instalaste PHPMailer

$mail = new PHPMailer(true);

try {
    // Configuración del servidor SMTP usando variables de entorno
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST; // Desde .env
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME; // Desde .env
    $mail->Password   = SMTP_PASSWORD; // Desde .env
    $mail->SMTPSecure = (SMTP_ENCRYPTION === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT; // Desde .env
    $mail->CharSet    = 'UTF-8';

    // Remitente y destinatario (desde .env)
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress(CONTACT_EMAIL, CONTACT_NAME);
    $mail->addReplyTo($email, $nombre);

    // Contenido del email
    $mail->isHTML(true);
    $mail->Subject = 'Nuevo mensaje de contacto - ' . $nombre;
    $mail->Body    = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { padding: 20px; background-color: #f4f4f4; }
                .content { background-color: white; padding: 20px; border-radius: 5px; }
                .label { font-weight: bold; color: #36E3A0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='content'>
                    <h2 style='color: #132D4B;'>Nuevo mensaje de contacto</h2>
                    <p><span class='label'>Nombre:</span> {$nombre}</p>
                    <p><span class='label'>Email:</span> {$email}</p>
                    <p><span class='label'>Mensaje:</span></p>
                    <p>" . nl2br($mensaje) . "</p>
                    <hr>
                    <p style='font-size: 12px; color: #666;'>
                        IP del remitente: {$clientIP}<br>
                        Fecha: " . date('Y-m-d H:i:s') . "
                    </p>
                </div>
            </div>
        </body>
        </html>
    ";
    $mail->AltBody = "Nuevo mensaje de contacto\n\nNombre: {$nombre}\nEmail: {$email}\n\nMensaje:\n{$mensaje}\n\nIP: {$clientIP}\nFecha: " . date('Y-m-d H:i:s');

    $mail->send();

    // Log exitoso
    error_log("Formulario enviado exitosamente desde {$email}");

    // Respuesta exitosa
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Mensaje enviado correctamente']);

} catch (Exception $e) {
    error_log("Error al enviar email: {$mail->ErrorInfo}");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al enviar el mensaje']);
}
*/

// ===========================================================================
// Opción 2: Usar mail() de PHP (NO RECOMENDADO - solo para pruebas)
// Usando variables de entorno desde .env
// ===========================================================================

$to = CONTACT_EMAIL; // Desde .env
$subject = 'Nuevo mensaje de contacto - ' . $nombre;
$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=UTF-8\r\n";
$headers .= "From: " . SMTP_FROM_EMAIL . "\r\n"; // Desde .env
$headers .= "Reply-To: {$email}\r\n";

$body = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { padding: 20px; background-color: #f4f4f4; }
        .content { background-color: white; padding: 20px; border-radius: 5px; }
        .label { font-weight: bold; color: #36E3A0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='content'>
            <h2 style='color: #132D4B;'>Nuevo mensaje de contacto</h2>
            <p><span class='label'>Nombre:</span> {$nombre}</p>
            <p><span class='label'>Email:</span> {$email}</p>
            <p><span class='label'>Mensaje:</span></p>
            <p>" . nl2br($mensaje) . "</p>
            <hr>
            <p style='font-size: 12px; color: #666;'>
                IP del remitente: {$clientIP}<br>
                Fecha: " . date('Y-m-d H:i:s') . "
            </p>
        </div>
    </div>
</body>
</html>
";

if (mail($to, $subject, $body, $headers)) {
    error_log("Formulario enviado exitosamente desde {$email}");
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Mensaje enviado correctamente']);
} else {
    error_log("Error al enviar email desde {$email}");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al enviar el mensaje']);
}

?>
