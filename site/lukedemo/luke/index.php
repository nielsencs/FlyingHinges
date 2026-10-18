<?php
require_once __DIR__ . '/src/bootstrap.php';

if (isset($_GET['demo']) && !project_demo_forced()) {
    project_demo_set_mode((string)$_GET['demo'] === 'on');
    header('Location: ./', true, 303);
    exit;
}
project_demo_start();
if (project_demo_mode() && (isset($_GET['audio']) || isset($_GET['attachment']))) {
    http_response_code(404);
    echo 'Not available in demo';
    exit;
}
if (isset($_GET['audio'])) {
    project_audio_response((string)$_GET['audio']);
    exit;
}
if (isset($_GET['attachment'])) {
    project_attachment_response((string)$_GET['attachment']);
    exit;
}


$board = project_load_board();
if (isset($_GET['board_version'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'version' => project_browser_version($board, $_GET),
        'updated_at' => (string)($board['updated_at'] ?? ''),
    ], JSON_UNESCAPED_SLASHES) . "\n";
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (project_field($_POST, 'action') === 'undo_last_change') {
        project_restore_latest_backup();
        header('Location: ' . project_redirect_after_action($board, $_POST), true, 303);
        exit;
    }
    if (project_demo_mode() && in_array(project_field($_POST, 'action'), project_demo_blocked_actions(), true)) {
        header('Location: ./', true, 303);
        exit;
    }
    $redirect = project_field($_POST, 'action') === 'delete_todo' ? project_redirect_after_action($board, $_POST) : '';
    $board = project_apply_action($board, $_POST, $_FILES);
    project_save_board($board);
    if ($redirect === '') {
        $redirect = project_redirect_after_action($board, $_POST);
    }
    header('Location: ' . $redirect, true, 303);
    exit;
}

echo project_render_board($board, $_GET);
