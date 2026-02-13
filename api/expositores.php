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
require_once __DIR__ . '/../models/Expositor.php';

// Define upload directory
$upload_dir = __DIR__ . '/../assets/uploads/';

// Ensure the upload directory exists
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true); // Create directory with more restrictive permissions
}

// Initialize CORS headers
set_cors_headers();

try {
    $database = Database::getInstance();
    $db = $database->getConnection();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(array("message" => "Error de conexión a la base de datos: " . $e->getMessage()));
    exit();
}

$expositor = new Expositor();

$request_method = $_SERVER["REQUEST_METHOD"];

// Helper function to handle file uploads
function handleFileUpload($file_key, $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'], $allowed_mimes = [], $max_size = 5 * 1024 * 1024) {
    global $upload_dir;
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        $error_code = isset($_FILES[$file_key]['error']) ? $_FILES[$file_key]['error'] : 'N/A';
        return ['success' => false, 'message' => "Error al subir el archivo '{$file_key}'. Código de error: {$error_code}."];
    }

    $file = $_FILES[$file_key];
    $file_name = $file['name'];
    $file_tmp_name = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];
    // $file_type = $file['type']; // This is client-provided and unreliable

    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Validate file extension
    if (!in_array($file_ext, $allowed_extensions)) {
        error_log("Invalid file extension for $file_key: $file_ext");
        return ['success' => false, 'message' => "Tipo de archivo no permitido. Extensiones permitidas: " . implode(', ', $allowed_extensions) . "."];
    }

    // Validate file size
    if ($file_size > $max_size) {
        error_log("File size too large for $file_key: $file_size bytes");
        return ['success' => false, 'message' => "El archivo es demasiado grande. Tamaño máximo permitido: " . ($max_size / (1024 * 1024)) . " MB."];
    }

    // Check for upload errors
    if ($file_error !== UPLOAD_ERR_OK) {
        error_log("File upload error for $file_key: $file_error");
        return ['success' => false, 'message' => "Error al subir el archivo. Código de error: " . $file_error . "."];
    }

    // Stricter MIME type checking
    if (!empty($allowed_mimes)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_tmp_name);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_mimes)) {
            error_log("Invalid MIME type for $file_key: $mime_type");
            return ['success' => false, 'message' => "Tipo de contenido de archivo no permitido. MIME types permitidos: " . implode(', ', $allowed_mimes) . "."];
        }
    }

    // Generate a unique filename
    $new_file_name = uniqid('', true) . '.' . $file_ext;
    $destination = $upload_dir . $new_file_name;

    // Move the uploaded file
    if (move_uploaded_file($file_tmp_name, $destination)) {
        return ['success' => true, 'path' => 'assets/uploads/' . $new_file_name]; // Return the relative path
    } else {
        error_log("Failed to move uploaded file for $file_key to $destination");
        return ['success' => false, 'message' => "No se pudo mover el archivo subido a su destino final."];
    }
}

switch ($request_method) {
    case 'POST':
        // For file uploads, data might come from $_POST and files from $_FILES
        // If content-type is application/json, file_get_contents("php://input") is used.
        // If content-type is multipart/form-data, $_POST and $_FILES are used.
        // We need to handle both cases or expect multipart/form-data for uploads.
        // For simplicity, let's assume multipart/form-data when files are expected.

        $data = (object)$_POST;
        $errors = [];

        // Validate required fields
        if (empty($data->nombre)) {
            $errors[] = "El nombre es requerido.";
        }
        if (empty($data->apellido)) {
            $errors[] = "El apellido es requerido.";
        }
        if (empty($data->correo)) {
            $errors[] = "El correo es requerido.";
        }
        if (empty($data->razon_social)) {
            $errors[] = "La razón social es requerida.";
        }

        // Validate email format
        if (!empty($data->correo) && !filter_var($data->correo, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "El formato del correo electrónico no es válido.";
        }

        // Basic phone number validation
        if (!empty($data->telefono) && !preg_match('/^[0-9\s\-\(\)]+$/', $data->telefono)) {
            $errors[] = "El formato del número de teléfono no es válido.";
        }

        // Validate mampara and rotulo_antepecho
        $mampara_val = isset($data->mampara) && ($data->mampara === '1' || $data->mampara === true);
        if ($mampara_val && empty($data->rotulo_antepecho)) {
            $errors[] = "El rótulo del antepecho es requerido si se solicita mampara.";
        }

        // If there are any validation errors, return them
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(array("message" => "Errores de validación:", "errors" => $errors));
            exit();
        }

        // Proceed if no validation errors
        $expositor->nombre = htmlspecialchars(strip_tags($data->nombre));
        $expositor->apellido = htmlspecialchars(strip_tags($data->apellido));
        $expositor->correo = htmlspecialchars(strip_tags($data->correo));
        $expositor->telefono = isset($data->telefono) ? htmlspecialchars(strip_tags($data->telefono)) : '';
        $expositor->razon_social = htmlspecialchars(strip_tags($data->razon_social));
        $expositor->cargo_contacto = isset($data->cargo_contacto) ? htmlspecialchars(strip_tags($data->cargo_contacto)) : '';
        $expositor->giro_empresa = isset($data->giro_empresa) ? htmlspecialchars(strip_tags($data->giro_empresa)) : '';
        $expositor->mampara = $mampara_val;
        $expositor->rotulo_antepecho = isset($data->rotulo_antepecho) ? htmlspecialchars(strip_tags($data->rotulo_antepecho)) : '';

        // Handle logo upload
        $logo_upload_result = handleFileUpload('logo', ['jpg', 'jpeg', 'png'], ['image/jpeg', 'image/png']);
        if (!$logo_upload_result['success']) {
            http_response_code(400);
            echo json_encode(array("message" => $logo_upload_result['message']));
            exit();
        }
        $expositor->logo_ruta = $logo_upload_result['path'];

        // Handle liability form upload
        $hoja_responsiva_upload_result = handleFileUpload('hoja_responsiva', ['pdf'], ['application/pdf']);
        if (!$hoja_responsiva_upload_result['success']) {
            http_response_code(400);
            echo json_encode(array("message" => $hoja_responsiva_upload_result['message']));
            exit();
        }
        $expositor->hoja_responsiva_ruta = $hoja_responsiva_upload_result['path'];

        if ($expositor->create()) {
            $expositorId = $expositor->id; // Get the ID of the newly created expositor

            // Handle participants data
            if (isset($data->participantes) && !empty($data->participantes)) {
                $participants_data = json_decode($data->participantes, true); // Decode JSON string to array

                if (json_last_error() !== JSON_ERROR_NONE) {
                    http_response_code(400);
                    echo json_encode(array("message" => "Error al decodificar los datos de los participantes: " . json_last_error_msg()));
                    exit();
                }

                require_once __DIR__ . '/../models/Participante.php';
                $participante = new Participante();

                foreach ($participants_data as $p_data) {
                    $participante->expositor_id = $expositorId;
                    $participante->nombre_completo = htmlspecialchars(strip_tags($p_data['nombre_completo']));
                    $participante->cargo_puesto = htmlspecialchars(strip_tags($p_data['cargo_puesto']));

                    if (!$participante->create()) {
                        // Log error but continue with other participants or decide to rollback
                        error_log("Error al crear participante para expositor ID {$expositorId}: " . $participante->error);
                        // Optionally, you might want to delete the expositor and previously created participants here
                        // to ensure atomicity, but for now, we'll just log and continue.
                    }
                }
            }

            http_response_code(201);
            echo json_encode(array("message" => "Expositor y participantes creados exitosamente.", "expositor_id" => $expositorId));
        } else {
            http_response_code(500); // Internal Server Error for database operation failure
            echo json_encode(array(
                "message" => "Error al crear el expositor en la base de datos.",
                "error" => $expositor->error,
                "code" => $expositor->errorCode // Add PDO error code
            ));
        }
        break;

    case 'GET':
        if (isset($_GET['id'])) {
            $expositor->id = $_GET['id'];
            if ($expositor->readOne()) {
                $expositor_arr = array(
                    "id" => $expositor->id,
                    "nombre" => $expositor->nombre,
                    "apellido" => $expositor->apellido,
                    "correo" => $expositor->correo,
                    "telefono" => $expositor->telefono,
                    "razon_social" => $expositor->razon_social,
                    "cargo_contacto" => $expositor->cargo_contacto,
                    "giro_empresa" => $expositor->giro_empresa,
                    "logo_ruta" => $expositor->logo_ruta,
                    "mampara" => $expositor->mampara,
                    "rotulo_antepecho" => $expositor->rotulo_antepecho,
                    "hoja_responsiva_ruta" => $expositor->hoja_responsiva_ruta,
                    "created_at" => $expositor->created_at,
                    "updated_at" => $expositor->updated_at
                );
                http_response_code(200);
                echo json_encode($expositor_arr);
            } else {
                http_response_code(404);
                echo json_encode(array("message" => "Expositor no encontrado."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Se requiere un ID de expositor para buscar."));
        }
        break;

    case 'PUT':
        // For PUT requests, data can come via JSON (for non-file fields)
        // and files via $_FILES if multipart/form-data is used with method override.
        $data = json_decode(file_get_contents("php://input"));
        $errors = [];

        // If data is not an object, it means JSON parsing failed or no JSON was sent.
        // In this case, we might try to get data from $_POST if it's a multipart/form-data request
        // with a method override.
        if ($data === null && !empty($_POST)) {
            $data = (object)$_POST;
        } elseif ($data === null) {
            http_response_code(400);
            echo json_encode(array("message" => "Datos inválidos o incompletos para la actualización."));
            exit();
        }

        // Validate required fields
        if (empty($data->id)) {
            $errors[] = "El ID del expositor es requerido para la actualización.";
        }
        if (empty($data->nombre)) {
            $errors[] = "El nombre es requerido.";
        }
        if (empty($data->apellido)) {
            $errors[] = "El apellido es requerido.";
        }
        if (empty($data->correo)) {
            $errors[] = "El correo es requerido.";
        }
        if (empty($data->razon_social)) {
            $errors[] = "La razón social es requerida.";
        }

        // Validate email format
        if (!empty($data->correo) && !filter_var($data->correo, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "El formato del correo electrónico no es válido.";
        }

        // Basic phone number validation
        if (!empty($data->telefono) && !preg_match('/^[0-9\s\-\(\)]+$/', $data->telefono)) {
            $errors[] = "El formato del número de teléfono no es válido.";
        }

        // Validate mampara and rotulo_antepecho
        $mampara_val = isset($data->mampara) && ($data->mampara === '1' || $data->mampara === true);
        if ($mampara_val && empty($data->rotulo_antepecho)) {
            $errors[] = "El rótulo del antepecho es requerido si se solicita mampara.";
        }

        // If there are any validation errors, return them
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(array("message" => "Errores de validación:", "errors" => $errors));
            exit();
        }

        // Proceed if no validation errors
        $expositor->id = $data->id;
        $expositor->nombre = htmlspecialchars(strip_tags($data->nombre));
        $expositor->apellido = htmlspecialchars(strip_tags($data->apellido));
        $expositor->correo = htmlspecialchars(strip_tags($data->correo));
        $expositor->telefono = isset($data->telefono) ? htmlspecialchars(strip_tags($data->telefono)) : '';
        $expositor->razon_social = htmlspecialchars(strip_tags($data->razon_social));
        $expositor->cargo_contacto = isset($data->cargo_contacto) ? htmlspecialchars(strip_tags($data->cargo_contacto)) : '';
        $expositor->giro_empresa = isset($data->giro_empresa) ? htmlspecialchars(strip_tags($data->giro_empresa)) : '';
        $expositor->mampara = $mampara_val;
        $expositor->rotulo_antepecho = isset($data->rotulo_antepecho) ? htmlspecialchars(strip_tags($data->rotulo_antepecho)) : '';

        // Fetch existing expositor data to retain current file paths if not updated
        $expositor->readOne(); // This populates existing logo_ruta and hoja_responsiva_ruta

        // Handle logo upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logo_upload_result = handleFileUpload('logo', ['jpg', 'jpeg', 'png'], ['image/jpeg', 'image/png']);
            if (!$logo_upload_result['success']) {
                http_response_code(400);
                echo json_encode(array("message" => $logo_upload_result['message']));
                exit();
            }
            // Delete old logo if it exists and is different from the new one
            if ($expositor->logo_ruta && file_exists($expositor->logo_ruta) && $expositor->logo_ruta !== $logo_upload_result['path']) {
                unlink($expositor->logo_ruta);
            }
            $expositor->logo_ruta = $logo_upload_result['path'];
        } else {
            // If no new file is uploaded, retain the existing one from the database
            // The readOne() call earlier already populated $expositor->logo_ruta
        }

        // Handle liability form upload
        if (isset($_FILES['hoja_responsiva']) && $_FILES['hoja_responsiva']['error'] === UPLOAD_ERR_OK) {
            $hoja_responsiva_upload_result = handleFileUpload('hoja_responsiva', ['pdf'], ['application/pdf']);
            if (!$hoja_responsiva_upload_result['success']) {
                http_response_code(400);
                echo json_encode(array("message" => $hoja_responsiva_upload_result['message']));
                exit();
            }
            // Delete old liability form if it exists and is different from the new one
            if ($expositor->hoja_responsiva_ruta && file_exists($expositor->hoja_responsiva_ruta) && $expositor->hoja_responsiva_ruta !== $hoja_responsiva_upload_result['path']) {
                unlink($expositor->hoja_responsiva_ruta);
            }
            $expositor->hoja_responsiva_ruta = $hoja_responsiva_upload_result['path'];
        } else {
            // If no new file is uploaded, retain the existing one from the database
            // The readOne() call earlier already populated $expositor->hoja_responsiva_ruta
        }

        if ($expositor->update()) {
            http_response_code(200);
            echo json_encode(array("message" => "Expositor actualizado exitosamente."));
        } else {
            http_response_code(500); // Internal Server Error for database operation failure
            echo json_encode(array("message" => "Error al actualizar el expositor en la base de datos."));
        }
        break;

    case 'DELETE':
        if (isset($_GET['id'])) {
            $expositor->id = $_GET['id'];
            // Before deleting the expositor, delete associated files
            $expositor->readOne(); // Read to get file paths
            if ($expositor->logo_ruta && file_exists($expositor->logo_ruta)) {
                unlink($expositor->logo_ruta);
            }
            if ($expositor->hoja_responsiva_ruta && file_exists($expositor->hoja_responsiva_ruta)) {
                unlink($expositor->hoja_responsiva_ruta);
            }

            if ($expositor->delete()) {
                http_response_code(200);
                echo json_encode(array("message" => "Expositor eliminado exitosamente."));
            } else {
                http_response_code(500); // Internal Server Error for database operation failure
                echo json_encode(array("message" => "Error al eliminar el expositor de la base de datos."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "No se pudo eliminar el expositor. Falta el ID."));
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
