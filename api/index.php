<?php
/*
 * File: api/index.php (BLANK GUARD)
 * Mencegah directory listing jika mod_autoindex aktif di Apache
 * meskipun Options -Indexes sudah diset di .htaccess.
 * Defense-in-depth: dua lapis proteksi sekaligus.
 */
http_response_code(403);
exit;
?>
