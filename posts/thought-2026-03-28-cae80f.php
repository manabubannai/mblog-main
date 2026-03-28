<?php
$page_title = '遊び優先の思考法。ボイチャ×ゲームアプリの構想';
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
<h1 class="title">遊び優先の思考法。ボイチャ×ゲームアプリの構想</h1>

<pre style="line-height: 1.9;">
常に遊ぶことを優先で考える。そうしたら、よりその遊びを加速させるものを例えばAIで作ったり、作らなかったりしてもいいし、今日とかは本当は南米旅行の計画立てようと思ったけど、ついついAIで自分のタスクとかまあ筋トレとかを自動化するアプリの設計とか考えたら時間が経ってしまった。でも日常での思考ってもっと楽しいこと、遊ぶこと、みんなを楽しませること、そこにフォーカスし続ければ、より発想豊かになるし、面白いアプリケーションサービスが生まれ、それを通して自分たちも遊ぶことができる。例えばダラダラ雑談できるようなアプリを作るとか、なんかオセロしながらとか、大富豪しながらとか、わかんないけど。なんか3Dドットの家でこうみんなでトコトコ入っていって、でオセロに着席すると、そこで開始ボタン押せるとか、将棋とか、知らないけど。いやでもそれちょっと頭使いすぎだな、例えばまあ大富豪ぐらいがちょうどいいかな、3人ぐらいだったら。でそれでマイクオンになってて、じゃあちょっと大富豪しながら話しますって言って、そこでブラウザの音声オンにしてやるとか、そういうもの。
</pre>

<p style="margin-top: 40px;"><a href="/health-log">&larr; Health Log</a></p>

<?php require dirname(__DIR__) . '/footer.php'; ?>
