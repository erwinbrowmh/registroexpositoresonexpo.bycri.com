<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/lib/Security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if no email in session (must come from forgot_password.php)
if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit;
}

$email = $_SESSION['reset_email'];
$step = isset($_SESSION['reset_step']) ? $_SESSION['reset_step'] : 1;
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Sesión inválida o expirada. Por favor recargue la página.";
    } else {
        $action = $_POST['action'] ?? '';
        $ip_address = $_SERVER['REMOTE_ADDR'];

        if ($action === 'verify_code') {
        // Rate limit verification attempts: 5 attempts per 15 minutes
        if (!Security::checkRateLimit('verify_code_' . $ip_address, 5, 900)) {
            $error = "Demasiados intentos fallidos. Por favor espere 15 minutos.";
            Security::logSecurityEvent('Code verification rate limit exceeded', ['email' => $email, 'ip' => $ip_address]);
        } else {
            $code = Security::sanitizeInput($_POST['code'] ?? '');
            
            if (empty($code)) {
                $error = "Por favor ingresa el código de verificación.";
            } else {
                try {
                    $db = Database::getInstance()->getConnection();
                    $stmt = $db->prepare("SELECT id, reset_code, reset_expires FROM expositores WHERE correo = ?");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user && $user['reset_code'] === $code) {
                        if (strtotime($user['reset_expires']) > time()) {
                            // Reset rate limit on success
                            Security::resetRateLimit('verify_code_' . $ip_address);
                            
                            $_SESSION['reset_step'] = 2;
                            $step = 2;
                            // Store user ID for next step security
                            $_SESSION['reset_user_id'] = $user['id'];
                            
                            Security::logSecurityEvent('Code verified successfully', ['email' => $email, 'ip' => $ip_address]);
                        } else {
                            $error = "El código ha expirado. Por favor solicita uno nuevo.";
                        }
                    } else {
                        $error = "El código de verificación es incorrecto.";
                        Security::logSecurityEvent('Invalid code verification attempt', ['email' => $email, 'code' => $code, 'ip' => $ip_address]);
                    }
                } catch (Exception $e) {
                    $error = "Error del sistema: " . $e->getMessage();
                    error_log($e->getMessage());
                }
            }
        }
    } elseif ($action === 'reset_password') {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $userId = $_SESSION['reset_user_id'] ?? null;

        if (!$userId) {
            $error = "Sesión inválida. Por favor reinicia el proceso.";
            // Reset to step 1 if session is lost
            $_SESSION['reset_step'] = 1;
            $step = 1;
        } elseif (strlen($password) < 6) {
            $error = "La contraseña debe tener al menos 6 caracteres.";
        } elseif ($password !== $confirm_password) {
            $error = "Las contraseñas no coinciden.";
        } else {
            try {
                $db = Database::getInstance()->getConnection();
                
                // Get user info for email
                $stmt = $db->prepare("SELECT nombre, apellido FROM expositores WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // Update password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update = $db->prepare("UPDATE expositores SET acceso = ?, reset_code = NULL, reset_expires = NULL WHERE id = ?");
                
                if ($update->execute([$hashed_password, $userId])) {
                    // Send confirmation email
                    $subject = "Contraseña Actualizada - ONEXPO 2026";
                    $body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                            <h2 style='color: #002B5C;'>Contraseña Actualizada</h2>
                            <p>Hola <strong>{$user['nombre']} {$user['apellido']}</strong>,</p>
                            <p>Tu contraseña ha sido actualizada exitosamente.</p>
                            <p>Ya puedes iniciar sesión con tu nueva contraseña.</p>
                            <br>
                            <p style='text-align: center;'>
                                <a href='" . SITE_URL . "/index.php' style='background-color: #002B5C; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Iniciar Sesión</a>
                            </p>
                        </div>
                    ";
                    send_email($email, $subject, $body);

                    $message = "Contraseña actualizada correctamente.";
                    // Clear session
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['reset_step']);
                    unset($_SESSION['reset_user_id']);
                    $step = 3; // Success state
                } else {
                    $error = "Error al actualizar la contraseña.";
                }
            } catch (Exception $e) {
                $error = "Error del sistema: " . $e->getMessage();
            }
        }
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
    <title>Restablecer Contraseña - ONEXPO 2026</title>
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .recover-card {
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
            padding: 0.7rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            transform: translateY(-1px);
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 43, 92, 0.25);
        }
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .back-link a {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
        }
        .back-link a:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin: 0 10px;
            position: relative;
        }
        .step.active {
            background-color: var(--primary-color);
            color: white;
        }
        .step.completed {
            background-color: #198754;
            color: white;
        }
        .step::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 2px;
            background-color: #e9ecef;
            right: -20px;
            top: 50%;
            transform: translateY(-50%);
            z-index: -1;
        }
        .step:last-child::after {
            display: none;
        }
    </style>
</head>
<body>
    <div class="card recover-card">
        <div class="text-center">
            <img src="assets/img/ONEXPO+LOGO+EVENTO-02.webp" alt="ONEXPO 2026" class="logo-img">
            <h3>Restablecer Contraseña</h3>
        </div>

        <?php if ($step < 3): ?>
        <div class="step-indicator">
            <div class="step completed"><i class="fas fa-check"></i></div> <!-- Email sent -->
            <div class="step <?= $step >= 1 ? ($step > 1 ? 'completed' : 'active') : '' ?>">
                <?= $step > 1 ? '<i class="fas fa-check"></i>' : '2' ?>
            </div>
            <div class="step <?= $step >= 2 ? ($step > 2 ? 'completed' : 'active') : '' ?>">3</div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($step === 3): ?>
            <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <div><?= htmlspecialchars($message) ?></div>
            </div>
            <div class="d-grid gap-2 mt-3">
                <a href="index.php" class="btn btn-primary">Iniciar Sesión</a>
            </div>

        <?php elseif ($step === 1): ?>
            <!-- Step 1: Verify Code -->
            <p class="text-muted text-center mb-4">Ingresa el código de 6 dígitos enviado a <strong><?= htmlspecialchars($email) ?></strong></p>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="verify_code">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <div class="mb-3">
                    <label for="code" class="form-label text-muted fw-bold">Código de Verificación</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-key text-muted"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" id="code" name="code" placeholder="123456" maxlength="6" required autofocus>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-3">
                    Verificar Código <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </form>
            
            <div class="back-link">
                <a href="forgot_password.php">¿No recibiste el código? Reenviar</a>
            </div>

        <?php elseif ($step === 2): ?>
            <!-- Step 2: Reset Password -->
            <p class="text-muted text-center mb-4">Crea una nueva contraseña segura.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="mb-3">
                    <label for="password" class="form-label text-muted fw-bold">Nueva Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                        <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="Mínimo 6 caracteres" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="form-label text-muted fw-bold">Confirmar Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                        <input type="password" class="form-control border-start-0 ps-0" id="confirm_password" name="confirm_password" placeholder="Repite la contraseña" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="fas fa-save me-2"></i>Cambiar Contraseña
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>