<?php
require __DIR__ . '/../helpers.php';
require __DIR__ . '/../db.php';

// This is the PAGE (the old todo.php was really the toggle endpoint — now toggle_task.php).
if (empty($_SESSION['user_id'])) {
    header('Location: ../index.html');
    exit;
}
$uid = (int)$_SESSION['user_id'];

$stmt = $conn->prepare(
    "SELECT id, task, completed, priority, due_date, created_at
       FROM tasks WHERE user_id = ? ORDER BY completed ASC, id DESC"
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$tasks = array_map('task_row', $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
$stmt->close();

$username = (string)($_SESSION['username'] ?? 'there');
header('Cache-Control: no-store'); // don't show this page from cache after logout
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Todo App - My tasks</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',system-ui,sans-serif}
:root{
  --orange:#ff7a18;--orange2:#ff9a3c;--deep:#ff4e00;--amber:#ffb347;--dark:#1a0f08;
  --glow:0 0 25px rgba(255,122,24,.45);
  --high:#ff5a4d;--medium:#ffb347;--low:#4cc38a;
  --text:#fff3e6;--muted:rgba(255,225,195,.65);
}
body{
  min-height:100vh;padding:28px 16px 60px;color:var(--text);
  background:radial-gradient(circle at 20% 10%,#2a1206 0%,#120703 60%,#000 100%);
  background-attachment:fixed;position:relative;overflow-x:hidden;
}
body::before,body::after{content:"";position:fixed;border-radius:50%;filter:blur(100px);opacity:.4;z-index:0;pointer-events:none}
body::before{width:420px;height:420px;background:var(--orange);top:-140px;left:-120px}
body::after{width:360px;height:360px;background:var(--deep);bottom:-140px;right:-120px}
.app{position:relative;z-index:1;max-width:760px;margin:0 auto}
button,input,select{font:inherit;color:inherit}
button{cursor:pointer}
:focus-visible{outline:2px solid var(--amber);outline-offset:2px}

.card{
  background:linear-gradient(145deg,rgba(255,255,255,.09),rgba(255,255,255,.03));
  backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);
  border:1px solid rgba(255,180,90,.3);border-radius:18px;
  box-shadow:0 18px 45px rgba(0,0,0,.5),inset 0 1px 0 rgba(255,255,255,.12);
}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px}
.top h1{
  font-size:28px;letter-spacing:.5px;
  background:linear-gradient(90deg,var(--amber),var(--orange),var(--deep));
  -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;
}
.top p{color:var(--muted);font-size:14px;margin-top:2px}

.btn{
  padding:10px 18px;border:none;border-radius:11px;font-weight:700;color:var(--dark);
  background:linear-gradient(135deg,var(--amber),var(--orange),var(--deep));
  box-shadow:0 6px 18px rgba(255,78,0,.35);transition:transform .2s,filter .2s;
}
.btn:hover{transform:translateY(-2px);filter:brightness(1.1)}
.btn:disabled{opacity:.6;cursor:wait}
.btn.ghost{background:rgba(255,255,255,.07);color:var(--amber);border:1px solid rgba(255,180,90,.35);box-shadow:none}

.stats{padding:18px 20px;margin-bottom:16px}
.counts{display:flex;gap:26px;flex-wrap:wrap;margin-bottom:12px}
.counts div{display:flex;flex-direction:column}
.counts b{font-size:26px;line-height:1.1}
.counts span{font-size:13px;color:var(--muted)}
.bar{height:8px;border-radius:99px;background:rgba(0,0,0,.4);overflow:hidden}
.bar i{display:block;height:100%;width:0;border-radius:99px;background:linear-gradient(90deg,var(--amber),var(--deep));transition:width .5s}

.add{padding:16px;margin-bottom:16px;display:grid;grid-template-columns:1fr auto auto auto;gap:10px}
.field{
  height:46px;padding:0 14px;border-radius:11px;border:1px solid rgba(255,180,90,.3);
  background:rgba(0,0,0,.3);outline:none;min-width:0;
}
.field:focus{border-color:var(--orange);box-shadow:0 0 0 3px rgba(255,122,24,.2)}
.field::placeholder{color:rgba(255,215,170,.5)}
select.field option{background:#2a1206;color:#fff}
input[type=date].field{color-scheme:dark}
.add .btn{height:46px}

.toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px}
.tabs{display:flex;gap:6px}
.tab{padding:8px 14px;border-radius:99px;border:1px solid rgba(255,180,90,.3);background:transparent;color:var(--muted)}
.tab[aria-pressed=true]{background:rgba(255,122,24,.25);color:#fff;border-color:var(--orange)}
.toolbar .field{height:38px;flex:1;min-width:140px}
.toolbar .btn{padding:8px 14px}

#list{list-style:none;display:flex;flex-direction:column;gap:10px}
.todo{
  display:flex;gap:12px;align-items:flex-start;padding:14px 14px 14px 12px;border-radius:14px;
  background:rgba(255,255,255,.05);border:1px solid rgba(255,180,90,.2);border-left:4px solid var(--medium);
}
.todo.prio-high{border-left-color:var(--high)}
.todo.prio-low{border-left-color:var(--low)}
.todo.done .text{text-decoration:line-through;opacity:.5}
.icon-btn{
  flex:none;width:34px;height:34px;display:grid;place-items:center;border-radius:9px;
  background:rgba(255,122,24,.12);border:1px solid rgba(255,180,90,.3);color:var(--amber);transition:background .2s;
}
.icon-btn:hover{background:rgba(255,122,24,.3);color:#fff}
.icon-btn svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.todo.done .check{background:var(--orange);color:var(--dark)}
.body{flex:1;min-width:0}
.text{font-size:16px;line-height:1.4;overflow-wrap:anywhere}
.meta{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;font-size:12.5px;color:var(--muted)}
.badge{padding:2px 9px;border-radius:99px;border:1px solid currentColor}
.badge.high{color:var(--high)}.badge.medium{color:var(--medium)}.badge.low{color:var(--low)}
.due.overdue{color:var(--high);font-weight:700}
.actions{display:flex;gap:6px;flex:none}
.edit-form{flex:1;display:grid;grid-template-columns:1fr 1fr;gap:8px}
.edit-form .field{height:40px}
.edit-form .wide{grid-column:1/-1}
.edit-form .row{grid-column:1/-1;display:flex;gap:8px}

#empty{text-align:center;color:var(--muted);padding:34px 10px}
#toast{
  position:fixed;left:50%;bottom:22px;transform:translate(-50%,160%);z-index:50;
  padding:12px 22px;border-radius:12px;font-weight:600;background:var(--orange);color:#fff;
  box-shadow:0 12px 30px rgba(0,0,0,.45);transition:transform .3s;max-width:90%;
}
#toast.show{transform:translate(-50%,0)}
#toast.err{background:#e63946}

@media (max-width:620px){
  .add{grid-template-columns:1fr 1fr}
  .add .field:first-child{grid-column:1/-1}
  .add .btn{grid-column:1/-1}
  .actions{flex-direction:column}
}
@media (prefers-reduced-motion:reduce){*{transition:none!important;animation:none!important}}
</style>
</head>
<body>
<main class="app">
  <header class="top">
    <div>
      <h1>Hi, <?= h($username) ?> 🍊</h1>
      <p id="subtitle"></p>
    </div>
    <form method="post" action="logout.php">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <button class="btn ghost" type="submit">Log out</button>
    </form>
  </header>

  <section class="card stats" aria-label="Progress">
    <div class="counts">
      <div><b id="nTotal">0</b><span>Total</span></div>
      <div><b id="nActive">0</b><span>To do</span></div>
      <div><b id="nDone">0</b><span>Done</span></div>
    </div>
    <div class="bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" id="bar"><i id="barFill"></i></div>
  </section>

  <form class="card add" id="addForm">
    <input class="field" id="newTask" maxlength="500" placeholder="What needs doing?" aria-label="New task" required>
    <select class="field" id="newPrio" aria-label="Priority">
      <option value="low">Low</option>
      <option value="medium" selected>Medium</option>
      <option value="high">High</option>
    </select>
    <input class="field" type="date" id="newDue" aria-label="Due date">
    <button class="btn" type="submit">Add task</button>
  </form>

  <div class="toolbar">
    <div class="tabs" id="tabs">
      <button class="tab" data-f="all" aria-pressed="true">All</button>
      <button class="tab" data-f="active" aria-pressed="false">To do</button>
      <button class="tab" data-f="done" aria-pressed="false">Done</button>
    </div>
    <input class="field" id="search" type="search" placeholder="Search tasks" aria-label="Search tasks">
    <button class="btn ghost" id="clearDone" type="button">Clear done</button>
  </div>

  <ul id="list"></ul>
  <p id="empty" hidden></p>
</main>
<div id="toast" role="status" aria-live="polite"></div>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
let tasks = <?= json_encode($tasks, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
let filter = 'all', query = '', editingId = null;

const $ = id => document.getElementById(id);
const ICON = {
  check: '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
  edit:  '<svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>',
  trash: '<svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>',
};

function el(tag, cls, text) {
  const n = document.createElement(tag);
  if (cls) n.className = cls;
  if (text !== undefined) n.textContent = text;   // textContent = no XSS
  return n;
}
function iconBtn(icon, label, cls, onClick) {
  const b = el('button', 'icon-btn ' + (cls || ''));
  b.type = 'button'; b.title = label; b.setAttribute('aria-label', label);
  b.innerHTML = icon;                              // static, trusted SVG only
  b.addEventListener('click', onClick);
  return b;
}

let toastTimer;
function toast(msg, isErr) {
  const t = $('toast');
  t.textContent = msg; t.classList.toggle('err', !!isErr); t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 2600);
}

async function api(endpoint, data = {}) {
  const fd = new FormData();
  fd.append('csrf', CSRF);
  for (const [k, v] of Object.entries(data)) fd.append(k, v ?? '');
  let res;
  try { res = await fetch(endpoint, { method: 'POST', body: fd, credentials: 'same-origin' }); }
  catch { toast('Network error. Is the server running?', true); return null; }
  if (res.status === 401) { location.href = '../index.html'; return null; }
  let json;
  try { json = await res.json(); } catch { toast('Unexpected server response.', true); return null; }
  if (!json.ok) { toast(json.message || 'Something went wrong.', true); return null; }
  return json;
}

const todayStr = () => new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD in local time
const sortTasks = () => tasks.sort((a, b) => a.completed - b.completed || b.id - a.id);

function row(t) {
  const li = el('li', `todo prio-${t.priority}${t.completed ? ' done' : ''}`);
  li.dataset.id = t.id;

  li.appendChild(iconBtn(ICON.check, t.completed ? 'Mark as not done' : 'Mark as done', 'check', () => toggle(t)));

  const body = el('div', 'body');
  body.appendChild(el('p', 'text', t.task));
  const meta = el('div', 'meta');
  meta.appendChild(el('span', 'badge ' + t.priority, t.priority));
  if (t.due_date) {
    const overdue = !t.completed && t.due_date < todayStr();
    meta.appendChild(el('span', 'due' + (overdue ? ' overdue' : ''), (overdue ? 'Overdue · ' : 'Due ') + t.due_date));
  }
  meta.appendChild(el('span', '', 'Added ' + t.created_at.slice(0, 16)));
  body.appendChild(meta);
  li.appendChild(body);

  const actions = el('div', 'actions');
  actions.appendChild(iconBtn(ICON.edit, 'Edit task', '', () => { editingId = t.id; render(); }));
  actions.appendChild(iconBtn(ICON.trash, 'Delete task', '', () => remove(t)));
  li.appendChild(actions);
  return li;
}

function editRow(t) {
  const li = el('li', `todo prio-${t.priority}`);
  const f = el('form', 'edit-form');

  const text = el('input', 'field wide'); text.value = t.task; text.maxLength = 500; text.required = true;
  text.setAttribute('aria-label', 'Task text');
  const prio = el('select', 'field');
  ['low', 'medium', 'high'].forEach(p => { const o = el('option', '', p); o.value = p; prio.appendChild(o); });
  prio.value = t.priority; prio.setAttribute('aria-label', 'Priority');
  const due = el('input', 'field'); due.type = 'date'; due.value = t.due_date || ''; due.setAttribute('aria-label', 'Due date');

  const rowEl = el('div', 'row');
  const save = el('button', 'btn', 'Save'); save.type = 'submit';
  const cancel = el('button', 'btn ghost', 'Cancel'); cancel.type = 'button';
  cancel.addEventListener('click', () => { editingId = null; render(); });
  rowEl.append(save, cancel);

  f.append(text, prio, due, rowEl);
  f.addEventListener('keydown', e => { if (e.key === 'Escape') { editingId = null; render(); } });
  f.addEventListener('submit', async e => {
    e.preventDefault();
    save.disabled = true;
    const r = await api('edit_task.php', { id: t.id, task: text.value, priority: prio.value, due_date: due.value });
    if (r) { Object.assign(t, r.task); editingId = null; sortTasks(); render(); toast('Task updated'); }
    else save.disabled = false;
  });
  li.appendChild(f);
  setTimeout(() => text.focus(), 0);
  return li;
}

function render() {
  const list = $('list');
  list.replaceChildren();
  const q = query.trim().toLowerCase();
  const visible = tasks.filter(t =>
    (filter === 'all' || (filter === 'active' ? !t.completed : t.completed)) &&
    (!q || t.task.toLowerCase().includes(q))
  );
  visible.forEach(t => list.appendChild(t.id === editingId ? editRow(t) : row(t)));

  const empty = $('empty');
  empty.hidden = visible.length > 0;
  if (!visible.length) {
    empty.textContent = tasks.length === 0 ? 'No tasks yet. Add your first one above.'
      : q ? 'No tasks match your search.' : 'Nothing in this view.';
  }

  const total = tasks.length, done = tasks.filter(t => t.completed).length;
  $('nTotal').textContent = total; $('nDone').textContent = done; $('nActive').textContent = total - done;
  const pct = total ? Math.round(done / total * 100) : 0;
  $('barFill').style.width = pct + '%';
  $('bar').setAttribute('aria-valuenow', pct);
  $('subtitle').textContent = total === 0 ? 'A fresh start.'
    : done === total ? 'All done. Nice work!' : `${total - done} left to do · ${pct}% complete`;
}

async function toggle(t) {
  const r = await api('toggle_task.php', { id: t.id });
  if (r) { Object.assign(t, r.task); sortTasks(); render(); }
}
async function remove(t) {
  if (!confirm('Delete this task?')) return;
  const r = await api('delete_task.php', { id: t.id });
  if (r) { tasks = tasks.filter(x => x.id !== t.id); render(); toast('Task deleted'); }
}

$('addForm').addEventListener('submit', async e => {
  e.preventDefault();
  const text = $('newTask').value.trim();
  if (!text) return;
  const btn = e.target.querySelector('[type=submit]'); btn.disabled = true;
  const r = await api('add_task.php', { task: text, priority: $('newPrio').value, due_date: $('newDue').value });
  btn.disabled = false;
  if (r) {
    tasks.push(r.task); sortTasks();
    $('newTask').value = ''; $('newDue').value = '';
    render(); $('newTask').focus();
  }
});

$('tabs').addEventListener('click', e => {
  const b = e.target.closest('.tab'); if (!b) return;
  filter = b.dataset.f;
  document.querySelectorAll('.tab').forEach(x => x.setAttribute('aria-pressed', String(x === b)));
  render();
});
$('search').addEventListener('input', e => { query = e.target.value; render(); });
$('clearDone').addEventListener('click', async () => {
  if (!tasks.some(t => t.completed)) return toast('No completed tasks to clear.');
  if (!confirm('Delete all completed tasks?')) return;
  const r = await api('clear_completed.php');
  if (r) { tasks = tasks.filter(t => !t.completed); render(); toast(`Cleared ${r.deleted} task(s)`); }
});

sortTasks();
render();
</script>
</body>
</html>
