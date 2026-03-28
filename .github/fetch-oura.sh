#!/bin/bash
# fetch-oura.sh — Oura Ring API からデータを取得し、フォーマット済みテキストを出力
# 出力先: .github/oura-today.txt

set -e

BLOG_DIR="/Users/manabu/mblog-main"
ENV_FILE="$BLOG_DIR/.env"
OUTPUT="$BLOG_DIR/.github/oura-today.txt"
TMPDIR=$(mktemp -d)

# .env 読み込み
if [ ! -f "$ENV_FILE" ]; then
  echo ".env not found" >&2
  exit 1
fi
source "$ENV_FILE"

if [ -z "$OURA_TOKEN" ]; then
  echo "OURA_TOKEN not set" >&2
  exit 1
fi

# 日付（引数があればその日付、なければ今日）
DATE=${1:-$(date '+%Y-%m-%d')}

# API 取得 → ファイルに保存
# sleep は前日またぎがあるため1日前から取得
PREV_DATE=$(date -j -v-1d -f "%Y-%m-%d" "$DATE" '+%Y-%m-%d')
curl -s -H "Authorization: Bearer $OURA_TOKEN" \
  "https://api.ouraring.com/v2/usercollection/sleep?start_date=$PREV_DATE&end_date=$DATE" \
  > "$TMPDIR/sleep.json"

curl -s -H "Authorization: Bearer $OURA_TOKEN" \
  "https://api.ouraring.com/v2/usercollection/daily_sleep?start_date=$DATE&end_date=$DATE" \
  > "$TMPDIR/daily_sleep.json"

curl -s -H "Authorization: Bearer $OURA_TOKEN" \
  "https://api.ouraring.com/v2/usercollection/daily_readiness?start_date=$DATE&end_date=$DATE" \
  > "$TMPDIR/readiness.json"

curl -s -H "Authorization: Bearer $OURA_TOKEN" \
  "https://api.ouraring.com/v2/usercollection/session?start_date=$DATE&end_date=$DATE" \
  > "$TMPDIR/session.json"

# Python でパース・フォーマット
python3 - "$TMPDIR" "$OUTPUT" "$DATE" << 'PYEOF'
import json, sys, os

tmpdir = sys.argv[1]
output = sys.argv[2]
target_date = sys.argv[3]

with open(f"{tmpdir}/sleep.json") as f:
    sleep_data = json.load(f)
with open(f"{tmpdir}/daily_sleep.json") as f:
    daily_sleep = json.load(f)
with open(f"{tmpdir}/readiness.json") as f:
    readiness = json.load(f)
with open(f"{tmpdir}/session.json") as f:
    session_data = json.load(f)

# 該当日のデータだけフィルタ（sleep は複数日分ある場合がある）
sleep_sessions = [x for x in sleep_data.get("data", []) if x["day"] == target_date]

if not sleep_sessions or not daily_sleep.get("data"):
    print("NO_DATA", file=sys.stderr)
    sys.exit(1)

s = sleep_sessions[0]
ds = daily_sleep["data"][0]
r = readiness["data"][0] if readiness.get("data") else None

def sec_to_hm(sec):
    h = sec // 3600
    m = (sec % 3600) // 60
    return f"{h}h{m:02d}m"

total = sec_to_hm(s["total_sleep_duration"])
deep = sec_to_hm(s["deep_sleep_duration"])
rem = sec_to_hm(s["rem_sleep_duration"])
hrv = int(s["average_hrv"])
hr = int(round(s["average_heart_rate"]))
sleep_score = ds["score"]
readiness_score = r["score"] if r else "—"

bedtime_start = s["bedtime_start"][11:16]
bedtime_end = s["bedtime_end"][11:16]

# Parse sessions (meditation, breathing, stretch)
sessions = session_data.get("data", [])
meditation_sessions = [x for x in sessions if x.get("type") == "meditation"]
stretch_sessions = [x for x in sessions if x.get("type") == "stretching" or x.get("type") == "stretch"]

def format_session(sess):
    dur = sec_to_hm(sess.get("duration", 0)) if sess.get("duration") else "—"
    start = sess.get("start_datetime", "")[11:16] if sess.get("start_datetime") else ""
    cal = sess.get("calories", 0)
    return dur, start, cal

med_line = "—"
if meditation_sessions:
    parts = []
    for ms in meditation_sessions:
        dur, start, cal = format_session(ms)
        parts.append(f"{dur}（{start}）{cal}kcal" if start else f"{dur} {cal}kcal")
    med_line = ", ".join(parts)

stretch_line = "—"
if stretch_sessions:
    parts = []
    for ss in stretch_sessions:
        dur, start, cal = format_session(ss)
        parts.append(f"{dur}（{start}）{cal}kcal" if start else f"{dur} {cal}kcal")
    stretch_line = ", ".join(parts)

lines = [
    f"DATE={s['day']}",
    f"BEDTIME_START={bedtime_start}",
    f"BEDTIME_END={bedtime_end}",
    f"TOTAL={total}",
    f"SLEEP_SCORE={sleep_score}",
    f"READINESS={readiness_score}",
    f"DEEP={deep}",
    f"REM={rem}",
    f"HRV={hrv}",
    f"HR={hr}",
    f"MEDITATION={med_line}",
    f"STRETCH={stretch_line}",
    "",
    "--- FORMATTED ---",
    f"■ Sleep (Oura Ring) — {bedtime_start}〜{bedtime_end}",
    f"- Total: {total}",
    f"- Readiness: {readiness_score}",
    f"- Sleep Score: {sleep_score}",
    f"- Deep: {deep}",
    f"- REM: {rem}",
    f"- HRV: {hrv}ms / HR: {hr}bpm",
    "",
    f"■ Stretch (Oura Ring)",
    f"- {stretch_line}",
    "",
    f"■ Meditation (Oura Ring)",
    f"- {med_line}",
    "",
    f"BEDTIME_LOG={bedtime_start} 就寝。",
    f"WAKEUP_LOG={bedtime_end} 起床（{total}）。",
]

with open(output, "w") as f:
    f.write("\n".join(lines) + "\n")
PYEOF

rm -rf "$TMPDIR"

echo "Oura data saved to $OUTPUT for $DATE"
