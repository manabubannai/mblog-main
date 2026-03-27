<?php
$page_title = 'Voice Notes — manablog';
$page_description = '音声メモの記録。Voice Note plugin from Obsidian.';
require dirname(__DIR__) . '/header.php';
?>

<a href="/"><img src="/img/logo.png" alt="manablog" class="logo"></a>
<h1 class="title">Voice Notes</h1>
<p style="font-size: 14px; color: #888; margin-bottom: 30px;">Obsidian Voice Note からの音声メモ。自動同期。</p>

<style>
.vn-entry { margin-bottom: 24px; }
.vn-entry time { font-size: 13px; color: #888; display: block; margin-bottom: 4px; }
.vn-entry pre { font-size: 14.5px; line-height: 1.7; padding: 16px; background: #f8f8f8; border: 1px solid #e8e8e8; white-space: pre-wrap; word-wrap: break-word; }
</style>

<div id="voice-notes">
<div class="vn-entry">
<time datetime="2026-03-27">27 Mar, 2026</time>
<pre>
## 27 Mar 2026 at 18:07
テストとしてまたまた音声を保存しています。テストです。
## 27 Mar 2026 at 18:34
半に卵を3つ、夕飯に卵を3つ
</pre>
</div>

<div class="vn-entry">
<time datetime="2026-03-27">27 Mar, 2026</time>
<pre>
半に卵を3つ、夕飯に卵を3つ
## 27 Mar 2026 at 19:42
タイマーサティバを0.6グラム位。タイマーじゃなくて大麻
## 27 Mar 2026 at 19:50
自分はポジションが取れていないどの立場でやっていくのか。例えばブライアン・ジョンソンだったら、健康にマジで特化しているので、健康+それに必要なAIと言う軸だから、AI単体で発信しているのではなく、あくまで自分は健康に興味がありますと言うスタンス。
## 27 Mar 2026 at 19:51
。
## 27 Mar 2026 at 20:01
残りの味噌汁を半分ぐらい食べる。ちなみに残りの味噌汁の意味がわからない場合は、過去のヘルスログを見てみると理解できると思います。昨日か一昨日に作った味噌汁です。
</pre>
</div>

<!-- VOICE_NOTES_END -->
</div>

<?php require dirname(__DIR__) . '/footer.php'; ?>
