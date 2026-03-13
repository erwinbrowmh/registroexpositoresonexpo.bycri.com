<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/Security.php';

session_start();

// Security Headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
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
        if (!Security::checkRateLimit('admin_login_' . $ip, 5, 300)) {
            $error = 'Demasiados intentos. Por favor espere 5 minutos.';
        } else {
            $usuario = $_POST['usuario'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if ($usuario && $password) {
                try {
                    $db = Database::getInstance()->getConnection();
                    $stmt = $db->prepare("SELECT * FROM admexpositor WHERE usuario = ?");
                    $stmt->execute([$usuario]);
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($admin && password_verify($password, $admin['password'])) {
                        session_regenerate_id(true); // Prevent session fixation
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_usuario'] = $admin['usuario'];
                        $_SESSION['can_manage_admins'] = (bool)$admin['can_manage_admins'];
                        $_SESSION['last_activity'] = time();
                        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        
                        header('Location: dashboard.php');
                        exit;
                    } else {
                        $error = 'Usuario o contraseña incorrectos.';
                    }
                } catch (Exception $e) {
                    $error = 'Error del sistema.';
                    error_log($e->getMessage());
                }
            } else {
                $error = 'Por favor complete todos los campos.';
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
    <title>Login - Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
        .brand-logo {
            width: 70px;
            height: 70px;
            background: #0d6efd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            margin: 0 auto 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card login-card border-0 bg-white">
                    <div class="text-center mb-4">
                        <div class="brand-logo">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h4 class="fw-bold text-dark">Administración</h4>
                        <p class="text-muted small">Panel de Control General</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="mb-3">
                            <label for="usuario" class="form-label text-muted fw-semibold small">USUARIO</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-user text-muted"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0 bg-light" id="usuario" name="usuario" required autofocus autocomplete="username" placeholder="Usuario admin">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label text-muted fw-semibold small">CONTRASEÑA</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                                <input type="password" class="form-control border-start-0 ps-0 bg-light" id="password" name="password" required autocomplete="current-password" placeholder="Contraseña">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                            <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
                        </button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <small class="text-muted">&copy; <?php echo date('Y'); ?> ByCRI</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
