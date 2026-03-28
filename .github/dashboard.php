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
        if (strpos($claude_md, "- [ ] $text\n") !== false) {
            $claude_md = str_replace("- [ ] $text\n", "- [x] $text\n", $claude_md);
        } else {
            $claude_md = str_replace("- [x] $text\n", "- [ ] $text\n", $claude_md);
        }
        file_put_contents($claude_md_path, $claude_md);
        echo json_encode(['ok' => true]);
    } elseif ($action === 'edit_task') {
        $old = $_POST['old_text'] ?? '';
        $new = trim($_POST['new_text'] ?? '');
        if ($old && $new) {
            $claude_md = str_replace("- [ ] $old\n", "- [ ] $new\n", $claude_md);
            $claude_md = str_replace("- [x] $old\n", "- [x] $new\n", $claude_md);
            file_put_contents($claude_md_path, $claude_md);
        }
        echo json_encode(['ok' => true]);
    } elseif ($action === 'edit_shopping') {
        $old = $_POST['old_text'] ?? '';
        $new = trim($_POST['new_text'] ?? '');
        if ($old && $new) {
            $claude_md = str_replace("- [ ] $old\n", "- [ ] $new\n", $claude_md);
            $claude_md = str_replace("- [x] $old\n", "- [x] $new\n", $claude_md);
            file_put_contents($claude_md_path, $claude_md);
        }
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
                $e['done'] = empty($e['done']);
                break;
            }
        }
        file_put_contents($voice_log_file, json_encode($voice_log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
    } elseif ($action === 'edit_voice_meta') {
        $file = $_POST['file'] ?? '';
        $time = $_POST['time'] ?? '';
        $new_date = $_POST['new_date'] ?? '';
        $new_time = $_POST['new_time'] ?? '';
        $voice_log = file_exists($voice_log_file) ? json_decode(file_get_contents($voice_log_file), true) : [];
        foreach ($voice_log as &$e) {
            if ($e['file'] === $file && $e['time'] === $time) {
                if ($new_date) $e['date'] = $new_date;
                if ($new_time) $e['time'] = $new_time;
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
function voice_entry_html($entry, $show_push = false, $use_summary = true, $show_date_edit = false) {
    $file = htmlspecialchars(addslashes($entry['file']));
    $time = htmlspecialchars($entry['time']);
    $date = htmlspecialchars($entry['date']);
    $time_short = htmlspecialchars(substr($entry['time'], 0, 5));
    $text = htmlspecialchars($entry['text']);
    $html = '<div class="voice-entry">';
    $html .= '<span class="voice-time">' . $time_short . '</span>';
    if ($use_summary) {
        $summary = isset($entry['summary']) ? htmlspecialchars($entry['summary']) : $text;
        $html .= '<div class="note-wrapper">';
        $html .= '<div class="note-summary" onclick="toggleNote(this)">' . $summary . '</div>';
        $html .= '<div class="note-full"><span>' . $text . '</span><div class="note-close" onclick="closeNote(this)">▲ close</div></div>';
        $html .= '</div>';
    } else {
        $html .= '<span class="voice-text">' . $text . '</span>';
    }
    $html .= '<span class="entry-actions">';
    $html .= '<button class="action-btn edit-btn" onclick="editAll(this,\'' . $file . '\',\'' . $time . '\',\'' . $date . '\',' . ($show_date_edit ? 'true' : 'false') . ')" title="Edit">✎</button>';
    if ($show_push) {
        $html .= '<button class="action-btn push-item-btn" onclick="pushToServer(this)" title="Push">↑</button>';
    }
    $html .= '<button class="action-btn delete-btn" onclick="deleteVoice(\'' . $file . '\',\'' . $time . '\')" title="Delete">×</button>';
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
<link rel="stylesheet" href="theme-a.css">
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
        <button class="action-btn edit-btn" onclick="editListItem(this,'task','<?= htmlspecialchars(addslashes($task['text'])) ?>')" title="Edit">✎</button>
        <button class="action-btn complete-btn" onclick="completeItem('task','<?= htmlspecialchars(addslashes($task['text'])) ?>')" title="<?= $task['done'] ? 'Undo' : 'Complete' ?>"><?= $task['done'] ? '↩' : '✓' ?></button>
        <button class="action-btn push-item-btn" onclick="pushToServer(this)" title="Push">↑</button>
        <button class="action-btn delete-btn" onclick="deleteItem('task','<?= htmlspecialchars(addslashes($task['text'])) ?>')">×</button>
      </span>
    </div>
  <?php endforeach; ?>
  <?php foreach ($voice_tasks as $vt): $is_done = !empty($vt['done']); ?>
    <div class="list-item expandable-item <?= $is_done ? 'done' : '' ?>">
      <div class="note-wrapper">
        <div class="note-summary" onclick="toggleNote(this)"><?= htmlspecialchars($vt['summary'] ?? $vt['text']) ?></div>
        <div class="note-full">
          <span class="editable" onclick="event.stopPropagation();" data-file="'<?= htmlspecialchars(addslashes($vt['file'])) ?>','<?= htmlspecialchars($vt['time']) ?>')"><?= htmlspecialchars($vt['text']) ?></span>
          <div class="note-close" onclick="closeNote(this)">▲ close</div>
        </div>
      </div>
      <span class="entry-actions">
        <button class="action-btn edit-btn" onclick="editAll(this,'<?= htmlspecialchars(addslashes($vt['file'])) ?>','<?= htmlspecialchars($vt['time']) ?>','<?= htmlspecialchars($vt['date'] ?? '') ?>',false)" title="Edit">✎</button>
        <button class="action-btn complete-btn" onclick="completeVoice('<?= htmlspecialchars(addslashes($vt['file'])) ?>','<?= htmlspecialchars($vt['time']) ?>')" title="<?= $is_done ? 'Undo' : 'Complete' ?>"><?= $is_done ? '↩' : '✓' ?></button>
        <button class="action-btn push-item-btn" onclick="pushToServer(this)" title="Push">↑</button>
        <button class="action-btn delete-btn" onclick="deleteVoice('<?= htmlspecialchars(addslashes($vt['file'])) ?>','<?= htmlspecialchars($vt['time']) ?>')">×</button>
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
          <button class="action-btn delete-btn" onclick="deleteVoice('<?= htmlspecialchars(addslashes($vt['file'])) ?>','<?= htmlspecialchars($vt['time']) ?>')">×</button>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="add-form">
    <input type="text" id="new-task" placeholder="Add task...">
    <button onclick="addItem('task')">Add</button>
  </div>

</div>

<!-- Health Log -->
<?php if (!empty($voice_food) || !empty($voice_health) || !empty($voice_substance)): $all_sub = array_merge($voice_health, $voice_substance); ?>
<div class="section">
  <div class="section-title">Health Log</div>

  <?php if (!empty($voice_food)): ?>
    <div class="sub-title">Food</div>
    <?php $food_by_date = []; foreach ($voice_food as $e) $food_by_date[$e['date']][] = $e; krsort($food_by_date);
      foreach ($food_by_date as $date => $entries): ?>
      <div class="voice-date"><?= htmlspecialchars($date) ?></div>
      <?php foreach ($entries as $entry) echo voice_entry_html($entry, true, false, true); ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php $all_substance = array_merge($voice_health, $voice_substance);
    if (!empty($all_substance)): ?>
    <div class="sub-title sub-title-border">Substance</div>
    <?php $sbd = []; foreach ($all_substance as $e) $sbd[$e['date']][] = $e; krsort($sbd);
      foreach ($sbd as $date => $entries): ?>
      <div class="voice-date"><?= htmlspecialchars($date) ?></div>
      <?php foreach ($entries as $entry) echo voice_entry_html($entry, true, true, true); ?>
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
            <button class="action-btn edit-btn" onclick="editAll(this,'<?= $file ?>','<?= $time ?>','<?= htmlspecialchars($entry['date']) ?>',false)" title="Edit">✎</button>
            <button class="action-btn delete-btn" onclick="deleteVoice('<?= $file ?>','<?= $time ?>')" title="Delete">×</button>
          </span>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Shopping -->
<div class="section">
  <div class="section-title">Shopping</div>
  <?php foreach ($shopping as $item): ?>
    <div class="list-item <?= $item['done'] ? 'done' : '' ?>">
      <span><?= htmlspecialchars($item['text']) ?></span>
      <span class="entry-actions">
        <button class="action-btn edit-btn" onclick="editListItem(this,'shopping','<?= htmlspecialchars(addslashes($item['text'])) ?>')" title="Edit">✎</button>
        <button class="action-btn delete-btn" onclick="deleteItem('shopping','<?= htmlspecialchars(addslashes($item['text'])) ?>')">×</button>
      </span>
    </div>
  <?php endforeach; ?>
  <?php foreach ($voice_shopping as $vs): ?>
    <div class="list-item expandable-item">
      <div class="note-wrapper">
        <div class="note-summary" onclick="toggleNote(this)"><?= htmlspecialchars($vs['summary'] ?? $vs['text']) ?></div>
        <div class="note-full">
          <span><?= htmlspecialchars($vs['text']) ?></span>
          <div class="note-close" onclick="closeNote(this)">▲ close</div>
        </div>
      </div>
      <span class="entry-actions">
        <button class="action-btn edit-btn" onclick="editAll(this,'<?= htmlspecialchars(addslashes($vs['file'])) ?>','<?= htmlspecialchars($vs['time']) ?>','<?= htmlspecialchars($vs['date']) ?>',false)" title="Edit">✎</button>
        <button class="action-btn delete-btn" onclick="deleteVoice('<?= htmlspecialchars(addslashes($vs['file'])) ?>','<?= htmlspecialchars($vs['time']) ?>')">×</button>
      </span>
    </div>
  <?php endforeach; ?>
  <?php if (!empty($voice_shopping_scheduled)): ?>
    <div class="toggle" onclick="this.nextElementSibling.classList.toggle('open')">▶ Scheduled (<?= count($voice_shopping_scheduled) ?>)</div>
    <div class="toggle-content">
      <?php foreach ($voice_shopping_scheduled as $vs): ?>
        <div class="list-item">
          <span class="editable" onclick="editVoice(this,'<?= htmlspecialchars(addslashes($vs['file'])) ?>','<?= htmlspecialchars($vs['time']) ?>')"><?= htmlspecialchars($vs['text']) ?></span>
          <span class="scheduled-date"><?= htmlspecialchars($vs['show_after']) ?></span>
          <button class="action-btn delete-btn" onclick="deleteVoice('<?= htmlspecialchars(addslashes($vs['file'])) ?>','<?= htmlspecialchars($vs['time']) ?>')">×</button>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="add-form">
    <input type="text" id="new-shopping" placeholder="Add item...">
    <button onclick="addItem('shopping')">Add</button>
  </div>
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


function toggleNote(el) {
  const wrapper = el.closest('.note-wrapper');
  const summary = wrapper.querySelector('.note-summary');
  const full = wrapper.querySelector('.note-full');
  const isOpen = full.classList.contains('open');
  if (isOpen) {
    full.classList.remove('open');
    summary.style.display = '';
  } else {
    full.classList.add('open');
    summary.style.display = 'none';
  }
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

function editListItem(btn, type, oldText) {
  const entry = btn.closest('.list-item');
  if (entry.querySelector('.inline-edit')) return;
  const originalHTML = entry.innerHTML;

  const form = document.createElement('div');
  form.className = 'inline-edit';
  form.innerHTML = '<textarea class="inline-textarea">' + oldText.replace(/</g,'&lt;') + '</textarea>' +
    '<div class="inline-edit-actions"><button class="inline-save">Save</button><button class="inline-cancel">Cancel</button></div>';

  entry.innerHTML = '';
  entry.appendChild(form);
  const textarea = form.querySelector('.inline-textarea');
  textarea.style.height = Math.max(50, textarea.scrollHeight) + 'px';
  textarea.focus();

  form.querySelector('.inline-cancel').onclick = () => { entry.innerHTML = originalHTML; };
  form.querySelector('.inline-save').onclick = () => {
    const newText = textarea.value.trim();
    if (newText && newText !== oldText) {
      const f = new FormData();
      f.append('old_text', oldText);
      f.append('new_text', newText);
      fetch('?action=edit_' + type, { method: 'POST', body: f }).then(() => location.reload());
    } else {
      entry.innerHTML = originalHTML;
    }
  };
  textarea.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.querySelector('.inline-save').click(); }
    if (e.key === 'Escape') entry.innerHTML = originalHTML;
  });
}

function editAll(btn, file, time, date, showDateEdit) {
  const entry = btn.closest('.voice-entry') || btn.closest('.list-item');
  if (entry.querySelector('.inline-edit')) return;
  const textEl = entry.querySelector('.voice-text, .note-full span, .note-summary');
  const originalText = textEl ? textEl.textContent : '';
  const originalHTML = entry.innerHTML;

  const form = document.createElement('div');
  form.className = 'inline-edit';
  let html = '';
  if (showDateEdit) {
    html += '<div class="inline-edit-row">';
    html += '<input type="date" class="inline-date" value="' + date + '">';
    html += '<input type="time" class="inline-time" value="' + time + '">';
    html += '</div>';
  }
  html += '<textarea class="inline-textarea">' + originalText.replace(/</g,'&lt;') + '</textarea>';
  html += '<div class="inline-edit-actions">';
  html += '<button class="inline-save">Save</button>';
  html += '<button class="inline-cancel">Cancel</button>';
  html += '</div>';
  form.innerHTML = html;

  entry.innerHTML = '';
  entry.appendChild(form);
  const textarea = form.querySelector('.inline-textarea');
  textarea.style.height = Math.max(60, textarea.scrollHeight) + 'px';
  textarea.focus();

  form.querySelector('.inline-cancel').onclick = () => { entry.innerHTML = originalHTML; };
  form.querySelector('.inline-save').onclick = () => {
    const newText = textarea.value.trim();
    const promises = [];
    if (newText && newText !== originalText) {
      const f = new FormData();
      f.append('file', file); f.append('time', time); f.append('new_text', newText);
      promises.push(fetch('?action=edit_voice', { method: 'POST', body: f }));
    }
    if (showDateEdit) {
      const nd = form.querySelector('.inline-date').value;
      const nt = form.querySelector('.inline-time').value;
      if (nd !== date || nt !== time) {
        const f2 = new FormData();
        f2.append('file', file); f2.append('time', time); f2.append('new_date', nd); f2.append('new_time', nt);
        promises.push(fetch('?action=edit_voice_meta', { method: 'POST', body: f2 }));
      }
    }
    if (promises.length > 0) Promise.all(promises).then(() => location.reload());
    else entry.innerHTML = originalHTML;
  };
  textarea.addEventListener('keydown', e => { if (e.key === 'Escape') entry.innerHTML = originalHTML; });
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
