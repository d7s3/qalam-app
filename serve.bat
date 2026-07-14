@echo off
cd /d "%~dp0"

start "altag-server" cmd /k "cd /d %~dp0public && php -S 127.0.0.1:8000 ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php"
start "altag-vite" cmd /k "cd /d %~dp0 && npm run dev"
start "altag-queue" cmd /k "cd /d %~dp0 && php artisan queue:listen --tries=1 --timeout=0"

echo Started server (http://127.0.0.1:8000), Vite, and the queue listener in separate windows.
echo Close those windows (or Ctrl+C in each) to stop them.
