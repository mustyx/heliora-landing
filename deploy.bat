@echo off
echo.
echo  Deploying Heliora Landing Page...
echo  ------------------------------------
cd /d "%~dp0"
git add .
set /p msg="  Describe your changes: "
git commit -m "%msg%"
git push origin main
echo.
echo  Done! Now go to cPanel Git Version Control and click Update.
echo  https://ap.www.namecheap.com
echo.
pause
