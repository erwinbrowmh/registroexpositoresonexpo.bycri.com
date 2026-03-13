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
            $expositor_id = $_POST['expositor_id'] ?? null;
            $nombre_completo = $_POST['nombre_completo'] ?? '';
            $cargo_puesto = $_POST['cargo_puesto'] ?? '';
            $empresa = $_POST['empresa'] ?? '';
            $correo = $_POST['correo'] ?? '';
            $telefono = $_POST['telefono'] ?? '';
            
            $stmt = $db->prepare("INSERT INTO participantes (expositor_id, nombre_completo, cargo_puesto, empresa, correo, telefono, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$expositor_id, $nombre_completo, $cargo_puesto, $empresa, $correo, $telefono]);
            
            $_SESSION['message'] = "Participante creado exitosamente.";
            $_SESSION['message_type'] = "success";
            
        } elseif ($action === 'update') {
            $id = $_POST['id'] ?? 0;
            $expositor_id = $_POST['expositor_id'] ?? null;
            $nombre_completo = $_POST['nombre_completo'] ?? '';
            $cargo_puesto = $_POST['cargo_puesto'] ?? '';
            $empresa = $_POST['empresa'] ?? '';
            $correo = $_POST['correo'] ?? '';
            $telefono = $_POST['telefono'] ?? '';
            
            $stmt = $db->prepare("UPDATE participantes SET expositor_id = ?, nombre_completo = ?, cargo_puesto = ?, empresa = ?, correo = ?, telefono = ? WHERE id = ?");
            $stmt->execute([$expositor_id, $nombre_completo, $cargo_puesto, $empresa, $correo, $telefono, $id]);
            
            $_SESSION['message'] = "Participante actualizado exitosamente.";
            $_SESSION['message_type'] = "success";
            
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? 0;
            
            $stmt = $db->prepare("DELETE FROM participantes WHERE id = ?");
            $stmt->execute([$id]);
            
            $_SESSION['message'] = "Participante eliminado exitosamente.";
            $_SESSION['message_type'] = "success";
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
}

header('Location: ../participantes.php');
exit;
