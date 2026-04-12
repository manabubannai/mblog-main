<?php
$page_title = 'Withings Data (JSON)';
$page_description = 'Withings API から取得した全データの表示';
require dirname(__DIR__) . '/header.php';

$json_path = dirname(__DIR__) . '/.github/withings-data.json';
$raw = file_exists($json_path) ? file_get_contents($json_path) : null;
$data = $raw ? json_decode($raw, true) : null;

function wt_count($v) {
    if (is_array($v)) return count($v);
    return 0;
}
function wt_pretty($v) {
    return htmlspecialchars(
        json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

// Withings meastype -> (label, unit, decimals)
$MEAS_META = [
    1  => ['Weight',               'kg',   2],
    5  => ['Fat Free Mass',        'kg',   2],
    6  => ['Body Fat',             '%',    1],
    8  => ['Fat Mass',             'kg',   2],
    9  => ['Diastolic BP',         'mmHg', 0],
    10 => ['Systolic BP',          'mmHg', 0],
    11 => ['Heart Pulse',          'bpm',  0],
    54 => ['SpO2',                 '%',    1],
    71 => ['Body Temperature',     '°C',   1],
    73 => ['Skin Temperature',     '°C',   1],
    76 => ['Muscle Mass',          'kg',   2],
    77 => ['Hydration',            'kg',   2],
    88 => ['Bone Mass',            'kg',   2],
    91 => ['Pulse Wave Velocity',  'm/s',  1],
];
?>

<a href="/"><img src="/img/logo.png" alt="manablog" class="logo"></a>
<h1 class="title">Withings Data</h1>

<?php if (!$data): ?>
<p>withings-data.json が見つかりません。<code>.github/fetch-withings.sh</code> を実行してください。</p>
<?php else: ?>

<p>Fetched at: <code><?= htmlspecialchars($data['fetched_at'] ?? '—') ?></code></p>

<?php
$meas_groups  = $data['measure_all']['body']['measuregrps'] ?? [];
$activities   = $data['activity']['body']['activities']     ?? [];
$sleep_series = $data['sleep']['body']['series']            ?? [];

// Determine date range for measurement groups
$meas_range = '—';
if ($meas_groups) {
    $dates = array_map(fn($g) => $g['date'] ?? 0, $meas_groups);
    $min = min($dates); $max = max($dates);
    if ($min && $max) {
        $meas_range = date('Y-m-d', $min) . ' 〜 ' . date('Y-m-d', $max);
    }
}

// Find the latest value for each meastype (most recent measurement overall)
$latest_by_type = [];
$groups_sorted = $meas_groups;
usort($groups_sorted, fn($a, $b) => ($b['date'] ?? 0) - ($a['date'] ?? 0));
foreach ($groups_sorted as $g) {
    $date = $g['date'] ?? 0;
    foreach ($g['measures'] ?? [] as $m) {
        $t = $m['type'];
        if (!isset($latest_by_type[$t])) {
            $val = $m['value'] * (10 ** $m['unit']);
            $latest_by_type[$t] = ['value' => $val, 'date' => $date];
        }
    }
}
?>

<h2>Summary</h2>
<ul class="long_list">
  <li><strong>Body Measurement groups</strong>: <?= wt_count($meas_groups) ?> 件（<?= htmlspecialchars($meas_range) ?>）</li>
  <li><strong>Activity days</strong>: <?= wt_count($activities) ?> 日分</li>
  <li><strong>Sleep days</strong>: <?= wt_count($sleep_series) ?> 日分</li>
</ul>

<?php if ($latest_by_type): ?>
<h2>Latest Body Measurements</h2>
<ul>
<?php foreach ($MEAS_META as $type => $meta):
    if (!isset($latest_by_type[$type])) continue;
    $entry = $latest_by_type[$type];
    [$label, $unit, $decimals] = $meta;
    $value = number_format($entry['value'], $decimals);
    $when  = date('Y-m-d', $entry['date']);
?>
  <li><?= htmlspecialchars($label) ?>: <strong><?= $value ?> <?= htmlspecialchars($unit) ?></strong> <span style="color:#888; font-size:13px;">（<?= $when ?>）</span></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<h2>Sections (Raw JSON)</h2>
<p>データソース: <code>.github/withings-data.json</code>（<code>fetch-withings.sh</code> が生成）。各セクションをクリックで展開。</p>

<?php
$sections = [
    'measure_today' => ['Measure Today',          $data['measure_today'] ?? null, null],
    'measure_all'   => ['Body Measurements (All)', $data['measure_all']   ?? null, wt_count($meas_groups)  . ' 件'],
    'activity'      => ['Activity',                $data['activity']      ?? null, wt_count($activities)   . ' 日分'],
    'sleep'         => ['Sleep',                   $data['sleep']         ?? null, wt_count($sleep_series) . ' 日分'],
];
foreach ($sections as $key => [$label, $v, $badge]):
    if ($v === null) continue;
?>
<details style="margin: 12px 0;">
  <summary style="cursor: pointer; padding: 8px 12px; background: #f0f0f0; outline: 1px solid rgba(210,210,210,0.8);">
    <strong><?= htmlspecialchars($label) ?></strong>
    <?php if ($badge): ?><span style="color:#888; font-size: 13px;">（<?= htmlspecialchars($badge) ?>）</span><?php endif; ?>
  </summary>
<pre><?= wt_pretty($v) ?></pre>
</details>
<?php endforeach; ?>

<h2>Full JSON</h2>
<details>
  <summary style="cursor: pointer; padding: 8px 12px; background: #f0f0f0; outline: 1px solid rgba(210,210,210,0.8);">
    <strong>全データを表示</strong>
  </summary>
<pre><?= wt_pretty($data) ?></pre>
</details>

<?php endif; ?>

<p style="margin-top: 40px;"><a href="/">← Home</a></p>

<?php require dirname(__DIR__) . '/footer.php'; ?>
