<?php
require_once __DIR__ . '/config/db.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if table exists
    $tables = $db->query("SHOW TABLES LIKE 'expositor_scans'")->fetchAll();
    
    if (count($tables) == 0) {
        $sql = "CREATE TABLE expositor_scans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            expositor_id INT NOT NULL,
            id_ticket INT NOT NULL,
            scan_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_scan (expositor_id, id_ticket),
            FOREIGN KEY (expositor_id) REFERENCES expositores(id),
            FOREIGN KEY (id_ticket) REFERENCES tickets(id_ticket)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $db->exec($sql);
        echo "Table 'expositor_scans' created successfully.\n";
    } else {
        echo "Table 'expositor_scans' already exists.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
