@echo off
echo.
echo ==========================================
echo   Heliora Landing Page — Deploy
echo ==========================================
echo.

:: Accept optional commit message as argument, default to timestamp
set MSG=%~1
if "%MSG%"=="" set MSG=Update site %date% %time%

cd /d "%~dp0"

git add .
git commit -m "%MSG%"
git push origin main

echo.
echo ==========================================
echo   Pushed! GitHub Actions will auto-deploy
echo   to Namecheap in ~30 seconds.
echo   Check: https://github.com/YOUR-USERNAME/heliora-landing/actions
echo ==========================================
echo.
pause
