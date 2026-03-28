<?php
$page_title = 'Apple Watch × HIIT心拍数測定方法';
$page_description = 'Apple WatchでHIITトレーニング中の心拍数を測定する方法まとめ';
require dirname(__DIR__) . '/header.php';
$answer = json_decode(file_get_contents(dirname(__DIR__) . '/.github/task-answers.json'), true)['Apple Watch × HIIT心拍数測定方法を調べる'] ?? '';
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
<h1 class="title">Apple Watch × HIIT心拍数測定方法</h1>

<pre style="line-height: 1.9;">
<?= htmlspecialchars($answer) ?>
</pre>

<pre style="line-height: 1.9; background: #f0f4e8; outline: 1px solid rgba(100,140,60,0.3);">
<strong>■ マナブの結論</strong>
✓ 自転車HIITを取り入れる
・攻める日：167bpmくらい（5分） × 3日
・攻めない日：120bpmくらい × 2日
</pre>

<p style="margin-top: 40px;"><a href="/health-log">← Health Log</a></p>

<?php require dirname(__DIR__) . '/footer.php'; ?>
