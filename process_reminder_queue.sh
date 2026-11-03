#!/usr/bin/env bash
cd "$(dirname "$0")" || exit 1

while true; do
    printf '[%s] Memeriksa antrean WhatsApp (Reminder Kontrol & Ulang Tahun)...\n' "$(date '+%Y-%m-%d %H:%M:%S')"
    php api/process_reminder_queue.php
    WAIT_SEC=$((270 + RANDOM % 61))
    printf 'Menunggu %d detik sebelum siklus berikutnya...\n' "$WAIT_SEC"
    sleep "$WAIT_SEC"
done
 