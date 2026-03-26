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
    $nombre = Security::sanitizeInput($_POST['nombre'] ?? '');
    $cargo = Security::sanitizeInput($_POST['cargo'] ?? '');
    $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);
    $telefono = Security::sanitizeInput($_POST['telefono'] ?? '');
    
    $expositor_id = $_SESSION['expositor_id'];

    if ($id && $nombre && $cargo && $correo && $telefono) {
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
             $_SESSION['error'] = "El correo electrónico no es válido.";
        } else {
            try {
                $db = Database::getInstance()->getConnection();
    
                // Get company name
                $stmt_company = $db->prepare("SELECT COALESCE(NULLIF(ex.razon_social, ''), e.nombre_empresa) as nombre_empresa FROM expositores ex LEFT JOIN empresas e ON ex.id_empresa = e.id WHERE ex.id = ?");
                $stmt_company->execute([$expositor_id]);
                $empresa = $stmt_company->fetchColumn();
    
                if ($empresa) {
                    $stmt = $db->prepare("UPDATE participantes SET nombre_completo = ?, cargo_puesto = ?, empresa = ?, correo = ?, telefono = ? WHERE id = ? AND expositor_id = ?");
                    $stmt->execute([$nombre, $cargo, $empresa, $correo, $telefono, $id, $expositor_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $_SESSION['message'] = "Expositor actualizado.";
                    } else {
                        $_SESSION['info'] = "No se realizaron cambios.";
                    }
                } else {
                     $_SESSION['error'] = "Error al obtener datos de la empresa.";
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "Error al actualizar expositor.";
            }
        }
    } else {
        $_SESSION['error'] = "Todos los campos son obligatorios.";
    }
}
header("Location: ../dashboard.php");
exit;
?>