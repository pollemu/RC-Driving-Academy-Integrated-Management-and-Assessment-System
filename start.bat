@echo off
REM RC Driving Academy IMAS - start script (uses SQLite, no MySQL required)
SETLOCAL
SET "PROJ=C:\xampp\htdocs\RC Driving Academy IMAS"
cd /d "%PROJ%"

IF NOT DEFINED PORT SET "PORT=8000"

REM Create the SQLite database file if it does not exist yet.
IF NOT EXIST "database\database.sqlite" (
    echo Creating SQLite database file...
    type nul > "database\database.sqlite"
)

REM Set up schema + seed data on first run; otherwise just apply new migrations.
IF NOT EXIST "database\database.sqlite.migrated" (
    echo First run: migrating and seeding the database...
    php artisan migrate --seed --force
    IF %ERRORLEVEL%==0 echo. > "database\database.sqlite.migrated"
) ELSE (
    echo Applying pending migrations...
    php artisan migrate --force
)

REM Clear all caches so code/config changes always take effect.
php artisan optimize:clear

echo.
echo Starting Laravel dev server on http://localhost:%PORT%
echo Press Ctrl+C in the server window to stop.
echo.
start "" "http://localhost:%PORT%/frontend/index.html"
php artisan serve --port=%PORT%

ENDLOCAL
