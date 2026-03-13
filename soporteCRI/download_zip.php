<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/../lib/Security.php';

if (PHP_SESSION_NONE === session_status()) {
    session_start();
}

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
        die("Acceso denegado.");
    }
}

// Validate Session Security
if (!Security::validateSession()) {
    header('Location: logout.php');
    exit;
}

$id = $_GET['id'] ?? null;
$section = $_GET['section'] ?? '';

if (!$id || !$section) {
    die("Parámetros faltantes.");
}

// Validate inputs
if (!filter_var($id, FILTER_VALIDATE_INT)) {
    die("ID inválido.");
}

// Allowed sections
$allowed_sections = ['logo', 'banner', 'video_promocional', 'gallery_images', 'gallery_videos', 'all'];

if (!in_array($section, $allowed_sections)) {
    die("Sección inválida.");
}

$db = Database::getInstance()->getConnection();

try {
    // Get Exhibitor Details
    $stmt = $db->prepare("SELECT * FROM expositores WHERE id = ?");
    $stmt->execute([$id]);
    $expositor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$expositor) {
        die("Expositor no encontrado.");
    }

    $files = [];
    $zipName = "expositor_{$id}_{$section}.zip";

    if ($section === 'logo' && $expositor['logo_ruta']) {
        $files[] = ['url' => $expositor['logo_ruta'], 'name' => 'logo_' . basename($expositor['logo_ruta'])];
    } elseif ($section === 'banner' && $expositor['banner_ruta']) {
        $files[] = ['url' => $expositor['banner_ruta'], 'name' => 'banner_' . basename($expositor['banner_ruta'])];
    } elseif ($section === 'video_promocional' && $expositor['video_promocional_ruta']) {
        $files[] = ['url' => $expositor['video_promocional_ruta'], 'name' => 'video_' . basename($expositor['video_promocional_ruta'])];
    } elseif ($section === 'gallery_images') {
        $stmt = $db->prepare("SELECT * FROM expositores_imagenes_galeria WHERE expositor_id = ?");
        $stmt->execute([$id]);
        $imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($imagenes as $img) {
            $files[] = ['url' => $img['imagen_ruta'], 'name' => 'galeria_img_' . basename($img['imagen_ruta'])];
        }
    } elseif ($section === 'gallery_videos') {
        $stmt = $db->prepare("SELECT * FROM expositores_videos_galeria WHERE expositor_id = ?");
        $stmt->execute([$id]);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($videos as $vid) {
            $files[] = ['url' => $vid['video_ruta'], 'name' => 'galeria_video_' . basename($vid['video_ruta'])];
        }
    } elseif ($section === 'all') {
        // Add Logo
        if ($expositor['logo_ruta']) {
            $files[] = ['url' => $expositor['logo_ruta'], 'name' => 'logo_' . basename($expositor['logo_ruta'])];
        }
        // Add Banner
        if ($expositor['banner_ruta']) {
            $files[] = ['url' => $expositor['banner_ruta'], 'name' => 'banner_' . basename($expositor['banner_ruta'])];
        }
        // Add Video Promo
        if ($expositor['video_promocional_ruta']) {
            $files[] = ['url' => $expositor['video_promocional_ruta'], 'name' => 'video_' . basename($expositor['video_promocional_ruta'])];
        }
        
        // Add Gallery Images
        $stmt = $db->prepare("SELECT * FROM expositores_imagenes_galeria WHERE expositor_id = ?");
        $stmt->execute([$id]);
        $imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($imagenes as $img) {
            $files[] = ['url' => $img['imagen_ruta'], 'name' => 'galeria_img_' . basename($img['imagen_ruta'])];
        }
        
        // Add Gallery Videos
        $stmt = $db->prepare("SELECT * FROM expositores_videos_galeria WHERE expositor_id = ?");
        $stmt->execute([$id]);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($videos as $vid) {
            $files[] = ['url' => $vid['video_ruta'], 'name' => 'galeria_video_' . basename($vid['video_ruta'])];
        }
    }

    if (empty($files)) {
        die("No hay archivos para descargar en esta sección.");
    }

    // If only one file, redirect to it (unless section is gallery or all where we might prefer zip, but strictly logic: one file -> redirect is better UX unless explicitly asked for ZIP)
    // Actually, for "all", the user expects a ZIP even if it's just one file, usually. But let's keep it simple.
    // If "all" has only 1 file, redirecting is fine.
    if (count($files) === 1 && !in_array($section, ['gallery_images', 'gallery_videos', 'all'])) {
        header("Location: " . $files[0]['url']);
        exit;
    }

    // Create Zip
    $zip = new ZipArchive();
    $tmp_file = tempnam(sys_get_temp_dir(), 'zip');
    
    if ($zip->open($tmp_file, ZipArchive::CREATE) !== TRUE) {
        die("No se pudo crear el archivo ZIP.");
    }

    foreach ($files as $file) {
        // Fetch content with SSL verification disabled for dev compatibility
        $arrContextOptions = array(
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
            ),
        );
        $content = @file_get_contents($file['url'], false, stream_context_create($arrContextOptions));
        
        if ($content !== false) {
            $zip->addFromString($file['name'], $content);
        }
    }

    $zip->close();

    // Serve Zip
    if (file_exists($tmp_file)) {
        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename=' . $zipName);
        header('Content-Length: ' . filesize($tmp_file));
        readfile($tmp_file);
        unlink($tmp_file);
    } else {
        die("Error al generar el archivo ZIP.");
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>