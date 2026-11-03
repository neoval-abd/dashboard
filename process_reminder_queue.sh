#!/usr/bin/env bash
cd "$(dirname "$0")" || exit 1

# Deteksi otomatis lokasi PHP (prioritaskan XAMPP Linux /opt/lampp/bin/php bila tersedia)
if [ -x "/opt/lampp/bin/php" ]; then
    PHP_BIN="/opt/lampp/bin/php"
elif command -v php >/dev/null 2>&1; then
    PHP_BIN="$(command -v php)"
else
    echo "Error: Binary PHP tidak ditemukan di /opt/lampp/bin/php maupun PATH sistem." >&2
    exit 1
fi

while true; do
    printf '[%s] Memeriksa antrean WhatsApp (%s)...\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$PHP_BIN"
    "$PHP_BIN" api/process_reminder_queue.php
    WAIT_SEC=$((270 + RANDOM % 61))
    printf 'Menunggu %d detik sebelum siklus berikutnya...\n' "$WAIT_SEC"
    sleep "$WAIT_SEC"
done
