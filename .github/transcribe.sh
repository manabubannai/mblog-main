#!/bin/bash
# Voice memo transcription script
# Just Press Record → Whisper → voice-log.json

JPR_DIR="$HOME/Library/Mobile Documents/iCloud~com~openplanetsoftware~just-press-record/Documents"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
STATE_FILE="$SCRIPT_DIR/voice-log-state.txt"
LOG_FILE="$SCRIPT_DIR/voice-log.json"
WHISPER="$HOME/Library/Python/3.9/bin/mlx_whisper"
WHISPER_MODEL="mlx-community/whisper-small-mlx"

# Load .env for HF_TOKEN
ENV_FILE="$SCRIPT_DIR/../.env"
if [ -f "$ENV_FILE" ]; then
    export $(grep -v '^#' "$ENV_FILE" | grep 'HF_TOKEN' | xargs)
fi
if [ -n "$HF_TOKEN" ]; then
    export HUGGING_FACE_HUB_TOKEN="$HF_TOKEN"
fi

# Initialize state file if not exists
[ -f "$STATE_FILE" ] || touch "$STATE_FILE"

# Initialize log file if not exists
[ -f "$LOG_FILE" ] || echo '[]' > "$LOG_FILE"

# Find new m4a files not yet processed
new_files=()
while IFS= read -r -d '' file; do
    if ! grep -qF "$file" "$STATE_FILE" 2>/dev/null; then
        new_files+=("$file")
    fi
done < <(find "$JPR_DIR" -name "*.m4a" -print0 2>/dev/null | sort -z)

if [ ${#new_files[@]} -eq 0 ]; then
    echo "No new recordings found."
    exit 0
fi

echo "Found ${#new_files[@]} new recording(s)."

for file in "${new_files[@]}"; do
    echo "Transcribing: $(basename "$file")"

    # Extract date and time from path (e.g., .../2026-03-28/14-08-26.m4a)
    dir_name=$(basename "$(dirname "$file")")   # 2026-03-28
    file_name=$(basename "$file" .m4a)          # 14-08-26
    time_formatted=$(echo "$file_name" | sed 's/-/:/g')  # 14:08:26

    # Copy to tmp (ffmpeg can't handle spaces in path)
    tmp_file="/tmp/jpr_$(basename "$file")"
    cp "$file" "$tmp_file"

    # Transcribe
    "$WHISPER" --model "$WHISPER_MODEL" --language ja --output-format txt --output-dir /tmp "$tmp_file" 2>/dev/null
    txt_file="/tmp/jpr_${file_name}.txt"
    if [ -f "$txt_file" ]; then
        text=$(cat "$txt_file")
        rm "$txt_file"
    else
        text="[transcription failed]"
    fi

    rm -f "$tmp_file"

    # Escape text for JSON
    escaped_text=$(echo "$text" | python3 -c "import sys,json; print(json.dumps(sys.stdin.read().strip()))")

    # Append to voice-log.json
    python3 -c "
import json, sys
log = json.load(open('$LOG_FILE'))
log.append({
    'date': '$dir_name',
    'time': '$time_formatted',
    'text': $escaped_text,
    'file': '$(basename "$file")'
})
json.dump(log, open('$LOG_FILE', 'w'), ensure_ascii=False, indent=2)
"

    # Mark as processed
    echo "$file" >> "$STATE_FILE"
    echo "  → Done: $text"
done

echo "Transcription complete."
