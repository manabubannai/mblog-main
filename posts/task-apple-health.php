<?php
$page_title = 'Claude Code と Apple ヘルスケアを連携する';
$page_description = 'Claude CodeとApple Healthデータを連携する方法まとめ';
require dirname(__DIR__) . '/header.php';
$answer = json_decode(file_get_contents(dirname(__DIR__) . '/.github/task-answers.json'), true)['Claude Code と Apple ヘルスケアを連携する'] ?? '';
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
<h1 class="title">Claude Code と Apple ヘルスケアを連携する</h1>

<pre style="line-height: 1.9;">
<?= htmlspecialchars($answer) ?>
</pre>

<p style="margin-top: 40px;"><a href="/health-log">← Health Log</a></p>

<?php require dirname(__DIR__) . '/footer.php'; ?>
