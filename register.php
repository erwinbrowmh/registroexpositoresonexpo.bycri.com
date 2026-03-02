<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/lib/Security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$error = '';

// Fetch companies for dropdown
$empresas = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT id, nombre_empresa FROM empresas ORDER BY nombre_empresa ASC");
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error al cargar empresas.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip_address = $_SERVER['REMOTE_ADDR'];

    // 1. Rate Limiting (5 attempts per hour for registration from same IP)
    if (!Security::checkRateLimit('register_' . $ip_address, 5, 3600)) {
        $error = "Demasiados intentos de registro. Por favor intente más tarde.";
        Security::logSecurityEvent('Registration rate limit exceeded', ['ip' => $ip_address]);
    } 
    // 2. CSRF Protection
    elseif (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
        $error = "Error de validación de seguridad (CSRF). Por favor recargue la página.";
        Security::logSecurityEvent('CSRF validation failed in registration', ['ip' => $ip_address]);
    } else {
        $id_empresa = $_POST['id_empresa'] ?? '';
        $nombre = Security::sanitizeInput($_POST['nombre'] ?? '');
        $apellido = Security::sanitizeInput($_POST['apellido'] ?? '');
        $telefono = Security::sanitizeInput($_POST['telefono'] ?? '');
        $cargo = Security::sanitizeInput($_POST['cargo'] ?? '');
        $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // New fields
        $giro = Security::sanitizeInput($_POST['giro'] ?? '');
        $requiere_mampara = isset($_POST['requiere_mampara']) && $_POST['requiere_mampara'] === '1' ? 1 : 0;
        $rotulo_antepecho = Security::sanitizeInput($_POST['rotulo_antepecho'] ?? '');
        $descripcion_breve = Security::sanitizeInput($_POST['descripcion_breve'] ?? '');
    
        // File Upload Paths
        $logo_ruta = '';
        $responsiva_ruta = '';
        $banner_ruta = '';
        $video_promocional_ruta = '';
    
        if (empty($id_empresa) || empty($nombre) || empty($apellido) || empty($correo) || empty($password)) {
            $error = 'Por favor complete todos los campos obligatorios.';
        } elseif ($password !== $confirm_password) {
            $error = 'Las contraseñas no coinciden.';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = 'El correo electrónico no es válido.';
        } else {
            try {
                $db = Database::getInstance()->getConnection();
                
                // Check if email already exists
                $stmt = $db->prepare("SELECT COUNT(*) FROM expositores WHERE correo = ?");
                $stmt->execute([$correo]);
                $emailCount = $stmt->fetchColumn();
    
                if ($emailCount > 0) {
                    $error = 'El correo ya está registrado.';
                } else {
                    
                    // Handle Logo Upload via API
                    if (isset($_FILES['logo_ruta']) && $_FILES['logo_ruta']['error'] === UPLOAD_ERR_OK) {
                        // Validate file using Security class
                        $uploadError = Security::validateFileUpload($_FILES['logo_ruta'], ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024); // 5MB max
                        
                        if ($uploadError) {
                            $error = "Error en logotipo: " . $uploadError;
                        } else {
                            $uploadedUrl = upload_file_to_api($_FILES['logo_ruta'], 'logo');
                            if ($uploadedUrl) {
                                $logo_ruta = $uploadedUrl;
                            } else {
                                $error = 'Error al subir el logotipo al servidor de recursos.';
                            }
                        }
                    }
    
                    // Handle Responsiva Upload via API
                    if (!$error && isset($_FILES['responsiva_firmada']) && $_FILES['responsiva_firmada']['error'] === UPLOAD_ERR_OK) {
                        // Validate file using Security class
                        $uploadError = Security::validateFileUpload($_FILES['responsiva_firmada'], ['pdf', 'jpg', 'jpeg', 'png'], 10 * 1024 * 1024); // 10MB max
                        
                        if ($uploadError) {
                            $error = "Error en responsiva: " . $uploadError;
                        } else {
                            $uploadedUrl = upload_file_to_api($_FILES['responsiva_firmada'], 'hoja_responsiva');
                            if ($uploadedUrl) {
                                $responsiva_ruta = $uploadedUrl;
                            } else {
                                $error = 'Error al subir la responsiva al servidor de recursos.';
                            }
                        }
                    }

                    // Handle Banner Upload via API
                    if (!$error && isset($_FILES['banner_ruta']) && $_FILES['banner_ruta']['error'] === UPLOAD_ERR_OK) {
                        $uploadError = Security::validateFileUpload($_FILES['banner_ruta'], ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024); // 5MB max
                        if ($uploadError) {
                            $error = "Error en banner: " . $uploadError;
                        } else {
                            $uploadedUrl = upload_file_to_api($_FILES['banner_ruta'], 'banner');
                            if ($uploadedUrl) {
                                $banner_ruta = $uploadedUrl;
                            } else {
                                $error = 'Error al subir el banner al servidor de recursos.';
                            }
                        }
                    }

                    // Handle Promotional Video Upload via API
                    if (!$error && isset($_FILES['video_promocional_ruta']) && $_FILES['video_promocional_ruta']['error'] === UPLOAD_ERR_OK) {
                        $uploadError = Security::validateFileUpload($_FILES['video_promocional_ruta'], ['mp4', 'avi', 'mov', 'webm'], 20 * 1024 * 1024); // 20MB max
                        if ($uploadError) {
                            $error = "Error en video promocional: " . $uploadError;
                        } else {
                            $uploadedUrl = upload_file_to_api($_FILES['video_promocional_ruta'], 'video_promocional');
                            if ($uploadedUrl) {
                                $video_promocional_ruta = $uploadedUrl;
                            } else {
                                $error = 'Error al subir el video promocional al servidor de recursos.';
                            }
                        }
                    }
    
                    if (!$error) {
                    $db->beginTransaction();

                    // 1. Hash password
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                    // 2. Create Exhibitor (Admin)
                    // Fetch company name for razon_social
                    $stmt = $db->prepare("SELECT nombre_empresa FROM empresas WHERE id = ?");
                    $stmt->execute([$id_empresa]);
                    $empresa_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    $razon_social = $empresa_data['nombre_empresa'] ?? '';

                    $stmt = $db->prepare("INSERT INTO expositores (nombre, apellido, telefono, cargo, correo, acceso, id_empresa, razon_social, giro, requiere_mampara, rotulo_antepecho, logo_ruta, responsiva_ruta, descripcion_breve, banner_ruta, video_promocional_ruta) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nombre, $apellido, $telefono, $cargo, $correo, $hashed_password, $id_empresa, $razon_social, $giro, $requiere_mampara, $rotulo_antepecho, $logo_ruta, $responsiva_ruta, $descripcion_breve, $banner_ruta, $video_promocional_ruta]);
                    $expositor_id = $db->lastInsertId();

                    // Handle Gallery Images Upload
                    if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])) {
                        foreach ($_FILES['gallery_images']['name'] as $key => $name) {
                            if ($_FILES['gallery_images']['error'][$key] === UPLOAD_ERR_OK) {
                                $file = [
                                    'name' => $_FILES['gallery_images']['name'][$key],
                                    'type' => $_FILES['gallery_images']['type'][$key],
                                    'tmp_name' => $_FILES['gallery_images']['tmp_name'][$key],
                                    'error' => $_FILES['gallery_images']['error'][$key],
                                    'size' => $_FILES['gallery_images']['size'][$key]
                                ];
                                $uploadError = Security::validateFileUpload($file, ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024); // 5MB max
                                if ($uploadError) {
                                    $error = "Error en imagen de galería ({$name}): " . $uploadError;
                                    $db->rollBack();
                                    break; // Exit loop on first error
                                }
                                $uploadedUrl = upload_file_to_api($file, 'gallery_image');
                                if ($uploadedUrl) {
                                    $stmt = $db->prepare("INSERT INTO expositores_imagenes_galeria (expositor_id, imagen_ruta) VALUES (:expositor_id, :imagen_ruta)");
                                    $stmt->execute([':expositor_id' => $expositor_id, ':imagen_ruta' => $uploadedUrl]);
                                } else {
                                    $error = 'Error al subir imagen de galería al servidor de recursos.';
                                    $db->rollBack();
                                    break; // Exit loop on first error
                                }
                            }
                        }
                    }

                    // Handle Gallery Videos Upload
                    if (!$error && isset($_FILES['gallery_videos']) && is_array($_FILES['gallery_videos']['name'])) {
                        foreach ($_FILES['gallery_videos']['name'] as $key => $name) {
                            if ($_FILES['gallery_videos']['error'][$key] === UPLOAD_ERR_OK) {
                                $file = [
                                    'name' => $_FILES['gallery_videos']['name'][$key],
                                    'type' => $_FILES['gallery_videos']['type'][$key],
                                    'tmp_name' => $_FILES['gallery_videos']['tmp_name'][$key],
                                    'error' => $_FILES['gallery_videos']['error'][$key],
                                    'size' => $_FILES['gallery_videos']['size'][$key]
                                ];
                                $uploadError = Security::validateFileUpload($file, ['mp4', 'avi', 'mov', 'webm'], 20 * 1024 * 1024); // 20MB max
                                if ($uploadError) {
                                    $error = "Error en video de galería ({$name}): " . $uploadError;
                                    $db->rollBack();
                                    break; // Exit loop on first error
                                }
                                $uploadedUrl = upload_file_to_api($file, 'gallery_video');
                                if ($uploadedUrl) {
                                    $stmt = $db->prepare("INSERT INTO expositores_videos_galeria (expositor_id, video_ruta) VALUES (:expositor_id, :video_ruta)");
                                    $stmt->execute([':expositor_id' => $expositor_id, ':video_ruta' => $uploadedUrl]);
                                } else {
                                    $error = 'Error al subir video de galería al servidor de recursos.';
                                    $db->rollBack();
                                    break; // Exit loop on first error
                                }
                            }
                        }
                    }

                    if ($error) {
                        $db->rollBack();
                    } else {
                        // 3. Audit (Transplano)
                        $stmt = $db->prepare("INSERT INTO transplano (tipo_usuario, id_usuario, usuario, password_plain) VALUES (?, ?, ?, ?)");
                        $stmt->execute(['expositor', $expositor_id, $correo, $password]);

                        $db->commit();
                        $message = "Registro exitoso. Ahora puede iniciar sesión.";
                    }
                }
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error = "Error al registrar: " . $e->getMessage();
        }
    }
}
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Expositores - ONEXPO 2026</title>
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
            padding: 2rem 0;
        }
        .register-card {
            max-width: 900px;
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
        h2 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        .section-title {
            color: var(--primary-color);
            border-bottom: 2px solid #eee;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
            margin-top: 1.5rem;
        }
        .form-label {
            font-weight: 500;
            color: var(--primary-color);
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.7rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            transform: translateY(-1px);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 43, 92, 0.25);
        }
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .download-box {
            background-color: #e9ecef;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
        }
        .download-box h6 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .download-box a {
            text-decoration: none;
            color: #333;
            font-size: 0.9rem;
            display: block;
            margin-bottom: 0.5rem;
        }
        .download-box a:hover {
            color: var(--accent-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card register-card">
                    <div class="text-center">
                        <img src="assets/img/ONEXPO+LOGO+EVENTO-02.webp" alt="ONEXPO 2026" class="logo-img">
                        <h2>Registro de Expositores</h2>
                        <p class="text-muted mb-4">Complete la información para gestionar su stand y participantes</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <div><?= htmlspecialchars($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($message): ?>
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <div><?= htmlspecialchars($message) ?> <a href="index.php" class="alert-link">Iniciar Sesión</a></div>
                        </div>
                    <?php else: ?>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">
                        
                        <!-- 1. Selección de Empresa -->
                        <h5 class="section-title"><i class="fas fa-building me-2"></i>Información de la Empresa</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="id_empresa" class="form-label">Nombre Comercial / Razón Social *</label>
                                <select class="form-select" id="id_empresa" name="id_empresa" required>
                                    <option value="">Seleccione su empresa...</option>
                                    <?php foreach ($empresas as $empresa): ?>
                                        <option value="<?= $empresa['id'] ?>" <?= (isset($_POST['id_empresa']) && $_POST['id_empresa'] == $empresa['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($empresa['nombre_empresa']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="giro" class="form-label">Giro de la Empresa *</label>
                                <input type="text" class="form-control" id="giro" name="giro" required value="<?= htmlspecialchars($_POST['giro'] ?? '') ?>" placeholder="Ej. Tecnología, Servicios, etc.">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="logo_ruta" class="form-label">Logotipo de la Empresa</label>
                                <input class="form-control" type="file" id="logo_ruta" name="logo_ruta" accept=".jpg,.jpeg,.png,.webp">
                                <div class="form-text">Formatos permitidos: JPG, PNG, WEBP.</div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="descripcion_breve" class="form-label">Descripción Breve de la Empresa</label>
                                <textarea class="form-control" id="descripcion_breve" name="descripcion_breve" rows="3" maxlength="255" placeholder="Máximo 255 caracteres"><?= htmlspecialchars($_POST['descripcion_breve'] ?? '') ?></textarea>
                                <div class="form-text">Una breve descripción que aparecerá en su perfil (máximo 255 caracteres).</div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="banner_ruta" class="form-label">Banner Principal (Imagen)</label>
                                <input class="form-control" type="file" id="banner_ruta" name="banner_ruta" accept=".jpg,.jpeg,.png,.webp">
                                <div class="form-text">Imagen principal para el perfil del expositor (JPG, PNG, WEBP).</div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="video_promocional_ruta" class="form-label">Video Promocional</label>
                                <input class="form-control" type="file" id="video_promocional_ruta" name="video_promocional_ruta" accept=".mp4,.avi,.mov,.webm">
                                <div class="form-text">Video corto para promocionar al expositor (MP4, AVI, MOV, WEBM).</div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="gallery_images" class="form-label">Galería de Imágenes (hasta 5)</label>
                                <input class="form-control" type="file" id="gallery_images" name="gallery_images[]" accept=".jpg,.jpeg,.png,.webp" multiple>
                                <div class="form-text">Imágenes adicionales para la galería (JPG, PNG, WEBP). Máximo 5 archivos.</div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="gallery_videos" class="form-label">Galería de Videos (hasta 5)</label>
                                <input class="form-control" type="file" id="gallery_videos" name="gallery_videos[]" accept=".mp4,.avi,.mov,.webm" multiple>
                                <div class="form-text">Videos adicionales para la galería (MP4, AVI, MOV, WEBM). Máximo 5 archivos.</div>
                            </div>
                        </div>

                        <!-- 2. Detalles del Stand (Mampara y Responsiva) -->
                        <h5 class="section-title"><i class="fas fa-store me-2"></i>Detalles del Stand y Documentación</h5>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="download-box">
                                    <h6><i class="fas fa-file-download me-2"></i>Documentos Importantes</h6>
                                    <a href="assets/docs/manual_expositor.pdf" target="_blank">
                                        <i class="fas fa-book me-2"></i>Descargar Manual del Expositor
                                    </a>
                                    <a href="assets/docs/hoja_responsiva.pdf" target="_blank">
                                        <i class="fas fa-file-contract me-2"></i>Descargar Hoja Responsiva de Daños
                                    </a>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label d-block">¿Requiere Mampara? *</label>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="requiere_mampara" id="mampara_si" value="1" <?= (isset($_POST['requiere_mampara']) && $_POST['requiere_mampara'] === '1') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="mampara_si">Sí</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="requiere_mampara" id="mampara_no" value="0" <?= (!isset($_POST['requiere_mampara']) || $_POST['requiere_mampara'] === '0') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="mampara_no">No</label>
                                    </div>
                                </div>
                                
                                <div class="mb-3" id="antepecho_container" style="display: none;">
                                    <label for="rotulo_antepecho" class="form-label">Rótulo del Antepecho</label>
                                    <input type="text" class="form-control" id="rotulo_antepecho" name="rotulo_antepecho" value="<?= htmlspecialchars($_POST['rotulo_antepecho'] ?? '') ?>" placeholder="Texto para el rótulo">
                                </div>

                                <div class="mb-3">
                                    <label for="responsiva_firmada" class="form-label">Carga de Responsiva Firmada</label>
                                    <input class="form-control" type="file" id="responsiva_firmada" name="responsiva_firmada" accept=".pdf,.jpg,.png">
                                    <div class="form-text">Suba el documento firmado (PDF o Imagen).</div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Datos del Administrador -->
                        <h5 class="section-title"><i class="fas fa-user-shield me-2"></i>Datos del Administrador de la Cuenta</h5>
                        <p class="text-muted small">Esta persona será responsable de registrar a los participantes del stand.</p>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombre" class="form-label">Nombre *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="apellido" class="form-label">Apellido *</label>
                                <input type="text" class="form-control" id="apellido" name="apellido" required value="<?= htmlspecialchars($_POST['apellido'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="telefono" class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="cargo" class="form-label">Cargo</label>
                                <input type="text" class="form-control" id="cargo" name="cargo" value="<?= htmlspecialchars($_POST['cargo'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- 4. Credenciales -->
                        <h5 class="section-title"><i class="fas fa-lock me-2"></i>Credenciales de Acceso</h5>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="correo" class="form-label">Correo Electrónico (Usuario) *</label>
                                <input type="email" class="form-control" id="correo" name="correo" required value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Contraseña *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirmar Contraseña *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-check-circle me-2"></i>Completar Registro
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>

                    <div class="login-link">
                        ¿Ya tienes una cuenta? <a href="index.php">Inicia Sesión aquí</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle Antepecho field
        const mamparaSi = document.getElementById('mampara_si');
        const mamparaNo = document.getElementById('mampara_no');
        const antepechoContainer = document.getElementById('antepecho_container');
        const antepechoInput = document.getElementById('rotulo_antepecho');

        if (mamparaSi && mamparaNo && antepechoContainer && antepechoInput) {
            function toggleAntepecho() {
                if (mamparaSi.checked) {
                    antepechoContainer.style.display = 'block';
                    antepechoInput.setAttribute('required', 'required');
                } else {
                    antepechoContainer.style.display = 'none';
                    antepechoInput.removeAttribute('required');
                    antepechoInput.value = '';
                }
            }

            mamparaSi.addEventListener('change', toggleAntepecho);
            mamparaNo.addEventListener('change', toggleAntepecho);

            // Run on load to set initial state
            toggleAntepecho();
        }
    </script>
</body>
</html>