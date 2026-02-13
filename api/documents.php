<?php
require_once __DIR__ . '/../config/database.php'; // For CORS headers

set_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(); // Handle preflight OPTIONS request
}

if (!isset($_GET['file'])) {
    http_response_code(400);
    echo json_encode(array("message" => "File parameter is missing."));
    exit();
}

$file_name = basename($_GET['file']); // Use basename to prevent directory traversal
$file_path = __DIR__ . '/../assets/documents/' . $file_name;

// Check if file exists
if (!file_exists($file_path)) {
    http_response_code(404);
    echo json_encode(array("message" => "File not found."));
    exit();
}

// Check if file is readable
if (!is_readable($file_path)) {
    http_response_code(403);
    echo json_encode(array("message" => "File is not readable."));
    exit();
}

// Set headers for download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));

// Clear output buffer
ob_clean();
flush();

// Read the file and output it to the browser
readfile($file_path);
exit();
