<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear Remember Me Token
if (isset($_COOKIE['remember_me'])) {
    $parts = explode(':', $_COOKIE['remember_me']);
    if (count($parts) === 2) {
        list($selector, $validator) = $parts;
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("DELETE FROM user_tokens WHERE selector = ?");
            $stmt->execute([$selector]);
        } catch (Exception $e) {
            // Ignore DB errors during logout
        }
    }
    setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
}

session_destroy();
header("Location: index.php");
exit;
