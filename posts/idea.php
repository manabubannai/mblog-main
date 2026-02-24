<?php
// ============================================================
// アイデア帳 — mblog.com/idea
// ============================================================
$EDIT_TOKEN     = getenv('IDEA_EDIT_TOKEN')     ?: '4b2822e1085294169d23fc1257bab998';
$SNAPSHOT_TOKEN = getenv('IDEA_SNAPSHOT_TOKEN') ?: 'bb54f8454f9e61fd';
$IDEAS_FILE     = dirname(__DIR__) . '/data/ideas.json';

// 初期データ（初回のみ）
$DEFAULT_ITEMS = [
    ['id'=>'1','text'=>'Cannabis×瞑想でHRV計測してみた。過去最悪のデータが出た話。','status'=>'編集中','note'=>'全8回のデータあり。胃痛が交絡因子として判明。','created_at'=>'2026-02-22','updated_at'=>'2026-02-24'],
    ['id'=>'2','text'=>'OpenClawってなに？何がすごいの？','status'=>'構想中','note'=>'ユースケースリスト作成済み。','created_at'=>'2026-02-18','updated_at'=>'2026-02-23'],
    ['id'=>'3','text'=>'あなたのAIを「最高の栄養士パートナー」にする方法','status'=>'構想中','note'=>'','created_at'=>'2026-02-20','updated_at'=>'2026-02-20'],
    ['id'=>'4','text'=>'Claude Code × Telegram連携でiPhoneからプログラミングする方法','status'=>'構想中','note'=>'','created_at'=>'2026-02-21','updated_at'=>'2026-02-21'],
    ['id'=>'5','text'=>'大麻と健康について正直に書く','status'=>'構想中','note'=>'','created_at'=>'2026-02-22','updated_at'=>'2026-02-22'],
];

// データディレクトリ初期化
function initData($file, $defaults) {
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!file_exists($file)) {
        file_put_contents($file, json_encode(
            ['items' => $defaults, 'updated_at' => date('Y-m-d H:i:s')],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ));
    }
}

function loadIdeas($file) {
    $data = json_decode(file_get_contents($file), true);
    return $data['items'] ?? [];
}

function saveIdeas($file, $items) {
    file_put_contents($file, json_encode(
        ['items' => $items, 'updated_at' => date('Y-m-d H:i:s')],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    ));
}

initData($IDEAS_FILE, $DEFAULT_ITEMS);

$isEdit     = isset($_GET['edit'])     && $_GET['edit']     === $EDIT_TOKEN;
$isSnapshot = isset($_GET['snapshot']) && $_GET['snapshot'] === $SNAPSHOT_TOKEN;

// ── スナップショット API ───────────────────────────────────────
if ($isSnapshot) {
    header('Content-Type: application/json; charset=utf-8');
    $items = loadIdeas($IDEAS_FILE);
    echo json_encode([
        'items'        => $items,
        'generated_at' => date('Y-m-d H:i:s'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ── 保存 (AJAX POST) ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isEdit) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['items'])) {
        saveIdeas($IDEAS_FILE, $input['items']);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }
}

$items    = loadIdeas($IDEAS_FILE);
$editUrl  = '?edit=' . urlencode($EDIT_TOKEN);
$statusList = ['編集中', '構想中', '完了'];

$page_title       = 'マナブのアイデア帳';
$page_description = 'Manabuのアイデア・思考・下書きを全公開するノート。';
require dirname(__DIR__) . '/header.php';
?>

<style>
.idea-wrap { max-width: 680px; margin: 0 auto; padding: 20px 20px 80px; }
.idea-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
.idea-header h1 { font-size: 22px; font-weight: 700; margin: 0; }
.idea-header .edit-btn { font-size: 13px; color: #888; text-decoration: none; border: 1px solid #ddd; border-radius: 6px; padding: 5px 12px; }
.idea-header .edit-btn:hover { background: #f5f5f5; }
.idea-group { margin-bottom: 40px; }
.idea-group-label { font-size: 12px; font-weight: 700; letter-spacing: .08em; color: #aaa; text-transform: uppercase; margin-bottom: 12px; }
.idea-card { background: #fff; border: 1px solid #eee; border-radius: 10px; padding: 16px 18px; margin-bottom: 10px; position: relative; }
.idea-card .idea-text { font-size: 15px; line-height: 1.6; color: #222; margin: 0 0 6px; }
.idea-card .idea-note { font-size: 13px; color: #999; margin: 0; }
.idea-card .idea-date { font-size: 11px; color: #ccc; margin-top: 8px; }
/* 編集モード */
.idea-text[contenteditable="true"],
.idea-note[contenteditable="true"] { outline: none; border-bottom: 1px dashed #ccc; cursor: text; }
.idea-text[contenteditable="true"]:focus,
.idea-note[contenteditable="true"]:focus { border-bottom-color: #555; background: rgba(255,255,200,.3); }
.idea-actions { display: none; gap: 8px; margin-top: 10px; align-items: center; }
.edit-mode .idea-actions { display: flex; }
.status-select { font-size: 12px; border: 1px solid #ddd; border-radius: 4px; padding: 2px 6px; cursor: pointer; }
.delete-btn { font-size: 12px; color: #e55; cursor: pointer; background: none; border: none; padding: 2px 6px; }
.add-card { border: 1.5px dashed #ddd; border-radius: 10px; padding: 14px 18px; margin-bottom: 10px; cursor: pointer; color: #bbb; font-size: 14px; text-align: center; display: none; }
.edit-mode .add-card { display: block; }
.add-card:hover { border-color: #999; color: #666; }
.save-toast { position: fixed; bottom: 24px; right: 24px; background: #222; color: #fff; padding: 10px 18px; border-radius: 8px; font-size: 13px; opacity: 0; transition: opacity .3s; pointer-events: none; }
.save-toast.show { opacity: 1; }
.edit-mode-bar { display: none; background: #222; color: #fff; font-size: 13px; text-align: center; padding: 8px; position: sticky; top: 0; z-index: 100; }
.edit-mode .edit-mode-bar { display: block; }
</style>

<div id="app" class="idea-wrap <?= $isEdit ? 'edit-mode' : '' ?>">

  <div class="edit-mode-bar">✏️ 編集モード — 変更は自動保存されます</div>

  <div class="idea-header">
    <h1>💡 アイデア帳</h1>
    <?php if (!$isEdit): ?>
      <a href="<?= $editUrl ?>" class="edit-btn">編集</a>
    <?php endif; ?>
  </div>

<?php
$grouped = [];
foreach ($statusList as $s) $grouped[$s] = [];
foreach ($items as $item) {
    $s = $item['status'] ?? '構想中';
    if (!isset($grouped[$s])) $grouped[$s] = [];
    $grouped[$s][] = $item;
}

foreach ($grouped as $status => $group):
    if (!$isEdit && empty($group)) continue;
?>
  <div class="idea-group" data-status="<?= htmlspecialchars($status) ?>">
    <div class="idea-group-label"><?= htmlspecialchars($status) ?> (<?= count($group) ?>)</div>

    <?php foreach ($group as $item): ?>
    <div class="idea-card" data-id="<?= htmlspecialchars($item['id']) ?>">
      <p class="idea-text" <?= $isEdit ? 'contenteditable="true"' : '' ?>><?= htmlspecialchars($item['text']) ?></p>
      <?php if ($isEdit || !empty($item['note'])): ?>
      <p class="idea-note" <?= $isEdit ? 'contenteditable="true"' : '' ?>><?= htmlspecialchars($item['note'] ?? '') ?></p>
      <?php endif; ?>
      <div class="idea-actions">
        <select class="status-select">
          <?php foreach ($statusList as $s): ?>
            <option <?= $item['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
        <button class="delete-btn" onclick="deleteCard(this)">削除</button>
      </div>
      <div class="idea-date"><?= htmlspecialchars($item['updated_at'] ?? '') ?></div>
    </div>
    <?php endforeach; ?>

    <div class="add-card" onclick="addCard('<?= htmlspecialchars($status) ?>')">＋ 追加</div>
  </div>
<?php endforeach; ?>

</div>

<div class="save-toast" id="toast">💾 保存しました</div>

<?php if ($isEdit): ?>
<script>
const EDIT_URL = '?edit=<?= urlencode($EDIT_TOKEN) ?>';
let saveTimer = null;

function getItems() {
    const items = [];
    document.querySelectorAll('.idea-card').forEach(card => {
        items.push({
            id:         card.dataset.id,
            text:       card.querySelector('.idea-text').textContent.trim(),
            note:       (card.querySelector('.idea-note') || {textContent:''}).textContent.trim(),
            status:     card.querySelector('.status-select').value,
            updated_at: new Date().toISOString().slice(0,10),
            created_at: card.dataset.created || new Date().toISOString().slice(0,10),
        });
    });
    return items;
}

function scheduleSave() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(doSave, 800);
}

async function doSave() {
    const items = getItems();
    const res = await fetch(EDIT_URL, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({items}),
    });
    if (res.ok) showToast();
}

function showToast() {
    const t = document.getElementById('toast');
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2000);
}

function addCard(status) {
    const id = Date.now().toString();
    const group = [...document.querySelectorAll('.idea-group')]
        .find(g => g.dataset.status === status);
    const addBtn = group.querySelector('.add-card');
    const card = document.createElement('div');
    card.className = 'idea-card';
    card.dataset.id = id;
    card.dataset.created = new Date().toISOString().slice(0,10);
    card.innerHTML = `
      <p class="idea-text" contenteditable="true">新しいアイデア</p>
      <p class="idea-note" contenteditable="true"></p>
      <div class="idea-actions">
        <select class="status-select">
          <?php foreach ($statusList as $s): ?><option <?= $s === '構想中' ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
        </select>
        <button class="delete-btn" onclick="deleteCard(this)">削除</button>
      </div>
      <div class="idea-date">${new Date().toISOString().slice(0,10)}</div>`;
    // select the right status
    card.querySelector('.status-select').value = status;
    group.insertBefore(card, addBtn);
    card.querySelector('.idea-text').focus();
    bindCard(card);
    scheduleSave();
}

function deleteCard(btn) {
    if (!confirm('削除しますか？')) return;
    btn.closest('.idea-card').remove();
    doSave();
}

function bindCard(card) {
    card.querySelectorAll('[contenteditable]').forEach(el => {
        el.addEventListener('input', scheduleSave);
    });
    card.querySelector('.status-select').addEventListener('change', scheduleSave);
}

// bind all existing cards
document.querySelectorAll('.idea-card').forEach(bindCard);
</script>
<?php endif; ?>

<?php require dirname(__DIR__) . '/footer.php'; ?>
