<?php
?>
<?php if ($view === 'settings'): ?>
<?= project_settings_form_html($board, $cancelUrl, $returnContextInputs) ?>
<?php elseif ($view === 'add_section' || $view === 'edit_section'): ?>
<?php $editingSection = $view === 'edit_section' && $selectedSection; ?>
<div class="modal-backdrop">
  <form class="modal dirty-watch" method="post">
    <input type="hidden" name="action" value="<?= $editingSection ? 'update_section' : 'add_section' ?>">
    <?= $returnContextInputs ?>
    <?php if ($editingSection): ?><input type="hidden" name="slug" value="<?= h((string)$selectedSection['slug']) ?>"><?php endif; ?>
    <h2><?= $editingSection ? 'Edit area' : 'Add area' ?></h2>
    <label>Name</label><input name="name" value="<?= h($editingSection ? (string)$selectedSection['name'] : '') ?>" placeholder="Area name" autofocus>
    <label>Description</label><textarea name="summary"><?= h($editingSection ? (string)($selectedSection['summary'] ?? '') : '') ?></textarea>
    <div class="actions center-actions"><button class="small">Save</button><a class="button small danger" href="<?= h($cancelUrl) ?>">Cancel</a></div>
  </form>
  <?php if ($editingSection): ?>
  <form class="modal danger-zone" method="post" onsubmit="return confirm('Delete this area and reassign its focuses/items?');">
    <input type="hidden" name="action" value="delete_section">
    <input type="hidden" name="slug" value="<?= h((string)$selectedSection['slug']) ?>">
    <h2>Delete area</h2>
    <label>Reassign its focuses/items to</label>
    <select name="reassign_section" required>
      <?php foreach ($sections as $section): ?>
        <?php if ((string)($section['slug'] ?? '') !== (string)$selectedSection['slug']): ?><option value="<?= h((string)$section['slug']) ?>"><?= h((string)$section['name']) ?></option><?php endif; ?>
      <?php endforeach; ?>
    </select>
    <div class="actions center-actions"><button class="small danger">Delete area</button></div>
  </form>
  <?php endif; ?>
</div>
<?php elseif ($view === 'add_project' || $view === 'edit_project'): ?>
<?php $editingProject = $view === 'edit_project' && $selectedProject; ?>
<div class="modal-backdrop">
  <form class="modal dirty-watch" method="post">
    <input type="hidden" name="action" value="<?= $editingProject ? 'update_project' : 'add_project' ?>">
    <?= $returnContextInputs ?>
    <?php if ($editingProject): ?><input type="hidden" name="slug" value="<?= h((string)$selectedProject['slug']) ?>"><?php endif; ?>
    <input type="hidden" name="section" value="<?= h($sectionFilter) ?>">
    <h2><?= $editingProject ? 'Edit focus' : 'Add focus' ?></h2>
    <label>Name</label><input name="name" value="<?= h($editingProject ? (string)$selectedProject['name'] : '') ?>" placeholder="Focus name" autofocus>
    <label>Status</label><select name="status"><?= project_status_options(PROJECT_STATUSES, $editingProject ? (string)($selectedProject['status'] ?? 'Active') : 'Active') ?></select>
    <label>Now</label><textarea name="now"><?= h($editingProject ? (string)($selectedProject['now'] ?? '') : '') ?></textarea>
    <label>Next</label><textarea name="next"><?= h($editingProject ? (string)($selectedProject['next'] ?? '') : '') ?></textarea>
    <label>Blocked</label><textarea name="blocked"><?= h($editingProject ? (string)($selectedProject['blocked'] ?? '') : '') ?></textarea>
    <div class="actions center-actions"><button class="small">Save</button><a class="button small danger" href="<?= h($cancelUrl) ?>">Cancel</a></div>
  </form>
  <?php if ($editingProject): ?>
  <form class="modal danger-zone" method="post" onsubmit="return confirm('Delete this focus and reassign its items?');">
    <input type="hidden" name="action" value="delete_project">
    <input type="hidden" name="section" value="<?= h((string)$selectedProject['section']) ?>">
    <input type="hidden" name="slug" value="<?= h((string)$selectedProject['slug']) ?>">
    <h2>Delete focus</h2>
    <label>Reassign its items to</label>
    <select name="reassign_project">
      <option value="">No focus</option>
      <?php foreach ($projects as $project): ?>
        <?php if ((string)($project['section'] ?? '') === (string)$selectedProject['section'] && (string)($project['slug'] ?? '') !== (string)$selectedProject['slug']): ?><option value="<?= h((string)$project['slug']) ?>"><?= h((string)$project['name']) ?></option><?php endif; ?>
      <?php endforeach; ?>
    </select>
    <div class="actions center-actions"><button class="small danger">Delete focus</button></div>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>
