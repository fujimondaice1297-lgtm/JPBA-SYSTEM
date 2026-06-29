# 大会成績方式別回帰監査

最終更新: 2026-06-29

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

## 修正した警告

大会ID 10のPDF生成時に、`MatchScoreSheetImageService` の `imagefilledpolygon()` が非推奨引数警告を出していた。

2026-06-29に `num_points` 引数を外し、同じポリゴンを3引数形式で描画するよう修正した。修正後、大会ID 10のPDF生成は警告なしで `%PDF` まで生成できる。

## 次に必要な回帰

1. シングルエリミネーションの実データを現DBへ戻す、または小さなfixture大会を作る。
2. シングルエリミネーションで、速報画面、正式成績snapshot、`tournament_results` 同期、PDFトーナメント表まで再確認する。
3. 通常トータルピン方式のみの大会fixtureを用意し、通常PDFにシーズントライアル/シュートアウト/トーナメント専用文言が混入しないことを確認する。
