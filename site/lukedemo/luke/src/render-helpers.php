<?php
function h(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function project_decode_note_text_entities(string $text): string {
    $decoded = $text;
    for ($i = 0; $i < 3; $i++) {
        $next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($next === $decoded) {
            break;
        }
        $decoded = $next;
    }
    return $decoded;
}

function project_note_image_from_tag(string $tag): string {
    if (!preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/is', $tag, $srcMatch)) {
        return '';
    }
    $src = html_entity_decode($srcMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!preg_match('~^data:image/(?:png|jpe?g|gif|webp);base64,[a-z0-9+/=]+$~i', $src)) {
        return '';
    }
    $alt = 'Pasted image';
    if (preg_match('/\balt\s*=\s*(["\'])(.*?)\1/is', $tag, $altMatch)) {
        $candidate = trim(html_entity_decode($altMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($candidate !== '') {
            $alt = $candidate;
        }
    }
    return '<img src="' . h($src) . '" alt="' . h($alt) . '" loading="lazy" class="note-image">';
}

/** Render only safe inline formatting stored in a LUKE note, including pasted image data URLs. */
function project_render_formatted_notes(string $text): string {
    $parts = preg_split('/(<\s*\/?\s*(?:sup|sub|strong|b|em|i|br|img)\b[^>]*>)/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $html = '';
    foreach ($parts ?: [] as $part) {
        if (preg_match('/^<\s*(\/?)\s*(sup|sub|strong|b|em|i|br|img)\b[^>]*>$/i', $part, $match)) {
            $closing = $match[1] === '/';
            $tag = strtolower($match[2]);
            $tag = match ($tag) {
                'b' => 'strong',
                'i' => 'em',
                default => $tag,
            };
            if ($tag === 'img') {
                if (!$closing) $html .= project_note_image_from_tag($part);
            } elseif ($tag === 'br') {
                if (!$closing) $html .= '<br>';
            } else {
                $html .= $closing ? "</$tag>" : "<$tag>";
            }
            continue;
        }
        $html .= str_replace("\n", "<br>", h(project_decode_note_text_entities($part)));
    }
    return $html;
}

function project_render_formatted_notes_editor(string $notes = ''): string {
    return '<label>Notes</label><textarea name="notes" data-formatted-notes-storage hidden>' . h($notes) . '</textarea>'
        . ($notes === '' ? '' : '<input type="hidden" name="notes_base" value="' . h(sha1(project_normalise_notes_text($notes))) . '">')
        . '<div class="formatted-notes-editor" contenteditable="true" role="textbox" aria-multiline="true" data-formatted-notes-editor data-note-stamp="1" data-note-stamp-user="' . h(project_note_stamp_user()) . '">'
        . project_render_formatted_notes($notes) . '</div>';
}
/** Shared by Add item and Edit item so both dialogs have the same layout: buttons, Title, Notes, buttons. */
function project_render_item_title_notes_fields(string $title, string $notesHtml, string $buttons, string $titlePlaceholder = '', string $rowClass = '', bool $bottomButtons = true): string {
    $row = 'task-actions center-actions' . ($rowClass !== '' ? ' ' . $rowClass : '');
    return '<div class="task-actions task-actions-top center-actions' . ($rowClass !== '' ? ' ' . $rowClass : '') . '" data-item-title-notes-fields>' . $buttons . '</div>'
        . '<div><label>Title</label><input name="title" value="' . h($title) . '"' . ($titlePlaceholder !== '' ? ' placeholder="' . h($titlePlaceholder) . '"' : '') . '></div>'
        . '<div class="notes-field">' . $notesHtml . '</div>'
        . ($bottomButtons ? '<div class="' . $row . '">' . $buttons . '</div>' : '');
}
function project_theme_key(string $value): string {
    $key = strtolower(str_replace(["'", '’'], '', trim($value)));
    $key = preg_replace('/[^a-z0-9]+/', '-', $key) ?? '';
    return trim($key, '-');
}

function project_item_is_binned(array $todo): bool {
    return trim((string)($todo['deleted_at'] ?? '')) !== '';
}

function project_item_display_status(array $todo): string {
    return project_item_is_binned($todo) ? 'Bin' : (string)($todo['status'] ?? 'Open');
}

function project_active_item_id(array $query = []): string {
    $queryId = trim((string)($query['active_item_id'] ?? ''));
    if ($queryId !== '') {
        return $queryId;
    }
    $path = project_activity_path();
    if (!is_file($path) || !is_readable($path)) {
        return '';
    }
    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data)) {
        return '';
    }
    return trim((string)($data['id'] ?? ''));
}

function project_agent_name(): string {
    return project_demo_mode() ? 'The Agent' : 'Joel';
}

function project_doing_led(array $todo, string $activeItemId): string {
    $itemId = trim((string)($todo['id'] ?? ''));
    $isActive = $itemId !== '' && $activeItemId !== '' && $itemId === $activeItemId;
    $class = 'doing-led' . ($isActive ? ' active' : '');
    $label = $isActive ? project_agent_name() . ' is working on item #' . $itemId : project_agent_name() . ' is not working on item #' . $itemId;
    $title = $isActive ? project_agent_name() . ' is working on this item' : project_agent_name() . ' is not working on this item';
    return '<span class="' . h($class) . '" aria-label="' . h($label) . '" title="' . h($title) . '">Agent</span>';
}

function project_site_agent_active_light(string $activeItemId, array $contextQuery = []): string {
    $activeItemId = trim($activeItemId);
    $class = 'doing-led' . ($activeItemId !== '' ? ' active' : '');
    $status = $activeItemId !== '' ? project_agent_name() . ' is working on item #' . $activeItemId : project_agent_name() . ' is not working on any LUKE item';
    $label = $status . '; show current context / Agent active';
    $params = array_filter([
        'section_filter' => (string)($contextQuery['section_filter'] ?? ''),
        'project_filter' => (string)($contextQuery['project_filter'] ?? ''),
        'task_status' => 'agent-active',
    ], fn($value) => $value !== '');
    $href = '?' . http_build_query($params);
    return '<div class="site-agent-active-light"><a class="' . h($class) . '" href="' . h($href) . '" aria-label="' . h($label) . '" title="' . h($label) . '">Agent</a></div>';
}

function project_item_style(array $todo): string {
    $priority = project_theme_key((string)($todo['priority'] ?? ''));
    $status = project_theme_key(project_item_display_status($todo));
    return ' data-priority="' . h($priority) . '" data-status="' . h($status) . '"';
}

function project_extract_urls(string $text): array {
    if (!preg_match_all('~https?://[^\s<>()]+~i', $text, $matches)) {
        return [];
    }
    $urls = [];
    foreach ($matches[0] as $url) {
        $url = rtrim($url, '.,;:!?)]}');
        if ($url !== '' && !in_array($url, $urls, true)) {
            $urls[] = $url;
        }
    }
    return $urls;
}

function project_render_found_links(array $todo): string {
    $text = (string)($todo['title'] ?? '') . "
" . (string)($todo['notes'] ?? '');
    $urls = project_extract_urls($text);
    if (!$urls) {
        return '';
    }
    $html = '<div class="found-links"><span>Links:</span>';
    foreach ($urls as $url) {
        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        $html .= '<a href="' . h($url) . '" target="_blank" rel="noopener noreferrer">' . h((string)$host) . '</a>';
    }
    return $html . '</div>';
}



function project_url(array $params = [], bool $keepEmpty = false): string {
    $params = array_filter($params, fn($value) => $value !== null && ($keepEmpty || $value !== ''));
    return $params ? '?' . h(http_build_query($params)) : './';
}


function project_scope_move_form(int $todoId, string $section, string $project, string $label, array $contextQuery = []): string {
    $html = '<form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="' . h((string)$todoId) . '"><input type="hidden" name="section" value="' . h($section) . '"><input type="hidden" name="project" value="' . h($project) . '">';
    $html .= '<input type="hidden" name="return_section" value="' . h((string)($contextQuery['section_filter'] ?? '')) . '"><input type="hidden" name="return_project" value="' . h((string)($contextQuery['project_filter'] ?? '')) . '">';
    foreach (['task_q', 'task_priority', 'task_status', 'task_sort'] as $key) {
        if (array_key_exists($key, $contextQuery)) {
            $html .= '<input type="hidden" name="' . h($key) . '" value="' . h((string)$contextQuery[$key]) . '">';
        }
    }
    return $html . '<button type="submit" class="scope-choice">' . h($label) . '</button></form>';
}

function project_item_section_scope_menu(array $sections, int $todoId, string $sectionSlug, string $sectionName, string $menuId, array $contextQuery = []): string {
    $html = '<span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="' . h($menuId) . '" aria-expanded="false">' . h($sectionName) . '</button><span class="scope-popover" id="scope-menu-' . h($menuId) . '" hidden>';
    $html .= '<a href="' . project_url(['section_filter' => $sectionSlug, 'project_filter' => '', 'task_status' => ''], true) . '">Show</a><strong>Change</strong>' . project_scope_move_form($todoId, $sectionSlug, '', 'No focus', $contextQuery);
    foreach ($sections as $section) {
        $slug = (string)($section['slug'] ?? '');
        if ($slug === '') { continue; }
        $html .= project_scope_move_form($todoId, $slug, '', (string)($section['name'] ?? $slug), $contextQuery);
    }
    $html .= '<a href="' . project_url(['view' => 'add_section'] + $contextQuery) . '">New...</a></span></span>';
    return $html;
}

function project_item_project_scope_menu(array $projects, int $todoId, string $sectionSlug, string $projectSlug, string $projectName, string $menuId, array $contextQuery = []): string {
    $html = '<span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="' . h($menuId) . '" aria-expanded="false">' . h($projectName) . '</button><span class="scope-popover" id="scope-menu-' . h($menuId) . '" hidden>';
    $showProjectFilter = $projectSlug === '' ? '__none' : $projectSlug;
    $html .= '<a href="' . project_url(['section_filter' => $sectionSlug, 'project_filter' => $showProjectFilter, 'task_status' => ''], true) . '">Show</a><strong>Change</strong>' . project_scope_move_form($todoId, $sectionSlug, '', 'No focus', $contextQuery);
    foreach ($projects as $project) {
        $slug = (string)($project['slug'] ?? '');
        if ($slug === '') { continue; }
        $projectSection = (string)($project['section'] ?? '');
        if ($projectSection !== $sectionSlug) { continue; }
        $html .= project_scope_move_form($todoId, $projectSection, $slug, (string)($project['name'] ?? $slug), $contextQuery);
    }
    $html .= '<a href="' . project_url(['view' => 'add_project', 'section_filter' => $sectionSlug]) . '">New...</a></span></span>';
    return $html;
}

function project_item_context_menu(array $sections, array $projects, array $todo, array $sectionNames, array $projectNames, array $contextQuery = []): string {
    $id = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string)($todo['id'] ?? 'item'));
    $sectionSlug = (string)($todo['section'] ?? '');
    $projectSlug = (string)($todo['project'] ?? '');
    $sectionName = $sectionNames[$sectionSlug] ?? $sectionSlug;
    $projectName = $projectSlug === '' ? 'No focus' : ($projectNames[$projectSlug] ?? $projectSlug);
    return '<span class="task-context">'
        . project_item_section_scope_menu($sections, (int)($todo['id'] ?? 0), $sectionSlug, $sectionName, 'item-section-' . $id, $contextQuery)
        . '<span class="scope-separator"> · </span>'
        . project_item_project_scope_menu($projects, (int)($todo['id'] ?? 0), $sectionSlug, $projectSlug, $projectName, 'item-project-' . $id, $contextQuery)
        . '</span>';
}


function project_render_audio_attachment(array $todo): string {
    $audio = $todo['audio'] ?? null;
    if (!is_array($audio) || trim((string)($audio['file'] ?? '')) === '') {
        return '';
    }
    $file = (string)$audio['file'];
    $transcript = trim((string)($audio['transcript'] ?? $todo['notes'] ?? ''));
    if ($transcript === '') {
        $transcript = 'Transcript unavailable';
    }
    $id = h((string)($todo['id'] ?? ''));
    $html = '<div class="audio-attachment"><audio controls preload="metadata" src="?audio=' . h(rawurlencode($file)) . '"></audio><div class="audio-transcript"><div class="audio-transcript-heading"><strong>Transcript</strong><button type="button" class="small copy-transcript-button" data-copy-transcript-button data-copy-transcript-text="' . h($transcript) . '">Copy</button></div><p>' . h($transcript) . '</p></div>';
    $improved = trim((string)($audio['improved_transcript'] ?? ''));
    if ($improved !== '') {
        $meta = trim((string)($audio['improved_transcript_engine'] ?? ''));
        $when = trim((string)($audio['improved_transcript_at'] ?? ''));
        $label = trim(($meta !== '' ? $meta : 'higher-accuracy local transcription') . ($when !== '' ? ' · ' . $when : ''));
        $html .= '<div class="audio-transcript improved-transcript"><div class="audio-transcript-heading"><strong>Improved transcript</strong><button type="button" class="small copy-transcript-button" data-copy-transcript-button data-copy-transcript-text="' . h($improved) . '">Copy</button></div>' . ($label !== '' ? '<div class="muted">' . h($label) . '</div>' : '') . '<p>' . h($improved) . '</p></div>';
    }
    $html .= '<div class="audio-transcript-actions"><button type="submit" class="small" form="improve-transcript-task-' . $id . '" data-improve-transcript-button>Improve transcript</button><button type="submit" class="small danger" form="delete-audio-task-' . $id . '">Delete voice note</button>';
    return $html . '</div></div>';
}


function project_format_attachment_size(int $bytes): string {
    if ($bytes >= 1048576) {
        return rtrim(rtrim(number_format($bytes / 1048576, 1), '0'), '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return rtrim(rtrim(number_format($bytes / 1024, 1), '0'), '.') . ' KB';
    }
    return $bytes . ' B';
}

function project_render_file_attachments(array $todo): string {
    $attachments = $todo['attachments'] ?? [];
    if (!is_array($attachments) || $attachments === []) {
        return '';
    }
    $html = '<div class="file-attachments"><strong>Attachments</strong><ul>';
    foreach ($attachments as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }
        $file = trim((string)($attachment['file'] ?? ''));
        if ($file === '') {
            continue;
        }
        $name = trim((string)($attachment['name'] ?? '')) ?: project_attachment_public_name($file);
        $meta = [];
        $size = (int)($attachment['size'] ?? 0);
        if ($size > 0) {
            $meta[] = project_format_attachment_size($size);
        }
        $html .= '<li><a href="?attachment=' . h(rawurlencode($file)) . '">' . h($name) . '</a>' . ($meta ? '<span class="muted"> · ' . h(implode(' · ', $meta)) . '</span>' : '') . '</li>';
    }
    return $html . '</ul></div>';
}

function project_project_options(array $projects, string $selected = '', string $section = ''): string {
    $html = '<option value=""' . ($selected === '' ? ' selected' : '') . '>All focuses</option>';
    $html .= '<option value="__none"' . ($selected === '__none' ? ' selected' : '') . '>No focus</option>';
    foreach ($projects as $project) {
        if ($section !== '' && ($project['section'] ?? '') !== $section) {
            continue;
        }
        $isSelected = ($project['slug'] ?? '') === $selected ? ' selected' : '';
        $html .= '<option value="' . h((string)$project['slug']) . '"' . $isSelected . '>' . h((string)$project['name']) . '</option>';
    }
    return $html;
}

function project_section_options(array $sections, string $selected = ''): string {
    $html = '<option value=""' . ($selected === '' ? ' selected' : '') . '>All areas</option>';
    foreach ($sections as $section) {
        $isSelected = ($section['slug'] ?? '') === $selected ? ' selected' : '';
        $html .= '<option value="' . h((string)$section['slug']) . '"' . $isSelected . '>' . h((string)$section['name']) . '</option>';
    }
    return $html;
}

function project_section_scope_options(array $sections, string $selected = ''): string {
    $html = '';
    foreach ($sections as $section) {
        $slug = (string)($section['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $isSelected = $slug === $selected ? ' selected' : '';
        $html .= '<option value="' . h($slug) . '"' . $isSelected . '>' . h((string)($section['name'] ?? $slug)) . '</option>';
    }
    return $html;
}

function project_project_scope_options(array $projects, string $selected = '', string $section = '', bool $keepOtherSectionsForDynamicFilter = false): string {
    $html = '<option value="" data-section=""' . ($selected === '' ? ' selected' : '') . '>No focus</option>';
    foreach ($projects as $project) {
        $slug = (string)($project['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $projectSection = (string)($project['section'] ?? '');
        $isOtherSection = $section !== '' && $projectSection !== $section;
        if ($isOtherSection && !$keepOtherSectionsForDynamicFilter) {
            continue;
        }
        $isSelected = $slug === $selected ? ' selected' : '';
        $hiddenUntilSectionSelected = $isOtherSection ? ' hidden disabled' : '';
        $html .= '<option value="' . h($slug) . '" data-section="' . h($projectSection) . '"' . $isSelected . $hiddenUntilSectionSelected . '>' . h((string)($project['name'] ?? $slug)) . '</option>';
    }
    return $html;
}

function project_item_needs_voice_scope_prompt(array $todo): bool {
    if ((string)($todo['section'] ?? '') !== 'the-umbrella' || (string)($todo['project'] ?? '') !== 'communication') {
        return false;
    }
    if (!empty($todo['is_voice_note'])) {
        return true;
    }
    $audio = $todo['audio'] ?? null;
    if (is_array($audio)) {
        return true;
    }
    return str_starts_with((string)($todo['title'] ?? ''), 'Voice note');
}

function project_render_voice_note_scope_prompt(array $sections, array $projects, array $todo, array $contextQuery = []): string {
    if (!project_item_needs_voice_scope_prompt($todo)) {
        return '';
    }
    $id = (int)($todo['id'] ?? 0);
    $section = (string)($todo['section'] ?? '');
    $project = (string)($todo['project'] ?? '');
    $html = '<div class="voice-scope-modal modal-backdrop" hidden data-voice-scope-modal="' . h((string)$id) . '"><div class="modal voice-scope-dialog" role="dialog" aria-modal="true" aria-labelledby="voice-scope-title-' . h((string)$id) . '">';
    $html .= '<h2 id="voice-scope-title-' . h((string)$id) . '">Assign voice note</h2><p>This voice note is still in The Umbrella · Communication. Choose the right Area and Focus, or confirm it should stay here.</p>';
    $html .= '<label>Area<select data-voice-scope-section onchange="boardVoiceScopeSectionChanged(this)">' . project_section_scope_options($sections, $section) . '</select></label>';
    $html .= '<label>Focus<select data-voice-scope-project onchange="boardVoiceScopeProjectChanged(this)">' . project_project_scope_options($projects, $project) . '</select></label>';
    $html .= '<div class="scope-new-links"><a href="' . project_url(['view' => 'add_section'] + $contextQuery) . '">New Area...</a><a data-voice-scope-new-project href="' . project_url(['view' => 'add_project', 'section_filter' => $section] + $contextQuery) . '">New Focus...</a></div>';
    return $html . '<div class="actions center-actions"><button type="button" class="small" onclick="boardVoiceScopeConfirm(this)">Confirm scope and save</button><button type="button" class="small danger" onclick="boardVoiceScopeCancel(this)">Cancel</button></div></div></div>';
}

function project_item_status_label(string $status): string {
    return $status;
}

function project_status_options(array $values, string $selected): string {
    $html = '';
    foreach ($values as $value) {
        $isSelected = $value === $selected ? ' selected' : '';
        $themeClass = '';
        if (in_array($value, PROJECT_PRIORITIES, true)) {
            $themeKey = project_theme_key($value === '' ? 'blank' : $value);
            $themeClass = ' class="priority-option priority-' . h($themeKey) . '"';
        } elseif (in_array($value, PROJECT_TODO_STATUSES, true)) {
            $themeKey = project_theme_key($value);
            $themeClass = ' class="status-option status-' . h($themeKey) . '"';
        }
        if ($value === '') {
            $html .= '<option value=""' . $themeClass . $isSelected . '></option>';
        } else {
            $label = in_array($value, PROJECT_TODO_STATUSES, true) ? project_item_status_label($value) : project_priority_label($value);
            $valueAttribute = $label !== $value ? ' value="' . h($value) . '"' : '';
            $html .= '<option' . $themeClass . $valueAttribute . $isSelected . '>' . h($label) . '</option>';
        }
    }
    return $html;
}

function project_filter_options(array $values, string $selected, string $allLabel = 'Any'): string {
    $html = '<option value=""' . ($selected === '' ? ' selected' : '') . '>' . h($allLabel) . '</option>';
    foreach ($values as $value) {
        if ($value === '') {
            $isSelected = $selected === '__blank' ? ' selected' : '';
            $html .= '<option value="__blank"' . $isSelected . '></option>';
            continue;
        }
        $isSelected = $value === $selected ? ' selected' : '';
        $label = in_array($value, PROJECT_TODO_STATUSES, true) ? project_item_status_label($value) : project_priority_label($value);
        $html .= '<option value="' . h($value) . '"' . $isSelected . '>' . h($label) . '</option>';
    }
    return $html;
}

function project_item_status_filter_values(mixed $raw): array {
    $allowed = ['active', 'inactive', 'recent', 'agent-active', ...PROJECT_TODO_STATUSES, 'Bin'];
    $values = is_array($raw) ? $raw : explode(',', (string)$raw);
    $normalised = [];
    foreach ($values as $value) {
        $value = trim((string)$value);
        if ($value === '' || !in_array($value, $allowed, true) || in_array($value, $normalised, true)) {
            continue;
        }
        $normalised[] = $value;
    }
    return $normalised;
}

function project_item_status_filter_query_value(mixed $raw): string {
    return implode(',', project_item_status_filter_values($raw));
}

function project_item_status_filter_selected(mixed $raw, string $value): bool {
    return in_array($value, project_item_status_filter_values($raw), true);
}

function project_effective_item_status_filters(mixed $raw): array {
    $filters = project_item_status_filter_values($raw);
    $activeStatuses = ['Open', 'Do', 'Ready'];
    $hasRecent = in_array('recent', $filters, true);
    $hasActiveStatus = count(array_intersect($activeStatuses, $filters)) > 0;
    $hasInactiveStatus = count(array_intersect(['Done', 'Blocked', 'Bin', 'inactive'], $filters)) > 0;
    if ($hasRecent && $hasActiveStatus && !$hasInactiveStatus) {
        $filters = array_values(array_filter($filters, fn($filter) => $filter !== 'recent'));
    }
    return $filters;
}

function project_recent_item_counts(): array {
    return [1, 3, 7, 14];
}

function project_recent_item_default_count(): int {
    return 3;
}

function project_recent_item_max_count(): int {
    return max(project_recent_item_counts());
}

function project_recent_item_timestamp(array $todo): string {
    $updatedAt = trim((string)($todo['updated_at'] ?? ''));
    if ($updatedAt !== '') return $updatedAt;
    return trim((string)($todo['changed_at'] ?? ''));
}

function project_recent_item_ranks(array $todos, int $maxCount = 30): array {
    $recent = array_values(array_filter($todos, fn($todo) => project_recent_item_timestamp($todo) !== ''));
    usort($recent, function ($a, $b) {
        return [
            project_recent_item_timestamp($b),
            (int)($b['id'] ?? 0),
        ] <=> [
            project_recent_item_timestamp($a),
            (int)($a['id'] ?? 0),
        ];
    });
    $ranks = [];
    foreach (array_slice($recent, 0, $maxCount) as $index => $todo) {
        $id = trim((string)($todo['id'] ?? ''));
        if ($id !== '') $ranks[$id] = $index + 1;
    }
    return $ranks;
}

function project_item_status_matches_filter(array $todo, array $statusFilters, bool $isBinned, string $activeItemId = '', array $recentRanks = []): bool {
    if ($statusFilters === []) {
        return true;
    }
    $status = (string)($todo['status'] ?? 'Open');
    foreach ($statusFilters as $filter) {
        if ($filter === 'recent' && array_key_exists(trim((string)($todo['id'] ?? '')), $recentRanks)) {
            return true;
        }
        if ($filter === 'agent-active' && $activeItemId !== '' && trim((string)($todo['id'] ?? '')) === $activeItemId) {
            return true;
        }
        if ($filter === 'active' && !$isBinned && in_array($status, ['Open', 'Do', 'Ready'], true)) {
            return true;
        }
        if ($filter === 'inactive' && ($isBinned || in_array($status, ['Done', 'Blocked'], true))) {
            return true;
        }
        if ($filter === 'Bin' && $isBinned) {
            return true;
        }
        if (in_array($filter, PROJECT_TODO_STATUSES, true) && !$isBinned && $status === $filter) {
            return true;
        }
    }
    return false;
}

function project_order_options(int $selected): string {
    $selected = max(1, min(50, $selected));
    $html = '';
    for ($i = 1; $i <= 50; $i++) {
        $isSelected = $i === $selected ? ' selected' : '';
        $html .= '<option' . $isSelected . '>' . $i . '</option>';
    }
    return $html;
}

function project_cycle_value(array $values, string $current): string {
    $index = array_search($current, $values, true);
    if ($index === false) {
        return (string)($values[0] ?? '');
    }
    return (string)$values[($index + 1) % count($values)];
}

function project_item_update_hidden_inputs(array $todo, array $overrides = [], array $contextQuery = []): string {
    $values = [
        'action' => 'update_item',
        'id' => (string)($todo['id'] ?? ''),
        'section' => (string)($todo['section'] ?? ''),
        'project' => (string)($todo['project'] ?? ''),
        'priority' => (string)($todo['priority'] ?? ''),
        'order' => (string)((int)($todo['order'] ?? 1)),
        'status' => (string)($todo['status'] ?? 'Open'),
        'title' => (string)($todo['title'] ?? ''),
        'notes' => (string)($todo['notes'] ?? ''),
        'return_section' => (string)($contextQuery['section_filter'] ?? ''),
        'return_project' => (string)($contextQuery['project_filter'] ?? ''),
    ];
    foreach (['task_q', 'task_priority', 'task_status', 'task_sort'] as $key) {
        if (array_key_exists($key, $contextQuery)) {
            $values[$key] = (string)$contextQuery[$key];
        }
    }
    foreach ($overrides as $key => $value) {
        $values[$key] = (string)$value;
    }
    // Values as loaded, so the save can tell what the user actually changed.
    $values['base_status'] = (string)($todo['status'] ?? 'Open');
    $values['base_priority'] = (string)($todo['priority'] ?? '');
    $values['base_title'] = (string)($todo['title'] ?? '');
    $values['base_order'] = (string)((int)($todo['order'] ?? 1));
    $values['notes_base'] = sha1(project_normalise_notes_text((string)($todo['notes'] ?? '')));

    $html = '';
    foreach ($values as $name => $value) {
        $html .= '<input type="hidden" name="' . h((string)$name) . '" value="' . h($value) . '">';
    }
    return $html;
}

function project_summary_meta_controls(array $todo, string $displayStatus, array $contextQuery = []): string {
    $priority = (string)($todo['priority'] ?? '');
    $priorityLabel = $priority === '' ? 'No priority' : project_priority_label($priority);
    $order = max(1, min(50, (int)($todo['order'] ?? 1)));
    $status = (string)($todo['status'] ?? 'Open');
    $isBinned = project_item_is_binned($todo);

    $priorityClass = 'badge priority-badge summary-control summary-priority-control priority-' . project_theme_key($priority === '' ? 'blank' : $priority) . ($priority === '' ? ' blank-priority' : '');
    $html = '<span class="task-badges task-summary-controls">';
    $html .= '<form method="post" class="summary-control-form">'
        . project_item_update_hidden_inputs($todo, [], $contextQuery)
        . '<select name="priority" class="' . h($priorityClass) . '" title="Change priority" aria-label="Change priority from ' . h($priorityLabel) . '" onchange="boardSummaryMetaChanged(this)">' . project_status_options(project_priority_values(), $priority) . '</select></form>';

    $html .= '<form method="post" class="summary-control-form summary-order-form">'
        . project_item_update_hidden_inputs($todo, [], $contextQuery)
        . '<select name="order" class="summary-order-control" title="Change order" aria-label="Change order" onchange="boardSummaryMetaChanged(this)">' . project_order_options($order) . '</select></form>';

    if ($isBinned) {
        $html .= '<span class="badge ' . h($displayStatus) . ' summary-control summary-status-control disabled-summary-control">' . h($displayStatus) . '</span>';
    } else {
        $html .= '<form method="post" class="summary-control-form">'
            . project_item_update_hidden_inputs($todo, [], $contextQuery)
            . '<select name="status" class="badge ' . h($displayStatus) . ' summary-control summary-status-control status-' . h(project_theme_key($status)) . '" title="Change status" aria-label="Change status from ' . h($displayStatus) . '" onchange="boardSummaryMetaChanged(this)">' . project_status_options(PROJECT_TODO_STATUSES, $status) . '</select></form>';
    }

    return $html . '</span><span class="summary-save-hint" data-summary-save-hint>Priority, Order and Status changes save at once</span>';
}

function project_sort_options(string $selected): string {
    $options = [
        'order' => 'Order',
        'item_number' => 'Item number',
        'title' => 'Title A–Z',
        'priority' => 'Priority',
        'status' => 'Status',
        'updated' => 'Recently updated',
    ];
    $html = '';
    foreach ($options as $value => $label) {
        $isSelected = $value === $selected ? ' selected' : '';
        $html .= '<option value="' . h($value) . '"' . $isSelected . '>' . h($label) . '</option>';
    }
    return $html;
}

function project_filter_and_sort_items(array $todos, string $sectionFilter, string $projectFilter, string $taskQ, mixed $taskStatus, string $taskPriority, string $taskSort, string $activeItemId = '', string $justEditedItemId = ''): array {
    $statusFilters = project_effective_item_status_filters($taskStatus);
    $recentRanks = project_recent_item_ranks($todos, project_recent_item_max_count());
    $visibleTodos = array_values(array_filter($todos, function ($todo) use ($sectionFilter, $projectFilter, $taskQ, $statusFilters, $taskPriority, $activeItemId, $justEditedItemId, $recentRanks) {
        if ($justEditedItemId !== '' && trim((string)($todo['id'] ?? '')) === $justEditedItemId && !project_item_is_binned($todo)) {
            return true;
        }
        if ($sectionFilter !== '' && ($todo['section'] ?? '') !== $sectionFilter) {
            return false;
        }
        if ($projectFilter === '__none') {
            if (trim((string)($todo['project'] ?? '')) !== '') {
                return false;
            }
        } elseif ($projectFilter !== '' && ($todo['project'] ?? '') !== $projectFilter) {
            return false;
        }
        $isBinned = project_item_is_binned($todo);
        if ($isBinned && !($sectionFilter === '' && $projectFilter === '' && $statusFilters === []) && !in_array('Bin', $statusFilters, true) && !in_array('inactive', $statusFilters, true)) {
            return false;
        }
        if (!project_item_status_matches_filter($todo, $statusFilters, $isBinned, $activeItemId, $recentRanks)) {
            return false;
        }
        if ($taskPriority === '__blank') {
            if (($todo['priority'] ?? '') !== '') {
                return false;
            }
        } elseif ($taskPriority !== '' && ($todo['priority'] ?? '') !== $taskPriority) {
            return false;
        }
        if ($taskQ !== '') {
            $haystack = strtolower((string)($todo['title'] ?? '') . "
" . (string)($todo['notes'] ?? ''));
            if (!str_contains($haystack, strtolower($taskQ))) {
                return false;
            }
        }
        return true;
    }));
    $statusOrder = ['Do' => 0, 'Ready' => 1, 'Open' => 2, 'Done' => 3, 'Blocked' => 4, 'Bin' => 5];
    $priorityOrder = ['Must' => 0, 'Should' => 1, 'Could' => 2, '' => 3, "Won't" => 4];
    usort($visibleTodos, function ($a, $b) use ($taskSort, $statusOrder, $priorityOrder) {
        return match ($taskSort) {
            'title' => strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')),
            'priority' => [
                $priorityOrder[$a['priority'] ?? ''] ?? 9,
                $statusOrder[project_item_display_status($a)] ?? 9,
                strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')),
            ] <=> [
                $priorityOrder[$b['priority'] ?? ''] ?? 9,
                $statusOrder[project_item_display_status($b)] ?? 9,
                strcasecmp((string)($b['title'] ?? ''), (string)($a['title'] ?? '')),
            ],
            'status' => [
                $statusOrder[project_item_display_status($a)] ?? 9,
                $priorityOrder[$a['priority'] ?? ''] ?? 9,
                strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')),
            ] <=> [
                $statusOrder[project_item_display_status($b)] ?? 9,
                $priorityOrder[$b['priority'] ?? ''] ?? 9,
                strcasecmp((string)($b['title'] ?? ''), (string)($a['title'] ?? '')),
            ],
            'recent',
            'updated' => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')),
            'item_number' => [
                (int)($a['id'] ?? 0),
            ] <=> [
                (int)($b['id'] ?? 0),
            ],
            'order', 'default' => [
                (int)($a['order'] ?? 1),
                (int)($a['id'] ?? 0),
            ] <=> [
                (int)($b['order'] ?? 1),
                (int)($b['id'] ?? 0),
            ],
        };
    });
    if ($recentRanks !== []) {
        $defaultRecentCount = project_recent_item_default_count();
        $isRecentView = in_array('recent', $statusFilters, true);
        foreach ($visibleTodos as &$todo) {
            $id = trim((string)($todo['id'] ?? ''));
            if (array_key_exists($id, $recentRanks) && ($isRecentView || $recentRanks[$id] <= $defaultRecentCount)) {
                $todo['_recent_rank'] = $recentRanks[$id];
                $todo['_recent_hidden'] = $isRecentView && $recentRanks[$id] > $defaultRecentCount;
            }
        }
        unset($todo);
    }
    return $visibleTodos;
}

// Legacy todo/task-named compatibility wrappers. The app's primary internal language is item.
function project_todo_is_binned(array $item): bool { return project_item_is_binned($item); }
function project_todo_display_status(array $item): string { return project_item_display_status($item); }
function project_task_style(array $item): string { return project_item_style($item); }
function project_todo_needs_voice_scope_prompt(array $item): bool { return project_item_needs_voice_scope_prompt($item); }
function project_todo_update_hidden_inputs(array $item, array $overrides = [], array $contextQuery = []): string { return project_item_update_hidden_inputs($item, $overrides, $contextQuery); }
function project_filter_and_sort_todos(array $items, string $sectionFilter, string $projectFilter, string $taskQ, mixed $taskStatus, string $taskPriority, string $taskSort): array { return project_filter_and_sort_items($items, $sectionFilter, $projectFilter, $taskQ, $taskStatus, $taskPriority, $taskSort); }

const PROJECT_QUOTA_STALE_SECONDS = 900;
const PROJECT_QUOTA_LOW_PERCENT = 5;

function project_quota_read(): ?array {
    $path = project_quota_path();
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data) || !is_array($data['meters'] ?? null) || $data['meters'] === []) {
        return null;
    }
    return $data;
}

function project_quota_level(?int $remaining): string {
    if ($remaining === null) return 'unknown';
    if ($remaining < PROJECT_QUOTA_LOW_PERCENT) return 'low';
    if ($remaining < 25) return 'warn';
    return 'ok';
}

function project_demo_quota_example(): array {
    $day = static fn(int $d, string $t): string => gmdate('j/M', time() + $d * 86400) . ' ' . $t;
    return ['updated' => gmdate('c'), 'meters' => [
        ['id' => 'ClS', 'label' => 'Claude session', 'remaining' => 72, 'reset' => $day(0, '18:00')],
        ['id' => 'ClW', 'label' => 'Claude week', 'remaining' => 18, 'reset' => $day(3, '09:00')],
        ['id' => 'CoS', 'label' => 'Codex session', 'remaining' => 45, 'reset' => $day(0, '17:30')],
        ['id' => 'CoW', 'label' => 'Codex week', 'remaining' => 3, 'reset' => $day(4, '09:00')],
    ]];
}

function project_limit_meter_html(): string {
    // Demo mode shows made-up amounts and never reads the real quota file.
    $data = project_demo_mode() ? project_demo_quota_example() : project_quota_read();
    if ($data === null) {
        return '';
    }
    $updated = strtotime((string)($data['updated'] ?? ''));
    $stale = $updated === false || (time() - $updated) > PROJECT_QUOTA_STALE_SECONDS;
    $bars = '';
    foreach ($data['meters'] as $meter) {
        if (!is_array($meter)) continue;
        $raw = $meter['remaining'] ?? null;
        $remaining = is_numeric($raw) ? max(0, min(100, (int)round((float)$raw))) : null;
        $level = $stale ? 'unknown' : project_quota_level($remaining);
        $full = trim((string)($meter['label'] ?? $meter['id'] ?? ''));
        $label = trim((string)($meter['id'] ?? $full));
        $reset = trim((string)($meter['reset'] ?? ''));
        $pct = $remaining === null ? '?' : $remaining . '%';
        $segments = '';
        for ($i = 0; $i < 5; $i++) {
            $lit = $remaining !== null && !$stale && ($i + 1) * 20 <= $remaining + 19;
            $segments .= '<i' . ($lit ? ' class="on"' : '') . '></i>';
        }
        $bars .= '<div class="limit-bar" data-level="' . h($level) . '" title="' . h($full . ': ' . $pct . ' left' . ($reset !== '' ? ', resets ' . $reset : '')) . '">'
            . '<div class="limit-label"><span>' . h($label) . '</span><b>' . h($pct) . '</b></div>'
            . '<div class="limit-track" aria-hidden="true">' . $segments . '</div>'
            . '</div>';
    }
    if ($bars === '') {
        return '';
    }
    $note = $stale ? '<div class="limit-stale-note">Stale: last reading is over 15 minutes old</div>' : '';
    return '<div class="limit-meter' . ($stale ? ' stale' : '') . '" role="group" aria-label="AI model limits remaining">' . $bars . $note . '</div>';
}
