<?php
$page_title = 'Oura Ring Data (JSON)';
$page_description = 'Oura Ring API v2 から取得した全データの表示';
require dirname(__DIR__) . '/header.php';

$json_path = dirname(__DIR__) . '/.github/oura-data.json';
$raw = file_exists($json_path) ? file_get_contents($json_path) : null;
$data = $raw ? json_decode($raw, true) : null;

function oura_count($v) {
    if (is_array($v)) return count($v);
    return 0;
}
function oura_range($bucket) {
    if (!is_array($bucket) || !$bucket) return '—';
    $keys = array_keys($bucket);
    sort($keys);
    return $keys[0] . ' 〜 ' . end($keys);
}
function oura_pretty($v) {
    return htmlspecialchars(
        json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}
?>

<a href="/"><img src="/img/logo.png" alt="manablog" class="logo"></a>
<h1 class="title">Oura Ring Data</h1>

<?php if (!$data): ?>
<p>oura-data.json が見つかりません。<code>.github/fetch-oura-full.sh</code> を実行してください。</p>
<?php else: ?>

<p>Fetched at: <code><?= htmlspecialchars($data['fetched_at'] ?? '—') ?></code></p>

<?php
$sections = [
    'personal_info' => 'Personal Info',
    'ring_configuration' => 'Ring Configuration',
    'daily_sleep' => 'Daily Sleep',
    'daily_readiness' => 'Daily Readiness',
    'daily_activity' => 'Daily Activity',
    'daily_spo2' => 'Daily SpO2',
    'daily_stress' => 'Daily Stress',
    'daily_resilience' => 'Daily Resilience',
    'daily_cardiovascular_age' => 'Daily Cardiovascular Age',
    'vO2_max' => 'VO2 Max',
    'sleep' => 'Sleep (sessions)',
    'sleep_time' => 'Sleep Time',
    'workout' => 'Workout',
    'session' => 'Session',
    'enhanced_tag' => 'Enhanced Tag',
    'rest_mode_period' => 'Rest Mode Period',
    'heartrate' => 'Heart Rate',
];
?>

<h2>Summary</h2>
<ul class="long_list">
<?php foreach ($sections as $key => $label):
    if (!isset($data[$key])) continue;
    $v = $data[$key];
    if (in_array($key, ['personal_info', 'ring_configuration'], true)) {
        echo '<li><strong>' . htmlspecialchars($label) . '</strong>: 取得済み</li>';
    } else {
        $count = oura_count($v);
        $range = oura_range($v);
        echo '<li><strong>' . htmlspecialchars($label) . '</strong>: '
            . $count . ' 日分（' . htmlspecialchars($range) . '）</li>';
    }
endforeach; ?>
</ul>

<h2>Latest Daily Scores</h2>
<?php
$ds = $data['daily_sleep'] ?? [];
$dr = $data['daily_readiness'] ?? [];
$da = $data['daily_activity'] ?? [];
$latest_day = null;
foreach ([$ds, $dr, $da] as $bucket) {
    if (is_array($bucket) && $bucket) {
        $keys = array_keys($bucket);
        sort($keys);
        $last = end($keys);
        if (!$latest_day || $last > $latest_day) $latest_day = $last;
    }
}
?>
<?php if ($latest_day): ?>
<p>直近: <code><?= htmlspecialchars($latest_day) ?></code></p>
<ul>
<?php
$sleep_score = $ds[$latest_day]['score'] ?? null;
$read_score  = $dr[$latest_day]['score'] ?? null;
$act_score   = $da[$latest_day]['score'] ?? null;
$steps       = $da[$latest_day]['steps'] ?? null;
$total_cal   = $da[$latest_day]['total_calories'] ?? null;
if ($sleep_score !== null) echo '<li>Sleep Score: <strong>' . $sleep_score . '</strong></li>';
if ($read_score  !== null) echo '<li>Readiness: <strong>' . $read_score . '</strong></li>';
if ($act_score   !== null) echo '<li>Activity Score: <strong>' . $act_score . '</strong></li>';
if ($steps       !== null) echo '<li>Steps: <strong>' . number_format($steps) . '</strong></li>';
if ($total_cal   !== null) echo '<li>Total Calories: <strong>' . number_format($total_cal) . '</strong> kcal</li>';
?>
</ul>
<?php endif; ?>

<h2>Sections (Raw JSON)</h2>
<p>データソース: <code>.github/oura-data.json</code>（<code>fetch-oura-full.sh</code> が生成）。各セクションをクリックで展開。</p>

<?php foreach ($sections as $key => $label):
    if (!isset($data[$key])) continue;
    $v = $data[$key];
?>
<details style="margin: 12px 0;">
  <summary style="cursor: pointer; padding: 8px 12px; background: #f0f0f0; outline: 1px solid rgba(210,210,210,0.8);">
    <strong><?= htmlspecialchars($label) ?></strong>
    <?php if (is_array($v) && !in_array($key, ['personal_info', 'ring_configuration'], true)): ?>
      <span style="color:#888; font-size: 13px;">（<?= oura_count($v) ?> 件）</span>
    <?php endif; ?>
  </summary>
<pre><?= oura_pretty($v) ?></pre>
</details>
<?php endforeach; ?>

<h2>Full JSON</h2>
<details>
  <summary style="cursor: pointer; padding: 8px 12px; background: #f0f0f0; outline: 1px solid rgba(210,210,210,0.8);">
    <strong>全データを表示</strong>
  </summary>
<pre><?= oura_pretty($data) ?></pre>
</details>

<?php endif; ?>

<p style="margin-top: 40px;"><a href="/">← Home</a></p>

<?php require dirname(__DIR__) . '/footer.php'; ?>
