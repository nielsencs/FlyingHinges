<?php
function project_backup_dir(): string {
    return project_data_path() . '.backups';
}

/**
 * When a backup was taken, in microseconds. Standard backups carry the time in their name
 * (luke-YYYYMMDD-HHMMSS-<microtime>-<pid>.json); hand-named ones fall back to the file time.
 */
function project_backup_time(string $file): int {
    if (preg_match('/^(?:luke|board)-\d{8}-\d{6}-(\d{16})-\d+\.json$/', basename($file), $m)) {
        return (int)$m[1];
    }
    $mtime = @filemtime($file);
    return $mtime === false ? 0 : $mtime * 1000000;
}

/** Newest first, by when each backup was taken (never by name: hand-named backups sort after dated ones). */
function project_backup_files(): array {
    $files = glob(project_backup_dir() . '/*.json') ?: [];
    $times = [];
    foreach ($files as $file) {
        $times[$file] = project_backup_time($file);
    }
    usort($files, fn($a, $b) => [$times[$b], $b] <=> [$times[$a], $a]);
    return $files;
}

function project_backup_meta_path(string $backup): string {
    return preg_replace('/\.json$/', '.meta', $backup) ?? ($backup . '.meta');
}

/** Who made the save that followed this backup, and what changed. Null for older backups. */
function project_backup_meta(string $backup): ?array {
    $path = project_backup_meta_path($backup);
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode((string)@file_get_contents($path), true);
    return is_array($data) && isset($data['actor']) ? $data : null;
}

function project_latest_backup_meta(): ?array {
    $files = project_backup_files();
    return $files ? project_backup_meta($files[0]) : null;
}

/** Undo is for the user's own changes only: refuse when the agent saved last. */
function project_undo_allowed(): bool {
    $meta = project_latest_backup_meta();
    return $meta === null || (string)$meta['actor'] !== project_agent_name();
}

function project_undo_prompt(): string {
    $meta = project_latest_backup_meta();
    $summary = $meta ? trim((string)($meta['summary'] ?? '')) : '';
    if (!project_undo_allowed()) {
        return 'The latest change was ' . project_agent_name() . "'s" . ($summary !== '' ? ' (' . $summary . ')' : '') . ". Undo only reverses your own changes, so nothing will be undone.";
    }
    return 'Undo your last saved change' . ($summary !== '' ? ': ' . $summary : '') . '?';
}

/** Short description of which items differ between two item lists. */
function project_change_summary(array $oldItems, array $newItems): string {
    $old = [];
    foreach ($oldItems as $item) { $old[(string)($item['id'] ?? '')] = $item; }
    $changed = [];
    foreach ($newItems as $item) {
        $id = (string)($item['id'] ?? '');
        if (!isset($old[$id]) || $old[$id] != $item) {
            $changed[] = $item;
        }
        unset($old[$id]);
    }
    $count = count($changed) + count($old);
    if ($count === 0) {
        return '';
    }
    if ($count === 1 && count($changed) === 1) {
        $title = trim((string)($changed[0]['title'] ?? ''));
        if (function_exists('mb_strimwidth')) { $title = mb_strimwidth($title, 0, 50, '…'); }
        return '#' . (string)$changed[0]['id'] . ($title !== '' ? ' "' . $title . '"' : '');
    }
    return $count . ' items';
}

const PROJECT_MAX_BACKUPS = 50;

/**
 * The one save lock. Every change to LUKE data (browser save, Undo, Restore, agent door)
 * runs inside it, so two writers never interleave. Re-entrant within one request.
 */
function project_with_save_lock(callable $fn) {
    static $depth = 0;
    if ($depth > 0) {
        $depth++;
        try { return $fn(); } finally { $depth--; }
    }
    project_ensure_dir(dirname(project_data_path()));
    $handle = @fopen(project_data_path() . '.lock', 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if ($handle !== false) { fclose($handle); }
        throw new RuntimeException('LUKE could not take its save lock');
    }
    $depth = 1;
    try {
        return $fn();
    } finally {
        $depth = 0;
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function project_ensure_dir(string $dir): void {
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('LUKE could not create its data folder');
    }
}

/** Write a whole file beside $path, checked. Returns the temp path; the caller renames it into place. */
function project_write_temp(string $path, string $bytes, string $tag = 'tmp'): string {
    project_ensure_dir(dirname($path));
    $tmp = $path . '.' . $tag . '.' . getmypid();
    if (@file_put_contents($tmp, $bytes, LOCK_EX) !== strlen($bytes)) {
        @unlink($tmp);
        throw new RuntimeException('LUKE could not write its data (disk full or folder read-only?)');
    }
    return $tmp;
}

function project_rename_into_place(string $tmp, string $path): void {
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('LUKE could not put the new data in place');
    }
}

/** Backs up the current data file. Throws when the backup cannot be written. $keep is never pruned. */
function project_create_backup(string $actor = '', string $summary = '', string $keep = ''): bool {
    $path = project_data_path();
    if (!is_file($path) || filesize($path) === 0) {
        return false;
    }
    $dir = project_backup_dir();
    project_ensure_dir($dir);
    $stamp = gmdate('Ymd-His') . '-' . str_replace('.', '', sprintf('%.6F', microtime(true))) . '-' . getmypid();
    $backup = $dir . '/luke-' . $stamp . '.json';
    if (!@copy($path, $backup) || filesize($backup) !== filesize($path)) {
        @unlink($backup);
        throw new RuntimeException('LUKE could not write a backup, so nothing was saved');
    }
    @chmod($backup, 0660);
    $meta = project_backup_meta_path($backup);
    $metaJson = json_encode(['actor' => $actor !== '' ? $actor : project_note_stamp_user(), 'summary' => $summary], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($metaJson) || @file_put_contents($meta, $metaJson) !== strlen($metaJson)) {
        @unlink($backup);
        @unlink($meta);
        throw new RuntimeException('LUKE could not write a backup, so nothing was saved');
    }
    @chmod($meta, 0660);
    $files = project_backup_files();
    $limit = PROJECT_MAX_BACKUPS;
    if ($keep !== '' && in_array($keep, $files, true)) {
        $files = array_values(array_filter($files, fn($f) => $f !== $keep));
        $limit--;
    }
    foreach (array_slice($files, $limit) as $old) {
        @unlink($old);
        @unlink(project_backup_meta_path($old));
    }
    return true;
}

/**
 * Put a backup in place of the current data: copy it beside the data first (checked),
 * back up the current data (never pruning the source), then rename. Throws on a write failure.
 */
function project_swap_in_backup(string $source, string $tag, string $summary): void {
    $tmp = project_prepare_swap($source, $tag);
    try {
        if (is_file(project_data_path())) {
            project_create_backup('', $summary, $source);
        }
    } catch (Throwable $e) {
        @unlink($tmp);
        throw $e;
    }
    project_rename_into_place($tmp, project_data_path());
    @chmod(project_data_path(), 0660);
}

/** Write a backup's contents beside the data, keeping newer notes and the item counter. Returns the temp path. */
function project_prepare_swap(string $source, string $tag): string {
    $raw = @file_get_contents($source);
    if ($raw === false || $raw === '') {
        throw new RuntimeException('LUKE could not read that backup');
    }
    $path = project_data_path();
    // LUKE #226: notes written since the backup are kept (Notes only ever grow).
    $backup = json_decode($raw, true);
    $current = is_file($path) ? json_decode((string)@file_get_contents($path), true) : null;
    if (is_array($backup) && is_array($current)) {
        $merged = project_keep_newer_notes($backup, $current);
        // LUKE #227: the item counter never goes back, so restored boards cannot reuse numbers.
        $merged['next_id'] = max(project_next_item_id($backup), project_next_item_id($current));
        if ($merged !== $backup) {
            try {
                $raw = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            } catch (JsonException $e) {
                throw new RuntimeException('LUKE could not merge newer notes into that backup', 0, $e);
            }
        }
    }
    $tmp = project_write_temp($path, $raw, $tag);
    @chmod($tmp, 0660);
    return $tmp;
}

/*
 * Undo and Redo (LUKE #229). Undo steps back through the backups: it puts the newest backup
 * in place and moves the board it replaced onto the Redo list (it does not make a new backup,
 * which is what used to make a second Undo flip back). Redo steps forward again and puts a
 * backup back, so Undo can return to it. Any other save or a Restore clears the Redo list.
 */
function project_redo_dir(): string {
    return project_data_path() . '.redo';
}

/** Newest first. */
function project_redo_files(): array {
    $files = glob(project_redo_dir() . '/*.json') ?: [];
    rsort($files, SORT_STRING);
    return $files;
}

function project_redo_available(): bool {
    return project_redo_files() !== [];
}

function project_clear_redo(): void {
    foreach (glob(project_redo_dir() . '/*') ?: [] as $file) {
        @unlink($file);
    }
}

function project_redo_prompt(): string {
    $files = project_redo_files();
    if (!$files) {
        return 'Nothing to redo.';
    }
    $meta = project_backup_meta($files[0]);
    $summary = $meta ? trim((string)($meta['summary'] ?? '')) : '';
    return 'Redo the change you undid' . ($summary !== '' ? ': ' . $summary : '') . '?';
}

function project_restore_latest_backup(): bool {
    return project_with_save_lock(function (): bool {
        $files = project_backup_files();
        if (!$files || !project_undo_allowed()) {
            return false;
        }
        $latest = $files[0];
        $path = project_data_path();
        if (!is_file($latest) || !is_file($path)) {
            return false;
        }
        $meta = project_backup_meta($latest) ?? ['actor' => project_note_stamp_user(), 'summary' => ''];
        $tmp = project_prepare_swap($latest, 'undo');
        // Keep the board being undone on the Redo list (checked), with the change it carries.
        $dir = project_redo_dir();
        // Named by when it was undone, so the most recent Undo is redone first.
        $stamp = str_replace('.', '', sprintf('%.6F', microtime(true))) . '-' . getmypid();
        $redo = $dir . '/redo-' . $stamp . '.json';
        $redoMeta = project_backup_meta_path($redo);
        try {
            project_ensure_dir($dir);
            $metaJson = json_encode(['actor' => (string)$meta['actor'], 'summary' => (string)($meta['summary'] ?? '')], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if (!@copy($path, $redo) || filesize($redo) !== filesize($path)
                || !is_string($metaJson) || @file_put_contents($redoMeta, $metaJson) !== strlen($metaJson)) {
                throw new RuntimeException('LUKE could not keep a Redo copy, so nothing was undone');
            }
            @chmod($redo, 0660);
            @chmod($redoMeta, 0660);
            project_rename_into_place($tmp, $path);
        } catch (Throwable $e) {
            @unlink($tmp);
            @unlink($redo);
            @unlink($redoMeta);
            throw $e;
        }
        @chmod($path, 0660);
        @unlink($latest);
        @unlink(project_backup_meta_path($latest));
        return true;
    });
}

function project_redo_last_change(): bool {
    return project_with_save_lock(function (): bool {
        $files = project_redo_files();
        if (!$files || !is_file(project_data_path())) {
            return false;
        }
        $redo = $files[0];
        $meta = project_backup_meta($redo) ?? ['actor' => '', 'summary' => ''];
        $tmp = project_prepare_swap($redo, 'redo');
        try {
            // Back up the current board as the undo point for this change, as the original save did.
            project_create_backup((string)$meta['actor'], (string)($meta['summary'] ?? ''));
        } catch (Throwable $e) {
            @unlink($tmp);
            throw $e;
        }
        project_rename_into_place($tmp, project_data_path());
        @chmod(project_data_path(), 0660);
        @unlink($redo);
        @unlink(project_backup_meta_path($redo));
        return true;
    });
}


function project_backup_history(): array {
    $rows = [];
    foreach (project_backup_files() as $file) {
        $name = basename($file);
        $time = '';
        if (preg_match('/^(?:luke|board)-(\d{8})-(\d{6})-/', $name, $m)) {
            $dt = DateTime::createFromFormat('YmdHis', $m[1] . $m[2], new DateTimeZone('UTC'));
            if ($dt) {
                $time = $dt->setTimezone(new DateTimeZone('Europe/London'))->format('Y-m-d H:i:s');
            }
        }
        $data = json_decode((string)@file_get_contents($file), true);
        $rows[] = [
            'name' => $name,
            'time' => $time,
            'items' => is_array($data) && isset($data['items']) && is_array($data['items']) ? count($data['items']) : 0,
        ];
    }
    return $rows;
}

function project_restore_backup_by_name(string $name): bool {
    if (!preg_match('/^(?:luke|board)-[0-9A-Za-z-]+\.json$/', $name)) {
        return false;
    }
    $source = project_backup_dir() . '/' . $name;
    if (!is_file($source)) {
        return false;
    }
    $data = json_decode((string)file_get_contents($source), true);
    if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
        return false;
    }
    return project_with_save_lock(function () use ($source): bool {
        if (!is_file($source)) {
            return false;
        }
        project_swap_in_backup($source, 'restore', 'restore from history');
        project_clear_redo();
        return true;
    });
}


function project_audio_dir(): string {
    $dir = dirname(project_data_path()) . '/audio';
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    return $dir;
}

function project_audio_path(string $file): string {
    $safe = basename($file);
    return project_audio_dir() . '/' . $safe;
}

function project_audio_public_name(string $file): string {
    return basename($file);
}

// Parse a single HTTP byte range. null = serve whole file; false = unsatisfiable (416).
function project_parse_byte_range(string $header, int $size) {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $m) || ($m[1] === '' && $m[2] === '')) {
        return null;
    }
    if ($m[1] === '') {
        $len = (int)$m[2];
        if ($len <= 0 || $size <= 0) return false;
        return [max(0, $size - $len), $size - 1];
    }
    $start = (int)$m[1];
    $end = $m[2] === '' ? $size - 1 : min((int)$m[2], $size - 1);
    if ($start >= $size || $start > $end) return false;
    return [$start, $end];
}

function project_audio_response(string $file): void {
    $path = project_audio_path($file);
    if (!is_file($path)) {
        http_response_code(404);
        echo 'Audio not found';
        return;
    }
    $mime = 'audio/webm';
    if (function_exists('mime_content_type')) {
        $detected = mime_content_type($path);
        if (is_string($detected) && str_starts_with($detected, 'audio/')) {
            $mime = $detected;
        }
    }
    $size = (int)filesize($path);
    header('Content-Type: ' . $mime);
    header('Accept-Ranges: bytes');
    header('X-Content-Type-Options: nosniff');
    $range = project_parse_byte_range((string)($_SERVER['HTTP_RANGE'] ?? ''), $size);
    if ($range === false) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        return;
    }
    if ($range === null) {
        header('Content-Length: ' . (string)$size);
        readfile($path);
        return;
    }
    [$start, $end] = $range;
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    header('Content-Length: ' . (string)($end - $start + 1));
    $fh = fopen($path, 'rb');
    fseek($fh, $start);
    $left = $end - $start + 1;
    while ($left > 0 && !feof($fh)) {
        $chunk = fread($fh, min(65536, $left));
        if ($chunk === false || $chunk === '') break;
        echo $chunk;
        $left -= strlen($chunk);
    }
    fclose($fh);
}

function project_attachment_dir(): string {
    $dir = dirname(project_data_path()) . '/attachments';
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    return $dir;
}

function project_attachment_path(string $file): string {
    $safe = basename($file);
    return project_attachment_dir() . '/' . $safe;
}

function project_attachment_public_name(string $file): string {
    return basename($file);
}

function project_attachment_response(string $file): void {
    $path = project_attachment_path($file);
    if (!is_file($path)) {
        http_response_code(404);
        echo 'Attachment not found';
        return;
    }
    $name = project_attachment_public_name($file);
    $mime = str_ends_with(strtolower($name), '.apk') ? 'application/vnd.android.package-archive' : 'application/octet-stream';
    if ($mime === 'application/octet-stream' && function_exists('mime_content_type')) {
        $detected = mime_content_type($path);
        if (is_string($detected) && trim($detected) !== '') {
            $mime = $detected;
        }
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($path));
    header('Content-Disposition: attachment; filename="' . addcslashes($name, "\\\"") . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
}

function project_note_image_dir(): string {
    $dir = dirname(project_data_path()) . '/note-images';
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    return $dir;
}

function project_note_image_name_valid(string $file): bool {
    return (bool)preg_match('/^[a-f0-9]{40}\.(?:png|jpg|gif|webp)$/', $file);
}

function project_note_image_response(string $file): void {
    $path = project_note_image_dir() . '/' . basename($file);
    if (!project_note_image_name_valid($file) || !is_file($path)) {
        http_response_code(404);
        echo 'Image not found';
        return;
    }
    $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    header('Content-Type: ' . $types[pathinfo($file, PATHINFO_EXTENSION)]);
    header('Content-Length: ' . (string)filesize($path));
    header('Cache-Control: private, max-age=31536000, immutable');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
}

/** Move pasted data-URL images out of a note into files; the note keeps a short link. */
function project_note_images_externalise(string $notes): string {
    if (!str_contains($notes, 'data:image/')) {
        return $notes;
    }
    return preg_replace_callback('/<img\b[^>]*>/i', function (array $m): string {
        $tag = $m[0];
        if (!preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/is', $tag, $srcMatch)) {
            return $tag;
        }
        $src = html_entity_decode($srcMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match('~^data:image/(png|jpe?g|gif|webp);base64,([a-z0-9+/=]+)$~i', $src, $img)) {
            return $tag;
        }
        $bytes = base64_decode($img[2], true);
        if ($bytes === false || $bytes === '') {
            return $tag;
        }
        $ext = strtolower($img[1]) === 'jpeg' ? 'jpg' : strtolower($img[1]);
        $name = sha1($bytes) . '.' . $ext;
        $path = project_note_image_dir() . '/' . $name;
        if (!is_file($path)) {
            $tmp = $path . '.tmp.' . getmypid();
            if (@file_put_contents($tmp, $bytes) !== strlen($bytes)) {
                @unlink($tmp);
                return $tag;
            }
            @chmod($tmp, 0660);
            if (!@rename($tmp, $path)) {
                @unlink($tmp);
                return $tag;
            }
        }
        $alt = 'Pasted image';
        if (preg_match('/\balt\s*=\s*(["\'])(.*?)\1/is', $tag, $altMatch) && trim($altMatch[2]) !== '') {
            $alt = trim(html_entity_decode($altMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return '<img src="?noteimage=' . $name . '" alt="' . htmlspecialchars($alt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" loading="lazy" class="note-image">';
    }, $notes) ?? $notes;
}
