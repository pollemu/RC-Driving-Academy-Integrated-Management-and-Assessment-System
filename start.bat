@echo off
REM RC Driving Academy IMAS start script - uses SQLite, no MySQL required
SETLOCAL
SET "PROJ=C:\xampp\htdocs\RC Driving Academy IMAS"
cd /d "%PROJ%"

REM Set up the database on first run.
IF NOT EXIST "database\database.sqlite" (
    echo Setting up database, running migrate and seed.
    php artisan migrate --seed
) ELSE (
    php artisan migrate --force
)

echo Starting Laravel dev server on http://localhost:8000
ping -n 3 127.0.0.1 >nul
start "" http://localhost:8000
php artisan serve --port=8000
ENDLOCAL
