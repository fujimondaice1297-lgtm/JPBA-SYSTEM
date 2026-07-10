# 現行JPBAサイト 個人成績取込 - 2026-07-10

## 目的

`Pro.csv` には、獲得タイトル明細、公認パーフェクト、800シリーズ、7-10スプリットメイド、公式戦通算成績の数値が含まれていなかった。

現行JPBAサイトはリニューアル後に閉鎖または更新停止される前提のため、継続同期ではなく、現行サイトに載っているプロフィール数値を一度だけ写し取る方針にした。

## CSV確認

確認した `Pro.csv` に含まれていた主な情報:

- ライセンスNo
- 氏名
- 地区
- 所属先
- 用品契約
- 資格系
- 永久シード
- A級ライセンス取得番号

一方、以下は含まれていなかった:

- 獲得タイトル明細
- 公認パーフェクト明細
- 800シリーズ明細
- 7-10スプリットメイド明細
- 公式戦総ゲーム数
- 公式戦トータルピン
- 公式戦総賞金額
- 公式戦通算アベレージ

## 追加した構造

`pro_bowlers` に現行JPBAサイト取込用カラムを追加した。

- `official_win_count`
- `official_total_games`
- `official_total_pins`
- `official_total_prize_money`
- `official_career_average`
- `official_profile_url`
- `official_profile_imported_at`
- `official_profile_import_error`

既存の褒章集計カラムも取込対象にした。

- `perfect_count`
- `eight_hundred_count`
- `seven_ten_count`
- `award_total_count`

`official_win_count` は既存の `titles_count` / `has_title` にも反映する。ただし現時点では、現行サイト上の「優勝回数」集計を反映している段階であり、優勝大会名ごとの `pro_bowler_titles` 明細の完全復元は次段階とする。

## 追加したコマンド

```bash
php artisan jpba:import-official-player-profile-stats
```

主なオプション:

- `--force`: DBを実更新する。未指定時はドライラン。
- `--license=M00000018`: 特定ライセンスのみ取込。
- `--limit=100`: 件数制限。
- `--missing-only`: `official_profile_imported_at` が空の選手だけ取込。
- `--all-visible`: 通常の現役検索対象ではなく、表示対象全体を取込。
- `--sleep-ms=50`: 現行サイトへの連続アクセス間隔。
- `--json`: JSONレポート出力。

## パーサー方針

- JPBA公式選手詳細ページは `https://www.jpba1.jp/player1/detail.html?id={license_no}` 形式。
- 公式戦記録の集計表から以下を取得する。
  - 優勝回数
  - 総ゲーム数
  - トータルピン
  - 総賞金額
  - 通算アベレージ
  - 公認パーフェクト
  - 800シリーズ
  - 7-10スプリットメイド
- `41 (シーズントライアル 1)` のような注記付き値は、先頭数値だけを採用する。
- 後続の年度別/大会別テーブルに同じヘッダー名があるため、先に取得した集計値を後続テーブルで上書きしない。
- 現行サイト側が空欄の値は空欄として保持する。

## 実行結果

DBカラム追加:

```bash
php artisan migrate
```

サンプル実取込:

```bash
php artisan jpba:import-official-player-profile-stats --license=M00000018 --force --sleep-ms=0 --json
```

矢島純一（M00000018）の確認値:

```json
{
    "license_no": "M00000018",
    "name_kanji": "矢島純一",
    "official_win_count": 41,
    "official_total_games": 15516,
    "official_total_pins": 3293826,
    "official_total_prize_money": 127192700,
    "official_career_average": "212.28",
    "perfect_count": 28,
    "eight_hundred_count": 8,
    "seven_ten_count": 0,
    "award_total_count": 36,
    "titles_count": 41,
    "has_title": true
}
```

公開プロフィール `http://127.0.0.1:8000/players/12374` で以下を確認した。

- `公式戦記録` が表示される
- `優勝回数` が表示される
- `公認パーフェクト` が表示される
- `3,293,826` が表示される

## 一括取込結果

現役・公開対象のM/Fライセンス選手:

```json
{
    "active_visible_mf": 862,
    "imported": 862,
    "remaining": 0,
    "errors": 0
}
```

取込バッチ:

- 1件サンプル取込: 成功
- 100件バッチ取込: 成功、エラー0
- 200件バッチ取込: 成功、エラー0
- 200件バッチ取込: 成功、エラー0
- 200件バッチ取込: 成功、エラー0
- 161件バッチ取込: 成功、エラー0

合計862件取込済み。

## 空欄値

以下2名は現行JPBAサイト側で優勝回数と通算AVGが空欄だったため、DBでも空欄として保持した。

- `F00000636` 網代羅夢
- `M00001505` 久冨木広

両名とも、現行サイト側は総ゲーム数0、トータルピン0、総賞金額0、公認パーフェクト/800/7-10は空欄。

## 検証結果

- `php -l`: 追加/変更PHPファイルはすべてOK
- `php artisan view:cache`: OK
- `php artisan route:list --except-vendor`: OK
- `php artisan public:parity-audit`: 公開12ページすべて200/OK、missing assets 0
- 公開プロフィールHTML確認:
  - 矢島純一（M00000018）: 公式戦記録、優勝41、総ゲーム数15,516、トータルピン3,293,826、公認パーフェクト28を確認
  - 網代羅夢（F00000636）: 優勝回数は現行サイト同様に空欄扱い、総ゲーム数0等を確認
  - 久冨木広（M00001505）: 優勝回数は現行サイト同様に空欄扱い、総ゲーム数0等を確認
- ブラウザで `http://127.0.0.1:8000/players/12374` を表示し、公式戦記録セクションの配置と数値表示を確認した。

## 次段階

- 次段階として、年度別成績ページから `1位` の大会を拾い、`pro_bowler_titles` 明細を復元するか検討する
