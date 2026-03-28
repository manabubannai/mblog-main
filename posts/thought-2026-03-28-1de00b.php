<?php
$page_title = '記事：AIで脳を整理するシステム';
$page_description = $page_title;
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
<h1 class="title">記事：AIで脳を整理するシステム</h1>

<pre style="line-height: 1.9;">
感覚的な脳を整理してくれる自動システムを作りました、みたいな切り口で記事を書いても面白そう。ボイスメモに話す。そうするとMacBook ProのAIが自動解析して、アウトプットをすべて整理してくれる。例えばiPhone持ってる人はそこのボイスメモを使える。
</pre>

<p style="margin-top: 40px;"><a href="/health-log">&larr; Health Log</a></p>

<?php require dirname(__DIR__) . '/footer.php'; ?>
