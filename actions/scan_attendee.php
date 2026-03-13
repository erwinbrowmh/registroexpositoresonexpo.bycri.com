<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/Security.php';

header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['expositor_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

$expositor_id = $_SESSION['expositor_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$qr_code = $_POST['qr_code'] ?? '';

if (empty($qr_code)) {
    echo json_encode(['success' => false, 'message' => 'Código QR vacío']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // 1. Find ticket by QR code
    // The QR code in DB is in tickets.codigo_qr
    // We also need user details
    $stmt = $db->prepare("
        SELECT t.id_ticket, t.codigo_qr, t.estatus_pago, u.nombre_completo, u.email, u.empresa, u.puesto, u.telefono, u.estado_provincia, u.ciudad
        FROM tickets t
        JOIN usuarios u ON t.id_usuario = u.id_usuario
        WHERE t.codigo_qr = ?
    ");
    $stmt->execute([$qr_code]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        // Try searching by ID if it's just a number (fallback)
        if (is_numeric($qr_code)) {
            $stmt = $db->prepare("
                SELECT t.id_ticket, t.codigo_qr, t.estatus_pago, u.nombre_completo, u.email, u.empresa, u.puesto, u.telefono, u.estado_provincia, u.ciudad
                FROM tickets t
                JOIN usuarios u ON t.id_usuario = u.id_usuario
                WHERE t.id_ticket = ?
            ");
            $stmt->execute([$qr_code]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Gafete no encontrado']);
        exit;
    }

    // Validate if ticket is canceled
    if ($ticket['estatus_pago'] === 'cancelado') {
        echo json_encode(['success' => false, 'message' => 'Este ticket está CANCELADO y no es válido']);
        exit;
    }

    // 2. Check if already scanned by this expositor
    $stmt = $db->prepare("SELECT id FROM expositor_scans WHERE expositor_id = ? AND id_ticket = ?");
    $stmt->execute([$expositor_id, $ticket['id_ticket']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Este asistente ya fue escaneado anteriormente']);
        exit;
    }

    // 3. Insert scan
    $stmt = $db->prepare("
        INSERT INTO expositor_scans (expositor_id, id_ticket, scan_time)
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$expositor_id, $ticket['id_ticket']]);

    // 4. Return success with data
    echo json_encode([
        'success' => true,
        'message' => 'Escaneo registrado exitosamente',
        'scan' => [
            'time' => date('d/m H:i'),
            'nombre' => $ticket['nombre_completo'],
            'empresa' => $ticket['empresa'],
            'puesto' => $ticket['puesto'],
            'telefono' => $ticket['telefono'] ?? '',
            'estado' => $ticket['estado_provincia'] ?? '',
            'ciudad' => $ticket['ciudad'] ?? '',
            'email' => $ticket['email']
        ]
    ]);

} catch (Exception $e) {
    // Check for duplicate entry exception (redundant check but good for safety)
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo json_encode(['success' => false, 'message' => 'Este asistente ya fue escaneado anteriormente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error del sistema: ' . $e->getMessage()]);
    }
}
?>
