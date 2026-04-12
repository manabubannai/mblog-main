<?php
$page_title = 'Withings Data (JSON)';
$page_description = 'Withings API から取得した全データの JSON 表示';
require dirname(__DIR__) . '/header.php';

$json_path = dirname(__DIR__) . '/.github/withings-data.json';
$raw = file_exists($json_path) ? file_get_contents($json_path) : null;
$data = $raw ? json_decode($raw, true) : null;

function wt_count($arr) { return is_array($arr) ? count($arr) : 0; }

$fetched_at = $data['fetched_at'] ?? '—';
$meas_groups = $data['measure_all']['body']['measuregrps'] ?? [];
$activities  = $data['activity']['body']['activities'] ?? [];
$sleep_series = $data['sleep']['body']['series'] ?? [];
?>

<a href="/"><img src="/img/logo.png" alt="manablog" class="logo"></a>
<h1 class="title">Withings Data</h1>

<p>Fetched at: <code><?= htmlspecialchars($fetched_at) ?></code></p>

<ul>
  <li>Body measurement groups: <strong><?= wt_count($meas_groups) ?></strong></li>
  <li>Activity days: <strong><?= wt_count($activities) ?></strong></li>
  <li>Sleep days: <strong><?= wt_count($sleep_series) ?></strong></li>
</ul>

<p>データソース: <code>.github/withings-data.json</code>（<code>fetch-withings.sh</code> が生成）。下記は Withings API のレスポンスを丸ごと表示したもの。</p>

<?php if ($raw): ?>
<pre><?= htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
<?php else: ?>
<pre>withings-data.json が見つかりません。`.github/fetch-withings.sh` を実行してください。</pre>
<?php endif; ?>

<p style="margin-top: 40px;"><a href="/">← Home</a></p>

<?php require dirname(__DIR__) . '/footer.php'; ?>
