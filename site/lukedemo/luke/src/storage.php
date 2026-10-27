<?php
function project_backup_dir(): string {
    return project_data_path() . '.backups';
}

function project_backup_files(): array {
    $files = glob(project_backup_dir() . '/*.json') ?: [];
    rsort($files, SORT_STRING);
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

function project_create_backup(string $actor = '', string $summary = ''): bool {
    $path = project_data_path();
    if (!is_file($path) || filesize($path) === 0) {
        return false;
    }
    $dir = project_backup_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    $stamp = gmdate('Ymd-His') . '-' . str_replace('.', '', sprintf('%.6F', microtime(true))) . '-' . getmypid();
    $backup = $dir . '/board-' . $stamp . '.json';
    if (!copy($path, $backup)) {
        return false;
    }
    @chmod($backup, 0660);
    $meta = project_backup_meta_path($backup);
    file_put_contents($meta, json_encode(['actor' => $actor !== '' ? $actor : project_note_stamp_user(), 'summary' => $summary], JSON_UNESCAPED_SLASHES));
    @chmod($meta, 0660);
    $files = project_backup_files();
    foreach (array_slice($files, 50) as $old) {
        @unlink($old);
        @unlink(project_backup_meta_path($old));
    }
    return true;
}

function project_restore_latest_backup(): bool {
    $files = project_backup_files();
    if (!$files || !project_undo_allowed()) {
        return false;
    }
    $path = project_data_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    $latest = $files[0];
    if (!is_file($latest)) {
        return false;
    }
    if (is_file($path)) {
        project_create_backup('', 'undo');
    }
    $tmp = $path . '.undo.' . getmypid();
    if (!copy($latest, $tmp)) {
        return false;
    }
    @chmod($tmp, 0660);
    rename($tmp, $path);
    @chmod($path, 0660);
    @unlink($latest);
    @unlink(project_backup_meta_path($latest));
    return true;
}


function project_backup_history(): array {
    $rows = [];
    foreach (project_backup_files() as $file) {
        $name = basename($file);
        $time = '';
        if (preg_match('/^board-(\d{8})-(\d{6})-/', $name, $m)) {
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
    if (!preg_match('/^board-[0-9A-Za-z-]+\.json$/', $name)) {
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
    $path = project_data_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    if (is_file($path)) {
        project_create_backup('', 'restore from history');
    }
    $tmp = $path . '.restore.' . getmypid();
    if (!copy($source, $tmp)) {
        return false;
    }
    @chmod($tmp, 0660);
    rename($tmp, $path);
    @chmod($path, 0660);
    return true;
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
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
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
            if (file_put_contents($tmp, $bytes) === false) {
                return $tag;
            }
            @chmod($tmp, 0660);
            rename($tmp, $path);
        }
        $alt = 'Pasted image';
        if (preg_match('/\balt\s*=\s*(["\'])(.*?)\1/is', $tag, $altMatch) && trim($altMatch[2]) !== '') {
            $alt = trim(html_entity_decode($altMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return '<img src="?noteimage=' . $name . '" alt="' . htmlspecialchars($alt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" loading="lazy" class="note-image">';
    }, $notes) ?? $notes;
}
