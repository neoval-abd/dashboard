@echo off
setlocal EnableDelayedExpansion
title Service WhatsApp Dashboard - RSU Assalam
cd /d "%~dp0"

:loop
echo [%date% %time%] Memeriksa antrean WhatsApp (Reminder Kontrol ^& Ulang Tahun)...
php api\process_reminder_queue.php

REM Jeda acak manusiawi 270 - 330 detik (~4.5 s/d 5.5 menit) agar aman dari deteksi spam WhatsApp
set /a WAIT_SEC=270 + (!RANDOM! %% 61)
echo Menunggu !WAIT_SEC! detik sebelum siklus berikutnya...
timeout /t !WAIT_SEC! /nobreak > nul
goto loop
