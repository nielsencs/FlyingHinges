<?php
$boardConfig = [
    'version' => $boardVersion,
    'cancelUrl' => $cancelUrl,
    'sectionFilter' => $sectionFilter,
    'projectFilter' => $projectFilter,
    'taskQ' => $taskQ,
    'taskPriority' => $taskPriority,
    'taskStatus' => $taskStatus,
    'taskSort' => $taskSort,
    'escapeCancel' => in_array($view, ['add_section', 'edit_section', 'add_project', 'edit_project'], true),
];
$boardJsFile = dirname(__DIR__, 2) . '/assets/project-board.js';
$boardJsVersion = is_file($boardJsFile) ? (string)filemtime($boardJsFile) : '0';
?>
<script>window.boardConfig = <?= json_encode($boardConfig, JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= h(project_asset_base()) ?>project-board.js?v=<?= h($boardJsVersion) ?>"></script>
