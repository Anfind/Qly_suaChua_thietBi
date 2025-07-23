@echo off
echo Copying updated files to web server...

REM Thường web server local ở: 
REM - XAMPP: C:\xampp\htdocs\
REM - WAMP: C:\wamp64\www\
REM - Laragon: C:\laragon\www\

echo.
echo Please check where your web server is running:
echo 1. XAMPP: Usually C:\xampp\htdocs\
echo 2. WAMP: Usually C:\wamp64\www\
echo 3. Laragon: Usually C:\laragon\www\
echo 4. Other location

echo.
set /p webroot="Enter your web root path (e.g., C:\xampp\htdocs\): "

if not exist "%webroot%" (
    echo Error: Web root path does not exist!
    pause
    exit /b 1
)

set target=%webroot%\Qly_suaChua_thietBi

echo.
echo Creating target directory: %target%
if not exist "%target%" mkdir "%target%"

echo.
echo Copying main files...
copy "index.php" "%target%\" >nul
copy "dashboard.php" "%target%\" >nul
copy "debug_csrf.php" "%target%\" >nul
copy "debug_session.php" "%target%\" >nul
copy "force_clear_session.php" "%target%\" >nul
copy "test_login.php" "%target%\" >nul

echo Copying config files...
if not exist "%target%\config" mkdir "%target%\config"
copy "config\*.php" "%target%\config\" >nul

echo Copying utils files...
if not exist "%target%\utils" mkdir "%target%\utils"
copy "utils\*.php" "%target%\utils\" >nul

echo Copying models files...
if not exist "%target%\models" mkdir "%target%\models"
copy "models\*.php" "%target%\models\" >nul

echo Copying auth files...
if not exist "%target%\auth" mkdir "%target%\auth"
copy "auth\*.php" "%target%\auth\" >nul

echo Copying layouts files...
if not exist "%target%\layouts" mkdir "%target%\layouts"
copy "layouts\*.php" "%target%\layouts\" >nul

echo Copying assets files...
if not exist "%target%\assets" mkdir "%target%\assets"
if not exist "%target%\assets\css" mkdir "%target%\assets\css"
copy "assets\css\*.css" "%target%\assets\css\" >nul

echo.
echo ✅ Files copied successfully!
echo.
echo You can now test the application at:
echo http://localhost/Qly_suaChua_thietBi/
echo.
echo Debug URLs:
echo http://localhost/Qly_suaChua_thietBi/debug_csrf.php
echo http://localhost/Qly_suaChua_thietBi/debug_session.php
echo http://localhost/Qly_suaChua_thietBi/force_clear_session.php
echo.
pause
