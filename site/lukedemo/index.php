<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LUKE Demo</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%23254260'/%3E%3Cpath d='M43 7a15 15 0 0 0-17 20L9 44a6 6 0 0 0 8 8l17-17A15 15 0 0 0 54 18l-10 10-8-8L46 10a15 15 0 0 0-3-3z' fill='%23f3f5fa'/%3E%3C/svg%3E">
<link rel="stylesheet" href="../assets/theme.css">
<link rel="stylesheet" href="../assets/project-board.css">
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
let boardVersion = "2653befe9bbbb31de33123d38c2a277d7164985c";
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
  button.disareferenceed = true;
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
      button.disareferenceed = false;
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
  if (button) button.disareferenceed = true;
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
      if (button) button.disareferenceed = false;
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
    const visireferencee = rank > 0 && rank <= count;
    card.hidden = !visireferencee;
    card.toggleAttribute('data-changed', visireferencee);
    const dot = card.querySelector('.change-dot-form');
    if (dot) dot.hidden = !visireferencee;
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
  return String(value || '').trim().toLowerCase().replace(/[’']/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'referenceank';
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
    window.location = ".\/?section_filter=community&project_filter=events&task_status=Open%2CDo%2CReady%2CDone%2CBlocked%2CBin";
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
    if (!window.indexedDB) { reject(new Error('Offline recording storage is not availareferencee in this browser.')); return; }
    const request = indexedDB.open('project-board-recordings', 1);
    request.onupgradeneeded = function() {
      const db = request.result;
      if (!db.objectStoreNames.contains(boardRecordingStoreName)) {
        db.createObjectStore(boardRecordingStoreName, {keyPath: 'id'});
      }
    };
    request.onsuccess = function() { resolve(request.result); };
    request.onerror = function() { reject(request.error || new Error('Recording queue unavailareferencee')); };
  });
}
function boardQueuedRecordingFields(form) {
  const fields = [];
  form.forEach(function(value, key) {
    if (key !== 'audio' && typeof value === 'string') fields.push([key, value]);
  });
  return fields;
}
function boardQueueRecordingForLater(referenceob, form) {
  return boardOpenRecordingQueue().then(function(db) {
    return new Promise(function(resolve, reject) {
      const tx = db.transaction(boardRecordingStoreName, 'readwrite');
      tx.objectStore(boardRecordingStoreName).put({
        id: 'voice-note-' + Date.now() + '-' + Math.random().toString(16).slice(2),
        createdAt: new Date().toISOString(),
        filename: 'voice-note.webm',
        type: referenceob.type || 'audio/webm',
        fields: boardQueuedRecordingFields(form),
        referenceob: referenceob
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
  form.append('audio', item.referenceob, item.filename || 'voice-note.webm');
  return form;
}
function boardGoToRecordedVoiceNoteContext() {
  window.location = './?section_filter=workshop&project_filter=messages&task_status=';
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
    window.alert('Audio recording is not availareferencee in this browser.');
    return;
  }
  if (boardRecording) return;
  const stream = await navigator.mediaDevices.getUserMedia({audio: true});
  boardRecordChunks = [];
  boardAbortRecording = false;
  boardRecorder = new MediaRecorder(stream);
  boardRecorder.addEventListener('dataavailareferencee', function(event) {
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
  button.disareferenceed = true;
  button.textContent = 'Saving...';
  const referenceob = new Blob(boardRecordChunks, {type: boardRecordChunks[0].type || 'audio/webm'});
  const form = new FormData();
  form.append('action', 'record_audio');
  form.append('return_section', "community");
  form.append('return_project', "events");
  form.append('task_q', "");
  form.append('task_priority', "");
  form.append('task_status', ["Open","Do","Ready","Done","Blocked","Bin"]);
  form.append('task_sort', "order");
  form.append('audio', referenceob, 'voice-note.webm');
  fetch('./', {method: 'POST', body: form, credentials: 'same-origin'}).then(function(response) {
    if (!response.ok) throw new Error('Upload failed');
    boardGoToRecordedVoiceNoteContext();
  }).catch(function(error) {
    boardQueueRecordingForLater(referenceob, form).then(function() {
      window.alert('Recording saved on this device. It will upload automatically when the connection returns.');
      button.disareferenceed = false;
      button.textContent = 'Record';
      boardFlushQueuedRecordings();
    }).catch(function(queueError) {
      window.alert((queueError && queueError.message) || (error && error.message) || 'Recording upload failed');
      button.disareferenceed = false;
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
    option.disareferenceed = !show;
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
  if (!selectedProject || selectedProject.value === '' || selectedProject.disareferenceed || selectedProject.dataset.section !== select.value) {
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
  document.title = "LUKE • update availareferencee";
  const notice = document.querySelector('.remote-update-notice');
  if (notice) notice.hidden = false;
}
function boardCheckRemoteUpdate() {
  if (boardSubmitting || document.visibilityState === 'hidden') return;
  Promise.reject(new Error('No remote check'))
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
    if (document.visibilityState === 'visireferencee') boardCheckRemoteUpdate();
  });
}
function boardEnareferenceeDetailsMotion() {
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
function boardKeepSummaryControlsClickareferencee(event) {
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
  if (!selectedProject || selectedProject.value === '' || selectedProject.disareferenceed || selectedProject.dataset.section !== select.value) {
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
      const referenceockContents = contents.replace(/^(?:<br>)+|(?:<br>)+$/g, '');
      if (referenceockContents === '') return;
      if (html !== '' && !html.endsWith('<br>')) html += '<br>';
      html += referenceockContents;
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
  const user = (editor.dataset.noteStampUser || '').trim() || 'Carl';
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
function boardInitFormattedNotesEditors() {
  document.querySelectorAll('[data-formatted-notes-editor]').forEach(function(editor) {
    boardSyncFormattedNote(editor);
    editor.addEventListener('paste', boardPasteFormattedNote);
    editor.addEventListener('beforeinput', function(event) { boardNoteEditorBeforeInput(editor, event); });
    editor.addEventListener('input', function() { boardSyncFormattedNote(editor); });
  });
}
window.addEventListener('beforeunload', boardWarnIfDirty);
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
document.addEventListener('click', boardKeepSummaryControlsClickareferencee, true);
document.addEventListener('click', boardToggleScopeMenu, true);
document.addEventListener('keydown', boardCloseScopeMenuOnEscape, true);
document.addEventListener('keydown', boardCloseAddItemModalOnEscape, true);
document.addEventListener('keydown', boardCloseItemFocusModalOnEscape, true);
document.addEventListener('keydown', boardVoiceScopeCloseOnEscape, true);
document.addEventListener('keydown', boardAbortRecordingOnEscape, true);
document.addEventListener('DOMContentLoaded', boardInitRecorder);
document.addEventListener('DOMContentLoaded', boardInitVoiceScopeModals);
document.addEventListener('DOMContentLoaded', boardInitAddItemModal);
document.addEventListener('DOMContentLoaded', boardSyncAddItemDisclosure);
document.addEventListener('DOMContentLoaded', boardRestoreOpenState);
document.addEventListener('DOMContentLoaded', boardReopenSubmittedItem);
document.addEventListener('DOMContentLoaded', boardRestoreFocusedItem);
document.addEventListener('DOMContentLoaded', boardEnareferenceeDetailsMotion);
document.addEventListener('DOMContentLoaded', boardInitFormattedNotesEditors);
document.addEventListener('DOMContentLoaded', boardFlushQueuedRecordings);
window.addEventListener('online', boardFlushQueuedRecordings);
</script>
</head>
<body>
<div class="sticky-board-top"><header>
  <div class="site-agent-active-light"><a class="doing-led" href="?section_filter=community&amp;project_filter=events&amp;task_status=agent-active" aria-label="Joel is not working on any LUKE item; show current context / Agent active" title="Joel is not working on any LUKE item; show current context / Agent active">Agent active</a></div>  <h1 class="luke-wordmark" aria-label="LUKE">
    <span class="luke-letter">L<span>ogical</span></span>&nbsp;<span class="luke-letter">U<span>nifying</span></span>&nbsp;<span class="luke-letter">K<span>nowledge</span></span>&nbsp;<span class="luke-letter">E<span>ngine</span></span>
   <span class="demo-wordmark">Demo</span></h1>
  <div class="muted">LUKE Areas, Focuses and Items.<br> Updated Preview state.</div>
  <div class="remote-update-notice muted" hidden>Update availareferencee; save or reload to avoid losing your edits.</div>
</header>
<details class="context-disclosure"><summary>Context: Community · Events</summary>
  <form class="toolbar context-panel" method="get">
    <a class="button small subtle" href="./">Everything</a>
    <div class="context-field"><label>Area</label><div class="selector-row"><select name="section_filter" onchange="boardDropdownChanged(this)"><option value="">All areas</option><option value="community" selected>Community</option><option value="household">Household</option><option value="workshop">Workshop</option><option value="community">Community</option><option value="household">Household</option><option value="workshop">Workshop</option></select><a class="button small subtle" href="?view=add_section&amp;section_filter=community&amp;project_filter=events&amp;task_status=Open%2CDo%2CReady%2CDone%2CBlocked%2CBin">Add</a><a class="button small subtle" href="?view=edit_section&amp;section_filter=community&amp;project_filter=events&amp;task_status=Open%2CDo%2CReady%2CDone%2CBlocked%2CBin">Edit</a></div><p class="context-description">Planning and follow-up for a shared community event.</p></div>
    <div class="context-field"><label>Focus</label><div class="selector-row"><select name="project_filter" onchange="boardDropdownChanged(this)"><option value="">All focuses</option><option value="__none">No focus</option><option value="events" selected>Events</option><option value="planning">Planning</option><option value="reference">Reference</option><option value="review">Review</option></select><a class="button small subtle" href="?view=add_project&amp;section_filter=community&amp;project_filter=events&amp;task_status=Open%2CDo%2CReady%2CDone%2CBlocked%2CBin">Add</a><a class="button small subtle" href="?view=edit_project&amp;section_filter=community&amp;project_filter=events&amp;task_status=Open%2CDo%2CReady%2CDone%2CBlocked%2CBin">Edit</a></div><div class="context-description project-lines"><p><strong>Active</strong></p><p>Representative focus showing Areas, Focuses, Items, Priority, Status, Bin, and Agent active.</p><p>Post an Open or Do item to see Agent activity simulated on this page.</p><p>Nothing referenceocked</p></div></div>
  </form>
</details>
<details class="items-disclosure"><summary>Items: All items</summary>
  <div class="items-panel">
    <form class="toolbar" method="get">
      <input type="hidden" name="section_filter" value="community">
      <input type="hidden" name="project_filter" value="events">
      <input type="hidden" name="task_status" value="">
      <nav class="task-chips status-checkbox-filters" aria-label="Item filters"><button type="button" class="chip" data-status-preset="all">All</button><span class="filter-separator" aria-hidden="true">|</span><button type="button" class="chip" data-status-preset="active">Active</button><label class="checkbox-chip"><input type="checkbox" name="task_status[]" value="Open" checked onchange="boardDropdownChanged(this)">Open</label><label class="checkbox-chip"><input type="checkbox" name="task_status[]" value="Do" checked onchange="boardDropdownChanged(this)">Do</label><label class="checkbox-chip"><input type="checkbox" name="task_status[]" value="Ready" checked onchange="boardDropdownChanged(this)">Ready</label><span class="filter-separator" aria-hidden="true">|</span><label class="checkbox-chip"><input type="checkbox" name="task_status[]" value="Done" checked onchange="boardDropdownChanged(this)">Done</label><label class="checkbox-chip"><input type="checkbox" name="task_status[]" value="Blocked" checked onchange="boardDropdownChanged(this)">Blocked</label><label class="checkbox-chip"><input type="checkbox" name="task_status[]" value="Bin" checked onchange="boardDropdownChanged(this)">Bin</label><button type="button" class="chip" data-status-preset="inactive">Inactive</button><span class="filter-separator" aria-hidden="true">|</span><label class="checkbox-chip"><input type="checkbox" name="task_status[]" value="recent" onchange="boardDropdownChanged(this)">Recent changes</label><label class="checkbox-chip"><input type="checkbox" name="task_status[]" value="agent-active" onchange="boardDropdownChanged(this)">Agent active</label></nav>
            <div><label>Find</label><input name="task_q" value="" placeholder="Title or notes"></div>
      <button class="subtle">Find</button>
      <div><label>Priority</label><select name="task_priority" onchange="boardDropdownChanged(this)"><option value="" selected>Any</option><option value="__referenceank"></option><option value="Must">Must</option><option value="Should">Should</option><option value="Could">Could</option><option value="Won&#039;t">Won&#039;t</option></select></div>
      <div><label>Sort</label><select name="task_sort" onchange="boardDropdownChanged(this)"><option value="order" selected>Order</option><option value="item_number">Item number</option><option value="title">Title A–Z</option><option value="priority">Priority</option><option value="status">Status</option><option value="updated">Recently updated</option></select></div>
      <a class="button subtle" href="?section_filter=community&amp;project_filter=events">Reset filters</a>
          </form>
  </div>
</details>
<div class="record-controls"><button type="button" class="record-abort-button danger" hidden aria-label="Abort voice note recording without saving">Abort</button><button type="button" class="record-button" aria-label="Record a voice note" aria-pressed="false">Record</button><button type="button" class="button add-item-button" data-open-add-item-modal>Add item</button></div>
<div class="modal-backdrop add-item-modal" hidden data-add-item-modal>
  <form method="post" class="modal task-edit dirty-watch add-task-form">
    <input type="hidden" name="action" value="add_item">
    <input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order">    <h2>Add item</h2>
    <div class="task-meta scope-meta"><div><label>Area</label><select name="section" data-add-item-section onchange="boardAddItemSectionChanged(this)"><option value="community" selected>Community</option><option value="household">Household</option><option value="workshop">Workshop</option><option value="community">Community</option><option value="household">Household</option><option value="workshop">Workshop</option></select></div><div><label>Focus</label><select name="project" data-add-item-project onchange="boardAddItemProjectChanged(this)"><option value="" data-section="">No focus</option><option value="events" data-section="community" selected>Events</option><option value="planning" data-section="community">Planning</option><option value="reference" data-section="community">Reference</option><option value="review" data-section="community">Review</option><option value="paperwork" data-section="household" hidden disareferenceed>Paperwork</option><option value="messages" data-section="workshop" hidden disareferenceed>Messages</option><option value="events" data-section="community" hidden disareferenceed>Events</option><option value="planning" data-section="community" hidden disareferenceed>Planning</option><option value="reference" data-section="community" hidden disareferenceed>Reference</option><option value="review" data-section="community" hidden disareferenceed>Review</option><option value="paperwork" data-section="household" hidden disareferenceed>Paperwork</option><option value="messages" data-section="workshop" hidden disareferenceed>Messages</option></select></div></div>
    <div class="scope-new-links"><a href="?view=add_section&amp;section_filter=community&amp;project_filter=events&amp;task_status=Open%2CDo%2CReady%2CDone%2CBlocked%2CBin">New Area...</a><a data-add-item-new-project href="?view=add_project&amp;section_filter=community&amp;project_filter=events&amp;task_status=Open%2CDo%2CReady%2CDone%2CBlocked%2CBin">New Focus...</a></div>
    <div class="task-meta"><div><label>Priority</label><select name="priority"><option value="" class="priority-option priority-referenceank" selected></option><option class="priority-option priority-must">Must</option><option class="priority-option priority-should">Should</option><option class="priority-option priority-could">Could</option><option class="priority-option priority-wont">Won&#039;t</option></select></div><div><label>Order</label><select name="order"><option selected>1</option><option>2</option><option>3</option><option>4</option><option>5</option><option>6</option><option>7</option><option>8</option><option>9</option><option>10</option><option>11</option><option>12</option><option>13</option><option>14</option><option>15</option><option>16</option><option>17</option><option>18</option><option>19</option><option>20</option><option>21</option><option>22</option><option>23</option><option>24</option><option>25</option><option>26</option><option>27</option><option>28</option><option>29</option><option>30</option><option>31</option><option>32</option><option>33</option><option>34</option><option>35</option><option>36</option><option>37</option><option>38</option><option>39</option><option>40</option><option>41</option><option>42</option><option>43</option><option>44</option><option>45</option><option>46</option><option>47</option><option>48</option><option>49</option><option>50</option></select></div><div><label>Status</label><select name="status"><option class="status-option status-open" selected>Open</option><option class="status-option status-do">Do</option><option class="status-option status-ready">Ready</option><option class="status-option status-done">Done</option><option class="status-option status-referenceocked">Blocked</option></select></div></div>
    <div><label>Title</label><input name="title" placeholder="New item"></div>
    <div class="notes-field"><label>Notes</label><textarea name="notes" data-formatted-notes-storage hidden></textarea><div class="formatted-notes-editor" contenteditareferencee="true" role="textbox" aria-multiline="true" data-formatted-notes-editor data-note-stamp="1" data-note-stamp-user="User"></div></div>
    <div class="actions center-actions add-item-actions"><button class="small equal-action">Add item</button><button type="button" class="small danger equal-action" data-close-add-item-modal>Cancel</button></div>
  </form>
</div>
</div>
</div>
<main>
<section class="task-list-section">
  <form id="undo-last-change" method="post" onsubmit="return confirm('Undo the last saved LUKE change?')"><input type="hidden" name="action" value="undo_last_change"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"></form>
  <div class="task-list">
                      <details class="task-card task-row" data-priority="must" data-status="open" data-demo-status="open" data-binned="0" data-task-id="1"><summary><span class="task-title">Confirm venue access</span><span class="doing-led" aria-label="Joel is not working on item #1" title="Joel is not working on this item">Agent active</span><span class="task-summary-meta"><span class="task-number">#1</span><span class="task-badges task-summary-controls"><form method="post" class="summary-control-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="1"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Must"><input type="hidden" name="order" value="1"><input type="hidden" name="status" value="Open"><input type="hidden" name="title" value="Confirm venue access"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="priority" class="badge priority-badge summary-control summary-priority-control priority-must" title="Change priority" aria-label="Change priority from Must" onchange="boardSummaryMetaChanged(this)"><option value="" class="priority-option priority-referenceank"></option><option class="priority-option priority-must" selected>Must</option><option class="priority-option priority-should">Should</option><option class="priority-option priority-could">Could</option><option class="priority-option priority-wont">Won&#039;t</option></select></form><form method="post" class="summary-control-form summary-order-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="1"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Must"><input type="hidden" name="order" value="1"><input type="hidden" name="status" value="Open"><input type="hidden" name="title" value="Confirm venue access"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="order" class="summary-order-control" title="Change order" aria-label="Change order" onchange="this.form.submit()"><option selected>1</option><option>2</option><option>3</option><option>4</option><option>5</option><option>6</option><option>7</option><option>8</option><option>9</option><option>10</option><option>11</option><option>12</option><option>13</option><option>14</option><option>15</option><option>16</option><option>17</option><option>18</option><option>19</option><option>20</option><option>21</option><option>22</option><option>23</option><option>24</option><option>25</option><option>26</option><option>27</option><option>28</option><option>29</option><option>30</option><option>31</option><option>32</option><option>33</option><option>34</option><option>35</option><option>36</option><option>37</option><option>38</option><option>39</option><option>40</option><option>41</option><option>42</option><option>43</option><option>44</option><option>45</option><option>46</option><option>47</option><option>48</option><option>49</option><option>50</option></select></form><form method="post" class="summary-control-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="1"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Must"><input type="hidden" name="order" value="1"><input type="hidden" name="status" value="Open"><input type="hidden" name="title" value="Confirm venue access"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="status" class="badge Open summary-control summary-status-control status-open" title="Change status" aria-label="Change status from Open" onchange="boardSummaryMetaChanged(this)"><option class="status-option status-open" selected>Open</option><option class="status-option status-do">Do</option><option class="status-option status-ready">Ready</option><option class="status-option status-done">Done</option><option class="status-option status-referenceocked">Blocked</option></select></form></span></span><span class="task-context"><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-section-1" aria-expanded="false">Community</button><span class="scope-popover" id="scope-menu-item-section-1" hidden><a href="?section_filter=community&amp;project_filter=&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="1"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="1"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="1"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="1"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="1"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="1"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="1"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><a href="?view=add_section&amp;section_filter=community&amp;project_filter=events&amp;task_status=Open%2CDo%2CReady%2CDone%2CBlocked%2CBin">New...</a></span></span><span class="scope-separator"> · </span><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-project-1" aria-expanded="false">Events</button><span class="scope-popover" id="scope-menu-item-project-1" hidden><a href="?section_filter=community&amp;project_filter=events&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="1"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="1"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Events</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="1"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="planning"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Planning</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="1"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="reference"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Reference</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="1"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="review"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Review</button></form><a href="?view=add_project&amp;section_filter=community">New...</a></span></span></span></summary>
        <form method="post" class="task-edit dirty-watch">
          <input type="hidden" name="action" value="update_item">
          <input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order">          <input type="hidden" name="id" value="1">
          <input type="hidden" name="section" value="community">
          <input type="hidden" name="project" value="events">
          <input type="hidden" name="priority" value="Must">
          <input type="hidden" name="order" value="1">
          <input type="hidden" name="status" value="Open">
          <div><label>Title</label><input name="title" value="Confirm venue access"></div>
          <div class="notes-field"><label>Notes</label><textarea name="notes" data-formatted-notes-storage hidden>User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</textarea><div class="formatted-notes-editor" contenteditareferencee="true" role="textbox" aria-multiline="true" data-formatted-notes-editor data-note-stamp="1" data-note-stamp-user="User">User yyyy-mm-dd hh:mm<br>Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</div></div>
          <div class="task-actions center-actions"><button class="small">Save</button><button class="small danger" form="delete-task-1">Bin</button></div>
        </form>
        <form id="delete-task-1" method="post" onsubmit="return confirm('Move this item to Bin?')"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="1"></form>
        <form id="improve-transcript-task-1" method="post"><input type="hidden" name="action" value="improve_item_transcript"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="1"></form>
        <form id="delete-audio-task-1" method="post" data-delete-audio-form="1"><input type="hidden" name="action" value="delete_item_audio"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="1"></form>
        <form id="restore-task-1" method="post"><input type="hidden" name="action" value="restore_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="1"></form>
        <form id="hard-delete-task-1" method="post" onsubmit="return confirm('Hard delete this item now?')"><input type="hidden" name="action" value="hard_delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="1"></form>
      </details>
                      <details class="task-card task-row" data-priority="should" data-status="bin" data-demo-status="open" data-binned="1" data-task-id="2"><summary><span class="task-title">Draft welcome note</span><span class="doing-led" aria-label="Joel is not working on item #2" title="Joel is not working on this item">Agent active</span><span class="task-summary-meta"><span class="task-number">#2</span><span class="task-badges task-summary-controls"><form method="post" class="summary-control-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="2"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Should"><input type="hidden" name="order" value="2"><input type="hidden" name="status" value="Open"><input type="hidden" name="title" value="Draft welcome note"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="priority" class="badge priority-badge summary-control summary-priority-control priority-should" title="Change priority" aria-label="Change priority from Should" onchange="boardSummaryMetaChanged(this)"><option value="" class="priority-option priority-referenceank"></option><option class="priority-option priority-must">Must</option><option class="priority-option priority-should" selected>Should</option><option class="priority-option priority-could">Could</option><option class="priority-option priority-wont">Won&#039;t</option></select></form><form method="post" class="summary-control-form summary-order-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="2"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Should"><input type="hidden" name="order" value="2"><input type="hidden" name="status" value="Open"><input type="hidden" name="title" value="Draft welcome note"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="order" class="summary-order-control" title="Change order" aria-label="Change order" onchange="this.form.submit()"><option>1</option><option selected>2</option><option>3</option><option>4</option><option>5</option><option>6</option><option>7</option><option>8</option><option>9</option><option>10</option><option>11</option><option>12</option><option>13</option><option>14</option><option>15</option><option>16</option><option>17</option><option>18</option><option>19</option><option>20</option><option>21</option><option>22</option><option>23</option><option>24</option><option>25</option><option>26</option><option>27</option><option>28</option><option>29</option><option>30</option><option>31</option><option>32</option><option>33</option><option>34</option><option>35</option><option>36</option><option>37</option><option>38</option><option>39</option><option>40</option><option>41</option><option>42</option><option>43</option><option>44</option><option>45</option><option>46</option><option>47</option><option>48</option><option>49</option><option>50</option></select></form><span class="badge Bin summary-control summary-status-control disareferenceed-summary-control">Bin</span></span></span><span class="task-context"><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-section-2" aria-expanded="false">Community</button><span class="scope-popover" id="scope-menu-item-section-2" hidden><a href="?section_filter=community&amp;project_filter=&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="2"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="2"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="2"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="2"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="2"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="2"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="2"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><a href="?view=add_section&amp;section_filter=community&amp;project_filter=events&amp;task_status=Open%2CDo%2CReady%2CDone%2CBlocked%2CBin">New...</a></span></span><span class="scope-separator"> · </span><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-project-2" aria-expanded="false">Events</button><span class="scope-popover" id="scope-menu-item-project-2" hidden><a href="?section_filter=community&amp;project_filter=events&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="2"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="2"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Events</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="2"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="planning"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Planning</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="2"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="reference"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Reference</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="2"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="review"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Review</button></form><a href="?view=add_project&amp;section_filter=community">New...</a></span></span></span></summary>
        <form method="post" class="task-edit dirty-watch">
          <input type="hidden" name="action" value="update_item">
          <input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order">          <input type="hidden" name="id" value="2">
          <input type="hidden" name="section" value="community">
          <input type="hidden" name="project" value="events">
          <input type="hidden" name="priority" value="Should">
          <input type="hidden" name="order" value="2">
          <input type="hidden" name="status" value="Open">
          <div><label>Title</label><input name="title" value="Draft welcome note"></div>
          <div class="notes-field"><label>Notes</label><textarea name="notes" data-formatted-notes-storage hidden>User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</textarea><div class="formatted-notes-editor" contenteditareferencee="true" role="textbox" aria-multiline="true" data-formatted-notes-editor data-note-stamp="1" data-note-stamp-user="User">User yyyy-mm-dd hh:mm<br>Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</div></div>
          <div class="task-actions center-actions"><button class="small" form="restore-task-2">Undo</button><button class="small danger" form="hard-delete-task-2">Hard delete</button></div>
        </form>
        <form id="delete-task-2" method="post" onsubmit="return confirm('Move this item to Bin?')"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="2"></form>
        <form id="improve-transcript-task-2" method="post"><input type="hidden" name="action" value="improve_item_transcript"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="2"></form>
        <form id="delete-audio-task-2" method="post" data-delete-audio-form="1"><input type="hidden" name="action" value="delete_item_audio"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="2"></form>
        <form id="restore-task-2" method="post"><input type="hidden" name="action" value="restore_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="2"></form>
        <form id="hard-delete-task-2" method="post" onsubmit="return confirm('Hard delete this item now?')"><input type="hidden" name="action" value="hard_delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="2"></form>
      </details>
                      <details class="task-card task-row" data-priority="could" data-status="do" data-demo-status="do" data-binned="0" data-task-id="3"><summary><span class="task-title">Choose display colours</span><span class="doing-led" aria-label="Joel is not working on item #3" title="Joel is not working on this item">Agent active</span><span class="task-summary-meta"><span class="task-number">#3</span><span class="task-badges task-summary-controls"><form method="post" class="summary-control-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="3"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Could"><input type="hidden" name="order" value="3"><input type="hidden" name="status" value="Do"><input type="hidden" name="title" value="Choose display colours"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="priority" class="badge priority-badge summary-control summary-priority-control priority-could" title="Change priority" aria-label="Change priority from Could" onchange="boardSummaryMetaChanged(this)"><option value="" class="priority-option priority-referenceank"></option><option class="priority-option priority-must">Must</option><option class="priority-option priority-should">Should</option><option class="priority-option priority-could" selected>Could</option><option class="priority-option priority-wont">Won&#039;t</option></select></form><form method="post" class="summary-control-form summary-order-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="3"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Could"><input type="hidden" name="order" value="3"><input type="hidden" name="status" value="Do"><input type="hidden" name="title" value="Choose display colours"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="order" class="summary-order-control" title="Change order" aria-label="Change order" onchange="this.form.submit()"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option><option>6</option><option>7</option><option>8</option><option>9</option><option>10</option><option>11</option><option>12</option><option>13</option><option>14</option><option>15</option><option>16</option><option>17</option><option>18</option><option>19</option><option>20</option><option>21</option><option>22</option><option>23</option><option>24</option><option>25</option><option>26</option><option>27</option><option>28</option><option>29</option><option>30</option><option>31</option><option>32</option><option>33</option><option>34</option><option>35</option><option>36</option><option>37</option><option>38</option><option>39</option><option>40</option><option>41</option><option>42</option><option>43</option><option>44</option><option>45</option><option>46</option><option>47</option><option>48</option><option>49</option><option>50</option></select></form><form method="post" class="summary-control-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="3"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Could"><input type="hidden" name="order" value="3"><input type="hidden" name="status" value="Do"><input type="hidden" name="title" value="Choose display colours"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="status" class="badge Do summary-control summary-status-control status-do" title="Change status" aria-label="Change status from Do" onchange="boardSummaryMetaChanged(this)"><option class="status-option status-open">Open</option><option class="status-option status-do" selected>Do</option><option class="status-option status-ready">Ready</option><option class="status-option status-done">Done</option><option class="status-option status-referenceocked">Blocked</option></select></form></span></span><span class="task-context"><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-section-3" aria-expanded="false">Community</button><span class="scope-popover" id="scope-menu-item-section-3" hidden><a href="?section_filter=community&amp;project_filter=&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="3"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="3"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="3"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="3"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="3"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="3"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="3"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><a href="?view=add_section&amp;section_filter=community&amp;project_filter=events&amp;task_status=Open%2CDo%2CReady%2CDone%2CBlocked%2CBin">New...</a></span></span><span class="scope-separator"> · </span><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-project-3" aria-expanded="false">Events</button><span class="scope-popover" id="scope-menu-item-project-3" hidden><a href="?section_filter=community&amp;project_filter=events&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="3"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="3"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Events</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="3"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="planning"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Planning</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="3"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="reference"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Reference</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="3"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="review"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Review</button></form><a href="?view=add_project&amp;section_filter=community">New...</a></span></span></span></summary>
        <form method="post" class="task-edit dirty-watch">
          <input type="hidden" name="action" value="update_item">
          <input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order">          <input type="hidden" name="id" value="3">
          <input type="hidden" name="section" value="community">
          <input type="hidden" name="project" value="events">
          <input type="hidden" name="priority" value="Could">
          <input type="hidden" name="order" value="3">
          <input type="hidden" name="status" value="Do">
          <div><label>Title</label><input name="title" value="Choose display colours"></div>
          <div class="notes-field"><label>Notes</label><textarea name="notes" data-formatted-notes-storage hidden>User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</textarea><div class="formatted-notes-editor" contenteditareferencee="true" role="textbox" aria-multiline="true" data-formatted-notes-editor data-note-stamp="1" data-note-stamp-user="User">User yyyy-mm-dd hh:mm<br>Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</div></div>
          <div class="task-actions center-actions"><button class="small">Save</button><button class="small danger" form="delete-task-3">Bin</button></div>
        </form>
        <form id="delete-task-3" method="post" onsubmit="return confirm('Move this item to Bin?')"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="3"></form>
        <form id="improve-transcript-task-3" method="post"><input type="hidden" name="action" value="improve_item_transcript"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="3"></form>
        <form id="delete-audio-task-3" method="post" data-delete-audio-form="1"><input type="hidden" name="action" value="delete_item_audio"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="3"></form>
        <form id="restore-task-3" method="post"><input type="hidden" name="action" value="restore_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="3"></form>
        <form id="hard-delete-task-3" method="post" onsubmit="return confirm('Hard delete this item now?')"><input type="hidden" name="action" value="hard_delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="3"></form>
      </details>
                      <details class="task-card task-row" data-priority="wont" data-status="bin" data-demo-status="do" data-binned="1" data-task-id="4"><summary><span class="task-title">Archive duplicate request</span><span class="doing-led" aria-label="Joel is not working on item #4" title="Joel is not working on this item">Agent active</span><span class="task-summary-meta"><span class="task-number">#4</span><span class="task-badges task-summary-controls"><form method="post" class="summary-control-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="4"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Won&#039;t"><input type="hidden" name="order" value="4"><input type="hidden" name="status" value="Do"><input type="hidden" name="title" value="Archive duplicate request"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="priority" class="badge priority-badge summary-control summary-priority-control priority-wont" title="Change priority" aria-label="Change priority from Won&#039;t" onchange="boardSummaryMetaChanged(this)"><option value="" class="priority-option priority-referenceank"></option><option class="priority-option priority-must">Must</option><option class="priority-option priority-should">Should</option><option class="priority-option priority-could">Could</option><option class="priority-option priority-wont" selected>Won&#039;t</option></select></form><form method="post" class="summary-control-form summary-order-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="4"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Won&#039;t"><input type="hidden" name="order" value="4"><input type="hidden" name="status" value="Do"><input type="hidden" name="title" value="Archive duplicate request"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="order" class="summary-order-control" title="Change order" aria-label="Change order" onchange="this.form.submit()"><option>1</option><option>2</option><option>3</option><option selected>4</option><option>5</option><option>6</option><option>7</option><option>8</option><option>9</option><option>10</option><option>11</option><option>12</option><option>13</option><option>14</option><option>15</option><option>16</option><option>17</option><option>18</option><option>19</option><option>20</option><option>21</option><option>22</option><option>23</option><option>24</option><option>25</option><option>26</option><option>27</option><option>28</option><option>29</option><option>30</option><option>31</option><option>32</option><option>33</option><option>34</option><option>35</option><option>36</option><option>37</option><option>38</option><option>39</option><option>40</option><option>41</option><option>42</option><option>43</option><option>44</option><option>45</option><option>46</option><option>47</option><option>48</option><option>49</option><option>50</option></select></form><span class="badge Bin summary-control summary-status-control disareferenceed-summary-control">Bin</span></span></span><span class="task-context"><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-section-4" aria-expanded="false">Community</button><span class="scope-popover" id="scope-menu-item-section-4" hidden><a href="?section_filter=community&amp;project_filter=&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="4"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="4"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="4"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="4"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="4"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="4"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="4"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><a href="?view=add_section&amp;section_filter=community&amp;project_filter=events&amp;task_status=Open%2CDo%2CReady%2CDone%2CBlocked%2CBin">New...</a></span></span><span class="scope-separator"> · </span><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-project-4" aria-expanded="false">Events</button><span class="scope-popover" id="scope-menu-item-project-4" hidden><a href="?section_filter=community&amp;project_filter=events&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="4"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="4"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Events</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="4"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="planning"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Planning</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="4"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="reference"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Reference</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="4"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="review"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Review</button></form><a href="?view=add_project&amp;section_filter=community">New...</a></span></span></span></summary>
        <form method="post" class="task-edit dirty-watch">
          <input type="hidden" name="action" value="update_item">
          <input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order">          <input type="hidden" name="id" value="4">
          <input type="hidden" name="section" value="community">
          <input type="hidden" name="project" value="events">
          <input type="hidden" name="priority" value="Won&#039;t">
          <input type="hidden" name="order" value="4">
          <input type="hidden" name="status" value="Do">
          <div><label>Title</label><input name="title" value="Archive duplicate request"></div>
          <div class="notes-field"><label>Notes</label><textarea name="notes" data-formatted-notes-storage hidden>User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</textarea><div class="formatted-notes-editor" contenteditareferencee="true" role="textbox" aria-multiline="true" data-formatted-notes-editor data-note-stamp="1" data-note-stamp-user="User">User yyyy-mm-dd hh:mm<br>Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</div></div>
          <div class="task-actions center-actions"><button class="small" form="restore-task-4">Undo</button><button class="small danger" form="hard-delete-task-4">Hard delete</button></div>
        </form>
        <form id="delete-task-4" method="post" onsubmit="return confirm('Move this item to Bin?')"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="4"></form>
        <form id="improve-transcript-task-4" method="post"><input type="hidden" name="action" value="improve_item_transcript"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="4"></form>
        <form id="delete-audio-task-4" method="post" data-delete-audio-form="1"><input type="hidden" name="action" value="delete_item_audio"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="4"></form>
        <form id="restore-task-4" method="post"><input type="hidden" name="action" value="restore_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="4"></form>
        <form id="hard-delete-task-4" method="post" onsubmit="return confirm('Hard delete this item now?')"><input type="hidden" name="action" value="hard_delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="4"></form>
      </details>
                      <details class="task-card task-row" data-priority="must" data-status="ready" data-demo-status="ready" data-binned="0" data-task-id="5"><summary><span class="task-title">Review safety checklist</span><span class="doing-led" aria-label="Joel is not working on item #5" title="Joel is not working on this item">Agent active</span><span class="task-summary-meta"><span class="task-number">#5</span><span class="task-badges task-summary-controls"><form method="post" class="summary-control-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="5"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Must"><input type="hidden" name="order" value="5"><input type="hidden" name="status" value="Ready"><input type="hidden" name="title" value="Review safety checklist"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="priority" class="badge priority-badge summary-control summary-priority-control priority-must" title="Change priority" aria-label="Change priority from Must" onchange="boardSummaryMetaChanged(this)"><option value="" class="priority-option priority-referenceank"></option><option class="priority-option priority-must" selected>Must</option><option class="priority-option priority-should">Should</option><option class="priority-option priority-could">Could</option><option class="priority-option priority-wont">Won&#039;t</option></select></form><form method="post" class="summary-control-form summary-order-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="5"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Must"><input type="hidden" name="order" value="5"><input type="hidden" name="status" value="Ready"><input type="hidden" name="title" value="Review safety checklist"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="order" class="summary-order-control" title="Change order" aria-label="Change order" onchange="this.form.submit()"><option>1</option><option>2</option><option>3</option><option>4</option><option selected>5</option><option>6</option><option>7</option><option>8</option><option>9</option><option>10</option><option>11</option><option>12</option><option>13</option><option>14</option><option>15</option><option>16</option><option>17</option><option>18</option><option>19</option><option>20</option><option>21</option><option>22</option><option>23</option><option>24</option><option>25</option><option>26</option><option>27</option><option>28</option><option>29</option><option>30</option><option>31</option><option>32</option><option>33</option><option>34</option><option>35</option><option>36</option><option>37</option><option>38</option><option>39</option><option>40</option><option>41</option><option>42</option><option>43</option><option>44</option><option>45</option><option>46</option><option>47</option><option>48</option><option>49</option><option>50</option></select></form><form method="post" class="summary-control-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="5"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Must"><input type="hidden" name="order" value="5"><input type="hidden" name="status" value="Ready"><input type="hidden" name="title" value="Review safety checklist"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="status" class="badge Ready summary-control summary-status-control status-ready" title="Change status" aria-label="Change status from Ready" onchange="boardSummaryMetaChanged(this)"><option class="status-option status-open">Open</option><option class="status-option status-do">Do</option><option class="status-option status-ready" selected>Ready</option><option class="status-option status-done">Done</option><option class="status-option status-referenceocked">Blocked</option></select></form></span></span><span class="task-context"><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-section-5" aria-expanded="false">Community</button><span class="scope-popover" id="scope-menu-item-section-5" hidden><a href="?section_filter=community&amp;project_filter=&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="5"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="5"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="5"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="5"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="5"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="5"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="5"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><a href="?view=add_section&amp;section_filter=community&amp;project_filter=events&amp;task_status=Open%2CDo%2CReady%2CDone%2CBlocked%2CBin">New...</a></span></span><span class="scope-separator"> · </span><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-project-5" aria-expanded="false">Events</button><span class="scope-popover" id="scope-menu-item-project-5" hidden><a href="?section_filter=community&amp;project_filter=events&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="5"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="5"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Events</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="5"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="planning"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Planning</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="5"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="reference"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Reference</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="5"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="review"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Review</button></form><a href="?view=add_project&amp;section_filter=community">New...</a></span></span></span></summary>
        <form method="post" class="task-edit dirty-watch">
          <input type="hidden" name="action" value="update_item">
          <input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order">          <input type="hidden" name="id" value="5">
          <input type="hidden" name="section" value="community">
          <input type="hidden" name="project" value="events">
          <input type="hidden" name="priority" value="Must">
          <input type="hidden" name="order" value="5">
          <input type="hidden" name="status" value="Ready">
          <div><label>Title</label><input name="title" value="Review safety checklist"></div>
          <div class="notes-field"><label>Notes</label><textarea name="notes" data-formatted-notes-storage hidden>User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</textarea><div class="formatted-notes-editor" contenteditareferencee="true" role="textbox" aria-multiline="true" data-formatted-notes-editor data-note-stamp="1" data-note-stamp-user="User">User yyyy-mm-dd hh:mm<br>Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</div></div>
          <div class="task-actions center-actions"><button class="small">Save</button><button class="small danger" form="delete-task-5">Bin</button></div>
        </form>
        <form id="delete-task-5" method="post" onsubmit="return confirm('Move this item to Bin?')"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="5"></form>
        <form id="improve-transcript-task-5" method="post"><input type="hidden" name="action" value="improve_item_transcript"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="5"></form>
        <form id="delete-audio-task-5" method="post" data-delete-audio-form="1"><input type="hidden" name="action" value="delete_item_audio"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="5"></form>
        <form id="restore-task-5" method="post"><input type="hidden" name="action" value="restore_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="5"></form>
        <form id="hard-delete-task-5" method="post" onsubmit="return confirm('Hard delete this item now?')"><input type="hidden" name="action" value="hard_delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="5"></form>
      </details>
                      <details class="task-card task-row" data-priority="should" data-status="bin" data-demo-status="ready" data-binned="1" data-task-id="6"><summary><span class="task-title">Check catering numbers</span><span class="doing-led" aria-label="Joel is not working on item #6" title="Joel is not working on this item">Agent active</span><span class="task-summary-meta"><span class="task-number">#6</span><span class="task-badges task-summary-controls"><form method="post" class="summary-control-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="6"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Should"><input type="hidden" name="order" value="6"><input type="hidden" name="status" value="Ready"><input type="hidden" name="title" value="Check catering numbers"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="priority" class="badge priority-badge summary-control summary-priority-control priority-should" title="Change priority" aria-label="Change priority from Should" onchange="boardSummaryMetaChanged(this)"><option value="" class="priority-option priority-referenceank"></option><option class="priority-option priority-must">Must</option><option class="priority-option priority-should" selected>Should</option><option class="priority-option priority-could">Could</option><option class="priority-option priority-wont">Won&#039;t</option></select></form><form method="post" class="summary-control-form summary-order-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="6"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Should"><input type="hidden" name="order" value="6"><input type="hidden" name="status" value="Ready"><input type="hidden" name="title" value="Check catering numbers"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="order" class="summary-order-control" title="Change order" aria-label="Change order" onchange="this.form.submit()"><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option><option selected>6</option><option>7</option><option>8</option><option>9</option><option>10</option><option>11</option><option>12</option><option>13</option><option>14</option><option>15</option><option>16</option><option>17</option><option>18</option><option>19</option><option>20</option><option>21</option><option>22</option><option>23</option><option>24</option><option>25</option><option>26</option><option>27</option><option>28</option><option>29</option><option>30</option><option>31</option><option>32</option><option>33</option><option>34</option><option>35</option><option>36</option><option>37</option><option>38</option><option>39</option><option>40</option><option>41</option><option>42</option><option>43</option><option>44</option><option>45</option><option>46</option><option>47</option><option>48</option><option>49</option><option>50</option></select></form><span class="badge Bin summary-control summary-status-control disareferenceed-summary-control">Bin</span></span></span><span class="task-context"><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-section-6" aria-expanded="false">Community</button><span class="scope-popover" id="scope-menu-item-section-6" hidden><a href="?section_filter=community&amp;project_filter=&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="6"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="6"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="6"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="6"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="6"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="6"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="6"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><a href="?view=add_section&amp;section_filter=community&amp;project_filter=events&amp;task_status=Open%2CDo%2CReady%2CDone%2CBlocked%2CBin">New...</a></span></span><span class="scope-separator"> · </span><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-project-6" aria-expanded="false">Events</button><span class="scope-popover" id="scope-menu-item-project-6" hidden><a href="?section_filter=community&amp;project_filter=events&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="6"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="6"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Events</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="6"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="planning"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Planning</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="6"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="reference"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Reference</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="6"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="review"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Review</button></form><a href="?view=add_project&amp;section_filter=community">New...</a></span></span></span></summary>
        <form method="post" class="task-edit dirty-watch">
          <input type="hidden" name="action" value="update_item">
          <input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order">          <input type="hidden" name="id" value="6">
          <input type="hidden" name="section" value="community">
          <input type="hidden" name="project" value="events">
          <input type="hidden" name="priority" value="Should">
          <input type="hidden" name="order" value="6">
          <input type="hidden" name="status" value="Ready">
          <div><label>Title</label><input name="title" value="Check catering numbers"></div>
          <div class="notes-field"><label>Notes</label><textarea name="notes" data-formatted-notes-storage hidden>User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</textarea><div class="formatted-notes-editor" contenteditareferencee="true" role="textbox" aria-multiline="true" data-formatted-notes-editor data-note-stamp="1" data-note-stamp-user="User">User yyyy-mm-dd hh:mm<br>Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</div></div>
          <div class="task-actions center-actions"><button class="small" form="restore-task-6">Undo</button><button class="small danger" form="hard-delete-task-6">Hard delete</button></div>
        </form>
        <form id="delete-task-6" method="post" onsubmit="return confirm('Move this item to Bin?')"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="6"></form>
        <form id="improve-transcript-task-6" method="post"><input type="hidden" name="action" value="improve_item_transcript"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="6"></form>
        <form id="delete-audio-task-6" method="post" data-delete-audio-form="1"><input type="hidden" name="action" value="delete_item_audio"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="6"></form>
        <form id="restore-task-6" method="post"><input type="hidden" name="action" value="restore_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="6"></form>
        <form id="hard-delete-task-6" method="post" onsubmit="return confirm('Hard delete this item now?')"><input type="hidden" name="action" value="hard_delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="6"></form>
      </details>
                      <details class="task-card task-row" data-priority="could" data-status="done" data-demo-status="done" data-binned="0" data-task-id="7"><summary><span class="task-title">Collect feedback cards</span><span class="doing-led" aria-label="Joel is not working on item #7" title="Joel is not working on this item">Agent active</span><span class="task-summary-meta"><span class="task-number">#7</span><span class="task-badges task-summary-controls"><form method="post" class="summary-control-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="7"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Could"><input type="hidden" name="order" value="7"><input type="hidden" name="status" value="Done"><input type="hidden" name="title" value="Collect feedback cards"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="priority" class="badge priority-badge summary-control summary-priority-control priority-could" title="Change priority" aria-label="Change priority from Could" onchange="boardSummaryMetaChanged(this)"><option value="" class="priority-option priority-referenceank"></option><option class="priority-option priority-must">Must</option><option class="priority-option priority-should">Should</option><option class="priority-option priority-could" selected>Could</option><option class="priority-option priority-wont">Won&#039;t</option></select></form><form method="post" class="summary-control-form summary-order-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="7"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Could"><input type="hidden" name="order" value="7"><input type="hidden" name="status" value="Done"><input type="hidden" name="title" value="Collect feedback cards"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="order" class="summary-order-control" title="Change order" aria-label="Change order" onchange="this.form.submit()"><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option><option>6</option><option selected>7</option><option>8</option><option>9</option><option>10</option><option>11</option><option>12</option><option>13</option><option>14</option><option>15</option><option>16</option><option>17</option><option>18</option><option>19</option><option>20</option><option>21</option><option>22</option><option>23</option><option>24</option><option>25</option><option>26</option><option>27</option><option>28</option><option>29</option><option>30</option><option>31</option><option>32</option><option>33</option><option>34</option><option>35</option><option>36</option><option>37</option><option>38</option><option>39</option><option>40</option><option>41</option><option>42</option><option>43</option><option>44</option><option>45</option><option>46</option><option>47</option><option>48</option><option>49</option><option>50</option></select></form><form method="post" class="summary-control-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="7"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Could"><input type="hidden" name="order" value="7"><input type="hidden" name="status" value="Done"><input type="hidden" name="title" value="Collect feedback cards"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="status" class="badge Done summary-control summary-status-control status-done" title="Change status" aria-label="Change status from Done" onchange="boardSummaryMetaChanged(this)"><option class="status-option status-open">Open</option><option class="status-option status-do">Do</option><option class="status-option status-ready">Ready</option><option class="status-option status-done" selected>Done</option><option class="status-option status-referenceocked">Blocked</option></select></form></span></span><span class="task-context"><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-section-7" aria-expanded="false">Community</button><span class="scope-popover" id="scope-menu-item-section-7" hidden><a href="?section_filter=community&amp;project_filter=&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="7"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="7"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="7"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="7"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="7"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="7"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="7"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><a href="?view=add_section&amp;section_filter=community&amp;project_filter=events&amp;task_status=Open%2CDo%2CReady%2CDone%2CBlocked%2CBin">New...</a></span></span><span class="scope-separator"> · </span><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-project-7" aria-expanded="false">Events</button><span class="scope-popover" id="scope-menu-item-project-7" hidden><a href="?section_filter=community&amp;project_filter=events&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="7"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="7"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Events</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="7"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="planning"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Planning</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="7"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="reference"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Reference</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="7"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="review"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Review</button></form><a href="?view=add_project&amp;section_filter=community">New...</a></span></span></span></summary>
        <form method="post" class="task-edit dirty-watch">
          <input type="hidden" name="action" value="update_item">
          <input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order">          <input type="hidden" name="id" value="7">
          <input type="hidden" name="section" value="community">
          <input type="hidden" name="project" value="events">
          <input type="hidden" name="priority" value="Could">
          <input type="hidden" name="order" value="7">
          <input type="hidden" name="status" value="Done">
          <div><label>Title</label><input name="title" value="Collect feedback cards"></div>
          <div class="notes-field"><label>Notes</label><textarea name="notes" data-formatted-notes-storage hidden>User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</textarea><div class="formatted-notes-editor" contenteditareferencee="true" role="textbox" aria-multiline="true" data-formatted-notes-editor data-note-stamp="1" data-note-stamp-user="User">User yyyy-mm-dd hh:mm<br>Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</div></div>
          <div class="task-actions center-actions"><button class="small">Save</button><button class="small danger" form="delete-task-7">Bin</button></div>
        </form>
        <form id="delete-task-7" method="post" onsubmit="return confirm('Move this item to Bin?')"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="7"></form>
        <form id="improve-transcript-task-7" method="post"><input type="hidden" name="action" value="improve_item_transcript"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="7"></form>
        <form id="delete-audio-task-7" method="post" data-delete-audio-form="1"><input type="hidden" name="action" value="delete_item_audio"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="7"></form>
        <form id="restore-task-7" method="post"><input type="hidden" name="action" value="restore_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="7"></form>
        <form id="hard-delete-task-7" method="post" onsubmit="return confirm('Hard delete this item now?')"><input type="hidden" name="action" value="hard_delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="7"></form>
      </details>
                      <details class="task-card task-row" data-priority="wont" data-status="bin" data-recent-rank="3" data-changed="1" data-demo-status="done" data-binned="1" data-task-id="8"><summary><span class="task-title">Retire old poster idea</span><span class="change-dot-form" title="Recent item #3"><span class="change-dot" aria-label="Recent item #3"></span></span><span class="doing-led" aria-label="Joel is not working on item #8" title="Joel is not working on this item">Agent active</span><span class="task-summary-meta"><span class="task-number">#8</span><span class="task-badges task-summary-controls"><form method="post" class="summary-control-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="8"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Won&#039;t"><input type="hidden" name="order" value="8"><input type="hidden" name="status" value="Done"><input type="hidden" name="title" value="Retire old poster idea"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="priority" class="badge priority-badge summary-control summary-priority-control priority-wont" title="Change priority" aria-label="Change priority from Won&#039;t" onchange="boardSummaryMetaChanged(this)"><option value="" class="priority-option priority-referenceank"></option><option class="priority-option priority-must">Must</option><option class="priority-option priority-should">Should</option><option class="priority-option priority-could">Could</option><option class="priority-option priority-wont" selected>Won&#039;t</option></select></form><form method="post" class="summary-control-form summary-order-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="8"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Won&#039;t"><input type="hidden" name="order" value="8"><input type="hidden" name="status" value="Done"><input type="hidden" name="title" value="Retire old poster idea"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="order" class="summary-order-control" title="Change order" aria-label="Change order" onchange="this.form.submit()"><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option><option>6</option><option>7</option><option selected>8</option><option>9</option><option>10</option><option>11</option><option>12</option><option>13</option><option>14</option><option>15</option><option>16</option><option>17</option><option>18</option><option>19</option><option>20</option><option>21</option><option>22</option><option>23</option><option>24</option><option>25</option><option>26</option><option>27</option><option>28</option><option>29</option><option>30</option><option>31</option><option>32</option><option>33</option><option>34</option><option>35</option><option>36</option><option>37</option><option>38</option><option>39</option><option>40</option><option>41</option><option>42</option><option>43</option><option>44</option><option>45</option><option>46</option><option>47</option><option>48</option><option>49</option><option>50</option></select></form><span class="badge Bin summary-control summary-status-control disareferenceed-summary-control">Bin</span></span></span><span class="task-context"><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-section-8" aria-expanded="false">Community</button><span class="scope-popover" id="scope-menu-item-section-8" hidden><a href="?section_filter=community&amp;project_filter=&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="8"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="8"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="8"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="8"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="8"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="8"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="8"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><a href="?view=add_section&amp;section_filter=community&amp;project_filter=events&amp;task_status=Open%2CDo%2CReady%2CDone%2CBlocked%2CBin">New...</a></span></span><span class="scope-separator"> · </span><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-project-8" aria-expanded="false">Events</button><span class="scope-popover" id="scope-menu-item-project-8" hidden><a href="?section_filter=community&amp;project_filter=events&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="8"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="8"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Events</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="8"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="planning"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Planning</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="8"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="reference"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Reference</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="8"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="review"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Review</button></form><a href="?view=add_project&amp;section_filter=community">New...</a></span></span></span></summary>
        <form method="post" class="task-edit dirty-watch">
          <input type="hidden" name="action" value="update_item">
          <input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order">          <input type="hidden" name="id" value="8">
          <input type="hidden" name="section" value="community">
          <input type="hidden" name="project" value="events">
          <input type="hidden" name="priority" value="Won&#039;t">
          <input type="hidden" name="order" value="8">
          <input type="hidden" name="status" value="Done">
          <div><label>Title</label><input name="title" value="Retire old poster idea"></div>
          <div class="notes-field"><label>Notes</label><textarea name="notes" data-formatted-notes-storage hidden>User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</textarea><div class="formatted-notes-editor" contenteditareferencee="true" role="textbox" aria-multiline="true" data-formatted-notes-editor data-note-stamp="1" data-note-stamp-user="User">User yyyy-mm-dd hh:mm<br>Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</div></div>
          <div class="task-actions center-actions"><button class="small" form="restore-task-8">Undo</button><button class="small danger" form="hard-delete-task-8">Hard delete</button></div>
        </form>
        <form id="delete-task-8" method="post" onsubmit="return confirm('Move this item to Bin?')"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="8"></form>
        <form id="improve-transcript-task-8" method="post"><input type="hidden" name="action" value="improve_item_transcript"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="8"></form>
        <form id="delete-audio-task-8" method="post" data-delete-audio-form="1"><input type="hidden" name="action" value="delete_item_audio"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="8"></form>
        <form id="restore-task-8" method="post"><input type="hidden" name="action" value="restore_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="8"></form>
        <form id="hard-delete-task-8" method="post" onsubmit="return confirm('Hard delete this item now?')"><input type="hidden" name="action" value="hard_delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="8"></form>
      </details>
                      <details class="task-card task-row" data-priority="must" data-status="referenceocked" data-recent-rank="2" data-changed="1" data-demo-status="blocked" data-binned="0" data-task-id="9"><summary><span class="task-title">Resolve room booking</span><span class="change-dot-form" title="Recent item #2"><span class="change-dot" aria-label="Recent item #2"></span></span><span class="doing-led" aria-label="Joel is not working on item #9" title="Joel is not working on this item">Agent active</span><span class="task-summary-meta"><span class="task-number">#9</span><span class="task-badges task-summary-controls"><form method="post" class="summary-control-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="9"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Must"><input type="hidden" name="order" value="9"><input type="hidden" name="status" value="Blocked"><input type="hidden" name="title" value="Resolve room booking"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="priority" class="badge priority-badge summary-control summary-priority-control priority-must" title="Change priority" aria-label="Change priority from Must" onchange="boardSummaryMetaChanged(this)"><option value="" class="priority-option priority-referenceank"></option><option class="priority-option priority-must" selected>Must</option><option class="priority-option priority-should">Should</option><option class="priority-option priority-could">Could</option><option class="priority-option priority-wont">Won&#039;t</option></select></form><form method="post" class="summary-control-form summary-order-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="9"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Must"><input type="hidden" name="order" value="9"><input type="hidden" name="status" value="Blocked"><input type="hidden" name="title" value="Resolve room booking"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="order" class="summary-order-control" title="Change order" aria-label="Change order" onchange="this.form.submit()"><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option><option>6</option><option>7</option><option>8</option><option selected>9</option><option>10</option><option>11</option><option>12</option><option>13</option><option>14</option><option>15</option><option>16</option><option>17</option><option>18</option><option>19</option><option>20</option><option>21</option><option>22</option><option>23</option><option>24</option><option>25</option><option>26</option><option>27</option><option>28</option><option>29</option><option>30</option><option>31</option><option>32</option><option>33</option><option>34</option><option>35</option><option>36</option><option>37</option><option>38</option><option>39</option><option>40</option><option>41</option><option>42</option><option>43</option><option>44</option><option>45</option><option>46</option><option>47</option><option>48</option><option>49</option><option>50</option></select></form><form method="post" class="summary-control-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="9"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Must"><input type="hidden" name="order" value="9"><input type="hidden" name="status" value="Blocked"><input type="hidden" name="title" value="Resolve room booking"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="status" class="badge Blocked summary-control summary-status-control status-referenceocked" title="Change status" aria-label="Change status from Blocked" onchange="boardSummaryMetaChanged(this)"><option class="status-option status-open">Open</option><option class="status-option status-do">Do</option><option class="status-option status-ready">Ready</option><option class="status-option status-done">Done</option><option class="status-option status-referenceocked" selected>Blocked</option></select></form></span></span><span class="task-context"><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-section-9" aria-expanded="false">Community</button><span class="scope-popover" id="scope-menu-item-section-9" hidden><a href="?section_filter=community&amp;project_filter=&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="9"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="9"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="9"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="9"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="9"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="9"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="9"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><a href="?view=add_section&amp;section_filter=community&amp;project_filter=events&amp;task_status=Open%2CDo%2CReady%2CDone%2CBlocked%2CBin">New...</a></span></span><span class="scope-separator"> · </span><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-project-9" aria-expanded="false">Events</button><span class="scope-popover" id="scope-menu-item-project-9" hidden><a href="?section_filter=community&amp;project_filter=events&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="9"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="9"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Events</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="9"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="planning"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Planning</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="9"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="reference"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Reference</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="9"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="review"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Review</button></form><a href="?view=add_project&amp;section_filter=community">New...</a></span></span></span></summary>
        <form method="post" class="task-edit dirty-watch">
          <input type="hidden" name="action" value="update_item">
          <input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order">          <input type="hidden" name="id" value="9">
          <input type="hidden" name="section" value="community">
          <input type="hidden" name="project" value="events">
          <input type="hidden" name="priority" value="Must">
          <input type="hidden" name="order" value="9">
          <input type="hidden" name="status" value="Blocked">
          <div><label>Title</label><input name="title" value="Resolve room booking"></div>
          <div class="notes-field"><label>Notes</label><textarea name="notes" data-formatted-notes-storage hidden>User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</textarea><div class="formatted-notes-editor" contenteditareferencee="true" role="textbox" aria-multiline="true" data-formatted-notes-editor data-note-stamp="1" data-note-stamp-user="User">User yyyy-mm-dd hh:mm<br>Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</div></div>
          <div class="task-actions center-actions"><button class="small">Save</button><button class="small danger" form="delete-task-9">Bin</button></div>
        </form>
        <form id="delete-task-9" method="post" onsubmit="return confirm('Move this item to Bin?')"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="9"></form>
        <form id="improve-transcript-task-9" method="post"><input type="hidden" name="action" value="improve_item_transcript"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="9"></form>
        <form id="delete-audio-task-9" method="post" data-delete-audio-form="1"><input type="hidden" name="action" value="delete_item_audio"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="9"></form>
        <form id="restore-task-9" method="post"><input type="hidden" name="action" value="restore_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="9"></form>
        <form id="hard-delete-task-9" method="post" onsubmit="return confirm('Hard delete this item now?')"><input type="hidden" name="action" value="hard_delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="9"></form>
      </details>
                      <details class="task-card task-row" data-priority="should" data-status="bin" data-recent-rank="1" data-changed="1" data-demo-status="blocked" data-binned="1" data-task-id="10"><summary><span class="task-title">Wait for supplier reply</span><span class="change-dot-form" title="Recent item #1"><span class="change-dot" aria-label="Recent item #1"></span></span><span class="doing-led" aria-label="Joel is not working on item #10" title="Joel is not working on this item">Agent active</span><span class="task-summary-meta"><span class="task-number">#10</span><span class="task-badges task-summary-controls"><form method="post" class="summary-control-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="10"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Should"><input type="hidden" name="order" value="10"><input type="hidden" name="status" value="Blocked"><input type="hidden" name="title" value="Wait for supplier reply"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="priority" class="badge priority-badge summary-control summary-priority-control priority-should" title="Change priority" aria-label="Change priority from Should" onchange="boardSummaryMetaChanged(this)"><option value="" class="priority-option priority-referenceank"></option><option class="priority-option priority-must">Must</option><option class="priority-option priority-should" selected>Should</option><option class="priority-option priority-could">Could</option><option class="priority-option priority-wont">Won&#039;t</option></select></form><form method="post" class="summary-control-form summary-order-form"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="10"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="priority" value="Should"><input type="hidden" name="order" value="10"><input type="hidden" name="status" value="Blocked"><input type="hidden" name="title" value="Wait for supplier reply"><input type="hidden" name="notes" value="User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser."><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><select name="order" class="summary-order-control" title="Change order" aria-label="Change order" onchange="this.form.submit()"><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option><option>6</option><option>7</option><option>8</option><option>9</option><option selected>10</option><option>11</option><option>12</option><option>13</option><option>14</option><option>15</option><option>16</option><option>17</option><option>18</option><option>19</option><option>20</option><option>21</option><option>22</option><option>23</option><option>24</option><option>25</option><option>26</option><option>27</option><option>28</option><option>29</option><option>30</option><option>31</option><option>32</option><option>33</option><option>34</option><option>35</option><option>36</option><option>37</option><option>38</option><option>39</option><option>40</option><option>41</option><option>42</option><option>43</option><option>44</option><option>45</option><option>46</option><option>47</option><option>48</option><option>49</option><option>50</option></select></form><span class="badge Bin summary-control summary-status-control disareferenceed-summary-control">Bin</span></span></span><span class="task-context"><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-section-10" aria-expanded="false">Community</button><span class="scope-popover" id="scope-menu-item-section-10" hidden><a href="?section_filter=community&amp;project_filter=&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="10"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="10"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="10"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="10"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="10"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Community</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="10"><input type="hidden" name="section" value="household"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Household</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="10"><input type="hidden" name="section" value="workshop"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Workshop</button></form><a href="?view=add_section&amp;section_filter=community&amp;project_filter=events&amp;task_status=Open%2CDo%2CReady%2CDone%2CBlocked%2CBin">New...</a></span></span><span class="scope-separator"> · </span><span class="item-scope-menu"><button type="button" class="scope-label" data-scope-menu="item-project-10" aria-expanded="false">Events</button><span class="scope-popover" id="scope-menu-item-project-10" hidden><a href="?section_filter=community&amp;project_filter=events&amp;task_status=">Show</a><strong>Change</strong><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="10"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value=""><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">No focus</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="10"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="events"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Events</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="10"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="planning"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Planning</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="10"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="reference"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Reference</button></form><form method="post" class="scope-choice-form"><input type="hidden" name="action" value="move_item_scope"><input type="hidden" name="id" value="10"><input type="hidden" name="section" value="community"><input type="hidden" name="project" value="review"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><button type="submit" class="scope-choice">Review</button></form><a href="?view=add_project&amp;section_filter=community">New...</a></span></span></span></summary>
        <form method="post" class="task-edit dirty-watch">
          <input type="hidden" name="action" value="update_item">
          <input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order">          <input type="hidden" name="id" value="10">
          <input type="hidden" name="section" value="community">
          <input type="hidden" name="project" value="events">
          <input type="hidden" name="priority" value="Should">
          <input type="hidden" name="order" value="10">
          <input type="hidden" name="status" value="Blocked">
          <div><label>Title</label><input name="title" value="Wait for supplier reply"></div>
          <div class="notes-field"><label>Notes</label><textarea name="notes" data-formatted-notes-storage hidden>User yyyy-mm-dd hh:mm
Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</textarea><div class="formatted-notes-editor" contenteditareferencee="true" role="textbox" aria-multiline="true" data-formatted-notes-editor data-note-stamp="1" data-note-stamp-user="User">User yyyy-mm-dd hh:mm<br>Try changing the title, priority, status, notes, or Bin state. Changes stay in this browser.</div></div>
          <div class="task-actions center-actions"><button class="small" form="restore-task-10">Undo</button><button class="small danger" form="hard-delete-task-10">Hard delete</button></div>
        </form>
        <form id="delete-task-10" method="post" onsubmit="return confirm('Move this item to Bin?')"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="10"></form>
        <form id="improve-transcript-task-10" method="post"><input type="hidden" name="action" value="improve_item_transcript"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="10"></form>
        <form id="delete-audio-task-10" method="post" data-delete-audio-form="1"><input type="hidden" name="action" value="delete_item_audio"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="10"></form>
        <form id="restore-task-10" method="post"><input type="hidden" name="action" value="restore_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="10"></form>
        <form id="hard-delete-task-10" method="post" onsubmit="return confirm('Hard delete this item now?')"><input type="hidden" name="action" value="hard_delete_item"><input type="hidden" name="return_section" value="community"><input type="hidden" name="return_project" value="events"><input type="hidden" name="task_q" value=""><input type="hidden" name="task_priority" value=""><input type="hidden" name="task_status" value="Open,Do,Ready,Done,Blocked,Bin"><input type="hidden" name="task_sort" value="order"><input type="hidden" name="id" value="10"></form>
      </details>
      </div>
</section>
<div class="item-focus-backdrop modal-backdrop" hidden data-item-focus-modal>
  <div class="modal item-focus-dialog" role="dialog" aria-modal="true" aria-labelledby="item-focus-title">
    <div class="item-focus-header"><h2 id="item-focus-title">Item focus</h2><button type="button" class="small" data-close-item-focus-modal>Close</button></div>
    <div class="item-focus-body" data-item-focus-body></div>
  </div>
</div>
</main>
<style>
.demo-wordmark { font-size: clamp(1rem, 2vw, 1.35rem); font-weight: 700; letter-spacing: .08em; margin-left: .35rem; text-transform: uppercase; vertical-align: middle; }
</style>
<script>
(function() {
  let nextDemoId = 1000;
  let pendingAgentCard = null;
  const priorityLabels = {must: 'Must', should: 'Should', could: 'Could', wont: "Won't", blank: ''};
  const statusLabels = {open: 'Open', do: 'Do', ready: 'Ready', done: 'Done', blocked: 'Blocked', bin: 'Bin'};
  function two(value) { return String(value).padStart(2, '0'); }
  function demoTimestamp(label) {
    const d = new Date();
    return label + ' ' + d.getFullYear() + '-' + two(d.getMonth() + 1) + '-' + two(d.getDate()) + ' ' + two(d.getHours()) + ':' + two(d.getMinutes());
  }
  function safeText(value) {
    return String(value || '').replace(/[<>&"]/g, function(ch) { return {'<':'&lt;', '>':'&gt;', '&':'&amp;', '"':'&quot;'}[ch]; });
  }
  function themeKey(value) {
    const text = String(value || '').toLowerCase().replace(/won't/g, 'wont').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    return text || 'blank';
  }
  function formValue(data, name, fallback) {
    const values = data.getAll(name).map(function(value) { return String(value || ''); }).filter(function(value) { return value !== ''; });
    return values.length ? values[values.length - 1] : fallback;
  }
  function siteLight() { return document.querySelector('.site-agent-active-light .doing-led'); }
  function setSiteLight(active, itemId) {
    const light = siteLight();
    if (!light) return;
    light.classList.toggle('active', !!active);
    const text = active ? 'Joel is working on item #' + itemId : 'Joel is not working on any LUKE item';
    light.setAttribute('aria-label', text + '; show current context / Agent active');
    light.setAttribute('title', text + '; show current context / Agent active');
  }
  function updatePill(card, kind, value) {
    const key = themeKey(value);
    const label = kind === 'priority' ? (priorityLabels[key] ?? value) : (statusLabels[key] ?? value);
    card.dataset[kind] = key;
    if (kind === 'status') card.dataset.demoStatus = key;
    card.querySelectorAll('.' + kind + '-badge, .summary-select.' + kind).forEach(function(pill) {
      pill.className = pill.className.replace(new RegExp('\\b' + kind + '-[^ ]+', 'g'), '').replace(/\s+/g, ' ').trim();
      pill.classList.add(kind + '-' + key);
      if (!pill.matches('select')) pill.textContent = label;
    });
    card.querySelectorAll('select[name="' + kind + '"]').forEach(function(select) { select.value = label; });
    card.querySelectorAll('input[type="hidden"][name="' + kind + '"]').forEach(function(input) { input.value = label; });
  }
  function updateTitle(card, title) {
    const finalTitle = title || 'Untitled item';
    const heading = card.querySelector('.task-title');
    if (heading) heading.textContent = finalTitle;
    card.querySelectorAll('input[name="title"]').forEach(function(input) { input.value = finalTitle; });
  }
  function syncNotes(card, notes) {
    card.querySelectorAll('textarea[name="notes"]').forEach(function(textarea) { textarea.value = notes; });
    card.querySelectorAll('[data-formatted-notes-editor]').forEach(function(editor) { editor.innerHTML = safeText(notes).replace(/\n/g, '<br>'); });
  }
  function markSaved(card) {
    let status = card.querySelector('.demo-local-status');
    if (!status) {
      status = document.createElement('p');
      status.className = 'muted demo-local-status';
      status.setAttribute('aria-live', 'polite');
      const actions = card.querySelector('.task-actions') || card.querySelector('form');
      if (actions) actions.after(status);
    }
    status.textContent = 'Saved locally in this browser.';
  }
  function cardHtml(id, title, priority, status, notes) {
    const priorityKey = themeKey(priority);
    const statusKey = themeKey(status);
    const prettyPriority = priorityLabels[priorityKey] ?? priority;
    const prettyStatus = statusLabels[statusKey] ?? status;
    const priorityOptions = ['Must', 'Should', 'Could', "Won't"].map(function(option) {
      return '<option' + (prettyPriority === option ? ' selected' : '') + '>' + safeText(option) + '</option>';
    }).join('');
    const statusOptions = ['Open', 'Do', 'Ready', 'Done', 'Blocked'].map(function(option) {
      return '<option' + (prettyStatus === option ? ' selected' : '') + '>' + safeText(option) + '</option>';
    }).join('');
    return '<details class="task-card task-row" data-priority="' + priorityKey + '" data-status="' + statusKey + '" data-demo-status="' + statusKey + '" data-binned="0" data-task-id="' + id + '" open>' +
      '<summary><span class="task-title">' + safeText(title) + '</span><span class="doing-led" aria-label="Joel is not working on item #' + id + '" title="Joel is not working on this item">Agent active</span><span class="task-summary-meta"><span class="task-number">#' + id + '</span><span class="summary-select priority priority-' + priorityKey + '">' + safeText(prettyPriority) + '</span><span class="summary-select status status-' + statusKey + '">' + safeText(prettyStatus) + '</span></span></summary>' +
      '<form method="post" class="task-edit dirty-watch"><input type="hidden" name="action" value="update_item"><input type="hidden" name="id" value="' + id + '"><div><label>Title</label><input name="title" value="' + safeText(title) + '"></div><div class="task-meta"><div><label>Priority</label><select name="priority">' + priorityOptions + '</select></div><div><label>Status</label><select name="status">' + statusOptions + '</select></div></div><div class="notes-field"><textarea name="notes">' + safeText(notes) + '</textarea></div><div class="task-actions center-actions"><button class="small">Save</button><button class="small danger" name="action" value="delete_item">Bin</button></div></form>' +
      '</details>';
  }
  function demoStartFakeAgentActivity() {
    if (!pendingAgentCard) return;
    const id = pendingAgentCard.dataset.taskId || '';
    pendingAgentCard.dataset.joelActive = '1';
    const led = pendingAgentCard.querySelector('.doing-led');
    if (led) {
      led.classList.add('active');
      led.setAttribute('aria-label', 'Joel is working on item #' + id);
      led.setAttribute('title', 'Joel is working on this item');
    }
    setSiteLight(true, id);
    setTimeout(demoAddFakeAgentReport, 7000);
  }
  function demoAddFakeAgentReport() {
    const card = pendingAgentCard;
    if (!card) return;
    const notes = card.querySelector('textarea[name="notes"]');
    if (notes) syncNotes(card, notes.value.replace(/\s*$/, '') + '\n\n' + demoTimestamp('Agent') + "\nThis is where the Agent would report back with what they've done.");
    const led = card.querySelector('.doing-led');
    if (led) {
      led.classList.remove('active');
      led.setAttribute('aria-label', 'Joel is not working on item #' + (card.dataset.taskId || ''));
      led.setAttribute('title', 'Joel is not working on this item');
    }
    delete card.dataset.joelActive;
    pendingAgentCard = null;
    setSiteLight(false, '');
  }
  function maybeStartAgentFor(card, status) {
    if (status === 'Open' || status === 'Do') {
      pendingAgentCard = card;
      setTimeout(demoStartFakeAgentActivity, 3000);
    }
  }
  function addDemoItem(form) {
    const data = new FormData(form);
    const id = nextDemoId++;
    const title = formValue(data, 'title', 'Untitled item');
    const priority = formValue(data, 'priority', 'Should');
    const status = formValue(data, 'status', 'Open');
    const noteText = formValue(data, 'notes', '') || formValue(data, 'formatted_notes', '') || 'Posted in the safe sandbox.';
    const notes = demoTimestamp('User') + '\n' + noteText;
    const list = document.querySelector('.task-list');
    if (!list) return;
    list.insertAdjacentHTML('afterbegin', cardHtml(id, title, priority, status, notes));
    const card = list.querySelector('[data-task-id="' + id + '"]');
    markSaved(card);
    maybeStartAgentFor(card, status);
  }
  function demoApplySummaryControl(form) {
    const data = new FormData(form);
    const card = form.closest('.task-card');
    if (!card) return;
    updatePill(card, 'priority', formValue(data, 'priority', priorityLabels[card.dataset.priority] || ''));
    updatePill(card, 'status', formValue(data, 'status', statusLabels[card.dataset.status] || 'Open'));
    markSaved(card);
    maybeStartAgentFor(card, statusLabels[card.dataset.status] || 'Open');
  }
  function demoSaveItem(form) {
    const data = new FormData(form);
    const card = form.closest('.task-card');
    if (!card) return;
    const status = formValue(data, 'status', statusLabels[card.dataset.status] || 'Open');
    updateTitle(card, formValue(data, 'title', 'Untitled item'));
    updatePill(card, 'priority', formValue(data, 'priority', priorityLabels[card.dataset.priority] || ''));
    updatePill(card, 'status', status);
    syncNotes(card, formValue(data, 'notes', ''));
    markSaved(card);
    maybeStartAgentFor(card, status);
  }
  function demoBinItem(form) {
    const data = new FormData(form);
    const id = formValue(data, 'id', '');
    const card = form.closest('.task-card') || document.querySelector('.task-card[data-task-id="' + id + '"]');
    if (!card) return;
    card.dataset.binned = '1';
    updatePill(card, 'status', 'Bin');
    markSaved(card);
  }
  document.addEventListener('submit', function(event) {
    const form = event.target.closest && event.target.closest('form');
    if (!form) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const data = new FormData(form);
    const action = formValue(data, 'action', '').toString();
    if (action === 'add_item' || action === 'add_todo') addDemoItem(form);
    else if (action === 'delete_item') demoBinItem(form);
    else if (form.classList.contains('summary-control-form')) demoApplySummaryControl(form);
    else if (action === 'update_item') demoSaveItem(form);
    if (form.closest('[data-add-item-modal]')) form.closest('[data-add-item-modal]').hidden = true;
    return false;
  }, true);
  document.addEventListener('click', function(event) {
    const button = event.target.closest && event.target.closest('button[name="action"][value="delete_item"]');
    if (button) {
      event.preventDefault();
      event.stopImmediatePropagation();
      demoBinItem(button.form);
      return false;
    }
    const link = event.target.closest && event.target.closest('a');
    if (!link) return;
    const href = link.getAttribute('href') || '';
    if (href.startsWith('?') || href === './') event.preventDefault();
  }, true);
  window.demoStartFakeAgentActivity = demoStartFakeAgentActivity;
  window.demoAddFakeAgentReport = demoAddFakeAgentReport;
  window.demoSaveItem = demoSaveItem;
  window.demoBinItem = demoBinItem;
  window.demoApplySummaryControl = demoApplySummaryControl;
})();
</script></body>
</html>
