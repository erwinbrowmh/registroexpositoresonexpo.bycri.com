<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/lib/Security.php';

check_auth();

$expositor_id = $_SESSION['expositor_id'];
$expositor = [];
$participantes = [];
$gallery_images = []; // Initialize as empty array
$gallery_videos = []; // Initialize as empty array
$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
$info = $_SESSION['info'] ?? '';
unset($_SESSION['message'], $_SESSION['error'], $_SESSION['info']);

try {
    $db = Database::getInstance()->getConnection();

    // Fetch Exhibitor + Company Info
    $stmt = $db->prepare("
        SELECT ex.*, COALESCE(e.nombre_empresa, ex.razon_social) as nombre_empresa, e.limite_participantes,
        (SELECT COUNT(*) FROM participantes WHERE expositor_id = ex.id) as total_participantes
        FROM expositores ex
        LEFT JOIN empresas e ON ex.id_empresa = e.id
        WHERE ex.id = ?
    ");
    $stmt->execute([$expositor_id]);
    $expositor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$expositor) {
        // Should not happen if logged in properly
        session_destroy();
        header("Location: index.php");
        exit;
    }

    // Fetch Participants
    $stmt = $db->prepare("SELECT * FROM participantes WHERE expositor_id = ? ORDER BY created_at DESC");
    $stmt->execute([$expositor_id]);
    $participantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Gallery Images
    $stmt = $db->prepare("SELECT id, imagen_ruta FROM expositores_imagenes_galeria WHERE expositor_id = ? ORDER BY orden ASC, created_at ASC");
    $stmt->execute([$expositor_id]);
    $gallery_images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Gallery Videos
    $stmt = $db->prepare("SELECT id, video_ruta FROM expositores_videos_galeria WHERE expositor_id = ? ORDER BY orden ASC, created_at ASC");
    $stmt->execute([$expositor_id]);
    $gallery_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Scans
    $stmt = $db->prepare("
        SELECT es.*, u.nombre_completo, u.email, u.empresa, u.puesto, u.telefono, u.estado_provincia, u.ciudad, t.codigo_qr 
        FROM expositor_scans es
        JOIN tickets t ON es.id_ticket = t.id_ticket
        JOIN usuarios u ON t.id_usuario = u.id_usuario
        WHERE es.expositor_id = ? 
        ORDER BY es.scan_time DESC
    ");
    $stmt->execute([$expositor_id]);
    $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_scans = count($scans);

} catch (Exception $e) {
    $error = "Error al cargar datos: " . $e->getMessage();
}

// Ensure gallery arrays are always arrays, even if queries failed or returned null
    $gallery_images = is_array($gallery_images) ? $gallery_images : [];
    $gallery_videos = is_array($gallery_videos) ? $gallery_videos : [];
    $scans = is_array($scans) ? $scans : [];
    $total_scans = $total_scans ?? 0;

    $limit = $expositor['limite_participantes'] ?? 0;
$current = $expositor['total_participantes'] ?? 0;
$remaining = max(0, $limit - $current);
$percent = ($limit > 0) ? ($current / $limit) * 100 : 0;
$csrf_token = Security::generateCsrfToken();
function res_url($u) {
    if (!$u) return $u;
    if (preg_match('/^https?:\\/\\/localhost\\/assets\\/uploads\\/expositores\\/(.+)$/', $u, $m)) {
        return rtrim(RESOURCE_BASE_URL, '/') . '/assets/uploads/expositores/' . $m[1];
    }
    return $u;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Expositor - ONEXPO 2026</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #004a99;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --info-color: #0dcaf0;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }

        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            width: 280px;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
            background-color: #1a1d20;
            color: white;
            padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            transition: transform 0.3s ease-in-out;
            z-index: 1000;
        }
        .sidebar a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            display: block;
            padding: 12px 20px;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        .sidebar a:hover, .sidebar a.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
            border-left: 4px solid var(--info-color);
        }
        .content {
            padding: 30px;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 12px;
            margin-left: 15px;
            margin-right: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .logo-container.w-100 {
            background: transparent;
            box-shadow: none;
            margin-bottom: 30px;
        }

        .logo-img {
            max-width: 100%;
            max-height: 80px;
            object-fit: contain;
        }

        /* Gallery Styles */
        .gallery-item {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .gallery-item:hover {
            transform: translateY(-5px);
        }
        .gallery-item .delete-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(220, 53, 69, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .gallery-item:hover .delete-overlay {
            opacity: 1;
        }

        .upload-placeholder {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            height: 150px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s;
            background: #fff;
        }
        .upload-placeholder:hover {
            border-color: var(--info-color);
            background: #f0f7ff;
            color: var(--primary-color);
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1.25rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .form-label.fw-bold {
            color: #495057;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .instruction-box {
            background-color: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 0 8px 8px 0;
        }
        
        .delete-profile-file {
            opacity: 0.6;
            transition: all 0.2s;
        }
        .delete-profile-file:hover {
            opacity: 1;
            transform: scale(1.1);
        }

        .pending-upload-item {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
        /* Mobile Responsive Improvements */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1040;
                transition: transform 0.3s ease-in-out;
                height: 100dvh; /* Use dynamic viewport height if supported */
                padding-bottom: 80px; /* Extra space for browser bars */
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .content {
                margin-left: 0 !important;
                padding: 10px; /* Reduced padding */
                margin-top: 50px; /* Space for toggle button */
            }
            
            /* Modal Adjustments for Mobile */
            .modal-dialog {
                margin: 0.5rem;
                max-width: none;
            }
            .modal-content {
                border-radius: 1rem;
            }
            .modal-body {
                padding: 1rem;
            }
            
            .mobile-nav-toggle {
                display: flex !important;
                align-items: center;
                justify-content: center;
                position: fixed;
                top: 10px;
                left: 10px;
                z-index: 1050;
                background: var(--primary-color);
                color: white;
                border: none;
                width: 45px;
                height: 45px;
                border-radius: 50%; /* Circle shape */
                box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            }
            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 1030;
                backdrop-filter: blur(2px);
            }
            .overlay.show {
                display: block;
            }
            
            /* Typography Tweaks */
            h2 { font-size: 1.5rem; }
            h3 { font-size: 1.25rem; }
            
            /* Input Zoom Prevention */
            input, select, textarea, .form-control {
                font-size: 16px !important;
            }
            
            /* Card & Layout Tweaks */
            .card-body {
                padding: 1rem;
            }
            .instruction-box {
                padding: 10px;
                font-size: 0.9rem;
            }
            
            /* Gallery Grid */
            .gallery-item, .upload-placeholder {
                height: 120px; /* Smaller height on mobile */
            }
            
            /* Table responsiveness improvements */
            .table-mobile-responsive thead {
                display: none; /* Hide headers on mobile */
            }
            .table-mobile-responsive tbody tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid #e0e0e0;
                border-radius: 0.75rem;
                background: #fff;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
                overflow: hidden;
            }
            .table-mobile-responsive tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.75rem 1rem;
                border-top: none;
                border-bottom: 1px solid #f0f0f0;
                text-align: right;
            }
            .table-mobile-responsive tbody td:last-child {
                border-bottom: none;
                background-color: #f8f9fa;
            }
            .table-mobile-responsive tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                margin-right: 1rem;
                text-align: left;
                color: var(--secondary-color);
                font-size: 0.9rem;
            }
            
            /* Scan Form Enhancements */
            #scan-form .input-group-lg {
                margin-bottom: 15px;
            }
            #scan-form button[type="submit"] {
                width: 100%;
                padding: 15px;
                font-size: 1.2rem;
                border-radius: 50px; /* Pill shape */
            }
        }
        
        .mobile-nav-toggle {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Mobile Nav Toggle -->
    <button class="mobile-nav-toggle d-md-none" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="overlay" id="sidebarOverlay"></div>

    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar d-flex flex-column flex-shrink-0 p-3 text-white bg-dark" id="sidebar">
            <div class="d-flex justify-content-between align-items-center d-md-none mb-3">
                <span class="fs-5 fw-bold">Menú</span>
                <button type="button" class="btn-close btn-close-white" id="sidebarClose"></button>
            </div>
            <a href="dashboard.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none justify-content-center">
                <div class="logo-container w-100">
                    <img src="assets/img/ONEXPO+LOGO+EVENTO-02.webp" alt="ONEXPO 2026" class="logo-img">
                </div>
            </a>
            <hr>
            <div class="logo-container">
                <?php if (!empty($expositor['logo_ruta'])): ?>
                    <img src="<?= htmlspecialchars(res_url($expositor['logo_ruta'])) ?>" alt="Logo Empresa" class="logo-img">
                <?php else: ?>
                    <div class="text-center text-muted"><i class="fas fa-image fa-2x"></i><br>Sin Logo</div>
                <?php endif; ?>
            </div>
            <p class="text-center small text-white-50"><?= htmlspecialchars($expositor['nombre_empresa']) ?></p>
            <hr>
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="#perfil" class="nav-link active" data-bs-toggle="pill">
                        <i class="fas fa-user me-2"></i> Datos de Contacto
                    </a>
                </li>
                <li>
                    <a href="#participantes" class="nav-link" data-bs-toggle="pill">
                        <i class="fas fa-users me-2"></i> Gafetes Expositores
                    </a>
                </li>
                <li>
                    <a href="#scan" class="nav-link" data-bs-toggle="pill">
                        <i class="fas fa-qrcode me-2"></i> Escanear Convencionista
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <span class="px-3 text-white-50 small text-uppercase">Documentos</span>
                </li>
                <li>
                    <a href="assets/docs/manual_expositor.pdf" target="_blank" class="nav-link">
                        <i class="fas fa-book me-2"></i> Manual Expositor
                    </a>
                </li>
                <li>
                    <a href="assets/docs/hoja_responsiva.pdf" target="_blank" class="nav-link">
                        <i class="fas fa-file-contract me-2"></i> Hoja Responsiva
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <a href="assets/docs/registro_montaje.pdf" target="_blank" class="nav-link text-warning fw-bold border border-warning rounded bg-dark shadow-sm pulse-animation">
                        <i class="fas fa-file-download me-2"></i> Hoja de Registro de Montaje <span class="badge bg-danger ms-1">¡AQUÍ ESTOY!</span>
                    </a>
                </li>
                <!-- Mobile Only Logout -->
                <li class="nav-item d-md-none mt-3">
                    <a href="logout.php" class="nav-link text-danger border border-danger rounded">
                        <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
                    </a>
                </li>
            </ul>
            <hr>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($expositor['nombre'] . ' ' . $expositor['apellido']) ?>&background=random" alt="" width="32" height="32" class="rounded-circle me-2">
                    <strong><?= htmlspecialchars($expositor['nombre']) ?></strong>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                    <li><a class="dropdown-item" href="logout.php">Cerrar Sesión</a></li>
                </ul>
            </div>
        </div>

        <!-- Content -->
        <div class="content flex-grow-1" style="margin-left: 280px;">
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($info): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i><?= htmlspecialchars($info) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>



            <div class="tab-content" id="v-pills-tabContent">
                <!-- Scan Tab -->
                <div class="tab-pane fade" id="scan">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Escanear Convencionista</h2>
                        <a href="actions/export_scans.php" class="btn btn-success">
                            <i class="fas fa-file-excel me-2"></i> Exportar a Excel
                        </a>
                    </div>

                    <div class="instruction-box mb-4">
                        <i class="fas fa-info-circle me-2 text-primary"></i>
                        En este apartado podra capturar los nombres de los colaboradores que estaran en su stand durante la ONEXPO
                    </div>

                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <form id="scan-form" class="row g-3 align-items-center">
                                <div class="col-md-9">
                                    <label for="qr_code" class="visually-hidden">Código QR</label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text"><i class="fas fa-qrcode"></i></span>
                                        <input type="text" class="form-control" id="qr_code" name="qr_code" placeholder="Escanea o escribe el código del gafete">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary btn-lg w-100">
                                        <i class="fas fa-camera me-2"></i> Escanear
                                    </button>
                                </div>
                            </form>
                            <div id="scan-message" class="mt-3"></div>
                        </div>
                    </div>

                    <!-- Camera Modal -->
                    <div class="modal fade" id="cameraModal" tabindex="-1" aria-labelledby="cameraModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="cameraModalLabel"><i class="fas fa-qrcode me-2"></i>Escanear Código QR</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-0 bg-dark text-center position-relative">
                                    <video id="qr-video" style="width: 100%; height: auto; max-height: 70vh; display: block;"></video>
                                    <div class="position-absolute top-50 start-50 translate-middle text-white-50" style="pointer-events: none;">
                                        <i class="fas fa-expand fa-3x opacity-50"></i>
                                    </div>
                                </div>
                                <div class="modal-footer justify-content-center">
                                    <small class="text-muted">Apunte la cámara al código QR del gafete</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-history me-2 text-muted"></i>Historial de Escaneos (<span id="total-scans-count"><?= $total_scans ?></span>)</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle table-mobile-responsive">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Hora</th>
                                            <th>Nombre</th>
                                            <th>Empresa</th>
                                            <th>Puesto</th>
                                            <th>Teléfono</th>
                                            <th>Estado</th>
                                            <th>Ciudad</th>
                                            <th>Email</th>
                                        </tr>
                                    </thead>
                                    <tbody id="scans-table-body">
                                        <?php if (empty($scans)): ?>
                                            <tr id="no-scans-row">
                                                <td colspan="8" class="text-center py-4 text-muted">
                                                    <i class="fas fa-qrcode fa-3x mb-3 d-block opacity-25"></i>
                                                    No hay escaneos registrados aún.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($scans as $scan): ?>
                                                <tr>
                                                    <td data-label="Hora"><?= date('d/m H:i', strtotime($scan['scan_time'])) ?></td>
                                                    <td data-label="Nombre" class="fw-bold"><?= htmlspecialchars($scan['nombre_completo']) ?></td>
                                                    <td data-label="Empresa"><?= htmlspecialchars($scan['empresa']) ?></td>
                                                    <td data-label="Puesto"><?= htmlspecialchars($scan['puesto']) ?></td>
                                                    <td data-label="Teléfono"><?= htmlspecialchars($scan['telefono'] ?? '') ?></td>
                                                    <td data-label="Estado"><?= htmlspecialchars($scan['estado_provincia'] ?? '') ?></td>
                                                    <td data-label="Ciudad"><?= htmlspecialchars($scan['ciudad'] ?? '') ?></td>
                                                    <td data-label="Email"><a href="mailto:<?= htmlspecialchars($scan['email']) ?>" class="text-decoration-none"><?= htmlspecialchars($scan['email']) ?></a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <script src="lib/qr-scanner-master/qr-scanner-master/qr-scanner.umd.min.js"></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            // QR Scanner Setup
                            const video = document.getElementById('qr-video');
                            const cameraModalEl = document.getElementById('cameraModal');
                            let scanner = null;

                            cameraModalEl.addEventListener('shown.bs.modal', function () {
                                if (!scanner) {
                                    scanner = new QrScanner(video, result => {
                                        scanner.stop();
                                        const modalInstance = bootstrap.Modal.getInstance(cameraModalEl);
                                        modalInstance.hide();
                                        
                                        const input = document.getElementById('qr_code');
                                        input.value = result.data || result;
                                        
                                        // Submit immediately
                                        document.getElementById('scan-form').dispatchEvent(new Event('submit'));
                                    }, {
                                        highlightScanRegion: true,
                                        highlightCodeOutline: true,
                                    });
                                }
                                scanner.start().catch(err => {
                                    console.error(err);
                                    alert('Error al acceder a la cámara. Verifique permisos y conexión segura (HTTPS).');
                                    const modalInstance = bootstrap.Modal.getInstance(cameraModalEl);
                                    modalInstance.hide();
                                });
                            });

                            cameraModalEl.addEventListener('hidden.bs.modal', function () {
                                if (scanner) {
                                    scanner.stop();
                                }
                                if (window.innerWidth > 768) {
                                    document.getElementById('qr_code').focus();
                                }
                            });

                            // Focus on input when tab is shown (Desktop only)
                            const scanTab = document.querySelector('a[href="#scan"]');
                            scanTab.addEventListener('shown.bs.tab', function (e) {
                                if (window.innerWidth > 768) {
                                    const input = document.getElementById('qr_code');
                                    input.focus();
                                }
                            });

                            // Real-time correction for scanner input (handle ' ? ´ ` chars)
                            const qrInput = document.getElementById('qr_code');
                            qrInput.addEventListener('input', function() {
                                let val = this.value;
                                // Replace common scanner mapping errors
                                if (val.match(/['?´`]/)) {
                                    this.value = val.replace(/['?´`]/g, '-');
                                }
                            });

                            // --- NFC INTEGRATION START ---
                            
                            // Helper to process scanned code (QR or NFC)
                            window.processScannedCode = (code) => {
                                const input = document.getElementById('qr_code');
                                if (!code) return;
                                
                                // Clean code
                                code = code.trim();
                                // Apply same fix as QR
                                if (code.match(/['?´`]/)) {
                                    code = code.replace(/['?´`]/g, '-');
                                }
                                
                                // Validate Format: ONX-0000-0 or ONX-0000-00
                                const match = code.match(/ONX-\d+-\d+/i);
                                if (match) {
                                    input.value = match[0].toUpperCase();
                                    // Trigger submit
                                    document.getElementById('scan-form').dispatchEvent(new Event('submit'));
                                } else {
                                    // Optional: Feedback for invalid format
                                    console.log('Ignored invalid format:', code);
                                }
                            };

                            // 1. Web NFC (Mobile/Tablet - Android/Chrome)
                            if ('NDEFReader' in window) {
                                const scanTab = document.querySelector('a[href="#scan"]');
                                scanTab.addEventListener('click', async function () {
                                    try {
                                        const ndef = new NDEFReader();
                                        await ndef.scan();
                                        console.log("NFC Scan started successfully.");
                                        
                                        ndef.onreading = event => {
                                            const decoder = new TextDecoder();
                                            for (const record of event.message.records) {
                                                if (record.recordType === "text") {
                                                    const text = decoder.decode(record.data);
                                                    window.processScannedCode(text);
                                                }
                                            }
                                        };
                                        
                                        // Visual indicator
                                        const msgDiv = document.getElementById('scan-message');
                                        if(!document.getElementById('nfc-mobile-status')) {
                                            const badge = document.createElement('div');
                                            badge.id = 'nfc-mobile-status';
                                            badge.className = 'alert alert-info mt-2 py-1';
                                            badge.innerHTML = '<i class="fas fa-wifi"></i> NFC Móvil Activo';
                                            msgDiv.parentNode.insertBefore(badge, msgDiv);
                                        }
                                        
                                    } catch (error) {
                                        console.log(`Error! Scan failed to start: ${error}.`);
                                    }
                                });
                            }

                            // 2. Desktop Bridge (WebSocket for Local NFC Reader)
                            let bridgeSocket = null;
                            const connectBridge = () => {
                                if (bridgeSocket && (bridgeSocket.readyState === WebSocket.OPEN || bridgeSocket.readyState === WebSocket.CONNECTING)) return;
                                
                                bridgeSocket = new WebSocket('ws://localhost:3000');
                                
                                bridgeSocket.onopen = () => {
                                    console.log("Connected to NFC Bridge");
                                    const msgDiv = document.getElementById('scan-message');
                                    if(!document.getElementById('nfc-bridge-status')) {
                                        const badge = document.createElement('div');
                                        badge.id = 'nfc-bridge-status';
                                        badge.className = 'alert alert-success mt-2 py-1';
                                        badge.innerHTML = '<i class="fas fa-link"></i> Lector NFC de Escritorio Conectado';
                                        msgDiv.parentNode.insertBefore(badge, msgDiv);
                                    }
                                };
                                
                                bridgeSocket.onmessage = (event) => {
                                    try {
                                        const data = JSON.parse(event.data);
                                        // Support for bridge.py (raw_dump with text)
                                        if (data.type === 'raw_dump' && data.text && data.text.trim()) {
                                             window.processScannedCode(data.text);
                                        } 
                                        // Support for bridge-pcsc.js (tag_detected with uid)
                                        else if (data.type === 'tag_detected' && data.uid) {
                                             window.processScannedCode(data.uid);
                                        }
                                    } catch(e) { console.error(e); }
                                };
                                
                                bridgeSocket.onclose = () => {
                                    const el = document.getElementById('nfc-bridge-status');
                                    if(el) el.remove();
                                    // Retry connection every 5 seconds
                                    setTimeout(connectBridge, 5000);
                                };
                                
                                bridgeSocket.onerror = (e) => {
                                    // Silent error, will retry on close
                                };
                            };

                            // Start trying to connect to bridge immediately
                            connectBridge();
                            // --- NFC INTEGRATION END ---

                            document.getElementById('scan-form').addEventListener('submit', function(e) {
                                e.preventDefault();
                                e.stopImmediatePropagation();
                                const input = document.getElementById('qr_code');
                                let code = input.value.trim();

                                // Fix scanner mapping error: replace ' with - (ONX'0000'0 -> ONX-0000-0)
                                if (code.match(/['?´`]/)) {
                                    code = code.replace(/['?´`]/g, '-');
                                    input.value = code;
                                }
                                
                                if (!code) {
                                    const modal = new bootstrap.Modal(document.getElementById('cameraModal'));
                                    modal.show();
                                    return;
                                }

                                const msgDiv = document.getElementById('scan-message');
                                const submitBtn = document.querySelector('#scan-form button[type="submit"]');
                                const originalBtnText = submitBtn.innerHTML;

                                msgDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i> Procesando...</div>';
                                submitBtn.disabled = true;
                                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Escaneando...';

                                fetch('actions/scan_attendee.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: 'qr_code=' + encodeURIComponent(code)
                                })
                                .then(response => response.json())
                                .then(data => {
                                    submitBtn.disabled = false;
                                    submitBtn.innerHTML = originalBtnText;

                                    if (data.success) {
                                        msgDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i> ' + data.message + '</div>';
                                        input.value = '';
                                        input.focus();
                                        
                                        // Update table
                                        const tbody = document.getElementById('scans-table-body');
                                        const noScans = document.getElementById('no-scans-row');
                                        if (noScans) noScans.remove();
                                        
                                        const row = document.createElement('tr');
                                        row.className = 'table-success'; // Highlight new row
                                        row.innerHTML = `
                                            <td>${data.scan.time}</td>
                                            <td class="fw-bold">${data.scan.nombre}</td>
                                            <td>${data.scan.empresa}</td>
                                            <td>${data.scan.puesto}</td>
                                            <td>${data.scan.telefono || ''}</td>
                                            <td>${data.scan.estado || ''}</td>
                                            <td>${data.scan.ciudad || ''}</td>
                                            <td><a href="mailto:${data.scan.email}" class="text-decoration-none">${data.scan.email}</a></td>
                                        `;
                                        tbody.insertBefore(row, tbody.firstChild);
                                        
                                        // Update counter
                                        const counter = document.getElementById('total-scans-count');
                                        counter.textContent = parseInt(counter.textContent) + 1;
                                        
                                        // Remove highlight after a moment
                                        setTimeout(() => row.classList.remove('table-success'), 2000);
                                        
                                    } else {
                                        msgDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i> ' + data.message + '</div>';
                                        input.select();
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    submitBtn.disabled = false;
                                    submitBtn.innerHTML = originalBtnText;
                                    msgDiv.innerHTML = '<div class="alert alert-danger">Error de conexión. Intente de nuevo.</div>';
                                });
                            });
                        });
                    </script>
                </div>
                <!-- Perfil Tab -->
                <div class="tab-pane fade show active" id="perfil">
                    <!-- Prominent Info Alert -->
                    <div class="alert alert-warning border-3 border-warning shadow-lg mb-4 p-4 alert-dismissible fade show" role="alert" style="background-color: #fff3cd;">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-exclamation-circle text-danger fa-2x me-3 pulse-animation"></i>
                            <h3 class="alert-heading fw-bold text-dark mb-0">¡AVISO IMPORTANTE!</h3>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        <p class="fs-5 mb-3">
                            Los archivos que necesita llenar se encuentran en el <strong>menú lateral</strong>, en la sección de <span class="badge bg-dark text-warning">DOCUMENTOS</span>.
                        </p>
                        <div class="mt-3 p-3 bg-white rounded border border-warning shadow-sm">
                            <ul class="list-unstyled mb-0 fs-5">
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Es <strong>muy importante</strong> que complete la documentación solicitada.
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-file-upload text-primary me-2"></i>
                                    La <strong>Hoja Responsiva</strong> debe firmarla y <strong>subirla</strong> en la sección correspondiente de su perfil (abajo).
                                </li>
                                <li>
                                    <i class="fas fa-clipboard-check text-danger me-2"></i>
                                    La <strong>Hoja de Registro de Montaje</strong> debe descargarla y enviarla al correo <a href="mailto:expositoresonexpo@cricongresos.com" class="fw-bold text-dark">expositoresonexpo@cricongresos.com</a>.
                                </li>
                            </ul>
                        </div>
                    </div>

                    <h2 class="mb-4">Datos de Contacto</h2>
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <form action="actions/update_profile.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                
                                <div class="instruction-box">
                                    <i class="fas fa-info-circle me-2 text-primary"></i>
                                    <strong>Gestión de Perfil:</strong> Aquí puede actualizar la información del contacto de su empresa que aparecerá en el directorio de expositores de la ONEXPO. Los campos marcados como <em>Solo lectura</em> son gestionados por el administrador.
                                </div>

                                <h5 class="text-primary mb-3"><i class="fas fa-id-card me-2"></i>Información Personal</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="nombre" class="form-label fw-bold">Nombre</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($expositor['nombre']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="apellido" class="form-label fw-bold">Apellido</label>
                                    <input type="text" class="form-control" id="apellido" name="apellido" value="<?= htmlspecialchars($expositor['apellido']) ?>">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="correo" class="form-label fw-bold">Correo Electrónico</label>
                                    <input type="email" class="form-control" id="correo" name="correo" value="<?= htmlspecialchars($expositor['correo']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="telefono" class="form-label fw-bold">Teléfono</label>
                                        <input type="text" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($expositor['telefono']) ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="cargo" class="form-label fw-bold">Cargo</label>
                                    <input type="text" class="form-control" id="cargo" name="cargo" value="<?= htmlspecialchars($expositor['cargo']) ?>">
                                </div>

                                <hr class="my-4">
                                <h5 class="text-primary mb-3"><i class="fas fa-building me-2"></i>Información de la Empresa</h5>
                                <div class="instruction-box mb-3 py-2 bg-light border-start-0 border-top border-bottom border-end">
                                    <small class="text-muted"><i class="fas fa-lightbulb me-1"></i> El <strong>Giro</strong> y la <strong>Descripción</strong> ayudan a los visitantes a conocer mejor su negocio.</small>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="nombre_empresa" class="form-label fw-bold">Empresa/Nombre Comercial</label>
                                        <input type="text" class="form-control" id="nombre_empresa" name="nombre_empresa" value="<?= htmlspecialchars($expositor['nombre_empresa']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <?php
                                        $giroOptions = [
                                            "Estaciones de servicio (gasolineras)",
                                            "Proveedores de equipos y tecnología para estaciones",
                                            "Empresas de combustibles y energéticos",
                                            "Proveedores de servicios regulatorios y normativos",
                                            "Tecnología aplicada al sector energético",
                                            "Seguridad industrial",
                                            "Energías alternativas y transición energética",
                                            "Servicios financieros, seguros y soporte corporativo",
                                            "Marketing, diseño y experiencia del cliente",
                                            "Constructoras y servicios integrales",
                                            "Servicio de instalación y mantenimiento a estaciones de servicio",
                                            "Imagen, señalización y techumbre",
                                            "Tanques",
                                            "Auto distribuidores de equipos",
                                            "Software y sistemas de administración",
                                            "Comercializadores de Combustibles",
                                            "Marcas de estaciones de servicio",
                                            "Otro"
                                        ];
                                        $currentGiro = $expositor['giro'] ?? '';
                                        $isCustom = !empty($currentGiro) && !in_array($currentGiro, $giroOptions);
                                        $selectValue = $isCustom ? 'Otro' : $currentGiro;
                                        ?>
                                        <label for="giro_select" class="form-label fw-bold">Giro</label>
                                        <select class="form-control" id="giro_select" onchange="toggleGiro(this)">
                                            <option value="">Seleccione una opción</option>
                                            <?php foreach ($giroOptions as $opt): ?>
                                                <option value="<?= htmlspecialchars($opt) ?>" <?= $selectValue === $opt ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($opt) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div id="giro_manual_container" class="mt-2" style="display: <?= $isCustom ? 'block' : 'none' ?>;">
                                            <input type="text" class="form-control" id="giro_manual" value="<?= $isCustom ? htmlspecialchars($currentGiro) : '' ?>" placeholder="Especifique el giro" oninput="updateHiddenGiro()">
                                        </div>
                                        <input type="hidden" name="giro" id="giro" value="<?= htmlspecialchars($currentGiro) ?>">
                                        
                                        <script>
                                        function toggleGiro(select) {
                                            const manualContainer = document.getElementById('giro_manual_container');
                                            const manualInput = document.getElementById('giro_manual');
                                            const hiddenInput = document.getElementById('giro');
                                            
                                            if (select.value === 'Otro') {
                                                manualContainer.style.display = 'block';
                                                hiddenInput.value = manualInput.value;
                                            } else {
                                                manualContainer.style.display = 'none';
                                                hiddenInput.value = select.value;
                                            }
                                        }
                                        function updateHiddenGiro() {
                                            const manualInput = document.getElementById('giro_manual');
                                            const hiddenInput = document.getElementById('giro');
                                            hiddenInput.value = manualInput.value;
                                        }
                                        </script>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="descripcion_breve" class="form-label fw-bold">Descripción Breve</label>
                                    <textarea class="form-control" id="descripcion_breve" name="descripcion_breve" rows="5" maxlength="7500"><?= htmlspecialchars($expositor['descripcion_breve'] ?? '') ?></textarea>
                                    <div class="form-text">Descripción detallada que aparecerá en su perfil (máximo 7500 caracteres). Opcional.</div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold">Número de Stand Asignado</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($expositor['stand'] ?? 'Pendiente') ?>" readonly disabled style="background-color: #e8f5e9; font-weight: bold; color: #2e7d32;">
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="logo_ruta" class="form-label fw-bold">Logotipo de la Empresa</label>
                                    <div class="card bg-light border-0 mb-2">
                                        <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between">
                                            <div class="small text-muted">Formatos: <strong>JPG, PNG, WEBP</strong> | Tamaño ideal: <strong>300x300px</strong></div>
                                            <?php if (!empty($expositor['logo_ruta'])): ?>
                                                <div class="d-flex align-items-center">
                                                    <div class="badge bg-success me-2">Subido <i class="fas fa-check"></i></div>
                                                    <button type="button" class="btn btn-outline-danger btn-sm border-0 py-0 px-1 delete-profile-file" data-field="logo_ruta" title="Eliminar Logo Actual">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($expositor['logo_ruta'])): ?>
                                        <div class="mb-3 p-3 border rounded text-center bg-light shadow-sm" style="max-width: 200px; margin: 0 auto;">
                                            <div class="mb-2 small fw-bold text-uppercase text-muted" style="font-size: 0.7rem;">Logo Actual</div>
                                            <img src="<?= htmlspecialchars(res_url($expositor['logo_ruta'])) ?>" alt="Logo Actual" class="img-fluid rounded" style="max-height: 100px; object-fit: contain;">
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" id="logo_ruta" name="logo_ruta" accept="image/*">
                                </div>

                                <div class="mb-4">
                                    <label for="banner_ruta" class="form-label fw-bold">Banner Principal (Opcional)</label>
                                    <div class="card bg-light border-0 mb-2">
                                        <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between">
                                            <div class="small text-muted">Formatos: <strong>JPG, PNG, WEBP</strong> | Dimensiones: <strong>566x368px</strong></div>
                                            <?php if (!empty($expositor['banner_ruta'])): ?>
                                                <div class="d-flex align-items-center">
                                                    <div class="badge bg-success me-2">Subido <i class="fas fa-check"></i></div>
                                                    <button type="button" class="btn btn-outline-danger btn-sm border-0 py-0 px-1 delete-profile-file" data-field="banner_ruta" title="Eliminar Banner Actual">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($expositor['banner_ruta'])): ?>
                                        <div class="mb-3 p-2 border rounded text-center bg-dark" style="background-image: linear-gradient(45deg, #212529 25%, #2b3035 25%, #2b3035 50%, #212529 50%, #212529 75%, #2b3035 75%, #2b3035 100%); background-size: 20px 20px;">
                                            <img src="<?= htmlspecialchars(res_url($expositor['banner_ruta'])) ?>" alt="Banner Actual" style="max-height: 250px; width: 100%; object-fit: contain; filter: drop-shadow(0 0 10px rgba(0,0,0,0.5));">
                                            <div class="mt-2 small text-white">Vista previa actual (Completa)</div>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" id="banner_ruta" name="banner_ruta" accept="image/*">
                                </div>

                                <div class="mb-4">
                                    <label for="video_promocional_ruta" class="form-label fw-bold">Video Promocional (Opcional)</label>
                                    <div class="card bg-light border-0 mb-2">
                                        <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between">
                                            <div class="small text-muted">Formato: <strong>MP4</strong> | Relación: <strong>9:16 (Vertical)</strong> | Máximo: <strong>55MB</strong></div>
                                            <?php if (!empty($expositor['video_promocional_ruta'])): ?>
                                                <div class="d-flex align-items-center">
                                                    <div class="badge bg-success me-2">Subido <i class="fas fa-check"></i></div>
                                                    <button type="button" class="btn btn-outline-danger btn-sm border-0 py-0 px-1 delete-profile-file" data-field="video_promocional_ruta" title="Eliminar Video Actual">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($expositor['video_promocional_ruta'])): ?>
                                        <div class="mb-3 p-3 border rounded text-center bg-dark shadow-sm position-relative overflow-hidden" style="max-width: 300px; margin: 0 auto; border-radius: 15px !important;">
                                            <div class="mb-2 small fw-bold text-uppercase text-white-50" style="font-size: 0.7rem;">Video Actual (9:16)</div>
                                            <video src="<?= htmlspecialchars(res_url($expositor['video_promocional_ruta'])) ?>" controls class="rounded shadow" style="max-height: 400px; width: 100%; object-fit: contain; background: #000;"></video>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" id="video_promocional_ruta" name="video_promocional_ruta" accept="video/mp4">
                                </div>

                                <hr class="my-4">
                                <h5 class="text-primary mb-3">Galería de Imágenes</h5>
                                <div class="instruction-box">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Instrucciones:</strong> Puede subir hasta <strong>5 imágenes</strong> en formato <strong>JPG/JPEG</strong> (Máximo <strong>5MB</strong> por imagen). 
                                    Haga clic en el botón "+" para seleccionar archivos uno por uno o de forma masiva.
                                    <div class="mt-1 small text-muted">Uso actual: <span id="image-counter"><?= count($gallery_images) ?></span> de 5</div>
                                </div>
                                
                                <div class="row mb-3" id="gallery-images-container">
                                <?php foreach ($gallery_images as $image): ?>
                                    <div class="col-6 col-md-3 mb-3">
                                        <div class="gallery-item">
                                            <img src="<?= htmlspecialchars(res_url($image['imagen_ruta'])) ?>" class="w-100" alt="Imagen de Galería" style="height: 150px; object-fit: cover;">
                                            <div class="delete-overlay">
                                                <button type="button" class="btn btn-light btn-sm delete-gallery-item" data-id="<?= $image['id'] ?>" data-type="image">
                                                    <i class="fas fa-trash text-danger"></i> Eliminar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (count($gallery_images) < 5): ?>
                                    <div class="col-6 col-md-3 mb-3" id="image-upload-trigger-container">
                                        <div class="upload-placeholder" onclick="document.getElementById('gallery_images').click()">
                                            <i class="fas fa-plus fa-2x mb-2"></i>
                                            <span>Agregar</span>
                                        </div>
                                        <input type="file" class="d-none" id="gallery_images" name="gallery_images[]" accept="image/jpeg" multiple onchange="handleFileSelect(this, 'image')">
                                    </div>
                                <?php endif; ?>
                            </div>
                                <div id="pending-images-list" class="mb-4"></div>

                                <hr class="my-4">
                                <h5 class="text-primary mb-3">Galería de Videos</h5>
                                <div class="instruction-box">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Instrucciones:</strong> Puede subir hasta <strong>5 videos</strong> en formato <strong>MP4</strong> (Máximo <strong>55MB</strong> por video). 
                                    Haga clic en el botón "+" para seleccionar archivos uno por uno o de forma masiva.
                                    <div class="mt-1 small text-muted">Uso actual: <span id="video-counter"><?= count($gallery_videos) ?></span> de 5</div>
                                </div>

                                <div class="row mb-3" id="gallery-videos-container">
                                <?php foreach ($gallery_videos as $video): ?>
                                    <div class="col-6 col-md-3 mb-3">
                                        <div class="gallery-item">
                                            <video src="<?= htmlspecialchars(res_url($video['video_ruta'])) ?>" class="w-100" style="height: 150px; object-fit: cover;"></video>
                                            <div class="delete-overlay">
                                                <button type="button" class="btn btn-light btn-sm delete-gallery-item" data-id="<?= $video['id'] ?>" data-type="video">
                                                    <i class="fas fa-trash text-danger"></i> Eliminar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (count($gallery_videos) < 5): ?>
                                    <div class="col-6 col-md-3 mb-3" id="video-upload-trigger-container">
                                        <div class="upload-placeholder" onclick="document.getElementById('gallery_videos').click()">
                                            <i class="fas fa-plus fa-2x mb-2"></i>
                                            <span>Agregar</span>
                                        </div>
                                        <input type="file" class="d-none" id="gallery_videos" name="gallery_videos[]" accept="video/mp4" multiple onchange="handleFileSelect(this, 'video')">
                                    </div>
                                <?php endif; ?>
                            </div>
                                <div id="pending-videos-list" class="mb-4"></div>

                                <hr class="my-4">
                                <h5 class="text-primary mb-3"><i class="fas fa-share-alt me-2"></i>Redes Sociales (Opcional)</h5>
                                <div class="instruction-box mb-3 py-2 bg-light border-start-0 border-top border-bottom border-end">
                                    <small class="text-muted"><i class="fas fa-link me-1"></i> Ingrese las URLs completas (ej: https://facebook.com/usuario) para que los visitantes puedan seguirlo.</small>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="website" class="form-label fw-bold">Sitio Web</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-globe text-muted"></i></span>
                                            <input type="url" class="form-control" id="website" name="website" value="<?= htmlspecialchars($expositor['website'] ?? '') ?>" placeholder="https://www.ejemplo.com">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="facebook" class="form-label fw-bold">Facebook</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-facebook-f text-muted"></i></span>
                                            <input type="text" class="form-control" id="facebook" name="facebook" value="<?= htmlspecialchars($expositor['facebook'] ?? '') ?>" placeholder="URL o Usuario">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="twitter" class="form-label fw-bold">Twitter (X)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-twitter text-muted"></i></span>
                                            <input type="text" class="form-control" id="twitter" name="twitter" value="<?= htmlspecialchars($expositor['twitter'] ?? '') ?>" placeholder="URL o Usuario">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="linkedin" class="form-label fw-bold">LinkedIn</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-linkedin-in text-muted"></i></span>
                                            <input type="text" class="form-control" id="linkedin" name="linkedin" value="<?= htmlspecialchars($expositor['linkedin'] ?? '') ?>" placeholder="URL o Usuario">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="instagram" class="form-label fw-bold">Instagram</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-instagram text-muted"></i></span>
                                            <input type="text" class="form-control" id="instagram" name="instagram" value="<?= htmlspecialchars($expositor['instagram'] ?? '') ?>" placeholder="URL o Usuario">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="whatsapp" class="form-label fw-bold">WhatsApp</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fab fa-whatsapp text-muted"></i></span>
                                            <input type="text" class="form-control" id="whatsapp" name="whatsapp" value="<?= htmlspecialchars($expositor['whatsapp'] ?? '') ?>" placeholder="Número con lada">
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4">
                                <h5 class="text-primary mb-3"><i class="fas fa-booth-curtain me-2"></i>Detalles del Stand y Documentación</h5>
                                <div class="instruction-box mb-3">
                                    <i class="fas fa-file-signature me-2"></i>
                                    <strong>Documentación Obligatoria:</strong> Es necesario descargar, firmar y subir la <strong>Hoja Responsiva</strong> que se encuentra en el panel lateral.
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="requiere_mampara" class="form-label fw-bold">¿Requiere Mampara?</label>
                                        <select class="form-select" id="requiere_mampara" name="requiere_mampara" onchange="toggleAntepecho()">
                                            <option value="0" <?= ($expositor['requiere_mampara'] == 0) ? 'selected' : '' ?>>NO</option>
                                            <option value="1" <?= ($expositor['requiere_mampara'] == 1) ? 'selected' : '' ?>>SÍ</option>
                                        </select>
                                    </div>
                                    <div class="col-md-8" id="antepecho_container" style="<?= ($expositor['requiere_mampara'] == 0) ? 'display:none;' : '' ?>">
                                        <label for="rotulo_antepecho" class="form-label fw-bold">Rótulo del Antepecho</label>
                                        <input type="text" class="form-control" id="rotulo_antepecho" name="rotulo_antepecho" value="<?= htmlspecialchars($expositor['rotulo_antepecho'] ?? '') ?>">
                                        <div class="form-text">Texto que aparecerá en la parte superior del stand.</div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="responsiva_ruta" class="form-label fw-bold">Hoja Responsiva Firmada</label>
                                    <div class="card bg-light border-0 mb-2">
                                        <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between">
                                            <div class="small text-muted">Formatos: <strong>PDF, JPG, PNG</strong> | Máximo: <strong>10MB</strong></div>
                                            <?php if (!empty($expositor['responsiva_ruta'])): ?>
                                                <div class="badge bg-success">Subido <i class="fas fa-check"></i></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($expositor['responsiva_ruta'])): ?>
                                        <div class="mb-3 p-3 border rounded bg-white d-flex align-items-center justify-content-between">
                                            <div>
                                                <i class="fas fa-file-pdf text-danger fa-2x me-3"></i>
                                                <span class="fw-bold">Hoja Responsiva Actual</span>
                                            </div>
                                            <a href="<?= htmlspecialchars($expositor['responsiva_ruta']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-external-link-alt me-1"></i> Ver Documento
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" id="responsiva_ruta" name="responsiva_ruta" accept=".pdf,.jpg,.jpeg,.png">
                                </div>

                                <div class="mt-4 text-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Guardar Cambios
                                    </button>
                                </div>
                            </form>
                            
                            <script>
                                function toggleAntepecho() {
                                    var select = document.getElementById('requiere_mampara');
                                    var container = document.getElementById('antepecho_container');
                                    if (select.value == '1') {
                                        container.style.display = 'block';
                                    } else {
                                        container.style.display = 'none';
                                    }
                                }
                            </script>
                        </div>
                    </div>
                </div>

                <!-- Gafetes Expositores Tab -->
                <div class="tab-pane fade" id="participantes">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Gafetes Expositores</h2>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal" <?= ($current >= $limit) ? 'disabled' : '' ?>>
                            <i class="fas fa-plus me-2"></i> Agregar Expositor
                        </button>
                    </div>

                    <div class="card mb-4 shadow-sm border-0 bg-light">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="card-title mb-0">Cupo Disponible</h5>
                                <span class="badge bg-primary fs-6"><?= $current ?> / <?= $limit ?></span>
                            </div>
                            <div class="progress mb-2" style="height: 10px;">
                                <div class="progress-bar <?= ($percent >= 100) ? 'bg-danger' : 'bg-success' ?>" role="progressbar" style="width: <?= $percent ?>%;" aria-valuenow="<?= $current ?>" aria-valuemin="0" aria-valuemax="<?= $limit ?>"></div>
                            </div>
                            <?php if ($current >= $limit): ?>
                                <div class="alert alert-warning mt-3 mb-0 py-2">
                                    <i class="fas fa-exclamation-triangle me-2"></i> Ha alcanzado el límite de cupos. Contacte al administrador si requiere más espacios.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4">Nombre Completo</th>
                                            <th>Cargo / Puesto</th>
                                            <th>Empresa</th>
                                            <th>Correo</th>
                                            <th>Teléfono</th>
                                            <th class="text-end pe-4">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($participantes) > 0): ?>
                                            <?php foreach ($participantes as $p): ?>
                                                <tr>
                                                    <td class="ps-4 fw-medium"><?= htmlspecialchars($p['nombre_completo']) ?></td>
                                                    <td><?= htmlspecialchars($p['cargo_puesto']) ?></td>
                                                    <td><?= htmlspecialchars($p['empresa']) ?></td>
                                                    <td><?= htmlspecialchars($p['correo'] ?? '') ?></td>
                                                    <td><?= htmlspecialchars($p['telefono'] ?? '') ?></td>
                                                    <td class="text-end pe-4">
                                                        <button class="btn btn-sm btn-outline-primary rounded-pill shadow-sm me-1 px-3" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editModal" 
                                                                data-id="<?= $p['id'] ?>" 
                                                                data-nombre="<?= htmlspecialchars($p['nombre_completo']) ?>" 
                                                                data-cargo="<?= htmlspecialchars($p['cargo_puesto']) ?>"
                                                                data-empresa="<?= htmlspecialchars($p['empresa']) ?>"
                                                                data-correo="<?= htmlspecialchars($p['correo'] ?? '') ?>"
                                                                data-telefono="<?= htmlspecialchars($p['telefono'] ?? '') ?>">
                                                            <i class="fas fa-edit me-1"></i> Editar
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger rounded-pill shadow-sm px-3" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#deleteModal" 
                                                                data-id="<?= $p['id'] ?>">
                                                            <i class="fas fa-trash me-1"></i> Eliminar
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-5 text-muted">
                                                    <i class="fas fa-users fa-3x mb-3 text-secondary"></i><br>
                                                    No hay expositores registrados.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Galeria Tab -->
                <div class="tab-pane fade" id="galeria">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Galería de Imágenes y Videos</h2>
                    </div>
                    <!-- Aquí iría el contenido de la galería si se implementa -->
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0" style="border-radius: 15px;">
                <div class="modal-header bg-dark text-white" style="border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Agregar Expositor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="actions/add_participant.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <div class="modal-body p-4 bg-light">
                        <div class="mb-3">
                            <label for="nombre" class="form-label fw-bold small text-muted">Nombre Completo <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-user text-muted"></i></span>
                                <input type="text" class="form-control" name="nombre" placeholder="Ej. Juan Pérez" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="cargo" class="form-label fw-bold small text-muted">Cargo / Puesto <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-briefcase text-muted"></i></span>
                                <input type="text" class="form-control" name="cargo" placeholder="Ej. Gerente de Ventas" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="empresa" class="form-label fw-bold small text-muted">Empresa</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-building text-muted"></i></span>
                                <input type="text" class="form-control bg-light" name="empresa" value="<?= htmlspecialchars($expositor['nombre_empresa']) ?>" required readonly>
                            </div>
                            <div class="form-text small"><i class="fas fa-lock me-1"></i>Campo bloqueado (asignado a su registro).</div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="correo" class="form-label fw-bold small text-muted">Correo Electrónico <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-envelope text-muted"></i></span>
                                    <input type="email" class="form-control" name="correo" placeholder="correo@ejemplo.com" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="telefono" class="form-label fw-bold small text-muted">Teléfono <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-phone text-muted"></i></span>
                                    <input type="tel" class="form-control" name="telefono" placeholder="55 1234 5678" pattern="[0-9]{10}" title="Por favor ingrese 10 dígitos numéricos" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0" style="border-radius: 0 0 15px 15px;">
                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm"><i class="fas fa-save me-2"></i>Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0" style="border-radius: 15px;">
                <div class="modal-header bg-dark text-white" style="border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Editar Expositor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="actions/edit_participant.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-body p-4 bg-light">
                        <div class="mb-3">
                            <label for="edit_nombre" class="form-label fw-bold small text-muted">Nombre Completo <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-user text-muted"></i></span>
                                <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_cargo" class="form-label fw-bold small text-muted">Cargo / Puesto <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fas fa-briefcase text-muted"></i></span>
                                <input type="text" class="form-control" name="cargo" id="edit_cargo" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_empresa" class="form-label fw-bold small text-muted">Empresa</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-building text-muted"></i></span>
                                <input type="text" class="form-control bg-light" name="empresa" id="edit_empresa" required readonly>
                            </div>
                            <div class="form-text small"><i class="fas fa-lock me-1"></i>Campo bloqueado.</div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_correo" class="form-label fw-bold small text-muted">Correo Electrónico <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-envelope text-muted"></i></span>
                                    <input type="email" class="form-control" name="correo" id="edit_correo" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_telefono" class="form-label fw-bold small text-muted">Teléfono <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-phone text-muted"></i></span>
                                    <input type="tel" class="form-control" name="telefono" id="edit_telefono" pattern="[0-9]{10}" title="Por favor ingrese 10 dígitos numéricos" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0" style="border-radius: 0 0 15px 15px;">
                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm"><i class="fas fa-save me-2"></i>Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="actions/delete_participant.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="id" id="delete_id">
                    <div class="modal-body">
                        <p>¿Está seguro que desea eliminar a este expositor? Esta acción no se puede deshacer.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gallery Management
        let pendingFiles = {
            image: [],
            video: []
        };

        function handleFileSelect(input, type) {
            const container = document.getElementById(`pending-${type}s-list`);
            const files = Array.from(input.files);
            const existingCount = type === 'image' ? <?= count($gallery_images) ?> : <?= count($gallery_videos) ?>;
            const currentPendingCount = pendingFiles[type].length;
            const maxAllowed = 5 - existingCount;

            if (currentPendingCount + files.length > maxAllowed) {
                alert(`Solo puede subir un máximo de 5 ${type === 'image' ? 'imágenes' : 'videos'} en total. Ya tiene ${existingCount} y seleccionó demasiados nuevos.`);
                syncFilesToInput(type);
                return;
            }

            files.forEach(file => {
                // Check if file already in pending
                if (pendingFiles[type].some(f => f.name === file.name && f.size === file.size)) return;

                pendingFiles[type].push(file);
                
                const item = document.createElement('div');
                item.className = 'pending-upload-item';
                item.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="fas ${type === 'image' ? 'fa-image' : 'fa-video'} me-3 text-info"></i>
                        <div>
                            <div class="fw-bold small">${file.name}</div>
                            <div class="text-muted small">${(file.size / 1024 / 1024).toFixed(2)} MB</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-link text-danger" onclick="removePendingFile('${file.name}', ${file.size}, '${type}', this)">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                container.appendChild(item);
            });

            syncFilesToInput(type);
            updateCounters(type, existingCount + pendingFiles[type].length);
            updateUploadTriggerVisibility(type, existingCount + pendingFiles[type].length);
        }

        function removePendingFile(name, size, type, btn) {
            pendingFiles[type] = pendingFiles[type].filter(f => !(f.name === name && f.size === size));
            btn.closest('.pending-upload-item').remove();
            
            syncFilesToInput(type);
            const existingCount = type === 'image' ? <?= count($gallery_images) ?> : <?= count($gallery_videos) ?>;
            updateCounters(type, existingCount + pendingFiles[type].length);
            updateUploadTriggerVisibility(type, existingCount + pendingFiles[type].length);
        }

        function updateCounters(type, total) {
            const counter = document.getElementById(`${type}-counter`);
            if (counter) {
                counter.textContent = total;
            }
        }

        function syncFilesToInput(type) {
            const input = document.getElementById(`gallery_${type}s`);
            const dt = new DataTransfer();
            
            pendingFiles[type].forEach(file => {
                dt.items.add(file);
            });
            
            input.files = dt.files;
        }

        function updateUploadTriggerVisibility(type, total) {
            const trigger = document.getElementById(`${type}-upload-trigger-container`);
            if (trigger) {
                if (total >= 5) {
                    trigger.style.display = 'none';
                } else {
                    trigger.style.display = 'block';
                }
            }
        }

        // Edit Modal
        var editModal = document.getElementById('editModal');
        var currentCompanyName = "<?= htmlspecialchars($expositor['nombre_empresa'] ?? '') ?>";

        editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var nombre = button.getAttribute('data-nombre');
            var cargo = button.getAttribute('data-cargo');
            // var empresa = button.getAttribute('data-empresa'); // Ignored, use currentCompanyName
            var correo = button.getAttribute('data-correo');
            var telefono = button.getAttribute('data-telefono');
            
            editModal.querySelector('#edit_id').value = id;
            editModal.querySelector('#edit_nombre').value = nombre;
            editModal.querySelector('#edit_cargo').value = cargo;
            editModal.querySelector('#edit_empresa').value = currentCompanyName;
            editModal.querySelector('#edit_correo').value = correo;
            editModal.querySelector('#edit_telefono').value = telefono;
        });

        // Delete Modal
        var deleteModal = document.getElementById('deleteModal');
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            deleteModal.querySelector('#delete_id').value = id;
        });

        // Persist Active Tab
        document.addEventListener('DOMContentLoaded', function() {
            // Restore active tab
            var activeTab = localStorage.getItem('activeTab');
            if (activeTab) {
                var tabTrigger = document.querySelector('a[href="' + activeTab + '"]');
                if (tabTrigger) {
                    var tab = new bootstrap.Tab(tabTrigger);
                    tab.show();
                }
            }

            // Save active tab on click
            var tabLinks = document.querySelectorAll('a[data-bs-toggle="pill"]');
            tabLinks.forEach(function(link) {
                link.addEventListener('shown.bs.tab', function(event) {
                    localStorage.setItem('activeTab', event.target.getAttribute('href'));
                });
            });
        });

        // Prevent double submission
        document.querySelectorAll('form:not(#scan-form)').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    return;
                }
                var submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    // Store original content
                    submitBtn.setAttribute('data-original-text', submitBtn.innerHTML);
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Procesando...';
                }
            });
        });

        // Keep Session Alive
        setInterval(function() {
            fetch('actions/ping.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'ok') {
                        console.log('Session refreshed: ' + new Date().toLocaleTimeString());
                    }
                })
                .catch(error => console.error('Error keeping session alive:', error));
        }, 300000); // Every 5 minutes

        // Prevent modal autofocus on mobile to avoid zoom/keyboard jump
        document.addEventListener('shown.bs.modal', function(event) {
            if (window.innerWidth <= 768) {
                const activeElement = document.activeElement;
                if (activeElement && (activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA' || activeElement.tagName === 'SELECT')) {
                    activeElement.blur();
                }
            }
        });
    </script>
<script>
            $(document).ready(function() {
                // CSRF Token for AJAX requests
                const csrfToken = "<?= Security::generateCsrfToken() ?>";

                // Function to handle deletion of profile files (logo, banner, video)
                $('.delete-profile-file').on('click', function() {
                    const field = $(this).data('field');
                    let fieldName = 'este archivo';
                    if (field === 'logo_ruta') fieldName = 'el logo';
                    else if (field === 'banner_ruta') fieldName = 'el banner';
                    else if (field === 'video_promocional_ruta') fieldName = 'el video promocional';

                    if (confirm(`¿Está seguro de que desea eliminar ${fieldName} actual? Esta acción no se puede deshacer.`)) {
                        const btn = $(this);
                        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                        $.ajax({
                            url: 'actions/delete_profile_file.php',
                            type: 'POST',
                            data: {
                                field: field,
                                csrf_token: csrfToken
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    alert(response.message);
                                    location.reload(); // Reload to update all previews and badges
                                } else {
                                    alert(response.message);
                                    btn.prop('disabled', false).html('<i class="fas fa-trash-alt"></i>');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error("AJAX Error: " + status + error);
                                alert("Ocurrió un error al intentar eliminar el archivo. Por favor, intente de nuevo.");
                                btn.prop('disabled', false).html('<i class="fas fa-trash-alt"></i>');
                            }
                        });
                    }
                });

                // Function to handle deletion of gallery items
                $('.delete-gallery-item').on('click', function() {
                    const itemId = $(this).data('id');
                    const itemType = $(this).data('type'); // 'image' or 'video'
                    const cardElement = $(this).closest('.col-md-3'); // Get the parent card element

                    if (confirm(`¿Está seguro de que desea eliminar este ${itemType === 'image' ? 'imagen' : 'video'} de la galería?`)) {
                        $.ajax({
                            url: 'actions/delete_gallery_item.php',
                            type: 'POST',
                            data: {
                                id: itemId,
                                type: itemType,
                                csrf_token: csrfToken
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    alert(response.message);
                                    cardElement.remove(); // Remove the card from the DOM
                                } else {
                                    alert(response.message);
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error("AJAX Error: " + status + error);
                                alert("Ocurrió un error al intentar eliminar el elemento. Por favor, intente de nuevo.");
                            }
                        });
                    }
                });
            });
        </script>
    <script>
        // Mobile Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarClose = document.getElementById('sidebarClose');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }

        if (sidebarClose) {
            sidebarClose.addEventListener('click', toggleSidebar);
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', toggleSidebar);
        }

        // Close sidebar when clicking a link on mobile
        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });
    </script>
    </body>
</html>
