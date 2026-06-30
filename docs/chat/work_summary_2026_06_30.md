# 2026-06-30 作業総括

このメモは、次の作業へ進む前に、Codexがここまで進めた内容を一箇所で把握できるようにまとめたもの。

## 現在の状態

- 作業対象は本番 `https://www.jpba1.jp/` ではなく、ローカル/プロトタイプの Laravel 版 JPBA SYSTEM。
- 目的は、現行サイトの見た目と公開導線を保ちながら、DB・PHP・運用手順を整理し、手作業運用を自動化へ寄せること。
- この総括を作成する直前のGitHub `main` 反映済みコミットは `3c57537`。
- 作業後の通常差分はなし。未追跡の `storage/backups/` はバックアップ/投入スクリプト置き場としてGit管理外のまま維持する。
- Active Backlogの未チェックは15件。

## 進めた主な作業

### 1. 古い未チェックの棚卸し

- 古い履歴中の未チェック、重複、後続候補を整理した。
- 必要な候補だけを `docs/chat/progress_board.md` 末尾の Active Backlog に集約した。
- `docs/chat/unchecked_inventory.md` を作成し、棚卸しの根拠を残した。
- 2026-06-23以降にCodexが直接進めた作業はチェック済み扱いでよい方針にした。

### 2. スコア取込/OCR運用の土台

- CSV、Excel貼り付け、写真/PDF原本、OCR JSON、OCR/AI出力貼り付けを、直接 `game_scores` へ入れず一時取込する方針に整理した。
- `score_import_batches`、`score_import_rows`、`score_import_row_candidates`、`score_import_operation_logs` を使う運用を整えた。
- 取込詳細画面で、候補選択、行修正、除外、確定反映、操作履歴を確認できるようにした。
- OCR/AI変換結果の警告、信頼度、変換元行、抽出元行を見やすくした。
- 実OCRエンジン接続境界を `ScoreImportOcrEngineBoundaryService` として固定した。
- 運用手順書 `docs/operations/score_import_runbook.md` を追加した。

### 3. 現行JPBAサイト踏襲の公開導線

- 公開トップを現行JPBA1の構成に寄せ、上部メニュー、更新履歴、大会枠、INFORMATION、バナー、外部リンク、SNS、フッター導線を整理した。
- INFORMATIONカテゴリを現行サイト実態に合わせた。
- `/about`、`/schedule`、`/players`、`/players/{id}`、`/tournament`、`/tournament/{tournament}`、`/instructor`、`/protest`、`/topics`、`/contact`、`/media`、`/commerce`、`/privacy` を公開ページとして追加/整理した。
- 現行URL互換として、旧URLをローカル公開ページへ301リダイレクトする方針にした。
- 現行 `jpba1.jp` と `jpba.or.jp` に分散しているリンクは、Laravelで正本化できるものと外部リンクとして残すものに分けた。
- 旧URL方針は `docs/operations/legacy_url_redirect_policy.md` に記録した。

### 4. ランキング・シード・優先出場PDF

- 2025年度公式ランキングから2026年度シードリストを生成する流れを整備した。
- 年度別シード、永久シード、大会別追加シードを整理した。
- 今年度シードプロ画面、大会別シード設定、優先出場PDF、大会PDFの `S` 表示が同じ判定を使う方針に寄せた。
- 永久シードは登録済み。準永久シードは今回は登録見送り。

### 5. 大会終了処理チェックリスト

- 大会運用ログに、大会終了処理チェックリストを追加した。
- エントリー、スコア入力、正式成績snapshot、賞金/ポイント、タイトル同期、シード確認、PDF確認を一画面で追えるようにした。
- `TournamentAutomationReadinessService` で、スコア差分、未入力候補、ステージ不足、final同期差分、賞金/タイトル/シード未反映候補を返すようにした。
- 選手単位のスコア未入力候補を、pro ID、ライセンス、下4桁、氏名で突合するようにした。

### 6. 大会成績フロー診断

- `TournamentAutomationReadinessService` に `score_flow` 診断を追加した。
- 大会運用ログで、成績フロー、carry設定、通過人数、同スコア時ルール、男女/シフト別集計、正式成績snapshot単位、人間確認後の反映ルートを見られるようにした。
- `score_import_rows` -> `game_scores` -> `tournament_result_snapshots` -> `tournament_results` / titles / PDF の反映順を、人間確認後のボタン方式として固定した。

### 7. 方式別回帰監査とPDF共通ルール

- `docs/operations/result_flow_regression_audit.md` を追加した。
- 現DBで確認できる大会ID 10と11を使い、以下を確認した。
  - 大会ID 11: ラウンドロビン、ステップラダー
  - 大会ID 10: シュートアウト
  - 大会ID 10/11: 大会PDFが `%PDF` として生成できること
- 現DBにはシングルエリミネーション大会と `SE:%` スコア行がないため、シングルエリミネーション実データ回帰は未完了として残した。
- `MatchScoreSheetImageService` の `imagefilledpolygon()` 非推奨警告を修正した。
- `docs/operations/pdf_common_output_rules.md` を追加し、氏名、期表示、ライセンス下4桁、賞金欄、方式別文言混入防止、警告NGをPDF共通表示ルールとして固定した。

### 8. データ正本の役割整理

- `docs/operations/data_source_ownership.md` を追加した。
- ポイント配分は `point_distributions`、賞金配分は `prize_distributions` を正本とし、`tournament_points` / `tournament_awards` は旧互換として扱う方針を固定した。
- 公式登録ボール台帳は `registered_balls`、大会エントリーで選べる使用ボール候補は `used_balls`、エントリーごとの選択履歴は `tournament_entry_balls` として役割を固定した。
- `docs/db/data_dictionary.md` に、配分正本、旧互換テーブル、登録ボール同期、仮登録、有効期限の扱いを追記した。

### 9. 大会方式・PDFテンプレート運用設計

- `docs/operations/double_elimination_design.md` を追加した。
- ダブルエリミネーションは `single_elimination` へ混ぜず、別service / 別result_type / 別PDF partialとして追加する方針にした。
- 敗者側ブラケット、リセット決勝、敗者側順位、同順位扱い、再戦条件、`DE:*` のスコアキー、snapshot保存内容を整理した。
- `docs/operations/tournament_pdf_template_policy.md` を追加し、大会ごとのBlade直接手修正を避ける運用に固定した。

### 10. 大会方式のソース確認ルール

- `docs/operations/tournament_format_source_policy.md` を追加した。
- ラウンドロビンやダブルエリミネーションは、一般的な方式説明だけで固定実装せず、JPBA公式大会要項PDF、公式成績PDF、現行サイト、既存DBデータを優先して確認する方針にした。
- `docs/operations/double_elimination_design.md` のリセット決勝、敗者側順位、同順位扱い、再戦条件を、JPBA資料確認前の仮設計として明記した。

### 11. ランキング・シード・優先出場順位運用

- `docs/operations/ranking_seed_entry_policy.md` を追加した。
- `/rankings` は補助画面ではなく、公式ランキング・年度末確定ランキング管理画面として残す方針にした。
- 男子2025 ranking snapshot id=4 の `as_of_date=2025-12-23` が、公式PDF本文の `2025.12.23` と一致することを確認した。
- 年度末確定ランキング、翌年度シード生成、全日本選手権用の年度途中ランキングsnapshot、大会別優先出場順位の反映方針を整理した。

### 12. インストラクター・ProTest・会員基盤

- `docs/operations/instructor_protest_identity_policy.md` を追加した。
- インストラクター情報は `instructor_registry` を正本、旧 `instructors` を互換レイヤとして残す方針にした。
- 講習・受講は `trainings` / `pro_bowler_trainings`、資格・更新・失効履歴は `instructor_registry` の current/history として扱う方針にした。
- `ProBowlerController` / `ProBowlerImportController` の `instructors` 更新は、旧画面・旧帳票互換のための同期として維持する。
- alias / 旧ライセンス表記は `source_key` / `legacy_instructor_license_no` / `cert_no` / `notes` / history 行として保持する。
- ProTest は `pro_test_*` テーブル群を申請、実技スコア、合否、公開結果PDF導線の正本候補として整理した。
- `pro_test.record_type_id` は ADR-0003 の通り、ProTest管理画面を本格化する段階で参照先を確定する。

## 残っている大きな作業

- 実データの紙成績表画像/PDFからOCR/AI出力を作り、貼り付け変換プレビュー、`score_import_rows`、要確認行修正、`game_scores` 確定反映まで通し確認する。
- シングルエリミネーションfixtureを復元/再作成し、速報、正式成績snapshot、PDFまで再確認する。
- 通常トータルピン方式のみの大会fixtureを作り、通常PDFへの方式別文言混入がないことを確認する。
- エントリー管理に、チェックイン、当日運用、抽選結果公開、取消理由、一括繰り上げ履歴を接続する。
- `tournaments` 周辺の最終スキーマ、辞書、ER、migrationを現DBと定期的に照合する。

## 次に進むなら

優先候補は次のどちらか。

1. 実OCR/AI出力の通し確認
   - 紙成績表画像/PDFの実サンプルが必要。
   - 取込プレビュー、要確認行修正、`game_scores` 反映まで確認する。

2. シングルエリミネーションfixture復元
   - 現DBに対象データがないため、小さなfixture大会を作るか、過去のBBBカップ相当データを復元する。
   - 速報、正式成績snapshot、PDFトーナメント表まで確認する。
