<?php
// Demo mode: LUKE runs the real code against a throw-away sandbox board.
// Demo mode never reads or writes the real board file.

const PROJECT_DEMO_COOKIE = 'luke_demo';
const PROJECT_DEMO_SID_COOKIE = 'luke_demo_sid';
const PROJECT_DEMO_MAX_BYTES = 262144;
const PROJECT_DEMO_TTL_SECONDS = 86400;

function project_demo_forced(): bool {
    // A copy of LUKE deployed to a public demo site carries this marker file,
    // so every entry point into that copy is demo-only.
    return (defined('PROJECT_BOARD_DEMO_FORCED') && PROJECT_BOARD_DEMO_FORCED === true)
        || is_file(__DIR__ . '/demo-only.flag');
}

function project_demo_mode(): bool {
    if (project_demo_forced()) {
        return true;
    }
    return ($_COOKIE[PROJECT_DEMO_COOKIE] ?? '') === '1';
}

function project_demo_sandbox_id(): string {
    static $sid = null;
    if ($sid !== null) {
        return $sid;
    }
    $given = (string)($_COOKIE[PROJECT_DEMO_SID_COOKIE] ?? '');
    $sid = preg_match('/^[a-f0-9]{32}$/', $given) === 1 ? $given : bin2hex(random_bytes(16));
    $_COOKIE[PROJECT_DEMO_SID_COOKIE] = $sid;
    return $sid;
}

function project_demo_root(): string {
    return rtrim(sys_get_temp_dir(), '/') . '/luke-demo';
}

function project_demo_data_path(): string {
    return project_demo_root() . '/' . project_demo_sandbox_id() . '/board.json';
}

function project_demo_cookie_path(): string {
    $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    return $dir === '' ? '/' : $dir;
}

function project_demo_set_cookie(string $name, string $value, int $ttl): void {
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    setcookie($name, $value, [
        'expires' => $ttl > 0 ? time() + $ttl : time() - 3600,
        'path' => project_demo_cookie_path(),
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function project_demo_set_mode(bool $on): void {
    if (project_demo_forced()) {
        return;
    }
    project_demo_set_cookie(PROJECT_DEMO_COOKIE, '1', $on ? PROJECT_DEMO_TTL_SECONDS : 0);
    $_COOKIE[PROJECT_DEMO_COOKIE] = $on ? '1' : '0';
}

function project_demo_start(): void {
    if (!project_demo_mode()) {
        return;
    }
    project_demo_set_cookie(PROJECT_DEMO_SID_COOKIE, project_demo_sandbox_id(), PROJECT_DEMO_TTL_SECONDS);
    project_demo_purge_old();
}

function project_demo_purge_old(): void {
    $root = project_demo_root();
    if (!is_dir($root) || random_int(1, 20) !== 1) {
        return;
    }
    foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        if (preg_match('/^[a-f0-9]{32}$/', basename($dir)) !== 1 || filemtime($dir) > time() - PROJECT_DEMO_TTL_SECONDS) {
            continue;
        }
        foreach (glob($dir . '/{,.}*', GLOB_BRACE) ?: [] as $entry) {
            if (is_dir($entry) && !in_array(basename($entry), ['.', '..'], true)) {
                foreach (glob($entry . '/*') ?: [] as $inner) { @unlink($inner); }
                @rmdir($entry);
            } elseif (is_file($entry)) {
                @unlink($entry);
            }
        }
        @rmdir($dir);
    }
}

// Actions that would reach real audio, transcription, or hand-off machinery.
function project_demo_blocked_actions(): array {
    return ['record_audio', 'improve_item_transcript', 'use_improved_item_transcript', 'delete_item_audio', 'delete_todo_audio'];
}

function project_asset_base(): string {
    return defined('PROJECT_BOARD_ASSET_BASE') ? (string)PROJECT_BOARD_ASSET_BASE : 'assets/';
}

function project_demo_seed_board(): array {
    $now = 'Preview state';
    $samples = [
        ['Must', 'Open', false, 'Confirm venue access', 'community', 'events'],
        ['Should', 'Open', true, 'Draft welcome note', 'community', 'events'],
        ['Could', 'Do', false, 'Choose display colours', 'community', 'planning'],
        ["Won't", 'Do', true, 'Archive duplicate request', 'community', 'reference'],
        ['Must', 'Ready', false, 'Review safety checklist', 'community', 'review'],
        ['Should', 'Ready', true, 'Check catering numbers', 'household', 'paperwork'],
        ['Could', 'Done', false, 'Collect feedback cards', 'workshop', 'messages'],
        ["Won't", 'Done', true, 'Retire old poster idea', 'community', 'planning'],
        ['Must', 'Blocked', false, 'Resolve room booking', 'household', 'paperwork'],
        ['Should', 'Blocked', true, 'Wait for supplier reply', 'workshop', 'messages'],
    ];
    $items = [];
    foreach ($samples as $index => [$priority, $status, $binned, $title, $section, $project]) {
        $id = $index + 1;
        $notes = "User yyyy-mm-dd hh:mm\nTry changing the title, priority, status, notes, or Bin state. Changes stay in your private demo sandbox.";
        $item = [
            'id' => $id,
            'section' => $section,
            'project' => $project,
            'priority' => $priority,
            'status' => $status,
            'order' => $id,
            'title' => $title,
            'notes' => $notes,
            'updated_at' => $now,
        ];
        if ($binned) {
            $item['deleted_at'] = '2026-10-10 19:45 UTC';
        }
        $items[] = $item;
    }
    return [
        'updated_at' => $now,
        'sections' => [
            ['slug' => 'community', 'name' => 'Community', 'summary' => 'Planning and follow-up for a shared community event.', 'updated_at' => $now],
            ['slug' => 'household', 'name' => 'Household', 'summary' => 'Everyday home and family admin.', 'updated_at' => $now],
            ['slug' => 'workshop', 'name' => 'Workshop', 'summary' => 'Ideas, notes, recordings, and general capture.', 'updated_at' => $now],
        ],
        'projects' => [
            ['slug' => 'events', 'section' => 'community', 'name' => 'Events', 'status' => 'Active', 'now' => 'Representative focus showing Areas, Focuses, Items, Priority, Status and Bin.', 'next' => 'Add, edit, move and bin items to see how LUKE works.', 'blocked' => 'Nothing blocked', 'updated_at' => $now],
            ['slug' => 'planning', 'section' => 'community', 'name' => 'Planning', 'status' => 'Active', 'now' => 'Decisions and next steps for upcoming gatherings.', 'next' => 'Keep notes short and actionable.', 'blocked' => '', 'updated_at' => $now],
            ['slug' => 'reference', 'section' => 'community', 'name' => 'Reference', 'status' => 'Watching', 'now' => 'Background material for decisions.', 'next' => 'Use only when it helps the next action.', 'blocked' => '', 'updated_at' => $now],
            ['slug' => 'review', 'section' => 'community', 'name' => 'Review', 'status' => 'Active', 'now' => 'Items waiting for a careful check.', 'next' => 'Move checked work to Ready.', 'blocked' => '', 'updated_at' => $now],
            ['slug' => 'paperwork', 'section' => 'household', 'name' => 'Paperwork', 'status' => 'Active', 'now' => 'Invoices, receipts, and admin.', 'next' => 'Keep records tidy.', 'blocked' => '', 'updated_at' => $now],
            ['slug' => 'messages', 'section' => 'workshop', 'name' => 'Messages', 'status' => 'Active', 'now' => 'Voice notes and shared communications.', 'next' => 'Turn useful notes into clear items.', 'blocked' => '', 'updated_at' => $now],
        ],
        'items' => $items,
    ];
}

function project_demo_toggle_html(): string {
    if (project_demo_forced()) {
        return '<span class="demo-wordmark">Demo</span>';
    }
    if (project_demo_mode()) {
        return '<span class="demo-wordmark">Demo</span> <a class="button small subtle" href="./?demo=off">Leave demo</a>';
    }
    return '<a class="button small subtle demo-toggle" href="./?demo=on">Demo</a>';
}
