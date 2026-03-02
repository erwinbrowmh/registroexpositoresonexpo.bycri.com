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
    
    $nombre = Security::sanitizeInput($_POST['nombre'] ?? '');
    $cargo = Security::sanitizeInput($_POST['cargo'] ?? '');
    $correo = filter_input(INPUT_POST, 'correo', FILTER_SANITIZE_EMAIL);
    $telefono = Security::sanitizeInput($_POST['telefono'] ?? '');
    
    $expositor_id = $_SESSION['expositor_id'];

    if ($nombre && $cargo && $correo && $telefono) {
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
             $_SESSION['error'] = "El correo electrónico no es válido.";
        } else {
            try {
                $db = Database::getInstance()->getConnection();
                
                // Verify limit and get company name
                $sql = "
                    SELECT 
                        (SELECT COUNT(*) FROM participantes WHERE expositor_id = :expositor_id_sub) as current_count,
                        COALESCE(e.limite_participantes, 0) as limite_participantes,
                        COALESCE(e.nombre_empresa, ex.razon_social) as nombre_empresa
                    FROM expositores ex
                    LEFT JOIN empresas e ON ex.id_empresa = e.id
                    WHERE ex.id = :expositor_id
                ";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([':expositor_id_sub' => $expositor_id, ':expositor_id' => $expositor_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
                if ($result) {
                    $current_count = $result['current_count'];
                    $limit = $result['limite_participantes'];
                    $empresa = $result['nombre_empresa']; // Force company name from DB
    
                    if ($current_count >= $limit) {
                        $_SESSION['error'] = "Cupo de su empresa alcanzado ({$limit} participantes).";
                    } else {
                        // 2. Insert
                        $stmt = $db->prepare("INSERT INTO participantes (nombre_completo, cargo_puesto, empresa, correo, telefono, expositor_id) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$nombre, $cargo, $empresa, $correo, $telefono, $expositor_id]);
                        $_SESSION['message'] = "Participante agregado exitosamente.";
                        
                        // Opcional: Registrar log
                        error_log("Participante agregado: $nombre ($correo) por expositor ID: $expositor_id");
                    }
                } else {
                    $_SESSION['error'] = "Error al obtener datos de la empresa.";
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "Error al agregar participante: " . $e->getMessage();
            }
        }
    } else {
        $_SESSION['error'] = "Todos los campos son obligatorios.";
    }
}
header("Location: ../dashboard.php");
exit;
?>