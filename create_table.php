<?php
require_once __DIR__ . '/config/db.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS expositor_scans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        expositor_id INT NOT NULL,
        id_ticket INT NOT NULL,
        scan_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_scan (expositor_id, id_ticket),
        FOREIGN KEY (expositor_id) REFERENCES expositores(id),
        FOREIGN KEY (id_ticket) REFERENCES tickets(id_ticket)
    )";
    
    $db->exec($sql);
    echo "Table 'expositor_scans' created successfully.";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>