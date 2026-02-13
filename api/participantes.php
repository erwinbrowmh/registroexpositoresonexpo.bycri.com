<?php
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Habilitar reporte de errores para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Participante.php';
require_once __DIR__ . '/../models/Expositor.php';

set_cors_headers();

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(array("message" => "Error de conexión a la base de datos: " . $e->getMessage()));
    exit();
}

$request_method = $_SERVER["REQUEST_METHOD"];
$participante = new Participante();
$expositor = new Expositor();

switch ($request_method) {
    case 'POST':
        // Crear un nuevo participante
        $data = (object)$_POST; // Data comes from URLSearchParams, so it's in $_POST
        $errors = [];

        // Validate required fields
        if (empty($data->expositor_id)) {
            $errors[] = "El ID del expositor es requerido.";
        } elseif (!filter_var($data->expositor_id, FILTER_VALIDATE_INT)) {
            $errors[] = "El ID del expositor debe ser un número entero válido.";
        }
        
        // Map field names from form (nombre, cargo, correo)
        $nombre = isset($data->nombre) ? $data->nombre : (isset($data->nombre_completo) ? $data->nombre_completo : '');
        $cargo = isset($data->cargo) ? $data->cargo : (isset($data->cargo_puesto) ? $data->cargo_puesto : '');

        if (empty($nombre)) {
            $errors[] = "El nombre completo es requerido.";
        }
        if (empty($cargo)) {
            $errors[] = "El cargo o puesto es requerido.";
        }

        // If there are any validation errors, return them
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(array("message" => "Errores de validación:", "errors" => $errors));
            exit();
        }

        // Check if expositor_id exists
        $expositor->id = $data->expositor_id;
        $expositor->readOne();
        if (empty($expositor->nombre)) { 
            http_response_code(404);
            echo json_encode(array("message" => "Expositor no encontrado."));
            exit();
        }

        // Proceed if no validation errors
        $participante->expositor_id = htmlspecialchars(strip_tags($data->expositor_id));
        $participante->nombre_completo = htmlspecialchars(strip_tags($nombre));
        $participante->cargo_puesto = htmlspecialchars(strip_tags($cargo));

        if ($participante->create()) {
            http_response_code(201);
            echo json_encode(array("message" => "Participante creado exitosamente."));
        } else {
            http_response_code(500); // Internal Server Error for database operation failure
            echo json_encode(array(
                "message" => "Error al crear el participante en la base de datos.",
                "error" => $participante->error
            ));
        }
        break;

    case 'GET':
        // Leer participantes o un participante específico
        if (isset($_GET['id'])) {
            // Leer un solo participante
            $participante->id = $_GET['id'];
            $participante->readOne();

            if ($participante->nombre_completo != null) {
                $participante_arr = array(
                    "id" => $participante->id,
                    "expositor_id" => $participante->expositor_id,
                    "nombre_completo" => $participante->nombre_completo,
                    "cargo_puesto" => $participante->cargo_puesto,
                    "created_at" => $participante->created_at,
                    "updated_at" => $participante->updated_at
                );
                http_response_code(200);
                echo json_encode($participante_arr);
            } else {
                http_response_code(404);
                echo json_encode(array("message" => "Participante no encontrado."));
            }
        } elseif (isset($_GET['expositor_id'])) {
            // Leer todos los participantes de un expositor
            $participante->expositor_id = $_GET['expositor_id'];
            $stmt = $participante->read();
            $num = $stmt->rowCount();

            if ($num > 0) {
                $participantes_arr = array();
                $participantes_arr["records"] = array();

                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    extract($row);
                    $participante_item = array(
                        "id" => $id,
                        "expositor_id" => $expositor_id,
                        "nombre_completo" => $nombre_completo,
                        "cargo_puesto" => $cargo_puesto,
                        "created_at" => $created_at,
                        "updated_at" => $updated_at
                    );
                    array_push($participantes_arr["records"], $participante_item);
                }
                http_response_code(200);
                echo json_encode($participantes_arr);
            } else {
                http_response_code(404);
                echo json_encode(array("message" => "No se encontraron participantes para este expositor."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Falta el parámetro 'id' o 'expositor_id' para la solicitud GET."));
        }
        break;

    case 'PUT':
        // Actualizar un participante
        parse_str(file_get_contents("php://input"), $_PUT); // Parse URL-encoded data for PUT
        $data = (object)$_PUT;
        $errors = [];

        // Validate required fields
        if (empty($data->id)) {
            $errors[] = "El ID del participante es requerido para la actualización.";
        } elseif (!filter_var($data->id, FILTER_VALIDATE_INT)) {
            $errors[] = "El ID del participante debe ser un número entero válido.";
        }
        if (empty($data->expositor_id)) {
            $errors[] = "El ID del expositor es requerido.";
        } elseif (!filter_var($data->expositor_id, FILTER_VALIDATE_INT)) {
            $errors[] = "El ID del expositor debe ser un número entero válido.";
        }

        // Map field names from form (nombre, cargo, correo)
        $nombre = isset($data->nombre) ? $data->nombre : (isset($data->nombre_completo) ? $data->nombre_completo : '');
        $cargo = isset($data->cargo) ? $data->cargo : (isset($data->cargo_puesto) ? $data->cargo_puesto : '');

        if (empty($nombre)) {
            $errors[] = "El nombre completo es requerido.";
        }
        if (empty($cargo)) {
            $errors[] = "El cargo o puesto es requerido.";
        }

        // If there are any validation errors, return them
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(array("message" => "Errores de validación:", "errors" => $errors));
            exit();
        }

        // Check if expositor_id exists
        $expositor->id = $data->expositor_id;
        $expositor->readOne();
        if (empty($expositor->nombre)) { 
            http_response_code(404);
            echo json_encode(array("message" => "Expositor no encontrado."));
            exit();
        }

        // Proceed if no validation errors
        $participante->id = htmlspecialchars(strip_tags($data->id));
        $participante->expositor_id = htmlspecialchars(strip_tags($data->expositor_id));
        $participante->nombre_completo = htmlspecialchars(strip_tags($nombre));
        $participante->cargo_puesto = htmlspecialchars(strip_tags($cargo));

        if ($participante->update()) {
            http_response_code(200);
            echo json_encode(array("message" => "Participante actualizado exitosamente."));
        } else {
            http_response_code(500); // Internal Server Error for database operation failure
            echo json_encode(array("message" => "Error al actualizar el participante en la base de datos."));
        }
        break;

    case 'DELETE':
        // Eliminar un participante
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id)) {
            $participante->id = $data->id;

            if ($participante->delete()) {
                http_response_code(200);
                echo json_encode(array("message" => "Participante eliminado exitosamente."));
            } else {
                http_response_code(500); // Internal Server Error for database operation failure
                echo json_encode(array("message" => "Error al eliminar el participante de la base de datos."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "No se pudo eliminar el participante. Falta el ID."));
        }
        break;

    case 'OPTIONS':
        // Manejar la solicitud OPTIONS para CORS
        http_response_code(200);
        break;

    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method not allowed."));
        break;
}
