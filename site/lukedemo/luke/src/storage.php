<?php
function project_backup_dir(): string {
    return project_data_path() . '.backups';
}

function project_backup_files(): array {
    $files = glob(project_backup_dir() . '/*.json') ?: [];
    rsort($files, SORT_STRING);
    return $files;
}

function project_create_backup(): bool {
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
    $files = project_backup_files();
    foreach (array_slice($files, 50) as $old) {
        @unlink($old);
    }
    return true;
}

function project_restore_latest_backup(): bool {
    $files = project_backup_files();
    if (!$files) {
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
        project_create_backup();
    }
    $tmp = $path . '.undo.' . getmypid();
    if (!copy($latest, $tmp)) {
        return false;
    }
    @chmod($tmp, 0660);
    rename($tmp, $path);
    @chmod($path, 0660);
    @unlink($latest);
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
        project_create_backup();
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
