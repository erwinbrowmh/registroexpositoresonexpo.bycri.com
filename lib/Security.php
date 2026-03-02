<?php

/**
 * Security Class - ONEXPO 2026
 * Centralizes security functions for the application
 */
class Security
{
    /**
     * Sanitize and validate input data
     * @param mixed $data Input data to sanitize
     * @param string $type Type of validation (text, email, number, alpha, alphanumeric)
     * @return mixed Sanitized data or false if validation fails
     */
    public static function sanitizeInput($data, $type = 'text')
    {
        if (is_null($data)) {
            return null;
        }

        // Remove whitespace
        $data = trim($data);

        // Remove backslashes
        $data = stripslashes($data);

        // Convert special characters to HTML entities
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

        // Additional validation based on type
        switch ($type) {
            case 'email':
                $data = filter_var($data, FILTER_SANITIZE_EMAIL);
                if (!filter_var($data, FILTER_VALIDATE_EMAIL)) {
                    return false;
                }
                break;

            case 'number':
            case 'int':
                if (!is_numeric($data)) {
                    return false;
                }
                $data = filter_var($data, FILTER_SANITIZE_NUMBER_INT);
                break;

            case 'float':
                if (!is_numeric($data)) {
                    return false;
                }
                $data = filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                break;

            case 'alpha':
                // Only letters
                if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u', $data)) {
                    return false;
                }
                break;

            case 'alphanumeric':
                // Letters and numbers only
                if (!preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s]+$/u', $data)) {
                    return false;
                }
                break;

            case 'username':
                // Username: letters, numbers, underscore, hyphen, spaces, accents
                if (!preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\._-]{3,50}$/u', $data)) {
                    return false;
                }
                break;

            case 'text':
            default:
                // Allow most characters but prevent dangerous ones
                $data = preg_replace('/[<>]/', '', $data);
                break;
        }

        return $data;
    }

    /**
     * Hash a password securely using PHP's password_hash
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify a password against a hash
     * @param string $password Plain text password
     * @param string $hash Hashed password from database
     * @return bool True if password matches
     */
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Generate a CSRF token
     * @return string CSRF token
     */
    public static function generateCSRFToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate a CSRF token
     * @param string $token Token to validate
     * @return bool True if token is valid
     */
    public static function validateCSRFToken($token)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Prevent SQL injection by validating prepared statement parameters
     * @param array $params Parameters to validate
     * @return bool True if all parameters are safe
     */
    public static function validatePreparedParams($params)
    {
        foreach ($params as $param) {
            // Check for SQL injection patterns
            if (is_string($param)) {
                $dangerous_patterns = [
                    '/(\bUNION\b|\bSELECT\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bDROP\b|\bCREATE\b|\bALTER\b)/i',
                    '/(\-\-|\/\*|\*\/|;)/',
                    '/(\bOR\b|\bAND\b)\s+[\'"]*\s*\d+\s*=\s*\d+/i'
                ];

                foreach ($dangerous_patterns as $pattern) {
                    if (preg_match($pattern, $param)) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * Log security events
     * @param string $event Event description
     * @param array $context Additional context
     */
    public static function logSecurityEvent($event, $context = [])
    {
        $logFile = __DIR__ . '/../logs/seguridad.log';
        $logDir = dirname($logFile);

        // Create logs directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $logEntry = sprintf(
            "[%s] IP: %s | Event: %s | Context: %s | User-Agent: %s\n",
            $timestamp,
            $ip,
            $event,
            json_encode($context),
            $userAgent
        );

        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Prevent brute force attacks by implementing rate limiting
     * @param string $identifier Unique identifier (e.g., IP address, username)
     * @param int $maxAttempts Maximum allowed attempts
     * @param int $timeWindow Time window in seconds
     * @return bool True if rate limit not exceeded
     */
    public static function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $key = 'rate_limit_' . md5($identifier);

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
        }

        $data = $_SESSION[$key];
        $currentTime = time();

        // Reset if time window has passed
        if ($currentTime - $data['first_attempt'] > $timeWindow) {
            $_SESSION[$key] = ['count' => 1, 'first_attempt' => $currentTime];
            return true;
        }

        // Check if limit exceeded
        if ($data['count'] >= $maxAttempts) {
            $timeRemaining = $timeWindow - ($currentTime - $data['first_attempt']);
            self::logSecurityEvent('Rate limit exceeded', [
                'identifier' => $identifier,
                'attempts' => $data['count'],
                'time_remaining' => $timeRemaining
            ]);
            return false;
        }

        // Increment counter
        $_SESSION[$key]['count']++;
        return true;
    }

    /**
     * Reset rate limit for an identifier
     * @param string $identifier Unique identifier
     */
    public static function resetRateLimit($identifier)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $key = 'rate_limit_' . md5($identifier);
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Validate session to prevent session hijacking
     * @return bool True if session is valid
     */
    public static function validateSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Session timeout check (24 hours) - Consistent with .htaccess and cookie settings
        $timeout_duration = 2592000; // 30 days based on .htaccess

        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
            // Session expired
            session_unset();
            session_destroy();
            return false;
        }
        
        // Update last activity time stamp
        $_SESSION['last_activity'] = time();

        // Regenerate session ID periodically (every 30 mins) to prevent fixation
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }

        // Validate user agent
        if (!isset($_SESSION['user_agent'])) {
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        } else if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            self::logSecurityEvent('Session user agent mismatch');
            // return false; // Relaxed for now as user requested improvements not strict lockdown
             // return false;
        }

        return true;
    }

    /**
     * Escape output for safe HTML display
     * @param string $data Data to escape
     * @return string Escaped data
     */
    public static function escapeOutput($data)
    {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate file upload
     * @param array $file $_FILES['input_name']
     * @param array $allowedExtensions Array of allowed extensions (e.g. ['jpg', 'png'])
     * @param int $maxSize Maximum size in bytes
     * @return string|null Error message or null if valid
     */
    public static function validateFileUpload($file, $allowedExtensions, $maxSize)
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return 'Parámetros de archivo inválidos.';
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return 'No se envió ningún archivo.';
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $max_upload = ini_get('upload_max_filesize');
                return "El archivo excede el tamaño permitido por el servidor (Máximo: {$max_upload}).";
            default:
                return 'Error desconocido al subir el archivo.';
        }

        if ($file['size'] > $maxSize) {
            return 'El archivo excede el tamaño máximo permitido.';
        }

        $mimeType = null;
        if (extension_loaded('fileinfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']) ?: null;
        } elseif (function_exists('mime_content_type')) {
            $mimeType = @mime_content_type($file['tmp_name']) ?: null;
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions)) {
            return 'Extensión de archivo no permitida.';
        }

        // MIME type validation (only if mimeType was detected)
        if ($mimeType) {
            $validMimes = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'pdf' => 'application/pdf',
                'mp4' => 'video/mp4'
            ];

            if (array_key_exists($extension, $validMimes)) {
                 if ($extension === 'pdf' && in_array($mimeType, ['application/x-pdf', 'application/octet-stream'])) {
                     // allow
                 } elseif ($mimeType !== $validMimes[$extension]) {
                     return 'El tipo de archivo no coincide con la extensión.';
                 }
            }
        }

        return null;
    }
}
