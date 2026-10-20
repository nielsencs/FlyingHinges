<?php
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LUKE<?= project_demo_mode() ? ' Demo' : '' ?></title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%23254260'/%3E%3Cpath d='M43 7a15 15 0 0 0-17 20L9 44a6 6 0 0 0 8 8l17-17A15 15 0 0 0 54 18l-10 10-8-8L46 10a15 15 0 0 0-3-3z' fill='%23f3f5fa'/%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= h(project_asset_base()) ?>theme.css">
<link rel="stylesheet" href="<?= h(project_asset_base()) ?>project-board.css">
<?= project_colour_overrides_css($board) ?>
<?php include __DIR__ . '/browser-script.php'; ?>
</head>
<body>
<div class="sticky-board-top"><header>
  <?= project_site_agent_active_light($activeItemId, ['section_filter' => $sectionFilter, 'project_filter' => $projectFilter]) ?>
  <h1 class="luke-wordmark" aria-label="LUKE">
    <span class="luke-letter">L<span>ogical</span></span>&nbsp;<span class="luke-letter">U<span>nifying</span></span>&nbsp;<span class="luke-letter">K<span>nowledge</span></span>&nbsp;<span class="luke-letter">E<span>ngine</span></span>
  </h1>
  <div class="demo-controls"><a class="button small subtle" href="<?= h('?' . http_build_query(['view' => 'settings'] + $fullContextQuery)) ?>">Settings</a> <?= project_demo_toggle_html() ?></div>
  <div class="muted">LUKE Areas, Focuses and Items.<br> Updated <?= h((string)($board['updated_at'] ?? '')) ?>.</div>
  <div class="remote-update-notice muted" hidden>Update available; save or reload to avoid losing your edits.</div>
</header>
<?php include __DIR__ . '/context-controls.php'; ?>
<?php include __DIR__ . '/item-filters.php'; ?>
</div>
<main>
<?php include __DIR__ . '/edit-modals.php'; ?>
<section class="task-list-section">
  <form id="empty-bin" method="post" onsubmit="return confirm('Permanently delete everything in the Bin? This cannot be undone.')"><input type="hidden" name="action" value="empty_bin"><?= $returnContextInputs ?></form>
  <form id="undo-last-change" method="post" onsubmit="return confirm('Undo the last saved LUKE change?')"><input type="hidden" name="action" value="undo_last_change"><input type="hidden" name="section" value="<?= h($sectionFilter) ?>"><input type="hidden" name="project" value="<?= h($projectFilter) ?>"><?= $returnContextInputs ?></form>
  <div class="task-list">
    <?php foreach ($visibleTodos as $todo): ?>
      <?php include __DIR__ . '/item-card.php'; ?>
    <?php endforeach; ?>
  </div>
</section>
<div class="item-focus-backdrop modal-backdrop" hidden data-item-focus-modal>
  <div class="modal item-focus-dialog" role="dialog" aria-modal="true" aria-labelledby="item-focus-title">
    <div class="item-focus-header"><h2 id="item-focus-title">Item focus</h2><button type="button" class="small" data-close-item-focus-modal>Close</button></div>
    <div class="item-focus-body" data-item-focus-body></div>
  </div>
</div>
</main>
<?= project_demo_agent_script_html() ?>
</body>
</html>
