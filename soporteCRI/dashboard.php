<?php
require_once __DIR__ . '/config/db.php';
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
if (!isset($_SESSION['soporte_logged_in']) || $_SESSION['soporte_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Validate Session Security (Timeout, User Agent)
if (!Security::validateSession()) {
    header('Location: logout.php');
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    $stmt = $db->query("
        SELECT 
            e.id, 
            CONCAT(e.nombre, ' ', e.apellido) as nombre_completo, 
            COALESCE(em.nombre_empresa, 'Sin Empresa') as empresa, 
            e.stand, 
            e.created_at,
            e.logo_ruta
        FROM expositores e 
        LEFT JOIN empresas em ON e.id_empresa = em.id 
        ORDER BY em.nombre_empresa ASC, e.nombre ASC
    ");
    $expositores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error al cargar expositores: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Soporte CRI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4 sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="fas fa-headset me-2"></i> Soporte CRI
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item">
                        <span class="nav-link text-white-50 small me-3">
                            <i class="far fa-clock me-1"></i> <?php echo date('d M Y'); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="btn btn-outline-light btn-sm px-3 rounded-pill">
                            <i class="fas fa-sign-out-alt me-1"></i> Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        <div class="row mb-4 align-items-center page-header border-0">
            <div class="col-md-6">
                <h2 class="fw-bold text-dark m-0">Panel de Control</h2>
                <p class="text-muted m-0">
                    <i class="fas fa-info-circle me-1"></i>
                    Gestión centralizada de expositores, perfiles y descargas de recursos.
                </p>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <div class="d-inline-flex bg-white p-2 rounded shadow-sm align-items-center" data-bs-toggle="tooltip" title="Número total de expositores registrados en el sistema">
                    <span class="text-muted small me-2">Total Expositores:</span>
                    <span class="badge bg-primary rounded-pill"><?php echo count($expositores); ?></span>
                </div>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>

        <div class="card shadow border-0">
            <div class="card-header bg-white border-bottom-0 py-3">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-list me-2"></i>Listado de Expositores</h5>
                        <small class="text-muted">Utilice el campo de búsqueda para filtrar por nombre, empresa o stand.</small>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="expositoresTable" class="table table-hover align-middle mb-0" style="width:100%">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4" width="80" title="Logotipo de la empresa">Logo <i class="fas fa-question-circle text-muted small opacity-50 ms-1" style="font-size: 0.7em;"></i></th>
                                <th width="60" title="Identificador único del expositor">ID</th>
                                <th>Expositor</th>
                                <th>Empresa</th>
                                <th title="Número de stand asignado">Stand</th>
                                <th title="Fecha de registro en la plataforma">Registro</th>
                                <th class="text-end pe-4" width="220">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expositores as $exp): ?>
                                <tr>
                                    <td class="ps-4">
                                        <?php if ($exp['logo_ruta']): ?>
                                            <img src="<?php echo htmlspecialchars($exp['logo_ruta']); ?>" class="logo-thumbnail shadow-sm" alt="Logo de <?php echo htmlspecialchars($exp['empresa']); ?>">
                                        <?php else: ?>
                                            <div class="logo-thumbnail d-flex align-items-center justify-content-center bg-light text-muted small" title="Sin logotipo">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-light text-dark border">#<?php echo htmlspecialchars($exp['id']); ?></span></td>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($exp['nombre_completo']); ?></td>
                                    <td class="text-secondary">
                                        <i class="far fa-building me-1 opacity-50"></i>
                                        <?php echo htmlspecialchars($exp['empresa']); ?>
                                    </td>
                                    <td>
                                        <?php if ($exp['stand']): ?>
                                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                                                <?php echo htmlspecialchars($exp['stand']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">Sin asignar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small">
                                        <i class="far fa-calendar-alt me-1"></i>
                                        <?php echo date('d/m/Y', strtotime($exp['created_at'])); ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <a href="detail.php?id=<?php echo $exp['id']; ?>" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center" data-bs-toggle="tooltip" title="Ver perfil completo y descargar archivos">
                                                <i class="fas fa-eye me-1"></i> Ver Detalle
                                            </a>
                                            <a href="download_pdf.php?id=<?php echo $exp['id']; ?>" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center" target="_blank" data-bs-toggle="tooltip" title="Generar reporte PDF">
                                                <i class="fas fa-file-pdf me-1"></i> PDF
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function () {
            // Inicializar Tooltips de Bootstrap
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })

            $('#expositoresTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                },
                pageLength: 10,
                order: [[5, 'desc']], // Ordenar por fecha de registro descendente
                dom: '<"p-3 d-flex justify-content-between align-items-center"f>t<"p-3 d-flex justify-content-between align-items-center"ip>',
            });
        });
    </script>
</body>
</html>
