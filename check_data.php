<?php
require_once __DIR__ . '/config/db.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Check stand_scans:\n";
    $scans = $db->query("SELECT * FROM stand_scans LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    print_r($scans);
    
    echo "\nCheck tickets QR:\n";
    $tickets = $db->query("SELECT id_ticket, codigo_qr FROM tickets LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    print_r($tickets);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>