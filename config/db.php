<?php
// Session initialization
$isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

if (PHP_SESSION_NONE === session_status()) {
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 30,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => $isSecure ? 'None' : 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/', '', $isSecure, true);
    }
    session_start();
}

// Database configuration
function set_cors_headers()
{
    // Define allowed origins
    $allowed_origins = [
        'https://registro-onexpo2026.bycri.com',
        'http://localhost',
        'http://127.0.0.1'
    ];

    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = $_SERVER['HTTP_ORIGIN'];
        if (in_array($origin, $allowed_origins)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');    // cache for 1 day
        }
    }

    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

        exit(0);
    }
}

error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'crivirtual_registro_onexpo2026');

// Detect Environment based on OS
// Windows (AppServ) -> Local -> root
// Linux/Other -> Production -> crivirtual_onexpo
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    define('DB_USER', 'root');
} else {
    define('DB_USER', 'crivirtual_onexpo');
}

define('DB_PASS', 'cri2017_');

// Master Admin Password
define('MASTER_ADMIN_PASS', 'Adexpo2026');

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'registro-onexpo2026.bycri.com';
define('SITE_URL', $protocol . '://' . $host);

define('DB_CHARSET', 'utf8mb4');

// API Resource Configuration
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // Local
    define('RESOURCE_API_URL', 'http://localhost/registro-onexpo2026.bycri.com/admin/apiResources/recibir_archivos.php');
    define('RESOURCE_BASE_URL', 'http://localhost/registro-onexpo2026.bycri.com/');
} else {
    // Production
    define('RESOURCE_API_URL', 'https://registro-onexpo2026.bycri.com/admin/apiResources/recibir_archivos.php');
    define('RESOURCE_BASE_URL', 'https://registro-onexpo2026.bycri.com/');
}
define('API_UPLOAD_KEY', 'EXPO2026_SECURE_UPLOAD');

// CLIP General Configuration
define('CLIP_USERNAME', 'ventas@standver.com');
define('CLIP_PASSWORD', '20Stv24#');

// Cargar configuración de Clip (debe ir después de las constantes de DB)
if (file_exists(__DIR__ . '/clip_config.php')) {
    require_once __DIR__ . '/clip_config.php';
}

// require_once __DIR__ . '/../lib/Logger.php';

// Connection class
class Database
{
    /**
     * @var Database|null
     */
    private static $instance = null;
    private $conn;

    private function __construct()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {

            throw new Exception("Database connection failed");
        }
    }

    /**
     * @return Database
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->conn;
    }

    // Prevent cloning
    private function __clone()
    {
    }

    // Prevent unserialization
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}
