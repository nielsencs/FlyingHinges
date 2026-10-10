<?php
$demoPath = __DIR__ . '/../site/lukedemo/index.php';
if (!is_file($demoPath)) {
    fwrite(STDERR, "LUKE demo page missing\n");
    exit(1);
}
$html = shell_exec('php ' . escapeshellarg($demoPath));
$required = [
    'LUKE playground',
    'Demo only: nothing here writes to the real LUKE board',
    'Agent active',
    'User yyyy-mm-dd hh:mm',
    'setTimeout(startFakeAgentActivity, 3000',
    'setTimeout(addFakeAgentReport, 7000',
    "This is where the Agent would report back with what they've done.",
];
foreach ($required as $needle) {
    if (!is_string($html) || !str_contains($html, $needle)) {
        fwrite(STDERR, "LUKE demo missing: {$needle}\n");
        exit(1);
    }
}
$comboPattern = "/data-priority=\"(Must|Should|Could|Won't)\"[^>]*data-status=\"(Open|Do|Ready|Done|Blocked)\"[^>]*data-binned=\"(false|true)\"/";
preg_match_all($comboPattern, $html, $matches, PREG_SET_ORDER);
$combos = [];
foreach ($matches as $match) {
    $combos[$match[1] . '|' . $match[2] . '|' . $match[3]] = true;
}
if (count($combos) !== 40) {
    fwrite(STDERR, 'LUKE demo should include 40 combinations; found ' . count($combos) . "\n");
    exit(1);
}
