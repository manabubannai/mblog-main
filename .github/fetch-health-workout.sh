#!/bin/bash
# fetch-health-workout.sh — Health Auto Export の iCloud JSON からワークアウトデータを取得
# 出力先: .github/health-workout-today.txt

set -e

BLOG_DIR="/Users/manabu/mblog-main"
OUTPUT="$BLOG_DIR/.github/health-workout-today.txt"
DATE=${1:-$(TZ=Asia/Bangkok date '+%Y-%m-%d')}

# iCloud Drive のパス（Workouts と Metrics は別フォルダ）
ICLOUD_WORKOUTS="$HOME/Library/Mobile Documents/iCloud~com~ifunography~HealthExport/Documents/Health Export for Workouts"
ICLOUD_METRICS="$HOME/Library/Mobile Documents/iCloud~com~ifunography~HealthExport/Documents/Health Export for Step"
WORKOUT_FILE="$ICLOUD_WORKOUTS/HealthAutoExport-${DATE}.json"
METRICS_FILE="$ICLOUD_METRICS/HealthAutoExport-${DATE}.json"

if [ ! -f "$WORKOUT_FILE" ] && [ ! -f "$METRICS_FILE" ]; then
  echo "No Health Export JSON found for $DATE" >&2
  echo "— No data" > "$OUTPUT"
  exit 0
fi

python3 - "$WORKOUT_FILE" "$METRICS_FILE" "$OUTPUT" "$DATE" << 'PYEOF'
import json, sys, os

workout_file = sys.argv[1]
metrics_file = sys.argv[2]

# Load workouts
workout_data = {}
if os.path.exists(workout_file):
    with open(workout_file) as f:
        workout_data = json.load(f)

# Load metrics
metrics_data = {}
if os.path.exists(metrics_file):
    with open(metrics_file) as f:
        metrics_data = json.load(f)
output = sys.argv[3]
target_date = sys.argv[4]

# Step count from metrics
metrics = metrics_data.get("data", {}).get("metrics", [])
steps = 0
for m in metrics:
    if m.get("name") == "step_count":
        steps = int(sum(e.get("qty", 0) for e in m.get("data", [])))

# "Other"は重複データなので除外。Flexibilityはストレッチとして使う
EXCLUDE = {"other"}
all_workouts = workout_data.get("data", {}).get("workouts", [])
workouts = [w for w in all_workouts if w.get("name", "").lower() not in EXCLUDE]
lines = [f"DATE={target_date}", f"STEPS={steps}", "", "--- FORMATTED ---", "■ Workout (Apple Health)"]

if not workouts:
    lines.append("—")
else:
    for w in workouts:
        name = w.get("name", "Unknown")
        start = w.get("start", "")[11:16]
        end = w.get("end", "")[11:16]
        dur_sec = w.get("duration", 0)
        dur_min = int(dur_sec / 60) if dur_sec else 0
        h = dur_min // 60
        m = dur_min % 60
        dur_str = f"{h}h{m:02d}m" if h else f"{m}min"

        # Active energy (sum of kJ entries, convert to kcal)
        ae = w.get("activeEnergy", [])
        if isinstance(ae, list):
            total_kj = sum(e.get("qty", 0) for e in ae)
            total_kcal = int(total_kj / 4.184)
        else:
            total_kcal = 0

        # Avg heart rate
        avg_hr = int(w.get("avgHeartRate", {}).get("qty", 0)) if isinstance(w.get("avgHeartRate"), dict) else 0

        hr_str = f" / Avg HR:{avg_hr}bpm" if avg_hr else ""
        cal_str = f" / {total_kcal}kcal" if total_kcal else ""
        lines.append(f"- {name}: {start}〜{end}（{dur_str}）{hr_str}{cal_str}")

if steps:
    lines.append("")
    lines.append(f"STEPS_LOG=Steps: {steps:,}")

with open(output, "w") as f:
    f.write("\n".join(lines) + "\n")
PYEOF

echo "Health workout data saved to $OUTPUT for $DATE"
