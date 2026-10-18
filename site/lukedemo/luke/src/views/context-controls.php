<?php
?>
<details class="context-disclosure"><summary>Context: <?= h($contextLabel) ?></summary>
  <form class="toolbar context-panel" method="get">
    <a class="button small subtle" href="./">Everything</a>
    <div class="context-field"><label>Area</label><div class="selector-row"><select name="section_filter" onchange="boardDropdownChanged(this)"><?= project_section_options($sections, $sectionFilter) ?></select><a class="button small subtle" href="<?= h($sectionAddUrl) ?>">Add</a><a class="button small subtle" href="<?= h($sectionEditUrl) ?>">Edit</a></div><?php if ($selectedSection && trim((string)($selectedSection['summary'] ?? '')) !== ''): ?><p class="context-description"><?= h((string)$selectedSection['summary']) ?></p><?php endif; ?></div>
    <div class="context-field"><label>Focus</label><div class="selector-row"><select name="project_filter" onchange="boardDropdownChanged(this)"><?= project_project_options($projects, $projectFilter, $sectionFilter) ?></select><a class="button small subtle" href="<?= h($projectAddUrl) ?>">Add</a><a class="button small subtle" href="<?= h($projectEditUrl) ?>">Edit</a></div><?php if ($projectLines): ?><div class="context-description project-lines"><?= implode('', $projectLines) ?></div><?php endif; ?></div>
  </form>
</details>
