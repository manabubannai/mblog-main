<?php
$data_file = __DIR__ . '/../.github/task-answers.json';
$answers = file_exists($data_file) ? json_decode(file_get_contents($data_file), true) : [];
$q = $_GET['q'] ?? '';
$answer = $answers[$q] ?? null;

// Debug: remove after confirming
if (!$answer && isset($_GET['debug'])) {
    header('Content-Type: text/plain');
    echo "file_exists: " . (file_exists($data_file) ? 'YES' : 'NO') . "\n";
    echo "path: $data_file\n";
    echo "q: [$q]\n";
    echo "keys: " . implode(', ', array_keys($answers)) . "\n";
    exit;
}

if (!$answer) {
    $page_title = 'Task Not Found';
    $page_description = 'The requested task was not found.';
    require dirname(__DIR__) . '/header.php';
    echo '<a href="/"><img src="/img/logo.png" alt="manablog" class="logo"></a>';
    echo '<h1 class="title">Task Not Found</h1>';
    echo '<p><a href="/health-log">← Health Log</a></p>';
    require dirname(__DIR__) . '/footer.php';
    return;
}
$page_title = $q;
$page_description = $q;
require dirname(__DIR__) . '/header.php';
?>

<script>
  window.onload = function () {
    document.querySelectorAll('pre').forEach(pre => {
      const regex = /([\u3040-\u309F\u30A0-\u30FF\u4E00-\u9FFF]+)/g;
      pre.innerHTML = pre.innerHTML.replace(regex, '<span class="jp-font">$1</span>');
    });
  };
</script>

<a href="/"><img src="/img/logo.png" alt="manablog" class="logo"></a>
<h1 class="title"><?= htmlspecialchars($q) ?></h1>

<pre style="line-height: 1.9;">
<?= htmlspecialchars($answer) ?>
</pre>

<p style="margin-top: 40px;"><a href="/health-log">← Health Log</a></p>

<?php require dirname(__DIR__) . '/footer.php'; ?>
