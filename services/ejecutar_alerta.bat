@echo off
REM =========================================================================
REM Script para ejecutar la verificación de inactividad del Reporte Maestro
REM Programado en Windows Task Scheduler
REM =========================================================================
cd /d "c:\wamp64\www\Progel_coree"
"c:\wamp64\bin\php\php8.0.30\php.exe" "c:\wamp64\www\Progel_coree\services\alerta_inactividad.php" >> "c:\wamp64\www\Progel_coree\services\log_alertas.txt" 2>&1

