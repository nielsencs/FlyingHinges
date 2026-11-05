<?php
// Agent door: the one way an agent (e.g. Joel) changes LUKE.
//
// The agent asks over HTTP from the server itself; LUKE does the write as its own
// site user, with the same save, backup and note-stamp code the browser uses. The
// agent never touches LUKE's files. Each agent has a secret key: LUKE keeps only
// its SHA-256 hash (agent-keys.json beside the data), the agent keeps the key.
//
// Request:  POST index.php?agent_door=ACTION   Authorization: Bearer KEY
//           JSON body (or multipart for `attach`).
// Actions:  read_item, read_board, read_state, add_note, set_status, fold, attach, set_light, clear_light.

const AGENT_DOOR_FILE_NAME_RE = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,120}$/';
const AGENT_DOOR_MIME = [
    'apk' => 'application/vnd.android.package-archive', 'pdf' => 'application/pdf', 'zip' => 'application/zip',
    'txt' => 'text/plain', 'png' => 'image/png', 'jpg' => 'image/jpeg',
];
const AGENT_DOOR_MAX_FOLD_DEPTH = 3;

class AgentDoorError extends RuntimeException {}

function agent_door_keys_path(): string {
    $path = getenv('PROJECT_BOARD_AGENT_KEYS');
    if ($path !== false && trim($path) !== '') {
        return $path;
    }
    return dirname(project_data_path()) . '/agent-keys.json';
}

// Which agent does this key belong to? Null when the key is missing or unknown.
function agent_door_actor(string $key): ?string {
    if ($key === '') {
        return null;
    }
    $keys = json_decode((string)@file_get_contents(agent_door_keys_path()), true);
    if (!is_array($keys)) {
        return null;
    }
    $hash = hash('sha256', $key);
    foreach ($keys as $name => $row) {
        $stored = is_array($row) ? (string)($row['sha256'] ?? '') : '';
        if ($stored !== '' && hash_equals(strtolower($stored), $hash)) {
            return (string)$name;
        }
    }
    return null;
}

function agent_door_bearer_key(): string {
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    return preg_match('/^Bearer\s+(\S+)$/i', trim($header), $m) ? $m[1] : '';
}

// Caddy hands PHP the real client address, so this is only true for a caller on the
// server itself. A forwarded-for entry from anywhere else means a proxy is involved.
function agent_door_from_server_itself(array $server): bool {
    $loopback = ['127.0.0.1', '::1'];
    if (!in_array((string)($server['REMOTE_ADDR'] ?? ''), $loopback, true)) {
        return false;
    }
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP'] as $header) {
        foreach (array_filter(array_map('trim', explode(',', (string)($server[$header] ?? '')))) as $ip) {
            if (!in_array($ip, $loopback, true)) {
                return false;
            }
        }
    }
    return empty($server['HTTP_FORWARDED']);
}

function agent_door_reply(int $code, array $body): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit;
}

function agent_door_locked(callable $fn) {
    $handle = fopen(project_data_path() . '.lock', 'c');
    if ($handle === false) {
        throw new AgentDoorError('LUKE could not take its save lock', 500);
    }
    flock($handle, LOCK_EX);
    try {
        return $fn();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

// ---- Fold helpers (Notes <details> blocks). Wrap only: no note text is changed or removed.

function agent_door_fold_tag_match(string $line): ?string {
    return preg_match('/^[ \t]*<\s*(\/?)\s*details\b[^>]*>/i', $line, $m) ? $m[1] : null;
}

function agent_door_split_entries(string $notes): array {
    $entries = [];
    $cur = [];
    $depth = 0;
    $flush = function () use (&$entries, &$cur) {
        $entries[] = trim(implode("\n", $cur), "\n");
        $cur = [];
    };
    foreach (explode("\n", $notes) as $line) {
        $top = $depth === 0;
        $hasText = trim(implode('', $cur)) !== '';
        if ($top && preg_match('/^[A-Za-z][A-Za-z ]{0,30} \d{4}-\d{2}-\d{2} \d{1,2}:\d{2}\s*$/', $line) && $cur && $hasText) {
            $flush();
        }
        if ($top && str_starts_with(strtolower(ltrim($line)), '<details') && $cur && $hasText) {
            $flush();
        }
        $cur[] = $line;
        $closing = agent_door_fold_tag_match($line);
        if ($closing !== null) {
            $depth = max(0, $depth + ($closing !== '' ? -1 : 1));
        }
        if ($depth === 0 && str_ends_with(strtolower(trim($line)), '</details>')) {
            $flush();
        }
    }
    if (trim(implode('', $cur)) !== '') {
        $flush();
    }
    return $entries;
}

function agent_door_is_fold(string $entry): bool {
    return str_starts_with(strtolower(ltrim($entry)), '<details');
}

function agent_door_fold_summary($summary): string {
    $summary = trim(preg_replace('/\s+/', ' ', (string)$summary) ?? '');
    if ($summary === '') {
        throw new AgentDoorError('every fold needs a summary', 422);
    }
    return $summary;
}

function agent_door_fold_block(string $summary, array $group): string {
    return "<details>\n<summary>" . $summary . "</summary>\n\n" . implode("\n\n", $group) . "\n\n</details>";
}

// folds: list of [count|'*', summary]. '*' = everything unfolded before Carl's latest note.
function agent_door_fold_notes(string $notes, array $folds): string {
    if (!$folds) {
        return $notes;
    }
    $entries = agent_door_split_entries($notes);
    $carlLast = count($entries);
    foreach ($entries as $i => $e) {
        if (str_starts_with($e, 'Carl ')) {
            $carlLast = $i;
        }
    }
    $start = 0;
    while ($start < count($entries) && agent_door_is_fold($entries[$start])) {
        $start++;
    }
    $pos = $start;
    $blocks = [];
    foreach ($folds as $fold) {
        $summary = agent_door_fold_summary($fold[1] ?? '');
        $count = $fold[0] ?? 0;
        if ($count === '*') {
            $count = $carlLast - $pos;
        }
        $count = (int)$count;
        if ($count < 1 || $pos + $count > $carlLast) {
            throw new AgentDoorError('fold would be empty or would cover the latest Carl note', 422);
        }
        $blocks[] = agent_door_fold_block($summary, array_slice($entries, $pos, $count));
        $pos += $count;
    }
    return implode("\n\n", array_merge(array_slice($entries, 0, $start), $blocks, array_slice($entries, $pos)));
}

function agent_door_relabel_fold(string $notes, int $index, string $summary): string {
    $summary = agent_door_fold_summary($summary);
    $entries = agent_door_split_entries($notes);
    $seen = 0;
    foreach ($entries as $i => $e) {
        if (agent_door_is_fold($e) && ++$seen === $index) {
            $new = preg_replace_callback('/<summary>.*?<\/summary>/is', fn() => '<summary>' . $summary . '</summary>', $e, 1, $n);
            if (!$n) {
                throw new AgentDoorError("fold $index has no summary tag", 422);
            }
            $entries[$i] = $new;
            return implode("\n\n", $entries);
        }
    }
    throw new AgentDoorError("fold $index not found", 422);
}

function agent_door_fold_depth(string $text): int {
    $depth = $best = 0;
    foreach (explode("\n", $text) as $line) {
        $closing = agent_door_fold_tag_match($line);
        if ($closing !== null) {
            $depth = max(0, $depth + ($closing !== '' ? -1 : 1));
            $best = max($best, $depth);
        }
    }
    return $best;
}

// Nest top-level folds first..last (1-based, neighbours) inside one new fold. Nothing is deleted.
function agent_door_merge_folds(string $notes, int $first, int $last, string $summary): string {
    $summary = agent_door_fold_summary($summary);
    if ($first < 1 || $last <= $first) {
        throw new AgentDoorError('merge needs a range of at least two folds, e.g. 1-3', 422);
    }
    $entries = agent_door_split_entries($notes);
    $idx = array_keys(array_filter($entries, 'agent_door_is_fold'));
    if ($last > count($idx)) {
        throw new AgentDoorError("fold $last not found", 422);
    }
    $chosen = array_slice($idx, $first - 1, $last - $first + 1);
    if (end($chosen) - $chosen[0] !== count($chosen) - 1) {
        throw new AgentDoorError('folds to merge must be neighbours', 422);
    }
    $group = array_slice($entries, $chosen[0], count($chosen));
    if (agent_door_fold_depth(implode("\n\n", $group)) + 1 > AGENT_DOOR_MAX_FOLD_DEPTH) {
        throw new AgentDoorError('folds may be at most ' . AGENT_DOOR_MAX_FOLD_DEPTH . ' deep', 422);
    }
    $block = agent_door_fold_block($summary, $group);
    return implode("\n\n", array_merge(array_slice($entries, 0, $chosen[0]), [$block], array_slice($entries, end($chosen) + 1)));
}

// ---- Actions

function agent_door_item_index(array $board, int $id): int {
    foreach ($board['items'] as $i => $item) {
        if ((int)($item['id'] ?? 0) === $id) {
            return $i;
        }
    }
    throw new AgentDoorError("item #$id not found", 404);
}

function agent_door_attachment_entry(array $p, array $files): array {
    $file = $files['file'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) {
        throw new AgentDoorError('attachment file did not arrive', 422);
    }
    $name = (string)($p['as'] ?? '');
    if (!preg_match(AGENT_DOOR_FILE_NAME_RE, $name) || str_contains($name, '..')) {
        throw new AgentDoorError('unsafe attachment file name', 422);
    }
    $dest = project_attachment_path($name);
    if (!move_uploaded_file((string)$file['tmp_name'], $dest)) {
        throw new AgentDoorError('attachment could not be stored', 500);
    }
    @chmod($dest, 0660);
    return [
        'file' => $name,
        'name' => (string)($p['name'] ?? '') !== '' ? (string)$p['name'] : $name,
        'mime' => AGENT_DOOR_MIME[strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? 'application/octet-stream',
        'size' => filesize($dest),
        'sha256' => hash_file('sha256', $dest),
        'created_at' => gmdate('Y-m-d H:i') . ' UTC',
    ];
}

// add_note / set_status / fold / attach: one save, so a handoff is never seen half-done.
function agent_door_change_item(string $actor, string $action, array $p, array $files): array {
    $id = (int)($p['id'] ?? 0);
    $text = trim((string)($p['text'] ?? ''));
    $status = trim((string)($p['status'] ?? ''));
    $folds = (array)($p['fold'] ?? []);
    $relabels = (array)($p['relabel'] ?? []);
    $merges = (array)($p['merge'] ?? []);
    $needs = ['add_note' => $text !== '', 'set_status' => $status !== '', 'fold' => $folds || $relabels || $merges, 'attach' => isset($files['file'])];
    if (!$needs[$action]) {
        throw new AgentDoorError("$action needs its main parameter", 422);
    }
    if ($status !== '' && !in_array($status, PROJECT_ITEM_STATUSES, true)) {
        throw new AgentDoorError('unknown status', 422);
    }
    if ($status === 'Done') {
        throw new AgentDoorError("Done is Carl's to set", 403);
    }
    $at = (string)($p['at'] ?? '');
    if ($at !== '' && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $at)) {
        throw new AgentDoorError('bad note time', 422);
    }
    return agent_door_locked(function () use ($actor, $id, $text, $status, $folds, $relabels, $merges, $at, $p, $files) {
        $board = project_load_board();
        $i = agent_door_item_index($board, $id);
        $item = $board['items'][$i];
        $notes = (string)($item['notes'] ?? '');
        $stamped = '';
        if ($text !== '') {
            // The door adds the stamp itself, from the key's owner; drop any the caller wrote.
            $text = trim(preg_replace('/^(?:' . preg_quote($actor, '/') . '\s+\d{4}-\d{2}-\d{2}\s+\d{1,2}:\d{2}\s*\n)+/', '', $text . "\n") ?? $text);
            $stamped = $actor . ' ' . ($at !== '' ? $at : project_note_stamp_datetime()) . "\n" . $text;
        }
        $attachment = isset($files['file']) ? agent_door_attachment_entry($p, $files) : null;
        if ($stamped === '' || !str_contains($notes, $stamped)) {
            foreach ($relabels as $r) {
                $notes = agent_door_relabel_fold($notes, (int)($r[0] ?? 0), (string)($r[1] ?? ''));
            }
            foreach ($merges as $m) {
                $notes = agent_door_merge_folds($notes, (int)($m[0] ?? 0), (int)($m[1] ?? 0), (string)($m[2] ?? ''));
            }
            if ($folds && trim($notes) !== '') {
                $notes = agent_door_fold_notes($notes, $folds);
            }
            if ($stamped !== '') {
                $notes = trim($notes) !== '' ? rtrim($notes, "\n") . "\n\n" . $stamped : $stamped;
            }
        }
        $item['notes'] = $notes;
        if ($status !== '') {
            $item['status'] = $status;
        }
        if ($attachment) {
            $kept = array_values(array_filter((array)($item['attachments'] ?? []), fn($a) => ($a['file'] ?? '') !== $attachment['file']));
            $item['attachments'] = array_merge($kept, [$attachment]);
        }
        $now = project_now();
        $item['changed_at'] = $now;
        $item['updated_at'] = $now;
        $board['items'][$i] = $item;
        project_save_board($board, $actor);
        return ['ok' => true, 'item' => $id, 'status' => (string)$item['status'], 'note' => $stamped];
    });
}

function agent_door_write_light(array $item): void {
    $path = project_activity_path();
    $payload = [
        'id' => $item['id'], 'title' => (string)($item['title'] ?? ''), 'priority' => (string)($item['priority'] ?? ''),
        'status' => (string)($item['status'] ?? ''), 'active_at' => (new DateTime('now', new DateTimeZone('Europe/London')))->format('c'),
    ];
    $tmp = tempnam(dirname($path), '.activity.');
    file_put_contents($tmp, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    chmod($tmp, 0664);
    rename($tmp, $path);
}

// Light off. With an id, only if that item is the lit one (a handoff must not dim another item's light).
function agent_door_clear_light($id): bool {
    $path = project_activity_path();
    if (!is_file($path)) {
        return false;
    }
    if ($id !== null) {
        $lit = json_decode((string)@file_get_contents($path), true);
        if (!is_array($lit) || (string)($lit['id'] ?? '') !== (string)$id) {
            return false;
        }
    }
    return @unlink($path);
}

function agent_door_state(): array {
    $activity = json_decode((string)@file_get_contents(project_activity_path()), true);
    return ['paused' => project_agent_paused(), 'activity' => is_array($activity) ? $activity : null];
}

function agent_door_handle(string $action): void {
    try {
        if (project_demo_mode()) {
            agent_door_reply(404, ['error' => 'not available']);
        }
        if (!agent_door_from_server_itself($_SERVER)) {
            agent_door_reply(403, ['error' => 'the agent door only answers the server itself']);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            agent_door_reply(405, ['error' => 'POST only']);
        }
        $actor = agent_door_actor(agent_door_bearer_key());
        if ($actor === null) {
            usleep(400000);
            agent_door_reply(401, ['error' => 'missing or unknown agent key']);
        }
        $p = stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'json') !== false
            ? (json_decode((string)file_get_contents('php://input'), true) ?: [])
            : $_POST;
        switch ($action) {
            case 'read_board':
                // The stored file verbatim, so a hash of it matches what the agent saw before.
                $raw = (string)@file_get_contents(project_data_path());
                if (json_decode($raw) === null) {
                    throw new AgentDoorError('LUKE state could not be read', 500);
                }
                http_response_code(200);
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store');
                echo '{"ok":true,"state":' . json_encode(agent_door_state(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ',"board":' . $raw . "}\n";
                exit;
            case 'read_state':
                agent_door_reply(200, ['ok' => true, 'state' => agent_door_state()]);
            case 'read_item':
                $board = project_load_board();
                $item = $board['items'][agent_door_item_index($board, (int)($p['id'] ?? 0))];
                agent_door_reply(200, ['ok' => true, 'item' => $item]);
            case 'add_note':
            case 'set_status':
            case 'fold':
            case 'attach':
                agent_door_reply(200, agent_door_change_item($actor, $action, $p, $_FILES));
            case 'set_light':
                $board = project_load_board();
                agent_door_write_light($board['items'][agent_door_item_index($board, (int)($p['id'] ?? 0))]);
                agent_door_reply(200, ['ok' => true]);
            case 'clear_light':
                $id = isset($p['id']) && $p['id'] !== '' ? $p['id'] : null;
                agent_door_reply(200, ['ok' => true, 'cleared' => agent_door_clear_light($id)]);
            default:
                agent_door_reply(404, ['error' => 'unknown action']);
        }
    } catch (AgentDoorError $e) {
        agent_door_reply($e->getCode() ?: 400, ['error' => $e->getMessage()]);
    } catch (Throwable $e) {
        error_log('agent door: ' . $e->getMessage());
        agent_door_reply(500, ['error' => 'LUKE could not do that']);
    }
}
