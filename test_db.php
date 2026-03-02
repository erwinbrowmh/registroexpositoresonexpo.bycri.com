<?php
require_once 'config/db.php';
require_once 'includes/functions.php'; // For check_auth if needed

try {
    $db = Database::getInstance()->getConnection();
    echo "DB Connection: OK\n";

    $tables = ['expositores', 'empresas', 'participantes', 'expositores_imagenes_galeria', 'expositores_videos_galeria'];
    foreach ($tables as $table) {
        try {
            $stmt = $db->query("DESCRIBE $table");
            echo "Table $table: OK\n";
        } catch (PDOException $e) {
            echo "Table $table: FAILED - " . $e->getMessage() . "\n";
        }
    }

    // Check query used in dashboard
    try {
        $expositor_id = 1; // dummy id
        $stmt = $db->prepare("
            SELECT ex.*, COALESCE(e.nombre_empresa, ex.razon_social) as nombre_empresa, e.limite_participantes,
            (SELECT COUNT(*) FROM participantes WHERE expositor_id = ex.id) as total_participantes
            FROM expositores ex
            LEFT JOIN empresas e ON ex.id_empresa = e.id
            WHERE ex.id = ?
        ");
        $stmt->execute([$expositor_id]);
        echo "Dashboard main query: OK\n";
    } catch (PDOException $e) {
        echo "Dashboard main query: FAILED - " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
}
?>