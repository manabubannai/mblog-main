<?php
$page_title = "Manabu's Personal Wiki";
$page_description = 'A public, transparent personal knowledge base. All health data, thoughts, and life experiments — open source. Powered by AI.';
require dirname(__DIR__) . '/header.php';
?>

<style>
.wiki-nav { margin: 20px 0; }
.wiki-nav a { color: #333; text-decoration: none; border-bottom: 1px solid #ddd; }
.wiki-nav a:hover { border-bottom-color: #333; }
.wiki-section { margin: 40px 0 20px; }
.wiki-section h2 { font-size: 18px; letter-spacing: 0.5px; }
.wiki-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin: 16px 0; }
.wiki-card { background: #f9f9f9; border-radius: 8px; padding: 16px; border: 1px solid #eee; }
.wiki-card h3 { margin: 0 0 8px; font-size: 15px; }
.wiki-card p { margin: 0; font-size: 13px; color: #666; line-height: 1.6; }
.wiki-card a { color: #333; text-decoration: none; }
.wiki-stats { display: flex; gap: 24px; flex-wrap: wrap; margin: 20px 0; }
.wiki-stat { text-align: center; }
.wiki-stat .num { font-size: 28px; font-weight: bold; }
.wiki-stat .label { font-size: 12px; color: #999; }
pre.wiki-content { white-space: pre-wrap; font-family: 'SFMono-Regular', Consolas, monospace; font-size: 13px; line-height: 1.8; background: none; border: none; padding: 0; }
</style>

<?php
// Load wiki data
$wiki_dir = $_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1'
    ? '/Users/manabu/wiki'
    : '/var/www/wiki';
$daily_dir = $wiki_dir . '/daily';

// Count entries
$daily_files = glob($daily_dir . '/*.md');
$total_days = count($daily_files);

// Get latest entry data
$latest_file = end($daily_files);
$latest_date = basename($latest_file, '.md');

// Parse latest frontmatter
$latest_content = file_get_contents($latest_file);
preg_match('/^---\s*\n(.*?)\n---/s', $latest_content, $fm_match);
$fm = [];
if (!empty($fm_match[1])) {
    foreach (explode("\n", $fm_match[1]) as $line) {
        if (strpos($line, ':') !== false) {
            list($k, $v) = explode(':', $line, 2);
            $fm[trim($k)] = trim(trim($v), '"');
        }
    }
}

// Get first entry date
$first_file = reset($daily_files);
$first_date = basename($first_file, '.md');
?>

<div class="wiki-stats">
  <div class="wiki-stat">
    <div class="num"><?= $total_days ?></div>
    <div class="label">Days Tracked</div>
  </div>
  <div class="wiki-stat">
    <div class="num"><?= $first_date ?></div>
    <div class="label">Since</div>
  </div>
  <div class="wiki-stat">
    <div class="num"><?= $latest_date ?></div>
    <div class="label">Latest Entry</div>
  </div>
  <?php if (isset($fm['sleep_score'])): ?>
  <div class="wiki-stat">
    <div class="num"><?= $fm['sleep_score'] ?></div>
    <div class="label">Sleep Score</div>
  </div>
  <?php endif; ?>
  <?php if (isset($fm['weight'])): ?>
  <div class="wiki-stat">
    <div class="num"><?= $fm['weight'] ?>kg</div>
    <div class="label">Weight</div>
  </div>
  <?php endif; ?>
</div>

<div class="wiki-section">
  <h2># Health Dashboard</h2>
  <div class="wiki-grid">
    <div class="wiki-card">
      <h3>Daily Logs</h3>
      <p>1日1ファイルのヘルスログ。Sleep, Food, Supplements, Workouts, Thoughts全てを記録。<?= $total_days ?>日分のデータ。</p>
    </div>
    <div class="wiki-card">
      <h3>Devices</h3>
      <p>Oura Ring (Sleep/HRV/Readiness)<br>Withings Body Scan (Weight/Muscle/Fat)<br>Apple Watch (Workouts/SpO2)</p>
    </div>
  </div>
</div>

<div class="wiki-section">
  <h2># Topics (横断分析)</h2>
  <div class="wiki-grid">
    <?php
    $topics = [
      'sleep' => ['Sleep Patterns', '睡眠パターン + トレンド + HRV相関'],
      'nutrition' => ['Nutrition', '栄養傾向 + BP Score + 目標達成率'],
      'body-composition' => ['Body Composition', '体組成の推移 (Weight/Muscle/Fat)'],
      'blood-tests' => ['Blood Tests', '血液検査の時系列 + 最適範囲'],
      'supplements' => ['Supplements', 'サプリプロトコル + 効果追跡'],
      'workouts' => ['Workouts', '筋トレ記録 + PR推移'],
      'substances' => ['Substances', 'Cannabis使用 + 睡眠相関'],
      'gut-health' => ['Gut Health', '腸の状態の推移'],
      'meditation' => ['Meditation', '瞑想 + HRV相関'],
    ];
    foreach ($topics as $slug => $info):
      $topic_file = $wiki_dir . '/topics/' . $slug . '.md';
      if (file_exists($topic_file)):
    ?>
    <div class="wiki-card">
      <h3><?= $info[0] ?></h3>
      <p><?= $info[1] ?></p>
    </div>
    <?php endif; endforeach; ?>
  </div>
</div>

<div class="wiki-section">
  <h2># References</h2>
  <div class="wiki-grid">
    <div class="wiki-card">
      <h3>BP Food Classification</h3>
      <p>Bulletproof Diet食材分類。Good / OK / Avoid</p>
    </div>
    <div class="wiki-card">
      <h3>Healthy Junk Menu</h3>
      <p>HJメニュー + 栄養素 + BP Score評価</p>
    </div>
    <div class="wiki-card">
      <h3>Supplement Protocol</h3>
      <p>現在のサプリスタック + 栄養素内訳</p>
    </div>
    <div class="wiki-card">
      <h3>Food Templates</h3>
      <p>食事テンプレート A/B/C + 注文ガイド</p>
    </div>
    <div class="wiki-card">
      <h3>Nutrition Targets</h3>
      <p>日次マクロ/ミクロ目標 + 計算根拠</p>
    </div>
  </div>
</div>

<div class="wiki-section">
  <h2># Thoughts</h2>
  <div class="wiki-grid">
    <?php
    $thought_files = glob($wiki_dir . '/thoughts/*.md');
    rsort($thought_files);
    foreach (array_slice($thought_files, 0, 6) as $tf):
      $thought_content = file_get_contents($tf);
      $thought_title = '';
      if (preg_match('/^# (.+)$/m', $thought_content, $tm)) {
        $thought_title = $tm[1];
      }
      $thought_date = basename($tf, '.md');
    ?>
    <div class="wiki-card">
      <h3><?= htmlspecialchars($thought_title ?: $thought_date) ?></h3>
      <p><?= $thought_date ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="wiki-section">
  <h2># Latest Daily Log (<?= $latest_date ?>)</h2>
  <pre class="wiki-content"><?php
    // Show content without frontmatter
    $display = preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $latest_content);
    echo htmlspecialchars(trim($display));
  ?></pre>
</div>

<div class="wiki-section" style="margin-top: 60px; padding-top: 20px; border-top: 1px solid #eee;">
  <p style="font-size: 13px; color: #999;">
    This wiki is 100% public. All health data, thoughts, and experiments are shared transparently.<br>
    Powered by Claude Code + Oura Ring + Withings Body Scan + Apple Watch.<br>
    Inspired by <a href="https://gist.github.com/karpathy" style="color: #666;">Andrej Karpathy's LLM Wiki</a>.
  </p>
</div>

<?php require dirname(__DIR__) . '/footer.php'; ?>
