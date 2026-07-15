#!/usr/bin/env bash
cd "$(dirname "$0")" || exit 1

while true; do
    printf '[%s] Memeriksa antrean WhatsApp...\n' "$(date '+%Y-%m-%d %H:%M:%S')"
    php api/process_reminder_queue.php
    sleep 300
done
