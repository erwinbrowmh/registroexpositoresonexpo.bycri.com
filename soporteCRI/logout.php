<?php
require_once __DIR__ . '/config/db.php';

session_start();

// Clear Remember Me Token
if (isset($_COOKIE['soporte_remember_me'])) {
    $parts = explode(':', $_COOKIE['soporte_remember_me']);
    if (count($parts) === 2) {
        list($selector, $validator) = $parts;
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("DELETE FROM soporte_tokens WHERE selector = ?");
            $stmt->execute([$selector]);
        } catch (Exception $e) {
            // Ignore DB errors during logout
        }
    }
    setcookie('soporte_remember_me', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
}

session_destroy();
header("Location: index.php");
exit;
