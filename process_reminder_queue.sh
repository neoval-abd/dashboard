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
    # Jeda acak 10 s/d 20 menit (600 s/d 1200 detik) agar pola pengiriman sangat alami & aman dari blokir WhatsApp
    WAIT_SEC=$((600 + RANDOM % 601))
    WAIT_MIN=$((WAIT_SEC / 60))
    WAIT_REM=$((WAIT_SEC % 60))
    printf 'Menunggu %d detik (%d menit %d detik) sebelum siklus berikutnya...\n' "$WAIT_SEC" "$WAIT_MIN" "$WAIT_REM"
    sleep "$WAIT_SEC"
done
