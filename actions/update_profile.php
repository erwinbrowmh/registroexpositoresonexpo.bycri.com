<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../lib/Security.php';

check_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Sesión inválida o expirada. Por favor recargue la página.";
        header("Location: ../dashboard.php");
        exit;
    }
    $expositor_id = $_SESSION['expositor_id'];
    
    // Get POST data and sanitize
    $nombre = Security::sanitizeInput($_POST['nombre'] ?? '');
    $apellido = Security::sanitizeInput($_POST['apellido'] ?? '');
    $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);
    $telefono = Security::sanitizeInput($_POST['telefono'] ?? '');
    $cargo = Security::sanitizeInput($_POST['cargo'] ?? '');
    $nombre_empresa = Security::sanitizeInput($_POST['nombre_empresa'] ?? '');
    $giro = Security::sanitizeInput($_POST['giro'] ?? '');
    $requiere_mampara = isset($_POST['requiere_mampara']) ? (int)$_POST['requiere_mampara'] : 0;
    $rotulo_antepecho = Security::sanitizeInput($_POST['rotulo_antepecho'] ?? '');
    $website = Security::sanitizeInput($_POST['website'] ?? '');
    $facebook = Security::sanitizeInput($_POST['facebook'] ?? '');
    $twitter = Security::sanitizeInput($_POST['twitter'] ?? '');
    $linkedin = Security::sanitizeInput($_POST['linkedin'] ?? '');
    $instagram = Security::sanitizeInput($_POST['instagram'] ?? '');
    $whatsapp = Security::sanitizeInput($_POST['whatsapp'] ?? '');
    $descripcion_breve = Security::sanitizeInput($_POST['descripcion_breve'] ?? '');

    // Validar email solo si fue proporcionado
    if (!empty($correo) && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "El correo electrónico no es válido.";
        header("Location: ../dashboard.php");
        exit;
    }

    try {
        $db = Database::getInstance()->getConnection();
        
        // Ya no se requiere validar banner o video promocional como obligatorios

        $db->beginTransaction();
        
        // Check if email is taken by another user
        if (!empty($correo)) {
            $stmtCheck = $db->prepare("SELECT id FROM expositores WHERE correo = ? AND id != ?");
            $stmtCheck->execute([$correo, $expositor_id]);
            if ($stmtCheck->fetchColumn()) {
                $_SESSION['error'] = "El correo electrónico ya está registrado por otro usuario.";
                header("Location: ../dashboard.php");
                exit;
            }
        }

        // Prepare base update query parts
        // Construir actualización solo con campos provistos (no sobrescribir con vacíos)
        $updateFields = [];
        $params = [':id' => $expositor_id];
        if ($nombre !== '') { $updateFields[] = "nombre = :nombre"; $params[':nombre'] = $nombre; }
        if ($apellido !== '') { $updateFields[] = "apellido = :apellido"; $params[':apellido'] = $apellido; }
        if ($correo !== '') { $updateFields[] = "correo = :correo"; $params[':correo'] = $correo; }
        if ($telefono !== '') { $updateFields[] = "telefono = :telefono"; $params[':telefono'] = $telefono; }
        if ($cargo !== '') { $updateFields[] = "cargo = :cargo"; $params[':cargo'] = $cargo; }
        if ($giro !== '') { $updateFields[] = "giro = :giro"; $params[':giro'] = $giro; }
        // Campos boolean/numéricos: siempre permitir actualización si vienen en POST
        $updateFields[] = "requiere_mampara = :requiere_mampara"; $params[':requiere_mampara'] = $requiere_mampara;
        if ($rotulo_antepecho !== '') { $updateFields[] = "rotulo_antepecho = :rotulo_antepecho"; $params[':rotulo_antepecho'] = $rotulo_antepecho; }
        if ($website !== '') { $updateFields[] = "website = :website"; $params[':website'] = $website; }
        if ($facebook !== '') { $updateFields[] = "facebook = :facebook"; $params[':facebook'] = $facebook; }
        if ($twitter !== '') { $updateFields[] = "twitter = :twitter"; $params[':twitter'] = $twitter; }
        if ($linkedin !== '') { $updateFields[] = "linkedin = :linkedin"; $params[':linkedin'] = $linkedin; }
        if ($instagram !== '') { $updateFields[] = "instagram = :instagram"; $params[':instagram'] = $instagram; }
        if ($whatsapp !== '') { $updateFields[] = "whatsapp = :whatsapp"; $params[':whatsapp'] = $whatsapp; }
        if ($descripcion_breve !== '') { $updateFields[] = "descripcion_breve = :descripcion_breve"; $params[':descripcion_breve'] = $descripcion_breve; }

        if ($nombre_empresa !== '') {
            $updateFields[] = "razon_social = :razon_social";
            $params[':razon_social'] = $nombre_empresa;
        }

        // --- Handle Logo Upload via API ---
        if (isset($_FILES['logo_ruta']) && $_FILES['logo_ruta']['error'] === UPLOAD_ERR_OK) {
            $uploadError = Security::validateFileUpload($_FILES['logo_ruta'], ['jpg', 'png', 'webp', 'jpeg'], 5 * 1024 * 1024);
            
            if ($uploadError) {
                $_SESSION['error'] = "Error en logotipo: " . $uploadError;
                header("Location: ../dashboard.php");
                exit;
            }
            
            $uploadResult = upload_file_to_api($_FILES['logo_ruta'], 'logo');
            if ($uploadResult['success']) {
                $updateFields[] = "logo_ruta = :logo_ruta";
                $params[':logo_ruta'] = $uploadResult['url'];
            } else {
                $_SESSION['error'] = "Error al subir el logotipo: " . $uploadResult['message'];
                header("Location: ../dashboard.php");
                exit;
            }
        } elseif (isset($_FILES['logo_ruta']) && $_FILES['logo_ruta']['error'] !== UPLOAD_ERR_NO_FILE) {
             // Handle upload error (e.g. size exceeded in PHP config)
             $_SESSION['error'] = "Error al subir el logotipo: " . $_FILES['logo_ruta']['error'];
             header("Location: ../dashboard.php");
             exit;
        }

        // --- Handle Banner Upload via API ---
        if (isset($_FILES['banner_ruta']) && $_FILES['banner_ruta']['error'] === UPLOAD_ERR_OK) {
            $uploadError = Security::validateFileUpload($_FILES['banner_ruta'], ['jpg', 'png', 'webp', 'jpeg'], 5 * 1024 * 1024);
            
            if ($uploadError) {
                $_SESSION['error'] = "Error en banner: " . $uploadError;
                header("Location: ../dashboard.php");
                exit;
            }
            
            $uploadResult = upload_file_to_api($_FILES['banner_ruta'], 'banner');
            if ($uploadResult['success']) {
                $updateFields[] = "banner_ruta = :banner_ruta";
                $params[':banner_ruta'] = $uploadResult['url'];
            } else {
                $_SESSION['error'] = "Error al subir el banner: " . $uploadResult['message'];
                header("Location: ../dashboard.php");
                exit;
            }
        } elseif (isset($_FILES['banner_ruta']) && $_FILES['banner_ruta']['error'] !== UPLOAD_ERR_NO_FILE) {
             $_SESSION['error'] = "Error al subir el banner: " . $_FILES['banner_ruta']['error'];
             header("Location: ../dashboard.php");
             exit;
        }

        // --- Handle Video Promocional Upload via API ---
        if (isset($_FILES['video_promocional_ruta']) && $_FILES['video_promocional_ruta']['error'] === UPLOAD_ERR_OK) {
            $uploadError = Security::validateFileUpload($_FILES['video_promocional_ruta'], ['mp4'], 55 * 1024 * 1024); // 55MB max
            
            if ($uploadError) {
                $_SESSION['error'] = "Error en video promocional: " . $uploadError;
                header("Location: ../dashboard.php");
                exit;
            }
            
            $uploadResult = upload_file_to_api($_FILES['video_promocional_ruta'], 'video_promocional');
            if ($uploadResult['success']) {
                $updateFields[] = "video_promocional_ruta = :video_promocional_ruta";
                $params[':video_promocional_ruta'] = $uploadResult['url'];
            } else {
                $_SESSION['error'] = "Error al subir el video promocional: " . $uploadResult['message'];
                header("Location: ../dashboard.php");
                exit;
            }
        } elseif (isset($_FILES['video_promocional_ruta']) && $_FILES['video_promocional_ruta']['error'] !== UPLOAD_ERR_NO_FILE) {
             $_SESSION['error'] = "Error al subir el video promocional: " . $_FILES['video_promocional_ruta']['error'];
             header("Location: ../dashboard.php");
             exit;
        }
        
        // --- Handle Responsiva Upload via API ---
        $responsivaKey = null;
        if (isset($_FILES['responsiva_ruta'])) { $responsivaKey = 'responsiva_ruta'; }
        if (isset($_FILES['responsiva_firmada'])) { $responsivaKey = 'responsiva_firmada'; }
        if ($responsivaKey && $_FILES[$responsivaKey]['error'] === UPLOAD_ERR_OK) {
             $uploadError = Security::validateFileUpload($_FILES[$responsivaKey], ['pdf', 'jpg', 'jpeg', 'png'], 10 * 1024 * 1024);
             
             if ($uploadError) {
                 $_SESSION['error'] = "Error en responsiva: " . $uploadError;
                 header("Location: ../dashboard.php");
                 exit;
             }
             
             $uploadResult = upload_file_to_api($_FILES[$responsivaKey], 'hoja_responsiva');
             if ($uploadResult['success']) {
                $updateFields[] = "responsiva_ruta = :responsiva_ruta";
                $params[':responsiva_ruta'] = $uploadResult['url'];
            } else {
                $_SESSION['error'] = "Error al subir la responsiva: " . $uploadResult['message'];
                header("Location: ../dashboard.php");
                exit;
            }
        } elseif ($responsivaKey && $_FILES[$responsivaKey]['error'] !== UPLOAD_ERR_NO_FILE) {
             $_SESSION['error'] = "Error al subir la responsiva: " . $_FILES[$responsivaKey]['error'];
             header("Location: ../dashboard.php");
             exit;
        }

        // --- Handle Gallery Images Upload via API ---
        if (isset($_FILES['gallery_images'])) {
            $imgSlot = 1;
            foreach ($_FILES['gallery_images']['name'] as $key => $name) {
                if ($_FILES['gallery_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['gallery_images']['name'][$key],
                        'type' => $_FILES['gallery_images']['type'][$key],
                        'tmp_name' => $_FILES['gallery_images']['tmp_name'][$key],
                        'error' => $_FILES['gallery_images']['error'][$key],
                        'size' => $_FILES['gallery_images']['size'][$key]
                    ];

                    $uploadError = Security::validateFileUpload($file, ['jpg', 'jpeg'], 5 * 1024 * 1024); // 5MB max per image
                    
                    if ($uploadError) {
                        $_SESSION['error'] = "Error en imagen de galería ({$name}): " . $uploadError;
                        $db->rollBack();
                        header("Location: ../dashboard.php");
                        exit;
                    }
                    
                    if ($imgSlot > 5) {
                        continue;
                    }
                    $uploadResult = upload_file_to_api($file, 'galeria_imagen_' . $imgSlot);
                    $imgSlot++;
                    if ($uploadResult['success']) {
                        $stmt = $db->prepare("INSERT INTO expositores_imagenes_galeria (expositor_id, imagen_ruta) VALUES (:expositor_id, :imagen_ruta)");
                        $stmt->execute([':expositor_id' => $expositor_id, ':imagen_ruta' => $uploadResult['url']]);
                    } else {
                        $_SESSION['error'] = "Error al subir la imagen de galería ({$name}): " . $uploadResult['message'];
                        $db->rollBack();
                        header("Location: ../dashboard.php");
                        exit;
                    }
                } elseif ($_FILES['gallery_images']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                    $_SESSION['error'] = "Error al subir la imagen de galería ({$name}): " . $_FILES['gallery_images']['error'][$key];
                    $db->rollBack();
                    header("Location: ../dashboard.php");
                    exit;
                }
            }
        }

        // --- Handle Gallery Videos Upload via API ---
        if (isset($_FILES['gallery_videos'])) {
            $vidSlot = 1;
            foreach ($_FILES['gallery_videos']['name'] as $key => $name) {
                if ($_FILES['gallery_videos']['error'][$key] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['gallery_videos']['name'][$key],
                        'type' => $_FILES['gallery_videos']['type'][$key],
                        'tmp_name' => $_FILES['gallery_videos']['tmp_name'][$key],
                        'error' => $_FILES['gallery_videos']['error'][$key],
                        'size' => $_FILES['gallery_videos']['size'][$key]
                    ];

                    $uploadError = Security::validateFileUpload($file, ['mp4'], 55 * 1024 * 1024); // 55MB max per video
                    
                    if ($uploadError) {
                        $_SESSION['error'] = "Error en video de galería ({$name}): " . $uploadError;
                        $db->rollBack();
                        header("Location: ../dashboard.php");
                        exit;
                    }
                    
                    if ($vidSlot > 5) {
                        continue;
                    }
                    $uploadResult = upload_file_to_api($file, 'galeria_video_' . $vidSlot);
                    $vidSlot++;
                    if ($uploadResult['success']) {
                        $stmt = $db->prepare("INSERT INTO expositores_videos_galeria (expositor_id, video_ruta) VALUES (:expositor_id, :video_ruta)");
                        $stmt->execute([':expositor_id' => $expositor_id, ':video_ruta' => $uploadResult['url']]);
                    } else {
                        $_SESSION['error'] = "Error al subir el video de galería ({$name}): " . $uploadResult['message'];
                        $db->rollBack();
                        header("Location: ../dashboard.php");
                        exit;
                    }
                } elseif ($_FILES['gallery_videos']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                    $_SESSION['error'] = "Error al subir el video de galería ({$name}): " . $_FILES['gallery_videos']['error'][$key];
                    $db->rollBack();
                    header("Location: ../dashboard.php");
                    exit;
                }
            }
        }
        
        // Construct Final Query
        // Si no hay cambios en campos de texto, aún así permitir si solo hubo archivos
        if (empty($updateFields)) {
            $updateFields[] = "id = :id";
        }
        $sql = "UPDATE expositores SET " . implode(", ", $updateFields) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        
        if ($stmt->execute($params)) {
            $db->commit();
            $_SESSION['message'] = "Perfil actualizado correctamente.";
        } else {
            $db->rollBack();
            $_SESSION['error'] = "Error al actualizar el perfil.";
        }

    } catch (Exception $e) {
        if (isset($db) && $db instanceof PDO) {
            try {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
            } catch (Exception $e2) {
                // ignore rollback errors
            }
        }
        error_log("update_profile.php exception: " . $e->getMessage());
        $_SESSION['error'] = "Error del sistema: " . $e->getMessage();
    }
    
    header("Location: ../dashboard.php");
    exit;
}
?>
