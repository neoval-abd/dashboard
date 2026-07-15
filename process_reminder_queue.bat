@echo off
title Service WhatsApp Dashboard
cd /d C:\Apache24\htdocs\dashboard
:loop
echo [%date% %time%] Memeriksa antrean WhatsApp...
php api\process_reminder_queue.php
echo Menunggu 5 menit...
timeout /t 300 /nobreak > nul
goto loop
