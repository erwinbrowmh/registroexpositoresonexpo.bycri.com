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
    // CSRF Check (optional if strict)
    
    $action = $_POST['action'] ?? '';
    $db = Database::getInstance()->getConnection();
    
    try {
        if ($action === 'create') {
            $nombre = $_POST['nombre_empresa'] ?? '';
            $limite = $_POST['limite_participantes'] ?? 0;
            
            $stmt = $db->prepare("INSERT INTO empresas (nombre_empresa, limite_participantes) VALUES (?, ?)");
            $stmt->execute([$nombre, $limite]);
            
            $_SESSION['message'] = "Empresa creada exitosamente.";
            $_SESSION['message_type'] = "success";
            
        } elseif ($action === 'update') {
            $id = $_POST['id'] ?? 0;
            $nombre = $_POST['nombre_empresa'] ?? '';
            $limite = $_POST['limite_participantes'] ?? 0;
            
            $stmt = $db->prepare("UPDATE empresas SET nombre_empresa = ?, limite_participantes = ? WHERE id = ?");
            $stmt->execute([$nombre, $limite, $id]);
            
            $_SESSION['message'] = "Empresa actualizada exitosamente.";
            $_SESSION['message_type'] = "success";
            
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? 0;
            
            // Check dependencies
            $count = $db->prepare("SELECT COUNT(*) FROM expositores WHERE id_empresa = ?");
            $count->execute([$id]);
            if ($count->fetchColumn() > 0) {
                 $_SESSION['message'] = "No se puede eliminar: Hay expositores asociados.";
                 $_SESSION['message_type'] = "danger";
            } else {
                $stmt = $db->prepare("DELETE FROM empresas WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['message'] = "Empresa eliminada exitosamente.";
                $_SESSION['message_type'] = "success";
            }
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
}

header('Location: ../empresas.php');
exit;
