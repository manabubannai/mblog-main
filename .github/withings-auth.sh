#!/bin/bash
# withings-auth.sh — Withings OAuth 2.0 初回認証
# 使い方:
#   1. https://developer.withings.com/ でアプリ作成
#   2. .env に WITHINGS_CLIENT_ID と WITHINGS_CLIENT_SECRET を追加
#   3. callback_uri を http://localhost:8080/callback に設定
#   4. このスクリプトを実行: bash .github/withings-auth.sh

set -e

BLOG_DIR="/Users/manabu/mblog-main"
ENV_FILE="$BLOG_DIR/.env"
TOKEN_FILE="$BLOG_DIR/.github/.withings-tokens.json"

source "$ENV_FILE"

if [ -z "$WITHINGS_CLIENT_ID" ] || [ -z "$WITHINGS_CLIENT_SECRET" ]; then
  echo "Error: .env に WITHINGS_CLIENT_ID と WITHINGS_CLIENT_SECRET を設定してください"
  exit 1
fi

REDIRECT_URI="http://localhost:8080/callback"
STATE="withings_$(date +%s)"

AUTH_URL="https://account.withings.com/oauth2_user/authorize2?response_type=code&client_id=${WITHINGS_CLIENT_ID}&redirect_uri=${REDIRECT_URI}&scope=user.metrics&state=${STATE}"

echo "ブラウザで認証ページを開きます..."
open "$AUTH_URL"

echo "認証後のリダイレクトを待機中（localhost:8080）..."

# Python で一時HTTPサーバーを起動してcallbackを受け取る
CODE=$(python3 - "$REDIRECT_URI" << 'PYEOF'
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import urlparse, parse_qs
import sys

class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        query = parse_qs(urlparse(self.path).query)
        code = query.get("code", [None])[0]
        if code:
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.end_headers()
            self.wfile.write("✅ 認証成功！このタブを閉じてください。".encode())
            print(code)
            raise SystemExit(0)
        else:
            self.send_response(400)
            self.end_headers()
            self.wfile.write(b"Error: no code")

    def log_message(self, format, *args):
        pass

try:
    server = HTTPServer(("localhost", 8080), Handler)
    server.handle_request()
except SystemExit:
    pass
PYEOF
)

if [ -z "$CODE" ]; then
  echo "Error: 認証コードを取得できませんでした"
  exit 1
fi

echo "認証コード取得成功。トークンを取得中..."

# コードをトークンに交換
RESPONSE=$(curl -s -X POST "https://wbsapi.withings.net/v2/oauth2" \
  -d "action=requesttoken" \
  -d "grant_type=authorization_code" \
  -d "client_id=${WITHINGS_CLIENT_ID}" \
  -d "client_secret=${WITHINGS_CLIENT_SECRET}" \
  -d "code=${CODE}" \
  -d "redirect_uri=${REDIRECT_URI}")

# トークンを保存
python3 - "$RESPONSE" "$TOKEN_FILE" << 'PYEOF'
import json, sys

response = json.loads(sys.argv[1])
token_file = sys.argv[2]

if response.get("status") != 0:
    print(f"Error: {response}", file=sys.stderr)
    sys.exit(1)

body = response["body"]
tokens = {
    "access_token": body["access_token"],
    "refresh_token": body["refresh_token"],
    "userid": body["userid"]
}

with open(token_file, "w") as f:
    json.dump(tokens, f, indent=2)

print(f"トークン保存完了: {token_file}")
PYEOF

echo "✅ Withings OAuth 認証完了！"
echo "   fetch-withings.sh で毎日のデータ取得が可能になりました。"
