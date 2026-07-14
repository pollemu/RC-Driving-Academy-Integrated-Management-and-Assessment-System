@echo off
REM RC Driving Academy IMAS - start script
SETLOCAL
SET "PROJ=C:\xampp\htdocs\RC Driving Academy IMAS"
SET "MYSQLBIN=C:\xampp\mysql\bin"

cd /d "%PROJ%"

REM Start MySQL if not already running
netstat -an | findstr ":3306" >nul
IF ERRORLEVEL 1 (
    echo Starting MySQL...
    start "" "%MYSQLBIN%\mysqld.exe"
    timeout /t 5 >nul
) ELSE (
    echo MySQL already running.
)

REM Start Laravel dev server
echo Starting Laravel on http://localhost:8000 ...
start "" http://localhost:8000
php artisan serve --port=8000
ENDLOCAL
