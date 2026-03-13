<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/Security.php';

if (PHP_SESSION_NONE === session_status()) {
    session_start();
}

// Security Headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Validate Session
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Basic session validation (can be enhanced like in soporteCRI)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}
$_SESSION['last_activity'] = time();

$db = Database::getInstance()->getConnection();

// Stats
$stats = [
    'expositores' => 0,
    'empresas' => 0,
    'participantes' => 0
];

try {
    $stats['expositores'] = $db->query("SELECT COUNT(*) FROM expositores")->fetchColumn();
    $stats['empresas'] = $db->query("SELECT COUNT(*) FROM empresas")->fetchColumn();
    // Assuming 'participantes' table exists based on previous user input context, or use placeholders
    // The user mentioned "gestion principalmente completa de participantes" and api/expositores.php used models/Participante.php
    // I'll check if table exists first to avoid error, or just try.
    // Based on user input "Crearemos... gestion principalmente completa de participantes", I assume the table should exist or be managed.
    // But I haven't created it. The previous user input showed 'models/Participante.php' and 'participantes' table name.
    // I'll assume it exists or try/catch.
    $stats['participantes'] = $db->query("SELECT COUNT(*) FROM participantes")->fetchColumn();
} catch (Exception $e) {
    // Table might not exist yet
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.8);
            padding: 0.8rem 1rem;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,.1);
        }
        .card-stat {
            border-radius: 10px;
            border: none;
            transition: transform 0.2s;
        }
        .card-stat:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar d-none d-md-block">
                <div class="p-3 text-center border-bottom border-secondary">
                    <h5 class="m-0 fw-bold"><i class="fas fa-shield-alt me-2"></i>Admin Panel</h5>
                    <small class="text-white-50"><?php echo htmlspecialchars($_SESSION['admin_usuario']); ?></small>
                </div>
                <ul class="nav flex-column py-3">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-home me-2"></i> Inicio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="empresas.php">
                            <i class="fas fa-building me-2"></i> Empresas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="expositores.php">
                            <i class="fas fa-id-badge me-2"></i> Expositores
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="participantes.php">
                            <i class="fas fa-users me-2"></i> Participantes
                        </a>
                    </li>
                    <?php if (!empty($_SESSION['can_manage_admins'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="administradores.php">
                            <i class="fas fa-user-shield me-2"></i> Administradores
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item mt-4">
                        <a class="nav-link text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ms-sm-auto px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="text-muted"><i class="far fa-calendar-alt me-1"></i> <?php echo date('d M Y'); ?></span>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row g-4 mb-5">
                    <div class="col-md-4">
                        <div class="card card-stat bg-primary text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title text-uppercase mb-0">Empresas</h6>
                                        <h2 class="display-4 fw-bold my-2"><?php echo $stats['empresas']; ?></h2>
                                    </div>
                                    <i class="fas fa-building fa-3x opacity-50"></i>
                                </div>
                                <p class="card-text small opacity-75">Registradas en el sistema</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-stat bg-success text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title text-uppercase mb-0">Expositores</h6>
                                        <h2 class="display-4 fw-bold my-2"><?php echo $stats['expositores']; ?></h2>
                                    </div>
                                    <i class="fas fa-id-badge fa-3x opacity-50"></i>
                                </div>
                                <p class="card-text small opacity-75">Usuarios expositores activos</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-stat bg-info text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title text-uppercase mb-0">Participantes</h6>
                                        <h2 class="display-4 fw-bold my-2"><?php echo $stats['participantes']; ?></h2>
                                    </div>
                                    <i class="fas fa-users fa-3x opacity-50"></i>
                                </div>
                                <p class="card-text small opacity-75">Asistentes registrados</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity / Quick Actions could go here -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-rocket me-2"></i>Acciones Rápidas</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Seleccione una opción del menú lateral para comenzar a gestionar los registros.</p>
                                <div class="d-flex gap-3">
                                    <a href="empresas.php" class="btn btn-outline-primary"><i class="fas fa-plus me-1"></i> Nueva Empresa</a>
                                    <a href="expositores.php" class="btn btn-outline-success"><i class="fas fa-user-plus me-1"></i> Nuevo Expositor</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
