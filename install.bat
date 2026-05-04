@echo off
echo Installing Dependencies for Seminar Management System
echo ===================================================

REM Check if composer is available
composer --version >nul 2>&1
if %errorlevel% == 0 (
    echo Composer found. Installing dependencies...
    composer require phpmailer/phpmailer
    composer require fpdf/fpdf
    echo Dependencies installed successfully!
) else (
    echo Composer not found. Please install Composer first:
    echo 1. Download from: https://getcomposer.org/Composer-Setup.exe
    echo 2. Run the installer
    echo 3. Restart this script
    echo.
    echo Alternative: Manual download instructions in install_dependencies.md
)

pause
