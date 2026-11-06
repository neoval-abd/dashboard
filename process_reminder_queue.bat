@echo off
setlocal EnableDelayedExpansion
title Service WhatsApp Dashboard - RSU Assalam
cd /d "%~dp0"

:loop
echo [%date% %time%] Memeriksa antrean WhatsApp (Reminder Kontrol ^& Ulang Tahun)...
php api\process_reminder_queue.php

REM Jeda acak manusiawi 600 - 1200 detik (~10 s/d 20 menit) agar pola pengiriman sangat alami & aman dari blokir WhatsApp
set /a WAIT_SEC=600 + (!RANDOM! %% 601)
echo Menunggu !WAIT_SEC! detik sebelum siklus berikutnya...
timeout /t !WAIT_SEC! /nobreak > nul
goto loop
