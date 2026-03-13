<?php
// includes/functions.php
require_once __DIR__ . '/../lib/Security.php';

function is_local() {
    return (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
}

function base_url($path = '') {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    // Adjust this if your app is in a subdirectory
    return $protocol . "://" . $host . "/" . ltrim($path, '/');
}

function check_remember_me() {
    if (isset($_SESSION['expositor_id'])) {
        return true;
    }

    if (!isset($_COOKIE['remember_me'])) {
        return false;
    }

    $parts = explode(':', $_COOKIE['remember_me']);
    if (count($parts) !== 2) {
        setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
        return false;
    }
    
    list($selector, $validator) = $parts;
    
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM user_tokens WHERE selector = ? AND expiry > NOW()");
        $stmt->execute([$selector]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($token && hash_equals($token['hashed_validator'], hash('sha256', $validator))) {
            // Token is valid! Log the user in.
            $stmt = $db->prepare("SELECT id, nombre, apellido, id_empresa FROM expositores WHERE id = ?");
            $stmt->execute([$token['user_id']]);
            $expositor = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($expositor) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                session_regenerate_id(true);
                $_SESSION['expositor_id'] = $expositor['id'];
                $_SESSION['expositor_nombre'] = $expositor['nombre'] . ' ' . $expositor['apellido'];
                $_SESSION['id_empresa'] = $expositor['id_empresa'];
                $_SESSION['last_activity'] = time();
                $_SESSION['created'] = time();
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                Security::logSecurityEvent('Auto-login successful', ['user_id' => $expositor['id']]);
                return true;
            }
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
    
    // Invalid token or user not found
    setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
    return false;
}

function check_auth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if session is valid (timeout, hijacking, etc.)
    if (!Security::validateSession()) {
        // Try auto-login before failing
        if (!check_remember_me()) {
            header("Location: " . base_url('index.php'));
            exit;
        }
    }

    if (!isset($_SESSION['expositor_id'])) {
        // Try auto-login if session var missing (should be handled by validateSession check usually, but good fallback)
        if (!check_remember_me()) {
            header("Location: " . base_url('index.php'));
            exit;
        }
    }
}

function get_db_connection() {
    require_once __DIR__ . '/../config/db.php';
    return Database::getInstance()->getConnection();
}

/**
 * Upload file to external API
 * @param array $file The $_FILES['input_name'] array
 * @param string $type The type of file ('logo' or 'hoja_responsiva')
 * @return array|string Returns the file URL on success, or false on failure.
 */
function upload_file_to_api($file, $type) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'message' => 'No se encontró el archivo temporal para subir.'];
    }

    $url = RESOURCE_API_URL;
    $key = API_UPLOAD_KEY;

    // Create a CURLFile object
    $cfile = new CURLFile($file['tmp_name'], $file['type'], $file['name']);

    // Data to send
    $data = [
        'api_key' => $key,
        $type => $cfile
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Set headers
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $key
    ]);

    // Disable SSL verification for local dev if needed
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $errorMessage = "CURL Error: " . curl_error($ch);
        error_log($errorMessage);
        curl_close($ch);
        return ['success' => false, 'message' => 'Error de conexión al servidor de recursos: ' . curl_error($ch)];
    }
    curl_close($ch);

    if ($httpCode !== 200) {
        $errorMessage = "API Error ($httpCode): " . $response;
        error_log($errorMessage);
        return ['success' => false, 'message' => 'El servidor de recursos devolvió un error (' . $httpCode . ').'];
    }

    $result = json_decode($response, true);
    
    if (isset($result['success']) && $result['success'] && isset($result['files'][$type])) {
        $apiUrl = $result['files'][$type];
        // Aceptar URLs absolutas del servidor o construir desde base si es relativa
        if (is_string($apiUrl) && (stripos($apiUrl, 'http://') === 0 || stripos($apiUrl, 'https://') === 0)) {
            return ['success' => true, 'url' => $apiUrl];
        } else {
            return ['success' => true, 'url' => rtrim(RESOURCE_BASE_URL, '/') . '/' . ltrim($apiUrl, '/')];
        }
    }

    // Devolver mensaje específico si la API lo proporcionó
    if (isset($result['errors'][$type])) {
        return ['success' => false, 'message' => $result['errors'][$type]];
    }
    if (isset($result['error'])) {
        return ['success' => false, 'message' => $result['error']];
    }

    $errorMessage = "API Upload Failed: " . json_encode($result);
    error_log($errorMessage);
    return ['success' => false, 'message' => 'El servidor de recursos no devolvió una URL de archivo válida.'];
}
?>
