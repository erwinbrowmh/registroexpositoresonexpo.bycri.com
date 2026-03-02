<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/lib/Security.php';

$message = '';
$error = '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    // 3 attempts per 10 minutes for password reset
    if (!Security::checkRateLimit('forgot_password_' . $ip_address, 3, 600)) {
        $error = "Demasiadas solicitudes. Por favor espere 10 minutos.";
        Security::logSecurityEvent('Forgot password rate limit exceeded', ['ip' => $ip_address]);
    } elseif (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Sesión inválida o expirada. Por favor recargue la página.";
    } else {
        $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);
        
        if ($correo) {
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT id, nombre, apellido FROM expositores WHERE correo = ?");
                $stmt->execute([$correo]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // Generate 6 digit code
                    $code = rand(100000, 999999);
                    $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    
                    $update = $db->prepare("UPDATE expositores SET reset_code = ?, reset_expires = ? WHERE id = ?");
                    $update->execute([$code, $expires, $user['id']]);
                    
                    // Send email
                    $subject = "Código de recuperación de contraseña - ONEXPO 2026";
                    $body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                            <h2 style='color: #002B5C;'>Recuperación de Contraseña</h2>
                            <p>Hola <strong>{$user['nombre']} {$user['apellido']}</strong>,</p>
                            <p>Has solicitado restablecer tu contraseña para el Portal de Expositores ONEXPO 2026.</p>
                            <p>Tu código de verificación es:</p>
                            <div style='background-color: #f8f9fa; padding: 15px; text-align: center; border-radius: 5px; margin: 20px 0;'>
                                <h1 style='color: #002B5C; letter-spacing: 5px; margin: 0;'>{$code}</h1>
                            </div>
                            <p>Este código expira en 15 minutos.</p>
                            <p>Si no solicitaste este cambio, por favor ignora este correo.</p>
                        </div>
                    ";
                    
                    if (send_email($correo, $subject, $body)) {
                        $_SESSION['reset_email'] = $correo;
                        Security::logSecurityEvent('Password reset requested', ['email' => $correo, 'ip' => $ip_address]);
                        header("Location: recover.php");
                        exit;
                    } else {
                        $error = "Hubo un error al enviar el correo. Por favor intenta nuevamente.";
                    }
                } else {
                    // For better UX in this specific tool, we'll be explicit
                    // Note: In strict security, we shouldn't reveal if email exists, but client requested UX focus.
                    $error = "El correo ingresado no se encuentra registrado en el sistema.";
                    Security::logSecurityEvent('Password reset requested for non-existent email', ['email' => $correo, 'ip' => $ip_address]);
                }
            } catch (Exception $e) {
                $error = "Error del sistema: " . $e->getMessage();
                error_log($e->getMessage());
            }
        } else {
            $error = "Por favor ingresa un correo válido.";
        }
    }
}
$csrf_token = Security::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - ONEXPO 2026</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #002B5C;
            --secondary-color: #6c757d;
            --light-bg: #f8f9fa;
            --accent-color: #0056b3;
        }
        body {
            background-color: var(--light-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            max-width: 450px;
            width: 100%;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            background: white;
            border-top: 5px solid var(--primary-color);
        }
        .logo-img {
            max-width: 250px;
            height: auto;
            margin-bottom: 1.5rem;
            background-color: #002B5C;
            padding: 10px;
            border-radius: 8px;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.8rem;
            font-weight: 600;
        }
        .btn-primary:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="text-center mb-4">
            <img src="assets/img/ONEXPO+LOGO+EVENTO-02.webp" alt="ONEXPO 2026" class="logo-img">
            <h3>Recuperar Contraseña</h3>
            <p class="text-muted">Ingresa tu correo para restablecer tu acceso</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($message) ?>
            </div>
            <div class="d-grid mt-3">
                <a href="index.php" class="btn btn-outline-secondary">Volver al Login</a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <div class="mb-3">
                    <label for="correo" class="form-label">Correo Electrónico</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fas fa-envelope text-muted"></i></span>
                        <input type="email" class="form-control" id="correo" name="correo" required placeholder="nombre@empresa.com">
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        Enviar Instrucciones
                    </button>
                    <a href="index.php" class="btn btn-link text-decoration-none text-muted">
                        Cancelar y volver
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
