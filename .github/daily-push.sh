#!/bin/bash
cd /Users/manabu/mblog-main

# バックアップ
bash .github/backup.sh 2>/dev/null || true

# データ取得（エラーがあっても続行）
bash .github/fetch-oura.sh 2>/dev/null || true
bash .github/fetch-withings.sh 2>/dev/null || true

# 変更がなければ何もしない
if git diff --quiet && git diff --cached --quiet && [ -z "$(git ls-files --others --exclude-standard)" ]; then
  exit 0
fi

# 変更があればcommit & push
git add -A
git commit -m "Daily update: $(date '+%Y-%m-%d')"
git push origin main
