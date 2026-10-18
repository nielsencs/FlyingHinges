<?php
// The public LUKE demo is the real LUKE code, demo-only, with no real data path.
function fail(string $m): void { fwrite(STDERR, $m . "\n"); exit(1); }
$dir = __DIR__ . '/../site/lukedemo';
foreach (['index.php', 'luke/index.php', 'luke/src/demo.php', 'luke/src/demo-only.flag', 'luke/assets/theme.css', 'luke/assets/project-board.css'] as $f) {
    if (!is_file("$dir/$f")) fail("missing $f (run scripts/sync-luke-demo.py)");
}
foreach (['index.php', 'luke/index.php'] as $entry) {
    $html = shell_exec('cd ' . escapeshellarg("$dir") . ' && TMPDIR=' . escapeshellarg(sys_get_temp_dir()) . ' php ' . escapeshellarg("$dir/$entry"));
    if (!is_string($html) || !str_contains($html, 'Confirm venue access') || !str_contains($html, 'Demo')) fail("$entry did not render the demo");
    if (str_contains($html, 'demo=off')) fail("$entry offers a way out of demo");
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
    if (preg_match('/\.(php)$/', $file) && preg_match('#/srv/www/[a-z]+\.biblestudyman|state/project/board\.json#', file_get_contents($file))) {
        fail('real board path found in ' . $file);
    }
}
echo "lukedemo test passed\n";
