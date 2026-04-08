# mblog-main

## デプロイフロー

```
ローカル (mblog-main/)
  ↓ git push origin main
GitHub Actions (deploy.yml)
  ↓ SSH → VPS
VPS (root@78.47.81.145:/var/www/mblog)
  ↓ git fetch && git reset --hard origin/main
https://mblog.com に反映
```

- `git push` は確認不要。変更が完了したら自動で push してよい。

## ブログの構造

- `router.php` — `/slug` → `posts/slug.php` に自動ルーティング
- `header.php` — 共通ヘッダー・CSS。各ページで `require` する
- `footer.php` — 共通フッター
- `index.php` — トップページ。セクション別にリンク一覧
- `posts/` — 各記事の PHP ファイル

## Voice Memo 自動書き起こし

- Just Press Record（Apple Watch/iPhone）で録音 → iCloud Drive に自動同期
- 録音先: `~/Library/Mobile Documents/iCloud~com~openplanetsoftware~just-press-record/Documents/`
- `.github/transcribe.sh` で mlx-whisper（日本語）による書き起こし → `.github/voice-log.json` に保存
- `.github/dashboard.php` でローカルダッシュボード表示（`php -S localhost:8000 dashboard.php`）
- ダッシュボードからタスク・買い物リストの追加・削除が可能（CLAUDE.md を直接編集）

## Voice Memo 書き起こしルール

- 音声認識ミスがある場合、health-log.php の既存データや文脈から判断して修正してよい（例:「フローバックスセブン」→「ProBac7」、「豆腐さん」→「TOFUSAN」、「足はガンダ」→「Ashwagandha」）。聞き取りづらい固有名詞は過去のヘルスログを参照して正しい表記に変換する
- ただし推測に自信がない場合はユーザーに確認する
- 修正した場合は元の文と修正後の文を報告する
- 「〜を買う」「買い物リストに追加」等の内容は自動的に `tag: "shopping"` に分類する
- 「〜を考える」「〜を調べる」「〜を設定する」等のアクション系は `tag: "task"` に分類する
- 食事の記録は `tag: "food"` に分類する
- 健康・運動・サプリ関連は `tag: "health"` に分類する
- Substance関連は `tag: "substance"` に分類する
- アイデア・企画・記事ネタ等は `tag: "idea"` に分類する
- タグなしはそのまま思考メモとして保存する
- 書き起こし後、認識精度が低い・意味不明なエントリはユーザーに確認する。音楽のみ（【音楽】）やノイズ（同じ単語の繰り返し等）は自動で削除してよい
- 食材の重量メモが複数連続し、時間帯から同一食事と推定できる場合は1つのエントリにまとめる（例: 19時台の食材メモ群 → Dinnerとして統合）。health-log.phpのFoodフォーマットに合わせて「Dinner 19:17\n- 食材1, 食材2, ...」の形式にする
- 「残りを食べた」「追加で食べた」等の食事修正メモは、時間帯が近い食事エントリに統合する（別エントリにしない）。例: 20:57の「味噌汁の残り9割を食べた」→ 19:54のDinnerに追加。食事と無関係なメモ（体感・決意等）はhealthやthoughtに分類し、foodにしない
- 各エントリには `summary` フィールドを追加し、AIが内容を短く要約したタイトルを付ける（ダッシュボードではsummaryを表示、クリックで原文展開）
- 書き起こし後、アクション可能なエントリ（tag: task, health, idea等）には**必ず**AIが自動でリサーチし `answer` フィールドにフィードバックを付与する。1つも漏れなく全エントリに付与すること。ダッシュボードで展開表示される。shopping, food, substanceには不要
- Substance の表記は**英語で統一**し、health-log.php の既存フォーマットに合わせる。日本語で記録された場合も英語に変換する。例:
  - 「カンナビス0.6g」→「Cannabis 0.6g」、「インディカ」→「Indica」
- voice-log.jsonのsummaryとtextも**過去のhealth-logのフォーマットに従い、なるべく英語で記載**する（食材名、サプリ名は英語）。例: 「パイナップルを夕食後に食べた」→ summary: "Pineapple（after dinner）"、「ヨーグルト62g」→ "Yogurt 62g"
  - 「日光 5分」→「Sunlight 5min」、「朝食前」→「before breakfast」
  - 「1カプセル」→「1 capsule」、「筋トレ直後」→「post-workout」
  - 括弧内の補足も英語で統一: 「（朝食前）」→「（before breakfast）」「（筋トレ直後）」→「（post-workout）」
- 全てのエントリで health-log.php の過去の表記・フォーマットを参照し、一貫性を保つ
- サプリメント（ProBac7等）が食事メモに含まれている場合、食事とサプリを別エントリに分離し、サプリは `tag: "substance"` として保存する

## Health Log 更新ルール

- 2026-04-03以降の新フォーマット (以下を厳守). 全セクション見出しは `■` を使用:
  ```
  ■ Body Check
  - Readiness: X, Sleep Score: X, Total Sleep: XhXXm
  - Sleep Time: HH:MM~HH:MM, Deep: XXm, REM: XhXXm
  - Weight: XX.XX kg, Muscle: XX.XX kg, Body Fat: X.X%

  ■ Treatment
  - Meditation: HH:MM~HH:MM (HR: XXbpm / HRV: XXms)
  - Stretch: HH:MM~HH:MM (Avg HR: XXbpm / XXXkcal)
  - Outdoor Walk: HH:MM~HH:MM (Xmin / Avg HR: XXbpm / XXkcal)
  - Steps: X,XXX

  ■ Food

  Breakfast HH:MM : 食材1, 食材2, ...
  → Xkcal / P:Xg / F:Xg / C:Xg / Fiber:Xg / Zinc:Xmg / Mg:Xmg

  ■ Substances
  - サプリ正式名称 (タイミング)
  → サプリで補っている栄養素を記載 (Zinc, Mg, VitD, Creatine, EPA+DHA等)

  ■ Feedback from AI
  - 以下の3パート構成で栄養フィードバックを生成（詳細ルールは下記参照）

  ■ Thought
  - 思考メモ (原文のまま)
  ```
- データがないセクション (Stretch無し等) は見出しごと省略する
- Substancesは独立セクションではなく、各食事の下にサプリを記載する
- 括弧は必ず半角 () を使う（全角（）は禁止）
- 数字は必ず半角を使う（全角数字は禁止）
- チルダは半角 ~ を使う（全角〜は禁止）
- voice-log.json の食事データを health-log.php の該当日に反映する
- Food セクション: 以下のフォーマットを厳守する。食事名と時刻は同じ行、食材は次行に `- ` で記載。食事間は空行1行。
  ```
  Breakfast 10:00
  - 食材1, 食材2, ...

  Lunch 15:00
  - 食材1, 食材2, ...

  Dinner 19:00
  - 食材1, 食材2, ...
  ```
  食事名は Breakfast / Lunch / Dinner / Snack / Intra-Workout / Night / Post-Workout のいずれか。**各食事には必ず栄養素を計算して `→` 行で記載すること（省略禁止）**。形式: `→ Xkcal / P:Xg / F:Xg / C:Xg / Fiber:Xg / Zinc:Xmg / Mg:Xmg`
- 食事を追加するたびに、栄養フィードバック（■ Feedback from AI）も一緒に更新する。以下の3パートで構成:
  【パート1: BP Score】
  - Dave Asprey「The Bulletproof Diet」のRoadmapに基づき、その日の全食材を 🟢 Good / 🟡 OK / 🔴 Avoid に分類
  - 1日の総合スコアを 0-100 で算出（🟢食材の割合×100。🔴が1つでもあれば-10/個）
  - 表記: 「BP Score: XX/100」
  - 🔴食材がある場合は具体的な代替案を1行で提示（例: 「🔴 卵麺 → 米麺推奨」）
  - 判定基準:
    - 🟢 Good: 天然魚, 放牧卵, アボカド, ココナッツオイル/ミルク, ベリー類, 低レクチン野菜, Chia Seeds, EVOO, グラスフェッドバター, MCTオイル, Brazil Nuts
    - 🟡 OK: 白米, Oats, ナッツ類(Cashews等), 一般的な乳製品, Honey, Banana, 味噌(発酵大豆), 一般的な鶏肉
    - 🔴 Avoid: 小麦グルテン(麺・パン), 大豆(豆腐・納豆), 加工食品, 砂糖, 植物油(キャノーラ・大豆油), ピーナッツ
  【パート2: 栄養バランス + 改善提案】
  - 各食事追加時にP/F/C/Fiber/Zinc/Mgの過不足を評価
  - 次の食事で何を意識すべきかを簡潔に1-2行で提示
  - 普段の食事をちょっと変えるだけで改善できる提案を都度行う（例: 「Cashews→Macadamia Nutsでマイコトキシンリスク低減」）
  - 累計kcal/目標を表示（例: 「本日累計: 1,355/2,700kcal (50%)」）
  - Sleep Score/Readinessが低い場合、食事との関連を指摘
  【パート3: 血液データとの相関チェック】
  - ユーザーの血液検査結果（2026-04-02時点）と照合し、食事が検査値に与える影響を指摘:
    - TSH 3.66（やや高め）: 大豆製品・グルテンの甲状腺への影響を監視
    - Cortisol 15.5（上寄り）: 穀物の抗栄養素、カフェイン、就寝前の食事タイミングとの関連
    - Testosterone 705: フィトエストロゲン（大豆）の影響、亜鉛・脂質の摂取量との関連
    - hs-CRP 0.24（優秀）: 炎症リスク食材（グルテン・植物油・砂糖）の摂取頻度を監視
    - Vitamin D 34.5（ギリギリ充足）: 日光浴の有無とサプリ摂取の追跡
  - 該当がある日のみ記載（毎日全項目を書く必要はない）。血液検査が更新されたらリファレンス値も更新する
  【共通ルール】
  - 1日1回、ヘルスログ全体を振り返り、ユーザーが気づいていない健康リスクや改善点をリマインド
  - フィードバックは簡潔に。1パートあたり1-3行。冗長な説明は不要
  - チェンマイ固有の環境リスクも考慮（食材保存温度、市場の食材品質、外食の油の質等）
- 食材は「サーモン丼（米171g）」のような料理名でまとめず、「Salmon 刺身 8切れ, Rice 171g」のように食材を個別にシンプルに記載する。過去ログのフォーマットに合わせる
- 食材メモを統合する際、複数のボイスメモの内容を正確に反映する。後から「食べなかった」「やめた」「残した」等の修正メモがあった場合、該当食材をリストから完全に削除する（「※残した」等の注釈を付けて残すのではなく、食材自体を消す）。統合時に元のボイスメモ全てを**時系列で最後まで**確認し、修正・取消がないか必ずチェックする（例: 13:57「卵3つと納豆1パックとキムチ25g」→ 14:33「納豆とキムチは残した」→ 納豆とキムチは記載しない）
- 日付は必ずサーバーのバンコク時間（Asia/Bangkok, UTC+7）で判定する。`TZ=Asia/Bangkok date '+%Y-%m-%d'` を使用。日本時間やUTCと混同しないこと
- 上記フィードバックルールに統合済み（1日1回のリマインド、改善提案は Feedback from AI セクション内で実施）
- voice-log.jsonを一括書き換えする際、既に整理済みのエントリを誤って削除しないこと。array_filterで除外する場合は、未処理の生データのみを対象にし、既にsummary/tagが付与されたエントリは絶対に除外しない。書き換え前後で必ずエントリ数を比較確認する
- ボイスメモ同期後、当日のhealth-log.phpエントリが存在しない場合は、Oura/Withingsデータを取得してエントリを自動作成する。既存日のエントリが消えていないかも確認する
- 食事メモの時間が離れている場合（筋トレ直後のプロテイン19:54 → 夕食20:37など）は1つにまとめず、別の食事エントリとして分けて記載する
- 「筋トレ直後」「ワークアウト後」等のメモは必ず `Post-Workout` カテゴリで記載する（DinnerやSnackに混ぜない）。フォーマット例: `Post-Workout 19:27\n- TOFUSAN プロテインドリンク 250ml`。TOFUSANの正式名称は過去ログ参照（`TOFUSAN プロテインドリンク 250ml` または `TOFUSAN No Sugar Added Organic Soymilk 250ml`）
- 「半分残した」「少し残した」等の食事修正メモが来たら、該当食事の栄養計算を修正し（食材名の後に「※半分残し」等を明記）、Daily Summaryも再計算する。修正メモ自体はFoodセクションに別行として追加しない（既存の食事行に反映するだけ。重複記載禁止）
- **食事をhealth-logに追加・pushした時は、必ず同時に `→` 行で栄養計算を記載し、Daily Summaryも更新する。栄養計算なしの食事行は絶対に放置しない。これはダッシュボードのpush処理、Claude Codeでの手動追記、どちらの場合でも同様**
- 「昨日の味噌汁の残り9割」のような曖昧な表記は禁止。元の材料から実際の分量を計算し、食材を個別に記載する（例: 味噌汁（豆腐 120g, エリンギ 54g, ...））。残り物の追跡は元の量×残り割合で計算する
- ヘルスログは未来の自分が振り返るためのデータ。「残り半分」「4割」「昨日の残り」等の相対的な表記は禁止。計算後の実際のグラム数のみ記載する（例: ×「Salmon 焼き 約85g (212gの4割)」→ ○「Salmon 焼き 85g」）。元の量や割合の情報はログに残す価値がない
- 食材のグラム数が記載されていない場合、前後の時間帯のボイスメモに重量が記録されていないか確認する。同じ食材の計量メモが近い時間にある場合は統合して反映する（例: 20:44「バナナ260g皮付き」→ 21:22「バナナ2本食べる」→ 統合して「バナナ 2本（260g 皮付き）」）
- Daily Summary: **食事データを追加・更新したら必ずDaily Summaryも再計算して更新すること（省略禁止）**。目標値: kcal:2,700 / P:126g / F:75g / C:380g / Fiber:28g / Zinc:11mg / Mg:400mg / VitD:2,000IU / Cr:5g。バーは10ブロック（▓░）でパーセンテージを視覚化。100%超は✅、50%以下は📉を付与。**サプリメントの栄養素（Mg, Zinc, VitD等）もDaily Summaryに含めること**。Thorne Multi-Vitamin Elite AM 2カプセル = Mg:200mg / Zinc:7.5mg / VitD:1,000IU。見出しは「■ Daily Summary（Food + Supplements）」とする
- 行動ログ（マナブの1日）: 毎日の会話終了時またはpush時に生成する。データソース: voice-log.json（ボイスメモ時刻・内容）、oura-today.txt（就寝・起床）、withings-today.txt（計測時刻）、git log（作業内容）、health-log.phpの食事データ。時刻順に「HH:MM 行動内容。」の形式で記載。最終行に「この日の一言：」を付ける
- 栄養計算は卵1個 = 約71kcal / P:6.3g / F:4.8g / C:0.4g / Zinc:0.6mg / Mg:6mg を基準にする
- health-log.php のセクション順序は以下を厳守する: Body → Sleep → Stretch → Meditation → Workout → Food → Substances → Daily Summary → タスク → Idea → Thought → マナブの1日（最下部）
- Health Notes（体感メモ）は独立セクションではなく、Substancesセクションの直下に `*メモ内容` の形式で記載する（`- ` ではなく `*` 始まり、改行なしでSubstancesの最終行の次行に書く）。例: `*ProBac7（4-5日目）：驚くほど効果を実感。腸は改善途中。`
- Substancesに記録がない日で、ヘルスノート（`*`行）もない場合は `—` を記載する。ヘルスノートがある場合は `—` は不要（`*`行のみ）
- Substancesは特に指示がない限り、前日と同じデータを入れる（Cannabis等の嗜好品は除く。サプリのみ前日踏襲）
- データがないセクション（Stretch, Workout等）は見出しごと省略する（「■ Stretch (Oura Ring)\n—」のように空のセクションを書かない）
- Push to Server 時もこの順序に従ってセクションに挿入する
- health-log.php に記載する際は、必ず過去のログを確認し、既存のフォーマットに合わせて記載する（例:「5分強ぐらいの日光浴完了」→「日光 5分（時刻）（VitD 約1,000IU）」）
- ボイスメモの口語表現をそのまま記載せず、過去ログの表記スタイルに変換してから書き込む
- Thought セクションはユーザーから渡されたテキストを**完全にコピー&ペースト**する。改行位置・装飾・記号・インデント等を一切変更しない。箇条書きへの変換、要約、言い換え、フォーマット統一は全て禁止。渡されたテキストをそのまま挿入すること
- Thoughtセクションへのpush時、時刻は付与しない（Substancesは時刻あり、Thoughtは時刻なし）
- 参照URLがある行は `- テキスト https://url` の形式で書く。JSが自動でテキスト部分をリンク化し、URLは非表示になる
- セクション間の空行は1行のみ。2行以上の連続空行は禁止（`■ セクション名` の前は空行1行）。同一セクション内の箇条書き間に空行は入れない。ダッシュボードのPush処理でも同様に遵守する
- `</pre>` は最終行の直後に改行なしで置く（最終行と同じ行、または改行なしで直結）

## Oura Ring データ取得

- `.github/fetch-oura.sh [YYYY-MM-DD]` で Oura API からデータ取得
- 出力先: `.github/oura-today.txt`（フォーマット済み）
- トークンは `.env` の `OURA_TOKEN` に保存（.gitignore 済み）
- 取得データ: Sleep Score, Readiness, Total/Deep/REM, HRV, HR, 就寝・起床時刻, Meditation, Stretch
- Meditation/Stretchデータはsessionエンドポイントから取得（end_dateは翌日を指定。Oura APIはend_date排他的）
- launchd で毎日23:00に自動取得（fetch-oura → sync-voice-notes → daily-push の順）
- health-log.php の Sleep, Stretch, Meditation セクションと行動ログの就寝・起床に反映する。oura-today.txtのデータは必ず全セクション反映すること

## 会話開始時の初期同期ルール

- ユーザーとの会話開始時（その日最初のやりとり）に以下を自動実行する:
  1. `fetch-oura.sh` でOura Ringデータ取得
  2. `fetch-withings.sh` でWithingsデータ取得
  3. `transcribe.sh` でボイスメモ書き起こし
  4. 取得したデータを当日のhealth-log.phpに反映（Body, Sleep, Stretch, Meditation）
  5. ボイスメモの整理・タグ付け・AIフィードバック付与

## daily-push.sh

- `.github/daily-push.sh` — 変更があれば自動で `git add -A && commit && push`
- 変更がなければ何もしない

## スケジュール表示（show_after）

- タスクリスト・買い物リストで `@YYYY-MM-DD` を末尾に付けると、その日付までScheduledセクションに非表示になる
- 日付を過ぎたら通常のリストに表示される
- 例: `- [ ] 遺伝子検査を受ける @2026-04-03` → 4/3まで非表示、4/3以降に表示
- voice-log.jsonの `show_after` フィールドも同様の仕組み

## AIタスクキュー

- `.github/task-queue.json` にダッシュボードの「⚡ AIで実行」ボタンから追加されたタスクが蓄積される
- ユーザーが「キューを処理して」と指示したら、`task-queue.json` の `status: "pending"` のタスクを順に実行し、完了後 `status: "done"` に更新する
- 各タスクの `task`（タスク名）、`detail`（原文）、`answer`（AI回答）、`instruction`（ユーザー指示）を参照して実行内容を判断する
- タスク完了時は `result` フィールドに実行結果の概要を記載する。ダッシュボードの「✅ 実行済み」ボタンの下に表示される
- ステータス: `pending`（実行待ち・黄色）→ `done`（実行済み・緑）。doneのタスクはボタン無効化
- 1日1回、会話の開始時にキューに `pending` タスクがないか確認し、あれば自動で実行する

## 買い物リスト

- [ ] 三面鏡
- [ ] Tofusanドリンク（MacroのEC）
- [ ] タイの臭い醤油
- [ ] 梅干し
- [ ] だし昆布（フジッコ）
- [ ] 間接照明
- [ ] お手拭き

## タスクリスト

- [ ] 遺伝子検査と血液栄養検査を受ける @2026-04-03
- [ ] Kindle送信アプリのSMTP設定＋動作確認（~/kindle-sender/） @2026-04-13
- [ ] Kindle届いたら「Outlive」Peter Attia を購入する @2026-04-13

## 毎月の固定タスク

- [ ] 家政婦さんの給料支払い（毎月1日）
