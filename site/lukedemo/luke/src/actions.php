<?php
function project_apply_action(array $board, array $post, array $files = []): array {
    return match (project_field($post, 'action')) {
        'save_settings' => project_save_settings($board, $post),
        'add_section' => project_add_section($board, $post),
        'update_section' => project_update_section($board, $post),
        'delete_section' => project_delete_section($board, $post),
        'add_project' => project_add_project($board, $post),
        'update_project' => project_update_project($board, $post),
        'delete_project' => project_delete_project($board, $post),
        'add_item' => project_add_item($board, $post),
        'duplicate_item' => project_duplicate_item($board, $post),
        'update_item' => project_update_item($board, $post),
        'move_item_scope' => project_move_item_scope($board, $post),
        'clear_item_changed' => project_clear_item_changed($board, $post),
        'add_todo' => project_add_item($board, $post),
        'update_todo' => project_update_item($board, $post),
        'move_todo_scope' => project_move_item_scope($board, $post),
        'clear_todo_changed' => project_clear_item_changed($board, $post),
        'record_audio' => project_record_audio_item($board, $files + $post),
        'improve_item_transcript' => project_improve_item_transcript($board, $post),
        'use_improved_item_transcript' => project_use_improved_item_transcript($board, $post),
        'delete_item' => project_delete_item($board, $post),
        'delete_item_audio' => project_delete_item_audio($board, $post),
        'restore_item' => project_restore_item($board, $post),
        'hard_delete_item' => project_hard_delete_item($board, $post),
        'empty_bin' => project_empty_bin($board, $post),
        'delete_todo' => project_delete_item($board, $post),
        'delete_todo_audio' => project_delete_item_audio($board, $post),
        'restore_todo' => project_restore_item($board, $post),
        'hard_delete_todo' => project_hard_delete_item($board, $post),
        default => $board,
    };
}

function project_redirect_after_action(array $board, array $post): string {
    $action = project_field($post, 'action');
    $section = project_field($post, 'section');
    $project = project_field($post, 'project');
    $hasReturnContext = array_key_exists('return_section', $post) || array_key_exists('return_project', $post);

    if ($action === 'add_section') {
        $wanted = project_slug(project_field($post, 'name', 'New area'));
        foreach ($board['sections'] ?? [] as $candidate) {
            if (($candidate['slug'] ?? '') === $wanted || ($candidate['name'] ?? '') === project_field($post, 'name')) {
                $section = (string)$candidate['slug'];
                break;
            }
        }
    }
    if ($action === 'update_section' && project_field($post, 'slug') !== '') {
        $section = project_field($post, 'slug');
    }
    if ($action === 'delete_section') {
        $section = project_field($post, 'reassign_section');
        $project = '';
    }
    if ($action === 'add_project') {
        $wanted = project_slug(project_field($post, 'name', 'New focus'));
        foreach ($board['projects'] ?? [] as $candidate) {
            if (($candidate['section'] ?? '') === $section && (($candidate['slug'] ?? '') === $wanted || ($candidate['name'] ?? '') === project_field($post, 'name'))) {
                $project = (string)$candidate['slug'];
                break;
            }
        }
    }
    if ($action === 'update_project' && project_field($post, 'slug') !== '') {
        $project = project_field($post, 'slug');
    }
    if ($action === 'delete_project') {
        $project = project_field($post, 'reassign_project');
        if ($project !== '') {
            foreach ($board['projects'] ?? [] as $candidate) {
                if (($candidate['slug'] ?? '') === $project) {
                    $section = (string)($candidate['section'] ?? $section);
                    break;
                }
            }
        }
    }
    if (($section === '' || ($project === '' && !in_array($action, ['move_item_scope', 'move_todo_scope'], true))) && project_field($post, 'id') !== '') {
        $id = (int)project_field($post, 'id');
        foreach ($board['items'] ?? [] as $todo) {
            if ((int)($todo['id'] ?? 0) === $id) {
                $section = $section !== '' ? $section : (string)($todo['section'] ?? '');
                $project = $project !== '' ? $project : (string)($todo['project'] ?? '');
                break;
            }
        }
    }
    if ($hasReturnContext && in_array($action, ['save_settings', 'add_section', 'update_section', 'add_project', 'update_project', 'add_item', 'update_item', 'move_item_scope', 'clear_item_changed', 'improve_item_transcript', 'use_improved_item_transcript', 'delete_item', 'delete_item_audio', 'restore_item', 'hard_delete_item', 'empty_bin', 'add_todo', 'update_todo', 'move_todo_scope', 'clear_todo_changed', 'record_audio', 'delete_todo', 'delete_todo_audio', 'restore_todo', 'hard_delete_todo', 'undo_last_change'], true)) {
        $section = project_field($post, 'return_section');
        $project = project_field($post, 'return_project');
    }
    if (!$hasReturnContext && $section === '') {
        $section = project_first_section_slug($board);
    }
    if (!$hasReturnContext && $project === '' && !in_array($action, ['add_section', 'delete_section', 'move_item_scope', 'move_todo_scope'], true)) {
        $project = project_first_project_slug($board, $section);
    }
    $query = ['section_filter' => $section, 'project_filter' => $project];
    foreach (['q', 'priority', 'status', 'sort'] as $suffix) {
        $itemKey = 'item_' . $suffix;
        $legacyKey = 'task_' . $suffix;
        if (array_key_exists($itemKey, $post)) {
            $query[$itemKey] = project_field($post, $itemKey);
        } elseif (array_key_exists($legacyKey, $post)) {
            $query[$legacyKey] = project_field($post, $legacyKey);
        }
    }
    if ($hasReturnContext && project_field($post, 'just_edited_item_id') !== '') {
        $query['just_edited_item_id'] = project_field($post, 'just_edited_item_id');
    }
    if ($hasReturnContext && in_array($action, ['update_item', 'move_item_scope', 'update_todo', 'move_todo_scope'], true) && project_field($post, 'id') !== '') {
        $id = (int)project_field($post, 'id');
        foreach ($board['items'] ?? [] as $todo) {
            if ((int)($todo['id'] ?? 0) !== $id) { continue; }
            $todoProject = trim((string)($todo['project'] ?? '')) === '' ? '__none' : (string)($todo['project'] ?? '');
            $returnProject = $project === '' ? '' : $project;
            $movedOutOfContext = ($section !== '' && (string)($todo['section'] ?? '') !== $section)
                || ($returnProject === '__none' && trim((string)($todo['project'] ?? '')) !== '')
                || ($returnProject !== '' && $returnProject !== '__none' && $todoProject !== $returnProject);
            $statusFilter = $query['item_status'] ?? $query['task_status'] ?? '';
            $statusMovedOut = $statusFilter !== '' && !project_item_status_matches_filter($todo, project_effective_item_status_filters($statusFilter), project_item_is_binned($todo), '');
            if ($movedOutOfContext || $statusMovedOut) {
                $query['just_edited_item_id'] = (string)$id;
            }
            break;
        }
    }
    if ($hasReturnContext && $action === 'duplicate_item' && project_field($post, 'id') !== '') {
        $maxId = 0;
        foreach ($board['items'] ?? [] as $todo) { $maxId = max($maxId, (int)($todo['id'] ?? 0)); }
        if ($maxId > (int)project_field($post, 'id')) { $query['just_edited_item_id'] = (string)$maxId; }
    }
    return './?' . http_build_query($query);
}
