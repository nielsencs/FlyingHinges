<?php
$priorities = ['Must', 'Should', 'Could', "Won't"];
$statuses = ['Open', 'Do', 'Ready', 'Done', 'Blocked'];
function demo_h(string $value): string { return htmlspecialchars($value, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8'); }
function demo_priority_class(string $priority): string { return strtolower(str_replace(["'", ' '], ['', '-'], $priority)); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LUKE playground</title>
<style>
:root { color-scheme: dark; --bg:#0f1724; --panel:#182235; --ink:#f4f7fb; --muted:#aeb9c9; --line:#2c3a52; --accent:#7dd3fc; --active:#5dff9a; --warn:#ffd166; --bad:#ff8a8a; }
* { box-sizing: border-box; }
body { margin:0; font:16px/1.45 system-ui, -apple-system, Segoe UI, sans-serif; background:linear-gradient(145deg,#0b1020,#162238); color:var(--ink); }
main { max-width:1180px; margin:0 auto; padding:24px; }
header { display:flex; gap:16px; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; margin-bottom:18px; }
h1 { margin:0 0 6px; font-size:clamp(2rem,5vw,4rem); letter-spacing:.02em; }
.lede { color:var(--muted); margin:.2rem 0; max-width:74ch; }
.notice { border:1px solid #4b617f; background:#111b2b; border-radius:14px; padding:12px 14px; color:#dbeafe; }
.agent-panel { min-width:260px; border:1px solid var(--line); background:var(--panel); border-radius:16px; padding:14px; box-shadow:0 18px 50px #0005; }
.agent-line { display:flex; align-items:center; gap:10px; font-weight:700; }
.agent-led { width:16px; height:16px; border-radius:99px; background:#405066; box-shadow:inset 0 0 0 2px #ffffff22; }
.agent-line[data-active="true"] .agent-led { background:var(--active); box-shadow:0 0 18px var(--active), inset 0 0 0 2px #ffffff66; }
.agent-status { color:var(--muted); font-size:.92rem; margin:.5rem 0 0; }
.demo-form, .matrix { border:1px solid var(--line); background:#111a2a; border-radius:18px; padding:16px; margin:16px 0; }
.demo-form h2, .matrix h2 { margin-top:0; }
form { display:grid; grid-template-columns:1.2fr repeat(2, minmax(130px,.35fr)) auto; gap:10px; align-items:end; }
label { display:grid; gap:5px; color:var(--muted); font-size:.92rem; }
input, select, button { border-radius:10px; border:1px solid #40506a; background:#0d1523; color:var(--ink); padding:10px 12px; font:inherit; }
button { background:#1d4ed8; border-color:#60a5fa; cursor:pointer; font-weight:700; }
button:hover { filter:brightness(1.12); }
.grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:12px; }
.task-card { position:relative; border:1px solid var(--line); border-left-width:7px; background:var(--panel); border-radius:14px; padding:12px; min-height:150px; }
.task-card[data-priority="Must"] { border-left-color:#ff7373; }
.task-card[data-priority="Should"] { border-left-color:#ffd166; }
.task-card[data-priority="Could"] { border-left-color:#7dd3fc; }
.task-card[data-priority="Won't"] { border-left-color:#94a3b8; opacity:.72; }
.task-card[data-binned="true"] { background:#241b25; filter:saturate(.75); }
.task-card[data-agent-active="true"] { outline:2px solid var(--active); box-shadow:0 0 22px #5dff9a45; }
.card-top { display:flex; justify-content:space-between; gap:8px; align-items:flex-start; }
.card-title { font-weight:800; margin:0; }
.badges { display:flex; gap:6px; flex-wrap:wrap; margin:.65rem 0; }
.badge { border:1px solid #4d5f7a; color:#dbeafe; border-radius:999px; padding:2px 8px; font-size:.78rem; }
.badge.status-Do { background:#4338ca; }
.badge.status-Ready { background:#166534; }
.badge.status-Done { background:#334155; }
.badge.status-Blocked { background:#7f1d1d; }
.badge.bin { background:#4a2436; }
.notes { color:#d5dfed; font-size:.9rem; white-space:pre-wrap; }
.notes p { margin:.45rem 0; }
.timestamp-example { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; color:var(--warn); }
@media (max-width:760px) { form { grid-template-columns:1fr; } main { padding:14px; } }
</style>
</head>
<body>
<main>
<header>
  <div>
    <h1>LUKE playground</h1>
    <p class="lede">A safe public demo of LUKE's Priority, Status, Bin state, and the Agent active light.</p>
    <p class="notice"><strong>Demo only: nothing here writes to the real LUKE board.</strong> It uses only this page in your browser. It does not contact an agent, save server state, send messages, deploy, or update any live queue.</p>
  </div>
  <aside class="agent-panel" aria-live="polite">
    <div class="agent-line" id="agentLine" data-active="false"><span class="agent-led" aria-hidden="true"></span><span>Agent active</span></div>
    <p class="agent-status" id="agentStatus">Idle. Add an Open or Do item to fake activity.</p>
  </aside>
</header>

<section class="demo-form">
  <h2>Try a fake item</h2>
  <p class="lede">For public demos, user-added notes are stamped <span class="timestamp-example">User yyyy-mm-dd hh:mm</span>, not Carl. Open and Do items light the agent three seconds after posting, then add a fake report seven seconds later.</p>
  <form id="demoForm" action="#" method="post">
    <label>Item title<input name="title" value="Try the fake agent light" autocomplete="off"></label>
    <label>Priority<select name="priority"><?php foreach ($priorities as $priority): ?><option><?= demo_h($priority) ?></option><?php endforeach; ?></select></label>
    <label>Status<select name="status"><?php foreach ($statuses as $status): ?><option><?= demo_h($status) ?></option><?php endforeach; ?></select></label>
    <button type="submit">Post fake item</button>
  </form>
</section>

<section class="matrix">
  <h2>Every Priority + Status + Bin combination</h2>
  <div class="grid" id="itemGrid">
<?php $id = 1; foreach ($priorities as $priority): foreach ($statuses as $status): foreach ([false, true] as $binned): ?>
    <article class="task-card" data-task-id="seed-<?= $id ?>" data-priority="<?= demo_h($priority) ?>" data-status="<?= demo_h($status) ?>" data-binned="<?= $binned ? 'true' : 'false' ?>" data-agent-active="false">
      <div class="card-top"><p class="card-title">#<?= $id ?> <?= demo_h($priority) ?> / <?= demo_h($status) ?><?= $binned ? ' / Bin' : '' ?></p></div>
      <div class="badges"><span class="badge priority-<?= demo_h(demo_priority_class($priority)) ?>"><?= demo_h($priority) ?></span><span class="badge status-<?= demo_h($status) ?>"><?= demo_h($status) ?></span><?php if ($binned): ?><span class="badge bin">Bin</span><?php endif; ?></div>
      <div class="notes"><p>User 2026-10-10 19:45</p><p>This sample shows how the card looks in this state.</p></div>
    </article>
<?php $id++; endforeach; endforeach; endforeach; ?>
  </div>
</section>
</main>
<script>
const demoForm = document.getElementById('demoForm');
const itemGrid = document.getElementById('itemGrid');
const agentLine = document.getElementById('agentLine');
const agentStatus = document.getElementById('agentStatus');
let nextDemoId = 1000;
let pendingAgentCard = null;
function two(value) { return String(value).padStart(2, '0'); }
function timestamp(label) {
  const d = new Date();
  return label + ' ' + d.getFullYear() + '-' + two(d.getMonth() + 1) + '-' + two(d.getDate()) + ' ' + two(d.getHours()) + ':' + two(d.getMinutes());
}
function safeText(value) { return String(value || '').replace(/[<>&]/g, function(ch) { return {'<':'&lt;', '>':'&gt;', '&':'&amp;'}[ch]; }); }
function cardHtml(id, title, priority, status) {
  return '<article class="task-card" data-task-id="demo-' + id + '" data-priority="' + safeText(priority) + '" data-status="' + safeText(status) + '" data-binned="false" data-agent-active="false">' +
    '<div class="card-top"><p class="card-title">#' + id + ' ' + safeText(title) + '</p></div>' +
    '<div class="badges"><span class="badge">' + safeText(priority) + '</span><span class="badge status-' + safeText(status) + '">' + safeText(status) + '</span></div>' +
    '<div class="notes"><p>' + timestamp('User') + '</p><p>Posted in the public LUKE playground.</p></div>' +
    '</article>';
}
function setAgentLight(active, message) {
  agentLine.dataset.active = active ? 'true' : 'false';
  agentStatus.textContent = message;
}
function startFakeAgentActivity() {
  if (!pendingAgentCard) return;
  pendingAgentCard.dataset.agentActive = 'true';
  setAgentLight(true, 'Fake agent activity running for item #' + pendingAgentCard.dataset.taskId.replace('demo-', '') + '.');
  setTimeout(addFakeAgentReport, 7000);
}
function addFakeAgentReport() {
  const card = pendingAgentCard;
  if (!card) return;
  const notes = card.querySelector('.notes');
  if (notes) notes.insertAdjacentHTML('beforeend', '<p>' + timestamp('Agent') + '</p><p>' + "This is where the Agent would report back with what they've done." + '</p>');
  card.dataset.agentActive = 'false';
  pendingAgentCard = null;
  setAgentLight(false, 'Fake agent report added. Nothing real happened.');
}
demoForm.addEventListener('submit', function(event) {
  event.preventDefault();
  const data = new FormData(demoForm);
  const id = nextDemoId++;
  const title = data.get('title') || 'Untitled fake item';
  const priority = data.get('priority') || 'Should';
  const status = data.get('status') || 'Open';
  itemGrid.insertAdjacentHTML('afterbegin', cardHtml(id, title, priority, status));
  const newCard = itemGrid.querySelector('[data-task-id="demo-' + id + '"]');
  if (status === 'Open' || status === 'Do') {
    pendingAgentCard = newCard;
    setAgentLight(false, 'Fake agent will notice item #' + id + ' in three seconds.');
    setTimeout(startFakeAgentActivity, 3000);
  } else {
    setAgentLight(false, 'Item posted with no fake agent trigger because status is ' + status + '.');
  }
});
</script>
</body>
</html>
