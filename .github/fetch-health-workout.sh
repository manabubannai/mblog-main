#!/bin/bash
# fetch-health-workout.sh — Health Auto Export の iCloud JSON からワークアウトデータを取得
# 出力先: .github/health-workout-today.txt

set -e

BLOG_DIR="/Users/manabu/mblog-main"
OUTPUT="$BLOG_DIR/.github/health-workout-today.txt"
DATE=${1:-$(TZ=Asia/Bangkok date '+%Y-%m-%d')}

# iCloud Drive のパス
ICLOUD_DIR="$HOME/Library/Mobile Documents/iCloud~com~ifunography~HealthExport/Documents/New Automation"
JSON_FILE="$ICLOUD_DIR/HealthAutoExport-${DATE}.json"

if [ ! -f "$JSON_FILE" ]; then
  echo "No Health Export JSON found for $DATE" >&2
  echo "— No workout data" > "$OUTPUT"
  exit 0
fi

python3 - "$JSON_FILE" "$OUTPUT" "$DATE" << 'PYEOF'
import json, sys

json_file = sys.argv[1]
output = sys.argv[2]
target_date = sys.argv[3]

with open(json_file) as f:
    data = json.load(f)

# "Other"は重複データなので除外。Flexibilityはストレッチとして使う
EXCLUDE = {"other"}
all_workouts = data.get("data", {}).get("workouts", [])
workouts = [w for w in all_workouts if w.get("name", "").lower() not in EXCLUDE]
lines = [f"DATE={target_date}", "", "--- FORMATTED ---", "■ Workout (Apple Health)"]

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

with open(output, "w") as f:
    f.write("\n".join(lines) + "\n")
PYEOF

echo "Health workout data saved to $OUTPUT for $DATE"
