<?php
$page_title = 'claude-code-telegramを安全に動かす設定メモ';
$page_description = 'Hetzner VPSにclaude-code-telegramをインストールし、セキュリティ設定した記録。非エンジニア向け。';
require dirname(__DIR__) . '/header.php';
?>

<a href="/"><img src="/img/logo.png" alt="manablog" class="logo"></a>

<div class="jp-article">
<time>23 Feb, 2026</time>
<h1 class="title">claude-code-telegramを安全に動かす設定メモ</h1>

<p><a href="https://x.com/levelsio/status/2023960543959101938" target="_blank">Levelsのツイート</a>を見て、claude-code-telegramを自分でも動かしてみた。<br>非エンジニアだけど、AIと壁打ちしながら全部やった。<br>セキュリティが心配だったので、やったことをメモしておく。</p>

<h2>前提：何を作ったか</h2>
<ul>
  <li>Hetzner VPS（月額€4.5）</li>
  <li>Nginx + PHP でブログを配信</li>
  <li>claude-code-telegramでTelegramからブログを更新</li>
</ul>

<h2>サーバー初期設定</h2>
<ul class="long_list">
  <li>Nginx + PHP-FPMをインストール</li>
  <li>Cloudflare Origin CertificateでSSL化</li>
  <li>ファイアウォールは22, 80, 443のみ開放</li>
  <li>SSHパスワード認証を無効化（鍵認証のみ）</li>
  <li>自動セキュリティアップデートを有効化</li>
</ul>

<p>ここまではVPSの基本設定。次がclaude-code-telegram固有の話。</p>

<h2>claude-code-telegramのセキュリティ</h2>

<h3>リスク</h3>
<ul>
  <li>Bot Token漏洩 → 誰でもBotを操作できる</li>
  <li>rootで動かす → サーバー全体が危険にさらされる</li>
</ul>

<h3>対策</h3>
<ul class="long_list">
  <li><strong>対策①：ALLOWED_USERSで自分だけに制限</strong><br>
    .envファイルにALLOWED_USERS=自分のTelegram IDを設定する。<br>これでBot Tokenが漏れても、自分以外は操作できない。</li>
  <li><strong>対策②：bot専用ユーザー（非root）で実行</strong><br>
    専用ユーザーを作り、そのユーザーでclaude-code-telegramを動かす。<br>万が一の侵害時にも、被害がそのユーザーの権限内に収まる。</li>
  <li><strong>対策③：ブログディレクトリのみ書込み許可</strong><br>
    bot専用ユーザーが書けるのはブログのディレクトリだけにする。<br>サーバーの他の部分には触れない。</li>
</ul>

<h3>補足：Tailscaleはこのケースでは不要</h3>
<p>前回の<a href="/how-to-set-up-openclaw">OpenClawの記事</a>ではTailscaleを使った。<br>OpenClawはWebダッシュボードがあるので、外部からのアクセス制限が必要だった。</p>

<p>claude-code-telegramはWebダッシュボードがない。<br>TelegramのBot API経由でしか通信しない（外向きのHTTPSのみ）。<br>なのでTailscaleなしでもセキュリティ的に問題ない。</p>

<h2>まとめ</h2>
<p>ソースコードはGitHubで公開しています。<br><a href="https://github.com/manabubannai/mblog-main" target="_blank">github.com/manabubannai/mblog-main</a></p>

</div>

<?php require dirname(__DIR__) . '/footer.php'; ?>
