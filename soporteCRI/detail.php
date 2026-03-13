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

if (!isset($_SESSION['soporte_logged_in']) || $_SESSION['soporte_logged_in'] !== true) {
    // Check for auto-login if session is missing
    $auto_login_success = false;
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
                    $auto_login_success = true;
                }
            } catch (Exception $e) {
                // Ignore DB errors
            }
        }
    }
    
    if (!$auto_login_success) {
        header('Location: index.php');
        exit;
    }
}

// Validate Session Security
if (!Security::validateSession()) {
    header('Location: logout.php');
    exit;
}

$id = $_GET['id'] ?? null;

if (!$id) {
    die("ID de expositor no proporcionado.");
}

// Validate ID is integer
if (!filter_var($id, FILTER_VALIDATE_INT)) {
    die("ID inválido.");
}

$db = Database::getInstance()->getConnection();

try {
    // Get Exhibitor Details
    $stmt = $db->prepare("
        SELECT 
            e.*, 
            COALESCE(em.nombre_empresa, 'Sin Empresa') as empresa_nombre 
        FROM expositores e 
        LEFT JOIN empresas em ON e.id_empresa = em.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $expositor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$expositor) {
        die("Expositor no encontrado.");
    }

    // Get Gallery Images
    $stmt = $db->prepare("SELECT * FROM expositores_imagenes_galeria WHERE expositor_id = ?");
    $stmt->execute([$id]);
    $imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get Gallery Videos
    $stmt = $db->prepare("SELECT * FROM expositores_videos_galeria WHERE expositor_id = ?");
    $stmt->execute([$id]);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error al cargar datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Expositor - Soporte CRI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4 sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                <i class="fas fa-headset me-2"></i> Soporte CRI
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center">
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
        <!-- Header & Actions -->
        <div class="row align-items-center mb-4 page-header border-0">
            <div class="col-lg-7">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Detalle Expositor</li>
                    </ol>
                </nav>
                <h2 class="fw-bold m-0 text-dark d-flex align-items-center">
                    <?php echo htmlspecialchars($expositor['nombre'] . ' ' . $expositor['apellido']); ?>
                    <span class="badge bg-light text-dark border ms-2 fs-6 align-middle">#<?php echo htmlspecialchars($expositor['id']); ?></span>
                </h2>
                <p class="text-muted m-0 mt-1"><i class="far fa-building me-1"></i> <?php echo htmlspecialchars($expositor['empresa_nombre']); ?></p>
            </div>
            <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
                <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                    <a href="dashboard.php" class="btn btn-outline-secondary d-inline-flex align-items-center shadow-sm">
                        <i class="fas fa-arrow-left me-2"></i> Volver al Inicio
                    </a>
                    <a href="download_pdf.php?id=<?php echo $id; ?>" class="btn btn-danger d-inline-flex align-items-center shadow-sm" target="_blank">
                        <i class="fas fa-file-pdf me-2"></i> Reporte PDF
                    </a>
                    <a href="download_zip.php?id=<?php echo $id; ?>&section=all" class="btn btn-primary d-inline-flex align-items-center shadow-sm">
                        <i class="fas fa-download me-2"></i> Todo (ZIP)
                    </a>
                </div>
            </div>
        </div>

        <!-- Info Sections -->
        <div class="row g-4 mb-4">
            <!-- Datos Generales -->
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-user-circle me-2"></i>Datos Generales</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted small fw-bold text-uppercase d-block">Nombre Completo</label>
                            <div class="fw-medium"><?php echo htmlspecialchars($expositor['nombre'] . ' ' . $expositor['apellido']); ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small fw-bold text-uppercase d-block">Cargo</label>
                            <div class="fw-medium"><?php echo htmlspecialchars($expositor['cargo']); ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small fw-bold text-uppercase d-block">Email</label>
                            <div class="fw-medium text-break"><a href="mailto:<?php echo htmlspecialchars($expositor['correo']); ?>" class="text-decoration-none"><?php echo htmlspecialchars($expositor['correo']); ?></a></div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small fw-bold text-uppercase d-block">Teléfono</label>
                            <div class="fw-medium"><?php echo htmlspecialchars($expositor['telefono']); ?></div>
                        </div>
                        <div>
                            <label class="text-muted small fw-bold text-uppercase d-block">WhatsApp</label>
                            <div class="fw-medium">
                                <?php if($expositor['whatsapp']): ?>
                                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $expositor['whatsapp']); ?>" target="_blank" class="text-success text-decoration-none"><i class="fab fa-whatsapp me-1"></i> <?php echo htmlspecialchars($expositor['whatsapp']); ?></a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Datos de Stand -->
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="mb-0 fw-bold text-success"><i class="fas fa-store me-2"></i>Datos del Stand</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="text-muted small fw-bold text-uppercase d-block">No. Stand</label>
                                <div class="fs-4 fw-bold text-dark"><?php echo htmlspecialchars($expositor['stand'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="col-6">
                                <label class="text-muted small fw-bold text-uppercase d-block">Tipo</label>
                                <div><span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><?php echo htmlspecialchars($expositor['tipo_stand'] ?? 'N/A'); ?></span></div>
                            </div>
                            <div class="col-12">
                                <label class="text-muted small fw-bold text-uppercase d-block">Rótulo Antepecho</label>
                                <div class="fw-medium p-2 bg-light rounded border"><?php echo htmlspecialchars($expositor['rotulo_antepecho'] ?? 'No especificado'); ?></div>
                            </div>
                            <div class="col-12">
                                <label class="text-muted small fw-bold text-uppercase d-block">Requiere Mampara</label>
                                <div>
                                    <?php if ($expositor['requiere_mampara']): ?>
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> Sí</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="fas fa-times me-1"></i> No</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Redes Sociales -->
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="mb-0 fw-bold text-info"><i class="fas fa-share-alt me-2"></i>Redes Sociales</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><i class="fas fa-globe me-2 text-secondary w-25px text-center"></i> Website</span>
                                <span class="text-muted text-end text-break ms-3 small"><?php echo htmlspecialchars($expositor['website'] ?? '-'); ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><i class="fab fa-facebook me-2 text-primary w-25px text-center"></i> Facebook</span>
                                <span class="text-muted text-end text-break ms-3 small"><?php echo htmlspecialchars($expositor['facebook'] ?? '-'); ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><i class="fab fa-twitter me-2 text-info w-25px text-center"></i> Twitter</span>
                                <span class="text-muted text-end text-break ms-3 small"><?php echo htmlspecialchars($expositor['twitter'] ?? '-'); ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><i class="fab fa-linkedin me-2 text-primary w-25px text-center"></i> LinkedIn</span>
                                <span class="text-muted text-end text-break ms-3 small"><?php echo htmlspecialchars($expositor['linkedin'] ?? '-'); ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><i class="fab fa-instagram me-2 text-danger w-25px text-center"></i> Instagram</span>
                                <span class="text-muted text-end text-break ms-3 small"><?php echo htmlspecialchars($expositor['instagram'] ?? '-'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Descripción Breve -->
            <div class="col-12">
                 <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-align-left me-2"></i>Descripción Breve</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary m-0"><?php echo nl2br(htmlspecialchars($expositor['descripcion_breve'] ?? 'Sin descripción')); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Archivos y Multimedia Header -->
        <div class="d-flex justify-content-between align-items-center mt-5 mb-4 border-bottom pb-2">
            <div>
                <h3 class="fw-bold text-dark m-0"><i class="fas fa-photo-video me-2 text-primary"></i>Archivos y Multimedia</h3>
                <p class="text-muted small m-0 mt-1">Recursos principales subidos por el expositor.</p>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <!-- Logo -->
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">Logotipo</h6>
                        <span class="badge bg-light text-muted border" title="Formato requerido: 300x300px">Info</span>
                    </div>
                    <div class="card-body text-center d-flex flex-column justify-content-center">
                        <?php if ($expositor['logo_ruta']): ?>
                            <img src="<?php echo htmlspecialchars($expositor['logo_ruta']); ?>" class="asset-preview img-fluid mb-3 mx-auto" style="max-height: 150px; object-fit: contain;">
                            <div class="mt-auto">
                                <a href="<?php echo htmlspecialchars($expositor['logo_ruta']); ?>" class="btn btn-outline-primary btn-sm w-100" download target="_blank">
                                    <i class="fas fa-download me-1"></i> Descargar Imagen
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-muted py-4">
                                <i class="fas fa-image fa-3x mb-2 opacity-25"></i>
                                <br>
                                <span class="d-block fw-medium">No disponible</span>
                                <small class="text-muted opacity-75">Sin logotipo</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Banner -->
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">Banner Principal</h6>
                        <span class="badge bg-light text-muted border" title="Formato requerido: 566x368px">Info</span>
                    </div>
                    <div class="card-body text-center d-flex flex-column justify-content-center">
                        <?php if ($expositor['banner_ruta']): ?>
                            <img src="<?php echo htmlspecialchars($expositor['banner_ruta']); ?>" class="asset-preview img-fluid mb-3 mx-auto" style="max-height: 150px; object-fit: contain;">
                            <div class="mt-auto">
                                <a href="<?php echo htmlspecialchars($expositor['banner_ruta']); ?>" class="btn btn-outline-primary btn-sm w-100" download target="_blank">
                                    <i class="fas fa-download me-1"></i> Descargar Banner
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-muted py-4">
                                <i class="fas fa-image fa-3x mb-2 opacity-25"></i>
                                <br>
                                <span class="d-block fw-medium">No disponible</span>
                                <small class="text-muted opacity-75">Sin banner</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Video Promocional -->
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">Video Promocional</h6>
                        <span class="badge bg-light text-muted border" title="Formato requerido: MP4 9:16">Info</span>
                    </div>
                    <div class="card-body text-center d-flex flex-column justify-content-center">
                        <?php if ($expositor['video_promocional_ruta']): ?>
                            <video src="<?php echo htmlspecialchars($expositor['video_promocional_ruta']); ?>" class="asset-preview w-100 mb-3" style="max-height: 150px;" controls></video>
                            <div class="mt-auto">
                                <a href="<?php echo htmlspecialchars($expositor['video_promocional_ruta']); ?>" class="btn btn-outline-primary btn-sm w-100" download target="_blank">
                                    <i class="fas fa-download me-1"></i> Descargar Video
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-muted py-4">
                                <i class="fas fa-video fa-3x mb-2 opacity-25"></i>
                                <br>
                                <span class="d-block fw-medium">No disponible</span>
                                <small class="text-muted opacity-75">Sin video</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Galería Imágenes -->
        <div class="d-flex justify-content-between align-items-center mt-4 mb-3 border-bottom pb-2">
            <div>
                <h5 class="fw-bold m-0"><i class="fas fa-images me-2 text-secondary"></i>Galería de Imágenes</h5>
                <p class="text-muted small m-0 mt-1">Imágenes adicionales de productos o servicios.</p>
            </div>
            <?php if (count($imagenes) > 0): ?>
                <a href="download_zip.php?id=<?php echo $id; ?>&section=gallery_images" class="btn btn-outline-primary btn-sm shadow-sm d-inline-flex align-items-center">
                    <i class="fas fa-file-archive me-2"></i> Descargar Galería (ZIP)
                </a>
            <?php endif; ?>
        </div>
        <div class="row g-3 mb-5">
            <?php if (count($imagenes) > 0): ?>
                <?php foreach ($imagenes as $img): ?>
                    <div class="col-6 col-md-3 col-lg-2">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center overflow-hidden position-relative group-hover-zoom" style="height: 120px;">
                                <img src="<?php echo htmlspecialchars($img['imagen_ruta']); ?>" class="img-fluid" style="object-fit: cover; width: 100%; height: 100%;">
                            </div>
                            <div class="card-body p-2 text-center">
                                <a href="<?php echo htmlspecialchars($img['imagen_ruta']); ?>" class="btn btn-light btn-sm w-100 text-muted" download target="_blank" title="Descargar imagen">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-light text-center border text-muted py-4">
                        <i class="fas fa-images fa-2x mb-3 opacity-25"></i>
                        <p class="m-0">No hay imágenes en la galería.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Galería Videos -->
        <div class="d-flex justify-content-between align-items-center mt-4 mb-3 border-bottom pb-2">
            <div>
                <h5 class="fw-bold m-0"><i class="fas fa-film me-2 text-secondary"></i>Galería de Videos</h5>
                <p class="text-muted small m-0 mt-1">Videos demostrativos adicionales.</p>
            </div>
            <?php if (count($videos) > 0): ?>
                <a href="download_zip.php?id=<?php echo $id; ?>&section=gallery_videos" class="btn btn-outline-primary btn-sm shadow-sm d-inline-flex align-items-center">
                    <i class="fas fa-file-archive me-2"></i> Descargar Galería (ZIP)
                </a>
            <?php endif; ?>
        </div>
        <div class="row g-3">
            <?php if (count($videos) > 0): ?>
                <?php foreach ($videos as $vid): ?>
                    <div class="col-12 col-md-4 col-lg-3">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-img-top bg-black d-flex align-items-center justify-content-center overflow-hidden" style="height: 180px;">
                                <video src="<?php echo htmlspecialchars($vid['video_ruta']); ?>" class="w-100 h-100" style="object-fit: cover;" controls></video>
                            </div>
                            <div class="card-body p-2 text-center">
                                <a href="<?php echo htmlspecialchars($vid['video_ruta']); ?>" class="btn btn-light btn-sm w-100 text-muted" download target="_blank" title="Descargar video">
                                    <i class="fas fa-download me-1"></i> Descargar Video
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-light text-center border text-muted py-4">
                        <i class="fas fa-film fa-2x mb-3 opacity-25"></i>
                        <p class="m-0">No hay videos en la galería.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>