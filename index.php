<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/lib/Security.php';

// Check for auto-login
if (check_remember_me()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Check Rate Limit (e.g., 5 attempts per 5 minutes)
    $ip_address = $_SERVER['REMOTE_ADDR'];
    if (!Security::checkRateLimit('login_' . $ip_address, 5, 300)) {
        $error = "Demasiados intentos de inicio de sesión. Por favor espere 5 minutos.";
        Security::logSecurityEvent('Login rate limit exceeded', ['ip' => $ip_address]);
    } elseif (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Sesión inválida o expirada. Por favor recargue la página.";
        Security::logSecurityEvent('Invalid CSRF token', ['ip' => $ip_address]);
    } else {
        $login_input = trim($_POST['correo'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($login_input && $password) {
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT id, nombre, apellido, acceso, id_empresa FROM expositores WHERE correo = ? OR usuario = ? LIMIT 1");
                $stmt->execute([$login_input, $login_input]);
                $expositor = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($expositor && password_verify($password, $expositor['acceso'])) {
                    // Login successful
                    // Regenerate session ID to prevent fixation
                    session_regenerate_id(true);
                    
                    // Reset rate limit on success
                    Security::resetRateLimit($ip_address);

                    $_SESSION['expositor_id'] = $expositor['id'];
                    $_SESSION['expositor_nombre'] = $expositor['nombre'] . ' ' . $expositor['apellido'];
                    $_SESSION['id_empresa'] = $expositor['id_empresa'];
                    
                    // Set session activity timestamps for Security::validateSession()
                    $_SESSION['last_activity'] = time();
                    $_SESSION['created'] = time();
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

                    Security::logSecurityEvent('Login successful', ['user_id' => $expositor['id'], 'ip' => $ip_address]);

                    // Remember Me Logic
                    if (isset($_POST['remember_me'])) {
                        $selector = bin2hex(random_bytes(12));
                        $validator = bin2hex(random_bytes(32));
                        $hashed_validator = hash('sha256', $validator);
                        $expiry = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 days

                        $stmt_token = $db->prepare("INSERT INTO user_tokens (user_id, selector, hashed_validator, expiry) VALUES (?, ?, ?, ?)");
                        $stmt_token->execute([$expositor['id'], $selector, $hashed_validator, $expiry]);

                        setcookie('remember_me', $selector . ':' . $validator, time() + (86400 * 30), '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
                    }

                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = "Credenciales inválidas.";
                    Security::logSecurityEvent('Login failed', ['input' => $login_input, 'ip' => $ip_address]);
                }
            } catch (Exception $e) {
                $error = "Error de conexión.";
                error_log($e->getMessage());
            }
        } else {
            $error = "Por favor ingrese correo y contraseña.";
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
    <title>Login Expositores - ONEXPO 2026</title>
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
        .login-card {
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
            margin-bottom: 2rem;
            background-color: #002B5C;
            padding: 10px;
            border-radius: 8px;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.8rem;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }
        .btn-primary:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            transform: translateY(-1px);
        }
        .form-control {
            padding: 0.8rem 1rem;
            border-radius: 8px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        .form-control:focus {
            background-color: #fff;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 43, 92, 0.15);
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h3 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .login-header p {
            color: var(--secondary-color);
        }
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }
        .login-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }
        .login-footer a:hover {
            color: var(--accent-color);
            text-decoration: underline;
        }
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
            color: var(--secondary-color);
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #dee2e6;
        }
        .divider span {
            padding: 0 10px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <img src="assets/img/ONEXPO+LOGO+EVENTO-02.webp" alt="ONEXPO 2026" class="logo-img">
            <h3>Portal de Expositores</h3>
            <p>Inicia sesión para gestionar tu stand</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="mb-3">
                <label for="correo" class="form-label">Usuario o Correo</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-user text-muted"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" id="correo" name="correo" required placeholder="Usuario o correo">
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                    <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" required placeholder="••••••••">
                </div>
                <div class="text-end mt-1">
                    <a href="forgot_password.php" class="text-decoration-none small text-muted">¿Olvidaste tu contraseña?</a>
                </div>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                <label class="form-check-label text-muted" for="remember_me">Recordarme</label>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
            </button>
        </form>
        
        <div class="login-footer mt-4">
            <p class="text-muted mb-0">&copy; 2026 ONEXPO. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>