@echo off
TITLE NFC Bridge Service - Onexpo 2026
SETLOCAL EnableDelayedExpansion

echo ======================================================
echo       SERVICIO DE PUENTE NFC (PYTHON BRIDGE)
echo ======================================================
echo.

:: 1. Verificar si Python está instalado
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Python no esta instalado o no esta en el PATH.
    echo Por favor, instala Python desde https://www.python.org/
    pause
    exit /b
)

:: 2. Verificar/Instalar dependencias necesarias
echo [1/2] Verificando dependencias...
echo.

:: Lista de dependencias
set DEPS=pyscard websockets

for %%d in (%DEPS%) do (
    python -c "import %%d" >nul 2>&1
    if !errorlevel! neq 0 (
        echo [INFO] Instalando dependencia faltante: %%d...
        python -m pip install %%d
        if !errorlevel! neq 0 (
            echo [ERROR] No se pudo instalar %%d. Verifica tu conexion a internet.
            pause
            exit /b
        )
    ) else (
        echo [OK] %%d ya esta instalada.
    )
)

echo.
echo [2/2] Iniciando el Bridge NFC...
echo ------------------------------------------------------
echo Presiona CTRL+C para detener el servicio.
echo ------------------------------------------------------
echo.

:: 3. Ejecutar el bridge
python bridge.py

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] El servicio se detuvo inesperadamente (Codigo: %errorlevel%).
    echo Asegurate de que ningun otro programa este usando el puerto 3000.
    pause
)

ENDLOCAL
