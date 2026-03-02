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
        echo json_encode($response);
        exit;
    }

    $field = filter_input(INPUT_POST, 'field', FILTER_SANITIZE_STRING);
    $expositor_id = $_SESSION['expositor_id'];

    $allowed_fields = ['logo_ruta', 'banner_ruta', 'video_promocional_ruta', 'responsiva_ruta'];
    if (!in_array($field, $allowed_fields)) {
        $response['message'] = "Campo inválido para eliminación.";
        echo json_encode($response);
        exit;
    }

    try {
        $db = Database::getInstance()->getConnection();
        
        // Prepare update query to set field to NULL
        $stmt = $db->prepare("UPDATE expositores SET {$field} = NULL WHERE id = ?");
        $stmt->execute([$expositor_id]);

        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = "Archivo eliminado correctamente.";
        } else {
            $response['message'] = "No se encontró el archivo o ya fue eliminado.";
        }

    } catch (Exception $e) {
        $response['message'] = "Error del sistema: " . $e->getMessage();
    }

    echo json_encode($response);
    exit;
} else {
    header('Location: ../dashboard.php');
    exit;
}
