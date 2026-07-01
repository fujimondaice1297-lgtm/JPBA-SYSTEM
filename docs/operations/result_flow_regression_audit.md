# 大会成績方式別回帰監査

最終更新: 2026-07-02

## 2026-06-30 方式ルール確認メモ

この監査は、現DBに入っている大会データとPDF生成が壊れていないかを確認した記録である。ラウンドロビン、ステップラダー、シュートアウトのJPBA公式ルールを確定する資料ではない。

ラウンドロビンやダブルエリミネーションを実装・修正する場合は、`docs/operations/tournament_format_source_policy.md` に従い、JPBA公式大会要項PDF、公式成績PDF、現行サイトの速報・成績、既存DBデータの順に確認する。一般的な方式説明は補助資料に留める。

## 目的

`game_scores`、正式成績snapshot、方式別サービス、PDF出力が現在のDBで壊れていないかを確認する。

## 現DBで確認できる大会

| 大会ID | 大会 | result_flow_type | game_scores | current snapshots | 確認範囲 |
|---:|---|---|---:|---:|---|
| 10 | JPBAシーズントライアル 2025 オータムシリーズ C会場 | `prelim_to_semifinal_to_shootout_to_final` | 650 | 6 | シーズントライアル、シュートアウト、PDF |
| 11 | 大岡産業レディース THE OPEN 2025 | `prelim_to_rr_to_final` | 1628 | 10 | ラウンドロビン、ステップラダー、PDF |

## 2026-06-29 確認結果

| 方式 | 対象 | 結果 | メモ |
|---|---|---|---|
| ラウンドロビン | 大会ID 11 | OK | carry snapshotあり。8名、RR 8G、勝ち30P/引分15P、ポジションラウンド有効。1位サンプルは久保田彩花、5勝3敗、Bonus 150。 |
| ステップラダー | 大会ID 11 | OK | seed 3名、1回戦/優勝決定戦とも `done`。最終順位3名、優勝は久保田彩花。 |
| シュートアウト | 大会ID 10 | OK | seed 8名、3試合すべて完了。優勝は水野耕佑。 |
| シングルエリミネーション | 現DB | 未確認 | 現DBに `result_flow_type like %single_elimination%` の大会、`game_scores.stage = トーナメント` / `SE:%` 行がない。実データfixtureの復元または再作成が必要。 |
| 大会PDF | 大会ID 10 | OK | `%PDF` 生成、714646 bytes。 |
| 大会PDF | 大会ID 11 | OK | `%PDF` 生成、515847 bytes。 |

## 2026-07-01 PDF方式別Blade回帰

`php artisan tournament:pdf-regression` を追加し、方式別Blade分割後のPDF入口を一括確認できるようにした。

既存DBに存在する大会はそのまま使用し、標準・純シュートアウト・シングルエリミネーションはロールバックされる一時fixtureでPDF生成だけ確認する。fixtureはDBに残さない。

| case | mode | 対象 | 結果 | bytes | メモ |
|---|---|---|---|---:|---|
| season_trial_existing | season_trial | 大会ID 10 | OK | 714646 | シーズントライアル外枠 + シュートアウト表示 |
| round_robin_step_ladder_existing | standard_with_step_ladder | 大会ID 11 | OK | 632638 | 通常外枠 + RR/ステップラダー |
| standard_fixture | standard | 一時fixture | OK | 203210 | 通常トータルピンPDF |
| shootout_fixture | shootout | 一時fixture | OK | 399369 | 純シュートアウトPDF |
| single_elimination_fixture | single_elimination | 一時fixture | OK | 120953 | シングルエリミネーションPDF |

実行後、`tournaments` は大会ID 10/11のみで、一時fixtureが残っていないことを確認した。

## 2026-07-02 PDFスコアシート仕上げ後回帰

公式PDF風スコアシートの罫線、JPBAロゴ、会場/開催日/レーン表示、複数ページ分割を調整した後、同じ `php artisan tournament:pdf-regression` を実行した。

| case | mode | 対象 | 結果 | bytes | メモ |
|---|---|---|---|---:|---|
| season_trial_existing | season_trial | 大会ID 10 | OK | 712137 | スコアシート見出し、会場/開催日/レーン表示、2枚ごとのページ分割を確認 |
| round_robin_step_ladder_existing | standard_with_step_ladder | 大会ID 11 | OK | 670411 | 通常外枠 + RR/ステップラダー |
| standard_fixture | standard | 一時fixture | OK | 203333 | 通常トータルピンPDF |
| shootout_fixture | shootout | 一時fixture | OK | 399687 | 純シュートアウトPDF |
| single_elimination_fixture | single_elimination | 一時fixture | OK | 121282 | シングルエリミネーションPDF |

大会ID 10のPDFを `tmp/pdfs/tournament_10_score_sheet_check.pdf` として生成し、PopplerでPNG化した。`tournament_10_page-2.png` と `tournament_10_page-3.png` を確認し、ロゴ、外枠罫線、会場/開催日/レーン表示、ページ分割に表示崩れがないことを確認した。

## 2026-07-01 結果フロー一括回帰

`php artisan tournament:result-flow-regression` を追加し、PDFではなく方式別サービスの計算結果を一括確認できるようにした。

既存DBに実データがある方式はそのまま使用する。現DBにシングルエリミネーション大会と `SE:%` スコア行がないため、シングルエリミネーションだけはロールバックされる一時fixtureで、ブラケット生成、スコア反映、最終順位生成まで確認する。

| case | mode | 対象 | 結果 | メモ |
|---|---|---|---|---|
| round_robin_existing | round_robin | 大会ID 11 | OK | 8名、8G、1位 久保田彩花、5勝3敗、Bonus 150 |
| step_ladder_existing | step_ladder | 大会ID 11 | OK | seed 3名、1回戦/優勝決定戦とも `done`、優勝 久保田彩花 |
| shootout_existing | shootout | 大会ID 10 | OK | seed source `semifinal_total`、8名、3試合完了、優勝 水野耕佑 |
| single_elimination_fixture | single_elimination | 一時fixture | OK | 4名、3試合完了、順位 `1,2,3,3` |

実行後、`tournaments` は大会ID 10/11のみで、一時fixtureが残っていないことを確認した。一時fixtureの `tournament_id` はロールバック後もシーケンス上は実行ごとに変動するため、固定IDとして扱わない。

## 修正した警告

大会ID 10のPDF生成時に、`MatchScoreSheetImageService` の `imagefilledpolygon()` が非推奨引数警告を出していた。

2026-06-29に `num_points` 引数を外し、同じポリゴンを3引数形式で描画するよう修正した。修正後、大会ID 10のPDF生成は警告なしで `%PDF` まで生成できる。

## 次に必要な回帰

1. シングルエリミネーションの実データを現DBへ戻す、または小さなfixture大会を作る。
2. シングルエリミネーションで、速報画面、正式成績snapshot、`tournament_results` 同期、PDFトーナメント表まで再確認する。
3. 実データの紙成績表画像/PDFを使い、OCR/AI取込から `game_scores` 確定反映まで通し確認する。
