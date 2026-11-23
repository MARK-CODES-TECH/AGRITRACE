@echo off
echo ========================================================
echo        Starting AgriTrace+ Web and Database Services
echo ========================================================
powershell -Command "Invoke-WmiMethod -Class Win32_Process -Name Create -ArgumentList 'C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini --standalone'; Invoke-WmiMethod -Class Win32_Process -Name Create -ArgumentList 'C:\xampp\apache\bin\httpd.exe'"
echo.
echo Servers successfully launched in background!
echo Opening AgriTrace+ in default browser...
timeout /t 2 >nul
start http://localhost/original/quick_login.php
exit
