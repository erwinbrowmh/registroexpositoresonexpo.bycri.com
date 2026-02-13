<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Participante.php';
require_once __DIR__ . '/../assets/lib/dompdf-3.1.4/dompdf-3.1.4/src/Dompdf.php';
require_once __DIR__ . '/../assets/lib/dompdf-3.1.4/dompdf-3.1.4/src/Options.php';
require_once __DIR__ . '/../assets/lib/dompdf-3.1.4/dompdf-3.1.4/lib/Cpdf.php';

use Dompdf\Dompdf;
use Dompdf\Options;

set_cors_headers();

$request_method = $_SERVER["REQUEST_METHOD"];

if ($request_method === 'GET') {
    if (isset($_GET['participante_id'])) {
        $participante_id = $_GET['participante_id'];

        $participante = new Participante();
        $participante->id = $participante_id;
        $participante->readOne();

        if ($participante->nombre_completo != null) {
            // Instantiate Dompdf with options
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $dompdf = new Dompdf($options);

            // HTML content for the badge
            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>Gafete de Participante</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
                    .badge {
                        width: 300px;
                        height: 200px;
                        border: 1px solid #ccc;
                        margin: 20px auto;
                        text-align: center;
                        padding: 20px;
                        box-sizing: border-box;
                        background-color: #f9f9f9;
                        border-radius: 10px;
                        display: flex;
                        flex-direction: column;
                        justify-content: center;
                        align-items: center;
                    }
                    .badge h1 {
                        font-size: 2em;
                        margin: 0;
                        color: #333;
                    }
                    .badge p {
                        font-size: 1.2em;
                        color: #666;
                    }
                    .logo {
                        max-width: 100px;
                        margin-bottom: 10px;
                    }
                </style>
            </head>
            <body>
                <div class="badge">
                    <img src="https://registroexpositoresonexpo.bycri.com/assets/img/onexpo_logo.png" alt="Logo Onexpo" class="logo">
                    <h1>' . htmlspecialchars($participante->nombre_completo) . '</h1>
                    <p>Participante</p>
                </div>
            </body>
            </html>';

            $dompdf->loadHtml($html);

            // (Optional) Setup the paper size and orientation
            $dompdf->setPaper('A7', 'portrait'); // A7 is a small size suitable for badges

            // Render the HTML as PDF
            $dompdf->render();

            // Output the generated PDF to Browser
            $dompdf->stream("gafete_" . str_replace(' ', '_', $participante->nombre_completo) . ".pdf", array("Attachment" => false));
            exit();

        } else {
            http_response_code(404);
            echo json_encode(array("message" => "Participante no encontrado."));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "ID de participante no proporcionado."));
    }
} else {
    http_response_code(405);
    echo json_encode(array("message" => "Método no permitido."));
}
?>