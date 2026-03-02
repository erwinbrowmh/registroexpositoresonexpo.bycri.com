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

function check_auth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if session is valid (timeout, hijacking, etc.)
    if (!Security::validateSession()) {
        header("Location: " . base_url('index.php'));
        exit;
    }

    if (!isset($_SESSION['expositor_id'])) {
        header("Location: " . base_url('index.php'));
        exit;
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
