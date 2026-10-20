<?php
?>
<script>
function boardContextUrl(form, view) {
  const section = form.elements.section_filter ? form.elements.section_filter.value : '';
  const project = form.elements.project_filter ? form.elements.project_filter.value : '';
  const params = new URLSearchParams({view: view});
  if (section && !section.startsWith('__')) params.set('section_filter', section);
  if (project && !project.startsWith('__')) params.set('project_filter', project);
  return '?' + params.toString();
}
let boardDirty = false;
let boardSubmitting = false;
let boardVersion = <?= json_encode($boardVersion) ?>;
let boardRefreshTimer = null;
const boardDirtyMessage = 'You have unsaved changes. Leave without saving?';
function boardHighlightDirtyForm(form) {
  if (!form) return;
  form.classList.add('dirty-highlight');
  const card = form.closest('.task-card');
  if (card) card.classList.add('dirty-highlight');
}
function boardRemoveDirtyHighlight(form) {
  if (!form) return;
  form.classList.remove('dirty-highlight');
  const card = form.closest('.task-card');
  if (card) card.classList.remove('dirty-highlight');
}
function boardMarkDirty(event) {
  if (event.target.closest && event.target.closest('.dirty-watch')) {
    boardDirty = true;
    boardHighlightDirtyForm(event.target.closest('.dirty-watch'));
  }
}
function boardRememberSubmittingItem(form) {
  const card = form ? form.closest('.task-card[data-task-id]') : null;
  if (!card || !window.sessionStorage) return;
  try {
    if (form.closest('[data-item-focus-modal]')) {
      sessionStorage.setItem('projectBoardFocusItemId', card.dataset.taskId || '');
    }
    sessionStorage.setItem('projectBoardReopenItemId', card.dataset.taskId || '');
  } catch (error) {}
}
function boardReopenSubmittedItem() {
  let reopenId = '';
  try {
    reopenId = window.sessionStorage ? sessionStorage.getItem('projectBoardReopenItemId') : '';
    if (window.sessionStorage) sessionStorage.removeItem('projectBoardReopenItemId');
    if (!reopenId) return;
    const card = reopenId ? document.querySelector('[data-task-id="' + CSS.escape(reopenId) + '"]') : null;
    if (!card) return;
    card.open = true;
  } catch (error) {}
}
function boardSavedOnSubmit(event) {
  if (event.target.closest && event.target.closest('[data-delete-audio-form="1"]')) return;
  const form = event.target.closest && event.target.closest('.task-edit[data-voice-scope-prompt="1"]');
  if (form && form.dataset.voiceScopeConfirmed !== '1') {
    const modal = form.querySelector('.voice-scope-modal');
    if (modal) {
      event.preventDefault();
      modal.hidden = false;
      const select = modal.querySelector('[data-voice-scope-section]');
      if (select) select.focus();
      return false;
    }
  }
  if (event.target.closest && event.target.closest('.dirty-watch')) {
    const form = event.target.closest('.dirty-watch');
    boardRememberSubmittingItem(form);
    boardRemoveDirtyHighlight(form);
    boardSubmitting = true;
    boardDirty = false;
  }
}
function boardShowImproveTranscriptProgress(button) {
  if (!button || button.dataset.busy === '1') return false;
  button.dataset.busy = '1';
  button.disabled = true;
  button.textContent = 'Improving transcript…';
  button.setAttribute('aria-busy', 'true');
  if (!button.parentElement.querySelector('.transcript-processing-status')) {
    const status = document.createElement('span');
    status.className = 'transcript-processing-status';
    status.setAttribute('role', 'status');
    status.textContent = 'Improving transcript… This can take a minute.';
    button.after(status);
  }
  return true;
}
function boardImproveTranscriptStatus(button) {
  return button && button.parentElement ? button.parentElement.querySelector('.transcript-processing-status') : null;
}
function boardImproveTranscriptForm(button) {
  if (!button) return null;
  const formId = button.getAttribute('form');
  return formId ? document.getElementById(formId) : button.closest('form');
}
function boardImproveTranscriptClicked(event) {
  const button = event.target.closest && event.target.closest('[data-improve-transcript-button]');
  if (!button) return;
  const form = boardImproveTranscriptForm(button);
  if (!form || button.dataset.busy === '1') return;
  boardShowImproveTranscriptProgress(button);
  if (!window.fetch || typeof FormData === 'undefined') return;
  event.preventDefault();
  fetch(form.getAttribute('action') || './', {method: 'POST', body: new FormData(form), credentials: 'same-origin'})
    .then(function(response) {
      if (!response.ok) throw new Error('Improve transcript failed');
      const status = boardImproveTranscriptStatus(button);
      if (status) status.textContent = 'Transcript improved. Refreshing…';
      window.location = response.url || window.location.href;
    })
    .catch(function(error) {
      button.dataset.busy = '0';
      button.disabled = false;
      button.textContent = 'Improve transcript';
      button.removeAttribute('aria-busy');
      const status = boardImproveTranscriptStatus(button);
      if (status) status.textContent = (error && error.message) ? error.message : 'Improve transcript failed';
    });
}
function boardImproveTranscriptSubmitted(event) {
  const form = event.target.closest && event.target.closest('form');
  if (!form || !form.querySelector('input[name="action"][value="improve_item_transcript"]')) return;
  const ownerSelector = boardFormOwnerSelector(form);
  const button = ownerSelector === '' ? null : document.querySelector(ownerSelector);
  boardShowImproveTranscriptProgress(button);
}
function boardWarnIfDirty(event) {
  if (!boardDirty || boardSubmitting) return;
  event.preventDefault();
  event.returnValue = boardDirtyMessage;
  return boardDirtyMessage;
}
function boardConfirmDirtyNavigation(event) {
  if (event && event.target.closest && event.target.closest('.voice-scope-modal button')) return true;
  if (event && event.target.closest && event.target.closest('[data-open-settings], [data-close-settings]')) return true;
  if (event && event.type === 'click' && !(event.target.closest && event.target.closest('a[href]'))) return true;
  if (!boardDirty) return true;
  if (boardDirty && !window.confirm(boardDirtyMessage)) {
    if (event) event.preventDefault();
    return false;
  }
  boardDirty = false;
  return true;
}
function boardFormOwnerSelector(form) {
  if (!form || !form.id) return '';
  const escapedId = (window.CSS && CSS.escape) ? CSS.escape(form.id) : form.id.replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  return '[form="' + escapedId + '"]';
}
function boardDeleteAudioSubmitted(event) {
  const form = event.target.closest && event.target.closest('[data-delete-audio-form="1"]');
  if (!form) return;
  event.preventDefault();
  if (form.dataset.busy === '1') return false;
  if (!window.confirm('Delete only the attached voice note and transcript? The item text stays.')) return false;
  const ownerSelector = boardFormOwnerSelector(form);
  const button = ownerSelector === '' ? null : document.querySelector(ownerSelector);
  if (button) button.disabled = true;
  form.dataset.busy = '1';
  fetch('./', {method: 'POST', body: new FormData(form), credentials: 'same-origin'})
    .then(function(response) {
      if (!response.ok) throw new Error('Delete failed');
      const card = form.closest('.task-card');
      if (card) {
        card.querySelectorAll('.audio-attachment').forEach(function(node) { node.remove(); });
        if (ownerSelector !== '') card.querySelectorAll(ownerSelector).forEach(function(node) { node.remove(); });
      }
      form.remove();
    })
    .catch(function(error) {
      window.alert(error.message || 'Delete failed');
      form.dataset.busy = '0';
      if (button) button.disabled = false;
    });
  return false;
}
function boardDropdownChanged(select) {
  if (!boardConfirmDirtyNavigation()) return;
  select.form.submit();
}
function boardRecentCountChanged(select) {
  const count = parseInt(select && select.value ? select.value : '3', 10) || 3;
  document.querySelectorAll('.task-card[data-recent-rank]').forEach(function(card) {
    const rank = parseInt(card.dataset.recentRank || '0', 10) || 0;
    const visible = rank > 0 && rank <= count;
    card.hidden = !visible;
    card.toggleAttribute('data-changed', visible);
    const dot = card.querySelector('.change-dot-form');
    if (dot) dot.hidden = !visible;
  });
}
document.addEventListener('change', function(event) {
  const control = event.target && event.target.closest ? event.target.closest('[data-recent-count-control]') : null;
  if (!control) return;
  boardRecentCountChanged(control);
});
function boardStatusPresetClicked(event) {
  const button = event.target.closest && event.target.closest('[data-status-preset]');
  if (!button) return;
  event.preventDefault();
  if (!boardConfirmDirtyNavigation()) return;
  const statusPresets = {
    all: [],
    active: ['Open', 'Do', 'Ready'],
    inactive: ['Done', 'Blocked', 'Bin']
  };
  const selected = new Set(statusPresets[button.dataset.statusPreset] || []);
  button.form.querySelectorAll('input[name="task_status[]"]').forEach(function(input) {
    input.checked = selected.has(input.value);
  });
  button.form.submit();
}
function boardThemeKey(value) {
  return String(value || '').trim().toLowerCase().replace(/[’']/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'blank';
}
function boardRethemeSummarySelect(select, prefix) {
  Array.from(select.classList).forEach(function(className) {
    if (className.startsWith(prefix + '-')) select.classList.remove(className);
  });
  select.classList.add(prefix + '-' + boardThemeKey(select.value));
}
function boardSummaryMetaChanged(select) {
  const card = select.closest('.task-card');
  if (!card || !card.open) {
    boardDropdownChanged(select);
    return;
  }
  const editForm = card.querySelector(':scope > form.task-edit');
  const hidden = editForm ? editForm.querySelector('input[type="hidden"][name="' + select.name + '"]') : null;
  if (hidden) hidden.value = select.value;
  if (select.name === 'priority') {
    boardRethemeSummarySelect(select, 'priority');
    card.dataset.priority = boardThemeKey(select.value);
  }
  if (select.name === 'status') {
    boardRethemeSummarySelect(select, 'status');
    card.dataset.status = boardThemeKey(select.value);
  }
  boardDirty = true;
  boardHighlightDirtyForm(editForm);
}
function boardCloseScopeMenus(except) {
  document.querySelectorAll('.scope-popover').forEach(function(menu) {
    if (menu !== except) menu.hidden = true;
  });
  document.querySelectorAll('.scope-label').forEach(function(button) {
    const menu = document.getElementById('scope-menu-' + button.dataset.scopeMenu);
    button.setAttribute('aria-expanded', menu && !menu.hidden ? 'true' : 'false');
  });
}
function boardToggleScopeMenu(event) {
  const button = event.target.closest && event.target.closest('.scope-label');
  if (button) {
    event.preventDefault();
    event.stopPropagation();
    const menu = document.getElementById('scope-menu-' + button.dataset.scopeMenu);
    if (!menu) return;
    const willOpen = menu.hidden;
    boardCloseScopeMenus(menu);
    menu.hidden = !willOpen;
    button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    return;
  }
  if (!(event.target.closest && event.target.closest('.scope-menu'))) {
    boardCloseScopeMenus(null);
  }
}
function boardCloseScopeMenuOnEscape(event) {
  if (event.key === 'Escape') boardCloseScopeMenus(null);
}
function boardCancelOnEscape(event) {
  if (event.key === 'Escape' && boardConfirmDirtyNavigation(event)) {
    event.preventDefault();
    window.location = <?= json_encode($cancelUrl) ?>;
  }
}
function boardOpenStateKey() {
  const url = new URL(window.location.href);
  url.hash = '';
  return 'project-board-open-items:' + url.pathname + '?' + url.searchParams.toString();
}
function boardOpenTaskIds() {
  return Array.from(document.querySelectorAll('.task-card[data-task-id][open]')).map(function(details) {
    return details.dataset.taskId;
  });
}
function boardSaveOpenState() {
  try {
    window.sessionStorage.setItem(boardOpenStateKey(), JSON.stringify(boardOpenTaskIds()));
  } catch (error) {}
}
function boardRestoreOpenState() {
  let ids = [];
  try {
    ids = JSON.parse(window.sessionStorage.getItem(boardOpenStateKey()) || '[]');
  } catch (error) {
    ids = [];
  }
  if (!Array.isArray(ids)) return;
  const openIds = new Set(ids.map(String));
  document.querySelectorAll('.task-card[data-task-id]').forEach(function(details) {
    details.open = openIds.has(details.dataset.taskId);
    details.addEventListener('toggle', boardSaveOpenState);
  });
}

let boardRecorder = null;
let boardRecordChunks = [];
let boardRecording = false;
let boardSuppressRecordClick = false;
let boardAbortRecording = false;
const boardRecordingDbName = 'project-board-recordings';
const boardRecordingStoreName = 'recordings';
let boardQueuedRecordingsFlushing = false;
function boardOpenRecordingQueue() {
  return new Promise(function(resolve, reject) {
    if (!window.indexedDB) { reject(new Error('Offline recording storage is not available in this browser.')); return; }
    const request = indexedDB.open('project-board-recordings', 1);
    request.onupgradeneeded = function() {
      const db = request.result;
      if (!db.objectStoreNames.contains(boardRecordingStoreName)) {
        db.createObjectStore(boardRecordingStoreName, {keyPath: 'id'});
      }
    };
    request.onsuccess = function() { resolve(request.result); };
    request.onerror = function() { reject(request.error || new Error('Recording queue unavailable')); };
  });
}
function boardQueuedRecordingFields(form) {
  const fields = [];
  form.forEach(function(value, key) {
    if (key !== 'audio' && typeof value === 'string') fields.push([key, value]);
  });
  return fields;
}
function boardQueueRecordingForLater(blob, form) {
  return boardOpenRecordingQueue().then(function(db) {
    return new Promise(function(resolve, reject) {
      const tx = db.transaction(boardRecordingStoreName, 'readwrite');
      tx.objectStore(boardRecordingStoreName).put({
        id: 'voice-note-' + Date.now() + '-' + Math.random().toString(16).slice(2),
        createdAt: new Date().toISOString(),
        filename: 'voice-note.webm',
        type: blob.type || 'audio/webm',
        fields: boardQueuedRecordingFields(form),
        blob: blob
      });
      tx.oncomplete = function() { db.close(); resolve(); };
      tx.onerror = function() { const error = tx.error || new Error('Recording queue write failed'); db.close(); reject(error); };
    });
  });
}
function boardDeleteQueuedRecording(id) {
  return boardOpenRecordingQueue().then(function(db) {
    return new Promise(function(resolve, reject) {
      const tx = db.transaction(boardRecordingStoreName, 'readwrite');
      tx.objectStore(boardRecordingStoreName).delete(id);
      tx.oncomplete = function() { db.close(); resolve(); };
      tx.onerror = function() { const error = tx.error || new Error('Recording queue delete failed'); db.close(); reject(error); };
    });
  });
}
function boardReadQueuedRecordings() {
  return boardOpenRecordingQueue().then(function(db) {
    return new Promise(function(resolve, reject) {
      const tx = db.transaction(boardRecordingStoreName, 'readonly');
      const request = tx.objectStore(boardRecordingStoreName).getAll();
      request.onsuccess = function() { db.close(); resolve(request.result || []); };
      request.onerror = function() { const error = request.error || new Error('Recording queue read failed'); db.close(); reject(error); };
    });
  });
}
function boardFormForQueuedRecording(item) {
  const form = new FormData();
  (item.fields || []).forEach(function(pair) { form.append(pair[0], pair[1]); });
  form.append('audio', item.blob, item.filename || 'voice-note.webm');
  return form;
}
function boardGoToRecordedVoiceNoteContext() {
  window.location = './?section_filter=the-umbrella&project_filter=communication&task_status=';
}
function boardUploadQueuedRecording(item) {
  return fetch('./', {method: 'POST', body: boardFormForQueuedRecording(item), credentials: 'same-origin'}).then(function(response) {
    if (!response.ok) throw new Error('Queued recording upload failed');
    return boardDeleteQueuedRecording(item.id);
  });
}
function boardFlushQueuedRecordings() {
  if (boardQueuedRecordingsFlushing || !window.fetch || !navigator.onLine) return;
  boardQueuedRecordingsFlushing = true;
  let uploadedQueuedRecording = false;
  boardReadQueuedRecordings().then(function(items) {
    return items.reduce(function(chain, item) {
      return chain.then(function() { return boardUploadQueuedRecording(item).then(function() { uploadedQueuedRecording = true; }); });
    }, Promise.resolve());
  }).then(function() {
    boardQueuedRecordingsFlushing = false;
    if (uploadedQueuedRecording) boardGoToRecordedVoiceNoteContext();
  }).catch(function() {
    boardQueuedRecordingsFlushing = false;
  });
}
function boardRecordAbortButton() {
  return document.querySelector('.record-abort-button');
}
function boardSetRecordControls(button, recording) {
  const abortButton = boardRecordAbortButton();
  button.classList.toggle('recording', recording);
  button.textContent = recording ? 'Stop' : 'Record';
  button.setAttribute('aria-pressed', recording ? 'true' : 'false');
  if (abortButton) abortButton.hidden = !recording;
}
async function boardStartRecording(button) {
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || typeof MediaRecorder === 'undefined') {
    window.alert('Audio recording is not available in this browser.');
    return;
  }
  if (boardRecording) return;
  const stream = await navigator.mediaDevices.getUserMedia({audio: true});
  boardRecordChunks = [];
  boardAbortRecording = false;
  boardRecorder = new MediaRecorder(stream);
  boardRecorder.addEventListener('dataavailable', function(event) {
    if (event.data && event.data.size) boardRecordChunks.push(event.data);
  });
  boardRecorder.addEventListener('stop', function() {
    stream.getTracks().forEach(function(track) { track.stop(); });
    if (boardAbortRecording) {
      boardRecordChunks = [];
      boardRecorder = null;
      boardAbortRecording = false;
      return;
    }
    boardUploadRecording(button);
  });
  boardRecorder.start();
  boardRecording = true;
  boardSetRecordControls(button, true);
}
function boardStopRecording(button) {
  if (!boardRecorder || !boardRecording) return;
  boardRecording = false;
  boardSetRecordControls(button, false);
  boardRecorder.stop();
}
function boardCancelRecording(button) {
  if (!boardRecorder || !boardRecording) return;
  boardAbortRecording = true;
  boardRecording = false;
  boardRecordChunks = [];
  boardSetRecordControls(button, false);
  boardRecorder.stop();
}
function boardUploadRecording(button) {
  if (!boardRecordChunks.length) return;
  button.disabled = true;
  button.textContent = 'Saving...';
  const blob = new Blob(boardRecordChunks, {type: boardRecordChunks[0].type || 'audio/webm'});
  const form = new FormData();
  form.append('action', 'record_audio');
  form.append('return_section', <?= json_encode($sectionFilter) ?>);
  form.append('return_project', <?= json_encode($projectFilter) ?>);
  form.append('task_q', <?= json_encode($taskQ) ?>);
  form.append('task_priority', <?= json_encode($taskPriority) ?>);
  form.append('task_status', <?= json_encode($taskStatus) ?>);
  form.append('task_sort', <?= json_encode($taskSort) ?>);
  form.append('audio', blob, 'voice-note.webm');
  fetch('./', {method: 'POST', body: form, credentials: 'same-origin'}).then(function(response) {
    if (!response.ok) throw new Error('Upload failed');
    boardGoToRecordedVoiceNoteContext();
  }).catch(function(error) {
    boardQueueRecordingForLater(blob, form).then(function() {
      window.alert('Recording saved on this device. It will upload automatically when the connection returns.');
      button.disabled = false;
      button.textContent = 'Record';
      boardFlushQueuedRecordings();
    }).catch(function(queueError) {
      window.alert((queueError && queueError.message) || (error && error.message) || 'Recording upload failed');
      button.disabled = false;
      button.textContent = 'Record';
    });
  });
}
function boardRecordPointerDown(event) {
  const button = event.currentTarget;
  if (event.pointerType === 'mouse' && event.button !== 0) return;
  button.dataset.recordHold = '1';
  window.setTimeout(function() {
    if (button.dataset.recordHold === '1' && !boardRecording) boardStartRecording(button).catch(function(error) { window.alert(error.message || 'Recording failed'); });
  }, 250);
}
function boardRecordPointerUp(event) {
  const button = event.currentTarget;
  if (button.dataset.recordHold === '1') {
    button.dataset.recordHold = '0';
    if (boardRecording) {
      boardSuppressRecordClick = true;
      boardStopRecording(button);
      window.setTimeout(function() { boardSuppressRecordClick = false; }, 0);
    }
  }
}
function boardRecordClicked(event) {
  if (boardSuppressRecordClick) {
    event.preventDefault();
    boardSuppressRecordClick = false;
    return;
  }
  const button = event.currentTarget;
  if (boardRecording) boardStopRecording(button);
  else boardStartRecording(button).catch(function(error) { window.alert(error.message || 'Recording failed'); });
}
function boardVoiceScopeProjectOptions(projectSelect, section) {
  return Array.from(projectSelect.options).filter(function(option) {
    return option.value === '' || !section || option.dataset.section === section;
  });
}
function boardVoiceScopeApplyProjectFilter(projectSelect, section) {
  Array.from(projectSelect.options).forEach(function(option) {
    const show = option.value === '' || !section || option.dataset.section === section;
    option.hidden = !show;
    option.disabled = !show;
  });
}
function boardVoiceScopeFirstProjectForSection(projectSelect, section) {
  boardVoiceScopeApplyProjectFilter(projectSelect, section);
  const match = boardVoiceScopeProjectOptions(projectSelect, section).find(function(option) { return option.value !== ''; });
  return match ? match.value : '';
}
function boardVoiceScopeUpdateNewProjectLink(root, section) {
  const link = root ? root.querySelector('[data-voice-scope-new-project]') : null;
  if (!link) return;
  const url = new URL(link.getAttribute('href'), window.location.href);
  if (section) url.searchParams.set('section_filter', section);
  else url.searchParams.delete('section_filter');
  link.setAttribute('href', url.pathname + url.search + url.hash);
}
function boardVoiceScopeSectionChanged(select) {
  const root = select.closest('.voice-scope-modal') || select.form;
  const projectSelect = root ? (root.querySelector('[data-voice-scope-project]') || (root.elements ? root.elements.project : null)) : null;
  if (!projectSelect) return;
  boardVoiceScopeApplyProjectFilter(projectSelect, select.value);
  const selectedProject = projectSelect.selectedOptions[0];
  if (!selectedProject || selectedProject.value === '' || selectedProject.disabled || selectedProject.dataset.section !== select.value) {
    projectSelect.value = boardVoiceScopeFirstProjectForSection(projectSelect, select.value);
  }
  boardVoiceScopeUpdateNewProjectLink(root, select.value);
}
function boardVoiceScopeProjectChanged(select) {
  const form = select.form;
  const root = select.closest('.voice-scope-modal') || form;
  const option = select.selectedOptions[0];
  const sectionSelect = root ? (root.querySelector('[data-voice-scope-section]') || (root.elements ? root.elements.section : null)) : null;
  if (!sectionSelect || !option || option.value === '') return;
  if (option.dataset.section) {
    sectionSelect.value = option.dataset.section;
    boardVoiceScopeUpdateNewProjectLink(root, option.dataset.section);
    if (select) boardVoiceScopeApplyProjectFilter(select, option.dataset.section);
  }
}
function boardVoiceScopeConfirm(button) {
  const modal = button.closest('.voice-scope-modal');
  const form = button.closest('form.task-edit');
  if (!modal || !form) return;
  const section = modal.querySelector('[data-voice-scope-section]');
  const project = modal.querySelector('[data-voice-scope-project]');
  if (section && form.elements.section) form.elements.section.value = section.value;
  if (project && form.elements.project) form.elements.project.value = project.value;
  form.dataset.voiceScopeConfirmed = '1';
  boardSubmitting = true;
  boardDirty = false;
  form.submit();
}
function boardVoiceScopeCancel(button) {
  const modal = button.closest('.voice-scope-modal');
  if (modal) modal.hidden = true;
}
function boardVoiceScopeModalClicked(event) {
  if (event.target && event.target.matches && event.target.matches('.voice-scope-modal')) {
    event.target.hidden = true;
  }
}
function boardVoiceScopeCloseOnEscape(event) {
  if (event.key !== 'Escape') return;
  const modal = document.querySelector('.voice-scope-modal:not([hidden])');
  if (!modal) return;
  event.preventDefault();
  modal.hidden = true;
}
function boardInitVoiceScopeModals() {
  document.querySelectorAll('.voice-scope-modal').forEach(function(modal) {
    const section = modal.querySelector('[data-voice-scope-section]');
    const project = modal.querySelector('[data-voice-scope-project]');
    if (section && project) {
      boardVoiceScopeApplyProjectFilter(project, section.value);
      boardVoiceScopeUpdateNewProjectLink(modal, section.value);
      const selectedProject = project.selectedOptions[0];
      if (selectedProject && selectedProject.value !== '' && selectedProject.dataset.section !== section.value) {
        project.value = boardVoiceScopeFirstProjectForSection(project, section.value);
      }
    }
    modal.addEventListener('click', boardVoiceScopeModalClicked);
  });
}
function boardInitRecorder() {
  const button = document.querySelector('.record-button');
  if (!button) return;
  const abortButton = boardRecordAbortButton();
  button.addEventListener('click', boardRecordClicked);
  button.addEventListener('pointerdown', boardRecordPointerDown);
  button.addEventListener('pointerup', boardRecordPointerUp);
  button.addEventListener('pointercancel', boardRecordPointerUp);
  if (abortButton) {
    abortButton.addEventListener('click', function() { boardCancelRecording(button); });
  }
}
function boardAbortRecordingOnEscape(event) {
  if (event.key !== 'Escape' || !boardRecording) return;
  const button = document.querySelector('.record-button');
  if (!button) return;
  event.preventDefault();
  boardCancelRecording(button);
}
function boardShowRemoteUpdateNotice() {
  document.title = "LUKE • update available";
  const notice = document.querySelector('.remote-update-notice');
  if (notice) notice.hidden = false;
}
function boardCheckRemoteUpdate() {
  if (boardSubmitting || document.visibilityState === 'hidden') return;
  fetch('./?board_version=1', {credentials: 'same-origin', cache: 'no-store'})
    .then(function(response) { if (!response.ok) throw new Error('Version check failed'); return response.json(); })
    .then(function(data) {
      if (!data || !data.version || data.version === boardVersion) return;
      if (boardDirty || boardRecording) {
        boardVersion = data.version;
        boardShowRemoteUpdateNotice();
        return;
      }
      boardSaveOpenState();
      window.location.reload();
    })
    .catch(function() {});
}
function boardInitAutoRefresh() {
  boardRefreshTimer = window.setInterval(boardCheckRemoteUpdate, 10000);
  document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') boardCheckRemoteUpdate();
  });
}
function boardEnableDetailsMotion() {
  document.body.classList.add('details-motion-ready');
}
function boardSyncAddItemDisclosure() {
  document.querySelectorAll('.add-item-disclosure').forEach(function(disclosure) {
    disclosure.classList.toggle('add-item-disclosure-open', disclosure.open);
    disclosure.addEventListener('toggle', function() {
      disclosure.classList.toggle('add-item-disclosure-open', disclosure.open);
    });
  });
}
function boardKeepSummaryControlsClickable(event) {
  if (event.target.closest && event.target.closest('.task-summary-controls, .change-dot-form, [data-open-item-focus-modal]')) event.stopPropagation();
}
function boardItemFocusModal() {
  return document.querySelector('[data-item-focus-modal]');
}
function boardReturnFocusedItem() {
  const modal = boardItemFocusModal();
  if (!modal) return;
  const body = modal.querySelector('[data-item-focus-body]');
  const card = body ? body.querySelector('.task-card[data-task-id]') : null;
  if (!card) return;
  const id = card.dataset.taskId || '';
  const escapedId = (window.CSS && CSS.escape) ? CSS.escape(id) : id.replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  const placeholder = document.querySelector('[data-item-focus-placeholder="' + escapedId + '"]');
  if (placeholder) placeholder.replaceWith(card);
}
function boardOpenFocusedItem(card) {
  const modal = boardItemFocusModal();
  const body = modal ? modal.querySelector('[data-item-focus-body]') : null;
  if (!modal || !body || !card) return false;
  boardReturnFocusedItem();
  const placeholder = document.createElement('div');
  placeholder.hidden = true;
  placeholder.dataset.itemFocusPlaceholder = card.dataset.taskId || '';
  card.before(placeholder);
  body.appendChild(card);
  card.open = true;
  modal.hidden = false;
  const firstField = card.querySelector('input[name="title"], [data-formatted-notes-editor], textarea, button');
  if (firstField) firstField.focus();
  return true;
}
function boardOpenItemFocusModal(event) {
  const button = event.target.closest && event.target.closest('[data-open-item-focus-modal]');
  if (!button) return;
  event.preventDefault();
  event.stopPropagation();
  const id = button.dataset.itemFocusId || '';
  const escapedId = (window.CSS && CSS.escape) ? CSS.escape(id) : id.replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  const card = document.querySelector('.task-card[data-task-id="' + escapedId + '"]');
  boardOpenFocusedItem(card);
}
function boardCloseItemFocusModal(event) {
  const closeButton = event.target.closest && event.target.closest('[data-close-item-focus-modal]');
  const backdropClick = event.target && event.target.matches && event.target.matches('[data-item-focus-modal]');
  if (!closeButton && !backdropClick) return;
  if (!boardConfirmDirtyNavigation(event)) return;
  event.preventDefault();
  boardReturnFocusedItem();
  const modal = boardItemFocusModal();
  if (modal) modal.hidden = true;
}
function boardCloseItemFocusModalOnEscape(event) {
  if (event.key !== 'Escape') return;
  const modal = document.querySelector('[data-item-focus-modal]:not([hidden])');
  if (!modal) return;
  if (!boardConfirmDirtyNavigation(event)) return;
  event.preventDefault();
  boardReturnFocusedItem();
  modal.hidden = true;
}
function boardRestoreFocusedItem() {
  if (!window.sessionStorage) return;
  let focusId = '';
  try {
    focusId = sessionStorage.getItem('projectBoardFocusItemId') || '';
    sessionStorage.removeItem('projectBoardFocusItemId');
  } catch (error) {}
  if (!focusId) return;
  const escapedId = (window.CSS && CSS.escape) ? CSS.escape(focusId) : focusId.replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  const card = document.querySelector('.task-card[data-task-id="' + escapedId + '"]');
  boardOpenFocusedItem(card);
}
function boardOpenAddItemModal(event) {
  const button = event.target.closest && event.target.closest('[data-open-add-item-modal]');
  if (!button) return;
  const modal = document.querySelector('[data-add-item-modal]');
  if (!modal) return;
  event.preventDefault();
  modal.hidden = false;
  const title = modal.querySelector('input[name="title"]');
  if (title) title.focus();
}
function boardCloseAddItemModal(event) {
  const closeButton = event.target.closest && event.target.closest('[data-close-add-item-modal]');
  const backdropClick = event.target && event.target.matches && event.target.matches('[data-add-item-modal]');
  if (!closeButton && !backdropClick) return;
  if (!boardConfirmDirtyNavigation(event)) return;
  event.preventDefault();
  const modal = document.querySelector('[data-add-item-modal]');
  if (modal) modal.hidden = true;
}
function boardCloseAddItemModalOnEscape(event) {
  if (event.key !== 'Escape') return;
  const modal = document.querySelector('[data-add-item-modal]:not([hidden])');
  if (!modal) return;
  if (!boardConfirmDirtyNavigation(event)) return;
  event.preventDefault();
  modal.hidden = true;
}
function boardAddItemUpdateNewProjectLink(root, section) {
  const link = root ? root.querySelector('[data-add-item-new-project]') : null;
  if (!link) return;
  const url = new URL(link.getAttribute('href'), window.location.href);
  if (section) url.searchParams.set('section_filter', section);
  else url.searchParams.delete('section_filter');
  link.setAttribute('href', url.pathname + url.search + url.hash);
}
function boardAddItemSectionChanged(select) {
  const root = select.closest('[data-add-item-modal]') || select.form;
  const projectSelect = root ? (root.querySelector('[data-add-item-project]') || (root.elements ? root.elements.project : null)) : null;
  if (!projectSelect) return;
  boardVoiceScopeApplyProjectFilter(projectSelect, select.value);
  const selectedProject = projectSelect.selectedOptions[0];
  if (!selectedProject || selectedProject.value === '' || selectedProject.disabled || selectedProject.dataset.section !== select.value) {
    projectSelect.value = boardVoiceScopeFirstProjectForSection(projectSelect, select.value);
  }
  boardAddItemUpdateNewProjectLink(root, select.value);
}
function boardAddItemProjectChanged(select) {
  const root = select.closest('[data-add-item-modal]') || select.form;
  const option = select.selectedOptions[0];
  const sectionSelect = root ? (root.querySelector('[data-add-item-section]') || (root.elements ? root.elements.section : null)) : null;
  if (!sectionSelect || !option || option.value === '') return;
  if (option.dataset.section) {
    sectionSelect.value = option.dataset.section;
    boardVoiceScopeApplyProjectFilter(select, option.dataset.section);
    boardAddItemUpdateNewProjectLink(root, option.dataset.section);
  }
}
function boardInitAddItemModal() {
  const modal = document.querySelector('[data-add-item-modal]');
  if (!modal) return;
  const section = modal.querySelector('[data-add-item-section]');
  const project = modal.querySelector('[data-add-item-project]');
  if (!section || !project) return;
  boardVoiceScopeApplyProjectFilter(project, section.value);
  const selectedProject = project.selectedOptions[0];
  if (selectedProject && selectedProject.value !== '' && selectedProject.dataset.section !== section.value) {
    project.value = boardVoiceScopeFirstProjectForSection(project, section.value);
  }
}
function boardCopyTextFallback(text) {
  const box = document.createElement('textarea');
  box.value = text;
  box.setAttribute('readonly', 'readonly');
  box.style.position = 'fixed';
  box.style.left = '-9999px';
  document.body.appendChild(box);
  box.select();
  let ok = false;
  try { ok = document.execCommand('copy'); } catch (error) { ok = false; }
  box.remove();
  return ok ? Promise.resolve() : Promise.reject(new Error('Copy failed'));
}
function boardCopyImprovedTranscriptClicked(event) {
  const button = event.target.closest && event.target.closest('[data-copy-transcript-button]');
  if (!button) return;
  const text = button.getAttribute('data-copy-transcript-text') || '';
  if (text === '') return;
  const original = button.textContent;
  const copy = navigator.clipboard && navigator.clipboard.writeText
    ? navigator.clipboard.writeText(text)
    : boardCopyTextFallback(text);
  copy.then(function() {
    button.textContent = 'Copied';
    window.setTimeout(function() { button.textContent = original; }, 1600);
  }).catch(function() {
    button.textContent = 'Copy failed';
    window.setTimeout(function() { button.textContent = original; }, 2200);
  });
}
function boardEscapeNoteText(text) {
  return text.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
}
function boardIsSafeNoteImageSrc(src) {
  return /^data:image\/(png|jpe?g|gif|webp);base64,[a-z0-9+/=]+$/i.test((src || '').trim());
}
function boardSafeNoteImageHtml(image) {
  const src = (image.getAttribute('src') || '').trim();
  if (!boardIsSafeNoteImageSrc(src)) return '';
  const alt = (image.getAttribute('alt') || 'Pasted image').trim() || 'Pasted image';
  return '<img src="' + src.replaceAll('"', '&quot;') + '" alt="' + boardEscapeNoteText(alt).replaceAll('"', '&quot;') + '" loading="lazy" class="note-image">';
}
function boardSafeNoteHtml(node) {
  let html = '';
  node.childNodes.forEach(function(child) {
    if (child.nodeType === Node.TEXT_NODE) { html += boardEscapeNoteText(child.textContent); return; }
    if (child.nodeType !== Node.ELEMENT_NODE) return;
    let tag = child.tagName.toLowerCase();
    if (tag === 'b') tag = 'strong';
    if (tag === 'i') tag = 'em';
    if (tag === 'br') { html += '<br>'; return; }
    if (tag === 'img') { html += boardSafeNoteImageHtml(child); return; }
    const contents = boardSafeNoteHtml(child);
    if (['sup', 'sub', 'strong', 'em'].includes(tag)) { html += '<' + tag + '>' + contents + '</' + tag + '>'; return; }
    if (['div', 'p'].includes(tag)) {
      const blockContents = contents.replace(/^(?:<br>)+|(?:<br>)+$/g, '');
      if (blockContents === '') return;
      if (html !== '' && !html.endsWith('<br>')) html += '<br>';
      html += blockContents;
      return;
    }
    html += contents;
  });
  return html;
}
function boardSyncFormattedNote(editor) {
  const storage = editor.parentElement.querySelector('[data-formatted-notes-storage]');
  if (storage) storage.value = boardSafeNoteHtml(editor);
}
function boardNoteTimestamp() {
  const now = new Date();
  const pad = function(value) { return String(value).padStart(2, '0'); };
  return now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes());
}
function boardPlaceCaretAtEditorEnd(editor) {
  const range = document.createRange();
  range.selectNodeContents(editor);
  range.collapse(false);
  const selection = window.getSelection();
  if (!selection) return;
  selection.removeAllRanges();
  selection.addRange(range);
}
function boardSelectionIsAtEditorEnd(editor) {
  const selection = window.getSelection();
  if (!selection || selection.rangeCount === 0 || !selection.isCollapsed) return false;
  const range = selection.getRangeAt(0);
  if (!editor.contains(range.startContainer)) return false;
  const tail = range.cloneRange();
  tail.selectNodeContents(editor);
  tail.setStart(range.endContainer, range.endOffset);
  return tail.toString() === '';
}
function boardEnsureNoteStamp(editor) {
  if (!editor || editor.dataset.noteStampAdded === '1') return;
  const user = (editor.dataset.noteStampUser || '').trim() || 'User';
  const stamp = user + ' ' + boardNoteTimestamp();
  const separator = editor.textContent.trim() === '' ? '' : '<br><br>';
  editor.innerHTML = editor.innerHTML + separator + boardEscapeNoteText(stamp) + '<br>';
  editor.dataset.noteStampAdded = '1';
  boardPlaceCaretAtEditorEnd(editor);
  boardSyncFormattedNote(editor);
}
function boardNoteEditorBeforeInput(editor, event) {
  if (!event || !event.inputType || !event.inputType.startsWith('insert')) return;
  if (!boardSelectionIsAtEditorEnd(editor)) return;
  boardEnsureNoteStamp(editor);
}
function boardPasteFormattedNote(event) {
  event.preventDefault();
  const clipboard = event.clipboardData;
  const imageItems = Array.from(clipboard.items || []).filter(function(item) {
    return item.kind === 'file' && item.type && item.type.startsWith('image/');
  });
  const source = clipboard.getData('text/html') || boardEscapeNoteText(clipboard.getData('text/plain'));
  if (source !== '') {
    const staging = document.createElement('div');
    staging.innerHTML = source;
    document.execCommand('insertHTML', false, boardSafeNoteHtml(staging));
  }
  if (imageItems.length === 0) return;
  Promise.all(imageItems.map(function(item) {
    return new Promise(function(resolve) {
      const file = item.getAsFile();
      if (!file || !file.type || !file.type.match(/^image\/(png|jpe?g|gif|webp)$/i)) { resolve(''); return; }
      const reader = new FileReader();
      reader.onload = function() {
        const src = String(reader.result || '');
        resolve(boardIsSafeNoteImageSrc(src) ? '<img src="' + src + '" alt="Pasted image" loading="lazy" class="note-image">' : '');
      };
      reader.onerror = function() { resolve(''); };
      reader.readAsDataURL(file);
    });
  })).then(function(images) {
    const html = images.filter(Boolean).join('<br>');
    if (html !== '') document.execCommand('insertHTML', false, html);
    const editor = event.target.closest && event.target.closest('[data-formatted-notes-editor]');
    if (editor) boardSyncFormattedNote(editor);
  });
}
function boardInitFormattedNotesEditors(root) {
  (root && root.querySelectorAll ? root : document).querySelectorAll('[data-formatted-notes-editor]').forEach(function(editor) {
    boardSyncFormattedNote(editor);
    editor.addEventListener('paste', boardPasteFormattedNote);
    editor.addEventListener('beforeinput', function(event) { boardNoteEditorBeforeInput(editor, event); });
    editor.addEventListener('input', function() { boardSyncFormattedNote(editor); });
  });
}
function boardDuplicateSubmitted(event) {
  const form = event.target.closest && event.target.closest('form[id^="duplicate-task-"]');
  if (!form || !window.fetch || !window.DOMParser) return;
  event.preventDefault();
  event.stopImmediatePropagation();
  if (form.dataset.busy === '1') return false;
  form.dataset.busy = '1';
  const original = document.querySelector('[data-task-id="' + CSS.escape(form.id.replace('duplicate-task-', '')) + '"]');
  fetch('./', {method: 'POST', body: new URLSearchParams(new FormData(form)), credentials: 'same-origin'})
    .then(function(response) {
      if (!response.ok) throw new Error('Duplicate failed');
      const newId = new URL(response.url, location.href).searchParams.get('just_edited_item_id') || '';
      return response.text().then(function(html) { return {html: html, newId: newId}; });
    })
    .then(function(result) {
      const doc = new DOMParser().parseFromString(result.html, 'text/html');
      const fresh = result.newId ? doc.querySelector('[data-task-id="' + CSS.escape(result.newId) + '"]') : null;
      if (!fresh || !original) { window.location.reload(); return; }
      const card = document.importNode(fresh, true);
      card.open = true;
      original.after(card);
      boardInitFormattedNotesEditors(card);
      card.scrollIntoView({block: 'nearest'});
      const title = card.querySelector('input[name="title"]');
      if (title) title.focus();
      form.dataset.busy = '0';
    })
    .catch(function(error) {
      window.alert(error.message || 'Duplicate failed');
      form.dataset.busy = '0';
    });
  return false;
}
window.addEventListener('beforeunload', boardWarnIfDirty);
document.addEventListener('submit', boardDuplicateSubmitted, true);
document.addEventListener('input', boardMarkDirty, true);
document.addEventListener('change', boardMarkDirty, true);
document.addEventListener('submit', boardDeleteAudioSubmitted, true);
document.addEventListener('submit', boardImproveTranscriptSubmitted, true);
document.addEventListener('submit', boardSavedOnSubmit, true);
document.addEventListener('click', boardImproveTranscriptClicked, true);
document.addEventListener('click', boardCopyImprovedTranscriptClicked, true);
document.addEventListener('click', boardStatusPresetClicked, true);
document.addEventListener('click', boardOpenAddItemModal, true);
document.addEventListener('click', boardCloseAddItemModal, true);
document.addEventListener('click', boardOpenItemFocusModal, true);
document.addEventListener('click', boardCloseItemFocusModal, true);
document.addEventListener('click', boardConfirmDirtyNavigation, true);
document.addEventListener('click', boardKeepSummaryControlsClickable, true);
document.addEventListener('click', boardToggleScopeMenu, true);
document.addEventListener('keydown', boardCloseScopeMenuOnEscape, true);
document.addEventListener('keydown', boardCloseAddItemModalOnEscape, true);
document.addEventListener('keydown', boardCloseItemFocusModalOnEscape, true);
document.addEventListener('keydown', boardVoiceScopeCloseOnEscape, true);

const boardPriorityOrder = {words: ['Must', 'Should', 'Could', "Won't"], numbers: ['Should', 'Could', "Won't", 'Must']};
const boardPriorityNumbers = {'Must': '0', 'Should': '1', 'Could': '2', "Won't": '3'};
function boardApplyLabelMode(mode) {
  document.querySelectorAll('select').forEach(function(select) {
    const options = Array.prototype.slice.call(select.options).filter(function(o) { return boardPriorityNumbers[o.value] !== undefined; });
    if (!options.length) return;
    options.forEach(function(o) {
      const key = o.value;
      o.setAttribute('value', key);
      o.textContent = mode === 'numbers' ? boardPriorityNumbers[key] : key;
    });
    options.sort(function(a, b) { return boardPriorityOrder[mode].indexOf(a.value) - boardPriorityOrder[mode].indexOf(b.value); });
    const selected = select.value;
    options.forEach(function(o) { select.appendChild(o); });
    select.value = selected;
  });
}
function boardSettingsForm() { return document.querySelector('[data-settings-form]'); }
function boardSettingsModal() { return document.querySelector('[data-settings-modal]'); }
function boardSettingsPreviewColours(form, useDefaults) {
  form.querySelectorAll('[data-colour-var]').forEach(function(input) {
    document.documentElement.style.setProperty(input.dataset.colourVar, input.value);
  });
}
function boardSettingsPalettes(form) { try { return JSON.parse(form.dataset.palettes || '{}'); } catch (e) { return {}; } }
function boardSettingsApplyPalette(form, name) {
  const set = boardSettingsPalettes(form)[name];
  if (!set) return;
  form.querySelectorAll('[data-colour-var]').forEach(function(input) {
    if (set[input.dataset.colourKey]) input.value = set[input.dataset.colourKey];
  });
  boardSettingsPreviewColours(form);
}
function boardSettingsSetPalette(form, name) {
  form.querySelectorAll('[data-palette]').forEach(function(r) { r.checked = r.value === name; });
}
function boardSettingsRevert(form) {
  form.querySelectorAll('[data-colour-var]').forEach(function(input) {
    input.value = input.dataset.saved;
    document.documentElement.style.setProperty(input.dataset.colourVar, input.dataset.saved);
  });
  boardSettingsSetPalette(form, form.dataset.savedPalette || 'bright');
  const mode = form.dataset.savedMode || 'words';
  form.querySelectorAll('[data-label-mode]').forEach(function(r) { r.checked = r.value === mode; });
  boardApplyLabelMode(mode);
}
function boardSettingsClick(event) {
  const t = event.target;
  if (!t.closest) return;
  const opener = t.closest('[data-open-settings]');
  const modal = boardSettingsModal();
  if (opener && modal) { event.preventDefault(); modal.hidden = false; return; }
  const closer = t.closest('[data-close-settings]');
  if (closer && modal) { event.preventDefault(); boardSettingsRevert(boardSettingsForm()); modal.hidden = true; return; }
  const reset = t.closest('[data-reset-colours]');
  if (reset) {
    event.preventDefault();
    const form = boardSettingsForm();
    boardSettingsSetPalette(form, 'bright');
    boardSettingsApplyPalette(form, 'bright');
  }
}
function boardSettingsInput(event) {
  const t = event.target;
  if (!t.closest || !t.closest('[data-settings-form]')) return;
  if (t.matches('[data-label-mode]')) boardApplyLabelMode(t.value);
  if (t.matches('[data-palette]')) boardSettingsApplyPalette(t.closest('form'), t.value);
  if (t.matches('[data-colour-var]')) {
    const form = t.closest('form');
    const current = form.querySelector('[data-palette]:checked');
    if (!current || current.value !== 'custom') {
      if (form.dataset.hasCustom === '1' && !window.confirm('Editing a colour switches to your Custom palette and replaces the Custom colours you already saved. Continue?')) {
        const set = boardSettingsPalettes(form)[current ? current.value : 'bright'] || {};
        t.value = set[t.dataset.colourKey] || t.dataset.default;
        return;
      }
      boardSettingsSetPalette(form, 'custom');
    }
    document.documentElement.style.setProperty(t.dataset.colourVar, t.value);
  }
}
function boardSettingsSubmit(event) {
  const form = event.target;
  if (!form.matches || !form.matches('[data-settings-form]') || !window.fetch || typeof FormData === 'undefined') return;
  event.preventDefault();
  fetch(form.getAttribute('action') || './', {method: 'POST', body: new FormData(form), credentials: 'same-origin'})
    .then(function(response) { if (!response.ok) throw new Error('Settings save failed'); return fetch('./?board_version=1', {credentials: 'same-origin', cache: 'no-store'}); })
    .then(function(response) { return response.json(); })
    .then(function(data) {
      if (data && data.version) boardVersion = data.version;
      form.querySelectorAll('[data-colour-var]').forEach(function(input) { input.dataset.saved = input.value; });
      const pal = form.querySelector('[data-palette]:checked');
      if (pal) { form.dataset.savedPalette = pal.value; if (pal.value === 'custom') form.dataset.hasCustom = '1'; }
      const checked = form.querySelector('[data-label-mode]:checked');
      if (checked) form.dataset.savedMode = checked.value;
      boardSettingsModal().hidden = true;
    })
    .catch(function() { window.alert('Settings could not be saved. Your edits are untouched; try again.'); });
}
document.addEventListener('click', boardSettingsClick);
document.addEventListener('input', boardSettingsInput);
document.addEventListener('change', boardSettingsInput);
document.addEventListener('submit', boardSettingsSubmit, true);
document.addEventListener('keydown', boardAbortRecordingOnEscape, true);
document.addEventListener('DOMContentLoaded', boardInitRecorder);
document.addEventListener('DOMContentLoaded', boardInitVoiceScopeModals);
document.addEventListener('DOMContentLoaded', boardInitAddItemModal);
document.addEventListener('DOMContentLoaded', boardSyncAddItemDisclosure);
document.addEventListener('DOMContentLoaded', boardRestoreOpenState);
document.addEventListener('DOMContentLoaded', boardReopenSubmittedItem);
document.addEventListener('DOMContentLoaded', boardRestoreFocusedItem);
document.addEventListener('DOMContentLoaded', boardEnableDetailsMotion);
document.addEventListener('DOMContentLoaded', function() { boardInitFormattedNotesEditors(); });
document.addEventListener('DOMContentLoaded', boardFlushQueuedRecordings);
document.addEventListener('DOMContentLoaded', boardInitAutoRefresh);
window.addEventListener('online', boardFlushQueuedRecordings);
<?php if (in_array($view, ['add_section', 'edit_section', 'add_project', 'edit_project'], true)): ?>
document.addEventListener('keydown', boardCancelOnEscape, true);
document.addEventListener('keyup', boardCancelOnEscape, true);
<?php endif; ?>
</script>
