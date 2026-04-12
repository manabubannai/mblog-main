#!/bin/bash
# fetch-oura-full.sh — Oura Ring API v2 の全エンドポイントを取得し、
# .github/oura-data.json に差分マージ保存する（/oura ページ用）
#
# トークン取得順:
#   1. 環境変数 OURA_TOKEN（GitHub Actions から）
#   2. $BLOG_DIR/.env の OURA_TOKEN（ローカル実行）
#
# 差分更新ロジック:
#   既存 JSON があれば、日付レンジの endpoint は (最終取得日 - 2 日) 〜 today を取得してマージ。
#   初回実行時は START_DATE（デフォルト 2018-01-01）〜 today を全取得。

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BLOG_DIR="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$BLOG_DIR/.env"
JSON_OUTPUT="$SCRIPT_DIR/oura-data.json"
START_DATE="${OURA_START_DATE:-2018-01-01}"

# トークン読み込み（env > .env の順）
if [ -z "$OURA_TOKEN" ] && [ -f "$ENV_FILE" ]; then
  # shellcheck disable=SC1090
  source "$ENV_FILE"
fi

if [ -z "$OURA_TOKEN" ]; then
  echo "OURA_TOKEN not set (env or $ENV_FILE)" >&2
  exit 1
fi

python3 - "$JSON_OUTPUT" "$START_DATE" << 'PYEOF'
import json, os, sys, urllib.request, urllib.parse, urllib.error
from datetime import datetime, timezone, timedelta

json_output = sys.argv[1]
start_date_default = sys.argv[2]
token = os.environ["OURA_TOKEN"]

BASE = "https://api.ouraring.com/v2/usercollection"
TZ = timezone(timedelta(hours=7))  # Asia/Bangkok
today = datetime.now(TZ).date()
today_str = today.isoformat()

def api_get(path, params=None):
    url = f"{BASE}/{path}"
    if params:
        url += "?" + urllib.parse.urlencode(params)
    req = urllib.request.Request(url, headers={"Authorization": f"Bearer {token}"})
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            return json.loads(resp.read())
    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8", errors="replace")
        print(f"HTTP {e.code} on {path}: {body}", file=sys.stderr)
        return None
    except Exception as e:
        print(f"Error on {path}: {e}", file=sys.stderr)
        return None

def paginate(path, params):
    """Follow next_token until exhausted; return merged list of `data`."""
    out = []
    p = dict(params)
    while True:
        resp = api_get(path, p)
        if not resp:
            break
        out.extend(resp.get("data", []) or [])
        nxt = resp.get("next_token")
        if not nxt:
            break
        p["next_token"] = nxt
    return out

# 既存データ読み込み
existing = {}
if os.path.exists(json_output):
    try:
        with open(json_output) as f:
            existing = json.load(f)
    except Exception:
        existing = {}

def last_day_in(section):
    """Return the max 'day' stored for a section (string YYYY-MM-DD), or None."""
    d = existing.get(section) or {}
    if not isinstance(d, dict) or not d:
        return None
    try:
        return max(d.keys())
    except Exception:
        return None

def day_range_for(section):
    last = last_day_in(section)
    if last:
        # 2日前から再取得（遅延到着データ対策）
        start = (datetime.fromisoformat(last).date() - timedelta(days=2)).isoformat()
    else:
        start = start_date_default
    return start, today_str

# --- 1) 日付キーで保存するエンドポイント（1件/日） ---
DAILY_ENDPOINTS = [
    "daily_activity",
    "daily_sleep",
    "daily_readiness",
    "daily_spo2",
    "daily_stress",
    "daily_resilience",
    "daily_cardiovascular_age",
    "vO2_max",
]

# --- 2) 日付キー・配列で保存（1日に複数件ありうる） ---
DAY_LIST_ENDPOINTS = [
    "sleep",          # セッションが分割される場合がある
    "sleep_time",     # 推奨就寝時刻
    "workout",
    "session",
    "enhanced_tag",
    "rest_mode_period",
]

# --- 3) 単発（日付レンジ不要） ---
SINGLE_ENDPOINTS = [
    "personal_info",
    "ring_configuration",
]

result = dict(existing)  # 既存を下敷きに上書きマージ
result["fetched_at"] = datetime.now(TZ).isoformat()
result.setdefault("last_sync", {})

# 単発データ
for ep in SINGLE_ENDPOINTS:
    resp = api_get(ep)
    if resp is not None:
        # personal_info は dict を直接返す、ring_configuration は {data: [...]} 形式
        result[ep] = resp
        result["last_sync"][ep] = today_str

# 日付キー（1件/日）
for ep in DAILY_ENDPOINTS:
    start, end = day_range_for(ep)
    items = paginate(ep, {"start_date": start, "end_date": end})
    if items is None:
        continue
    bucket = result.get(ep)
    if not isinstance(bucket, dict):
        bucket = {}
    for item in items:
        day = item.get("day") or item.get("timestamp", "")[:10]
        if day:
            bucket[day] = item
    result[ep] = bucket
    result["last_sync"][ep] = today_str

# 日付キー・配列
for ep in DAY_LIST_ENDPOINTS:
    start, end = day_range_for(ep)
    # session / workout / enhanced_tag は end_date が排他的な場合があるので +1 日
    end_exclusive = (datetime.fromisoformat(end).date() + timedelta(days=1)).isoformat()
    items = paginate(ep, {"start_date": start, "end_date": end_exclusive})
    if items is None:
        continue
    bucket = result.get(ep)
    if not isinstance(bucket, dict):
        bucket = {}
    # 再取得範囲の日付は一旦クリアして新しいデータで置き換え
    for k in list(bucket.keys()):
        if k >= start:
            del bucket[k]
    for item in items:
        day = (
            item.get("day")
            or (item.get("start_datetime") or item.get("bedtime_start") or "")[:10]
        )
        if not day:
            continue
        bucket.setdefault(day, []).append(item)
    result[ep] = bucket
    result["last_sync"][ep] = today_str

# --- 4) heartrate（datetime レンジ、容量が大きいので直近のみ） ---
# 初回のみ START_DATE から、それ以降は last_sync の 1 日前からインクリメンタル
hr_last = result.get("last_sync", {}).get("heartrate")
if hr_last:
    hr_start_dt = (datetime.fromisoformat(hr_last) - timedelta(days=1))
else:
    # 初回は容量対策として直近 30 日のみ
    hr_start_dt = datetime.now(TZ) - timedelta(days=30)
hr_end_dt = datetime.now(TZ)
hr_items = paginate("heartrate", {
    "start_datetime": hr_start_dt.strftime("%Y-%m-%dT%H:%M:%S%z")[:-2] + ":" + hr_start_dt.strftime("%z")[-2:],
    "end_datetime": hr_end_dt.strftime("%Y-%m-%dT%H:%M:%S%z")[:-2] + ":" + hr_end_dt.strftime("%z")[-2:],
})
hr_bucket = result.get("heartrate")
if not isinstance(hr_bucket, dict):
    hr_bucket = {}
# 再取得範囲の日付を削除してから追記
hr_start_day = hr_start_dt.date().isoformat()
for k in list(hr_bucket.keys()):
    if k >= hr_start_day:
        del hr_bucket[k]
for item in hr_items or []:
    ts = item.get("timestamp", "")
    day = ts[:10]
    if day:
        hr_bucket.setdefault(day, []).append(item)
result["heartrate"] = hr_bucket
result["last_sync"]["heartrate"] = today_str

# 保存
with open(json_output, "w") as f:
    json.dump(result, f, indent=2, ensure_ascii=False, sort_keys=True)

# サマリ
sizes = {k: (len(v) if isinstance(v, dict) else 1) for k, v in result.items() if k not in ("fetched_at", "last_sync")}
print(f"Oura full data saved to {json_output}")
print(f"Sections: {sizes}")
PYEOF

echo "Done."
