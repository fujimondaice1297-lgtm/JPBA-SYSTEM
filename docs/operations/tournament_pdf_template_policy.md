# 大会PDFテンプレート運用方針

更新日: 2026-07-01

## 大会方式ルールの扱い

PDFの方式別文言や表示項目は、方式名だけで固定しない。ラウンドロビン、ダブルエリミネーションなどは大会ごとの要項で、ゲーム数、通過人数、ボーナスポイント、リセット決勝、順位決定方法が変わる可能性がある。

実装時は `docs/operations/tournament_format_source_policy.md` を参照し、JPBA公式資料と既存DBデータで確認できた設定を `result_flow_type`、方式別設定JSON、snapshotの `calculation_definition` からPDFへ渡す。

## 目的

大会ごとにBladeを直接手修正する運用をやめ、DB設定・共通Controller・方式別partialで公式PDFを再現する。  
見た目の微調整が必要な場合も、大会ID専用Bladeや大会名条件分岐を増やさない。

## 現行構成

大会成績PDFは `TournamentResultController::exportTournamentPdf()` から出力する。

- 共通入口: `resources/views/tournament_results/pdf.blade.php`
- 共通文脈: `resources/views/tournament_results/pdfs/partials/context.blade.php`
- 標準: `resources/views/tournament_results/pdfs/standard.blade.php`
- シーズントライアル: `resources/views/tournament_results/pdfs/season_trial.blade.php`
- シュートアウト: `resources/views/tournament_results/pdfs/shootout.blade.php`
- シングルエリミネーション: `resources/views/tournament_results/pdfs/single_elimination.blade.php`
- snapshot単体PDF: `resources/views/tournament_results/pdfs/snapshot_score.blade.php`

`context.blade.php` が `tournaments.result_flow_type` と大会設定から `finalFormat` / `pdfMode` を決める。

## 正本の分担

- 大会基本情報: `tournaments`
- 大会進行方式: `tournaments.result_flow_type`
- 方式別設定: `shootout_settings` / `single_elimination_seed_settings` / 将来の `double_elimination_settings`
- 途中・正式成績: `tournament_result_snapshots` / `tournament_result_snapshot_rows`
- 最終成績: `tournament_results`
- スコアシート表示: `tournament_match_score_sheets`
- 公開PDF・外部資料: `tournament_files`

## 禁止する運用

- 大会IDや大会名ごとの専用Bladeを作る。
- `@if ($tournament->id === ...)` のような大会個別条件をPDF Bladeに増やす。
- 公式PDFの文言違いをBladeに直書きする。
- 既存の方式partialに別方式の文言や図を混ぜる。

## 追加・変更が必要な場合の手順

1. まず既存DB/JSON設定で表現できるか確認する。
2. 表現できない場合は、大会個別Bladeではなく、共通のDBカラムまたは設定JSONキーを追加候補にする。
3. `TournamentResultController` で方式別のデータ配列を作る。
4. 図が必要なら `*BracketImageService` を追加する。
5. Bladeは方式別partialとして追加する。
6. `context.blade.php` の `finalFormat` / `pdfMode` 判定へ接続する。
7. PDF共通表示ルールと回帰監査資料を更新する。

## 方式別partialの責務

| partial | 責務 |
|---|---|
| `result_page` | 最終成績、賞金、ポイント、基本ヘッダー |
| `snapshots` | 予選、準決勝、ラウンドロビンなど途中成績 |
| `score_sheets` | 公式スコアシート画像 |
| `shootout_pages` | シュートアウト図、試合順、スコア表 |
| `single_elimination_pages` | トーナメント表、seed/BYE、レーン表示 |
| `step_ladder_pages` | ステップラダー図、勝者ルート |
| `double_elimination_pages` | 将来追加。勝者側/敗者側/グランドファイナル/リセット決勝 |

## 表示差分の保存先

| 表示差分 | 保存先 |
|---|---|
| 大会タイトル、会場、日付 | `tournaments` |
| 予選/準決勝/決勝人数やG数 | 方式別設定JSON |
| トーナメントのseed/BYE | 方式別設定JSON |
| レーン表示 | 方式別設定JSONの `lane_settings` |
| 公式スコアシート | `tournament_match_score_sheets` |
| PDF添付・外部資料 | `tournament_files` |
| 大会ページ右側の速報/結果カード | `sidebar_schedule` / `result_cards` / `simple_result_pdfs` |

## 回帰確認の最低ライン

- `php artisan tournament:pdf-regression` が全ケースOKになる。
- `%PDF` で出力できる。
- PHP warning / deprecated warning が出ない。
- 別方式の文言が混入しない。
- 氏名、期表示、ライセンス下4桁、賞金欄が枠内に収まる。
- 図、スコアシート、途中成績、最終成績のいずれかが欠落していない。
- `docs/operations/result_flow_regression_audit.md` に確認対象と結果を残す。

`tournament:pdf-regression` は、現DBにあるシーズントライアル / RR・ステップラダー大会を使い、現DBにシングルエリミネーション大会がある場合は `single_elimination_existing` も確認する。標準 / 純シュートアウト / シングルエリミネーションの一時fixture確認も維持する。

## 今後の追加候補

- `double_elimination_pages.blade.php`
- `DoubleEliminationBracketImageService`
- `TournamentPdfModeService` のようなPDFモード判定専用サービス
- 大会設定JSONのUI化
- PDF回帰確認用の小さなfixture大会
