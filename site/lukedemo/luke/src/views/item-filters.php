<?php
?>
<details class="items-disclosure"<?= $itemsFilterJustApplied ? '' : ' open' ?>><summary>Items: <?= h($itemsFilterLabel) ?></summary>
  <div class="items-panel">
    <form class="toolbar" method="get">
      <input type="hidden" name="section_filter" value="<?= h($sectionFilter) ?>">
      <input type="hidden" name="project_filter" value="<?= h($projectFilter) ?>">
      <input type="hidden" name="task_status" value="">
      <nav class="task-chips status-checkbox-filters" aria-label="Item filters"><button type="button" class="chip<?= $taskStatusQuery === implode(',', ['recent', 'agent-active', ...PROJECT_TODO_STATUSES, 'Bin']) || $taskStatusQuery === '' ? ' active' : '' ?>" data-status-preset="all">All</button><span class="filter-separator" aria-hidden="true">|</span><button type="button" class="chip<?= $taskStatusQuery === implode(',', ['Open', 'Do', 'Ready']) || $taskStatusQuery === 'active' ? ' active' : '' ?>" data-status-preset="active">Active</button><?php foreach (['Open', 'Do', 'Ready'] as $status): ?><label class="checkbox-chip"><input type="checkbox" name="task_status[]" value="<?= h($status) ?>"<?= project_item_status_filter_selected($taskStatus, $status) ? ' checked' : '' ?> onchange="boardDropdownChanged(this)"><?= h(project_item_status_label($status)) ?></label><?php endforeach; ?><span class="filter-separator" aria-hidden="true">|</span><?php foreach (['Done', 'Blocked'] as $status): ?><label class="checkbox-chip"><input type="checkbox" name="task_status[]" value="<?= h($status) ?>"<?= project_item_status_filter_selected($taskStatus, $status) ? ' checked' : '' ?> onchange="boardDropdownChanged(this)"><?= h(project_item_status_label($status)) ?></label><?php endforeach; ?><label class="checkbox-chip"><input type="checkbox" name="task_status[]" value="Bin"<?= project_item_status_filter_selected($taskStatus, 'Bin') ? ' checked' : '' ?> onchange="boardDropdownChanged(this)">Bin</label><button type="button" class="chip<?= $taskStatusQuery === implode(',', ['Done', 'Blocked', 'Bin']) || $taskStatusQuery === 'inactive' ? ' active' : '' ?>" data-status-preset="inactive">Inactive</button><span class="filter-separator" aria-hidden="true">|</span><label class="checkbox-chip"><input type="checkbox" name="task_status[]" value="recent"<?= project_item_status_filter_selected($taskStatus, 'recent') ? ' checked' : '' ?> onchange="boardDropdownChanged(this)">Recent changes</label><label class="checkbox-chip"><input type="checkbox" name="task_status[]" value="agent-active"<?= project_item_status_filter_selected($taskStatus, 'agent-active') ? ' checked' : '' ?> onchange="boardDropdownChanged(this)">Agent active</label></nav>
      <?php if ($recentFilterActive): ?><div><label>Recent count</label><select data-recent-count-control onchange="boardRecentCountChanged(this)"><?php foreach ($recentItemCounts as $count): ?><option value="<?= h((string)$count) ?>"<?= $count === $recentDefaultCount ? ' selected' : '' ?>><?= h((string)$count) ?></option><?php endforeach; ?></select></div><?php endif; ?>
      <div><label>Find</label><input name="task_q" value="<?= h($taskQ) ?>" placeholder="Title or notes"></div>
      <button class="subtle">Find</button>
      <div><label>Priority</label><select name="task_priority" onchange="boardDropdownChanged(this)"><?= project_filter_options(project_priority_values(), $taskPriority) ?></select></div>
      <div><label>Sort</label><select name="task_sort" onchange="boardDropdownChanged(this)"><?= project_sort_options($taskSort) ?></select></div>
      <a class="button subtle" href="?<?= h(http_build_query(['section_filter' => $sectionFilter, 'project_filter' => $projectFilter])) ?>">Reset filters</a>
      <?php if (project_backup_files()): ?><div><label>Safety</label><button class="subtle" form="undo-last-change" type="submit">Undo last change</button></div><?php endif; ?>
    </form>
  </div>
</details>
<div class="record-controls"><button type="button" class="record-abort-button danger" hidden aria-label="Abort voice note recording without saving">Abort</button><button type="button" class="record-button" aria-label="Record a voice note" aria-pressed="false">Record</button><button type="button" class="button add-item-button" data-open-add-item-modal>Add item</button></div>
<div class="modal-backdrop add-item-modal" hidden data-add-item-modal>
  <form method="post" class="modal task-edit dirty-watch add-task-form">
    <input type="hidden" name="action" value="add_item">
    <?= $returnContextInputs ?>
    <h2>Add item</h2>
    <div class="task-meta scope-meta"><div><label>Area</label><select name="section" data-add-item-section onchange="boardAddItemSectionChanged(this)"><?= project_section_scope_options($sections, $addItemSectionFilter) ?></select></div><div><label>Focus</label><select name="project" data-add-item-project onchange="boardAddItemProjectChanged(this)"><?= project_project_scope_options($projects, $addItemProjectFilter, $addItemSectionFilter, true) ?></select></div></div>
    <div class="scope-new-links"><a href="<?= project_url(['view' => 'add_section'] + $fullContextQuery) ?>">New Area...</a><a data-add-item-new-project href="<?= project_url(['view' => 'add_project', 'section_filter' => $addItemSectionFilter] + $fullContextQuery) ?>">New Focus...</a></div>
    <div class="task-meta"><div><label>Priority</label><select name="priority"><?= project_status_options(project_priority_values(), '') ?></select></div><div><label>Order</label><select name="order"><?= project_order_options(1) ?></select></div><div><label>Status</label><select name="status"><?= project_status_options(PROJECT_TODO_STATUSES, 'Open') ?></select></div></div>
    <div><label>Title</label><input name="title" placeholder="New item"></div>
    <div class="notes-field"><?= project_render_formatted_notes_editor() ?></div>
    <div class="actions center-actions add-item-actions"><button class="small equal-action">Add item</button><button type="button" class="small danger equal-action" data-close-add-item-modal>Cancel</button></div>
  </form>
</div>
</div>
