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
$source = file_get_contents($demoPath);
$required = [
    '<title>LUKE Demo</title>',
    'class="luke-wordmark"',
    '<span class="demo-wordmark">Demo</span>',
    'Areas, Focuses and Items.',
    'Items: All items',
    'data-open-add-item-modal',
    'class="task-card task-row"',
    'Community',
    'Events',
    'Confirm venue access',
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
if (preg_match('/\bproject_save_board\b|PROJECT_BOARD_PATH/', $source) || str_contains($html, "fetch('./?board_version=1'")) {
    fwrite(STDERR, "LUKE demo must not save state or call the backend\n");
    exit(1);
}
$forbidden = [
    'Demo only:',
    'nothing here writes to the real LUKE board',
    'Public LUKE Demo',
    'Try adding',
    'Every Priority + Status + Bin combination',
    'Tooling',
    'BSM',
    'RFX',
    'Area A',
    'Focus A',
    'Item A',
    'luke-demo',
];
foreach ($forbidden as $needle) {
    if (str_contains($html, $needle)) {
        fwrite(STDERR, "LUKE demo interface should not include: {$needle}\n");
        exit(1);
    }
}
$comboPattern = "/data-priority=\"(must|should|could|wont)\"[^>]*data-demo-status=\"(open|do|ready|done|blocked)\"[^>]*data-binned=\"(0|1)\"/";
preg_match_all($comboPattern, $html, $matches, PREG_SET_ORDER);
$combos = [];
foreach ($matches as $match) {
    $combos[$match[1] . '|' . $match[2] . '|' . $match[3]] = true;
}
if (count($combos) < 8 || count($combos) > 12) {
    fwrite(STDERR, 'LUKE demo should use a reduced representative sample; found ' . count($combos) . "\n");
    exit(1);
}
$visibleHtml = preg_replace('/<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>/is', '', $html);
$demoOccurrences = substr_count(strip_tags((string)$visibleHtml), 'Demo');
if ($demoOccurrences > 2) {
    fwrite(STDERR, "LUKE demo should only add Demo to the LUKE identity, not repeat it around the interface\n");
    exit(1);
}
