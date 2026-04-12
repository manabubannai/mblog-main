#!/bin/bash
# fetch-withings-full.sh — GitHub Actions 対応 Withings 全データ取得スクリプト
#
# 必要な環境変数:
#   WITHINGS_CLIENT_ID
#   WITHINGS_CLIENT_SECRET
#   WITHINGS_REFRESH_TOKEN
#
# 出力:
#   .github/withings-data.json     — 取得した全データ
#   $GITHUB_OUTPUT に new_refresh_token=... を追記（ローテーション後のトークン）
#   GITHUB_OUTPUT が未設定なら /tmp/withings-new-refresh-token に書き出す

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
JSON_OUTPUT="$SCRIPT_DIR/withings-data.json"

if [ -z "$WITHINGS_CLIENT_ID" ] || [ -z "$WITHINGS_CLIENT_SECRET" ] || [ -z "$WITHINGS_REFRESH_TOKEN" ]; then
  echo "WITHINGS_CLIENT_ID / WITHINGS_CLIENT_SECRET / WITHINGS_REFRESH_TOKEN must be set" >&2
  exit 1
fi

python3 - "$JSON_OUTPUT" << 'PYEOF'
import json, os, sys, time, urllib.request, urllib.parse
from datetime import datetime, timezone, timedelta

json_output = sys.argv[1]
client_id = os.environ["WITHINGS_CLIENT_ID"]
client_secret = os.environ["WITHINGS_CLIENT_SECRET"]
refresh_token = os.environ["WITHINGS_REFRESH_TOKEN"]

TZ = timezone(timedelta(hours=7))  # Asia/Bangkok

def api_post(url, data):
    encoded = urllib.parse.urlencode(data).encode()
    req = urllib.request.Request(url, data=encoded, method="POST")
    with urllib.request.urlopen(req, timeout=60) as resp:
        return json.loads(resp.read())

# 1) Refresh access token（refresh_token もローテーションされる）
refresh_resp = api_post("https://wbsapi.withings.net/v2/oauth2", {
    "action": "requesttoken",
    "grant_type": "refresh_token",
    "client_id": client_id,
    "client_secret": client_secret,
    "refresh_token": refresh_token,
})
if refresh_resp.get("status") != 0:
    print(f"Token refresh failed: {refresh_resp}", file=sys.stderr)
    sys.exit(1)

body = refresh_resp["body"]
access_token = body["access_token"]
new_refresh_token = body["refresh_token"]

# 新しい refresh_token を次のステップに引き渡す
gh_out = os.environ.get("GITHUB_OUTPUT")
if gh_out:
    with open(gh_out, "a") as f:
        f.write(f"new_refresh_token={new_refresh_token}\n")
else:
    with open("/tmp/withings-new-refresh-token", "w") as f:
        f.write(new_refresh_token)

now = datetime.now(TZ)
today_str = now.strftime("%Y-%m-%d")
year_ago = (now - timedelta(days=365)).strftime("%Y-%m-%d")

# 2) 今日の計測 (target day)
start_dt = now.replace(hour=0, minute=0, second=0, microsecond=0)
end_dt = start_dt + timedelta(days=1)
meas_today = api_post("https://wbsapi.withings.net/measure", {
    "action": "getmeas",
    "startdate": int(start_dt.timestamp()),
    "enddate": int(end_dt.timestamp()),
    "access_token": access_token,
})

# 3) 全履歴の計測
MEAS_TYPES = "1,5,6,8,9,10,11,54,71,73,76,77,88,91"
meas_all = api_post("https://wbsapi.withings.net/measure", {
    "action": "getmeas",
    "meastypes": MEAS_TYPES,
    "category": 1,
    "startdate": 0,
    "enddate": int(time.time()),
    "access_token": access_token,
})

# 4) Activity（過去 1 年）
activity = api_post("https://wbsapi.withings.net/v2/measure", {
    "action": "getactivity",
    "startdateymd": year_ago,
    "enddateymd": today_str,
    "data_fields": "steps,distance,elevation,soft,moderate,intense,active,calories,totalcalories,hr_average,hr_min,hr_max,hr_zone_0,hr_zone_1,hr_zone_2,hr_zone_3",
    "access_token": access_token,
})

# 5) Sleep Summary（過去 1 年）
sleep = api_post("https://wbsapi.withings.net/v2/sleep", {
    "action": "getsummary",
    "startdateymd": year_ago,
    "enddateymd": today_str,
    "data_fields": "total_sleep_time,light_sleep_duration,deep_sleep_duration,rem_sleep_duration,wakeup_duration,wakeup_count,durationtosleep,durationtowakeup,hr_average,hr_min,hr_max,rr_average,rr_min,rr_max,sleep_score,snoring,snoring_episode_count,breathing_disturbances_intensity",
    "access_token": access_token,
})

combined = {
    "fetched_at": now.isoformat(),
    "target_date": today_str,
    "measure_today": meas_today,
    "measure_all": meas_all,
    "activity": activity,
    "sleep": sleep,
}

with open(json_output, "w") as f:
    json.dump(combined, f, indent=2, ensure_ascii=False)

mg = meas_all.get("body", {}).get("measuregrps", [])
ad = activity.get("body", {}).get("activities", [])
sd = sleep.get("body", {}).get("series", [])
print(f"Withings data saved to {json_output}")
print(f"measure_groups={len(mg)} activity_days={len(ad)} sleep_days={len(sd)}")
PYEOF

echo "Done."
