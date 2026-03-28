<?php
$page_title = '地元の人には常に10%の上乗せで支払う';
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
<h1 class="title">地元の人には常に10%の上乗せで支払う</h1>

<pre style="line-height: 1.9;">
ローカルの人には常に10%増しで支払う。例えば515バーツと言われたら550バーツぐらいを払ってお釣りはいらないと言う。
</pre>

<p style="margin-top: 40px;"><a href="/health-log">&larr; Health Log</a></p>

<?php require dirname(__DIR__) . '/footer.php'; ?>
