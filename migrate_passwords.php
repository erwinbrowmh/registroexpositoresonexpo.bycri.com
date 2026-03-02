<?php
require_once 'config/db.php'; // Adjust path as necessary
require_once 'lib/Security.php'; // Assuming Security class might be useful, though password_hash is native

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Iniciando migración de contraseñas...\n";

    // 1. Obtener usuarios y contraseñas en texto plano de la tabla transplano
    // Asumimos que 'usuario' es el campo para identificar al usuario y 'contrasena' es la contraseña en texto plano.
    // Si el campo de identificación es 'correo', ajusta la consulta.
    $stmt_select = $db->query("SELECT usuario, contrasena FROM transplano");
    $plain_text_users = $stmt_select->fetchAll(PDO::FETCH_ASSOC);

    if (empty($plain_text_users)) {
        echo "No se encontraron contraseñas en la tabla 'transplano'.\n";
        exit;
    }

    $migrated_count = 0;
    foreach ($plain_text_users as $user_data) {
        $username = $user_data['usuario'];
        $plain_password = $user_data['contrasena'];

        // Generar hash bcrypt
        $hashed_password = password_hash($plain_password, PASSWORD_BCRYPT);

        // 2. Actualizar la tabla expositores con la contraseña hasheada
        // Asumimos que 'usuario' es el campo para identificar al usuario en la tabla 'expositores'
        // y 'acceso' es el campo donde se guarda la contraseña hasheada.
        // Si el campo de identificación es 'correo', ajusta la consulta.
        $stmt_update = $db->prepare("UPDATE expositores SET acceso = ? WHERE usuario = ?");
        $stmt_update->execute([$hashed_password, $username]);

        if ($stmt_update->rowCount() > 0) {
            echo "Contraseña migrada para el usuario: " . $username . "\n";
            $migrated_count++;
        } else {
            echo "Advertencia: No se pudo actualizar la contraseña para el usuario: " . $username . " (posiblemente no encontrado en 'expositores').\n";
        }
    }

    echo "Migración completada. Total de contraseñas migradas: " . $migrated_count . "\n";

} catch (PDOException $e) {
    echo "Error de base de datos: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error general: " . $e->getMessage() . "\n";
}
?>