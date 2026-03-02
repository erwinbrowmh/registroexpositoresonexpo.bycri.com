<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../lib/Security.php';

check_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Sesión inválida o expirada. Por favor recargue la página.";
        header("Location: ../dashboard.php");
        exit;
    }
    $id = $_POST['id'] ?? null;
    $expositor_id = $_SESSION['expositor_id'];

    if ($id) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("DELETE FROM participantes WHERE id = ? AND expositor_id = ?");
            $stmt->execute([$id, $expositor_id]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['message'] = "Participante eliminado.";
            } else {
                $_SESSION['error'] = "No se pudo eliminar el participante.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error al eliminar participante.";
        }
    }
}
header("Location: ../dashboard.php");
exit;
?>