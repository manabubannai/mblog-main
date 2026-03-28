<?php
$page_title = '新Mac購入→OpenClawでDeFi自動運用の実験';
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
<h1 class="title">新Mac購入→OpenClawでDeFi自動運用の実験</h1>

<pre style="line-height: 1.9;">
Macの新しいPCを買ってきて、そこにOpenClawやClaude Codeといったものを入れて、さらにChromeのMetaMaskとかにお金を入れながら、DeFiで死ぬほどリスク取って急激に増やしまくる運用などなど。全部クリプトだったらブラウザ操作できれば完結できる気がする。これを新しいPC買って全権限与えてやってみるとか面白いかも。
</pre>

<p style="margin-top: 40px;"><a href="/health-log">&larr; Health Log</a></p>

<?php require dirname(__DIR__) . '/footer.php'; ?>
