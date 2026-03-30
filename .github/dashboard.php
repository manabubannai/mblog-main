<?php
$claude_md_path = __DIR__ . '/../CLAUDE.md';
$claude_md = file_get_contents($claude_md_path);
$voice_log_file = __DIR__ . '/voice-log.json';
$voice_log = file_exists($voice_log_file) ? json_decode(file_get_contents($voice_log_file), true) : [];
$task_answers_file = __DIR__ . '/task-answers.json';
$task_answers = file_exists($task_answers_file) ? json_decode(file_get_contents($task_answers_file), true) : [];
$queue_file = __DIR__ . '/task-queue.json';
$task_queue = file_exists($queue_file) ? json_decode(file_get_contents($queue_file), true) : [];
$queued_pending = array_column(array_filter($task_queue, fn($q) => ($q['status'] ?? '') === 'pending'), 'task');
$queued_done = array_column(array_filter($task_queue, fn($q) => ($q['status'] ?? '') === 'done'), 'task');

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'add_task') {
        $text = trim($_POST['text'] ?? '');
        if ($text) {
            $claude_md = preg_replace('/(## タスクリスト\n)/', "$1- [ ] $text\n", $claude_md);
            file_put_contents($claude_md_path, $claude_md);
        }
        echo json_encode(['ok' => true]);
    } elseif ($action === 'complete_task' || $action === 'complete_monthly') {
        $text = $_POST['text'] ?? '';
        if (strpos($claude_md, "- [ ] $text\n") !== false) {
            $claude_md = str_replace("- [ ] $text\n", "- [x] $text\n", $claude_md);
        } else {
            $claude_md = str_replace("- [x] $text\n", "- [ ] $text\n", $claude_md);
        }
        file_put_contents($claude_md_path, $claude_md);
        echo json_encode(['ok' => true]);
    } elseif ($action === 'edit_task') {
        $old = $_POST['old_text'] ?? '';
        $new = trim($_POST['new_text'] ?? '');
        if ($old && $new) {
            $claude_md = str_replace("- [ ] $old\n", "- [ ] $new\n", $claude_md);
            $claude_md = str_replace("- [x] $old\n", "- [x] $new\n", $claude_md);
            file_put_contents($claude_md_path, $claude_md);
        }
        echo json_encode(['ok' => true]);
    } elseif ($action === 'edit_task_answer') {
        $task_name = $_POST['task_name'] ?? '';
        $new_answer = $_POST['new_answer'] ?? '';
        $source = $_POST['source'] ?? 'claude';
        if ($source === 'voice') {
            $voice_log = file_exists($voice_log_file) ? json_decode(file_get_contents($voice_log_file), true) : [];
            $file = $_POST['file'] ?? '';
            $time = $_POST['time'] ?? '';
            foreach ($voice_log as &$e) {
                if ($e['file'] === $file && $e['time'] === $time) {
                    if ($new_answer) { $e['answer'] = $new_answer; } else { unset($e['answer']); }
                    break;
                }
            }
            file_put_contents($voice_log_file, json_encode($voice_log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $old_task_name = $_POST['old_task_name'] ?? '';
            if ($old_task_name && $old_task_name !== $task_name && isset($task_answers[$old_task_name])) {
                unset($task_answers[$old_task_name]);
            }
            if ($new_answer) {
                $task_answers[$task_name] = $new_answer;
            } else {
                unset($task_answers[$task_name]);
            }
            file_put_contents($task_answers_file, json_encode($task_answers, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
        echo json_encode(['ok' => true]);
    } elseif ($action === 'edit_shopping') {
        $old = $_POST['old_text'] ?? '';
        $new = trim($_POST['new_text'] ?? '');
        if ($old && $new) {
            $claude_md = str_replace("- [ ] $old\n", "- [ ] $new\n", $claude_md);
            $claude_md = str_replace("- [x] $old\n", "- [x] $new\n", $claude_md);
            file_put_contents($claude_md_path, $claude_md);
        }
        echo json_encode(['ok' => true]);
    } elseif ($action === 'reorder_task') {
        $text = $_POST['text'] ?? '';
        $dir = $_POST['dir'] ?? ''; // 'up' or 'down'
        $source = $_POST['source'] ?? 'claude'; // 'claude' or 'voice'

        if ($source === 'voice') {
            $voice_log = file_exists($voice_log_file) ? json_decode(file_get_contents($voice_log_file), true) : [];
            // Find indices of task-tagged entries
            $task_indices = [];
            foreach ($voice_log as $i => $e) {
                if (($e['tag'] ?? '') === 'task' && empty($e['show_after'])) $task_indices[] = $i;
            }
            $current_pos = null;
            foreach ($task_indices as $pos => $idx) {
                if (($voice_log[$idx]['file'] ?? '') === ($_POST['file'] ?? '') && ($voice_log[$idx]['time'] ?? '') === ($_POST['time'] ?? '')) {
                    $current_pos = $pos;
                    break;
                }
            }
            if ($current_pos !== null) {
                $swap_pos = $dir === 'up' ? $current_pos - 1 : $current_pos + 1;
                if ($swap_pos >= 0 && $swap_pos < count($task_indices)) {
                    $a = $task_indices[$current_pos];
                    $b = $task_indices[$swap_pos];
                    $tmp = $voice_log[$a];
                    $voice_log[$a] = $voice_log[$b];
                    $voice_log[$b] = $tmp;
                    file_put_contents($voice_log_file, json_encode($voice_log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                }
            }
        } else {
            // CLAUDE.md tasks
            preg_match_all('/- \[[ x]\] (.+)\n/', $claude_md, $matches, PREG_SET_ORDER);
            $task_lines = [];
            foreach ($matches as $m) $task_lines[] = $m[0];
            $current_idx = null;
            foreach ($task_lines as $i => $line) {
                if (strpos($line, $text) !== false) { $current_idx = $i; break; }
            }
            if ($current_idx !== null) {
                $swap_idx = $dir === 'up' ? $current_idx - 1 : $current_idx + 1;
                if ($swap_idx >= 0 && $swap_idx < count($task_lines)) {
                    $tmp = $task_lines[$current_idx];
                    $task_lines[$current_idx] = $task_lines[$swap_idx];
                    $task_lines[$swap_idx] = $tmp;
                    $new_list = implode('', $task_lines);
                    $claude_md = preg_replace('/(## タスクリスト\n)((?:- \[[ x]\] .+\n)+)/', '${1}' . $new_list, $claude_md);
                    file_put_contents($claude_md_path, $claude_md);
                }
            }
        }
        echo json_encode(['ok' => true]);
    } elseif ($action === 'delete_task') {
        $text = $_POST['text'] ?? '';
        $claude_md = str_replace("- [ ] $text\n", '', $claude_md);
        $claude_md = str_replace("- [x] $text\n", '', $claude_md);
        file_put_contents($claude_md_path, $claude_md);
        echo json_encode(['ok' => true]);
    } elseif ($action === 'reorder_shopping') {
        $text = $_POST['text'] ?? '';
        $dir = $_POST['dir'] ?? '';
        $source = $_POST['source'] ?? 'claude';

        if ($source === 'voice') {
            $voice_log = file_exists($voice_log_file) ? json_decode(file_get_contents($voice_log_file), true) : [];
            $indices = [];
            foreach ($voice_log as $i => $e) {
                if (($e['tag'] ?? '') === 'shopping' && empty($e['show_after'])) $indices[] = $i;
            }
            $current_pos = null;
            foreach ($indices as $pos => $idx) {
                if (($voice_log[$idx]['file'] ?? '') === ($_POST['file'] ?? '') && ($voice_log[$idx]['time'] ?? '') === ($_POST['time'] ?? '')) {
                    $current_pos = $pos;
                    break;
                }
            }
            if ($current_pos !== null) {
                $swap_pos = $dir === 'up' ? $current_pos - 1 : $current_pos + 1;
                if ($swap_pos >= 0 && $swap_pos < count($indices)) {
                    $a = $indices[$current_pos];
                    $b = $indices[$swap_pos];
                    $tmp = $voice_log[$a];
                    $voice_log[$a] = $voice_log[$b];
                    $voice_log[$b] = $tmp;
                    file_put_contents($voice_log_file, json_encode($voice_log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                }
            }
        } else {
            preg_match('/## 買い物リスト\n([\s\S]*?)(?=\n##|\z)/', $claude_md, $shop_match);
            if (!empty($shop_match[1])) {
                preg_match_all('/- \[[ x]\] (.+)\n/', $shop_match[1], $matches, PREG_SET_ORDER);
                $lines = [];
                foreach ($matches as $m) $lines[] = $m[0];
                $current_idx = null;
                foreach ($lines as $i => $line) {
                    if (strpos($line, $text) !== false) { $current_idx = $i; break; }
                }
                if ($current_idx !== null) {
                    $swap_idx = $dir === 'up' ? $current_idx - 1 : $current_idx + 1;
                    if ($swap_idx >= 0 && $swap_idx < count($lines)) {
                        $tmp = $lines[$current_idx];
                        $lines[$current_idx] = $lines[$swap_idx];
                        $lines[$swap_idx] = $tmp;
                        $new_list = implode('', $lines);
                        $claude_md = preg_replace('/(## 買い物リスト\n)((?:- \[[ x]\] .+\n)+)/', '${1}' . $new_list, $claude_md);
                        file_put_contents($claude_md_path, $claude_md);
                    }
                }
            }
        }
        echo json_encode(['ok' => true]);
    } elseif ($action === 'add_shopping') {
        $text = trim($_POST['text'] ?? '');
        if ($text) {
            $claude_md = preg_replace('/(## 買い物リスト\n)/', "$1- [ ] $text\n", $claude_md);
            file_put_contents($claude_md_path, $claude_md);
        }
        echo json_encode(['ok' => true]);
    } elseif ($action === 'delete_shopping') {
        $text = $_POST['text'] ?? '';
        $claude_md = str_replace("- [ ] $text\n", '', $claude_md);
        $claude_md = str_replace("- [x] $text\n", '', $claude_md);
        file_put_contents($claude_md_path, $claude_md);
        echo json_encode(['ok' => true]);
    } elseif ($action === 'push') {
        // Push a specific voice-log entry to health-log.php, then remove from voice-log.json
        $file = $_POST['file'] ?? '';
        $time = $_POST['time'] ?? '';
        $voice_log = file_exists($voice_log_file) ? json_decode(file_get_contents($voice_log_file), true) : [];
        $target = null;
        $target_idx = null;
        foreach ($voice_log as $i => $e) {
            if ($e['file'] === $file && $e['time'] === $time) {
                $target = $e;
                $target_idx = $i;
                break;
            }
        }

        if ($target) {
            $health_log_path = __DIR__ . '/../posts/health-log.php';
            $health_log = file_get_contents($health_log_path);
            $date = $target['date'];
            $tag = $target['tag'] ?? '';
            $use_summary_val = $_POST['use_summary'] ?? '';
            $use_summary = $use_summary_val === '1';
            $use_both = $use_summary_val === 'both';
            $use_action = $use_summary_val === 'action';
            $action_text = $_POST['action_text'] ?? '';
            if ($use_action && $action_text) {
                $text = $action_text;
            } elseif ($use_both) {
                $text = ($target['summary'] ?? $target['text']);
            } else {
                $text = $use_summary ? ($target['summary'] ?? $target['text']) : $target['text'];
            }
            $entry_time = substr($target['time'], 0, 5);

            // Determine which section to write to
            $section = '';
            $line = '';
            // "Push with AI Answer" always goes to Thought regardless of tag
            if ($use_action && $action_text) {
                $section = '■ Thought';
                $answer = $target['answer'] ?? '';
                $slug = 'thought-' . $date . '-' . substr(md5($action_text), 0, 6);
                $page_path = __DIR__ . '/../posts/' . $slug . '.php';
                if (!file_exists($page_path)) {
                    $detail_content = ($target['text'] ?? '') . "\n\n" . $answer;
                    $page_content = "<?php\n\$page_title = " . var_export($action_text, true) . ";\n\$page_description = \$page_title;\nrequire dirname(__DIR__) . '/header.php';\n?>\n\n<script>\n  window.onload = function () {\n    document.querySelectorAll('pre').forEach(pre => {\n      const regex = /([\\u3040-\\u309F\\u30A0-\\u30FF\\u4E00-\\u9FFF]+)/g;\n      pre.innerHTML = pre.innerHTML.replace(regex, '<span class=\"jp-font\">\$1</span>');\n    });\n  };\n</script>\n\n<a href=\"/\"><img src=\"/img/logo.png\" alt=\"manablog\" class=\"logo\"></a>\n<h1 class=\"title\">" . htmlspecialchars($action_text) . "</h1>\n\n<pre style=\"line-height: 1.9;\">\n" . htmlspecialchars($detail_content) . "\n</pre>\n\n<p style=\"margin-top: 40px;\"><a href=\"/health-log\">&larr; Health Log</a></p>\n\n<?php require dirname(__DIR__) . '/footer.php'; ?>\n";
                    file_put_contents($page_path, $page_content);
                }
                $line = '- ' . $action_text . ' /' . $slug;
            } elseif ($tag === 'food') {
                $section = '■ Food';
                // Food always uses original text (not summary)
                $food_text = $target['text'] ?? $text;
                $line = $food_text;
            } elseif ($tag === 'substance' || $tag === 'health') {
                $section = '■ Substances';
                $line = '- ' . $text . '（' . $entry_time . '）';
            } elseif ($tag === 'task') {
                $section = '■ タスク';
                $line = '- ' . $text;
            } elseif ($tag === 'idea') {
                $section = '■ Idea';
                if ($use_both) {
                    $original = $target['text'] ?? '';
                    $summary = $target['summary'] ?? $text;
                    $slug = 'thought-' . $date . '-' . substr(md5($summary), 0, 6);
                    $page_path = __DIR__ . '/../posts/' . $slug . '.php';
                    if (!file_exists($page_path)) {
                        $page_content = "<?php\n\$page_title = " . var_export($summary, true) . ";\n\$page_description = \$page_title;\nrequire dirname(__DIR__) . '/header.php';\n?>\n\n<script>\n  window.onload = function () {\n    document.querySelectorAll('pre').forEach(pre => {\n      const regex = /([\\u3040-\\u309F\\u30A0-\\u30FF\\u4E00-\\u9FFF]+)/g;\n      pre.innerHTML = pre.innerHTML.replace(regex, '<span class=\"jp-font\">\$1</span>');\n    });\n  };\n</script>\n\n<a href=\"/\"><img src=\"/img/logo.png\" alt=\"manablog\" class=\"logo\"></a>\n<h1 class=\"title\">" . htmlspecialchars($summary) . "</h1>\n\n<pre style=\"line-height: 1.9;\">\n" . htmlspecialchars($original) . "\n</pre>\n\n<p style=\"margin-top: 40px;\"><a href=\"/health-log\">&larr; Health Log</a></p>\n\n<?php require dirname(__DIR__) . '/footer.php'; ?>\n";
                        file_put_contents($page_path, $page_content);
                    }
                    $line = '- ' . $summary . ' /' . $slug;
                } else {
                    $line = '- ' . $text;
                }
            } else {
                $section = '■ Thought';
                if ($use_both) {
                    $original = $target['text'] ?? '';
                    $summary = $target['summary'] ?? $text;
                    $slug = 'thought-' . $date . '-' . substr(md5($summary), 0, 6);
                    $page_path = __DIR__ . '/../posts/' . $slug . '.php';
                    if (!file_exists($page_path)) {
                        $page_content = "<?php\n\$page_title = " . var_export($summary, true) . ";\n\$page_description = \$page_title;\nrequire dirname(__DIR__) . '/header.php';\n?>\n\n<script>\n  window.onload = function () {\n    document.querySelectorAll('pre').forEach(pre => {\n      const regex = /([\\u3040-\\u309F\\u30A0-\\u30FF\\u4E00-\\u9FFF]+)/g;\n      pre.innerHTML = pre.innerHTML.replace(regex, '<span class=\"jp-font\">\$1</span>');\n    });\n  };\n</script>\n\n<a href=\"/\"><img src=\"/img/logo.png\" alt=\"manablog\" class=\"logo\"></a>\n<h1 class=\"title\">" . htmlspecialchars($summary) . "</h1>\n\n<pre style=\"line-height: 1.9;\">\n" . htmlspecialchars($original) . "\n</pre>\n\n<p style=\"margin-top: 40px;\"><a href=\"/health-log\">&larr; Health Log</a></p>\n\n<?php require dirname(__DIR__) . '/footer.php'; ?>\n";
                        file_put_contents($page_path, $page_content);
                    }
                    $line = '- ' . $summary . ' /' . $slug;
                } else {
                    $line = '- ' . $text;
                }
            }

            // Find or create date entry
            $date_header = '<h2># ' . $date . '</h2>';
            if (strpos($health_log, $date_header) !== false) {
                // Date exists — find section and append
                $date_pos = strpos($health_log, $date_header);
                $pre_start = strpos($health_log, '<pre>', $date_pos);
                $pre_end = strpos($health_log, '</pre>', $pre_start);
                $pre_content = substr($health_log, $pre_start + 5, $pre_end - $pre_start - 5);

                $section_pos = strpos($pre_content, $section);
                if ($section_pos !== false) {
                    // Section exists — find its end (next ■ or end of pre)
                    $next_section = strpos($pre_content, "\n■", $section_pos + 1);
                    if ($next_section === false) {
                        // Last section — append after last content line
                        $insert_pos = $pre_start + 5 + strlen(rtrim($pre_content));
                        $health_log = substr($health_log, 0, $insert_pos) . "\n" . $line . substr($health_log, $insert_pos);
                    } else {
                        // Insert before next section (keep exactly 1 blank line)
                        $insert_pos = $pre_start + 5 + $next_section;
                        $health_log = substr($health_log, 0, $insert_pos) . $line . "\n" . substr($health_log, $insert_pos);
                    }
                } else {
                    // Section doesn't exist — add it before </pre>
                    $insert_pos = $pre_end;
                    $health_log = substr($health_log, 0, $insert_pos) . "\n" . $section . "\n" . $line . "\n  " . substr($health_log, $insert_pos);
                }
            } else {
                // Date doesn't exist — create new entry before first existing date
                $first_h2 = strpos($health_log, '<h2>#');
                if ($first_h2 !== false) {
                    $new_entry = "    $date_header\n  <pre>\n$section\n$line\n  </pre>\n\n  <hr style=\"border: none; border-top: 0.5px solid rgba(0,0,0,0.06); margin: 50px 0 40px;\">\n\n";
                    // Try to find <hr> before first h2, if not found insert directly before h2
                    $hr_before = strrpos(substr($health_log, 0, $first_h2), '<hr');
                    if ($hr_before !== false) {
                        $health_log = substr($health_log, 0, $hr_before) . $new_entry . substr($health_log, $hr_before);
                    } else {
                        $health_log = substr($health_log, 0, $first_h2) . $new_entry . substr($health_log, $first_h2);
                    }
                }
            }

            $write_result = file_put_contents($health_log_path, $health_log);

            // Only remove from voice-log.json if health-log write succeeded
            if ($write_result !== false) {
                // Verify the content was actually written
                $verify = file_get_contents($health_log_path);
                if (strpos($verify, trim($line)) !== false || strpos($verify, $date_header) !== false) {
                    array_splice($voice_log, $target_idx, 1);
                    file_put_contents($voice_log_file, json_encode(array_values($voice_log), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                }
            }

            // Git commit & push
            chdir(__DIR__ . '/..');
            $output = shell_exec('git add -A && git commit -m "health-log: ' . $date . ' ' . $tag . '" && git push origin main 2>&1');
            echo json_encode(['ok' => true, 'output' => $output]);
        } elseif (!empty($_POST['task'])) {
            // CLAUDE.md task — push to health-log.php
            $task_text = $_POST['task'];
            $health_log_path = __DIR__ . '/../posts/health-log.php';
            $health_log = file_get_contents($health_log_path);
            $date = date('Y-m-d');
            $date_header = '<h2># ' . $date . '</h2>';

            $task_answer = $task_answers[$task_text] ?? null;
            if ($task_answer) {
                $task_line = '- <a href="/task-answers?q=' . rawurlencode($task_text) . '">' . htmlspecialchars($task_text) . '</a>';
            } else {
                $task_line = '- ' . htmlspecialchars($task_text);
            }

            // Skip if already in health-log (duplicate prevention)
            if (strpos($health_log, htmlspecialchars($task_text)) !== false) {
                $claude_md = file_get_contents($claude_md_path);
                $claude_md = str_replace("- [ ] $task_text\n", "- [x] $task_text\n", $claude_md);
                file_put_contents($claude_md_path, $claude_md);
                chdir(__DIR__ . '/..');
                $output = shell_exec('git add -A && git commit -m "health-log: ' . $date . ' task done" && git push origin main 2>&1');
                echo json_encode(['ok' => true, 'output' => $output]);
                exit;
            }

            if (strpos($health_log, $date_header) !== false) {
                $date_pos = strpos($health_log, $date_header);
                $pre_start = strpos($health_log, '<pre>', $date_pos);
                $pre_end = strpos($health_log, '</pre>', $pre_start);
                $pre_content = substr($health_log, $pre_start + 5, $pre_end - $pre_start - 5);

                // Add task to ■ タスク as a link (if answer exists) or plain text
                $section_pos = strpos($pre_content, '■ タスク');
                if ($section_pos !== false) {
                    $next_section = strpos($pre_content, "\n■", $section_pos + 1);
                    if ($next_section === false) {
                        $insert_pos = $pre_start + 5 + strlen(rtrim($pre_content));
                        $health_log = substr($health_log, 0, $insert_pos) . "\n" . $task_line . "\n" . substr($health_log, $insert_pos);
                    } else {
                        $insert_pos = $pre_start + 5 + $next_section;
                        $health_log = substr($health_log, 0, $insert_pos) . $task_line . "\n\n" . substr($health_log, $insert_pos);
                    }
                } else {
                    $insert_pos = $pre_end;
                    $health_log = substr($health_log, 0, $insert_pos) . "\n■ タスク\n" . $task_line . "\n  " . substr($health_log, $insert_pos);
                }
            }

            file_put_contents($health_log_path, $health_log);

            // Mark task as done in CLAUDE.md
            $claude_md = file_get_contents($claude_md_path);
            $claude_md = str_replace("- [ ] $task_text\n", "- [x] $task_text\n", $claude_md);
            file_put_contents($claude_md_path, $claude_md);

            // Keep task-answers.json intact so AI summary remains visible after completion

            // Git commit & push
            chdir(__DIR__ . '/..');
            $output = shell_exec('git add -A && git commit -m "health-log: ' . $date . ' task push" && git push origin main 2>&1');
            echo json_encode(['ok' => true, 'output' => $output]);
        } else {
            // No voice entry — just push current changes
            chdir(__DIR__ . '/..');
            $output = shell_exec('git add -A && git commit -m "dashboard update" && git push origin main 2>&1');
            echo json_encode(['ok' => true, 'output' => $output]);
        }
    } elseif ($action === 'delete_voice') {
        $file = $_POST['file'] ?? '';
        $time = $_POST['time'] ?? '';
        $voice_log = file_exists($voice_log_file) ? json_decode(file_get_contents($voice_log_file), true) : [];
        $voice_log = array_values(array_filter($voice_log, function($e) use ($file, $time) {
            return !($e['file'] === $file && $e['time'] === $time);
        }));
        file_put_contents($voice_log_file, json_encode($voice_log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
    } elseif ($action === 'complete_voice') {
        $file = $_POST['file'] ?? '';
        $time = $_POST['time'] ?? '';
        $voice_log = file_exists($voice_log_file) ? json_decode(file_get_contents($voice_log_file), true) : [];
        foreach ($voice_log as &$e) {
            if ($e['file'] === $file && $e['time'] === $time) {
                $e['done'] = empty($e['done']);
                break;
            }
        }
        file_put_contents($voice_log_file, json_encode($voice_log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
    } elseif ($action === 'add_health_note') {
        // Add *note directly under Substances section in health-log.php
        $text = trim($_POST['text'] ?? '');
        if ($text) {
            $health_log_path = __DIR__ . '/../posts/health-log.php';
            $health_log = file_get_contents($health_log_path);
            $now_date = date('Y-m-d');
            $date_header = '<h2># ' . $now_date . '</h2>';
            if (strpos($health_log, $date_header) !== false) {
                $date_pos = strpos($health_log, $date_header);
                $pre_start = strpos($health_log, '<pre>', $date_pos);
                $pre_end = strpos($health_log, '</pre>', $pre_start);
                $pre_content = substr($health_log, $pre_start + 5, $pre_end - $pre_start - 5);
                $sub_pos = strpos($pre_content, '■ Substances');
                if ($sub_pos !== false) {
                    // Find end of Substances section (next ■ or end)
                    $next_section = strpos($pre_content, "\n■", $sub_pos + 1);
                    if ($next_section !== false) {
                        $insert_pos = $pre_start + 5 + $next_section;
                        $health_log = substr($health_log, 0, $insert_pos) . '*' . $text . "\n" . substr($health_log, $insert_pos);
                    } else {
                        $insert_pos = $pre_start + 5 + strlen(rtrim($pre_content));
                        $health_log = substr($health_log, 0, $insert_pos) . "\n*" . $text . substr($health_log, $insert_pos);
                    }
                    file_put_contents($health_log_path, $health_log);
                }
            }
        }
        echo json_encode(['ok' => true]);
    } elseif ($action === 'get_health_log') {
        $date = $_POST['date'] ?? date('Y-m-d');
        $health_log_path = __DIR__ . '/../posts/health-log.php';
        $health_log = file_get_contents($health_log_path);
        $date_header = '<h2># ' . $date . '</h2>';
        $content = '';
        if (strpos($health_log, $date_header) !== false) {
            $date_pos = strpos($health_log, $date_header);
            $pre_start = strpos($health_log, '<pre>', $date_pos);
            $pre_end = strpos($health_log, '</pre>', $pre_start);
            if ($pre_start !== false && $pre_end !== false) {
                $content = substr($health_log, $pre_start + 5, $pre_end - $pre_start - 5);
                $content = trim($content);
            }
        }
        // Get available dates
        preg_match_all('/<h2># (\d{4}-\d{2}-\d{2})<\/h2>/', $health_log, $matches);
        echo json_encode(['content' => $content, 'dates' => $matches[1] ?? []], JSON_UNESCAPED_UNICODE);
    } elseif ($action === 'save_health_log') {
        $date = $_POST['date'] ?? '';
        $content = $_POST['content'] ?? '';
        if ($date && $content !== '') {
            $health_log_path = __DIR__ . '/../posts/health-log.php';
            $health_log = file_get_contents($health_log_path);
            $date_header = '<h2># ' . $date . '</h2>';
            $date_pos = strpos($health_log, $date_header);
            if ($date_pos !== false) {
                $pre_start = strpos($health_log, '<pre>', $date_pos);
                $pre_end = strpos($health_log, '</pre>', $pre_start);
                if ($pre_start !== false && $pre_end !== false) {
                    $health_log = substr($health_log, 0, $pre_start + 5) . "\n" . rtrim($content) . "\n  " . substr($health_log, $pre_end);
                    file_put_contents($health_log_path, $health_log);
                }
            }
        }
        echo json_encode(['ok' => true]);
    } elseif ($action === 'add_health') {
        $text = trim($_POST['text'] ?? '');
        $tag = $_POST['tag'] ?? 'food';
        if ($text) {
            if ($tag === 'note') {
                // Direct write to health-log.php Note section
                $health_log_path = __DIR__ . '/../posts/health-log.php';
                $health_log = file_get_contents($health_log_path);
                $now_date = date('Y-m-d');
                $date_header = '<h2># ' . $now_date . '</h2>';

                if (strpos($health_log, $date_header) !== false) {
                    $date_pos = strpos($health_log, $date_header);
                    $pre_start = strpos($health_log, '<pre>', $date_pos);
                    $pre_end = strpos($health_log, '</pre>', $pre_start);
                    $pre_content = substr($health_log, $pre_start + 5, $pre_end - $pre_start - 5);
                    $note_pos = strpos($pre_content, '■ Thought');
                    if ($note_pos !== false) {
                        $insert_pos = $pre_start + 5 + strlen(rtrim($pre_content));
                        $health_log = substr($health_log, 0, $insert_pos) . "\n\n" . $text . "\n" . substr($health_log, $insert_pos);
                    } else {
                        $insert_pos = $pre_end;
                        $health_log = substr($health_log, 0, $insert_pos) . "\n■ Note\n" . $text . "\n  " . substr($health_log, $insert_pos);
                    }
                } else {
                    $first_h2 = strpos($health_log, '<h2>#');
                    if ($first_h2 !== false) {
                        $hr_before = strrpos(substr($health_log, 0, $first_h2), '<hr');
                        if ($hr_before !== false) {
                            $new_entry = "  $date_header\n  <pre>\n■ Note\n$text\n  </pre>\n\n  <hr style=\"border: none; border-top: 0.5px solid rgba(0,0,0,0.06); margin: 50px 0 40px;\">\n\n";
                            $health_log = substr($health_log, 0, $hr_before) . $new_entry . substr($health_log, $hr_before);
                        }
                    }
                }
                file_put_contents($health_log_path, $health_log);

                // Auto commit & push
                chdir(__DIR__ . '/..');
                shell_exec('git add -A && git commit -m "health-log: ' . $now_date . ' note" && git push origin main 2>&1');
            } else {
                // Add to voice-log.json for review
                $voice_log = file_exists($voice_log_file) ? json_decode(file_get_contents($voice_log_file), true) : [];
                $now_date = date('Y-m-d');
                $now_time = date('H:i');
                $voice_log[] = [
                    'date' => $now_date,
                    'time' => $now_time,
                    'text' => $text,
                    'summary' => mb_strlen($text) > 30 ? mb_substr($text, 0, 30) . '...' : $text,
                    'file' => 'manual-' . time(),
                    'tag' => $tag
                ];
                file_put_contents($voice_log_file, json_encode($voice_log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }
        }
        echo json_encode(['ok' => true]);
    } elseif ($action === 'edit_voice_meta') {
        $file = $_POST['file'] ?? '';
        $time = $_POST['time'] ?? '';
        $new_date = $_POST['new_date'] ?? '';
        $new_time = $_POST['new_time'] ?? '';
        $voice_log = file_exists($voice_log_file) ? json_decode(file_get_contents($voice_log_file), true) : [];
        foreach ($voice_log as &$e) {
            if ($e['file'] === $file && $e['time'] === $time) {
                if ($new_date) $e['date'] = $new_date;
                if ($new_time) $e['time'] = $new_time;
                break;
            }
        }
        file_put_contents($voice_log_file, json_encode($voice_log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
    } elseif ($action === 'queue_ai') {
        $queue_file = __DIR__ . '/task-queue.json';
        $queue = file_exists($queue_file) ? json_decode(file_get_contents($queue_file), true) : [];
        $file = $_POST['file'] ?? '';
        $time = $_POST['time'] ?? '';
        $task_name = $_POST['task'] ?? '';

        // Find the entry details
        $entry_text = '';
        $entry_answer = '';
        $entry_summary = '';
        if ($task_name) {
            $entry_text = $task_name;
            $entry_summary = $task_name;
            $entry_answer = $task_answers[$task_name] ?? '';
        } else {
            $voice_log = file_exists($voice_log_file) ? json_decode(file_get_contents($voice_log_file), true) : [];
            foreach ($voice_log as $e) {
                if ($e['file'] === $file && $e['time'] === $time) {
                    $entry_text = $e['text'] ?? '';
                    $entry_summary = $e['summary'] ?? $entry_text;
                    $entry_answer = $e['answer'] ?? '';
                    break;
                }
            }
        }

        $instruction = $_POST['instruction'] ?? '';
        $item = [
            'task' => $entry_summary,
            'detail' => $entry_text,
            'answer' => $entry_answer,
            'status' => 'pending',
            'queued_at' => date('Y-m-d H:i:s')
        ];
        if ($instruction) $item['instruction'] = $instruction;
        $queue[] = $item;
        file_put_contents($queue_file, json_encode($queue, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
    } elseif ($action === 'get_voice') {
        $file = $_POST['file'] ?? '';
        $time = $_POST['time'] ?? '';
        $voice_log = file_exists($voice_log_file) ? json_decode(file_get_contents($voice_log_file), true) : [];
        $result = ['text' => '', 'summary' => ''];
        foreach ($voice_log as $e) {
            if ($e['file'] === $file && $e['time'] === $time) {
                $result = ['text' => $e['text'] ?? '', 'summary' => $e['summary'] ?? ''];
                break;
            }
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    } elseif ($action === 'edit_voice') {
        $file = $_POST['file'] ?? '';
        $time = $_POST['time'] ?? '';
        $new_text = $_POST['new_text'] ?? '';
        $new_summary = $_POST['new_summary'] ?? '';
        $voice_log = file_exists($voice_log_file) ? json_decode(file_get_contents($voice_log_file), true) : [];
        foreach ($voice_log as &$e) {
            if ($e['file'] === $file && $e['time'] === $time) {
                if ($new_text) $e['text'] = $new_text;
                if ($new_summary) $e['summary'] = $new_summary;
                break;
            }
        }
        file_put_contents($voice_log_file, json_encode($voice_log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
    }
    exit;
}

// Parse lists from CLAUDE.md
preg_match('/## タスクリスト\n([\s\S]*?)(?=\n##|\z)/', $claude_md, $task_match);
$tasks = [];
$tasks_scheduled = [];
$today = date('Y-m-d');
if ($task_match) {
    preg_match_all('/- \[([ x])\] (.+)/', $task_match[1], $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $text = trim($m[2]);
        $show_after = null;
        if (preg_match('/ @(\d{4}-\d{2}-\d{2})$/', $text, $dm)) {
            $show_after = $dm[1];
            $text = trim(preg_replace('/ @\d{4}-\d{2}-\d{2}$/', '', $text));
        }
        $item = ['done' => $m[1] === 'x', 'text' => $text, 'show_after' => $show_after];
        if ($show_after && $show_after > $today) {
            $tasks_scheduled[] = $item;
        } else {
            $tasks[] = $item;
        }
    }
}

preg_match('/## 買い物リスト\n([\s\S]*?)(?=\n##|\z)/', $claude_md, $shop_match);
$shopping = [];
$shopping_scheduled = [];
if ($shop_match) {
    preg_match_all('/- \[([ x])\] (.+)/', $shop_match[1], $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $text = trim($m[2]);
        $show_after = null;
        if (preg_match('/ @(\d{4}-\d{2}-\d{2})$/', $text, $dm)) {
            $show_after = $dm[1];
            $text = trim(preg_replace('/ @\d{4}-\d{2}-\d{2}$/', '', $text));
        }
        $item = ['done' => $m[1] === 'x', 'text' => $text, 'show_after' => $show_after];
        if ($show_after && $show_after > $today) {
            $shopping_scheduled[] = $item;
        } else {
            $shopping[] = $item;
        }
    }
}

// Separate voice logs by tag
$voice_notes = []; $voice_tasks = []; $voice_tasks_scheduled = [];
$voice_shopping = []; $voice_shopping_scheduled = [];
$voice_food = []; $voice_health = []; $voice_substance = [];
$voice_ideas = []; $voice_read_later = [];
$today = date('Y-m-d');
$today_day = (int)date('j');
$days_in_month = (int)date('t');
$days_until_1st = ($today_day === 1) ? 0 : $days_in_month - $today_day + 1;

// Parse recurring tasks from CLAUDE.md
$recurring_tasks = [];
if (preg_match('/## 毎月の固定タスク\n([\s\S]*?)(?=\n##|\z)/', $claude_md, $rt_match)) {
    preg_match_all('/- \[( |x)\] (.+)/', $rt_match[1], $rt_matches, PREG_SET_ORDER);
    foreach ($rt_matches as $m) {
        $recurring_tasks[] = ['done' => $m[1] === 'x', 'text' => trim($m[2])];
    }
}

foreach ($voice_log as $entry) {
    $tag = $entry['tag'] ?? '';
    $show_after = $entry['show_after'] ?? null;
    $scheduled = $show_after && $show_after > $today;
    switch ($tag) {
        case 'task': $scheduled ? $voice_tasks_scheduled[] = $entry : $voice_tasks[] = $entry; break;
        case 'shopping': $scheduled ? $voice_shopping_scheduled[] = $entry : $voice_shopping[] = $entry; break;
        case 'food': $voice_food[] = $entry; break;
        case 'health': $voice_health[] = $entry; break;
        case 'substance': $voice_substance[] = $entry; break;
        case 'idea': $voice_ideas[] = $entry; break;
        case 'read_later': $voice_read_later[] = $entry; break;
        default: $voice_notes[] = $entry; break;
    }
}

$notes_by_date = [];
foreach ($voice_notes as $e) $notes_by_date[$e['date']][] = $e;
krsort($notes_by_date);

// Helper: render voice entry with edit/delete/push
function voice_entry_html($entry, $show_push = false, $use_summary = true, $show_date_edit = false) {
    global $queued_pending, $queued_done;
    $file = htmlspecialchars(addslashes($entry['file']));
    $time = htmlspecialchars($entry['time']);
    $date = htmlspecialchars($entry['date']);
    $time_short = htmlspecialchars(substr($entry['time'], 0, 5));
    $text = htmlspecialchars($entry['text']);
    $summary = isset($entry['summary']) ? htmlspecialchars($entry['summary']) : $text;
    $de = $show_date_edit ? 'true' : 'false';
    $has_answer = !empty($entry['answer']);
    $html = '<div class="voice-entry' . ($has_answer ? ' has-answer' : '') . '">';
    $html .= '<span class="voice-time">' . $time_short . '</span>';
    if ($use_summary) {
        $onclick = $has_answer ? 'toggleAnswer(this,event)' : 'editAll(this,\'' . $file . '\',\'' . $time . '\',\'' . $date . '\',' . $de . ',\'' . addslashes($summary) . '\')';
        $html .= '<span class="voice-text clickable-title" onclick="' . $onclick . '">' . $summary . '</span>';
    } else {
        $onclick = $has_answer ? 'toggleAnswer(this,event)' : 'editAll(this,\'' . $file . '\',\'' . $time . '\',\'' . $date . '\',' . $de . ',\'\')';
        $html .= '<span class="voice-text clickable-title" onclick="' . $onclick . '">' . $text . '</span>';
    }
    $html .= '<span class="entry-actions">';
    if ($show_push) {
        $html .= '<button class="action-btn push-item-btn" onclick="pushToServer(this,true)" data-file="' . $file . '" data-time="' . $time . '" title="Push">🚀</button>';
    }
    $html .= '<button class="action-btn delete-btn" onclick="deleteVoice(\'' . $file . '\',\'' . $time . '\')" title="Delete">×</button>';
    $html .= '</span>';
    $entry_answer = $entry['answer'] ?? null;
    if ($entry_answer) {
        $html .= '<div class="answer-panel">';
        $html .= '<div style="opacity:0.6;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid rgba(255,255,255,0.1);">📝 ' . nl2br(htmlspecialchars($entry['text'])) . '</div>';
        $html .= nl2br(htmlspecialchars($entry_answer));
        $html .= '<div style="margin-top:16px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.1);">';
        $html .= '<input type="text" class="inline-action-input" placeholder="アクションを入力..." style="width:100%;background:rgba(0,0,0,0.3);border:1px solid rgba(255,255,255,0.15);color:#e8e8ef;padding:10px 14px;border-radius:10px;font-size:15px;font-family:inherit;margin-bottom:8px;">';
        $html .= '<button class="inline-push-action" data-file="' . $file . '" data-time="' . $time . '" style="width:100%;padding:12px;border:none;border-radius:10px;cursor:pointer;font-size:14px;font-family:inherit;background:rgba(80,200,120,0.12);color:#50c878;" onclick="pushAction(this)">↑ Push with AI Answer</button>';
        $entry_summary = $entry['summary'] ?? $entry['text'] ?? '';
        if (in_array($entry_summary, $queued_done)) {
            $html .= '<button disabled style="width:100%;padding:12px;border:none;border-radius:10px;font-size:14px;font-family:inherit;background:rgba(80,200,120,0.08);color:rgba(80,200,120,0.5);margin-top:8px;opacity:0.5;">✅ 実行済み</button>';
        } elseif (in_array($entry_summary, $queued_pending)) {
            $html .= '<button disabled style="width:100%;padding:12px;border:none;border-radius:10px;font-size:14px;font-family:inherit;background:rgba(255,200,60,0.08);color:rgba(255,200,60,0.5);margin-top:8px;opacity:0.5;">⏳ 実行待ち</button>';
        } else {
            $html .= '<input type="text" class="ai-instruction" placeholder="AIへの指示（任意）" style="width:100%;background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.1);color:#e8e8ef;padding:8px 12px;border-radius:8px;font-size:13px;font-family:inherit;margin-top:8px;">';
            $html .= '<button data-file="' . $file . '" data-time="' . $time . '" style="width:100%;padding:12px;border:none;border-radius:10px;cursor:pointer;font-size:14px;font-family:inherit;background:rgba(255,160,60,0.12);color:#ffa03c;margin-top:4px;" onclick="queueForAI(this)">⚡ AIで実行</button>';
        }
        $html .= '</div>';
        $html .= '<div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.1);display:flex;gap:6px;">';
        $html .= '<input type="text" class="panel-text-input" placeholder="テキストを入力..." style="flex:1;background:rgba(0,0,0,0.3);border:1px solid rgba(255,255,255,0.15);color:#e8e8ef;padding:8px 12px;border-radius:8px;font-size:13px;font-family:inherit;">';
        $html .= '<button style="padding:8px 16px;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-family:inherit;background:rgba(255,255,255,0.1);color:#e8e8ef;white-space:nowrap;" onclick="sendPanelText(this)">送信</button>';
        $html .= '</div>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard</title>
<link rel="stylesheet" href="theme-a.css">
</head>
<body>


<?php if (!empty($recurring_tasks)): ?>
<!-- Recurring Tasks -->
<div class="section" style="<?= $days_until_1st <= 3 ? 'border:1px solid rgba(255,100,100,0.4);background:rgba(255,100,100,0.05);' : '' ?>">
  <div class="section-title">Monthly Tasks <?php if ($days_until_1st <= 3): ?><span style="font-size:12px;color:#ff6464;margin-left:8px;"><?= $days_until_1st === 0 ? '⚠️ 今日！' : '⚠️ あと' . $days_until_1st . '日' ?></span><?php endif; ?></div>
  <?php foreach ($recurring_tasks as $rt): ?>
    <div class="list-item <?= $rt['done'] ? 'done' : '' ?>" style="<?= (!$rt['done'] && $days_until_1st <= 3) ? 'color:#ff6464;' : '' ?>">
      <span class="voice-text"><?= htmlspecialchars($rt['text']) ?></span>
      <span class="entry-actions">
        <button class="action-btn done-btn" onclick="completeItem('monthly','<?= htmlspecialchars(addslashes($rt['text'])) ?>')" title="Toggle done"><?= $rt['done'] ? '↩' : '✓' ?></button>
      </span>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Tasks -->
<div class="section">
  <div class="section-title">Tasks</div>
  <?php foreach ($tasks as $task):
    $answer = $task_answers[$task['text']] ?? null;
  ?>
    <div class="list-item <?= $task['done'] ? 'done' : '' ?> <?= $answer ? 'has-answer' : '' ?>">
      <span class="voice-text clickable-title" onclick="<?= $answer ? 'toggleAnswer(this,event)' : 'editTask(this,\'' . htmlspecialchars(addslashes($task['text'])) . '\',false)' ?>"><?= htmlspecialchars($task['text']) ?></span>
      <?php if ($answer): ?>
        <div class="answer-panel"><?= nl2br(htmlspecialchars($answer)) ?>
        <div style="margin-top:16px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.1);">
          <?php if (in_array($task['text'], $queued_done)): ?>
          <button disabled style="width:100%;padding:12px;border:none;border-radius:10px;font-size:14px;font-family:inherit;background:rgba(80,200,120,0.08);color:rgba(80,200,120,0.5);opacity:0.5;">✅ 実行済み</button>
          <?php elseif (in_array($task['text'], $queued_pending)): ?>
          <button disabled style="width:100%;padding:12px;border:none;border-radius:10px;font-size:14px;font-family:inherit;background:rgba(255,200,60,0.08);color:rgba(255,200,60,0.5);opacity:0.5;">⏳ 実行待ち</button>
          <?php else: ?>
          <input type="text" class="ai-instruction" placeholder="AIへの指示（任意）" style="width:100%;background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.1);color:#e8e8ef;padding:8px 12px;border-radius:8px;font-size:13px;font-family:inherit;">
          <button data-task="<?= htmlspecialchars($task['text']) ?>" style="width:100%;padding:12px;border:none;border-radius:10px;cursor:pointer;font-size:14px;font-family:inherit;background:rgba(255,160,60,0.12);color:#ffa03c;margin-top:4px;" onclick="queueForAI(this)">⚡ AIで実行</button>
          <?php endif; ?>
        </div>
        </div>
      <?php endif; ?>
      <span class="entry-actions">
        <button class="action-btn done-btn" onclick="completeItem('task','<?= htmlspecialchars(addslashes($task['text'])) ?>')" title="Toggle done"><?= $task['done'] ? '↩' : '✓' ?></button>
        <button class="action-btn reorder-btn" onclick="reorderTask('<?= htmlspecialchars(addslashes($task['text'])) ?>','up','claude')" title="Up">▲</button>
        <button class="action-btn reorder-btn" onclick="reorderTask('<?= htmlspecialchars(addslashes($task['text'])) ?>','down','claude')" title="Down">▼</button>
        <button class="action-btn push-item-btn" onclick="pushToServer(this,true)" data-task="<?= htmlspecialchars($task['text']) ?>" title="Push">🚀</button>
        <button class="action-btn delete-btn" onclick="deleteItem('task','<?= htmlspecialchars(addslashes($task['text'])) ?>')">×</button>
      </span>
    </div>
  <?php endforeach; ?>
  <?php foreach ($voice_tasks as $vt): $is_done = !empty($vt['done']); $vt_answer = $vt['answer'] ?? null; ?>
    <div class="list-item <?= $is_done ? 'done' : '' ?> <?= $vt_answer ? 'has-answer' : '' ?>">
      <span class="voice-text clickable-title" onclick="<?= $vt_answer ? 'toggleAnswer(this,event)' : 'editAll(this,\'' . htmlspecialchars(addslashes($vt['file'])) . '\',\'' . htmlspecialchars($vt['time']) . '\',\'' . htmlspecialchars($vt['date'] ?? '') . '\',false,\'' . htmlspecialchars(addslashes($vt['summary'] ?? $vt['text'])) . '\')' ?>"><?= htmlspecialchars($vt['summary'] ?? $vt['text']) ?></span>
      <?php if ($vt_answer): ?>
        <div class="answer-panel"><div style="opacity:0.6;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid rgba(255,255,255,0.1);">📝 <?= nl2br(htmlspecialchars($vt['text'])) ?></div><?= nl2br(htmlspecialchars($vt_answer)) ?>
        <div style="margin-top:16px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.1);">
          <?php $vt_summary = $vt['summary'] ?? $vt['text']; if (in_array($vt_summary, $queued_done)): ?>
          <button disabled style="width:100%;padding:12px;border:none;border-radius:10px;font-size:14px;font-family:inherit;background:rgba(80,200,120,0.08);color:rgba(80,200,120,0.5);opacity:0.5;">✅ 実行済み</button>
          <?php elseif (in_array($vt_summary, $queued_pending)): ?>
          <button disabled style="width:100%;padding:12px;border:none;border-radius:10px;font-size:14px;font-family:inherit;background:rgba(255,200,60,0.08);color:rgba(255,200,60,0.5);opacity:0.5;">⏳ 実行待ち</button>
          <?php else: ?>
          <input type="text" class="ai-instruction" placeholder="AIへの指示（任意）" style="width:100%;background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.1);color:#e8e8ef;padding:8px 12px;border-radius:8px;font-size:13px;font-family:inherit;">
          <button data-file="<?= htmlspecialchars(addslashes($vt['file'])) ?>" data-time="<?= htmlspecialchars($vt['time']) ?>" style="width:100%;padding:12px;border:none;border-radius:10px;cursor:pointer;font-size:14px;font-family:inherit;background:rgba(255,160,60,0.12);color:#ffa03c;margin-top:4px;" onclick="queueForAI(this)">⚡ AIで実行</button>
          <?php endif; ?>
        </div>
        </div>
      <?php endif; ?>
      <span class="entry-actions">
        <button class="action-btn done-btn" onclick="completeVoice('<?= htmlspecialchars(addslashes($vt['file'])) ?>','<?= htmlspecialchars($vt['time']) ?>')" title="Toggle done"><?= $is_done ? '↩' : '✓' ?></button>
        <button class="action-btn reorder-btn" onclick="reorderTask('<?= htmlspecialchars(addslashes($vt['summary'] ?? $vt['text'])) ?>','up','voice','<?= htmlspecialchars(addslashes($vt['file'])) ?>','<?= htmlspecialchars($vt['time']) ?>')" title="Up">▲</button>
        <button class="action-btn reorder-btn" onclick="reorderTask('<?= htmlspecialchars(addslashes($vt['summary'] ?? $vt['text'])) ?>','down','voice','<?= htmlspecialchars(addslashes($vt['file'])) ?>','<?= htmlspecialchars($vt['time']) ?>')" title="Down">▼</button>
        <button class="action-btn push-item-btn" onclick="pushToServer(this,true)" data-file="<?= htmlspecialchars(addslashes($vt['file'])) ?>" data-time="<?= htmlspecialchars($vt['time']) ?>" title="Push">🚀</button>
        <button class="action-btn delete-btn" onclick="deleteVoice('<?= htmlspecialchars(addslashes($vt['file'])) ?>','<?= htmlspecialchars($vt['time']) ?>')">×</button>
      </span>
    </div>
  <?php endforeach; ?>
  <?php $all_scheduled_count = count($tasks_scheduled) + count($voice_tasks_scheduled);
    if ($all_scheduled_count > 0): ?>
    <div class="toggle" onclick="this.nextElementSibling.classList.toggle('open')">▶ Scheduled (<?= $all_scheduled_count ?>)</div>
    <div class="toggle-content">
      <?php foreach ($tasks_scheduled as $ts): ?>
        <div class="list-item">
          <span class="voice-text"><?= htmlspecialchars($ts['text']) ?></span>
          <span class="scheduled-date"><?= htmlspecialchars($ts['show_after']) ?></span>
        </div>
      <?php endforeach; ?>
      <?php foreach ($voice_tasks_scheduled as $vt): ?>
        <div class="list-item">
          <span class="editable" onclick="editVoice(this,'<?= htmlspecialchars(addslashes($vt['file'])) ?>','<?= htmlspecialchars($vt['time']) ?>')"><?= htmlspecialchars($vt['text']) ?></span>
          <span class="scheduled-date"><?= htmlspecialchars($vt['show_after']) ?></span>
          <button class="action-btn delete-btn" onclick="deleteVoice('<?= htmlspecialchars(addslashes($vt['file'])) ?>','<?= htmlspecialchars($vt['time']) ?>')">×</button>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="add-form">
    <input type="text" id="new-task" placeholder="Add task...">
    <button onclick="addItem('task')">Add</button>
  </div>

</div>

<!-- Health Log Editor -->
<div class="section" id="health-log-editor" style="display:none;">
  <div class="section-title">Edit Health Log <button onclick="closeEditor()" style="float:right;background:none;border:none;color:#e8e8ef;font-size:18px;cursor:pointer;">✕</button></div>
  <div style="margin-bottom:10px;">
    <select id="editor-date" onchange="loadHealthLog(this.value)" style="background:rgba(0,0,0,0.3);border:1px solid rgba(255,255,255,0.15);color:#e8e8ef;padding:8px 12px;border-radius:8px;font-size:14px;font-family:inherit;"></select>
    <span id="editor-status" style="margin-left:10px;font-size:12px;color:rgba(255,255,255,0.3);"></span>
  </div>
  <textarea id="editor-textarea" style="width:100%;min-height:500px;background:rgba(0,0,0,0.3);border:1px solid rgba(255,255,255,0.15);color:#e8e8ef;padding:14px;border-radius:10px;font-size:14px;font-family:'SFMono-Regular',Consolas,Menlo,monospace;line-height:1.7;resize:vertical;white-space:pre-wrap;" oninput="editorAutoSave()"></textarea>
</div>

<!-- Health Log -->
<?php if (!empty($voice_food) || !empty($voice_health) || !empty($voice_substance)): ?>
<div class="section">
  <div class="section-title">Health Log <button onclick="openEditor()" style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.2);color:#e8e8ef;padding:6px 14px;border-radius:8px;font-size:13px;cursor:pointer;margin-left:10px;">✏️ Edit Health Log</button></div>

  <?php if (!empty($voice_food)): ?>
    <div class="sub-title">Food</div>
    <?php $food_by_date = []; foreach ($voice_food as $e) $food_by_date[$e['date']][] = $e; krsort($food_by_date);
      foreach ($food_by_date as $date => $entries): ?>
      <div class="voice-date"><?= htmlspecialchars($date) ?></div>
      <?php foreach ($entries as $entry) echo voice_entry_html($entry, true, false, true); ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($voice_substance)): ?>
    <div class="sub-title sub-title-border">Substance</div>
    <?php $sbd = []; foreach ($voice_substance as $e) $sbd[$e['date']][] = $e; krsort($sbd);
      foreach ($sbd as $date => $entries): ?>
      <div class="voice-date"><?= htmlspecialchars($date) ?></div>
      <?php foreach ($entries as $entry) echo voice_entry_html($entry, true, true, true); ?>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php if (!empty($voice_health)): ?>
    <div class="sub-title sub-title-border">Health Notes</div>
    <?php $hbd = []; foreach ($voice_health as $e) $hbd[$e['date']][] = $e; krsort($hbd);
      foreach ($hbd as $date => $entries): ?>
      <div class="voice-date"><?= htmlspecialchars($date) ?></div>
      <?php foreach ($entries as $entry) echo voice_entry_html($entry, true, true, true); ?>
    <?php endforeach; ?>
  <?php endif; ?>
  <div class="add-form" style="flex-wrap:wrap;">
    <select id="new-health-tag" style="background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.1);color:#e8e8ef;padding:10px 12px;border-radius:10px;font-size:15px;font-family:inherit;">
      <option value="note">Thought</option>
      <option value="food">Food</option>
      <option value="substance">Substance</option>
    </select>
    <input type="text" id="new-health" placeholder="Add entry..." style="flex:2;">
    <button onclick="addHealthEntry()">Add</button>
  </div>
</div>
<?php else: ?>
<div class="section">
  <div class="section-title">Health Log <button onclick="openEditor()" style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.2);color:#e8e8ef;padding:6px 14px;border-radius:8px;font-size:13px;cursor:pointer;margin-left:10px;">✏️ Edit Health Log</button></div>
  <div class="empty">No entries yet.</div>
  <div class="add-form" style="flex-wrap:wrap;">
    <select id="new-health-tag" style="background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.1);color:#e8e8ef;padding:10px 12px;border-radius:10px;font-size:15px;font-family:inherit;">
      <option value="note">Thought</option>
      <option value="food">Food</option>
      <option value="substance">Substance</option>
    </select>
    <input type="text" id="new-health" placeholder="Add entry..." style="flex:2;">
    <button onclick="addHealthEntry()">Add</button>
  </div>
</div>
<?php endif; ?>

<!-- Ideas -->
<div class="section">
  <div class="section-title">Ideas</div>
  <?php if (empty($voice_ideas)): ?>
    <div class="empty">No ideas yet.</div>
  <?php else: ?>
    <?php foreach ($voice_ideas as $vi): ?>
      <div class="list-item">
        <span class="voice-text clickable-title" onclick="editAll(this,'<?= htmlspecialchars(addslashes($vi['file'])) ?>','<?= htmlspecialchars($vi['time']) ?>','<?= htmlspecialchars($vi['date']) ?>',false,'<?= htmlspecialchars(addslashes($vi['summary'] ?? $vi['text'])) ?>')"><?= htmlspecialchars($vi['summary'] ?? $vi['text']) ?></span>
        <span class="entry-actions">
          <button class="action-btn delete-btn" onclick="deleteVoice('<?= htmlspecialchars(addslashes($vi['file'])) ?>','<?= htmlspecialchars($vi['time']) ?>')">×</button>
        </span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
  <div class="add-form">
    <input type="text" id="new-idea" placeholder="Add idea...">
    <button onclick="addIdea()">Add</button>
  </div>
</div>

<!-- Read Later -->
<div class="section">
  <div class="section-title">Read Later</div>
  <?php if (empty($voice_read_later)): ?>
    <div class="empty">No items.</div>
  <?php else: ?>
    <?php foreach ($voice_read_later as $rl): ?>
      <div class="list-item">
        <a href="<?= htmlspecialchars($rl['text']) ?>" target="_blank" class="voice-text" style="color:#4a9eff;text-decoration:underline;text-underline-offset:3px;"><?= htmlspecialchars($rl['summary'] ?? $rl['text']) ?></a>
        <span class="entry-actions">
          <button class="action-btn done-btn" onclick="completeVoice('<?= htmlspecialchars(addslashes($rl['file'])) ?>','<?= htmlspecialchars($rl['time']) ?>')" title="Read">✓</button>
          <button class="action-btn delete-btn" onclick="deleteVoice('<?= htmlspecialchars(addslashes($rl['file'])) ?>','<?= htmlspecialchars($rl['time']) ?>')">×</button>
        </span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
  <div class="add-form">
    <input type="text" id="new-read-later" placeholder="URL を追加...">
    <button onclick="addReadLater()">Add</button>
  </div>
</div>

<!-- Voice Notes -->
<div class="section">
  <div class="section-title">Voice Notes</div>
  <?php if (empty($notes_by_date)): ?>
    <div class="empty">No voice notes.</div>
  <?php else: ?>
    <?php foreach ($notes_by_date as $date => $entries): ?>
      <div class="voice-date"><?= htmlspecialchars($date) ?></div>
      <?php foreach ($entries as $entry):
        $file = htmlspecialchars(addslashes($entry['file']));
        $time = htmlspecialchars($entry['time']);
        $time_short = htmlspecialchars(substr($entry['time'], 0, 5));
        $text = htmlspecialchars($entry['text']);
        $summary = isset($entry['summary']) ? htmlspecialchars($entry['summary']) : (mb_strlen($entry['text']) > 40 ? htmlspecialchars(mb_substr($entry['text'], 0, 40)) . '...' : $text);
      ?>
        <div class="voice-entry">
          <span class="voice-time"><?= $time_short ?></span>
          <span class="voice-text clickable-title" onclick="editAll(this,'<?= $file ?>','<?= $time ?>','<?= htmlspecialchars($entry['date']) ?>',false,'<?= htmlspecialchars(addslashes($entry['summary'] ?? $entry['text'])) ?>')"><?= $summary ?></span>
          <span class="entry-actions">
            <button class="action-btn delete-btn" onclick="deleteVoice('<?= $file ?>','<?= $time ?>')" title="Delete">×</button>
          </span>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Shopping -->
<div class="section">
  <div class="section-title">Shopping</div>
  <?php foreach ($shopping as $item): ?>
    <div class="list-item <?= $item['done'] ? 'done' : '' ?>">
      <span class="voice-text clickable-title" onclick="editListItem(this,'shopping','<?= htmlspecialchars(addslashes($item['text'])) ?>')"><?= htmlspecialchars($item['text']) ?></span>
      <span class="entry-actions">
        <button class="action-btn reorder-btn" onclick="reorderShopping('<?= htmlspecialchars(addslashes($item['text'])) ?>','up','claude')" title="Up">▲</button>
        <button class="action-btn reorder-btn" onclick="reorderShopping('<?= htmlspecialchars(addslashes($item['text'])) ?>','down','claude')" title="Down">▼</button>
        <button class="action-btn delete-btn" onclick="deleteItem('shopping','<?= htmlspecialchars(addslashes($item['text'])) ?>')">×</button>
      </span>
    </div>
  <?php endforeach; ?>
  <?php foreach ($voice_shopping as $vs): ?>
    <div class="list-item">
      <span class="voice-text clickable-title" onclick="editAll(this,'<?= htmlspecialchars(addslashes($vs['file'])) ?>','<?= htmlspecialchars($vs['time']) ?>','<?= htmlspecialchars($vs['date']) ?>',false,'<?= htmlspecialchars(addslashes($vs['summary'] ?? $vs['text'])) ?>')"><?= htmlspecialchars($vs['summary'] ?? $vs['text']) ?></span>
      <span class="entry-actions">
        <button class="action-btn reorder-btn" onclick="reorderShopping('<?= htmlspecialchars(addslashes($vs['summary'] ?? $vs['text'])) ?>','up','voice','<?= htmlspecialchars(addslashes($vs['file'])) ?>','<?= htmlspecialchars($vs['time']) ?>')" title="Up">▲</button>
        <button class="action-btn reorder-btn" onclick="reorderShopping('<?= htmlspecialchars(addslashes($vs['summary'] ?? $vs['text'])) ?>','down','voice','<?= htmlspecialchars(addslashes($vs['file'])) ?>','<?= htmlspecialchars($vs['time']) ?>')" title="Down">▼</button>
        <button class="action-btn delete-btn" onclick="deleteVoice('<?= htmlspecialchars(addslashes($vs['file'])) ?>','<?= htmlspecialchars($vs['time']) ?>')">×</button>
      </span>
    </div>
  <?php endforeach; ?>
  <?php if (!empty($voice_shopping_scheduled)): ?>
    <div class="toggle" onclick="this.nextElementSibling.classList.toggle('open')">▶ Scheduled (<?= count($voice_shopping_scheduled) ?>)</div>
    <div class="toggle-content">
      <?php foreach ($voice_shopping_scheduled as $vs): ?>
        <div class="list-item">
          <span class="editable" onclick="editVoice(this,'<?= htmlspecialchars(addslashes($vs['file'])) ?>','<?= htmlspecialchars($vs['time']) ?>')"><?= htmlspecialchars($vs['text']) ?></span>
          <span class="scheduled-date"><?= htmlspecialchars($vs['show_after']) ?></span>
          <button class="action-btn delete-btn" onclick="deleteVoice('<?= htmlspecialchars(addslashes($vs['file'])) ?>','<?= htmlspecialchars($vs['time']) ?>')">×</button>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="add-form">
    <input type="text" id="new-shopping" placeholder="Add item...">
    <button onclick="addItem('shopping')">Add</button>
  </div>
</div>

<script>
// Global close-on-outside manager: only one active at a time
let _activeCloseHandler = null;
function registerCloseOnOutside(container, onClose) {
  // Remove previous handler
  if (_activeCloseHandler) {
    document.removeEventListener('click', _activeCloseHandler);
    _activeCloseHandler = null;
  }
  setTimeout(() => {
    function handler(ev) {
      if (container.contains(ev.target)) return;
      // Ignore clicks on inputs/textareas/buttons that may have been dynamically added
      if (ev.target.closest('.inline-edit, .answer-panel')) return;
      onClose();
      document.removeEventListener('click', handler);
      _activeCloseHandler = null;
    }
    _activeCloseHandler = handler;
    document.addEventListener('click', handler);
  }, 200);
}

function toggleAnswer(el, e) {
  if (e) e.stopPropagation();
  const item = el.closest('.list-item') || el.closest('.voice-entry');
  const panel = item ? item.querySelector('.answer-panel') : null;
  if (!panel) return;
  const isOpen = panel.classList.contains('open');
  if (isOpen) {
    panel.classList.remove('open');
  } else {
    panel.classList.add('open');
    registerCloseOnOutside(item, () => panel.classList.remove('open'));
  }
}

function addItem(type) {
  const input = document.getElementById('new-' + type);
  const text = input.value.trim();
  if (!text) return;
  const form = new FormData();
  form.append('text', text);
  fetch('?action=add_' + type, { method: 'POST', body: form }).then(() => location.reload());
}

function deleteItem(type, text) {
  if (!confirm('Delete this item?')) return;
  const form = new FormData();
  form.append('text', text);
  fetch('?action=delete_' + type, { method: 'POST', body: form }).then(() => location.reload());
}

function deleteVoice(file, time) {
  if (!confirm('Delete this item?')) return;
  const form = new FormData();
  form.append('file', file);
  form.append('time', time);
  fetch('?action=delete_voice', { method: 'POST', body: form }).then(() => location.reload());
}


function toggleNote(el) {
  const wrapper = el.closest('.note-wrapper');
  const summary = wrapper.querySelector('.note-summary');
  const full = wrapper.querySelector('.note-full');
  const isOpen = full.classList.contains('open');
  if (isOpen) {
    full.classList.remove('open');
    summary.style.display = '';
  } else {
    full.classList.add('open');
    summary.style.display = 'none';
  }
}

function completeItem(type, text) {
  const form = new FormData();
  form.append('text', text);
  fetch('?action=complete_' + type, { method: 'POST', body: form }).then(() => location.reload());
}

function completeVoice(file, time) {
  const form = new FormData();
  form.append('file', file);
  form.append('time', time);
  fetch('?action=complete_voice', { method: 'POST', body: form }).then(() => location.reload());
}

function editTask(el, oldText, hasAnswer) {
  const entry = el.closest('.list-item');
  if (entry.querySelector('.inline-edit')) return;
  const originalHTML = entry.innerHTML;
  const answerPanel = entry.querySelector('.answer-panel');
  const oldAnswer = answerPanel ? answerPanel.textContent.trim() : '';

  const form = document.createElement('div');
  form.className = 'inline-edit';
  let html = '<label style="font-size:13px;color:rgba(255,255,255,0.4);margin-bottom:4px;display:block;">Task</label>';
  html += '<textarea class="inline-textarea">' + oldText.replace(/</g,'&lt;') + '</textarea>';
  if (hasAnswer) {
    html += '<label style="font-size:13px;color:rgba(255,255,255,0.4);margin:12px 0 4px;display:block;">AI Answer</label>';
    html += '<textarea class="inline-textarea inline-answer">' + oldAnswer.replace(/</g,'&lt;') + '</textarea>';
  }
  html += '<div class="inline-edit-actions"><button class="inline-save">Save</button></div>';
  form.innerHTML = html;

  entry.innerHTML = '';
  entry.appendChild(form);
  const textareas = form.querySelectorAll('.inline-textarea');
  textareas.forEach(ta => { ta.style.height = Math.max(50, ta.scrollHeight) + 'px'; });
  textareas[0].focus();

  form.querySelector('.inline-save').onclick = () => {
    const newText = textareas[0].value.trim();
    const newAnswer = hasAnswer ? (form.querySelector('.inline-answer')?.value.trim() ?? '') : '';
    const promises = [];
    if (newText && newText !== oldText) {
      const f = new FormData();
      f.append('old_text', oldText);
      f.append('new_text', newText);
      promises.push(fetch('?action=edit_task', { method: 'POST', body: f }));
    }
    if (hasAnswer && newAnswer !== oldAnswer) {
      const f2 = new FormData();
      f2.append('task_name', newText || oldText);
      f2.append('new_answer', newAnswer);
      f2.append('source', 'claude');
      if (newText && newText !== oldText) f2.append('old_task_name', oldText);
      promises.push(fetch('?action=edit_task_answer', { method: 'POST', body: f2 }));
    }
    if (promises.length) Promise.all(promises).then(() => location.reload());
    else entry.innerHTML = originalHTML;
  };
  textareas[0].addEventListener('keydown', e => {
    if (e.key === 'Escape') entry.innerHTML = originalHTML;
  });
  registerCloseOnOutside(entry, () => { entry.innerHTML = originalHTML; });
}

function editListItem(btn, type, oldText) {
  const entry = btn.closest('.list-item');
  if (entry.querySelector('.inline-edit')) return;
  const originalHTML = entry.innerHTML;

  const form = document.createElement('div');
  form.className = 'inline-edit';
  form.innerHTML = '<textarea class="inline-textarea">' + oldText.replace(/</g,'&lt;') + '</textarea>' +
    '<div class="inline-edit-actions"><button class="inline-save">Save</button></div>';

  entry.innerHTML = '';
  entry.appendChild(form);
  const textarea = form.querySelector('.inline-textarea');
  textarea.style.height = Math.max(50, textarea.scrollHeight) + 'px';
  textarea.focus();

  form.querySelector('.inline-save').onclick = () => {
    const newText = textarea.value.trim();
    if (newText && newText !== oldText) {
      const f = new FormData();
      f.append('old_text', oldText);
      f.append('new_text', newText);
      fetch('?action=edit_' + type, { method: 'POST', body: f }).then(() => location.reload());
    } else {
      entry.innerHTML = originalHTML;
    }
  };
  textarea.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.querySelector('.inline-save').click(); }
    if (e.key === 'Escape') entry.innerHTML = originalHTML;
  });
  registerCloseOnOutside(entry, () => { entry.innerHTML = originalHTML; });
}

function editAll(el, file, time, date, showDateEdit, currentSummary) {
  const entry = el.closest('.voice-entry') || el.closest('.list-item');
  if (entry.querySelector('.inline-edit')) return;
  const textEl = entry.querySelector('.voice-text, .clickable-title');
  const originalText = textEl ? textEl.textContent : '';
  const originalHTML = entry.innerHTML;

  // Fetch full text from server
  const form = document.createElement('div');
  form.className = 'inline-edit';
  let html = '';
  if (showDateEdit) {
    html += '<div class="inline-edit-row">';
    html += '<input type="date" class="inline-date" value="' + date + '">';
    html += '<input type="time" class="inline-time" value="' + time + '">';
    html += '</div>';
  }
  if (currentSummary) {
    html += '<label style="font-size:13px;color:rgba(255,255,255,0.4);margin-bottom:4px;display:block;">Summary</label>';
    html += '<input type="text" class="inline-summary" value="' + currentSummary.replace(/"/g,'&quot;') + '" style="width:100%;background:rgba(0,0,0,0.3);border:1px solid rgba(255,255,255,0.15);color:#e8e8ef;padding:10px 14px;border-radius:10px;font-size:16px;font-family:inherit;">';
    html += '<div class="inline-btn-row">';
    html += '<button class="inline-push-s" data-file="' + file + '" data-time="' + time + '">🚀 Push Summary</button>';
    html += '</div>';
    html += '<label style="font-size:13px;color:rgba(255,255,255,0.4);margin-bottom:4px;display:block;">Original</label>';
  }
  html += '<textarea class="inline-textarea"></textarea>';
  html += '<div class="inline-btn-row">';
  html += '<button class="inline-push-o" data-file="' + file + '" data-time="' + time + '">🚀 Push Original</button>';
  html += '</div>';
  if (currentSummary) {
    html += '<div class="inline-btn-row">';
    html += '<button class="inline-push-so" data-file="' + file + '" data-time="' + time + '" style="background:rgba(160,120,200,0.12);color:#a078c8;">🚀 Push Summary & Original</button>';
    html += '</div>';
  }
  // Check for answer panel
  const answerPanel = entry.querySelector('.answer-panel');
  const oldAnswer = answerPanel ? answerPanel.textContent.trim() : '';
  if (answerPanel) {
    html += '<label style="font-size:13px;color:rgba(255,255,255,0.4);margin:12px 0 4px;display:block;">AI Answer</label>';
    html += '<textarea class="inline-textarea inline-answer">' + oldAnswer.replace(/</g,'&lt;') + '</textarea>';
  }
  form.innerHTML = html;

  entry.innerHTML = '';
  entry.appendChild(form);

  // Load full text via fetch
  const textarea = form.querySelector('.inline-textarea');
  const fd = new FormData();
  fd.append('file', file); fd.append('time', time);
  fetch('?action=get_voice', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      textarea.value = data.text || originalText;
      textarea.style.height = Math.max(60, textarea.scrollHeight) + 'px';
    })
    .catch(() => {
      textarea.value = originalText;
      textarea.style.height = Math.max(60, textarea.scrollHeight) + 'px';
    });
  // Auto-size answer textarea
  const answerTaInit = form.querySelector('.inline-answer');
  if (answerTaInit) answerTaInit.style.height = Math.max(120, answerTaInit.scrollHeight) + 'px';
  if (form.querySelector('.inline-summary')) form.querySelector('.inline-summary').focus();
  else textarea.focus();

  function saveFirst() {
    const newText = textarea.value.trim();
    const newSummary = form.querySelector('.inline-summary') ? form.querySelector('.inline-summary').value.trim() : '';
    const f = new FormData();
    f.append('file', file); f.append('time', time);
    if (newText) f.append('new_text', newText);
    if (newSummary) f.append('new_summary', newSummary);
    return fetch('?action=edit_voice', { method: 'POST', body: f });
  }
  if (form.querySelector('.inline-push-s')) form.querySelector('.inline-push-s').onclick = function() { const btn = this; saveFirst().then(() => pushToServer(btn, true)); };
  form.querySelector('.inline-push-o').onclick = function() { const btn = this; saveFirst().then(() => pushToServer(btn, false)); };
  if (form.querySelector('.inline-push-so')) form.querySelector('.inline-push-so').onclick = function() { const btn = this; saveFirst().then(() => pushToServer(btn, 'both')); };
  function doSave() {
    const newText = textarea.value.trim();
    const newSummary = form.querySelector('.inline-summary') ? form.querySelector('.inline-summary').value.trim() : '';
    const promises = [];
    const f = new FormData();
    f.append('file', file); f.append('time', time);
    let hasChange = false;
    if (newText) { f.append('new_text', newText); hasChange = true; }
    if (newSummary && newSummary !== currentSummary) { f.append('new_summary', newSummary); hasChange = true; }
    if (hasChange) promises.push(fetch('?action=edit_voice', { method: 'POST', body: f }));
    if (showDateEdit) {
      const nd = form.querySelector('.inline-date').value;
      const nt = form.querySelector('.inline-time').value;
      if (nd !== date || nt !== time) {
        const f2 = new FormData();
        f2.append('file', file); f2.append('time', time); f2.append('new_date', nd); f2.append('new_time', nt);
        promises.push(fetch('?action=edit_voice_meta', { method: 'POST', body: f2 }));
      }
    }
    const answerTa = form.querySelector('.inline-answer');
    if (answerTa) {
      const newAnswer = answerTa.value.trim();
      if (newAnswer !== oldAnswer) {
        const f3 = new FormData();
        f3.append('task_name', currentSummary || originalText);
        f3.append('new_answer', newAnswer);
        f3.append('source', 'voice');
        f3.append('file', file);
        f3.append('time', time);
        promises.push(fetch('?action=edit_task_answer', { method: 'POST', body: f3 }));
      }
    }
    if (promises.length > 0) Promise.all(promises);
  }
  // Auto-save with debounce
  let saveTimer = null;
  function autoSave() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(doSave, 800);
  }
  form.querySelectorAll('input, textarea').forEach(el => el.addEventListener('input', autoSave));
  textarea.addEventListener('keydown', e => { if (e.key === 'Escape') { clearTimeout(saveTimer); entry.innerHTML = originalHTML; } });
  registerCloseOnOutside(entry, () => { clearTimeout(saveTimer); doSave(); entry.innerHTML = originalHTML; });
}

function reorderTask(text, dir, source, file, time) {
  const form = new FormData();
  form.append('text', text);
  form.append('dir', dir);
  form.append('source', source);
  if (file) form.append('file', file);
  if (time) form.append('time', time);
  fetch('?action=reorder_task', { method: 'POST', body: form }).then(() => location.reload());
}

function reorderShopping(text, dir, source, file, time) {
  const form = new FormData();
  form.append('text', text);
  form.append('dir', dir);
  form.append('source', source);
  if (file) form.append('file', file);
  if (time) form.append('time', time);
  fetch('?action=reorder_shopping', { method: 'POST', body: form }).then(() => location.reload());
}

function pushToServer(btn, useSummary) {
  btn.disabled = true;
  btn.textContent = '...';
  // Disable sibling push button if exists
  const parent = btn.parentElement;
  if (parent) parent.querySelectorAll('.push-item-btn').forEach(b => { b.disabled = true; });
  const file = btn.dataset.file || '';
  const time = btn.dataset.time || '';
  const task = btn.dataset.task || '';
  const form = new FormData();
  if (file) form.append('file', file);
  if (time) form.append('time', time);
  if (task) form.append('task', task);
  if (useSummary === 'both') form.append('use_summary', 'both');
  else if (useSummary !== undefined) form.append('use_summary', useSummary ? '1' : '0');
  fetch('?action=push', { method: 'POST', body: form })
    .then(r => r.json())
    .then(() => { btn.textContent = '✓'; btn.classList.add('done'); setTimeout(() => location.reload(), 500); })
    .catch(() => { btn.textContent = '!'; btn.disabled = false; });
}

function pushAction(btn) {
  const input = btn.parentElement.querySelector('.inline-action-input');
  const actionText = input ? input.value.trim() : '';
  if (!actionText) { input.focus(); return; }
  btn.disabled = true;
  btn.textContent = '...';
  const file = btn.dataset.file || '';
  const time = btn.dataset.time || '';
  const form = new FormData();
  form.append('file', file);
  form.append('time', time);
  form.append('use_summary', 'action');
  form.append('action_text', actionText);
  fetch('?action=push', { method: 'POST', body: form })
    .then(r => r.json())
    .then(() => { btn.textContent = '✓'; btn.classList.add('done'); setTimeout(() => location.reload(), 500); })
    .catch(() => { btn.textContent = '!'; btn.disabled = false; });
}

function queueForAI(btn) {
  btn.disabled = true;
  btn.textContent = '⏳ キューに追加中...';
  const file = btn.dataset.file || '';
  const time = btn.dataset.time || '';
  const task = btn.dataset.task || '';
  const instructionInput = btn.parentElement.querySelector('.ai-instruction');
  const instruction = instructionInput ? instructionInput.value.trim() : '';
  const form = new FormData();
  if (file) form.append('file', file);
  if (time) form.append('time', time);
  if (task) form.append('task', task);
  if (instruction) form.append('instruction', instruction);
  fetch('?action=queue_ai', { method: 'POST', body: form })
    .then(r => r.json())
    .then(() => { btn.textContent = '✅ キューに追加済み'; btn.style.opacity = '0.5'; })
    .catch(() => { btn.textContent = '!'; btn.disabled = false; });
}

// Health Log Editor
let _editorSaveTimer = null;
let _editorDate = '';

function openEditor() {
  document.getElementById('health-log-editor').style.display = '';
  const fd = new FormData();
  fd.append('date', '');
  fetch('?action=get_health_log', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      const sel = document.getElementById('editor-date');
      sel.innerHTML = '';
      (data.dates || []).forEach(d => {
        const opt = document.createElement('option');
        opt.value = d; opt.textContent = d;
        sel.appendChild(opt);
      });
      if (data.dates && data.dates.length > 0) {
        _editorDate = data.dates[0];
        loadHealthLog(_editorDate);
      }
    });
}

function closeEditor() {
  document.getElementById('health-log-editor').style.display = 'none';
}

function loadHealthLog(date) {
  _editorDate = date;
  const fd = new FormData();
  fd.append('date', date);
  fetch('?action=get_health_log', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      document.getElementById('editor-textarea').value = data.content || '';
      document.getElementById('editor-status').textContent = '';
    });
}

function editorAutoSave() {
  clearTimeout(_editorSaveTimer);
  document.getElementById('editor-status').textContent = '...';
  _editorSaveTimer = setTimeout(() => {
    const fd = new FormData();
    fd.append('date', _editorDate);
    fd.append('content', document.getElementById('editor-textarea').value);
    fetch('?action=save_health_log', { method: 'POST', body: fd })
      .then(() => { document.getElementById('editor-status').textContent = '✓ 保存済み'; })
      .catch(() => { document.getElementById('editor-status').textContent = '! エラー'; });
  }, 1000);
}

// Cmd+S / Ctrl+S to save
document.addEventListener('keydown', e => {
  if ((e.metaKey || e.ctrlKey) && e.key === 's') {
    e.preventDefault();
    if (document.getElementById('health-log-editor').style.display !== 'none') {
      clearTimeout(_editorSaveTimer);
      const fd = new FormData();
      fd.append('date', _editorDate);
      fd.append('content', document.getElementById('editor-textarea').value);
      fetch('?action=save_health_log', { method: 'POST', body: fd })
        .then(() => { document.getElementById('editor-status').textContent = '✓ 保存済み'; });
    }
  }
});

function sendPanelText(btn) {
  const input = btn.parentElement.querySelector('.panel-text-input');
  const text = input ? input.value.trim() : '';
  if (!text) { input.focus(); return; }
  btn.disabled = true;
  btn.textContent = '...';
  const form = new FormData();
  form.append('text', text);
  fetch('?action=add_health_note', { method: 'POST', body: form })
    .then(() => { btn.textContent = '✓'; input.value = ''; setTimeout(() => { btn.textContent = '送信'; btn.disabled = false; }, 1000); })
    .catch(() => { btn.textContent = '!'; btn.disabled = false; });
}

function addHealthEntry() {
  const text = document.getElementById('new-health').value.trim();
  const tag = document.getElementById('new-health-tag').value;
  if (!text) return;
  const form = new FormData();
  form.append('text', text);
  form.append('tag', tag);
  fetch('?action=add_health', { method: 'POST', body: form }).then(() => location.reload());
}

function addIdea() {
  const text = document.getElementById('new-idea').value.trim();
  if (!text) return;
  const form = new FormData();
  form.append('text', text);
  form.append('tag', 'idea');
  fetch('?action=add_health', { method: 'POST', body: form }).then(() => location.reload());
}

function addReadLater() {
  const text = document.getElementById('new-read-later').value.trim();
  if (!text) return;
  const form = new FormData();
  form.append('text', text);
  form.append('tag', 'read_later');
  fetch('?action=add_health', { method: 'POST', body: form }).then(() => location.reload());
}

document.getElementById('new-idea').addEventListener('keydown', e => { if (e.key === 'Enter') addIdea(); });
document.getElementById('new-health').addEventListener('keydown', e => { if (e.key === 'Enter') addHealthEntry(); });
document.getElementById('new-task').addEventListener('keydown', e => { if (e.key === 'Enter') addItem('task'); });
document.getElementById('new-shopping').addEventListener('keydown', e => { if (e.key === 'Enter') addItem('shopping'); });
</script>

</body>
</html>
