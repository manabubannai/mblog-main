#!/bin/bash
cd /Users/manabu/mblog-main

# 変更がなければ何もしない
if git diff --quiet && git diff --cached --quiet && [ -z "$(git ls-files --others --exclude-standard)" ]; then
  exit 0
fi

# 変更があればcommit & push
git add -A
git commit -m "Daily update: $(date '+%Y-%m-%d')"
git push origin main
