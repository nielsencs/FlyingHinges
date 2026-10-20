<?php
function project_field(array $input, string $key, string $default = ''): string {
    return trim((string)($input[$key] ?? $default));
}

function project_normalise_notes_text(string $notes): string {
    $notes = str_replace(["\r\n", "\r"], "\n", $notes);
    $notes = preg_replace('/<br\s*\/?>[ \t]*\n?/i', "\n", $notes) ?? $notes;
    $notes = preg_replace('/[ \t]+\n/', "\n", $notes) ?? $notes;
    $notes = preg_replace('/\n[ \t]+/', "\n", $notes) ?? $notes;
    $notes = preg_replace('/\n{3,}/', "\n\n", $notes) ?? $notes;
    return trim($notes);
}

function project_note_entry_has_stamp(string $entry): bool {
    return preg_match('/^[A-Z][A-Za-z]*(?: [A-Z][A-Za-z]*)* \d{4}-\d{2}-\d{2} \d{2}:\d{2}\b/', ltrim($entry)) === 1;
}

function project_stamp_appended_note_text(string $oldNotes, string $newNotes): string {
    $old = project_normalise_notes_text($oldNotes);
    $new = project_normalise_notes_text($newNotes);
    if ($new === '' || $new === $old || $old === '') {
        return $new;
    }
    if (!str_starts_with($new, $old)) {
        return $new;
    }
    $appended = substr($new, strlen($old));
    if ($appended === false || trim($appended) === '') {
        return $new;
    }
    if (!preg_match('/^\s*\n/', $appended)) {
        return $new;
    }
    $entry = trim($appended);
    if (project_note_entry_has_stamp($entry)) {
        return $new;
    }
    return rtrim($old) . "\n\n" . project_note_stamp_line() . "\n" . $entry;
}

function project_add_section(array $board, array $input): array {
    $name = project_field($input, 'name', 'New area');
    $base = project_slug($name);
    $slug = $base;
    $used = project_section_slugs($board);
    $n = 2;
    while (in_array($slug, $used, true)) {
        $slug = $base . '-' . $n;
        $n++;
    }
    $now = project_now();
    $board['sections'][] = [
        'slug' => $slug,
        'name' => $name,
        'summary' => project_field($input, 'summary'),
        'updated_at' => $now,
    ];
    return project_normalise_board($board);
}

function project_update_section(array $board, array $input): array {
    $slug = project_field($input, 'slug');
    foreach ($board['sections'] as &$section) {
        if (($section['slug'] ?? '') === $slug) {
            $section['name'] = project_field($input, 'name', (string)($section['name'] ?? ''));
            $section['summary'] = project_field($input, 'summary', (string)($section['summary'] ?? ''));
            $section['updated_at'] = project_now();
        }
    }
    unset($section);
    return project_normalise_board($board);
}

function project_delete_section(array $board, array $input): array {
    $slug = project_field($input, 'slug');
    $target = project_field($input, 'reassign_section');
    if ($slug === '' || $target === '' || $slug === $target || !in_array($target, project_section_slugs($board), true)) {
        return project_normalise_board($board);
    }
    $board['sections'] = array_values(array_filter($board['sections'] ?? [], fn($section) => (string)($section['slug'] ?? '') !== $slug));
    $now = project_now();
    foreach ($board['projects'] as &$project) {
        if ((string)($project['section'] ?? '') === $slug) {
            $project['section'] = $target;
            $project['updated_at'] = $now;
        }
    }
    unset($project);
    foreach ($board['items'] as &$todo) {
        if ((string)($todo['section'] ?? '') === $slug) {
            $todo['section'] = $target;
            $todo['changed_at'] = $now;
            $todo['updated_at'] = $now;
        }
    }
    unset($todo);
    return project_normalise_board($board);
}

function project_add_project(array $board, array $input): array {
    $name = project_field($input, 'name', 'New focus');
    if (strcasecmp($name, 'General') === 0) {
        return project_normalise_board($board);
    }
    $base = project_slug($name);
    $slug = $base;
    $used = project_project_slugs($board);
    $n = 2;
    while (in_array($slug, $used, true)) {
        $slug = $base . '-' . $n;
        $n++;
    }
    $board['projects'][] = [
        'slug' => $slug,
        'section' => project_field($input, 'section', project_first_section_slug($board)),
        'name' => $name,
        'status' => project_field($input, 'status', 'Active'),
        'now' => project_field($input, 'now'),
        'next' => project_field($input, 'next'),
        'blocked' => project_field($input, 'blocked'),
        'updated_at' => project_now(),
    ];
    return project_normalise_board($board);
}

function project_update_project(array $board, array $input): array {
    $slug = project_field($input, 'slug');
    foreach ($board['projects'] as &$project) {
        if (($project['slug'] ?? '') === $slug) {
            $incomingName = project_field($input, 'name', (string)($project['name'] ?? ''));
            if (strcasecmp($incomingName, 'General') === 0) {
                continue;
            }
            foreach (['section', 'name', 'status', 'now', 'next', 'blocked'] as $key) {
                $project[$key] = project_field($input, $key, (string)($project[$key] ?? ''));
            }
            $project['updated_at'] = project_now();
        }
    }
    unset($project);
    return project_normalise_board($board);
}

function project_delete_project(array $board, array $input): array {
    $slug = project_field($input, 'slug');
    $target = project_field($input, 'reassign_project');
    if ($slug === '' || $slug === $target) {
        return project_normalise_board($board);
    }
    $sourceSection = '';
    $targetSection = '';
    foreach ($board['projects'] ?? [] as $project) {
        if ((string)($project['slug'] ?? '') === $slug) {
            $sourceSection = (string)($project['section'] ?? '');
        }
        if ($target !== '' && (string)($project['slug'] ?? '') === $target) {
            $targetSection = (string)($project['section'] ?? '');
        }
    }
    if ($sourceSection === '' || ($target !== '' && ($targetSection === '' || $targetSection !== $sourceSection))) {
        return project_normalise_board($board);
    }
    $board['projects'] = array_values(array_filter($board['projects'] ?? [], fn($project) => (string)($project['slug'] ?? '') !== $slug));
    $now = project_now();
    foreach ($board['items'] as &$todo) {
        if ((string)($todo['project'] ?? '') === $slug) {
            $todo['project'] = $target;
            $todo['section'] = $targetSection !== '' ? $targetSection : $sourceSection;
            $todo['changed_at'] = $now;
            $todo['updated_at'] = $now;
        }
    }
    unset($todo);
    return project_normalise_board($board);
}

function project_next_item_id(array $board): int {
    $max = 0;
    foreach ($board['items'] ?? [] as $todo) {
        $max = max($max, (int)($todo['id'] ?? 0));
    }
    return $max + 1;
}

function project_project_section(array $board, string $projectSlug): string {
    foreach ($board['projects'] ?? [] as $project) {
        if ((string)($project['slug'] ?? '') === $projectSlug) {
            return (string)($project['section'] ?? '');
        }
    }
    return '';
}

function project_item_scope(array $board, array $input, array $existing = []): array {
    $section = project_field($input, 'section', (string)($existing['section'] ?? project_first_section_slug($board)));
    if (!in_array($section, project_section_slugs($board), true)) {
        $section = project_first_section_slug($board);
    }
    $project = project_field($input, 'project', (string)($existing['project'] ?? ''));
    if ($project !== '') {
        $projectSection = project_project_section($board, $project);
        if ($projectSection !== '' && $projectSection === $section) {
            $section = $projectSection;
        } else {
            $project = '';
        }
    }
    return [$section, $project];
}

function project_add_item(array $board, array $input): array {
    [$section, $project] = project_item_scope($board, $input);
    $now = project_now();
    $board['items'][] = [
        'id' => project_next_item_id($board),
        'section' => $section,
        'project' => $project,
        'priority' => project_field($input, 'priority', ''),
        'order' => max(1, min(50, (int)project_field($input, 'order', '1'))),
        'status' => project_field($input, 'status', 'Open'),
        'title' => project_field($input, 'title', 'New item'),
        'notes' => project_normalise_notes_text(project_field($input, 'notes')),
        'changed_at' => $now,
        'updated_at' => $now,
    ];
    return project_normalise_board($board);
}

function project_duplicate_item(array $board, array $input): array {
    $id = (int)project_field($input, 'id', '0');
    foreach ($board['items'] ?? [] as $todo) {
        if ((int)($todo['id'] ?? 0) !== $id) { continue; }
        $now = project_now();
        $board['items'][] = [
            'id' => project_next_item_id($board),
            'section' => (string)($todo['section'] ?? ''),
            'project' => (string)($todo['project'] ?? ''),
            'priority' => (string)($todo['priority'] ?? ''),
            'order' => (int)($todo['order'] ?? 1),
            'status' => 'Open',
            'title' => (string)($todo['title'] ?? ''),
            'notes' => '',
            'changed_at' => $now,
            'updated_at' => $now,
        ];
        return project_normalise_board($board);
    }
    return $board;
}

function project_ready_handoff_note_present(string $notes): bool {
    $lower = strtolower($notes);
    return str_contains($lower, 'joel update:')
        || str_contains($lower, 'ready handoff:')
        || str_contains($lower, 'ready for carl')
        || str_contains($lower, 'carl should verify');
}

function project_ready_handoff_guardrail(string $notes): string {
    if (project_ready_handoff_note_present($notes)) {
        return $notes;
    }
    $prefix = rtrim($notes);
    $entry = "\n\n---\nReady handoff needed: add what changed, what was tested, and what Carl should verify before this becomes Done.";
    return $prefix === '' ? ltrim($entry) : $prefix . $entry;
}

function project_update_item(array $board, array $input): array {
    $id = (int)($input['id'] ?? 0);
    foreach ($board['items'] as &$todo) {
        if ((int)($todo['id'] ?? 0) === $id) {
            $previousStatus = (string)($todo['status'] ?? '');
            [$section, $project] = project_item_scope($board, $input, $todo);
            $todo['section'] = $section;
            $todo['project'] = $project;
            foreach (['priority', 'status', 'title', 'notes'] as $key) {
                if ($key === 'notes') {
                    $incomingNotes = project_field($input, $key, (string)($todo[$key] ?? ''));
                    $todo[$key] = project_stamp_appended_note_text((string)($todo[$key] ?? ''), $incomingNotes);
                } else {
                    $todo[$key] = project_field($input, $key, (string)($todo[$key] ?? ''));
                }
            }
            if ($previousStatus !== 'Ready' && (string)($todo['status'] ?? '') === 'Ready') {
                $todo['notes'] = project_ready_handoff_guardrail((string)($todo['notes'] ?? ''));
            }
            $todo['order'] = max(1, min(50, (int)project_field($input, 'order', (string)($todo['order'] ?? 1))));
            $now = project_now();
            $todo['changed_at'] = $now;
            $todo['updated_at'] = $now;
        }
    }
    unset($todo);
    return project_normalise_board($board);
}

function project_move_item_scope(array $board, array $input): array {
    $id = (int)($input['id'] ?? 0);
    $section = project_field($input, 'section', project_first_section_slug($board));
    $project = project_field($input, 'project');
    if ($project !== '') {
        $projectSection = project_project_section($board, $project);
        if ($projectSection !== '' && $projectSection === $section) {
            $section = $projectSection;
        } else {
            $project = '';
        }
    }
    foreach ($board['items'] as &$todo) {
        if ((int)($todo['id'] ?? 0) === $id) {
            $todo['section'] = $section;
            $todo['project'] = $project;
            $now = project_now();
            $todo['changed_at'] = $now;
            $todo['updated_at'] = $now;
            break;
        }
    }
    unset($todo);
    return project_normalise_board($board);
}

function project_clear_item_changed(array $board, array $input): array {
    $id = (int)($input['id'] ?? 0);
    foreach ($board['items'] as &$todo) {
        if ((int)($todo['id'] ?? 0) === $id) {
            unset($todo['changed_at']);
            break;
        }
    }
    unset($todo);
    return project_normalise_board($board);
}

function project_delete_item(array $board, array $input): array {
    $id = (int)($input['id'] ?? 0);
    $now = project_now();
    foreach ($board['items'] as &$todo) {
        if ((int)($todo['id'] ?? 0) === $id) {
            $todo['deleted_at'] = $now;
            $todo['changed_at'] = $now;
            $todo['updated_at'] = $now;
            break;
        }
    }
    unset($todo);
    return project_normalise_board($board);
}

function project_restore_item(array $board, array $input): array {
    $id = (int)($input['id'] ?? 0);
    foreach ($board['items'] as &$todo) {
        if ((int)($todo['id'] ?? 0) === $id) {
            unset($todo['deleted_at']);
            $now = project_now();
            $todo['changed_at'] = $now;
            $todo['updated_at'] = $now;
            break;
        }
    }
    unset($todo);
    return project_normalise_board($board);
}

function project_hard_delete_item(array $board, array $input): array {
    $id = (int)($input['id'] ?? 0);
    $audioFiles = [];
    $board['items'] = array_values(array_filter($board['items'] ?? [], function ($todo) use ($id, &$audioFiles) {
        if ((int)($todo['id'] ?? 0) !== $id) {
            return true;
        }
        if (!project_item_is_binned($todo)) {
            return true;
        }
        $audio = $todo['audio'] ?? null;
        if (is_array($audio) && trim((string)($audio['file'] ?? '')) !== '') {
            $audioFiles[] = (string)$audio['file'];
        }
        return false;
    }));
    foreach ($audioFiles as $file) {
        $path = project_audio_path($file);
        if (is_file($path)) {
            @unlink($path);
        }
    }
    return project_normalise_board($board);
}

function project_empty_bin(array $board, array $input): array {
    $audioFiles = [];
    $board['items'] = array_values(array_filter($board['items'] ?? [], function ($todo) use (&$audioFiles) {
        if (!project_item_is_binned($todo)) {
            return true;
        }
        $audio = $todo['audio'] ?? null;
        if (is_array($audio) && trim((string)($audio['file'] ?? '')) !== '') {
            $audioFiles[] = (string)$audio['file'];
        }
        return false;
    }));
    foreach ($audioFiles as $file) {
        $path = project_audio_path($file);
        if (is_file($path)) {
            @unlink($path);
        }
    }
    return project_normalise_board($board);
}

function project_delete_item_audio(array $board, array $input): array {
    $id = (int)($input['id'] ?? 0);
    foreach ($board['items'] as &$todo) {
        if ((int)($todo['id'] ?? 0) !== $id || project_item_is_binned($todo)) {
            continue;
        }
        $audio = $todo['audio'] ?? null;
        if (is_array($audio)) {
            $file = trim((string)($audio['file'] ?? ''));
            if ($file !== '') {
                $path = project_audio_path($file);
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            $todo['is_voice_note'] = true;
            unset($todo['audio']);
            $now = project_now();
            $todo['changed_at'] = $now;
            $todo['updated_at'] = $now;
        }
        break;
    }
    unset($todo);
    return project_normalise_board($board);
}

function project_purge_expired_binned_items(array $board, int $retentionDays = 30): array {
    $cutoff = time() - max(0, $retentionDays) * 86400;
    $audioFiles = [];
    $board['items'] = array_values(array_filter($board['items'] ?? [], function ($todo) use ($cutoff, &$audioFiles) {
        if (!project_item_is_binned($todo)) {
            return true;
        }
        $deletedAt = strtotime((string)($todo['deleted_at'] ?? ''));
        if ($deletedAt === false || $deletedAt > $cutoff) {
            return true;
        }
        $audio = $todo['audio'] ?? null;
        if (is_array($audio) && trim((string)($audio['file'] ?? '')) !== '') {
            $audioFiles[] = (string)$audio['file'];
        }
        return false;
    }));
    foreach ($audioFiles as $file) {
        $path = project_audio_path($file);
        if (is_file($path)) {
            @unlink($path);
        }
    }
    return project_normalise_board($board);
}


function project_transcribe_audio(string $path, string $profile = 'fast'): string {
    $python = '/opt/project-board-transcribe/venv/bin/python';
    $script = __DIR__ . '/transcribe-audio.py';
    if (!is_file($path) || !is_file($script) || !is_executable($python)) {
        return 'Transcript unavailable';
    }
    $cmd = escapeshellarg($python) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($path) . ' ' . escapeshellarg($profile) . ' 2>/dev/null';
    $output = [];
    $code = 0;
    exec($cmd, $output, $code);
    $text = trim(implode("
", $output));
    return $code === 0 && $text !== '' ? $text : 'Transcript unavailable';
}

function project_improve_item_transcript(array $board, array $input): array {
    $id = (int)($input['id'] ?? 0);
    foreach ($board['items'] as &$todo) {
        if ((int)($todo['id'] ?? 0) !== $id || project_item_is_binned($todo)) {
            continue;
        }
        $audio = $todo['audio'] ?? null;
        if (!is_array($audio) || trim((string)($audio['file'] ?? '')) === '') {
            break;
        }
        $transcript = project_transcribe_audio(project_audio_path((string)$audio['file']), 'accurate');
        $now = project_now();
        $todo['audio']['improved_transcript'] = $transcript;
        $todo['audio']['improved_transcript_at'] = $now;
        $todo['audio']['improved_transcript_engine'] = 'faster-whisper small.en cpu int8 beam 5';
        $todo['changed_at'] = $now;
        $todo['updated_at'] = $now;
        break;
    }
    unset($todo);
    return project_normalise_board($board);
}

function project_use_improved_item_transcript(array $board, array $input): array {
    $id = (int)($input['id'] ?? 0);
    foreach ($board['items'] as &$todo) {
        if ((int)($todo['id'] ?? 0) !== $id || project_item_is_binned($todo)) {
            continue;
        }
        $audio = $todo['audio'] ?? null;
        $improved = is_array($audio) ? trim((string)($audio['improved_transcript'] ?? '')) : '';
        if ($improved === '' || $improved === 'Transcript unavailable') {
            break;
        }
        $todo['notes'] = $improved;
        $todo['audio']['transcript'] = $improved;
        unset($todo['audio']['improved_transcript'], $todo['audio']['improved_transcript_at'], $todo['audio']['improved_transcript_engine']);
        $now = project_now();
        $todo['changed_at'] = $now;
        $todo['updated_at'] = $now;
        break;
    }
    unset($todo);
    return project_normalise_board($board);
}

function project_record_audio_item(array $board, array $input): array {
    $upload = $input['audio'] ?? null;
    if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return $board;
    }
    $tmp = (string)($upload['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) {
        return $board;
    }
    $mime = (string)($upload['type'] ?? 'audio/webm');
    if (!str_starts_with($mime, 'audio/')) {
        return $board;
    }
    $ext = match ($mime) {
        'audio/ogg', 'audio/oga' => 'ogg',
        'audio/mpeg', 'audio/mp3' => 'mp3',
        'audio/mp4', 'audio/x-m4a' => 'm4a',
        'audio/wav', 'audio/x-wav' => 'wav',
        default => 'webm',
    };
    $name = 'voice-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = project_audio_path($name);
    $moved = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $dest) : rename($tmp, $dest);
    if (!$moved) {
        return $board;
    }
    @chmod($dest, 0660);
    $transcript = project_transcribe_audio($dest);
    $now = project_now();
    $titleText = $transcript !== 'Transcript unavailable' ? mb_substr($transcript, 0, 80) : 'Voice note';
    foreach ($board['items'] as &$todo) {
        if (($todo['section'] ?? '') === 'the-umbrella' && ($todo['project'] ?? '') === 'communication' && is_array($todo['audio'] ?? null)) {
            $todo['order'] = max(1, min(50, (int)($todo['order'] ?? 1) + 1));
        }
    }
    unset($todo);
    $board['items'][] = [
        'id' => project_next_item_id($board),
        'section' => 'the-umbrella',
        'project' => 'communication',
        'priority' => '',
        'order' => 1,
        'status' => 'Open',
        'title' => 'Voice note' . ($titleText !== 'Voice note' && $titleText !== '' ? ': ' . $titleText : ''),
        'notes' => $transcript,
        'is_voice_note' => true,
        'audio' => [
            'file' => project_audio_public_name($name),
            'mime' => $mime,
            'transcript' => $transcript,
            'recorded_at' => $now,
        ],
        'changed_at' => $now,
        'updated_at' => $now,
    ];
    return project_normalise_board($board);
}

// Legacy todo-named compatibility wrappers. Keep until saved forms/scripts no longer post old actions.
function project_next_todo_id(array $board): int { return project_next_item_id($board); }
function project_todo_scope(array $board, array $input, array $existing = []): array { return project_item_scope($board, $input, $existing); }
function project_add_todo(array $board, array $input): array { return project_add_item($board, $input); }
function project_update_todo(array $board, array $input): array { return project_update_item($board, $input); }
function project_move_todo_scope(array $board, array $input): array { return project_move_item_scope($board, $input); }
function project_clear_todo_changed(array $board, array $input): array { return project_clear_item_changed($board, $input); }
function project_delete_todo(array $board, array $input): array { return project_delete_item($board, $input); }
function project_restore_todo(array $board, array $input): array { return project_restore_item($board, $input); }
function project_hard_delete_todo(array $board, array $input): array { return project_hard_delete_item($board, $input); }
function project_delete_todo_audio(array $board, array $input): array { return project_delete_item_audio($board, $input); }
function project_purge_expired_binned_todos(array $board, int $retentionDays = 30): array { return project_purge_expired_binned_items($board, $retentionDays); }
