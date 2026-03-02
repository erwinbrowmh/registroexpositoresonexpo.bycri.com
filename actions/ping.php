<?php
require_once __DIR__ . '/../lib/Security.php';

// Refresh session activity and validate
if (Security::validateSession() && isset($_SESSION['expositor_id'])) {
    // Session is active and valid
    echo json_encode([
        'status' => 'ok', 
        'timestamp' => time(),
        'message' => 'Session active'
    ]);
} else {
    // Session expired or not started
    http_response_code(401);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Session expired'
    ]);
}
