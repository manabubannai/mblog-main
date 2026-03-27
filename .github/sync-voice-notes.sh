#!/bin/bash
# sync-voice-notes.sh — inbox.txt の新しいメモを voice-notes.php に追記

INBOX="/Users/manabu/Library/Mobile Documents/iCloud~md~obsidian/Documents/Voice Note/inbox.txt"
BLOG_DIR="/Users/manabu/mblog-main"
STATE_FILE="$BLOG_DIR/.github/voice-notes-state.txt"
POST_FILE="$BLOG_DIR/posts/voice-notes.php"

# inbox.txt が存在しなければ終了
if [ ! -f "$INBOX" ]; then
  echo "inbox.txt not found, skipping."
  exit 0
fi

# 状態ファイルがなければ作成（0行処理済み）
if [ ! -f "$STATE_FILE" ]; then
  echo "0" > "$STATE_FILE"
fi

PROCESSED=$(cat "$STATE_FILE")
TOTAL=$(wc -l < "$INBOX" | tr -d ' ')

# 新しい行がなければ終了
if [ "$TOTAL" -le "$PROCESSED" ]; then
  echo "No new notes. ($TOTAL lines, $PROCESSED processed)"
  exit 0
fi

# 新しいメモを抽出（空行はスキップ）
NEW_NOTES=$(tail -n +"$((PROCESSED + 1))" "$INBOX" | grep -v '^[[:space:]]*$')

if [ -z "$NEW_NOTES" ]; then
  # 空行のみだった場合も状態を更新
  echo "$TOTAL" > "$STATE_FILE"
  echo "Only blank lines, state updated."
  exit 0
fi

# voice-notes.php のマーカー直前に追記
DATE=$(date '+%-d %b, %Y')
DATE_ISO=$(date '+%Y-%m-%d')

# エントリをテンプファイルに書き出し
TMPFILE=$(mktemp)
echo "<div class=\"vn-entry\">" >> "$TMPFILE"
echo "<time datetime=\"$DATE_ISO\">$DATE</time>" >> "$TMPFILE"
echo "<pre>" >> "$TMPFILE"

while IFS= read -r line; do
  # HTMLエスケープ
  escaped=$(printf '%s' "$line" | sed 's/&/\&amp;/g; s/</\&lt;/g; s/>/\&gt;/g')
  echo "$escaped" >> "$TMPFILE"
done <<< "$NEW_NOTES"

echo "</pre>" >> "$TMPFILE"
echo "</div>" >> "$TMPFILE"
echo "" >> "$TMPFILE"

# マーカーの位置にエントリを挿入
perl -i -p0e "
  open(my \$fh, '<', '$TMPFILE') or die;
  my \$entry = do { local \$/; <\$fh> };
  close \$fh;
  s/<!-- VOICE_NOTES_END -->/\${entry}<!-- VOICE_NOTES_END -->/s;
" "$POST_FILE"

rm -f "$TMPFILE"

# 状態を更新
echo "$TOTAL" > "$STATE_FILE"

echo "Added $(echo "$NEW_NOTES" | wc -l | tr -d ' ') new note(s). Total processed: $TOTAL"
