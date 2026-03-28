<?php
$claude_md_path = __DIR__ . '/../CLAUDE.md';
$claude_md = file_get_contents($claude_md_path);
$voice_log_file = __DIR__ . '/voice-log.json';
$voice_log = file_exists($voice_log_file) ? json_decode(file_get_contents($voice_log_file), true) : [];

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'add_task') {
        $text = trim($_POST['text'] ?? '');
        if ($text) {
            $claude_md = preg_replace('/(## タスクリスト\n)/', "$1- [ ] $text\n", $claude_md);
            file_put_contents($claude_md_path, $claude_md);
        }
        echo json_encode(['ok' => true]);
    } elseif ($action === 'complete_task') {
        $text = $_POST['text'] ?? '';
        $claude_md = str_replace("- [ ] $text\n", "- [x] $text\n", $claude_md);
        file_put_contents($claude_md_path, $claude_md);
        echo json_encode(['ok' => true]);
    } elseif ($action === 'delete_task') {
        $text = $_POST['text'] ?? '';
        $claude_md = str_replace("- [ ] $text\n", '', $claude_md);
        $claude_md = str_replace("- [x] $text\n", '', $claude_md);
        file_put_contents($claude_md_path, $claude_md);
        echo json_encode(['ok' => true]);
    } elseif ($action === 'add_shopping') {
        $text = trim($_POST['text'] ?? '');
        if ($text) {
            $claude_md = preg_replace('/(## 買い物リスト\n)/', "$1- [ ] $text\n", $claude_md);
            file_put_contents($claude_md_path, $claude_md);
        }
        echo json_encode(['ok' => true]);
    } elseif ($action === 'delete_shopping') {
        $text = $_POST['text'] ?? '';
        $claude_md = str_replace("- [ ] $text\n", '', $claude_md);
        $claude_md = str_replace("- [x] $text\n", '', $claude_md);
        file_put_contents($claude_md_path, $claude_md);
        echo json_encode(['ok' => true]);
    } elseif ($action === 'push') {
        chdir(__DIR__ . '/..');
        $output = shell_exec('git add -A && git commit -m "dashboard update" && git push origin main 2>&1');
        echo json_encode(['ok' => true, 'output' => $output]);
    } elseif ($action === 'delete_voice') {
        $file = $_POST['file'] ?? '';
        $time = $_POST['time'] ?? '';
        $voice_log = file_exists($voice_log_file) ? json_decode(file_get_contents($voice_log_file), true) : [];
        $voice_log = array_values(array_filter($voice_log, function($e) use ($file, $time) {
            return !($e['file'] === $file && $e['time'] === $time);
        }));
        file_put_contents($voice_log_file, json_encode($voice_log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
    } elseif ($action === 'complete_voice') {
        $file = $_POST['file'] ?? '';
        $time = $_POST['time'] ?? '';
        $voice_log = file_exists($voice_log_file) ? json_decode(file_get_contents($voice_log_file), true) : [];
        foreach ($voice_log as &$e) {
            if ($e['file'] === $file && $e['time'] === $time) {
                $e['done'] = true;
                break;
            }
        }
        file_put_contents($voice_log_file, json_encode($voice_log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
    } elseif ($action === 'edit_voice') {
        $file = $_POST['file'] ?? '';
        $time = $_POST['time'] ?? '';
        $new_text = $_POST['new_text'] ?? '';
        $voice_log = file_exists($voice_log_file) ? json_decode(file_get_contents($voice_log_file), true) : [];
        foreach ($voice_log as &$e) {
            if ($e['file'] === $file && $e['time'] === $time) {
                $e['text'] = $new_text;
                break;
            }
        }
        file_put_contents($voice_log_file, json_encode($voice_log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
    }
    exit;
}

// Parse lists from CLAUDE.md
preg_match('/## タスクリスト\n([\s\S]*?)(?=\n##|\z)/', $claude_md, $task_match);
$tasks = [];
if ($task_match) {
    preg_match_all('/- \[([ x])\] (.+)/', $task_match[1], $matches, PREG_SET_ORDER);
    foreach ($matches as $m) $tasks[] = ['done' => $m[1] === 'x', 'text' => trim($m[2])];
}

preg_match('/## 買い物リスト\n([\s\S]*?)(?=\n##|\z)/', $claude_md, $shop_match);
$shopping = [];
if ($shop_match) {
    preg_match_all('/- \[([ x])\] (.+)/', $shop_match[1], $matches, PREG_SET_ORDER);
    foreach ($matches as $m) $shopping[] = ['done' => $m[1] === 'x', 'text' => trim($m[2])];
}

// Separate voice logs by tag
$voice_notes = []; $voice_tasks = []; $voice_tasks_scheduled = [];
$voice_shopping = []; $voice_shopping_scheduled = [];
$voice_food = []; $voice_health = []; $voice_substance = [];
$today = date('Y-m-d');

foreach ($voice_log as $entry) {
    $tag = $entry['tag'] ?? '';
    $show_after = $entry['show_after'] ?? null;
    $scheduled = $show_after && $show_after > $today;
    switch ($tag) {
        case 'task': $scheduled ? $voice_tasks_scheduled[] = $entry : $voice_tasks[] = $entry; break;
        case 'shopping': $scheduled ? $voice_shopping_scheduled[] = $entry : $voice_shopping[] = $entry; break;
        case 'food': $voice_food[] = $entry; break;
        case 'health': $voice_health[] = $entry; break;
        case 'substance': $voice_substance[] = $entry; break;
        default: $voice_notes[] = $entry; break;
    }
}

$notes_by_date = [];
foreach ($voice_notes as $e) $notes_by_date[$e['date']][] = $e;
krsort($notes_by_date);

// Helper: render voice entry with edit/delete/push
function voice_entry_html($entry, $show_push = false) {
    $file = htmlspecialchars(addslashes($entry['file']));
    $time = htmlspecialchars($entry['time']);
    $time_short = htmlspecialchars(substr($entry['time'], 0, 5));
    $text = htmlspecialchars($entry['text']);
    $summary = isset($entry['summary']) ? htmlspecialchars($entry['summary']) : $text;
    $html = '<div class="voice-entry">';
    $html .= '<span class="voice-time">' . $time_short . '</span>';
    $html .= '<div class="note-wrapper">';
    $html .= '<div class="note-summary" onclick="toggleNote(this)">' . $summary . '</div>';
    $html .= '<div class="note-full"><span class="editable" onclick="event.stopPropagation();editVoice(this,\'' . $file . '\',\'' . $time . '\')">' . $text . '</span><div class="note-close" onclick="closeNote(this)">▲ close</div></div>';
    $html .= '</div>';
    $html .= '<span class="entry-actions">';
    if ($show_push) {
        $html .= '<button class="push-item-btn" onclick="pushToServer(this)" title="Push">↑</button>';
    }
    $html .= '<button class="delete-btn" onclick="deleteVoice(\'' . $file . '\',\'' . $time . '\')" title="Delete">×</button>';
    $html .= '</span>';
    $html .= '</div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Hiragino Sans', 'Hiragino Kaku Gothic ProN', sans-serif; background: #0d0d0d; color: #e0e0e0; padding: 20px; max-width: 760px; margin: 0 auto; font-size: 16px; }
  h1 { font-size: 15px; color: #666; margin-bottom: 28px; font-weight: normal; }
  .section { margin-bottom: 28px; background: #1a1a1a; border-radius: 10px; padding: 24px; }
  .section-title { font-size: 14px; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 18px; padding-bottom: 10px; border-bottom: 1px solid #2a2a2a; }
  .sub-title { color: #888; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
  .sub-title-border { margin: 20px 0 10px; padding-top: 16px; border-top: 1px solid #2a2a2a; }
  .voice-date { font-size: 13px; color: #555; margin: 14px 0 8px; }
  .voice-date:first-child { margin-top: 0; }
  .voice-entry { display: flex; gap: 12px; padding: 10px 0; font-size: 16px; line-height: 1.7; border-bottom: 1px solid #1f1f1f; align-items: flex-start; }
  .voice-entry:last-child { border-bottom: none; }
  .voice-time { color: #666; font-size: 14px; white-space: nowrap; min-width: 48px; padding-top: 2px; }
  .voice-text { color: #ccc; flex: 1; }
  .editable { cursor: pointer; border-radius: 4px; padding: 4px 6px; margin: -4px -6px; }
  .editable:hover { background: #222; }
  .edit-input { background: #111; border: 1px solid #444; color: #ccc; padding: 8px 12px; border-radius: 6px; font-size: 16px; font-family: inherit; width: 100%; line-height: 1.7; resize: vertical; }
  .entry-actions { display: flex; gap: 2px; white-space: nowrap; align-items: center; }
  .list-item { display: flex; align-items: center; gap: 10px; padding: 10px 0; font-size: 16px; line-height: 1.7; border-bottom: 1px solid #1f1f1f; }
  .list-item:last-of-type { border-bottom: none; }
  .list-item.done { color: #555; text-decoration: line-through; }
  .list-item .editable { flex: 1; }
  .delete-btn { background: #2a2a2a; border: none; color: #888; cursor: pointer; font-size: 18px; padding: 6px 12px; line-height: 1; border-radius: 6px; min-width: 36px; min-height: 36px; display: flex; align-items: center; justify-content: center; }
  .delete-btn:hover { background: #3a1a1a; color: #e55; }
  .push-item-btn { background: #1a2a1a; border: none; color: #5cb85c; cursor: pointer; font-size: 18px; padding: 6px 12px; line-height: 1; border-radius: 6px; min-width: 36px; min-height: 36px; display: flex; align-items: center; justify-content: center; }
  .push-item-btn:hover { background: #1f3a1f; }
  .complete-btn { background: #1a2a3a; border: none; color: #4a9eff; cursor: pointer; font-size: 16px; padding: 6px 12px; line-height: 1; border-radius: 6px; min-width: 36px; min-height: 36px; display: flex; align-items: center; justify-content: center; }
  .complete-btn:hover { background: #1a3a4a; }
  .push-item-btn.done { background: #1a1a1a; color: #555; }
  .add-form { display: flex; gap: 10px; margin-top: 16px; padding-top: 16px; border-top: 1px solid #2a2a2a; }
  .add-form input[type="text"] { flex: 1; background: #111; border: 1px solid #333; color: #ccc; padding: 10px 14px; border-radius: 6px; font-size: 16px; font-family: inherit; }
  .add-form input[type="text"]:focus { outline: none; border-color: #555; }
  .add-form button { background: #2a2a2a; color: #aaa; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 15px; font-family: inherit; }
  .add-form button:hover { background: #333; color: #ccc; }
  .empty { color: #444; font-size: 14px; font-style: italic; }
  .toggle { color: #666; font-size: 14px; cursor: pointer; padding: 12px 0 6px; user-select: none; }
  .toggle:hover { color: #999; }
  .toggle-content { display: none; }
  .toggle-content.open { display: block; }
  .scheduled-date { font-size: 13px; color: #555; margin-left: 6px; }
  .note-summary { color: #999; cursor: pointer; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; border-radius: 4px; padding: 4px 6px; margin: -4px -6px; }
  .note-summary:hover { background: #222; color: #ccc; }
  .note-full { display: none; color: #ccc; white-space: pre-wrap; line-height: 1.8; padding: 10px 0 6px; flex: 1; }
  .note-full.open { display: block; }
  .note-wrapper { flex: 1; min-width: 0; }
  .note-close { color: #555; font-size: 13px; cursor: pointer; padding: 8px 0 2px; user-select: none; }
  .note-close:hover { color: #888; }
</style>
</head>
<body>

<h1>mblog local dashboard</h1>

<!-- Tasks -->
<div class="section">
  <div class="section-title">Tasks</div>
  <?php foreach ($tasks as $task): ?>
    <div class="list-item <?= $task['done'] ? 'done' : '' ?>">
      <span><?= htmlspecialchars($task['text']) ?></span>
      <span class="entry-actions">
        <?php if (!$task['done']): ?>
          <button class="complete-btn" onclick="completeItem('task','<?= htmlspecialchars(addslashes($task['text'])) ?>')" title="Complete">✓</button>
        <?php endif; ?>
        <button class="delete-btn" onclick="deleteItem('task','<?= htmlspecialchars(addslashes($task['text'])) ?>')">×</button>
      </span>
    </div>
  <?php endforeach; ?>
  <?php foreach ($voice_tasks as $vt): $is_done = !empty($vt['done']); ?>
    <div class="list-item expandable-item <?= $is_done ? 'done' : '' ?>">
      <div class="note-wrapper">
        <div class="note-summary" onclick="toggleNote(this)"><?= htmlspecialchars($vt['summary'] ?? $vt['text']) ?></div>
        <div class="note-full">
          <span class="editable" onclick="event.stopPropagation();editVoice(this,'<?= htmlspecialchars(addslashes($vt['file'])) ?>','<?= htmlspecialchars($vt['time']) ?>')"><?= htmlspecialchars($vt['text']) ?></span>
          <div class="note-close" onclick="closeNote(this)">▲ close</div>
        </div>
      </div>
      <span class="entry-actions">
        <?php if (!$is_done): ?>
          <button class="complete-btn" onclick="completeVoice('<?= htmlspecialchars(addslashes($vt['file'])) ?>','<?= htmlspecialchars($vt['time']) ?>')" title="Complete">✓</button>
        <?php endif; ?>
        <button class="delete-btn" onclick="deleteVoice('<?= htmlspecialchars(addslashes($vt['file'])) ?>','<?= htmlspecialchars($vt['time']) ?>')">×</button>
      </span>
    </div>
  <?php endforeach; ?>
  <?php if (!empty($voice_tasks_scheduled)): ?>
    <div class="toggle" onclick="this.nextElementSibling.classList.toggle('open')">▶ Scheduled (<?= count($voice_tasks_scheduled) ?>)</div>
    <div class="toggle-content">
      <?php foreach ($voice_tasks_scheduled as $vt): ?>
        <div class="list-item">
          <span class="editable" onclick="editVoice(this,'<?= htmlspecialchars(addslashes($vt['file'])) ?>','<?= htmlspecialchars($vt['time']) ?>')"><?= htmlspecialchars($vt['text']) ?></span>
          <span class="scheduled-date"><?= htmlspecialchars($vt['show_after']) ?></span>
          <button class="delete-btn" onclick="deleteVoice('<?= htmlspecialchars(addslashes($vt['file'])) ?>','<?= htmlspecialchars($vt['time']) ?>')">×</button>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="add-form">
    <input type="text" id="new-task" placeholder="Add task...">
    <button onclick="addItem('task')">Add</button>
  </div>
  <button class="push-item-btn" style="width:100%;margin-top:12px;padding:10px;font-size:14px;" onclick="pushToServer(this)">↑ Push to Server</button>
</div>

<!-- Shopping -->
<div class="section">
  <div class="section-title">Shopping</div>
  <?php foreach ($shopping as $item): ?>
    <div class="list-item <?= $item['done'] ? 'done' : '' ?>">
      <span><?= htmlspecialchars($item['text']) ?></span>
      <button class="delete-btn" onclick="deleteItem('shopping','<?= htmlspecialchars(addslashes($item['text'])) ?>')">×</button>
    </div>
  <?php endforeach; ?>
  <?php foreach ($voice_shopping as $vs): ?>
    <div class="list-item expandable-item">
      <div class="note-wrapper">
        <div class="note-summary" onclick="toggleNote(this)"><?= htmlspecialchars($vs['summary'] ?? $vs['text']) ?></div>
        <div class="note-full">
          <span class="editable" onclick="event.stopPropagation();editVoice(this,'<?= htmlspecialchars(addslashes($vs['file'])) ?>','<?= htmlspecialchars($vs['time']) ?>')"><?= htmlspecialchars($vs['text']) ?></span>
          <div class="note-close" onclick="closeNote(this)">▲ close</div>
        </div>
      </div>
      <button class="delete-btn" onclick="deleteVoice('<?= htmlspecialchars(addslashes($vs['file'])) ?>','<?= htmlspecialchars($vs['time']) ?>')">×</button>
    </div>
  <?php endforeach; ?>
  <?php if (!empty($voice_shopping_scheduled)): ?>
    <div class="toggle" onclick="this.nextElementSibling.classList.toggle('open')">▶ Scheduled (<?= count($voice_shopping_scheduled) ?>)</div>
    <div class="toggle-content">
      <?php foreach ($voice_shopping_scheduled as $vs): ?>
        <div class="list-item">
          <span class="editable" onclick="editVoice(this,'<?= htmlspecialchars(addslashes($vs['file'])) ?>','<?= htmlspecialchars($vs['time']) ?>')"><?= htmlspecialchars($vs['text']) ?></span>
          <span class="scheduled-date"><?= htmlspecialchars($vs['show_after']) ?></span>
          <button class="delete-btn" onclick="deleteVoice('<?= htmlspecialchars(addslashes($vs['file'])) ?>','<?= htmlspecialchars($vs['time']) ?>')">×</button>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="add-form">
    <input type="text" id="new-shopping" placeholder="Add item...">
    <button onclick="addItem('shopping')">Add</button>
  </div>
</div>

<!-- Health Log -->
<?php if (!empty($voice_food) || !empty($voice_health) || !empty($voice_substance)): ?>
<div class="section">
  <div class="section-title">Health Log</div>

  <?php if (!empty($voice_food)): ?>
    <div class="sub-title">Food</div>
    <?php $food_by_date = []; foreach ($voice_food as $e) $food_by_date[$e['date']][] = $e; krsort($food_by_date);
      foreach ($food_by_date as $date => $entries): ?>
      <div class="voice-date"><?= htmlspecialchars($date) ?></div>
      <?php foreach ($entries as $entry) echo voice_entry_html($entry, true); ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($voice_health)): ?>
    <div class="sub-title sub-title-border">Health</div>
    <?php $hbd = []; foreach ($voice_health as $e) $hbd[$e['date']][] = $e; krsort($hbd);
      foreach ($hbd as $date => $entries): ?>
      <div class="voice-date"><?= htmlspecialchars($date) ?></div>
      <?php foreach ($entries as $entry) echo voice_entry_html($entry, true); ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($voice_substance)): ?>
    <div class="sub-title sub-title-border">Substance</div>
    <?php $sbd = []; foreach ($voice_substance as $e) $sbd[$e['date']][] = $e; krsort($sbd);
      foreach ($sbd as $date => $entries): ?>
      <div class="voice-date"><?= htmlspecialchars($date) ?></div>
      <?php foreach ($entries as $entry) echo voice_entry_html($entry, true); ?>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Voice Notes -->
<div class="section">
  <div class="section-title">Voice Notes</div>
  <?php if (empty($notes_by_date)): ?>
    <div class="empty">No voice notes.</div>
  <?php else: ?>
    <?php foreach ($notes_by_date as $date => $entries): ?>
      <div class="voice-date"><?= htmlspecialchars($date) ?></div>
      <?php foreach ($entries as $entry):
        $file = htmlspecialchars(addslashes($entry['file']));
        $time = htmlspecialchars($entry['time']);
        $time_short = htmlspecialchars(substr($entry['time'], 0, 5));
        $text = htmlspecialchars($entry['text']);
        $summary = isset($entry['summary']) ? htmlspecialchars($entry['summary']) : (mb_strlen($entry['text']) > 40 ? htmlspecialchars(mb_substr($entry['text'], 0, 40)) . '...' : $text);
      ?>
        <div class="voice-entry">
          <span class="voice-time"><?= $time_short ?></span>
          <div class="note-wrapper">
            <div class="note-summary" onclick="toggleNote(this)"><?= $summary ?></div>
            <div class="note-full">
              <span class="editable" onclick="editVoice(this,'<?= $file ?>','<?= $time ?>')"><?= $text ?></span>
              <div class="note-close" onclick="closeNote(this)">▲ close</div>
            </div>
          </div>
          <span class="entry-actions">
            <button class="delete-btn" onclick="deleteVoice('<?= $file ?>','<?= $time ?>')" title="Delete">×</button>
          </span>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
function addItem(type) {
  const input = document.getElementById('new-' + type);
  const text = input.value.trim();
  if (!text) return;
  const form = new FormData();
  form.append('text', text);
  fetch('?action=add_' + type, { method: 'POST', body: form }).then(() => location.reload());
}

function deleteItem(type, text) {
  if (!confirm('Delete this item?')) return;
  const form = new FormData();
  form.append('text', text);
  fetch('?action=delete_' + type, { method: 'POST', body: form }).then(() => location.reload());
}

function deleteVoice(file, time) {
  if (!confirm('Delete this item?')) return;
  const form = new FormData();
  form.append('file', file);
  form.append('time', time);
  fetch('?action=delete_voice', { method: 'POST', body: form }).then(() => location.reload());
}

function editVoice(el, file, time) {
  if (el.querySelector('textarea')) return;
  const original = el.textContent;
  const textarea = document.createElement('textarea');
  textarea.className = 'edit-input';
  textarea.value = original;
  textarea.rows = Math.max(2, Math.ceil(original.length / 40));
  el.textContent = '';
  el.appendChild(textarea);
  textarea.focus();

  function save() {
    const newText = textarea.value.trim();
    if (newText && newText !== original) {
      const form = new FormData();
      form.append('file', file);
      form.append('time', time);
      form.append('new_text', newText);
      fetch('?action=edit_voice', { method: 'POST', body: form }).then(() => location.reload());
    } else {
      el.textContent = original;
    }
  }

  textarea.addEventListener('blur', save);
  textarea.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); save(); }
    if (e.key === 'Escape') { el.textContent = original; }
  });
}

function toggleNote(el) {
  const full = el.nextElementSibling;
  full.classList.toggle('open');
  el.style.display = full.classList.contains('open') ? 'none' : '';
}

function closeNote(el) {
  const full = el.parentElement;
  const summary = full.previousElementSibling;
  full.classList.remove('open');
  summary.style.display = '';
}

function completeItem(type, text) {
  const form = new FormData();
  form.append('text', text);
  fetch('?action=complete_' + type, { method: 'POST', body: form }).then(() => location.reload());
}

function completeVoice(file, time) {
  const form = new FormData();
  form.append('file', file);
  form.append('time', time);
  fetch('?action=complete_voice', { method: 'POST', body: form }).then(() => location.reload());
}

function pushToServer(btn) {
  btn.disabled = true;
  btn.textContent = '...';
  fetch('?action=push', { method: 'POST' })
    .then(r => r.json())
    .then(() => { btn.textContent = '✓'; btn.classList.add('done'); })
    .catch(() => { btn.textContent = '!'; btn.disabled = false; });
}

document.getElementById('new-task').addEventListener('keydown', e => { if (e.key === 'Enter') addItem('task'); });
document.getElementById('new-shopping').addEventListener('keydown', e => { if (e.key === 'Enter') addItem('shopping'); });
</script>

</body>
</html>
