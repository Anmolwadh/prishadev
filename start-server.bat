@echo off
title Prisha Enterprises - Local Server
cd /d "%~dp0"

if exist "C:\xampp\php\php.exe" (
  set PHP=C:\xampp\php\php.exe
) else (
  set PHP=C:\Users\admin\AppData\Local\Programs\php\php.exe
)

set MYSQLD=C:\Program Files\MariaDB 12.3\bin\mysqld.exe
set MYSQL=C:\Program Files\MariaDB 12.3\bin\mysql.exe
set USERDATA=C:\Users\admin\mariadb-data
set ORIGDATA=C:\Program Files\MariaDB 12.3\data

if not exist "%PHP%" (
  echo [ERROR] PHP not found.
  pause
  exit /b 1
)

if not exist "%USERDATA%\ibdata1" (
  echo Preparing MariaDB data folder...
  mkdir "%USERDATA%" >nul 2>&1
  xcopy "%ORIGDATA%\*" "%USERDATA%\" /E /I /Y /Q
)

echo [mysqld]> "%USERDATA%\my.ini"
echo datadir=C:/Users/admin/mariadb-data>> "%USERDATA%\my.ini"
echo port=3306>> "%USERDATA%\my.ini"
echo innodb_buffer_pool_size=256M>> "%USERDATA%\my.ini"
echo [client]>> "%USERDATA%\my.ini"
echo port=3306>> "%USERDATA%\my.ini"

rem Check if MariaDB/MySQL is already listening on port 3306
powershell -NoProfile -Command "$client = New-Object System.Net.Sockets.TcpClient; try { $client.Connect('127.0.0.1', 3306); $client.Connected; $client.Close() } catch { exit 1 }" >nul 2>&1
if %ERRORLEVEL% equ 0 (
  echo [OK] MariaDB is already running on port 3306.
) else (
  echo Starting MariaDB...
  start "" /MIN "%MYSQLD%" --defaults-file="%USERDATA%\my.ini"
  powershell -NoProfile -Command "Start-Sleep -Seconds 3"
)

rem Automatically open default browser after server initializes
start "" powershell -NoProfile -Command "Start-Sleep -Seconds 2; Start-Process 'http://127.0.0.1:8080/'"

echo.
echo ========================================================
echo    Prisha Enterprises Local Server is Running!
echo ========================================================
echo.
echo    Website URL: http://127.0.0.1:8080/
echo    Admin URL:   http://127.0.0.1:8080/admin/login.php
echo.
echo    [NOTE] Keep this window OPEN while using the website.
echo    Closing this window will stop the PHP server.
echo ========================================================
echo.

"%PHP%" -S 127.0.0.1:8080 -t .
pause

