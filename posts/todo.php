<?php
$page_title = 'Todo — manablog';
$page_description = '買い物リスト・タスクリスト';
require dirname(__DIR__) . '/header.php';
?>

<a href="/"><img src="/img/logo.png" alt="manablog" class="logo"></a>
<h1 class="title">Todo</h1>

<style>
.todo-section { margin-bottom: 40px; }
.todo-section h2 { font-size: 20px; font-weight: 600; margin-bottom: 16px; }
.todo-list { list-style: none; padding: 16px 20px; background: #f8f8f8; border: 1px solid #e8e8e8; font-size: 15px; line-height: 2; }
.todo-list li { display: flex; align-items: baseline; gap: 8px; }
.todo-list li::before { content: "☐"; flex-shrink: 0; }
.todo-list li.done::before { content: "☑"; color: #999; }
.todo-list li.done { color: #999; text-decoration: line-through; }
</style>

<div class="todo-section">
<h2>🛒 買い物リスト</h2>
<ul class="todo-list">
<li>三面鏡</li>
<li>Tofusanドリンク（MacroのEC）</li>
<li>タイの臭い醤油</li>
<li>梅干し</li>
<li>だし昆布（フジッコ）</li>
<li>間接照明</li>
<li>家政婦さんの座りやすい椅子（暖炉室用）</li>
</ul>
</div>

<div class="todo-section">
<h2>📋 タスクリスト</h2>
<ul class="todo-list">
<li>水道代の支払い</li>
<li>Apple Watch × HIIT心拍数測定方法を調べる</li>
<li>Claude Code と Apple ヘルスケアを連携する</li>
<li>Claude Code と Withings を連携する</li>
</ul>
</div>

<?php require dirname(__DIR__) . '/footer.php'; ?>
