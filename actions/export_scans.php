<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/SimpleXLSXGen.php';

session_start();

if (!isset($_SESSION['expositor_id'])) {
    header("Location: ../index.php");
    exit;
}

$expositor_id = $_SESSION['expositor_id'];

try {
    $db = Database::getInstance()->getConnection();

    // Fetch all scans
    $stmt = $db->prepare("
        SELECT es.scan_time, u.nombre_completo, u.empresa, u.puesto, u.email, u.telefono, u.estado_provincia, u.ciudad
        FROM expositor_scans es
        JOIN tickets t ON es.id_ticket = t.id_ticket
        JOIN usuarios u ON t.id_usuario = u.id_usuario
        WHERE es.expositor_id = ?
        ORDER BY es.scan_time DESC
    ");
    $stmt->execute([$expositor_id]);
    $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare data for Excel
    $data = [
        ['Fecha y Hora', 'Nombre', 'Empresa', 'Puesto', 'Teléfono', 'Estado', 'Ciudad', 'Email'] // Header row
    ];

    foreach ($scans as $scan) {
        $data[] = [
            $scan['scan_time'],
            $scan['nombre_completo'],
            $scan['empresa'],
            $scan['puesto'],
            $scan['telefono'] ?? '',
            $scan['estado_provincia'] ?? '',
            $scan['ciudad'] ?? '',
            $scan['email']
        ];
    }

    // Generate and download XLSX
    $filename = 'escaneos_gafete_' . date('Y-m-d_H-i') . '.xlsx';
    Shuchkin\SimpleXLSXGen::fromArray($data)->downloadAs($filename);
    exit;

} catch (Exception $e) {
    echo "Error al exportar: " . $e->getMessage();
}
