# 実運用フォワードテスト用データリセット計画

## 目的

2026年7月以降の実運用に寄せたフォワードテストを行うため、プロトタイプ作成中に投入した会員、インストラクター、大会、成績、スケジュール、ログイン権限などの入力データを一度クリアする。

UI、入力導線、自動反映ロジック、PDF生成、OCR/スコア取込、公開画面互換、マイグレーション、Controller/Service/Viewなどの仕組みは残す。

## 絶対に守る安全条件

- 実削除は、DBバックアップと必要な `storage` 配下ファイルのバックアップ後に行う。
- 実削除前に、対象テーブルごとの件数をドライランで出し、削除予定件数を確認する。
- 新しい管理者アカウントを先に作成し、ログイン確認を終えてから既存ユーザーや権限を整理する。
- 現在の長原京子アカウントに紐づく管理権限は、新管理者が動作確認できるまで消さない。
- 削除処理はトランザクション内で行い、外部キー順序と関連テーブルを明示する。
- コード、ルート、Blade、Service、Command、自動反映システム、PDFテンプレート、OCR/スコア取込導線は削除対象にしない。

## クリア対象

### プロボウラー、会員、選手情報

候補: `pro_bowlers`, `pro_bowler_profiles`, `pro_bowler_links`, `pro_bowler_biographies`, `pro_bowler_sponsors`, `pro_bowler_instructor_info`, `pro_bowler_trainings`, `pro_bowler_titles`, `registered_balls`, `used_balls`, `approved_ball_pro_bowler`, ランキング/シード/年度末確定関連、選手に紐づく大会エントリー関連。

### インストラクター情報

候補: `instructor_registry`, `instructors`, インストラクター資格、講習、更新履歴、旧ライセンス表記、互換用同期データ。  
`trainings` など講習マスタ/履歴が混在する可能性があるテーブルは、ドライラン時に「マスタとして残すもの」と「入力履歴として消すもの」を分ける。

### 大会、成績、スコア、PDF/公開連携データ

候補: `tournaments`, `tournament_results`, `tournament_participants`, `tournament_entries`, `tournament_entry_balls`, `tournament_entry_operation_logs`, `game_scores`, `score_import_batches`, `score_import_rows`, `score_import_operation_logs`, `tournament_result_snapshots`, `tournament_result_snapshot_rows`, `tournament_files`, `tournament_round_lane_assignments`, `tournament_match_score_sheets`, `tournament_match_score_sheet_players`, `tournament_match_score_frames`, `match_videos`, `media_publications`, `tournament_points`, `tournament_awards`, `point_distributions`, `prize_distributions`。

`point_distributions` と `prize_distributions` は大会個別設定かマスタ設定かを確認し、実運用の基準表として残す必要がある場合は削除しない。

### スケジュール

候補: `calendar_events`, `calendar_days`, 大会日程から派生した公開スケジュール、プロテスト/講習日程の入力済みデータ。  
`config/jpba_public.php` などの固定リンクや公開導線設定は残す。

### ログイン権限、ユーザー、セッション

候補: `users` の新管理者以外、`sessions`, `password_reset_tokens`, APIトークン系テーブル、認証/権限の入力済みデータ。  
新管理者は `pro_bowler_id` に依存しない独立アカウントとして作る。旧管理者は、新管理者ログイン確認後に削除または一般ユーザー化する。

## 残すもの

- 現在のUI、入力導線、公開画面、管理画面。
- スコア取込、OCR境界、成績確定、ポイント/賞金/タイトル反映、PDF生成などの自動化システム。
- `routes`, `controllers`, `services`, `views`, `commands`, `migrations`, `tests`, `config`。
- 会場、地区、性別、ライセンス種別、記録種別、承認ボールなど、実運用で参照するマスタ/設定データ。
- 現行JPBAサイトの見た目を維持するための画像、バナー、固定リンク、静的ページ設定。

## 要確認に置くもの

- `informations` / `information_files`: 公開ニュース/お知らせの入力データ。フォワードテストで現行サイトと同期し直すならクリア候補だが、今回の明示クリア対象には含めず、削除前に確認する。
- `pro_test_*`: プロテスト運用データ。スケジュールや受験者データをクリアするか、実運用初期値として残すか確認する。
- `sponsors`, `approved_balls`, `venues`: マスタとして残す可能性が高い。個別入力データとマスタを分けて確認する。
- `storage/app/public` や `public/storage` のアップロード済みPDF/画像: DB削除後に孤立ファイルになるため、バックアップ後に削除候補を一覧化する。

## 実行順

1. 新しい管理者アカウントの氏名、メールアドレス、初期パスワード、権限名を決める。
2. 現DBと関連アップロードファイルをバックアップする。
3. 対象テーブルの存在確認と件数確認をドライランで出す。
4. `jpba:forward-test-reset --dry-run` のような専用コマンド、または同等のSQL/Artisan手順を作成する。
5. ドライラン結果を確認し、削除対象と残す対象を最終確認する。
6. 新管理者を作成し、ログイン確認を行う。
7. 承認後にリセットを実行する。
8. リセット後に、新管理者ログイン、公開トップ、選手検索、インストラクター一覧、大会作成、スコア入力、PDF生成、成績一覧をスモークテストする。

## リセット後の再投入順

1. 2026年7月現在の正会員/プロボウラー情報。
2. 2026年7月現在のインストラクター情報。
3. 2025年度のシードプロ設定。
4. 2026年1月以降の大会、参加者、成績、ポイント/賞金/タイトル。
5. 現行JPBAサイトとの公開表示整合性チェック。
6. 過去年度の成績、タイトル、ランキングを順次投入。

## 次に行う作業候補

- 新管理者アカウント情報の確定。
- DB全テーブルの件数ドライランを出すコマンドの作成。
- リセット対象/残す対象のテーブル分類表を現DBから自動生成する。
- バックアップ手順と復元手順をドキュメント化する。
- 実削除コマンドは `--dry-run` を既定にし、`--force` なしでは削除しない設計にする。

## 2026-07-09 準備状況

- `jpba:forward-test-reset` を追加した。既定ではドライランのみで、`--force` なしでは削除しない。
- 実削除には `--confirm=FORWARD-TEST-RESET`、`--backup-confirmed`、`--admin-email`、`--admin-password` が必須。
- DB dump と `storage/app/public` のバックアップを `storage/backups/forward_test_reset_20260709_092735/` に作成した。
- 通常ドライランの削除候補は16,212行。
- 公開ニュース/プロテストを含めたドライランは16,213行。追加分は `informations` 1行のみで、`pro_test_*` は0行。
- 現在の管理者候補は `admin@example.com` だが、`pro_bowler_id=56` に紐づいているため、新管理者情報の確定が実削除前の必須条件。
- 詳細は `docs/operations/forward_test_reset_dry_run_20260709.md` を参照する。

## 2026-07-09 補足: 入力データ以外は消さない

- リセット対象は入力済みレコード行のみ。
- テーブル、カラム、インデックス、外部キー、migration、Model、Controller、Service、View、Route は残す。
- 空の将来用テーブルや空カラムは削除しない。
- `record_types` は現スキーマ上、パーフェクト、7-10スプリットメイド、800シリーズなどの達成・褒章レコードを保存する入力データ用テーブル。テーブル名は紛らわしいが、今回のリセットでは構造を残し、行だけを削除対象にする。
- もし `record_types` をより分かりやすい名称へ変更する場合は、リセット実行とは別作業としてmigrationと全参照修正を行う。

## 2026-07-09 実行完了

- 入力済みデータのリセットを実行した。
- 新管理者 `yamaguchi@jpba.or.jp` / `admin` を作成し、プロボウラーに紐づかない独立管理者として確認した。
- `informations` は削除対象に含めず、1行を残した。
- `pro_test_*` は削除対象に含めていない。
- 削除後ドライランで通常対象の削除候補0行を確認した。
- `record_types` は行0件だが、褒章系カラムは残っている。
- 公開ページ監査は12ページすべて200/OK。
- 詳細は `docs/operations/forward_test_reset_execution_20260709.md` を参照する。
