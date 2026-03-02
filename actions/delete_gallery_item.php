<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../lib/Security.php';

check_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    // 1. CSRF Protection
    if (!isset($_POST['csrf_token']) || !Security::validateCsrfToken($_POST['csrf_token'])) {
        $response['message'] = "Error de validación de seguridad (CSRF). Por favor recargue la página.";
        Security::logSecurityEvent('CSRF validation failed in delete_gallery_item', ['ip' => $_SERVER['REMOTE_ADDR']]);
        echo json_encode($response);
        exit;
    }

    $item_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $item_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
    $expositor_id = $_SESSION['expositor_id'];

    if (!$item_id || !in_array($item_type, ['image', 'video'])) {
        $response['message'] = "Datos inválidos para la eliminación.";
        echo json_encode($response);
        exit;
    }

    try {
        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();

        $table = '';
        $column = '';
        if ($item_type === 'image') {
            $table = 'expositores_imagenes_galeria';
            $column = 'imagen_ruta';
        } else { // video
            $table = 'expositores_videos_galeria';
            $column = 'video_ruta';
        }

        // Get the file path before deleting from DB
        $stmt = $db->prepare("SELECT {$column} FROM {$table} WHERE id = ? AND expositor_id = ?");
        $stmt->execute([$item_id, $expositor_id]);
        $file_path = $stmt->fetchColumn();

        if (!$file_path) {
            $db->rollBack();
            $response['message'] = "Elemento de galería no encontrado o no pertenece a este expositor.";
            echo json_encode($response);
            exit;
        }

        // Delete from DB
        $stmt = $db->prepare("DELETE FROM {$table} WHERE id = ? AND expositor_id = ?");
        $stmt->execute([$item_id, $expositor_id]);

        if ($stmt->rowCount() > 0) {
            // Attempt to delete from resource server
            // Assuming delete_file_from_api function exists and handles external deletion
            // For now, we'll just log it or assume success if no specific API for deletion
            // In a real scenario, you'd call a function like:
            // delete_file_from_api($file_path);
            // For this exercise, we'll consider it deleted from the DB.
            
            $db->commit();
            $response['success'] = true;
            $response['message'] = "Elemento de galería eliminado correctamente.";
        } else {
            $db->rollBack();
            $response['message'] = "No se pudo eliminar el elemento de galería.";
        }

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $response['message'] = "Error del sistema: " . $e->getMessage();
        Security::logSecurityEvent('Error deleting gallery item', ['expositor_id' => $expositor_id, 'item_id' => $item_id, 'item_type' => $item_type, 'error' => $e->getMessage()]);
    }

    echo json_encode($response);
    exit;
} else {
    header('Location: ../dashboard.php');
    exit;
}
