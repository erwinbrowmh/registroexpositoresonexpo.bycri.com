<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/../lib/Security.php';

session_start();

// Check for auto-login
if (!isset($_SESSION['soporte_logged_in']) || $_SESSION['soporte_logged_in'] !== true) {
    if (isset($_COOKIE['soporte_remember_me'])) {
        $parts = explode(':', $_COOKIE['soporte_remember_me']);
        if (count($parts) === 2) {
            list($selector, $validator) = $parts;
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT * FROM soporte_tokens WHERE selector = ? AND expiry > NOW()");
                $stmt->execute([$selector]);
                $token = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($token && hash_equals($token['hashed_validator'], hash('sha256', $validator))) {
                    session_regenerate_id(true);
                    $_SESSION['soporte_logged_in'] = true;
                    $_SESSION['last_activity'] = time();
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    header('Location: dashboard.php');
                    exit;
                }
            } catch (Exception $e) {
                // Ignore errors
            }
        }
    }
}

// Security Headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

if (isset($_SESSION['soporte_logged_in']) && $_SESSION['soporte_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Error de seguridad (CSRF). Por favor recargue la página.';
    } else {
        // Rate Limiting
        $ip = $_SERVER['REMOTE_ADDR'];
        if (!Security::checkRateLimit('soporte_login_' . $ip, 5, 300)) {
            $error = 'Demasiados intentos. Por favor espere 5 minutos.';
        } else {
            $code = $_POST['code'] ?? '';
            if ($code === 'cri2017_') {
                session_regenerate_id(true); // Prevent session fixation
                $_SESSION['soporte_logged_in'] = true;
                $_SESSION['last_activity'] = time();
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                // Remember Me Logic
                if (isset($_POST['remember_me'])) {
                    $selector = bin2hex(random_bytes(12));
                    $validator = bin2hex(random_bytes(32));
                    $hashed_validator = hash('sha256', $validator);
                    $expiry = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 days

                    try {
                        $db = Database::getInstance()->getConnection();
                        $stmt_token = $db->prepare("INSERT INTO soporte_tokens (selector, hashed_validator, expiry) VALUES (?, ?, ?)");
                        $stmt_token->execute([$selector, $hashed_validator, $expiry]);

                        setcookie('soporte_remember_me', $selector . ':' . $validator, time() + (86400 * 30), '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
                    } catch (Exception $e) {
                        // Ignore DB errors
                    }
                }

                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Código incorrecto';
            }
        }
    }
}

$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Soporte CRI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .brand-logo {
            width: 80px;
            height: 80px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 4px 6px rgba(13, 110, 253, 0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card login-card border-0">
                    <div class="text-center mb-4">
                        <div class="brand-logo">
                            <i class="fas fa-headset"></i>
                        </div>
                        <h4 class="fw-bold text-primary">Soporte CRI</h4>
                        <p class="text-muted small">Panel de Administración de Expositores</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="mb-4">
                            <label for="code" class="form-label text-muted fw-semibold small">CÓDIGO DE ACCESO</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                                <input type="password" class="form-control border-start-0 ps-0 bg-light" id="code" name="code" required autofocus autocomplete="off" placeholder="Ingrese su código">
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                            <label class="form-check-label text-muted small" for="remember_me">Recordarme</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                            <i class="fas fa-sign-in-alt me-2"></i> Ingresar al Panel
                        </button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <small class="text-muted">&copy; <?php echo date('Y'); ?> ByCRI. Todos los derechos reservados.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
