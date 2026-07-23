# context_pack（このチャット開始時点の状況）

## 1. いまの目的（最重要）
- JPBA1サイト踏襲を優先しつつ、バックエンドDBを正規化・DX化する
- 現在は公開中のJPBA1本番URLではなく、ローカル/プロトタイプとしてリニューアル版を整備している
- 手作業運用を減らし、大会・成績・ランキング・シード・エントリー・ボール登録などをDB正本から自動反映できる状態へ寄せる

## 2. 現在のブランチ/コミット
- branch: `main`
- upstream: `origin/main`
- HEAD: `4a91069 docs: シードプロ整備と永久シード登録の作業ログを更新`
- 作業開始時の通常差分: なし
- 未追跡: `storage/backups/`（バックアップ/一時投入スクリプト置き場。Git管理に入れない）

## 3. 直近で変更したファイル（最大10個）
- `routes/web.php`
  - 本番にも登録されていた `__debug` ルートを `app()->environment('local')` 限定へ変更
  - 構文確認: `php -l routes/web.php`

## 4. いま困っていること（あれば）
- 現DBから `docs/db/SCHEMA.sql` / `docs/db/columns_public.csv` / `docs/db/columns_by_table.md` を更新済み
- `pro_bowler_ranking_*` / `pro_bowler_seed_*` / `tournament_seed_players` は `SCHEMA.sql` とカラム資料にも反映済み
- `tools/generate_db_docs.php` は PHP の `fgetcsv()` 警告を避けるため、escape引数を明示済み

## 5. 次にやる作業（チェックリスト）
- [x] `git status -sb` で `storage/backups/` 以外の差分を確認する
- [x] `docs/db/SCHEMA.sql` と `docs/db/columns_by_table.md` の古さを現DBで確認する
- [x] `pg_dump -s` で `docs/db/SCHEMA.sql` を更新する
- [x] カラム一覧資料を再生成する
- [x] 大会別 `歴代優勝者シード` の登録画面・リスト作成へ進む
- [x] 大会終了処理チェックリストを `大会運用ログ` へ追加する
- [x] 自動化ロードマップを `docs/chat/automation_roadmap.md` として追加する
- [x] 大会終了処理チェックリストに、スコア差分・ステージ不足・final同期差分・賞金/タイトル/シード未反映候補を表示する
- [x] 年度別シード / 永久シード / 大会別追加シードが、エントリー管理・優先出場PDF・大会PDFの `S` 表示に正しく接続されるか回帰確認する
- [x] 大会終了処理チェックリストで、選手単位の未入力一覧まで表示する
- [x] CSV/Excel取込用の一時テーブル設計へ進む。写真OCRはその後の段階にする

## 6. 参照してほしい資料
- docs/db/data_dictionary.md
- docs/db/ER.dbml（辞書から自動生成）
- docs/db/refs_missing.md
- docs/db/refs_skipped.md
- docs/chat/worklog_db.md（追記済み）
- docs/chat/progress_board.md
- docs/chat/automation_roadmap.md
- docs/db/PREFLIGHT.md
- docs/db/SCHEMA.sql

---

## 2026-06-23 追記: スコア取込ステージング

- CSV / Excel / 写真OCR / 手入力補助の解析結果を、直接 `game_scores` に書かず確認できるようにするため、以下のDB土台を追加。
  - `score_import_batches`
  - `score_import_rows`
  - `score_import_row_candidates`
- 目的は「OCR結果・CSV解析結果を保存 -> 管理者確認 -> `game_scores` 確定反映」という流れを作ること。
- 次の候補作業は、CSVアップロードサービス、名寄せ/照合サービス、確認画面、確定反映サービス。

## 2026-06-24 追記: スコアCSV一時取込入口

- `ScoreImportCsvStageService` と `TournamentScoreImportController` を追加し、運用ログ画面からCSVを一時取込できるようにした。
- CSVは `score_import_batches` / `score_import_rows` / `score_import_row_candidates` に保存する。まだ `game_scores` へは直接反映しない。
- 次の候補作業は、取込詳細画面、`needs_review` 行の修正画面、確認済み行の `game_scores` 確定反映。

## 2026-06-24 追記: スコア取込詳細・確定反映

- `ScoreImportCommitService` を追加し、確認済みの `score_import_rows` を `game_scores` へ作成/更新できるようにした。
- `score_imports.show` 画面を追加し、候補選択、行修正、除外、反映済み確認をできるようにした。
- 未照合・不足行は `needs_review` のまま残し、確定反映時にも `game_scores` へ入れない。
- 取込詳細画面に複数行一括修正を追加。選択行へステージ、ゲーム番号、シフト、性別、状態をまとめて適用できる。
- `score_import_operation_logs` を追加し、CSV取込・行修正・一括修正・確定反映の操作履歴を記録/表示できるようにした。

## 2026-06-24 追記: 横持ちスコアCSV対応

- `ScoreImportCsvStageService` を `score_csv_stage_v2` に更新。
- `game_number` + `score` の1行1スコア形式に加え、`1G` / `2G` / `3G`、`G1`、`game1`、`第1ゲーム` などの横持ち列を検出し、1つのCSV行から複数の `score_import_rows` を作れるようにした。
- 空欄の横持ちゲーム列はスキップする。元CSV行番号とスコア列情報は `raw_payload` に残す。
- 操作ログには `score_mode` と検出スコア列情報を残す。

## 2026-06-24 追記: OCR原本アップロード入口

- `ScoreImportImageStageService` を追加。
- 運用ログ画面から写真/PDF原本をアップロードし、`score_import_batches.import_type = score_sheet_image` のバッチとして保存できるようにした。
- まだOCR解析はしない。行数0の `draft` バッチとして残し、操作ログ `image_stage` にファイル情報と既定値を保存する。
- 取込詳細画面はCSV専用表記を避け、写真/PDFバッチも確認できる表示へ調整した。

## 2026-06-25 追記: OCR解析結果JSONステージング

- `ScoreImportOcrResultStageService` を追加。
- 写真/PDFバッチ詳細からOCR解析結果JSONをアップロードし、`score_import_rows` へ確認用行を作れるようにした。
- JSONは `rows` 配列を基本とし、`games` / `scores` の横持ち形式もゲームごとに展開する。
- 反映済み行があるバッチは差し替え不可。既存解析行がある場合は、画面で「既存解析行を差し替える」を選んだ時だけ置き換える。
- OCR JSON取込は英語キーだけでなく、日本語キーや `players` / `results` / `items` も受けられる。詳細画面からサンプルJSONをダウンロードできる。

## 2026-06-25 追記: Excel貼り付け取込

- 運用ログ画面にExcel貼り付け欄を追加。
- Excel/Googleスプレッドシートからコピーしたタブ区切り表をCSVへ変換し、既存CSV取込サービスへ流す。
- 依存ライブラリを追加せず、`1G` / `2G` / `3G` の横持ち形式にも対応する。

## 2026-06-25 追記: 古い未チェック項目の棚卸し

- `progress_board.md` / `automation_roadmap.md` / `context_pack.md` の未チェックを確認した。
- 未チェック139件はすべて `progress_board.md` に残っており、古い履歴メモ・重複・後続候補が混在していた。
- 2026-06-23以降にCodexが直接進めた作業はチェック済み扱いでよい。
- 詳細な分類は `docs/chat/unchecked_inventory.md` に作成済み。
- 次に進める直近候補は、実OCRエンジン接続、またはOCR/AI出力を現在のJSON仕様へ変換する実アダプタの実装。

## 2026-06-26 追記: OCR/AI出力テキストアダプタ

- 写真/PDFバッチ詳細画面に、OCR/AI出力貼り付け欄を追加した。
- `ScoreImportOcrTextAdapterService` で、JSON / Markdown表 / タブ区切り / カンマ区切り / 空白区切りの簡易表を既存OCR JSON仕様へ変換する。
- 変換後は既存のOCR JSON取込と同じく `score_import_rows` に保存し、確認後にだけ `game_scores` へ反映する。
- `変換JSONを確認` で、DB保存前に変換結果を別タブのJSONとして確認できる。

## 2026-06-26 追記: 未チェック整理

- 古い未チェックを削除し、必要な候補だけを `progress_board.md` 末尾の `Active Backlog` に集約した。
- 整理後の未チェックは43件。
- 現行JPBAサイトを確認し、公開側の不足候補も `Active Backlog` に追加した。
- 次に作業するときは、古い履歴中の未完了風メモではなく `Active Backlog` を参照する。

## 2026-06-26 追記: OCR/AI取込詳細の確認情報表示

- `resources/views/score_imports/show.blade.php` に、最新のOCR/AI変換サマリーカードを追加した。
- 取込行一覧に `確認情報` 列を追加し、信頼度、要確認理由、変換元ファイル/行/列、抽出元行を表示できるようにした。
- DB変更はない。既存の `confidence` / `error_message` / `raw_payload` / 操作ログ `adapter_summary` を表示に使う。
- 未チェックは42件。

## 2026-06-26 追記: OCRエンジン接続境界の固定

- `app/Services/ScoreImportOcrEngineBoundaryService.php` を追加した。
- 実OCRエンジンへ渡す入力仕様は `buildEngineInput()`、OCR/AI出力テキストをステージングへ流す入口は `stageTextResult()`。
- 既存の貼り付け変換画面もこの境界サービスを通すように変更した。
- 境界は `画像/PDF原本バッチ -> OCR処理 -> ScoreImportOcrTextAdapterService -> ScoreImportOcrResultStageService -> score_import_rows`。
- `game_scores` / `tournament_results` へ直接書き込まず、人間確認後に確定反映する。
- 未チェックは41件。

## 2026-06-26 追記: スコア取込運用手順書

- `docs/operations/score_import_runbook.md` を追加した。
- CSV、Excel/Googleスプレッドシート貼り付け、写真/PDF原本、OCR JSON、OCR/AI出力貼り付けの共通手順書。
- すべて `score_import_rows` -> 人間確認 -> `game_scores` の流れにそろえる。
- 次にスコア取込運用を確認するときは、この手順書を正本として読む。
- 未チェックは40件。

## 2026-06-26 追記: 公開トップ棚卸し・INFORMATIONカテゴリ正本化

- `docs/operations/public_site_parity_checklist.md` を追加した。
- 現行JPBA1トップの上部メニュー、更新履歴、プロボウラー専用ページ、大会バナー、PDFリンク、動画/外部サービス、INFORMATION、協賛/関連団体、SNS、フッター導線を棚卸しした。
- INFORMATIONカテゴリは `NEWS` / `大会` / `TV情報` / `ｲﾝｽﾄﾗｸﾀｰ` / `イベント` を正本にする。
- カテゴリ候補は `Information::categories()`、管理画面バリデーションは `Information::categoryValidationRule()` を使う。
- `informations_category_check` を更新するmigrationを追加し、DBでも `TV情報` を許可する。
- `/info`、`/info/{information}`、`/info/files/{informationFile}` はログイン不要の公開ルート。会員向けは `/member/info` 系のまま。
- 未チェックは38件。

## 2026-06-26 追記: 公開トップのDB表示化

- `/` は `PublicHomeController@index`、ルート名は `public.home`。
- 公開トップViewは `resources/views/public/home.blade.php`。
- 大会枠は `tournaments` と公開 `tournament_files` を読み、未来/開催中優先、なければ直近大会を表示する。
- 大会画像は `hero_image_path` / `image_path` / `title_logo_path` / `poster_images` の順に使い、なければ `public/images/jpba_logo.png` を表示する。
- INFORMATION枠は `Information::active()->public()` を読む。
- 現行サイト由来の外部ナビ、PDF、フッター導線は `config/jpba_public.php`。
- 未チェックは37件。

## 2026-06-26 追記: JPBAについて・スケジュール公開ページ

- `/about` は `PublicPageController@about`、ルート名は `public.about`。
- `/schedule` は `PublicPageController@schedule`、ルート名は `public.schedule`。
- 公開下層共通レイアウトは `resources/views/public/layout.blade.php`。
- `JPBAについて` の協会概要、事業、公式資料PDF導線は `config/jpba_public.php` の `association` を読む。
- `スケジュール` は `tournaments` / `calendar_events` を読み、年別・月別に表示する。指定年にデータがなければDB上の最新年へ寄せる。
- 未チェックは35件。

## 2026-06-26 追記: 選手データ公開検索

- `/players` は `PublicPlayerController@index`、ルート名は `public.players.index`。
- `/players/{id}` は `PublicProfileController@show`、ルート名は `public.players.show`。
- `/player` と `/player/index.html` は `/players` へ301リダイレクトする。
- 検索条件は、氏名、ライセンスNo範囲、性別、地区、退会者。公開側では `is_visible=true` を前提に、通常検索は `is_active=true`、退会者検索は退会ステータス/非アクティブを読む。
- 公開検索Viewは `resources/views/public/players/index.blade.php`、公開プロフィールViewは `resources/views/public/players/show.blade.php`。
- 既存の会員向け `pro_bowlers.public_show` は維持し、公開ルートから来た時だけ公開プロフィールViewを返す。
- `config/jpba_public.php` の `選手データ` ナビは `public.players.index` を指す。
- 未チェックは34件。

## 2026-06-26 追記: トーナメント公開ページ

- `/tournament` は `PublicTournamentController@index`、ルート名は `public.tournaments.index`。
- `/tournament/{tournament}` は `PublicTournamentController@show`、ルート名は `public.tournaments.show`。
- `/tournament/index.html` は `/tournament` へ301リダイレクトする。
- 公開側は管理画面の `/tournaments` と衝突しないよう、現行サイト寄りの単数形URL `/tournament` にしている。
- 一覧検索条件は、大会区分、年、月、地区。地区は会場名/会場住所の都道府県キーワードで絞る。
- 詳細は `tournament_files.visibility=public`、`sidebar_schedule`、`result_cards`、`simple_result_pdfs`、`tournament_results` 上位行を読む。
- `config/jpba_public.php` の `トーナメント` ナビは `public.tournaments.index` を指す。
- 未チェックは33件。

## 2026-06-26 追記: インストラクター公開ページ

- `/instructor` は `PublicInstructorController@index`、ルート名は `public.instructors.index`。
- `/instructor/index.html` は `/instructor` へ301リダイレクトする。
- 公開Viewは `resources/views/public/instructors/index.blade.php`。
- 上部の制度/講習/スクール/テキスト/ライセンス別導線は `config/jpba_public.php` の `instructor` を読む。
- 最新情報は `informations.category = ｲﾝｽﾄﾗｸﾀｰ` の一般公開データを読む。
- ライセンス別一覧は `instructor_registry` の `is_current=true` / `is_active=true` / `is_visible=true` を読む。
- `config/jpba_public.php` の `インストラクター` ナビは `public.instructors.index` を指す。
- 未チェックは32件。

## 2026-06-26 追記: プロテスト・トピックス・フッター公開導線

- `/protest` は `PublicPageController@protest`、ルート名は `public.protest`。
- `/topics` は `PublicPageController@topics`、ルート名は `public.topics`。
- `/contact` / `/media` / `/commerce` / `/privacy` は `PublicPageController@staticPage` で表示する。
- 公開Viewは `resources/views/public/protest.blade.php`、`resources/views/public/topics.blade.php`、`resources/views/public/static_page.blade.php`。
- プロテストは `config('jpba_public.protest')`、`pro_test_schedule`、`calendar_events.kind=pro_test`、プロテスト関連INFORMATIONを読む。
- トピックスは `informations` と公開 `information_files` を正本に、記事本文、画像、添付、カテゴリを表示する。
- フッター固定ページの本文・表・リンクは `config('jpba_public.static_pages')` に集約した。
- 旧URL互換は `/protest/index.html`、`/topics.html`、`/update_logs.html`、`/inquiry/index.html`、`/media/index.html`、`/ovservance/index.html`、`/ovservance.html`、`/policy/index.html` を301でローカル公開ページへ寄せる。
- 未チェックは28件。

## 2026-06-29 追記: 大会成績フロー診断

- `TournamentAutomationReadinessService` が `score_flow` を返すようにした。
- `score_flow` には、成績フロー、carryプリセット、同スコア時ルール、方式別通過人数、carry集計元、`game_scores` の男女/シフト別スコープ、正式成績snapshot反映単位、人間確認後の反映ルートを含める。
- `大会運用ログ` の大会終了処理チェックリストに、速報・正式成績フロー診断を追加した。
- 速報から正式成績への反映は、`score_import_rows` -> `game_scores` -> `tournament_result_snapshots` -> `tournament_results` / titles / PDF の順に、人間確認後のボタン方式で固定する。
- Active Backlog Cの4件を完了扱いにし、未チェックは24件。

## 2026-06-29 追記: 方式別回帰監査・PDF共通ルール

- `docs/operations/result_flow_regression_audit.md` を追加した。
- 現DBで確認できる実データは、大会ID 10（シーズントライアル + シュートアウト）と大会ID 11（THE OPEN + ラウンドロビン + ステップラダー）。
- 大会ID 11のRR/ステップラダー、大会ID 10のシュートアウト、大会ID 10/11のPDF生成を確認した。
- 現DBにはシングルエリミネーション大会と `SE:%` スコア行がないため、シングルエリミネーション実データ回帰は未完了として残す。
- `MatchScoreSheetImageService` の `imagefilledpolygon()` 非推奨警告を修正した。
- `docs/operations/pdf_common_output_rules.md` を追加し、PDF共通表示ルールを固定した。
- Active Backlog DのPDF共通表示ルールを完了扱いにし、未チェックは23件。

## 2026-06-30 追記: 作業ログ総括

- 次へ進む前の総括として `docs/chat/work_summary_2026_06_30.md` を追加した。
- ここまでのCodex作業を、棚卸し、スコア取込/OCR、公開導線、ランキング/シード、大会終了処理、成績フロー診断、方式別回帰、PDF共通ルール、残作業に分けて整理した。
- 次に進む候補は、実OCR/AI出力の通し確認、またはシングルエリミネーションfixture復元。

## 2026-06-30 追記: データ正本の役割整理

- `docs/operations/data_source_ownership.md` を追加し、賞金・ポイント配分と登録ボール/使用ボールの正本を固定した。
- ポイント配分は `point_distributions`、賞金配分は `prize_distributions` を正本とする。`tournament_points` / `tournament_awards` は旧互換として残し、新規自動化・集計・PDF出力では参照しない。
- 公式登録ボール台帳は `registered_balls`、大会エントリーで選べる使用ボール候補は `used_balls`、エントリーごとの選択履歴は `tournament_entry_balls` を正本とする。
- `docs/db/data_dictionary.md` に上記の役割、同期ルール、仮登録/有効期限の扱いを追記した。
- Active Backlog C/Eの2件を完了扱いにし、未チェックは21件。

## 2026-06-30 追記: 大会方式・PDFテンプレート運用設計

- `docs/operations/double_elimination_design.md` を追加し、ダブルエリミネーションを `single_elimination` とは別方式として実装する方針を固定した。
- 敗者側ブラケット、リセット決勝、敗者側順位、同順位扱い、再戦条件、`DE:*` の `game_scores.entry_number` 候補、snapshot保存内容、PDF方針を整理した。
- `docs/operations/tournament_pdf_template_policy.md` を追加し、大会IDや大会名ごとの専用Bladeを作らず、DB設定・共通Controller・方式別partialでPDFを拡張する運用に固定した。
- Active Backlog C/Dの2件を完了扱いにし、未チェックは19件。

## 2026-06-30 追記: 大会方式のソース確認ルール

- `docs/operations/tournament_format_source_policy.md` を追加した。
- ラウンドロビン、ダブルエリミネーションなどの方式は、一般的な大会方式説明だけで固定実装しない。
- JPBA公式大会要項PDF、公式成績PDF、現行サイトの速報・成績、既存DBデータを優先し、一般説明は補助資料に留める。
- `docs/operations/double_elimination_design.md` のリセット決勝、敗者側順位、同順位扱い、再戦条件は、JPBA資料確認前の仮設計であることを明記した。
- 未チェックは19件のまま。

## 2026-06-30 追記: ランキング・シード・優先出場順位運用

- `docs/operations/ranking_seed_entry_policy.md` を追加した。
- `/rankings` は補助画面ではなく、公式ランキング・年度末確定ランキング管理画面として残す。
- 男子2025 ranking snapshot id=4 は `as_of_date=2025-12-23`、公式PDF本文も `2025.12.23` のため一致確認済み。
- 全日本選手権用の年度途中ランキングは、年度末最終ランキングとは別snapshotとして `ranking_scope = all_japan_entry_priority` 候補で保存する方針にした。
- 大会エントリーへの優先出場順位は `tournament_entries` へ直接コピーせず、年度別シード + 大会別追加シード + `priority_order` を合成して参照する。
- Active Backlog Eの4件を完了扱いにし、未チェックは15件。

## 2026-06-30 追記: インストラクター・ProTest・会員基盤

- `docs/operations/instructor_protest_identity_policy.md` を追加した。
- インストラクター情報の正本は `instructor_registry`、旧 `instructors` は互換レイヤとして残す方針にした。
- `InstructorController` はすでに `InstructorRegistry` 中心で、`ProBowlerController` / `ProBowlerImportController` の `instructors` 更新は互換同期として維持する。
- 講習・受講は `trainings` / `pro_bowler_trainings`、資格・更新・失効履歴は `instructor_registry` の current/history として扱う。
- alias / 旧ライセンス表記は `source_key` / `legacy_instructor_license_no` / `cert_no` / `notes` / history 行として保持し、現在値を安易に上書きしない。
- ProTest は `pro_test_*` テーブル群を申請、実技スコア、合否、公開結果PDF導線の正本候補として整理した。`pro_test.record_type_id` は ADR-0003 の通り参照先確定を保留する。
- Active Backlog Fの5件を完了扱いにし、未チェックは10件。

## 2026-07-01 追記: 大会DB正本・公開/管理境界

- `docs/operations/tournament_db_alignment_public_admin_policy.md` を追加した。
- `pg_dump -s` で `docs/db/SCHEMA.sql` を現DBから再生成した。
- `information_schema.columns` から `docs/db/columns_public.csv` を再生成し、`php tools/generate_db_docs.php` で `docs/db/columns_by_table.md` を再生成した。
- `php tools/generate_er_from_dictionary.php` で `docs/db/ER.dbml` を再生成した。
- `SCHEMA.sql` は `informations_category_check` が `TV情報` を含む現DB制約へ更新された。`tournaments` 周辺の構造差分は出なかった。
- 現DB確認では `tournaments` 83 columns、`tournament_entries` 17 columns、`tournament_results` 17 columns、`tournament_files` 7 columns。
- 公開側はDB正本を読むだけ、管理側は入力・確認・反映を担当する境界を整理した。
- Active Backlog Gの3件を完了扱いにし、未チェックは7件。

## 2026-07-01 追記: エントリー当日運用・取消理由・繰り上げ履歴

- `tournament_entry_operation_logs` を追加し、エントリー取消、ウェイティング登録、単独繰り上げ、一括繰り上げ、対象外スキップ、会員チェックインの履歴を保存できるようにした。
- 管理側エントリー一覧 / 抽選一覧に、直近20件のエントリー操作履歴を表示する共通パーツを追加した。
- 取消時は `cancel_reason` を必須入力にし、取消前のシフト、レーン、チェックイン、ウェイティング状態を `payload.previous_state` に保存する。
- 一括繰り上げは `batch_key` で同一操作単位を追跡し、繰り上げ成功行と参加権利なし等の対象外行を同じ履歴で確認できる。
- `docs/db/data_dictionary.md`、`SCHEMA.sql`、`columns_public.csv`、`columns_by_table.md`、`ER.dbml` を現DBに合わせて更新した。
- 検証は `php -l`、`php artisan migrate`、`php artisan view:cache`、Laravel tinkerでの新テーブル確認。
- Active Backlog Eの1件を完了扱いにし、未チェックは6件。

## 2026-07-01 追記: 大会PDF方式別Blade回帰

- `php artisan tournament:pdf-regression` を追加し、方式別PDF入口を一括確認できるようにした。
- 既存大会ID 10でシーズントライアル外枠 + シュートアウト表示、大会ID 11で通常外枠 + RR/ステップラダーPDFを確認した。
- 標準、純シュートアウト、シングルエリミネーションはロールバックされる一時fixtureでPDF生成を確認する。
- 2026-07-01実行結果は全5ケースOK。`%PDF` 生成、warningなし、一時fixtureはDBに残らないことを確認済み。
- `docs/operations/result_flow_regression_audit.md` と `docs/operations/tournament_pdf_template_policy.md` を更新した。
- Active Backlog DのPDF方式別回帰1件を完了扱いにし、未チェックは5件。

## 2026-07-01 追記: 公開画面・現行サイト踏襲監査

- 現行 `https://www.jpba1.jp/` トップを再確認し、2026 JPBAトーナメント予定表PDF、JPBA LIVE、io.LEAGUE系リンクを `config/jpba_public.php` へ反映した。
- `php artisan public:parity-audit` を追加し、公開ページをLaravel内部レンダリングで監査できるようにした。
- 監査対象は、主要ナビ、補助導線、フッター導線、ページ固有見出し、画像/バナー、PDFリンク、外部リンク、内部リンク、ローカル参照切れ。
- `/`、`/about`、`/schedule`、`/players`、`/tournament`、`/instructor`、`/protest`、`/topics`、`/contact`、`/media`、`/commerce`、`/privacy` の12ページがすべてOK。
- `docs/operations/public_site_parity_checklist.md` を更新した。
- Active Backlog Gの公開画面照合1件を完了扱いにし、未チェックは4件。

## 2026-07-01 追記: 大会結果フロー方式別回帰

- `php artisan tournament:result-flow-regression` を追加し、方式別サービス計算を一括確認できるようにした。
- 既存DBの実データで、大会ID 11のラウンドロビン8名/8G、1位久保田彩花、5勝3敗、Bonus 150を確認した。
- 既存DBの実データで、大会ID 11のステップラダーseed 3名、1回戦/優勝決定戦とも `done`、優勝久保田彩花を確認した。
- 既存DBの実データで、大会ID 10のシュートアウトseed 8名、3試合完了、優勝水野耕佑を確認した。
- 現DBにはシングルエリミネーション大会と `SE:%` スコア行がないため、シングルエリミネーションだけはロールバックfixtureで、4名/3試合完了、順位 `1,2,3,3` まで確認した。
- 実行後に `tournaments` が大会ID 10/11のみで、一時fixtureがDBへ残っていないことを確認した。
- Active Backlog Cの方式別回帰1件を完了扱いにした。
- シングルエリミネーションの現DB実データ復元/再作成後の速報、正式成績snapshot、`tournament_results` 同期、PDF通し確認を新しい未チェックとして残し、未チェックは4件。

## 2026-07-01 追記: DB・構成整理監査

- `docs/operations/database_simplification_audit.md` を追加した。
- `php artisan migrate:status` で167件すべて適用済み、未適用migrationなしを確認した。
- migration timestamp重複は既知の `2025_09_02_000026` 2件のみ。既存DBで適用済みのため、削除/リネームせず `docs/db/migration_duplicates.md` に維持方針を記録した。
- `app/Models/*.php` は全Modelが現DBテーブルを参照しており、Model/DBテーブル不一致はなかった。
- Serviceは完全未参照なし。
- ルート未接続Controller 6本と関連View/Request/未読込routeを削除し、Controllerは68本すべてルート接続済みに整理した。
- 現DBは95テーブル中64テーブルが空。ただし将来運用、旧互換、Laravel標準テーブルを含むためDBテーブル削除は実施しない。

## 2026-07-02 追記: 公式PDF風スコアシート

- スコアシート1枚分の描画を `resources/views/tournament_results/pdfs/partials/score_sheet_block.blade.php` に共通化した。
- スコアシート見出しはJPBA実ロゴ、会場、開催日、レーン、ゲーム番号を表示する。
- 通常/シングルエリミネーション用 `score_sheets.blade.php` は、複数スコアシート時も先頭を落とさず全件を2枚ごとにページ分割する。
- シュートアウト用 `shootout_pages.blade.php` は、優勝決定戦を図ページ内、残りを2枚ごとの別ページとして出す。
- `MatchScoreSheetImageService::generateDataUris()` は `player_count` を返す。
- `php artisan tournament:pdf-regression` は全5ケースOK。
- 大会ID 10のPDFをPNG化し、スコアシートページのロゴ、会場/開催日/レーン表示、外枠罫線、ページ分割に崩れがないことを確認した。
- 未チェックは3件。

## 2026-07-02 追記: シングルエリミネーション現DB通し確認

- `app/Services/SingleEliminationFixtureDataService.php` と `tournament:restore-single-elimination-fixture` を追加した。
- `php artisan tournament:restore-single-elimination-fixture --force --json` で、現DBに大会ID 27 `シングルエリミネーション通し確認 fixture` を作成した。
- 大会ID 27には、予選 `game_scores` 32行、SE `game_scores` 6行、`prelim_total` snapshot、`single_elimination_final` snapshot、`tournament_results` 4行がある。
- `tournament:result-flow-regression` は、現DBにSE大会がある場合 `single_elimination_existing` として既存SEデータを確認する。
- `tournament:pdf-regression` は、現DBにSE大会がある場合 `single_elimination_existing` のPDFも確認する。
- 認証ありHTTPで `/scores/result?tournament_id=27&stage=トーナメント&upto_game=2` がstatus 200、優勝者名、`R2-M1` を含むことを確認した。
- `tournament:result-flow-regression` と `tournament:pdf-regression` は大会ID 27込みでOK。
- 未チェックは2件。残りは実物の紙成績表画像/PDFが必要なOCR/AI取込通し確認2件。

## 2026-07-03 追記: ST Summer 2026 B会場 公式PDF実データ通し確認

- `jpba:import-st-summer-2026-b` を追加し、JPBA公式ページのB会場（サンスクエアボウル）PDF抽出テキストから大会ID 34を再作成できるようにした。
- 参加者50名、エントリー49件、レーン配置50件、予選 `game_scores` 384行、準決勝 `game_scores` 96行、シュートアウト `game_scores` 10行を作成した。
- 予選8Gは `payload.rows` 48行 -> `score_import_rows` 384行 -> `game_scores` 384行、準決勝4Gは `payload.rows` 24行 -> `score_import_rows` 96行 -> `game_scores` 96行で、全行 `accepted` / `confirmed` になった。
- `prelim_4g`、`prelim_total`、`semifinal_total`、`shootout_final` の4 snapshotと、公式最終成績8行の `tournament_results` を作成した。
- 既存 `ShootoutService` でSO1/SO2/SO3の3試合が完了し、優勝者「市原 竜太」と最終順位8名が公式PDFと一致した。
- `php artisan tournament:result-flow-regression` と `php artisan tournament:pdf-regression` は全OK。
- Active Backlog Aの実データOCR/PDF取込2件を完了扱いにし、未チェックは0件。
## 2026-07-03 追記: シーズントライアルPDF表示修正
- 2026 ST Summer B会場の最終成績PDFでシュートアウトページが欠落していた原因は、PDF用 seed snapshot 検索が `gender IS NULL` のみを対象にし、`gender = M` の `semifinal_total` snapshot を拾えなかったこと。
- `TournamentResultController::findCurrentSnapshotByCode()` は、大会の `gender` に一致するsnapshotも対象にするよう修正済み。
- シーズントライアル準決勝表の列ずれは、本文側の「入賞者リスト参照 / ステップポイント」列に対するヘッダー列が無かったことが原因。`snapshots.blade.php` へ空ヘッダー列を追加して修正済み。
- 同一PHPプロセスで複数PDFを連続生成すると `view()->share()` の値が混ざる可能性があったため、PDF生成前に大会成績PDF用共有値をクリアする処理を追加済み。
- `tournament:pdf-regression` に `season_trial_gender_snapshot_existing` とシュートアウトPDF payload 検査を追加済み。
- 2026版・2025版ともPDFを再生成し、PopplerでPNG化して目視確認済み。確認用PDFは `Downloads` の `_fixed.pdf`。
- `php artisan tournament:pdf-regression` / `php artisan tournament:result-flow-regression` は全OK。
- 残り未チェックは0件。

## 2026-07-04 追記・直近の状態

- シーズントライアルPDFの追加修正では、所属・用品契約の長文表示、予選の準決勝進出二重線、準決勝表の列ずれ、シュートアウト図とスコアシート表示を確認済み。
- シュートアウト表示は、公開済み `TournamentMatchScoreSheet` の `sheet_type = shootout` かつ `is_published = true` の `final_score` を優先する。従来の `game_scores` SO入力はフォールバックとして残す。
- 成績一覧 `/tournaments/{id}/results` は、`tournament_results` が少なくても最新snapshotに全員分がある場合、確認用snapshot行を表示する。大会ID 34は48名表示の確認済み。
- 成績一覧の左端列は「順位」。年度列は非表示にした。
- PDF修正後は、必ずPDF生成、Poppler PNG化、ページ目視確認を行う運用とする。
- 検証済みコマンド: `php artisan view:cache`、`php artisan tournament:pdf-regression`、`php artisan tournament:result-flow-regression`。
## 2026-07-08 追記・回帰防止ポイント

- シーズントライアル成績一覧で正式成績より多い人数を表示する場合、単一snapshotだけを使うとポイント・賞金が落ちる。上位は `tournament_results`、準決勝通過後の順位は `semifinal_total`、予選敗退者は `prelim_total` で補完する。
- 34番大会は48名表示を維持しつつ、上位8名の `points` / `award_points` / `step_points` / `prize_money` と、9〜24位の `step_points` を表示する。
- PDFスコアシートは、投球マークからの再計算が正本だが、表示欠落防止のため `cumulative_score` / `frame_score` をフォールバックとして使う。
- PDF変更後は必ずPDF生成、Poppler PNG化、該当ページの目視確認を行う。2025 ST Autumn C はスコアシート累計、2026 ST Summer B はシュートアウト図・準決勝表・予選表・二重線を確認対象にする。
## 2026-07-08 追記・実運用フォワードテスト用初期化方針
- プロトタイプ投入済みデータを一度クリアし、2026年7月現在の正会員/インストラクター、2025年度シード、2026年1月以降の大会成績を再投入して現行JPBAサイトとの整合性を取る方針。
- クリア対象は、プロボウラー/会員、インストラクター、大会/成績/スコア、スケジュール、ログイン権限。ただし新管理者アカウントを先に作成してログイン確認するまで、既存管理者権限は削除しない。
- 残す対象は、UI、入力導線、自動反映システム、PDF生成、OCR/スコア取込、公開互換、コード、DB構造、マスタ/設定データ。
- 実削除は未実行。必ずバックアップ、テーブル件数ドライラン、削除対象の最終確認、明示承認を経てから行う。
- 詳細は `docs/operations/forward_test_data_reset_plan.md` を参照する。

## 2026-07-21 追記・大会テンプレートから合算成績まで

- 大会シリーズ、年度開催、版管理テンプレート、会場マスター選択、年度シード／歴代優勝者等の優先出場自動同期を実装済み。
- ダブルス／チームの編成と、複数大会・ステージ・ゲーム範囲を束ねる個人／編成合算を実装済み。
- 管理画面は `/tournaments/{id}/aggregate-results`。元スコアは `game_scores`、計算結果は `tournament_result_snapshots` の版として保持する。
- 必須競技、編成人数、予定ゲーム数の不足／超過、未編成スコア、仮照合、合算範囲重複を検査する。
- 同ピン方針は大会ごとに同順位／ローハイ差を選べ、既定は同順位。
- 大会ID61の予選8Gで48名、連携済み2名のペアで完了ケースを確認。統合確認はロールバックし、表示確認用データも削除済み。
- Unit全体17件・115アサーション、PC／390px幅、ブラウザエラー0件、Bladeキャッシュ、公開ページ監査を確認する。
- 詳細は `docs/operations/tournament_template_foundation_20260721.md` と `docs/operations/tournament_aggregate_results_20260721.md` を参照する。
- 次は実大会の年度開催へ各競技を登録し、大会要項に基づいて合算結果から最終成績、ポイント、賞金、PDF、公開ページへの確定反映を接続する。

## 2026-07-21 追記・公式結果の確定公開

- 管理画面は `/tournaments/{id}/result-publications`。編集者／管理者はプレビュー、管理者だけが確定公開できる。
- スナップショット作成時にはポイント、賞金、タイトル、公開PDFを更新しない。管理者の確定時にだけ同一トランザクションで反映する。
- 最終ステージの選手を優先し、準決勝、準々決勝、予選から敗退者を補完して全員分の `tournament_results` を作る。
- シーズントライアルは1〜8位の入賞ポイントと準決勝のステップポイントを自動合算する。
- 公開版と全行を履歴保存し、訂正時は元成績を直して改訂版を公開する。公開済み `tournament_results` の直接編集はHTTP 409で拒否する。
- 大会ID61はプレビュー48名、541ポイント、賞金398,700円。仮公開PDF4ページを全ページ目視確認した。
- 検証はロールバック済みで、実公開履歴は0件。大会ID61の既存成績8件とタイトル1件は変更していない。
- 詳細は `docs/operations/tournament_result_publication_20260721.md` を参照する。
- 次は直近5年の大会要項から、閉鎖会場を除く現役会場を会場マスターへ登録する。

## 2026-07-22 追記・直近5年の現役会場マスター

- JPBA公式大会ページ2022～2026年の127件を調査し、国内現役58会場を `venues` へ登録した。
- `canonical_key` と `aliases` で表記揺れを吸収し、`is_active` で大会作成時の候補表示を制御する。
- 閉鎖会場は削除せず停止扱いにする。初回データではスポルト名古屋、星が丘ボウル、牧野松園ボウルを除外した。
- 海外会場と仮設レーンは現在の国内会場マスター対象外とした。
- `php artisan jpba:import-recent-venues` は既定ドライラン、`--force` で確定。既存の手修正済み項目を上書きしない。
- 58件登録後の再実行は作成0、更新0、変更なし58件。住所/TEL欠損0、重複0、閉鎖会場混入0。
- 大会ID61はサンスクエアボウルID18へ自動結線済み。
- `/venues` と `/tournaments/create` をPC・390px幅で実確認し、会場一覧と大会会場選択は正常。
- 現DBから `SCHEMA.sql`、`columns_public.csv`（1,340カラム）、`columns_by_table.md`、辞書から `ER.dbml` を再生成済み。
- 詳細は `docs/operations/venue_master_import_20260722.md`。
- 次は大会ID61をシーズントライアル年度開催へ割り当て、実績データを含めない標準テンプレートを確定する。

## 2026-07-22 追記・シーズントライアル標準テンプレート

- 大会ID61はシリーズID2、2026サマー年度開催ID2、テンプレートID2のv2（版ID3）へ接続済み。
- 標準は男子、公認、予選8G、準決勝4G、通算12G、上位8名シュートアウト。
- 年度シード自動登録なし、公式ポイント対象外、STポイント・公式AVE・賞金・後続大会出場優先順位は対象、タイトルはST優勝へ反映する。
- テンプレートは季節、会場、レーン範囲、日付、参加人数、選手、スコア、成績、配分表を含まない。
- 季節シリーズ保存時は `season_key` 必須。テンプレート適用後に春夏秋冬を選ぶ。
- 実行コマンドは `php artisan jpba:setup-season-trial-template`。既定はドライラン、`--force` で確定する。
- 大会ID61の保護チェックサムは適用前後とも `5935e70a504781c026f366dbd845a8f351b7ab14a8d2b80342ba78624d144fbe` で、参加者50、スコア490、正式成績8、タイトル1などを維持した。
- 公式ポイント、賞金、AVEの各ランキングは大会の反映フラグを集計クエリで強制する。
- Unit25件・159アサーション、方式別回帰、PDF回帰、公開12ページ監査、Pint、実ブラウザ表示を確認済み。
- 詳細は `docs/operations/season_trial_standard_template_20260722.md`。
- 次は2026年1月以降の残り大会を公式資料単位で棚卸しし、確定済み大会から登録する。

## 2026-07-22 追記・2026シーズントライアル全12会場

- 3季節・各A～D会場の12大会を公式ページから確定し、大会枠を登録済み。
- IDはウィンター109～112、スプリング113～116、サマー117・既存61・118～119。
- 実行コマンドは `php artisan jpba:import-season-trial-2026-catalog`。既定ドライラン、`--force` で確定、`--json` で監査詳細を出力する。
- シリーズID2、テンプレートID2・v2（版ID3）、使用中会場マスターが前提。既存大会との設定競合があれば書込みは行わない。
- 新規11大会は大会枠、標準ステージ2件、結果出力5件だけを持ち、選手、エントリー、レーン、スコア、成績、タイトルは空。
- ID61は既存データを維持し、成績一覧48名とポイント・賞金を実ブラウザで確認した。
- 保護チェックサムは前後とも `07cdd1df8a9e9e10260473ea7be1050268b46ccd7875933075b2a894a71159f0`。
- 公式最終成績PDFは11大会で公開済み。サマーDは2026-07-28開催予定のため未公開。
- Unit26件・265アサーション、方式別回帰、PDF回帰、Pint、公開12ページ監査、ブラウザエラー0件を確認済み。
- 詳細は `docs/operations/season_trial_2026_catalog_20260722.md`。
- 次はID109のウィンターA会場から公式成績をトランザクション投入する。

## 2026-07-22 追記・2026年公式成績とランキング照合

- 公開済みの通常大会12件、シーズントライアル11会場を確定済み。合計23大会、70スナップショット、資料延べ3,142行、最終公開成績1,952行。
- 実行コマンドは `php artisan jpba:import-official-2026-results`。既定ドライラン、`--force` で確定する。
- 男子327名（7/21）・女子212名（7/13）の公式ランキングと選手別ポイント・賞金を全件照合し、差分0件。
- 年間ポイント表示とポイント基準シードは、通常公式戦にシーズントライアルのポイントを合算する。STの内部区分と専用ポイント設定は維持する。
- 表記差があった2026年タイトル5件は既存の公式サイト由来タイトルを大会へ接続し、自動生成された同義重複だけを削除した。
- 藤永北斗プロは公式5、ST3、各明細数も一致。東海男子はアマチュア優勝のためプロタイトルなし。
- サマーB会場ID61の既存ゲームスコア490件とシュートアウトデータは保持されている。
- 次はサマーD会場の公式結果公開後の追加と、必要に応じて選抜・予選資料を含むゲーム数・トータルピン・AVEの完全照合を行う。
- 詳細は `docs/operations/official_2026_results_import_20260722.md`。

## 最優先注意・2026年大会の詳細スコア／PDF再監査

- 第41バッチの「23大会確定」は集計順位、ポイント、賞金、タイトル連携まで。詳細スコアとPDF内容は未完了なので、実運用完了として扱わない。
- 23大会中、`game_scores` があるのは既存サマーB会場ID61の490件だけ。残り22大会は0件で、PDF各ゲーム欄と決勝対戦が空になる。
- 通常大会12件はステージ設定と結果出力設定も0件。新規ST10大会は枠設定だけがあり、各ゲーム、レーン、対戦スコアがない。
- 公式ランキング539名との再集計で、ゲーム数またはトータルピンに差異がある選手は178名。
- PDF・シュートアウト作図コードは破損していない。ID61の旧取込経路では正常表示でき、欠落原因は一括取込が集計行だけを保存したこと。
- 修復はID61の各ゲーム取込経路を共通化し、22大会の公式PDFを再取得して各ゲーム、持越し、決勝方式、対戦結果を投入する。
- 今後の合格条件は、ポイント・賞金・ゲーム数・トータルピン・AVEの差分0、必要な全ゲーム存在、決勝完了・勝者一致、PDF全ページ画像確認。
- 詳細は `docs/operations/official_2026_results_gap_audit_20260722.md`。

## 2026-07-23 解消・2026年大会の詳細スコア／PDF修復

- 上記の「最優先注意」は第42バッチで解消した。公開済みST11大会、通常12大会へ詳細ゲームと決勝方式を再投入し、選抜大会ID144も別大会として追加した。
- 修復対象24大会は `game_scores=23,246`、対戦スコアシート62件、選手行173件、フレーム行1,730件。
- 年間監査は公開単位24件、539ライセンスで、ポイント・賞金・ゲーム数・トータルピン・AVEの差分0件。
- PDFは全トーナメント対戦、選抜A～D、最終スナップショットのフォールバックに対応し、連続生成時のBlade共有状態混入も防止した。
- 修復対象8大会43ページは画像化して空白0、端接触0、全ページ目視済み。2026年公開済み24大会の全PDF生成もFAIL 0。
- Unit38件・10,720アサーション、変更PHP/Blade 47ファイルの構文、Bladeキャッシュ、差分検査は成功。
- 次の大会データ作業は、2026-07-28開催予定のサマーD会場が公式公開された後に行う。
