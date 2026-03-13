<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Error Interno del Servidor | ONEXPO 2026</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #002B5C;
            --secondary-color: #6c757d;
            --accent-color: #0056b3;
            --danger-color: #dc3545;
            --light-bg: #f8f9fa;
        }
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow-x: hidden;
        }
        .error-container {
            text-align: center;
            padding: 3rem;
            max-width: 600px;
            width: 90%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1;
            border-top: 5px solid var(--danger-color);
            animation: shake 0.5s ease-in-out;
        }
        @keyframes shake {
            0% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
            100% { transform: translateX(0); }
        }
        .error-code {
            font-size: 8rem;
            font-weight: 800;
            color: var(--danger-color);
            line-height: 1;
            margin-bottom: 0;
            text-shadow: 2px 2px 0px rgba(0,0,0,0.05);
            background: linear-gradient(135deg, var(--danger-color), #ff6b6b);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .error-title {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 1rem;
        }
        .error-message {
            color: var(--secondary-color);
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .btn-retry {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 43, 92, 0.3);
        }
        .btn-retry:hover {
            background-color: var(--accent-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 43, 92, 0.4);
            color: white;
        }
        .logo-img {
            max-width: 200px;
            margin-bottom: 2rem;
            height: auto;
        }
        .icon-bg {
            position: absolute;
            font-size: 15rem;
            color: rgba(220, 53, 69, 0.05);
            z-index: -1;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(10deg);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <i class="fas fa-server icon-bg"></i>
        
        <img src="/assets/img/ONEXPO+LOGO+EVENTO-02.webp" alt="ONEXPO 2026" class="logo-img">
        
        <h1 class="error-code">500</h1>
        <h2 class="error-title">Error Interno del Servidor</h2>
        <p class="error-message">
            Ups, algo salió mal de nuestro lado. Estamos trabajando para solucionarlo.
            <br>Por favor intenta recargar la página en unos momentos.
        </p>
        
        <div class="d-flex justify-content-center gap-3">
            <a href="javascript:location.reload()" class="btn btn-retry text-decoration-none">
                <i class="fas fa-sync-alt me-2"></i> Reintentar
            </a>
            <a href="/" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-semibold" style="padding-top: 12px;">
                <i class="fas fa-home me-2"></i> Ir al Inicio
            </a>
        </div>
        
        <div class="mt-4 pt-3 border-top">
            <small class="text-muted">Si el problema persiste, contacta a soporte técnico.</small>
        </div>
    </div>
</body>
</html>
