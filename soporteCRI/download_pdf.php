<?php
// Start output buffering to prevent accidental output
ob_start();

require_once __DIR__ . '/config/db.php';

// Enable error reporting for debugging (temporary for troubleshooting)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check for TCPDF library in multiple possible locations
$tcpdfPaths = [
    __DIR__ . '/../lib/TCPDF-main/TCPDF-main/tcpdf.php', // Local structure
    __DIR__ . '/../lib/TCPDF/tcpdf.php',                 // Common production structure
    __DIR__ . '/../lib/tcpdf/tcpdf.php'                  // Lowercase structure
];

$tcpdfPath = null;
foreach ($tcpdfPaths as $path) {
    if (file_exists($path)) {
        $tcpdfPath = $path;
        break;
    }
}

if (!$tcpdfPath) {
    // Log error and show message
    error_log("TCPDF library not found in any expected location.");
    die("Error crítico: Librería TCPDF no encontrada. Contacte al administrador.");
}

require_once $tcpdfPath;
require_once __DIR__ . '/../lib/Security.php';

if (PHP_SESSION_NONE === session_status()) {
    session_start();
}

if (!isset($_SESSION['soporte_logged_in']) || $_SESSION['soporte_logged_in'] !== true) {
    // Check for auto-login if session is missing
    $auto_login_success = false;
    if (isset($_COOKIE['soporte_remember_me'])) {
        $parts = explode(':', $_COOKIE['soporte_remember_me']);
        if (count($parts) === 2) {
            list($selector, $validator) = $parts;
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT * FROM soporte_tokens WHERE selector = ? AND expiry > NOW()");
                $stmt->execute([$selector]);
                $token = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($token && hash_equals($token['hashed_validator'], hash('sha256', $validator))) {
                    session_regenerate_id(true);
                    $_SESSION['soporte_logged_in'] = true;
                    $_SESSION['last_activity'] = time();
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $auto_login_success = true;
                }
            } catch (Exception $e) {
                // Ignore DB errors
            }
        }
    }
    
    if (!$auto_login_success) {
        header('Location: index.php');
        exit;
    }
}

// Validate Session Security
if (!Security::validateSession()) {
    header('Location: logout.php');
    exit;
}

$id = $_GET['id'] ?? null;

if (!$id) {
    die("ID de expositor no proporcionado.");
}

// Validate ID is integer
if (!filter_var($id, FILTER_VALIDATE_INT)) {
    die("ID inválido.");
}

$db = Database::getInstance()->getConnection();

try {
    // Get Exhibitor Details
    $stmt = $db->prepare("
        SELECT 
            e.*, 
            COALESCE(em.nombre_empresa, 'Sin Empresa') as empresa_nombre 
        FROM expositores e 
        LEFT JOIN empresas em ON e.id_empresa = em.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $expositor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$expositor) {
        die("Expositor no encontrado.");
    }

} catch (Exception $e) {
    die("Error al cargar datos: " . $e->getMessage());
}

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Soporte CRI');
$pdf->SetTitle('Detalle Expositor - ' . $expositor['nombre']);
$pdf->SetSubject('Información del Expositor');

// Set default header data
$pdf->SetHeaderData('', 0, 'Detalle de Expositor', 'Generado por Soporte CRI - ' . date('d/m/Y H:i'));

// Set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();

// Content
$html = '
<h1>' . htmlspecialchars($expositor['nombre'] . ' ' . $expositor['apellido']) . '</h1>
<h3>' . htmlspecialchars($expositor['empresa_nombre']) . '</h3>
<hr>
<table cellpadding="5">
    <tr>
        <td><strong>Cargo:</strong> ' . htmlspecialchars($expositor['cargo']) . '</td>
        <td><strong>Email:</strong> ' . htmlspecialchars($expositor['correo']) . '</td>
    </tr>
    <tr>
        <td><strong>Teléfono:</strong> ' . htmlspecialchars($expositor['telefono']) . '</td>
        <td><strong>WhatsApp:</strong> ' . htmlspecialchars($expositor['whatsapp']) . '</td>
    </tr>
    <tr>
        <td><strong>Stand:</strong> ' . htmlspecialchars($expositor['stand']) . '</td>
        <td><strong>Tipo Stand:</strong> ' . htmlspecialchars($expositor['tipo_stand']) . '</td>
    </tr>
    <tr>
        <td><strong>Rótulo Antepecho:</strong> ' . htmlspecialchars($expositor['rotulo_antepecho']) . '</td>
        <td><strong>Requiere Mampara:</strong> ' . ($expositor['requiere_mampara'] ? 'Sí' : 'No') . '</td>
    </tr>
</table>
<br>
<h4>Descripción Breve</h4>
<p>' . nl2br(htmlspecialchars($expositor['descripcion_breve'])) . '</p>
<br>
<h4>Redes Sociales</h4>
<ul>
    <li>Website: ' . htmlspecialchars($expositor['website']) . '</li>
    <li>Facebook: ' . htmlspecialchars($expositor['facebook']) . '</li>
    <li>Twitter: ' . htmlspecialchars($expositor['twitter']) . '</li>
    <li>LinkedIn: ' . htmlspecialchars($expositor['linkedin']) . '</li>
    <li>Instagram: ' . htmlspecialchars($expositor['instagram']) . '</li>
</ul>
<br>
<h4>Archivos Multimedia (Enlaces)</h4>
<ul>';

if ($expositor['logo_ruta']) {
    $html .= '<li>Logo: <a href="' . htmlspecialchars($expositor['logo_ruta']) . '">' . htmlspecialchars($expositor['logo_ruta']) . '</a></li>';
}
if ($expositor['banner_ruta']) {
    $html .= '<li>Banner: <a href="' . htmlspecialchars($expositor['banner_ruta']) . '">' . htmlspecialchars($expositor['banner_ruta']) . '</a></li>';
}
if ($expositor['video_promocional_ruta']) {
    $html .= '<li>Video Promocional: <a href="' . htmlspecialchars($expositor['video_promocional_ruta']) . '">' . htmlspecialchars($expositor['video_promocional_ruta']) . '</a></li>';
}

$html .= '</ul>';

$pdf->writeHTML($html, true, false, true, false, '');

// Clean any previous output (whitespace, warnings) before generating PDF
if (ob_get_length()) ob_clean();

// Close and output PDF document
$pdf->Output('Expositor_' . $id . '.pdf', 'I');
?>
