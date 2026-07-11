# 公式タイトル明細復元の安全運用メモ（2026-07-11）

## 目的

現行JPBAサイトに掲載されている過去大会・シーズントライアル優勝情報を、プロ個人のタイトル明細へ復元する。

最重要条件は以下。

- `pro_bowlers` の公式集計値と `pro_bowler_titles` の明細件数をずらさない
- 大会ページから拾ったタイトル年月日と、個人プロフィールに表示されるタイトル年月日をずらさない
- 途中まで拾えた情報を、未完成のまま公開プロフィールへ出さない

## 今回追加した仕組み

- `official_title_import_candidates` を追加し、公式サイトから拾ったタイトル候補を一度ここへ保存する
- `pro_bowler_titles` に `source_url` / `source_label` を追加し、どの公式ページから作成した明細か追跡できるようにした
- `jpba:import-official-title-history` を追加した
  - 既定はドライラン
  - `--force` で候補テーブルへ保存
  - `--promote` で、集計件数と候補明細件数が完全一致する選手だけ `pro_bowler_titles` へ昇格

## 昇格ルール

シーズントライアルは `season_trial_win_count`、通常タイトルは `official_win_count` を公式集計値として扱う。

候補明細数が公式集計値と完全一致し、かつ既存明細が未登録の場合のみ本登録する。

例外:

- 公式集計値が2件なのに候補が1件しかない選手は、候補保存のみで本登録しない
- 既に同カテゴリの明細がある選手は、自動上書きや自動追加を行わない
- 不一致は `promotion_blocked` として記録し、手動確認または追加ソース取得へ回す

## 2026サマーシリーズでの確認

対象URL:

`https://www.jpba.or.jp/information/tournament/tournament2026/ST_Summer/ST_Summer2026.html`

取得候補:

- 江川司 / M00001400 / A会場 / 2026-07-02
- 市原竜太 / M00000978 / B会場 / 2026-07-01
- 安里秀策 / M00001423 / C会場 / 2026-07-07

DB確認結果:

- 候補3件を `official_title_import_candidates` へ保存
- 江川司は `season_trial_win_count = 1`、候補1件のため本登録
- 市原竜太は `season_trial_win_count = 1`、候補1件のため本登録
- 安里秀策は `season_trial_win_count = 2`、候補1件のみのため本登録せず候補止まり
- `pro_bowler_titles` の `source_url` 付き本登録は2件
- 公開プロフィール内部レンダリングで、市原竜太・江川司にはタイトル名が表示され、候補止まりの安里秀策には表示されないことを確認
- 同じURLを `--force --promote` で再実行しても、新規昇格0件で重複登録されないことを確認

検証:

- `php -l` 対象5ファイル OK
- `php artisan view:cache` OK
- `php artisan route:list --except-vendor` OK
- `php artisan public:parity-audit` OK
- `/players/13334` / `/players/13756` / `/players/13779` の内部レンダリングでタイトル表示有無を確認

確認コマンド:

```bash
php artisan jpba:import-official-title-history --url=https://www.jpba.or.jp/information/tournament/tournament2026/ST_Summer/ST_Summer2026.html --sleep-ms=0 --json
php artisan jpba:import-official-title-history --url=https://www.jpba.or.jp/information/tournament/tournament2026/ST_Summer/ST_Summer2026.html --force --promote --sleep-ms=0 --json
```

## 次の候補

- 安里秀策のもう1件のシーズントライアル優勝元を公式サイトから特定する
- JPBA公式大会ページの年度別一覧をたどり、同じ候補方式でタイトル明細を増やす
- PDF最終成績からしか優勝者が取れない大会は、PDF抽出を別段階で追加する
- 個人プロフィールの年度別出場大会で1位行が取れる場合は、公式大会ページと突合して候補化する
- `docs/db/columns_public.csv` は現DBからの再生成が別途必要なため、DB資料更新は次のDB辞書更新フェーズで行う
