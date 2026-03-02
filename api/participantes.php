<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Habilitar reporte de errores para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Participante.php';

// Initialize CORS headers
function set_cors_headers() {
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');    // cache for 1 day
    }

    // Access-Control headers are received during OPTIONS requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");         

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

        exit(0);
    }
}
set_cors_headers();

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(array("message" => "Error de conexión a la base de datos: " . $e->getMessage()));
    exit();
}

$participante = new Participante($db);

$request_method = $_SERVER["REQUEST_METHOD"];

switch ($request_method) {
    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        $errors = [];

        if (empty($data->expositor_id)) {
            $errors[] = "El ID del expositor es requerido.";
        }
        if (empty($data->nombre_completo)) {
            $errors[] = "El nombre completo es requerido.";
        }
        if (empty($data->cargo_puesto)) {
            $errors[] = "El cargo o puesto es requerido.";
        }

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(array("message" => "Errores de validación:", "errors" => $errors));
            exit();
        }

        $participante->expositor_id = htmlspecialchars(strip_tags($data->expositor_id));
        $participante->nombre_completo = htmlspecialchars(strip_tags($data->nombre_completo));
        $participante->cargo_puesto = htmlspecialchars(strip_tags($data->cargo_puesto));

        if ($participante->create()) {
            http_response_code(201);
            echo json_encode(array("message" => "Participante creado exitosamente.", "id" => $participante->id));
        } else {
            http_response_code(500);
            echo json_encode(array(
                "message" => "Error al crear el participante.",
                "error" => $participante->error,
                "code" => $participante->errorCode
            ));
        }
        break;

    case 'GET':
        if (isset($_GET['id'])) {
            $participante->id = $_GET['id'];
            if ($participante->readOne()) {
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
        } else {
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
                echo json_encode(array("message" => "No se encontraron participantes."));
            }
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));
        $errors = [];

        if (empty($data->id)) {
            $errors[] = "El ID del participante es requerido para la actualización.";
        }
        if (empty($data->expositor_id)) {
            $errors[] = "El ID del expositor es requerido.";
        }
        if (empty($data->nombre_completo)) {
            $errors[] = "El nombre completo es requerido.";
        }
        if (empty($data->cargo_puesto)) {
            $errors[] = "El cargo o puesto es requerido.";
        }

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(array("message" => "Errores de validación:", "errors" => $errors));
            exit();
        }

        $participante->id = $data->id;
        $participante->expositor_id = htmlspecialchars(strip_tags($data->expositor_id));
        $participante->nombre_completo = htmlspecialchars(strip_tags($data->nombre_completo));
        $participante->cargo_puesto = htmlspecialchars(strip_tags($data->cargo_puesto));

        if ($participante->update()) {
            http_response_code(200);
            echo json_encode(array("message" => "Participante actualizado exitosamente."));
        } else {
            http_response_code(500);
            echo json_encode(array(
                "message" => "Error al actualizar el participante.",
                "error" => $participante->error,
                "code" => $participante->errorCode
            ));
        }
        break;

    case 'DELETE':
        if (isset($_GET['id'])) {
            $participante->id = $_GET['id'];
            if ($participante->delete()) {
                http_response_code(200);
                echo json_encode(array("message" => "Participante eliminado exitosamente."));
            } else {
                http_response_code(500);
                echo json_encode(array("message" => "Error al eliminar el participante."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "No se pudo eliminar el participante. Falta el ID."));
        }
        break;

    case 'OPTIONS':
        // Handled by set_cors_headers()
        break;

    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method not allowed."));
        break;
}
?>