<?php
$demoPath = __DIR__ . '/../site/lukedemo/index.php';
if (!is_file($demoPath)) {
    fwrite(STDERR, "LUKE demo page missing\n");
    exit(1);
}
$html = shell_exec('php ' . escapeshellarg($demoPath));
if (!is_string($html) || $html === '') {
    fwrite(STDERR, "LUKE demo did not render\n");
    exit(1);
}
$required = [
    '<title>LUKE Demo</title>',
    'LUKE Demo Areas, Focuses and Items.',
    'class="luke-wordmark"',
    'Items: All items',
    'data-open-add-item-modal',
    'class="task-card task-row"',
    'Demo only: nothing here writes to the real LUKE board',
    'data-priority="must"',
    'data-priority="should"',
    'data-priority="could"',
    'data-priority="wont"',
    'data-demo-status="open"',
    'data-demo-status="do"',
    'data-demo-status="ready"',
    'data-demo-status="done"',
    'data-demo-status="blocked"',
    'data-binned="1"',
    'User yyyy-mm-dd hh:mm',
    'Agent active',
    'setTimeout(demoStartFakeAgentActivity, 3000',
    'setTimeout(demoAddFakeAgentReport, 7000',
    'This is where the Agent would report back with what they\'ve done.',
];
foreach ($required as $needle) {
    if (!str_contains($html, $needle)) {
        fwrite(STDERR, "LUKE demo missing: {$needle}\n");
        exit(1);
    }
}
if (str_contains($html, "fetch('./?board_version=1'") || str_contains($html, 'Try a fake item') || str_contains($html, 'Every Priority + Status + Bin combination')) {
    fwrite(STDERR, "LUKE demo should look like real LUKE and must not call the backend\n");
    exit(1);
}
$comboPattern = "/data-priority=\"(must|should|could|wont)\"[^>]*data-demo-status=\"(open|do|ready|done|blocked)\"[^>]*data-binned=\"(0|1)\"/";
preg_match_all($comboPattern, $html, $matches, PREG_SET_ORDER);
$combos = [];
foreach ($matches as $match) {
    $combos[$match[1] . '|' . $match[2] . '|' . $match[3]] = true;
}
if (count($combos) < 40) {
    fwrite(STDERR, 'LUKE demo should include every Priority + Status + binned combination; found ' . count($combos) . "\n");
    exit(1);
}
