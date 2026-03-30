#!/bin/bash
# generate-daily-log.sh — 1日の行動ログを自動生成
# データソース: oura-today.txt, withings-today.txt, voice-log.json, health-log.php
# 出力: stdout（マナブの1日セクション用テキスト）

set -e
BLOG_DIR="/Users/manabu/mblog-main"
DATE=${1:-$(TZ=Asia/Bangkok date '+%Y-%m-%d')}

php -r '
$date = $argv[1];
$blog = $argv[2];

$events = [];

// Oura data
$oura = file_get_contents("$blog/.github/oura-today.txt");
if (preg_match("/BEDTIME_START=(\d+:\d+)/", $oura, $m)) $events[$m[1]] = "就寝。";
if (preg_match("/BEDTIME_END=(\d+:\d+)/", $oura, $m)) {
    $total = "";
    if (preg_match("/TOTAL=([^\n]+)/", $oura, $t)) $total = "（{$t[1]}）";
    $events[$m[1]] = "起床{$total}。";
}
if (preg_match("/STRETCH=(\d+:\d+)〜(\d+:\d+)（([^）]+)）/", $oura, $m)) {
    $events[$m[1]] = "ストレッチ開始（{$m[3]}）。";
}
if (preg_match("/MEDITATION=(\d+:\d+)〜(\d+:\d+)（([^）]+)）/", $oura, $m)) {
    $events[$m[1]] = "メディテーション開始（{$m[3]}）。";
}

// Withings data
$withings_file = "$blog/.github/withings-today.txt";
if (file_exists($withings_file)) {
    $withings = file_get_contents($withings_file);
    if (preg_match("/TIME=(\d+:\d+)/", $withings, $m) && preg_match("/Weight: ([^\n]+)/", $withings, $w)) {
        $muscle = "";
        if (preg_match("/Muscle Mass: ([^\n]+)/", $withings, $mm)) $muscle = " / 筋肉量{$mm[1]}";
        $events[$m[1]] = "Withings計測。{$w[1]}{$muscle}。";
    }
}

// Voice log food & substance entries
$voice = json_decode(file_get_contents("$blog/.github/voice-log.json"), true) ?: [];
foreach ($voice as $e) {
    if (($e["date"] ?? "") !== $date) continue;
    $time = substr($e["time"], 0, 5);
    $tag = $e["tag"] ?? "";
    $summary = $e["summary"] ?? $e["text"] ?? "";
    if ($tag === "food") {
        $events[$time] = $summary . "。";
    } elseif ($tag === "substance") {
        $events[$time] = $summary . "。";
    }
}

// Health log food entries
$health = file_get_contents("$blog/posts/health-log.php");
$header = "<h2># $date</h2>";
if (strpos($health, $header) !== false) {
    $pos = strpos($health, $header);
    $pre_start = strpos($health, "<pre>", $pos);
    $pre_end = strpos($health, "</pre>", $pre_start);
    $content = substr($health, $pre_start + 5, $pre_end - $pre_start - 5);
    // Extract food times
    if (preg_match_all("/(Breakfast|Lunch|Dinner|Post-Workout|Snack|Night) (\d+:\d+)/", $content, $meals, PREG_SET_ORDER)) {
        foreach ($meals as $meal) {
            $name_map = ["Breakfast"=>"朝食","Lunch"=>"昼食","Dinner"=>"夕食","Post-Workout"=>"筋トレ後","Snack"=>"間食","Night"=>"夜食"];
            $jp = $name_map[$meal[1]] ?? $meal[1];
            // Get the food line after this
            $meal_pos = strpos($content, $meal[0]);
            $next_line = strpos($content, "\n- ", $meal_pos);
            if ($next_line !== false) {
                $end_line = strpos($content, "\n", $next_line + 3);
                $food_line = trim(substr($content, $next_line + 3, $end_line ? $end_line - $next_line - 3 : 80));
                // Truncate if too long
                if (mb_strlen($food_line) > 60) $food_line = mb_substr($food_line, 0, 57) . "...";
                $events[$meal[2]] = "{$jp}。{$food_line}。";
            } else {
                $events[$meal[2]] = "{$jp}。";
            }
        }
    }
}

ksort($events);
$lines = [];
foreach ($events as $time => $desc) {
    $lines[] = "$time $desc";
}
echo implode("\n", $lines) . "\n";
' "$DATE" "$BLOG_DIR"
