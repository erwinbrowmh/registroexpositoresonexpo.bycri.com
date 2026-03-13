<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/Security.php';

if (PHP_SESSION_NONE === session_status()) {
    session_start();
}

// Validate Session
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $db = Database::getInstance()->getConnection();
    
    try {
        if ($action === 'create') {
            $nombre = $_POST['nombre'] ?? '';
            $apellido = $_POST['apellido'] ?? '';
            $usuario = $_POST['usuario'] ?? '';
            $password = $_POST['password'] ?? '';
            $correo = $_POST['correo'] ?? '';
            $telefono = $_POST['telefono'] ?? '';
            $id_empresa = $_POST['id_empresa'] ?? null;
            $stand = $_POST['stand'] ?? '';
            
            // Basic validation
            if (empty($usuario) || empty($password)) {
                throw new Exception("Usuario y contraseña son obligatorios.");
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("INSERT INTO expositores (nombre, apellido, usuario, password, correo, telefono, id_empresa, stand, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$nombre, $apellido, $usuario, $hashed_password, $correo, $telefono, $id_empresa, $stand]);
            
            $_SESSION['message'] = "Expositor creado exitosamente.";
            $_SESSION['message_type'] = "success";
            
        } elseif ($action === 'update') {
            $id = $_POST['id'] ?? 0;
            $nombre = $_POST['nombre'] ?? '';
            $apellido = $_POST['apellido'] ?? '';
            $usuario = $_POST['usuario'] ?? '';
            $correo = $_POST['correo'] ?? '';
            $telefono = $_POST['telefono'] ?? '';
            $id_empresa = $_POST['id_empresa'] ?? null;
            $stand = $_POST['stand'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE expositores SET nombre = ?, apellido = ?, usuario = ?, password = ?, correo = ?, telefono = ?, id_empresa = ?, stand = ? WHERE id = ?");
                $stmt->execute([$nombre, $apellido, $usuario, $hashed_password, $correo, $telefono, $id_empresa, $stand, $id]);
            } else {
                $stmt = $db->prepare("UPDATE expositores SET nombre = ?, apellido = ?, usuario = ?, correo = ?, telefono = ?, id_empresa = ?, stand = ? WHERE id = ?");
                $stmt->execute([$nombre, $apellido, $usuario, $correo, $telefono, $id_empresa, $stand, $id]);
            }
            
            $_SESSION['message'] = "Expositor actualizado exitosamente.";
            $_SESSION['message_type'] = "success";
            
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? 0;
            
            // Check dependencies
            $count = $db->prepare("SELECT COUNT(*) FROM participantes WHERE expositor_id = ?");
            $count->execute([$id]);
            if ($count->fetchColumn() > 0) {
                 $_SESSION['message'] = "No se puede eliminar: Hay participantes asociados.";
                 $_SESSION['message_type'] = "danger";
            } else {
                $stmt = $db->prepare("DELETE FROM expositores WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['message'] = "Expositor eliminado exitosamente.";
                $_SESSION['message_type'] = "success";
            }
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
}

header('Location: ../expositores.php');
exit;
