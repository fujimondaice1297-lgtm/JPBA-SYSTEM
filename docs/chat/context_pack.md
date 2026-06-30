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
