<?php
const PROJECT_STATUSES = ['Active', 'Watching', 'Blocked', 'Done', 'Parked'];
const PROJECT_TODO_STATUSES = ['Open', 'Do', 'Ready', 'Done', 'Blocked'];
const PROJECT_ITEM_STATUSES = PROJECT_TODO_STATUSES;
const PROJECT_PRIORITIES = ['', 'Must', 'Should', 'Could', 'Won\'t'];

function project_data_path(): string {
    if (project_demo_mode()) {
        return project_demo_data_path();
    }
    $path = getenv('PROJECT_BOARD_PATH');
    if ($path !== false && trim($path) !== '') {
        return $path;
    }
    throw new RuntimeException('No board path: this copy is demo-only.');
}

function project_activity_path(): string {
    $path = getenv('PROJECT_BOARD_ACTIVITY_PATH');
    if ($path !== false && trim($path) !== '') {
        return $path;
    }
    return dirname(project_data_path()) . '/activity.json';
}

function project_now(): string {
    return gmdate('Y-m-d H:i') . ' UTC';
}

function project_note_stamp_datetime(): string {
    return date('Y-m-d H:i');
}

function project_note_stamp_user(): string {
    $candidates = [
        getenv('PROJECT_BOARD_NOTE_USER') ?: '',
        $_SERVER['REMOTE_USER'] ?? '',
        $_SERVER['PHP_AUTH_USER'] ?? '',
        $_SERVER['AUTH_USER'] ?? '',
    ];
    foreach ($candidates as $candidate) {
        $name = trim((string)$candidate);
        if ($name === '') continue;
        $name = preg_replace('/.*[\\\\\/]/', '', $name) ?? $name;
        $name = preg_replace('/[._-]+/', ' ', $name) ?? $name;
        $name = trim($name);
        if ($name !== '') return ucwords($name);
    }
    return 'Carl';
}

function project_note_stamp_line(?string $user = null): string {
    $name = trim((string)($user ?? project_note_stamp_user()));
    if ($name === '') $name = 'Carl';
    return $name . ' ' . project_note_stamp_datetime();
}
