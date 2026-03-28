<?php
$page_title = 'ボイスメモを思考の宝箱に。AI学習→ログ生成';
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
<h1 class="title">ボイスメモを思考の宝箱に。AI学習→ログ生成</h1>

<pre style="line-height: 1.9;">
このボイスメモの保存のログを自分の宝箱として扱う。思考をすべて吐き出し、それをAIに読み込ませて学習させていく。これが思考の核となる部分。朝の内省とかもここにすべて書いていくのがいいと思う。それをMacのローカル上、これは絶対誰にもお見せしないファイルとして保存する。そこから部分的に抜き出していって、ヘルスログを生成していったりってことをやっていく。
</pre>

<p style="margin-top: 40px;"><a href="/health-log">&larr; Health Log</a></p>

<?php require dirname(__DIR__) . '/footer.php'; ?>
