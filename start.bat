@echo off
REM RC Driving Academy IMAS - start script (SQLite, no MySQL required)
SETLOCAL
SET "PROJ=C:\xampp\htdocs\RC Driving Academy IMAS"

cd /d "%PROJ%"

REM Ensure the database exists and is up to date (creates database/database.sqlite on first run).
IF NOT EXIST "database\database.sqlite" (
    echo Setting up database (migrate + seed)...
    php artisan migrate --seed
) ELSE (
    php artisan migrate --force
)

REM Start the Laravel dev server
echo Starting Laravel on http://localhost:8000 ...
start "" http://localhost:8000
php artisan serve --port=8000
ENDLOCAL
