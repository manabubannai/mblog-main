# Apple Health → iCloud Drive 自動エクスポート設定

## Step 1: ショートカットを作成

iPhoneで「ショートカット」アプリを開く → + → 名前を「Health Export」にする

以下のアクションを上から順に追加：

### 1. 日付を取得
- アクション: 「日付」→ 「現在の日付」

### 2. 日付をフォーマット
- アクション: 「日付をフォーマット」
- フォーマット: カスタム → `yyyy-MM-dd`
- 変数名: formattedDate

### 3. 歩数を取得
- アクション: 「ヘルスケアサンプルを検索」
- タイプ: 歩数
- 開始日: 今日の開始
- 終了日: 今日の終了
- グループ分け: 日
→ 結果を「steps」に設定

### 4. アクティブエネルギーを取得
- アクション: 「ヘルスケアサンプルを検索」
- タイプ: アクティブエネルギー
- 開始日: 今日の開始
- 終了日: 今日の終了
- グループ分け: 日
→ 結果を「activeEnergy」に設定

### 5. 安静時心拍数を取得
- アクション: 「ヘルスケアサンプルを検索」
- タイプ: 安静時心拍数
- 開始日: 今日の開始
- 終了日: 今日の終了
- 並び順: 最新順
- 制限: 1
→ 結果を「restingHR」に設定

### 6. ワークアウトを取得
- アクション: 「ヘルスケアサンプルを検索」
- タイプ: ワークアウト
- 開始日: 今日の開始
- 終了日: 今日の終了
→ 結果を「workouts」に設定

### 7. テキストを作成（JSON）
- アクション: 「テキスト」
- 内容:
```
{"date":"formattedDate","steps":"steps","active_energy":"activeEnergy","resting_hr":"restingHR","workouts":"workouts"}
```
※ 各変数はショートカットのマジック変数として挿入

### 8. ファイルに保存
- アクション: 「ファイルを保存」
- 保存先: iCloud Drive > HealthExport フォルダ
- ファイル名: `health-formattedDate.json`
- 「上書き」をON

## Step 2: オートメーションを設定

1. ショートカットアプリ → 「オートメーション」タブ
2. 「+」→ 「時刻」
3. 毎日 22:00
4. 「すぐに実行」をON
5. ショートカット「Health Export」を選択
6. 完了

## Step 3: Mac側のパス確認

iCloud Driveに保存されたファイルは以下のパスに同期される:
```
~/Library/Mobile Documents/com~apple~CloudDocs/HealthExport/
```

テストエクスポート後に確認:
```
ls ~/Library/Mobile\ Documents/com~apple~CloudDocs/HealthExport/
```

## Step 4: fetch-apple-health.sh のパス修正

確認したパスで `.github/fetch-apple-health.sh` の ICLOUD_ALT を更新する。
