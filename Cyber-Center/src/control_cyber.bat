@echo off
setlocal enabledelayedexpansion

REM ============================================================
REM control_cyber.bat (versión robusta)
REM ============================================================
set SERVER_URL=http://TU_SERVIDOR/Cyber-Center/src
set MAX_FAILURES=3
set FAIL_COUNT=0

:bucle
REM Consultar al servidor con timeout y capturar código de salida
set RESPONSE=
for /f "usebackq delims=" %%A in (`powershell -NoProfile -Command "try { (Invoke-WebRequest -UseBasicParsing -Uri '%SERVER_URL%/verificar_estado.php' -TimeoutSec 5).Content } catch { Write-Host 'ERROR' }"`) do set RESPONSE=%%A

REM Verificar si hubo error de red o timeout
echo !RESPONSE! | findstr /C:"ERROR" >nul
if !ERRORLEVEL!==0 (
    set /a FAIL_COUNT+=1
    if !FAIL_COUNT! geq !MAX_FAILURES! (
        REM Tras varios fallos, bloqueamos por precaución
        msg * "SERVIDOR NO RESPONDE. Por seguridad, la sesión se cerrará en 15 segundos. Guarde su trabajo."
        timeout /t 15 /nobreak >nul
        rem shutdown /l
    ) else (
        REM Reintentar después de 10 segundos
        timeout /t 10 /nobreak >nul
        goto :bucle
    )
) else (
    set FAIL_COUNT=0
    REM Verificar si la respuesta contiene "bloquear"
    echo !RESPONSE! | findstr /C:"\"accion\":\"bloquear\"" >nul
    if !ERRORLEVEL!==0 (
        REM Mostrar motivo si está disponible
        for /f "usebackq delims=" %%M in (`powershell -NoProfile -Command "$r = '!RESPONSE!' | ConvertFrom-Json; Write-Host $r.motivo"`) do set MOTIVO=%%M
        msg * "Atención: Su sesión finalizará. Motivo: !MOTIVO! La sesión se cerrará en 15 segundos. Guarde su trabajo."
        timeout /t 15 /nobreak >nul
        rem shutdown /l
    )
)

REM Esperar 30 segundos entre consultas
timeout /t 30 /nobreak >nul
goto :bucle