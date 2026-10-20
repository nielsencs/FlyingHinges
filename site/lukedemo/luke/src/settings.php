<?php
// LUKE settings: priority label set (Words/Numbers) and colour overrides.
// Items always store the internal priority key (Must/Should/Could/Won't); only display changes.

const PROJECT_LABEL_MODES = ['words', 'numbers'];
const PROJECT_NUMBER_LABELS = ['Must' => '0', 'Should' => '1', 'Could' => '2', "Won't" => '3'];

function project_default_colours(): array {
    return [
        'priority_must' => '#63ffa2',
        'priority_should' => '#c0ff7b',
        'priority_could' => '#ffb764',
        'priority_wont' => '#ff5656',
        'priority_blank' => '#ff8fc7',
        'status_open' => '#6495ff',
        'status_doing' => '#63ffa2',
        'status_ready' => '#f0c75e',
        'status_blocked' => '#616161',
        'status_done' => '#a985ff',
    ];
}

function project_colour_names(): array {
    return [
        'priority_must' => 'Priority: Must (0)',
        'priority_should' => 'Priority: Should (1)',
        'priority_could' => 'Priority: Could (2)',
        'priority_wont' => 'Priority: Won\'t (3)',
        'priority_blank' => 'Priority: none',
        'status_open' => 'Status: Open',
        'status_doing' => 'Status: Do',
        'status_ready' => 'Status: Ready',
        'status_blocked' => 'Status: Blocked',
        'status_done' => 'Status: Done',
    ];
}

function project_settings(array $board): array {
    $settings = is_array($board['settings'] ?? null) ? $board['settings'] : [];
    $mode = (string)($settings['label_mode'] ?? 'words');
    $colours = [];
    $defaults = project_default_colours();
    foreach ((array)($settings['colours'] ?? []) as $key => $value) {
        if (isset($defaults[$key]) && is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            $colours[$key] = strtolower($value);
        }
    }
    return ['label_mode' => in_array($mode, PROJECT_LABEL_MODES, true) ? $mode : 'words', 'colours' => $colours];
}

function project_save_settings(array $board, array $post): array {
    $current = project_settings($board);
    $mode = (string)($post['label_mode'] ?? '');
    if (in_array($mode, PROJECT_LABEL_MODES, true)) {
        $current['label_mode'] = $mode;
    }
    if (!empty($post['reset_colours'])) {
        $current['colours'] = [];
    } else {
        foreach (array_keys(project_default_colours()) as $key) {
            $value = $post['colour_' . $key] ?? null;
            if (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) && strtolower($value) !== project_default_colours()[$key]) {
                $current['colours'][$key] = strtolower($value);
            } elseif (is_string($value) && strtolower($value) === project_default_colours()[$key]) {
                unset($current['colours'][$key]);
            }
        }
    }
    $board['settings'] = $current;
    return $board;
}

function project_use_settings(array $board): void {
    $GLOBALS['project_active_settings'] = project_settings($board);
}

function project_active_label_mode(): string {
    return (string)(($GLOBALS['project_active_settings']['label_mode'] ?? 'words'));
}

function project_priority_label(string $priority, ?array $board = null): string {
    $mode = $board !== null ? project_settings($board)['label_mode'] : project_active_label_mode();
    if ($mode === 'numbers' && isset(PROJECT_NUMBER_LABELS[$priority])) {
        return PROJECT_NUMBER_LABELS[$priority];
    }
    return $priority;
}

function project_priority_values(?array $board = null): array {
    $mode = $board !== null ? project_settings($board)['label_mode'] : project_active_label_mode();
    if ($mode === 'numbers') {
        return ['', 'Should', 'Could', "Won't", 'Must'];
    }
    return PROJECT_PRIORITIES;
}

function project_colour_overrides_css(array $board): string {
    $colours = project_settings($board)['colours'];
    if (!$colours) {
        return '';
    }
    $lines = [];
    foreach ($colours as $key => $value) {
        $lines[] = '--' . str_replace('_', '-', $key) . '-bg: ' . $value . ';';
    }
    return '<style>:root { ' . implode(' ', $lines) . " }</style>\n";
}

function project_settings_form_html(array $board, string $cancelUrl, string $returnInputs): string {
    $settings = project_settings($board);
    $defaults = project_default_colours();
    $html = '<div class="modal-backdrop"><form class="modal dirty-watch" method="post">';
    $html .= '<input type="hidden" name="action" value="save_settings">' . $returnInputs;
    $html .= '<h2>LUKE settings</h2><h3>Priority labels</h3>';
    foreach (['words' => 'Words: Must, Should, Could, Won\'t', 'numbers' => 'Numbers: 0 (Must), 1, 2, 3'] as $mode => $text) {
        $html .= '<label class="setting-choice"><input type="radio" name="label_mode" value="' . h($mode) . '"' . ($settings['label_mode'] === $mode ? ' checked' : '') . '> ' . h($text) . '</label>';
    }
    $html .= '<h3>Colours</h3><div class="setting-colours">';
    foreach (project_colour_names() as $key => $name) {
        $value = $settings['colours'][$key] ?? $defaults[$key];
        $html .= '<label class="setting-colour">' . h($name) . ' <input type="color" name="colour_' . h($key) . '" value="' . h($value) . '"></label>';
    }
    $html .= '</div><label class="setting-choice"><input type="checkbox" name="reset_colours" value="1"> Reset all colours to defaults when saving</label>';
    $html .= '<div class="actions center-actions"><button class="small">Save</button><a class="button small danger" href="' . h($cancelUrl) . '">Cancel</a></div></form></div>';
    return $html;
}
