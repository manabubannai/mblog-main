#!/bin/bash
# fetch-apple-health.sh — Health Auto Export (iOS) の iCloud Drive JSON を読み取りフォーマット出力
# 前提: Health Auto Export Pro が iCloud Drive に日次JSONを出力している
# 出力先: .github/apple-health-today.txt

set -e

BLOG_DIR="/Users/manabu/mblog-main"
OUTPUT="$BLOG_DIR/.github/apple-health-today.txt"
DATE=${1:-$(TZ=Asia/Bangkok date '+%Y-%m-%d')}

# iCloud Drive のパス（アプリインストール後に確認して修正）
ICLOUD_BASE="$HOME/Library/Mobile Documents/iCloud~com~larkinator~HealthAutoExport/Documents"
# 代替パス（アプリが iCloud Drive 直下に出力する場合）
ICLOUD_ALT="$HOME/Library/Mobile Documents/com~apple~CloudDocs/HealthAutoExport"

# JSONファイルを探す
JSON_FILE=""
for dir in "$ICLOUD_BASE" "$ICLOUD_ALT"; do
  if [ -d "$dir" ]; then
    # 日付を含むファイルを探す
    found=$(find "$dir" -name "*${DATE}*" -name "*.json" 2>/dev/null | head -1)
    if [ -n "$found" ]; then
      JSON_FILE="$found"
      break
    fi
    # 固定名ファイル（最新）
    found=$(find "$dir" -name "*.json" -newer "$dir" 2>/dev/null | sort -r | head -1)
    if [ -n "$found" ]; then
      JSON_FILE="$found"
      break
    fi
  fi
done

if [ -z "$JSON_FILE" ]; then
  echo "No Health Auto Export JSON found for $DATE" >&2
  echo "NO_DATA" > "$OUTPUT"
  exit 0
fi

python3 - "$JSON_FILE" "$OUTPUT" "$DATE" << 'PYEOF'
import json, sys

json_file = sys.argv[1]
output = sys.argv[2]
target_date = sys.argv[3]

with open(json_file) as f:
    data = json.load(f)

metrics = {}
if "data" in data and "metrics" in data["data"]:
    for m in data["data"]["metrics"]:
        name = m.get("name", "")
        entries = m.get("data", [])
        # Filter by target date
        day_entries = [e for e in entries if target_date in e.get("date", "")]
        if day_entries:
            if name in ("step_count", "active_energy"):
                metrics[name] = sum(e.get("qty", 0) for e in day_entries)
            elif name in ("heart_rate", "resting_heart_rate", "heart_rate_variability"):
                vals = [e.get("qty", 0) for e in day_entries if e.get("qty")]
                metrics[name] = round(sum(vals) / len(vals), 1) if vals else 0
            elif name in ("weight_body_mass", "body_fat_percentage"):
                metrics[name] = day_entries[-1].get("qty", 0)
            else:
                metrics[name] = day_entries[-1].get("qty", 0)

workouts = []
if "data" in data and "workouts" in data["data"]:
    for w in data["data"]["workouts"]:
        if target_date in w.get("start", ""):
            workouts.append(w)

lines = [f"DATE={target_date}", ""]

if metrics:
    lines.append("--- METRICS ---")
    for k, v in sorted(metrics.items()):
        lines.append(f"{k}={v}")
    lines.append("")

lines.append("--- FORMATTED ---")
steps = int(metrics.get("step_count", 0))
active_cal = int(metrics.get("active_energy", 0))
rhr = int(metrics.get("resting_heart_rate", 0))
hrv = int(metrics.get("heart_rate_variability", 0))

lines.append("■ Apple Health")
if steps: lines.append(f"- Steps: {steps:,}")
if active_cal: lines.append(f"- Active Energy: {active_cal} kcal")
if rhr: lines.append(f"- Resting HR: {rhr} bpm")
if hrv: lines.append(f"- HRV: {hrv} ms")

if workouts:
    lines.append("")
    lines.append("■ Workouts (Apple Health)")
    for w in workouts:
        name = w.get("name", "Unknown")
        dur_min = int(w.get("duration", 0) / 60)
        cal = int(w.get("activeEnergy", 0))
        start = w.get("start", "")[11:16]
        lines.append(f"- {name} {start} ({dur_min}min, {cal}kcal)")

with open(output, "w") as f:
    f.write("\n".join(lines) + "\n")
PYEOF

echo "Apple Health data saved to $OUTPUT for $DATE"
