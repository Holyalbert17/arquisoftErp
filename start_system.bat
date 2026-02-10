@echo off
title Sistema Arquisoft - Launcher
color 0A
cls
echo ====================================================
echo      INICIANDO SISTEMA ARQUISOFT
echo ====================================================
echo.
cd /d "%~dp0"
echo Abriendo navegador...
start "" "http://localhost/arquisoft_system/public/dashboard"
echo.
echo Sistema iniciado. CRM deshabilitado.
pause
