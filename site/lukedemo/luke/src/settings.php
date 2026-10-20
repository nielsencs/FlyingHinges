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

const PROJECT_PALETTES = ['bright' => 'Bright', 'light' => 'Light', 'dark' => 'Dark', 'custom' => 'Custom'];

function project_palettes(): array {
    return [
        'bright' => project_default_colours(),
        'light' => [
            'priority_must' => '#b9ffd6', 'priority_should' => '#dcffb3', 'priority_could' => '#ffdbb0',
            'priority_wont' => '#ffb0b0', 'priority_blank' => '#ffc9e4',
            'status_open' => '#b8d0ff', 'status_doing' => '#b9ffd6', 'status_ready' => '#f8e6ae',
            'status_blocked' => '#c4c4c4', 'status_done' => '#d8c8ff',
        ],
        'dark' => [
            'priority_must' => '#1f9d57', 'priority_should' => '#6f9a2e', 'priority_could' => '#b8691a',
            'priority_wont' => '#b02a2a', 'priority_blank' => '#b0447f',
            'status_open' => '#2f5fcf', 'status_doing' => '#1f9d57', 'status_ready' => '#a8841a',
            'status_blocked' => '#3d3d3d', 'status_done' => '#6a43c9',
        ],
    ];
}

function project_valid_colour($value): bool {
    return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
}

function project_clean_colours(array $input): array {
    $out = [];
    foreach (array_keys(project_default_colours()) as $key) {
        if (project_valid_colour($input[$key] ?? null)) {
            $out[$key] = strtolower($input[$key]);
        }
    }
    return $out;
}

// Saved shape: label_mode, palette (bright|light|dark|custom), custom_colours (full set).
// Returned 'colours' = effective colours that differ from Bright (what must be emitted as CSS).
function project_settings(array $board): array {
    $settings = is_array($board['settings'] ?? null) ? $board['settings'] : [];
    $mode = (string)($settings['label_mode'] ?? 'words');
    $defaults = project_default_colours();
    $custom = project_clean_colours((array)($settings['custom_colours'] ?? []));
    $palette = (string)($settings['palette'] ?? '');
    $legacy = project_clean_colours((array)($settings['colours'] ?? []));
    if (!$custom && $legacy) {
        // Older saves stored only changed colours; they become the Custom palette.
        $custom = array_merge($defaults, $legacy);
        if ($palette === '') $palette = 'custom';
    }
    if (!isset(PROJECT_PALETTES[$palette])) $palette = 'bright';
    if ($palette === 'custom' && !$custom) $palette = 'bright';
    $customFull = array_merge($defaults, $custom);
    $effective = $palette === 'custom' ? $customFull : (project_palettes()[$palette] ?? $defaults);
    $diff = [];
    foreach ($effective as $key => $value) {
        if ($value !== $defaults[$key]) $diff[$key] = $value;
    }
    return [
        'label_mode' => in_array($mode, PROJECT_LABEL_MODES, true) ? $mode : 'words',
        'palette' => $palette,
        'custom_colours' => $custom ? $customFull : [],
        'effective' => $effective,
        'colours' => $diff,
    ];
}

function project_save_settings(array $board, array $post): array {
    $current = project_settings($board);
    $mode = (string)($post['label_mode'] ?? '');
    if (in_array($mode, PROJECT_LABEL_MODES, true)) {
        $current['label_mode'] = $mode;
    }
    $palette = (string)($post['palette'] ?? $current['palette']);
    if (!isset(PROJECT_PALETTES[$palette])) {
        $palette = $current['palette'];
    }
    if (!empty($post['reset_colours'])) {
        $palette = 'bright';
    }
    $base = $palette === 'custom'
        ? array_merge(project_default_colours(), $current['custom_colours'])
        : (project_palettes()[$palette] ?? project_default_colours());
    $submitted = [];
    if (empty($post['reset_colours'])) {
        foreach (array_keys(project_default_colours()) as $key) {
            if (project_valid_colour($post['colour_' . $key] ?? null)) {
                $submitted[$key] = strtolower($post['colour_' . $key]);
            }
        }
    }
    $merged = array_merge($base, $submitted);
    $customStored = $current['custom_colours'];
    if ($merged !== $base || ($palette === 'custom' && $submitted)) {
        // Editing any palette's colours makes the result the Custom palette.
        $palette = 'custom';
        $customStored = $merged;
    }
    if ($palette === 'custom' && !$customStored) {
        $palette = 'bright';
    }
    $board['settings'] = [
        'label_mode' => $current['label_mode'],
        'palette' => $palette,
        'custom_colours' => $customStored,
    ];
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

function project_settings_form_html(array $board, string $cancelUrl, string $returnInputs, bool $visible = false): string {
    $settings = project_settings($board);
    $names = project_colour_names();
    $palettes = project_palettes();
    $palettes['custom'] = $settings['custom_colours'] ?: $settings['effective'];
    $html = '<div class="modal-backdrop settings-backdrop" data-settings-modal' . ($visible ? '' : ' hidden') . '><form class="modal" method="post" data-settings-form data-saved-mode="' . h($settings['label_mode']) . '" data-saved-palette="' . h($settings['palette']) . '" data-has-custom="' . ($settings['custom_colours'] ? '1' : '0') . '" data-palettes="' . h((string)json_encode($palettes)) . '">';
    $html .= '<input type="hidden" name="action" value="save_settings">' . $returnInputs;
    $html .= '<h2>LUKE settings</h2><h3>Priority style</h3>';
    foreach (['words' => 'MoSCoW: Must, Should, Could, Won\'t', 'numbers' => 'Numeric priority: 1, 2, 3 (0 reserved for extreme urgency)'] as $mode => $text) {
        $html .= '<label class="setting-choice"><input type="radio" name="label_mode" value="' . h($mode) . '"' . ($settings['label_mode'] === $mode ? ' checked' : '') . ' data-label-mode> ' . h($text) . '</label>';
    }
    $html .= '<h3>Colours</h3><div class="setting-palettes">';
    foreach (PROJECT_PALETTES as $key => $label) {
        $html .= '<label class="setting-choice"><input type="radio" name="palette" value="' . h($key) . '"' . ($settings['palette'] === $key ? ' checked' : '') . ' data-palette> ' . h($label) . '</label>';
    }
    $html .= '</div><div class="setting-colours">';
    foreach (['priority' => 'Priority', 'status' => 'Status'] as $group => $heading) {
        $html .= '<div class="setting-colour-col"><h4>' . h($heading) . '</h4>';
        foreach ($names as $key => $name) {
            if (strpos($key, $group . '_') !== 0) continue;
            $value = $settings['effective'][$key];
            $label = trim(substr($name, strpos($name, ':') + 1));
            $html .= '<label class="setting-colour">' . h($label) . ' <input type="color" name="colour_' . h($key) . '" value="' . h($value) . '" data-colour-var="--' . h(str_replace('_', '-', $key)) . '-bg" data-colour-key="' . h($key) . '" data-default="' . h(project_default_colours()[$key]) . '" data-saved="' . h($value) . '"></label>';
        }
        $html .= '</div>';
    }
    $html .= '</div><div class="actions"><button type="button" class="small subtle" data-reset-colours>Reset colours to defaults</button></div>';
    $html .= '<div class="actions center-actions"><button class="small">Save</button><a class="button small danger" href="' . h($cancelUrl) . '" data-close-settings>Cancel</a></div></form></div>';
    return $html;
}
