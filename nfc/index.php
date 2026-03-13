<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>NFC Admin - Onexpo 2026</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body { background: #f4f6f9; }
        .nfc-panel { border-radius: 12px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        #log-box { background: #1a1a1a; color: #00ff41; height: 100px; overflow-y: auto; padding: 8px; font-family: monospace; font-size: 11px; }
        .scroll-map { max-height: 450px; overflow-y: auto; border: 1px solid #ddd; background: #fff; }
        .table-hex { font-size: 0.75rem; margin-bottom: 0; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="card nfc-panel">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-dark"><i class="fas fa-satellite-dish text-primary mr-2"></i> NFC Bridge ACR122U</h5>
            <button id="btnConnect" class="btn btn-outline-primary btn-sm">Conectar</button>
        </div>
        <div class="card-body">
            <div id="mainView" style="display:none;">
                <div class="row">
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded mb-3">
                            <h6>Estado del Chip</h6>
                            <p class="small mb-1"><strong>UID:</strong> <span id="lbl-uid">--</span></p>
                            <p class="small mb-1"><strong>Chip:</strong> <span id="lbl-chip">--</span></p>
                            <p class="small"><strong>Memoria:</strong> <span id="lbl-pages">0</span> págs.</p>
                        </div>
                        <div class="form-group">
                            <label class="small font-weight-bold">Nuevo Mensaje:</label>
                            <textarea id="txtInput" class="form-control form-control-sm mb-2" rows="6" placeholder="Escribe aquí..."></textarea>
                            <button id="btnWrite" class="btn btn-primary btn-sm btn-block">Grabar en Chip</button>
                        </div>
                        <button id="btnReset" class="btn btn-danger btn-sm btn-block mt-4">
                            <i class="fas fa-eraser mr-1"></i> Reinicio de Fábrica
                        </button>
                        <p class="x-small text-muted mt-2" style="font-size: 10px;">* El reinicio habilita compatibilidad con teléfonos.</p>
                    </div>
                    <div class="col-md-8">
                        <label class="font-weight-bold small text-uppercase">Texto Recuperado</label>
                        <div class="bg-white border rounded p-2 mb-3" style="min-height: 42px; white-space: pre-wrap; word-break: break-word;">
                            <div id="lbl-text" class="small text-dark">--</div>
                        </div>
                        <label class="font-weight-bold small text-uppercase">Volcado de Memoria (ASCII/HEX)</label>
                        <div id="memoryContainer" class="scroll-map">
                            <p class="text-center py-5 text-muted">Esperando lectura...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="log-box"></div>
    </div>
</div>

<div class="modal fade" id="modalWait" data-backdrop="static"><div class="modal-dialog modal-dialog-centered text-center"><div class="modal-content p-4"><div class="spinner-border text-primary mx-auto mb-2"></div><h6 id="waitTitle">Procesando...</h6></div></div></div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="app.js"></script>
</body>
</html>
