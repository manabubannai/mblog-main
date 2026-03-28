<?php
$page_title = 'Task Detail';
$data_file = __DIR__ . '/../.github/completed-tasks.json';
$tasks = file_exists($data_file) ? json_decode(file_get_contents($data_file), true) : [];
$q = $_GET['q'] ?? '';
$task = null;
foreach ($tasks as $t) {
    if ($t['name'] === $q) { $task = $t; break; }
}
if (!$task) {
    $page_title = 'Task Not Found';
    $page_description = 'The requested task was not found.';
    require dirname(__DIR__) . '/header.php';
    echo '<a href="/"><img src="/img/logo.png" alt="manablog" class="logo"></a>';
    echo '<h1 class="title">Task Not Found</h1>';
    echo '<p><a href="/health-log">← Health Log</a></p>';
    require dirname(__DIR__) . '/footer.php';
    return;
}
$page_title = $task['name'];
$page_description = $task['name'];
require dirname(__DIR__) . '/header.php';
?>

<a href="/"><img src="/img/logo.png" alt="manablog" class="logo"></a>
<h1 class="title"><?= htmlspecialchars($task['name']) ?></h1>
<p style="font-family: Noto, 'Hiragino Sans', sans-serif; font-size: 13px; color: #999; margin-bottom: 30px;"><?= htmlspecialchars($task['date']) ?></p>

<pre>
<?= htmlspecialchars($task['answer']) ?>
</pre>

<p style="margin-top: 40px;"><a href="/health-log">← Health Log</a></p>

<?php require dirname(__DIR__) . '/footer.php'; ?>
