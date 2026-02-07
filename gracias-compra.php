<?php
/**
 * Thank You Page - Post-Purchase Confirmation
 *
 * Este archivo:
 * 1. Recibe redirect de Teachable con parámetros de compra
 * 2. Recupera el email almacenado en pre-checkout
 * 3. Envía email de confirmación con plantilla tier-específica
 * 4. Muestra página de agradecimiento personalizada
 */

// ========== MODO DEBUG: Comentar en producción ==========
$debugMode = true; // Cambiar a false cuando funcione
if ($debugMode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    echo "<pre style='background:#f0f0f0; padding:20px; border:2px solid #36E3A0;'>";
    echo "<h2>🔍 DEBUG MODE - gracias-compra.php</h2>";
    echo "<strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "\n";
    echo "<strong>Request URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
    echo "<strong>Query String:</strong> " . ($_SERVER['QUERY_STRING'] ?? 'N/A') . "\n";
    echo "<strong>GET Parameters:</strong>\n";
    print_r($_GET);
    echo "<strong>__DIR__:</strong> " . __DIR__ . "\n";
    echo "<strong>Script:</strong> " . ($_SERVER['SCRIPT_FILENAME'] ?? 'N/A') . "\n\n";
}

// Logging de diagnóstico
$logFile = __DIR__ . '/data/gracias-compra-debug.log';
$logEntry = date('Y-m-d H:i:s') . " | INICIO GRACIAS-COMPRA.PHP\n";
$logEntry .= "URL: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
$logEntry .= "Query String: " . ($_SERVER['QUERY_STRING'] ?? 'N/A') . "\n";
$logEntry .= "GET params: " . json_encode($_GET) . "\n";
$logEntry .= "__DIR__: " . __DIR__ . "\n";
$logEntry .= "Script: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'N/A') . "\n";
@file_put_contents($logFile, $logEntry, FILE_APPEND);

if ($debugMode) {
    echo "<strong>✓ Log escrito a:</strong> " . $logFile . "\n";
}

// Marcar que el archivo puede cargar config.php
define('PRE_CHECKOUT_CAPTURE_LOADED', true);
if ($debugMode) echo "✓ Constante PRE_CHECKOUT_CAPTURE_LOADED definida\n";

// Cargar configuración
try {
    if ($debugMode) echo "Intentando cargar config.php...\n";
    require_once __DIR__ . '/api/config.php';
    @file_put_contents($logFile, date('Y-m-d H:i:s') . " | Config.php cargado OK\n", FILE_APPEND);
    if ($debugMode) echo "✓ Config.php cargado correctamente\n";
} catch (Exception $e) {
    @file_put_contents($logFile, date('Y-m-d H:i:s') . " | ERROR cargando config: " . $e->getMessage() . "\n", FILE_APPEND);
    if ($debugMode) {
        echo "❌ ERROR cargando config: " . $e->getMessage() . "\n";
        echo "</pre>";
    }
    die("Error al cargar configuración");
}

// Cargar PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/phpmailer/PHPMailer-master/src/Exception.php';
require __DIR__ . '/vendor/phpmailer/PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/vendor/phpmailer/PHPMailer-master/src/SMTP.php';

// ===========================================================================
// 1. OBTENER PARÁMETROS DE TEACHABLE
// ===========================================================================

// IMPORTANTE: Teachable envía 'purchased' con el product_id, NO 'product_id' directamente
$purchased = $_GET['purchased'] ?? '';
$product_id = $purchased; // El product_id es el valor de 'purchased'
$user_id = $_GET['user_id'] ?? '';
$sale_id = $_GET['sale_id'] ?? '';
$final_price = $_GET['final_price'] ?? '';

if ($debugMode) {
    echo "\n<strong>📥 Parámetros procesados:</strong>\n";
    echo "product_id (de 'purchased'): {$product_id}\n";
    echo "sale_id: {$sale_id}\n";
    echo "user_id: {$user_id}\n";
    echo "final_price: {$final_price}\n";
}

// Log de los parámetros recibidos
error_log("Teachable Redirect - product_id: {$product_id}, sale_id: {$sale_id}, user_id: {$user_id}");
@file_put_contents($logFile, date('Y-m-d H:i:s') . " | Params - product_id: {$product_id}, sale: {$sale_id}, user: {$user_id}\n", FILE_APPEND);

// ===========================================================================
// 2. MAPEAR PRODUCT_ID A TIER
// ===========================================================================

$tierMap = [
    '6589884' => 'basic',
    '6597569' => 'premium',
    '6601525' => 'elite'
];

$tier = $tierMap[$product_id] ?? null;

if ($debugMode) {
    echo "\n<strong>🎯 Mapeo de tier:</strong>\n";
    echo "product_id '{$product_id}' → tier: " . ($tier ? "'{$tier}'" : "NULL (no encontrado)") . "\n";
    if (!$tier) {
        echo "❌ PROBLEMA: product_id no está en el mapa de tiers\n";
        echo "Mapa disponible: " . json_encode($tierMap) . "\n";
    }
}

// Datos de tier para mostrar
$tierNames = [
    'basic' => 'Programa Básico',
    'premium' => 'Programa Premium',
    'elite' => 'Programa Elite'
];

$tierPrices = [
    'basic' => '495€',
    'premium' => '895€',
    'elite' => '1.995€'
];

$tierName = $tierNames[$tier] ?? 'Programa';
$tierPrice = $tierPrices[$tier] ?? '';

if ($debugMode) {
    echo "Tier Name: {$tierName}\n";
    echo "Tier Price: {$tierPrice}\n";
}

// ===========================================================================
// 3. RECUPERAR EMAIL DEL ALMACENAMIENTO
// ===========================================================================

$dataFile = __DIR__ . '/data/pre-checkout-emails.json';
$email = null;
$nombre = 'Estudiante';
$emailSent = false;
$errorMessage = '';

if (!$tier) {
    // Producto no válido
    error_log("ERROR: product_id inválido - {$product_id}");
    $errorMessage = 'Producto no encontrado';
    if ($debugMode) echo "\n❌ No se pudo mapear el tier\n";
} elseif (file_exists($dataFile)) {
    if ($debugMode) echo "\n<strong>📂 Buscando email almacenado...</strong>\n";
    if ($debugMode) echo "Archivo: {$dataFile}\n";

    $data = json_decode(file_get_contents($dataFile), true);

    if ($debugMode) {
        echo "Total de registros en archivo: " . (isset($data['emails']) ? count($data['emails']) : 0) . "\n";
    }

    if (is_array($data) && isset($data['emails'])) {
        // Filtrar entradas del tier correcto que no se hayan completado
        $entries = array_filter($data['emails'], function($entry) use ($tier) {
            return isset($entry['tier'])
                && $entry['tier'] === $tier
                && (!isset($entry['purchase_completed']) || $entry['purchase_completed'] === false);
        });

        if ($debugMode) {
            echo "Registros del tier '{$tier}' no completados: " . count($entries) . "\n";
        }

        // Ordenar por timestamp descendente (más reciente primero)
        usort($entries, function($a, $b) {
            $timeA = $a['timestamp'] ?? 0;
            $timeB = $b['timestamp'] ?? 0;
            return $timeB - $timeA;
        });

        // Tomar la más reciente
        if (!empty($entries)) {
            $entry = $entries[0];
            $email = $entry['email'] ?? null;
            $nombre = $entry['nombre'] ?? 'Estudiante';

            if ($debugMode) {
                echo "✓ Email encontrado: {$email}\n";
                echo "✓ Nombre: {$nombre}\n";
            }

            error_log("Email recuperado para {$tier}: {$email}");
            @file_put_contents($logFile, date('Y-m-d H:i:s') . " | Email recuperado: {$email} para tier {$tier}\n", FILE_APPEND);

            // ===========================================================================
            // 4. ENVIAR EMAIL DE CONFIRMACIÓN
            // ===========================================================================

            if ($email) {
                if ($debugMode) echo "\n<strong>📧 Preparando envío de email...</strong>\n";

                $templateFile = __DIR__ . "/email-templates/06-confirmacion-compra-{$tier}.html";

                if ($debugMode) {
                    echo "Template: {$templateFile}\n";
                    echo "Template existe: " . (file_exists($templateFile) ? "SÍ" : "NO") . "\n";
                }

                if (file_exists($templateFile)) {
                    $emailBody = file_get_contents($templateFile);
                    if ($debugMode) echo "✓ Template cargado (" . strlen($emailBody) . " bytes)\n";

                    // Reemplazar variables en la plantilla
                    $emailBody = str_replace('{{nombre}}', htmlspecialchars($nombre), $emailBody);
                    $emailBody = str_replace('{{tier_name}}', $tierName, $emailBody);
                    $emailBody = str_replace('{{price}}', $tierPrice, $emailBody);
                    $emailBody = str_replace('{{teachable_url}}', 'https://jorge-alonso-s-school.teachable.com/courses/enrolled', $emailBody);

                    // Configurar PHPMailer
                    $mail = new PHPMailer(true);

                    try {
                        // Configuración SMTP
                        $mail->isSMTP();
                        $mail->Host = SMTP_HOST;
                        $mail->SMTPAuth = true;
                        $mail->Username = SMTP_USERNAME;
                        $mail->Password = SMTP_PASSWORD;
                        $mail->SMTPSecure = (SMTP_ENCRYPTION === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = SMTP_PORT;
                        $mail->CharSet = 'UTF-8';

                        // Remitente y destinatario
                        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                        $mail->addAddress($email, $nombre);

                        // Contenido
                        $mail->isHTML(true);
                        $mail->Subject = "¡Bienvenido al {$tierName}!";
                        $mail->Body = $emailBody;

                        // Enviar
                        if ($debugMode) echo "Enviando email a {$email}...\n";
                        $mail->send();
                        $emailSent = true;

                        if ($debugMode) echo "✅ EMAIL ENVIADO EXITOSAMENTE\n";

                        // Log de éxito
                        $purchaseLogFile = __DIR__ . '/data/purchase-confirmations.log';
                        $logEntry = date('Y-m-d H:i:s') . " | SUCCESS | {$email} | {$tier} | sale_id:{$sale_id}\n";
                        @file_put_contents($purchaseLogFile, $logEntry, FILE_APPEND);

                        error_log("Email enviado exitosamente a {$email} para tier {$tier}");

                    } catch (Exception $e) {
                        // Error al enviar email
                        if ($debugMode) {
                            echo "❌ ERROR AL ENVIAR EMAIL\n";
                            echo "Error: " . $mail->ErrorInfo . "\n";
                            echo "Exception: " . $e->getMessage() . "\n";
                        }

                        error_log("ERROR al enviar email: " . $mail->ErrorInfo);

                        $errorLogFile = __DIR__ . '/data/failed-emails.log';
                        $errorEntry = date('Y-m-d H:i:s') . " | {$email} | {$tier} | sale_id:{$sale_id} | Error: {$mail->ErrorInfo}\n";
                        @file_put_contents($errorLogFile, $errorEntry, FILE_APPEND);
                    }

                    // ===========================================================================
                    // 5. MARCAR COMPRA COMO COMPLETADA
                    // ===========================================================================

                    // Actualizar el archivo JSON para marcar como completado
                    foreach ($data['emails'] as $key => &$item) {
                        if ($item['email'] === $email && $item['tier'] === $tier && !$item['purchase_completed']) {
                            $item['purchase_completed'] = true;
                            $item['sale_id'] = $sale_id;
                            $item['completed_at'] = time();
                            break;
                        }
                    }

                    // Guardar con file locking
                    $fp = fopen($dataFile, 'w');
                    if ($fp) {
                        if (flock($fp, LOCK_EX)) {
                            fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                            flock($fp, LOCK_UN);
                        }
                        fclose($fp);
                    }

                } else {
                    error_log("ERROR: Template no encontrado - {$templateFile}");
                    $errorMessage = 'Template de email no encontrado';
                }
            } else {
                error_log("ERROR: Email no encontrado para tier {$tier}");
                $errorMessage = 'Email no encontrado';
            }
        } else {
            error_log("ERROR: No hay entradas disponibles para tier {$tier}");
            $errorMessage = 'No se encontraron datos de pre-checkout';
        }
    }
} else {
    error_log("ERROR: Archivo de datos no existe - {$dataFile}");
    $errorMessage = 'Archivo de datos no encontrado';
}

// ===========================================================================
// 6. RENDERIZAR PÁGINA HTML
// ===========================================================================

if ($debugMode) {
    echo "\n<strong>🎨 Renderizando página HTML...</strong>\n";
    echo "Email encontrado: " . ($email ? "SÍ ({$email})" : "NO") . "\n";
    echo "Tier identificado: " . ($tier ? "SÍ ({$tier})" : "NO") . "\n";
    echo "Email enviado: " . ($emailSent ? "SÍ ✅" : "NO ❌") . "\n";
    echo "</pre>\n";
    echo "<hr style='border:2px solid #36E3A0; margin:20px 0;'>\n";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8" />
	<title>¡Compra Completada! - Futbolista Inversor</title>
	<meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport" />
	<link rel="icon" type="image/x-icon" href="assets/img/LOGO/favicon.ico?v=1" />
	<link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" crossorigin="anonymous" />
	<link href="assets/css/one-page-parallax/vendor.min.css?v=3" rel="stylesheet" />
	<link href="assets/css/one-page-parallax/app.min.css?v=51" rel="stylesheet" />
	<style>
		body {
			margin: 0;
			min-height: 100vh;
			display: flex;
			flex-direction: column;
			background: linear-gradient(135deg, #1b2c4d 0%, #2d353c 100%);
			font-family: 'Open Sans', sans-serif;
		}

		.confirmation-wrapper {
			flex: 1;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 40px 20px;
		}

		.confirmation-card {
			max-width: 650px;
			width: 100%;
			background: #ffffff;
			border-radius: 16px;
			overflow: hidden;
			box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
		}

		.card-header {
			background: linear-gradient(135deg, #1b2c4d 0%, #2d353c 100%);
			padding: 35px 40px 30px;
			text-align: center;
			border-bottom: 3px solid rgb(54, 227, 160);
		}

		.card-header img {
			max-width: 180px;
			height: auto;
			margin-bottom: 25px;
		}

		.icon-circle {
			width: 90px;
			height: 90px;
			background: rgba(54, 227, 160, 0.15);
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			margin: 0 auto;
		}

		.icon-circle i {
			font-size: 42px;
			color: rgb(54, 227, 160);
		}

		.card-body {
			padding: 45px 40px;
			text-align: center;
		}

		.card-body h2 {
			color: #1b2c4d;
			font-size: 2rem;
			font-weight: 700;
			margin: 0 0 15px;
			line-height: 1.3;
		}

		.card-body h2 span {
			color: rgb(54, 227, 160);
		}

		.card-body p {
			color: #555;
			font-size: 1.05rem;
			line-height: 1.7;
			margin: 0 0 30px;
		}

		.info-box {
			background: #f8f9fa;
			border-left: 4px solid rgb(54, 227, 160);
			border-radius: 0 8px 8px 0;
			padding: 22px 25px;
			text-align: left;
			margin: 0 0 35px;
		}

		.info-box p {
			margin: 0 0 10px;
			font-size: 0.95rem;
			color: #444;
		}

		.info-box p:last-child {
			margin-bottom: 0;
		}

		.info-box strong {
			color: #1b2c4d;
		}

		.success-badge {
			display: inline-block;
			background: rgba(54, 227, 160, 0.1);
			color: rgb(54, 227, 160);
			padding: 8px 20px;
			border-radius: 20px;
			font-size: 0.85rem;
			font-weight: 600;
			margin-bottom: 15px;
		}

		.btn-access {
			display: inline-block;
			background: linear-gradient(135deg, rgb(54, 227, 160) 0%, #2dd492 100%);
			color: #1b2c4d;
			text-decoration: none;
			padding: 16px 40px;
			border-radius: 50px;
			font-size: 1.1rem;
			font-weight: 700;
			box-shadow: 0 4px 15px rgba(54, 227, 160, 0.3);
			transition: transform 0.2s, box-shadow 0.2s;
			margin-bottom: 20px;
		}

		.btn-access:hover {
			transform: translateY(-2px);
			box-shadow: 0 6px 20px rgba(54, 227, 160, 0.4);
			color: #1b2c4d;
			text-decoration: none;
		}

		.btn-secondary {
			display: inline-block;
			color: #666;
			text-decoration: none;
			padding: 10px 20px;
			font-size: 0.95rem;
			transition: color 0.2s;
		}

		.btn-secondary:hover {
			color: rgb(54, 227, 160);
		}

		.card-footer {
			padding: 25px 40px 30px;
			text-align: center;
			border-top: 1px solid #eee;
		}

		.card-footer p {
			color: #999;
			font-size: 0.85rem;
			margin: 0;
			line-height: 1.6;
		}

		.card-footer a {
			color: rgb(54, 227, 160);
			text-decoration: none;
		}

		.card-footer a:hover {
			text-decoration: underline;
		}

		.error-notice {
			background: #fff3cd;
			border: 1px solid #ffc107;
			border-radius: 8px;
			padding: 15px;
			margin-bottom: 20px;
			font-size: 0.9rem;
			color: #856404;
		}
	</style>
</head>
<body>

	<div class="confirmation-wrapper">
		<div class="confirmation-card">

			<!-- Header -->
			<div class="card-header">
				<img src="assets/img/LOGO/logo_1.png" alt="Futbolista Inversor">
				<div class="icon-circle">
					<i class="fas fa-check-circle"></i>
				</div>
			</div>

			<!-- Body -->
			<div class="card-body">
				<?php if ($tier && $email): ?>
					<!-- Compra exitosa con email enviado -->
					<span class="success-badge">✓ Compra completada</span>
					<h2>¡Bienvenido al <span><?php echo htmlspecialchars($tierName); ?></span>!</h2>
					<p>
						Hola <strong><?php echo htmlspecialchars($nombre); ?></strong>,<br>
						Tu inscripción al <strong><?php echo htmlspecialchars($tierName); ?></strong> está confirmada.
					</p>

					<div class="info-box">
						<p><strong>📧 Email de confirmación enviado a:</strong></p>
						<p style="font-size: 1rem; color: rgb(54, 227, 160); font-weight: 600;"><?php echo htmlspecialchars($email); ?></p>
						<p style="margin-top: 15px;"><strong>💰 Inversión:</strong> <?php echo htmlspecialchars($tierPrice); ?></p>
						<?php if ($sale_id): ?>
							<p><strong>🔢 ID de venta:</strong> <?php echo htmlspecialchars($sale_id); ?></p>
						<?php endif; ?>
					</div>

					<?php if ($emailSent): ?>
						<p style="font-size: 0.95rem; color: #666; margin-bottom: 30px;">
							Revisa tu bandeja de entrada (y spam) para acceder a toda la información de tu curso.
						</p>
					<?php else: ?>
						<div class="error-notice">
							⚠️ Hubo un problema al enviar el email de confirmación. No te preocupes, tu compra está registrada. Contacta a soporte: info@futbolistainversor.com
						</div>
					<?php endif; ?>

					<a href="https://jorge-alonso-s-school.teachable.com/courses/enrolled" class="btn-access">
						🎯 Acceder a Mi Área de Miembro
					</a>

					<div style="margin-top: 20px;">
						<a href="https://futbolistainversor.com" class="btn-secondary">
							← Volver a la página principal
						</a>
					</div>

				<?php elseif ($tier && !$email): ?>
					<!-- Compra exitosa pero sin email encontrado -->
					<span class="success-badge">✓ Compra completada</span>
					<h2>¡Bienvenido al <span><?php echo htmlspecialchars($tierName); ?></span>!</h2>
					<p>
						Tu compra se ha procesado correctamente.
					</p>

					<div class="error-notice">
						⚠️ No pudimos recuperar tu email de nuestros registros. Recibirás la confirmación directamente de Teachable. Si tienes dudas, contacta: info@futbolistainversor.com
					</div>

					<a href="https://jorge-alonso-s-school.teachable.com/courses/enrolled" class="btn-access">
						🎯 Acceder a Mi Área de Miembro
					</a>

					<div style="margin-top: 20px;">
						<a href="https://futbolistainversor.com" class="btn-secondary">
							← Volver a la página principal
						</a>
					</div>

				<?php else: ?>
					<!-- Error: producto no identificado -->
					<h2>¡Gracias por tu <span>compra</span>!</h2>
					<p>
						Tu compra se ha procesado correctamente.
					</p>

					<div class="error-notice">
						⚠️ Hubo un problema al identificar el producto. Recibirás la confirmación de Teachable. Si tienes dudas, contacta: info@futbolistainversor.com
					</div>

					<a href="https://jorge-alonso-s-school.teachable.com/courses/enrolled" class="btn-access">
						🎯 Acceder a Mis Cursos
					</a>

					<div style="margin-top: 20px;">
						<a href="https://futbolistainversor.com" class="btn-secondary">
							← Volver a la página principal
						</a>
					</div>
				<?php endif; ?>
			</div>

			<!-- Footer -->
			<div class="card-footer">
				<p>
					¿Necesitas ayuda? Contáctanos en<br>
					<a href="mailto:info@futbolistainversor.com">info@futbolistainversor.com</a>
				</p>
			</div>

		</div>
	</div>

	<!-- Fallback: Intentar recuperar de localStorage si no se encontró en servidor -->
	<?php if (!$email && $tier): ?>
	<script>
		(function() {
			try {
				const storedEmail = localStorage.getItem('preCheckoutEmail');
				const storedNombre = localStorage.getItem('preCheckoutNombre');
				const storedTier = localStorage.getItem('preCheckoutTier');

				if (storedEmail && storedTier === '<?php echo $tier; ?>') {
					// Intentar enviar por AJAX al servidor como fallback
					fetch('api/send-purchase-email-fallback.php', {
						method: 'POST',
						headers: {'Content-Type': 'application/json'},
						body: JSON.stringify({
							email: storedEmail,
							nombre: storedNombre || 'Estudiante',
							tier: storedTier,
							sale_id: '<?php echo $sale_id; ?>',
							product_id: '<?php echo $product_id; ?>'
						})
					}).then(response => response.json())
					  .then(data => {
						if (data.success) {
							console.log('Email de fallback enviado exitosamente');
						}
					  })
					  .catch(err => console.error('Error en fallback:', err));
				}
			} catch (e) {
				console.error('Error en localStorage fallback:', e);
			}
		})();
	</script>
	<?php endif; ?>

</body>
</html>
