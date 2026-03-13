<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/Security.php';

if (PHP_SESSION_NONE === session_status()) {
    session_start();
}

// Validate Session and Permissions
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

if (empty($_SESSION['can_manage_admins'])) {
    $_SESSION['message'] = "No tienes permiso para realizar esta acción.";
    $_SESSION['message_type'] = "danger";
    header('Location: ../dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $db = Database::getInstance()->getConnection();
    
    try {
        if ($action === 'create') {
            $usuario = trim($_POST['usuario'] ?? '');
            $password = $_POST['password'] ?? '';
            $can_manage_admins = isset($_POST['can_manage_admins']) ? 1 : 0;
            
            if (empty($usuario) || empty($password)) {
                throw new Exception("Usuario y contraseña son obligatorios.");
            }
            
            // Check if user exists
            $stmt = $db->prepare("SELECT COUNT(*) FROM admexpositor WHERE usuario = ?");
            $stmt->execute([$usuario]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("El usuario ya existe.");
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("INSERT INTO admexpositor (usuario, password, can_manage_admins) VALUES (?, ?, ?)");
            $stmt->execute([$usuario, $hashed_password, $can_manage_admins]);
            
            $_SESSION['message'] = "Administrador creado exitosamente.";
            $_SESSION['message_type'] = "success";
            
        } elseif ($action === 'update') {
            $id = $_POST['id'] ?? 0;
            $usuario = trim($_POST['usuario'] ?? '');
            $password = $_POST['password'] ?? '';
            $can_manage_admins = isset($_POST['can_manage_admins']) ? 1 : 0;
            
            // Prevent modifying self permissions to false if you are the only one? 
            // Ideally, we should check this, but for now simple update.
            
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE admexpositor SET usuario = ?, password = ?, can_manage_admins = ? WHERE id = ?");
                $stmt->execute([$usuario, $hashed_password, $can_manage_admins, $id]);
            } else {
                $stmt = $db->prepare("UPDATE admexpositor SET usuario = ?, can_manage_admins = ? WHERE id = ?");
                $stmt->execute([$usuario, $can_manage_admins, $id]);
            }
            
            // If updating self, update session
            if ($id == $_SESSION['admin_id']) {
                $_SESSION['can_manage_admins'] = (bool)$can_manage_admins;
            }
            
            $_SESSION['message'] = "Administrador actualizado exitosamente.";
            $_SESSION['message_type'] = "success";
            
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? 0;
            
            if ($id == $_SESSION['admin_id']) {
                throw new Exception("No puedes eliminar tu propia cuenta.");
            }
            
            // Protect main admin (ID 1)
            if ($id == 1) {
                 throw new Exception("No se puede eliminar al administrador principal.");
            }
            
            $stmt = $db->prepare("DELETE FROM admexpositor WHERE id = ?");
            $stmt->execute([$id]);
            
            $_SESSION['message'] = "Administrador eliminado exitosamente.";
            $_SESSION['message_type'] = "success";
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
}

header('Location: ../administradores.php');
exit;
