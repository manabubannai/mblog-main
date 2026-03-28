#!/bin/bash
# backup.sh — ダッシュボード関連データの日次バックアップ
# 保存先: ~/mblog-backups/YYYY-MM-DD/

set -e

BLOG_DIR="/Users/manabu/mblog-main"
BACKUP_BASE="/Users/manabu/mblog-backups"
DATE=$(date '+%Y-%m-%d')
BACKUP_DIR="$BACKUP_BASE/$DATE"

mkdir -p "$BACKUP_DIR"

# バックアップ対象ファイル
cp "$BLOG_DIR/.github/voice-log.json" "$BACKUP_DIR/" 2>/dev/null || true
cp "$BLOG_DIR/.github/task-answers.json" "$BACKUP_DIR/" 2>/dev/null || true
cp "$BLOG_DIR/.github/task-queue.json" "$BACKUP_DIR/" 2>/dev/null || true
cp "$BLOG_DIR/CLAUDE.md" "$BACKUP_DIR/" 2>/dev/null || true
cp "$BLOG_DIR/posts/health-log.php" "$BACKUP_DIR/" 2>/dev/null || true

# 30日以上前のバックアップを自動削除
find "$BACKUP_BASE" -maxdepth 1 -type d -mtime +30 -exec rm -rf {} \; 2>/dev/null || true

echo "Backup complete: $BACKUP_DIR"
