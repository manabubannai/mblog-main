#!/bin/bash
# fetch-withings.sh — Withings API からデータを取得し、フォーマット済みテキストを出力
# 出力先: .github/withings-today.txt
# 前提: withings-auth.sh で初回認証済み

set -e

BLOG_DIR="/Users/manabu/mblog-main"
ENV_FILE="$BLOG_DIR/.env"
TOKEN_FILE="$BLOG_DIR/.github/.withings-tokens.json"
OUTPUT="$BLOG_DIR/.github/withings-today.txt"
JSON_OUTPUT="$BLOG_DIR/.github/withings-data.json"

# .env 読み込み
if [ ! -f "$ENV_FILE" ]; then
  echo ".env not found" >&2
  exit 1
fi
source "$ENV_FILE"

if [ -z "$WITHINGS_CLIENT_ID" ] || [ -z "$WITHINGS_CLIENT_SECRET" ]; then
  echo "WITHINGS_CLIENT_ID / WITHINGS_CLIENT_SECRET not set" >&2
  exit 1
fi

if [ ! -f "$TOKEN_FILE" ]; then
  echo "Token file not found. Run withings-auth.sh first." >&2
  exit 1
fi

# 日付（引数があればその日付、なければ今日）
DATE=${1:-$(date '+%Y-%m-%d')}

# Python でトークン更新 → データ取得 → フォーマット
python3 - "$TOKEN_FILE" "$OUTPUT" "$DATE" "$WITHINGS_CLIENT_ID" "$WITHINGS_CLIENT_SECRET" "$JSON_OUTPUT" << 'PYEOF'
import json, sys, time, urllib.request, urllib.parse
from datetime import datetime, timezone, timedelta

token_file = sys.argv[1]
output_file = sys.argv[2]
target_date = sys.argv[3]
client_id = sys.argv[4]
client_secret = sys.argv[5]
json_output_file = sys.argv[6]

def api_post(url, data):
    encoded = urllib.parse.urlencode(data).encode()
    req = urllib.request.Request(url, data=encoded, method="POST")
    with urllib.request.urlopen(req) as resp:
        return json.loads(resp.read())

# トークン読み込み
with open(token_file) as f:
    tokens = json.load(f)

# トークンをリフレッシュ（毎回実行。access_tokenは3時間で切れるため）
refresh_resp = api_post("https://wbsapi.withings.net/v2/oauth2", {
    "action": "requesttoken",
    "grant_type": "refresh_token",
    "client_id": client_id,
    "client_secret": client_secret,
    "refresh_token": tokens["refresh_token"]
})

if refresh_resp.get("status") != 0:
    print(f"Token refresh failed: {refresh_resp}", file=sys.stderr)
    sys.exit(1)

body = refresh_resp["body"]
tokens["access_token"] = body["access_token"]
tokens["refresh_token"] = body["refresh_token"]

# 新しいトークンを保存
with open(token_file, "w") as f:
    json.dump(tokens, f, indent=2)

# 対象日のUNIXタイムスタンプ（UTC）
dt = datetime.strptime(target_date, "%Y-%m-%d")
# タイ時間 (UTC+7) を想定
local_tz = timezone(timedelta(hours=7))
start_dt = dt.replace(tzinfo=local_tz)
end_dt = start_dt + timedelta(days=1)
startdate = int(start_dt.timestamp())
enddate = int(end_dt.timestamp())

# 体組成データ取得
meas_resp = api_post("https://wbsapi.withings.net/measure", {
    "action": "getmeas",
    "startdate": startdate,
    "enddate": enddate,
    "access_token": tokens["access_token"]
})

if meas_resp.get("status") != 0:
    print(f"Measure API error: {meas_resp}", file=sys.stderr)
    sys.exit(1)

groups = meas_resp.get("body", {}).get("measuregrps", [])

if not groups:
    print("NO_DATA", file=sys.stderr)
    sys.exit(1)

# meastype: 1=体重, 5=除脂肪体重, 6=体脂肪率, 76=筋肉量, 88=骨量
MEAS_NAMES = {
    1: ("Weight", "kg"),
    6: ("Body Fat", "%"),
    76: ("Muscle Mass", "kg"),
    5: ("Fat Free Mass", "kg"),
    88: ("Bone Mass", "kg"),
}

# 全グループから計測値を集約（データが複数グループに分散するため）
meas_time = datetime.fromtimestamp(groups[0]["date"], tz=local_tz).strftime("%H:%M")

results = {}
for grp in groups:
    for m in grp["measures"]:
        mtype = m["type"]
        value = m["value"] * (10 ** m["unit"])
        if mtype in MEAS_NAMES and mtype not in results:
            name, unit = MEAS_NAMES[mtype]
            results[mtype] = (name, value, unit)

# フォーマット出力
lines = [
    f"DATE={target_date}",
    f"TIME={meas_time}",
    "",
    "--- FORMATTED ---",
    f"■ Body (Withings Body Scan) — {meas_time}",
]

if 1 in results:
    lines.append(f"- Weight: {results[1][1]:.2f} {results[1][2]}")
if 76 in results:
    lines.append(f"- Muscle Mass: {results[76][1]:.2f} {results[76][2]}")
if 6 in results:
    lines.append(f"- Body Fat: {results[6][1]:.1f}{results[6][2]}")
if 88 in results:
    lines.append(f"- Bone Mass: {results[88][1]:.2f} {results[88][2]}")

with open(output_file, "w") as f:
    f.write("\n".join(lines) + "\n")

# --- 全履歴の Raw JSON 取得（/withings ページ用） ---
# meastype: 1=Weight, 5=Fat Free Mass, 6=Fat Ratio, 8=Fat Mass Weight, 76=Muscle Mass,
#           77=Hydration, 88=Bone Mass, 91=Pulse Wave Velocity, 9=Diastolic BP, 10=Systolic BP,
#           11=Heart Pulse, 54=SpO2, 71=Body Temperature, 73=Skin Temperature
MEAS_TYPES = "1,5,6,8,9,10,11,54,71,73,76,77,88,91"

all_meas_resp = api_post("https://wbsapi.withings.net/measure", {
    "action": "getmeas",
    "meastypes": MEAS_TYPES,
    "category": 1,
    "startdate": 0,
    "enddate": int(time.time()),
    "access_token": tokens["access_token"]
})

# Activity データ（歩数・消費カロリー等） 過去1年
year_ago = (datetime.now(local_tz) - timedelta(days=365)).strftime("%Y-%m-%d")
today_str = datetime.now(local_tz).strftime("%Y-%m-%d")
activity_resp = api_post("https://wbsapi.withings.net/v2/measure", {
    "action": "getactivity",
    "startdateymd": year_ago,
    "enddateymd": today_str,
    "data_fields": "steps,distance,elevation,soft,moderate,intense,active,calories,totalcalories,hr_average,hr_min,hr_max,hr_zone_0,hr_zone_1,hr_zone_2,hr_zone_3",
    "access_token": tokens["access_token"]
})

# Sleep Summary 過去1年
sleep_resp = api_post("https://wbsapi.withings.net/v2/sleep", {
    "action": "getsummary",
    "startdateymd": year_ago,
    "enddateymd": today_str,
    "data_fields": "total_sleep_time,light_sleep_duration,deep_sleep_duration,rem_sleep_duration,wakeup_duration,wakeup_count,durationtosleep,durationtowakeup,hr_average,hr_min,hr_max,rr_average,rr_min,rr_max,sleep_score,snoring,snoring_episode_count,breathing_disturbances_intensity",
    "access_token": tokens["access_token"]
})

combined = {
    "fetched_at": datetime.now(local_tz).isoformat(),
    "target_date": target_date,
    "measure_today": meas_resp,
    "measure_all": all_meas_resp,
    "activity": activity_resp,
    "sleep": sleep_resp,
}

with open(json_output_file, "w") as f:
    json.dump(combined, f, indent=2, ensure_ascii=False)

print(f"Withings data saved for {target_date}")
PYEOF

echo "Withings data saved to $OUTPUT for $DATE"
