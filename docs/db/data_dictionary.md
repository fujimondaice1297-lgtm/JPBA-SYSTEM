JPBA SYSTEM Database Data Dictionary
====================================

このファイルは **DBの正本**（single source of truth）です。  
マイグレーション・ER図・参照関係の管理は原則この辞書を基準にします。

- DB: PostgreSQL
- Project: jpba-system（Laravel）
- ER生成: `tools/generate_er_from_dictionary.php` で `docs/db/ER.dbml` を生成

---

## 目次

- [1. テーブル一覧](#1-テーブル一覧)
- [2. テーブル定義](#2-テーブル定義)
  - [annual_dues](#annual_dues)
  - [approved_ball_pro_bowler](#approved_ball_pro_bowler)
  - [approved_balls](#approved_balls)
  - [amateur_bowlers](#amateur_bowlers)
  - [area](#area)
  - [distribution_patterns](#distribution_patterns)
  - [districts](#districts)
  - [game_scores](#game_scores)
  - [score_import_batches](#score_import_batches)
  - [score_import_rows](#score_import_rows)
  - [score_import_row_candidates](#score_import_row_candidates)
  - [group_mail_recipients](#group_mail_recipients)
  - [group_mailouts](#group_mailouts)
  - [group_members](#group_members)
  - [groups](#groups)
  - [hof_inductions](#hof_inductions)
  - [hof_photos](#hof_photos)
  - [information_files](#information_files)
  - [informations](#informations)
  - [instructor_registry](#instructor_registry)
  - [instructors](#instructors)
  - [kaiin_status](#kaiin_status)
  - [license](#license)
  - [media_publications](#media_publications)
  - [place](#place)
  - [point_distributions](#point_distributions)
  - [prize_distributions](#prize_distributions)
  - [pro_bowler_biographies](#pro_bowler_biographies)
  - [pro_bowler_instructor_info](#pro_bowler_instructor_info)
  - [pro_bowler_links](#pro_bowler_links)
  - [pro_bowler_ranking_snapshots](#pro_bowler_ranking_snapshots)
  - [pro_bowler_ranking_rows](#pro_bowler_ranking_rows)
  - [pro_bowler_seed_lists](#pro_bowler_seed_lists)
  - [pro_bowler_seed_list_players](#pro_bowler_seed_list_players)
  - [tournament_seed_players](#tournament_seed_players)
  - [pro_bowler_profiles](#pro_bowler_profiles)
  - [pro_bowler_sponsors](#pro_bowler_sponsors)
  - [pro_bowler_titles](#pro_bowler_titles)
  - [pro_bowler_trainings](#pro_bowler_trainings)
  - [pro_bowlers](#pro_bowlers)
  - [pro_test](#pro_test)
  - [pro_test_attachment](#pro_test_attachment)
  - [pro_test_category](#pro_test_category)
  - [pro_test_comment](#pro_test_comment)
  - [pro_test_result_status](#pro_test_result_status)
  - [pro_test_schedule](#pro_test_schedule)
  - [pro_test_score](#pro_test_score)
  - [pro_test_score_summary](#pro_test_score_summary)
  - [pro_test_status_log](#pro_test_status_log)
  - [pro_test_venue](#pro_test_venue)
  - [record_types](#record_types)
  - [registered_balls](#registered_balls)
  - [sessions](#sessions)
  - [sexes](#sexes)
  - [stage_settings](#stage_settings)
  - [tournament_awards](#tournament_awards)
  - [tournament_entries](#tournament_entries)
  - [tournament_entry_balls](#tournament_entry_balls)
  - [tournament_files](#tournament_files)
  - [tournament_organizations](#tournament_organizations)
  - [tournament_participants](#tournament_participants)
  - [tournament_points](#tournament_points)
  - [tournament_round_lane_assignments](#tournament_round_lane_assignments)
  - [tournament_results](#tournament_results)
  - [tournament_result_snapshots](#tournament_result_snapshots)
  - [tournament_result_snapshot_rows](#tournament_result_snapshot_rows)
  - [tournaments](#tournaments)
  - [trainings](#trainings)
  - [used_balls](#used_balls)
  - [users](#users)
  - [venues](#venues)

---

# 1. テーブル一覧

（列一覧は `docs/db/columns_by_table.md` を参照）

---

# 2. テーブル定義

## annual_dues

### 役割
年会費（年度ごとの会費納付状況）を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- pro_bowler_id（誰の年会費か）
- year（年度）
- amount（会費額）
- paid_at（支払い日：nullable）

### 外部キー（FK）
- pro_bowler_id -> pro_bowlers.id

---

## approved_ball_pro_bowler

### 役割
公認ボールとプロボウラーの紐付け（年度など）を保持する中間テーブル。
既存の `pro_bowler_license_no`（文字列）を残しつつ、段階移行のため `pro_bowler_id`（nullable）を追加して「ID参照」も可能にする。

### 主キー
- id (bigint)

### 主要カラム
- pro_bowler_license_no（ライセンス番号：文字列）
- approved_ball_id（公認ボールID）
- year（年度）

### 外部キー（自動反映：refs_missing.md）
- approved_ball_pro_bowler.approved_ball_id -> approved_balls.id
- approved_ball_pro_bowler.pro_bowler_id -> pro_bowlers.id
---

## approved_balls

### 役割
JPBA公認ボールのマスタ。

### 主キー
- id (bigint)

### 主要カラム
- name（ボール名）
- manufacturer（メーカー）
- released_on（発売日：nullable）

---

## amateur_bowlers

### 役割
大会に出場するアマチュア選手の恒久マスター。
プロボウラープロフィール（`pro_bowlers`）には登録しないが、複数大会に同じアマチュア選手が出場する場合に、アマチュア識別番号・氏名・フリガナ・性別・利き腕・所属ボウリング場・用品契約などを再利用できるようにする。

### 主キー
- id (bigint)

### 主要カラム
- amateur_no（アマチュア識別番号：例 `A000001`。一意。大会をまたいで同一アマチュアを検索・再利用するための番号）
- name（氏名）
- name_kana（フリガナ：nullable）
- gender（M / F / X：nullable）
- dominant_arm（利き腕：右 / 左 / 両 など：nullable）
- affiliation_name（所属ボウリング場・所属先：nullable）
- equipment_contract（用品契約：nullable）
- note（備考：nullable）
- is_active（有効フラグ）
- created_at / updated_at

### 制約
- `amateur_no` は一意。未入力登録時はアプリ側で `A000001` 形式を自動採番する。

### 注意（運用方針）
- `amateur_bowlers` はアマチュア選手の再利用用マスターであり、プロ資格・公式戦出場資格の正本ではない。
- 大会への実際の参加は `tournament_participants` に `participant_type = amateur` として登録する。
- 同姓同名の可能性があるため、`name` だけで一意制約は張らない。検索・呼び出し時は `amateur_no` を最優先し、氏名、フリガナ、性別、所属なども確認する。
- 大会ごとの表示名・利き腕・所属・用品契約が公式資料と異なる場合は、`tournament_participants` 側の表示用カラムを優先する。
- 画面・PDF上で、ライセンスNo相当の欄は `アマ`、プロの「期」欄に相当する欄は `選手` と表示する。

### 外部キー（FK）
- なし

---
## area

### 役割
地域マスタ（ProTest等で使われる地域区分）。

### 主キー
- id (bigint)

### 主要カラム
- name（地域名）

---

## distribution_patterns

### 役割
ポイント配分・賞金配分のパターンマスタ。

### 主キー
- id (bigint)

### 主要カラム
- name（パターン名）

---

## districts

### 役割
地区（所属地区）マスタ。

### 主キー
- id (bigint)

### 主要カラム
- name（地区名：内部識別用の名称。旧来互換のため保持）
- label（表示名：正本。画面表示・選択肢は基本こちらを使用）

---

## game_scores

### 役割
大会のゲーム別スコア（1ゲームごとの点数）を保持するテーブル。
旧データ互換のため `license_number`（文字列）や `name` を残しつつ、段階移行のため `pro_bowler_id`（nullable）で `pro_bowlers` と紐付けられるようにする。
アマチュアや一時参加者を含む大会では、`tournament_participant_id` を使って大会内参加者行へ紐付ける。

### 主キー
- id (bigint)

### 主要カラム（DB実体）
- tournament_id（どの大会）
- tournament_participant_id（大会内参加者ID：nullable。プロ/アマを問わず大会内の同一人物判定に使う）
- stage（ステージ：入力元の値を保持）
- shift（シフト：入力元の値を保持）
- gender（性別：入力元の値を保持）
- license_number（ライセンス番号：文字列。アマチュアの場合は表示用に `アマ` を保持できる）
- name（氏名：入力元の値を保持）
- entry_number（エントリー番号：入力元の値を保持）
- game_number（ゲーム番号）
- score（スコア）
- pro_bowler_id（pro_bowlers.id：nullable。埋められる行はIDで紐付ける）

### 外部キー（FK）
- tournament_id -> tournaments.id
- tournament_participant_id -> tournament_participants.id（ON DELETE SET NULL）
- pro_bowler_id -> pro_bowlers.id（ON DELETE SET NULL）

### 注意（運用方針）
- `license_number` / `name` はスナップショット（当時の表記）として残す。
- アプリ側は、まず `tournament_participant_id`、次に `pro_bowler_id`、最後に `license_number` / `entry_number` / `name` の順で同一人物判定を行う。
- アマチュアは `tournament_participants.participant_type = amateur` の行に紐付け、プロフィールマスタ `pro_bowlers` には登録しない。
- アマチュアの画面表示では、ライセンスNo相当の欄に `アマ` を表示する。
- `license_number = アマ` / `display_license_no = アマ` は表示ラベルであり、人物検索キーとして使わない。アマチュアの人物特定は `tournament_participant_id`、または `tournament_participants.pro_bowler_license_no` の大会内番号（`AM-xxx`）、および `amateur_bowlers.amateur_no` を使う。
- 公式戦でアマチュアが優勝した場合は、最終成績側で `amateur_name` として大会履歴に残し、プロプロフィールは作成しない。

### インデックス
- tournament_participant_id
- `(tournament_id, stage, COALESCE(shift, ''), game_number, tournament_participant_id)` unique where tournament_participant_id is not null
  - 同一大会・同一ステージ・同一シフト・同一ゲーム・同一参加者の重複スコアを防ぐ。

---

## score_import_batches

### 役割
スコア取込（CSV / Excel / 写真OCR / 手入力補助）の1回分の処理単位を保持するステージング親テーブル。
解析結果をいきなり `game_scores` へ書かず、管理者確認・差戻し・再取込の状態を残すために使う。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id（対象大会）
- import_type（`csv` / `excel` / `photo_ocr` / `manual` など）
- source_filename / stored_path（元ファイル名と保存先）
- status（`draft` / `parsed` / `reviewing` / `confirmed` / `cancelled` / `failed` など）
- parser_version（解析ロジックのバージョン）
- imported_by / confirmed_by（取込者・確定者）
- row_count / accepted_row_count / rejected_row_count
- parsed_at / confirmed_at
- error_message / notes

### 外部キー（FK）
- tournament_id -> tournaments.id
- imported_by -> users.id
- confirmed_by -> users.id

### 注意（運用方針）
- OCRやCSV解析の信頼度が低い場合でも、このテーブルに処理単位を残し、確定前の確認画面で止める。
- `confirmed_at` が入るまでは `game_scores` / `tournament_results` への本反映済みとは扱わない。

---

## score_import_rows

### 役割
スコア取込バッチ内の1行・1セル相当の解析結果を保持するステージング明細テーブル。
スコア、氏名、ライセンス番号、ゲーム番号などを確認し、確定時に `game_scores` へ反映する。

### 主キー
- id (bigint)

### 主要カラム
- score_import_batch_id（取込バッチ）
- row_number（元ファイル上の行番号または解析順）
- raw_payload（元データ・OCR JSON等）
- parse_status（`pending` / `parsed` / `needs_review` / `accepted` / `rejected` など）
- confidence（解析信頼度）
- tournament_participant_id / pro_bowler_id
- license_number / name / entry_number
- stage / shift / gender
- game_number / score
- reviewed_by / reviewed_at
- confirmed_game_score_id（確定後に作成または更新したスコア行）
- error_message

### 外部キー（FK）
- score_import_batch_id -> score_import_batches.id
- tournament_participant_id -> tournament_participants.id
- pro_bowler_id -> pro_bowlers.id
- reviewed_by -> users.id
- confirmed_game_score_id -> game_scores.id

### 注意（運用方針）
- 人物判定は `tournament_participant_id` を最優先し、次に `pro_bowler_id`、最後に表示用文字列で確認する。
- `confirmed_game_score_id` が入っていない行は、本番スコアへ未反映として扱う。

---

## score_import_row_candidates

### 役割
取込行に対して、人物候補・ライセンス候補・スコアセル候補など複数の解析候補を保持するテーブル。
OCRや名寄せで迷った候補を保存し、管理者が選択した結果を `score_import_rows` へ反映するために使う。

### 主キー
- id (bigint)

### 主要カラム
- score_import_row_id（対象取込行）
- candidate_type（`participant` / `pro_bowler` / `license` / `name` / `score_cell` など）
- candidate_value（候補文字列）
- tournament_participant_id / pro_bowler_id
- confidence（候補信頼度）
- rank（候補順位）
- payload（解析器固有の詳細）
- is_selected（管理者が採用した候補）

### 外部キー（FK）
- score_import_row_id -> score_import_rows.id
- tournament_participant_id -> tournament_participants.id
- pro_bowler_id -> pro_bowlers.id

### 注意（運用方針）
- `is_selected = true` は最終判断の記録として使う。候補履歴は残し、OCR改善や誤認識分析に利用できるようにする。

---

## group_mail_recipients

### 役割
グループメール配信の宛先（受信者）を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- mailout_id（配信ID）
- pro_bowler_id（宛先のプロボウラーID）

### 外部キー（FK）
- mailout_id -> group_mailouts.id
- pro_bowler_id -> pro_bowlers.id

---

## group_mailouts

### 役割
グループメール配信（送信ログ）を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- group_id（どのグループ）
- sender_user_id（送信者ユーザー）
- subject（件名）
- body（本文）

### 外部キー（FK）
- group_id -> groups.id
- sender_user_id -> users.id

---

## group_members

### 役割
グループ（配信グループ等）とプロボウラーの所属を保持する中間テーブル。

### 主キー
- id (bigint)

### 主要カラム
- group_id（どのグループ）
- pro_bowler_id（誰が所属）

### 外部キー（FK）
- group_id -> groups.id
- pro_bowler_id -> pro_bowlers.id

---

## groups

### 役割
配信グループ等のマスタ。

### 主キー
- id (bigint)

### 主要カラム
- name（グループ名）

---

## hof_inductions

### 役割
殿堂入り（Hall of Fame）情報を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- pro_id（殿堂入り対象のプロボウラー）
- inducted_on（殿堂入り日：nullable）

### 外部キー（FK）
- pro_id -> pro_bowlers.id

---

## hof_photos

### 役割
殿堂入りの写真（複数枚）を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- hof_id（どの殿堂入りレコードか）
- file_path（画像パス）

### 外部キー（FK）
- hof_id -> hof_inductions.id

---

## informations

### 役割
お知らせ（案内）を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- title（タイトル：string）
- category（カテゴリ：string(32), nullable）
  - 許容値: NEWS / イベント / 大会 / ｲﾝｽﾄﾗｸﾀｰ
  - 備考: DB制約（CHECK）で上記以外は拒否（NULLは許容）
- body（本文：text）
- is_public（公開フラグ：boolean, default true）
- starts_at（公開開始：timestamp, nullable）
- ends_at（公開終了：timestamp, nullable）
- audience（公開対象：enum, default 'public'）
  - public / members / district_leaders / needs_training
- required_training_id（対象講習：bigint, nullable）
  - 備考: 参照先未確定（FKなし。refs_skipped / ADR参照）
- created_at / updated_at

### 制約
- informations_category_check（category の許容値制約）

### インデックス
- (is_public, audience)
- (starts_at, ends_at)
- required_training_id
- category

---

## information_files

### 役割
お知らせに紐づく添付ファイル（PDF/画像等）を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- information_id (bigint) : 親情報
- type (string(32)) : pdf / image / custom など
- title (string, nullable) : 表示名（任意）
- file_path (string) : storage パス
- visibility (string(16), default 'public') : public / members
- sort_order (int, default 0) : 表示順
- created_at / updated_at

### 外部キー（FK）
- information_id -> informations.id（ON DELETE CASCADE）

### インデックス
- (information_id, sort_order)
- type
- visibility

---

## instructor_registry

### 役割
インストラクター情報の新正本。
既存 `instructors` は `license_no` 主キー前提の互換テーブルとして残し、今後の正本管理は `instructor_registry` に段階移行する。

### 主キー
- id (bigint)

### 主要カラム
- source_type（取込元種別。例: `legacy_instructors` / `pro_bowler_csv` / `auth_instructor_csv` / `manual`）
- source_key（source_type 内で一意なキー）
- legacy_instructor_license_no（旧 `instructors.license_no` の退避：nullable）
- pro_bowler_id（対象プロボウラー：nullable）
- license_no（ライセンス番号：nullable）
- cert_no（認定番号：nullable）
- name / name_kana
- sex（boolean, nullable）
- district_id（所属地区：nullable）
- instructor_category（`pro_bowler` / `pro_instructor` / `certified`）
- grade（インストラクター区分：nullable）
- coach_qualification（スクール開講資格等の補助フラグ）
- source_registered_at（元データ上の登録日・交付日・開始日：nullable）
- is_current（現在有効な所属状態か）
- superseded_at（後続状態に置き換わった日時：nullable）
- supersede_reason（置換理由：nullable）
- is_active
- is_visible
- renewal_year（更新対象年度：nullable）
- renewal_due_on（更新期限：nullable。原則 12/31）
- renewal_status（更新状態：nullable。`pending` / `renewed` / `expired`）
- renewed_at（更新完了日：nullable）
- renewal_note（更新備考：nullable）
- last_synced_at（最終同期日時：nullable）
- notes（備考：nullable）

### 制約
- `(source_type, source_key)` 一意
- `instructor_category` は `pro_bowler / pro_instructor / certified`
- `grade` は `instructors.grade` と同じ許容値
- `renewal_status` は `pending / renewed / expired` または NULL

### grade の運用値
- `C級`
- `準B級`
- `B級`
- `準A級`
- `A級`
- `2級`
- `1級`

### renewal_status の運用値
- `pending`（更新対象・未更新）
- `renewed`（更新済み）
- `expired`（期限切れ / 年次更新失効）

### supersede_reason の代表値
- `promoted_to_pro_instructor`（認定インストラクター → プロインストラクターへ昇格）
- `promoted_to_pro_bowler`（認定インストラクター / プロインストラクター → プロボウラーへ昇格）
- `downgraded_to_certified`（プロボウラー / プロインストラクター → 認定インストラクターへ降格）
- `certified_not_renewed`（認定インストラクターが当年更新されず失効）
- `inactive_in_source`（取込元CSV上で無効）
- `qualification_removed`（プロ系資格が取込結果上で消滅し、復帰先の有効認定資格も無い）
- `replaced_by_pro_bowler_import`（同一カテゴリ行を `pro_bowler_csv` 再取込で置換）
- `category_changed`（上記以外のカテゴリ変更）

### 注意（運用方針）
- 新規正本は `instructor_registry` とする。
- 既存 `instructors` は既存画面・既存Controller互換のため当面維持する。
- 初回 backfill は既存 `instructors` から `source_type = legacy_instructors` として投入する。
- `license_no` / `cert_no` はどちらか片方だけでも保持できる設計にする。
- `source_type = pro_bowler_csv` は `Pro_colum.csv` を `pro_bowlers` に取り込んだ後の同期結果を表す。
- `source_type = pro_bowler_csv` のプロ系 row は、`license_no + instructor_category` 単位で current / history を持つ。`source_key` は原則 `{license_no}:{instructor_category}` を使う。
- `source_type = auth_instructor_csv` は `AuthInstructor.csv` を独立投入した認定インストラクター行を表す。
- `AuthInstructor.csv` は専用の認定番号列を持たないため、当面は `#ID` を `source_key` および `cert_no` に使って一意管理する。
- `AuthInstructor.csv` は `license_no` 一致を最優先に `pro_bowlers` へ自動結線する。
- `AuthInstructor.csv` で `license_no` が空、または `license_no` で結線できない場合は、`name_kanji` を含む複数条件（例: `name_kana` / `sex` / `district_id`）で **一意に特定できた場合のみ** `pro_bowlers` に自動結線する。
- 名前だけ、または名前を含まない曖昧条件では `pro_bowlers` に自動結線しない。
- 同一人物が別資格へ遷移する可能性があるため、旧行は物理削除せず `is_current = false` と `superseded_at` / `supersede_reason` で履歴化できる設計にする。
- 一覧・検索の既定は `is_current = true` を対象にする。
- `pro_instructor` の件数比較や検索条件は、`license_no` の文字列検索ではなく `instructor_category = 'pro_instructor'` かつ `is_current = true` を正本条件とする。
- `legacy_instructor_license_no` は互換移行用の退避列であり、FKは張らない。
- 年次更新管理は `renewal_year` / `renewal_due_on` / `renewal_status` / `renewed_at` / `renewal_note` を正本とする。
- 毎年の更新期限は原則 `12/31` とし、更新専用一覧では `renewal_year` と `renewal_status` を主な絞り込み軸にする。
- `AuthInstructor.csv` の年次取込では、当年CSVに存在する current `certified` 行を `renewed`、当年CSVに存在しない current `auth_instructor_csv` 行を `expired` として扱う。
- `AuthInstructor.csv` 取込時に、current な `pro_bowler` / `pro_instructor` 行が見つかった認定行は、`promoted_to_pro_bowler` または `promoted_to_pro_instructor` で履歴化する。
- `pro_bowler_csv` / manual 由来のプロ系 current 行は、年次更新対象として `renewal_status = pending` から管理できるようにする。
- `Pro_colum.csv` 取込時にプロ系資格対象外になった行は、対応する有効な `certified` 行があればその認定行を current に戻し、旧プロ系行は `downgraded_to_certified` として履歴化する。
- `Pro_colum.csv` 取込時にプロ系資格対象外になり、復帰先の有効な `certified` 行も無い場合は、旧プロ系行を `qualification_removed` として履歴化する。

### 外部キー（FK）
- instructor_registry.pro_bowler_id -> pro_bowlers.id（ON DELETE SET NULL）
- instructor_registry.district_id -> districts.id（ON DELETE SET NULL）

## instructors

### 役割
旧インストラクター管理の互換テーブル。
既存画面・既存Controllerが `license_no` 主キー前提で動いているため当面は残すが、新規正本は `instructor_registry` とする。

### 主キー
- license_no (string)

### 主要カラム
- license_no（ライセンス番号）
- pro_bowler_id（対象プロボウラー：nullable）
- name / name_kana
- sex
- district_id（所属地区）
- instructor_type（`pro` / `certified`）
- grade（インストラクター区分：nullable）
- is_active
- is_visible
- coach_qualification（スクール開講資格等の補助フラグ）

### grade の運用値
- `C級`
- `準B級`
- `B級`
- `準A級`
- `A級`
- `2級`
- `1級`

### 注意（運用方針）
- 新規正本は `instructor_registry` とする。
- `instructors` は既存画面・既存Controller互換のため当面維持する互換テーブルであり、新規機能の参照正本にはしない.
- `instructor_type = 'pro'` の行には、`pro_bowler` / `pro_instructor` の両方が混在し得る。正式な種別判定は `instructor_registry.instructor_category` を正とする。
- manual 由来の認定系・プロインストラクター系データの正本管理は `instructor_registry` 側で行う。
- `master_status` は別資格であり、`grade` には含めない。

### 外部キー（FK）
- instructors.pro_bowler_id -> pro_bowlers.id（ON DELETE SET NULL）
- instructors.district_id -> districts.id

---

## kaiin_status

### 役割
会員種別マスタ。`pro_bowlers.membership_type` の参照先であり、現役/退会等の判定正本として利用する。

### 主キー
- id (bigint)

### 主要カラム
- name（会員種別名）
- is_retired（退会扱いフラグ：boolean）

### 注意（運用方針）
- `死亡` / `除名` / `退会届` は `is_retired = true` とする。
- `pro_bowlers.is_active` は、`membership_type` と `kaiin_status.is_retired` に整合するよう importer / backfill で維持する。

---

## license

### 役割
ライセンス種別マスタ（ProTest等で利用される可能性）。

### 主キー
- id (bigint)

### 主要カラム
- name（ライセンス名）

---

## media_publications

### 役割
大会のメディア露出（掲載/放映）情報を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id（どの大会）
- media_name（媒体名）
- published_on（掲載日：nullable）

### 外部キー（FK）
- tournament_id -> tournaments.id

---

## place

### 役割
場所マスタ（ProTest等で使われる区分）。

### 主キー
- id (bigint)

### 主要カラム
- name（場所名）

---

## point_distributions

### 役割
大会ごとのポイント配分（順位→ポイント）を保持するテーブル。  
現行アプリの大会成績反映では、`tournament_points` ではなく **このテーブルを正本** として参照する。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id（どの大会）
- rank（順位）
- points（ポイント）
- pattern_id（配分パターン：nullable）

### 注意（運用方針）
- 現在の大会成績保存・再計算処理は `point_distributions.points` を参照する。
- 大会ごとの個別配分を保持する正本は `point_distributions` とする。
- `pattern_id` はテンプレート由来で投入した場合の参照用であり、手入力のカスタム配分では NULL を許容する。
- 運用上は `tournament_id + rank` 単位で1件に揃える。

### 外部キー（FK）
- tournament_id -> tournaments.id
- pattern_id -> distribution_patterns.id

---

## prize_distributions

### 役割
大会ごとの賞金配分（順位→賞金額）を保持するテーブル。  
現行アプリの大会成績反映では、`tournament_awards` ではなく **このテーブルを正本** として参照する。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id（どの大会）
- rank（順位）
- amount（賞金額）
- pattern_id（配分パターン：nullable）

### 注意（運用方針）
- 現在の大会成績保存・再計算処理は `prize_distributions.amount` を参照する。
- 大会ごとの個別配分を保持する正本は `prize_distributions` とする。
- `pattern_id` はテンプレート由来で投入した場合の参照用であり、手入力のカスタム配分では NULL を許容する。
- 運用上は `tournament_id + rank` 単位で1件に揃える。

### 外部キー（FK）
- tournament_id -> tournaments.id
- pattern_id -> distribution_patterns.id

---

## pro_bowlers

### 役割
プロボウラーの基本情報（競技者ハブ）を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム（抜粋）
- license_no（ライセンス番号：文字列）
- name_kanji / name_kana
- sex（sexes.id 参照）
- district_id（所属地区：nullable）
- membership_type（会員種別：`kaiin_status.name` 参照）
- member_class（業務判定用の所属区分）
  - `player`
  - `pro_instructor`
  - `honorary_or_overseas`
  - `other`
- can_enter_official_tournament（公式戦出場可否）
- is_active（有効フラグ。運用上は `membership_type` と `kaiin_status.is_retired` に整合させる）
- is_visible
- login_id（参照先未確定のためFKなし：ADR参照）
- （他、多数）

### 注意（運用方針）
- `pro_bowlers` は **競技者ハブ** として扱う。
- `membership_type` は元データの会員種別名を保持する生値とする。
- `member_class` は業務判定用の派生区分であり、競技導線・画面条件分岐は原則こちらを使う。
- `can_enter_official_tournament` は公式戦の出場可否を示す補助フラグであり、一覧・大会系導線は原則こちらを使う。
- 現役/退会等の正本は `membership_type` と `kaiin_status.is_retired` とする。
- `is_active` は公開・検索などで使う補助フラグであり、`membership_type` と不整合にならないよう importer / migration で維持する。
- `member_class = 'player'` かつ `is_active = true` の行を、競技系では「現在の公式戦対象者」として扱う。
- `member_class = 'pro_instructor'` の行は `pro_bowlers` に保持されていても、競技系では `can_enter_official_tournament = false` として扱う。
- ティーチングプロ判定は `license_no like '%T%'` のような文字列検索を正とせず、`member_class = 'pro_instructor'` を正本条件とする。
- 代表例:
  - `T015`
  - `M0000T015`
  - `F0000T004`
  のような教示系ライセンスは、importer / 保存処理で `member_class = 'pro_instructor'` へ正規化して扱う。

### 外部キー（自動反映：refs_missing.md）
- pro_bowlers.district_id -> districts.id
- pro_bowlers.sex -> sexes.id
- pro_bowlers.membership_type -> kaiin_status.name

---

## pro_bowler_profiles

### 役割
プロボウラーの詳細プロフィール（公開/非公開の拡張項目）を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- pro_bowler_id（どのプロボウラーか）
- （プロフィール詳細）

### 外部キー（FK）
- pro_bowler_id -> pro_bowlers.id

---

## pro_bowler_links

### 役割
SNS等リンク集を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- pro_bowler_id（どのプロボウラーか）
- kind（twitter/instagram 等）
- url

### 外部キー（FK）
- pro_bowler_id -> pro_bowlers.id

---

## pro_bowler_sponsors

### 役割
スポンサー情報を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- pro_bowler_id（どのプロボウラーか）
- sponsor_name

### 外部キー（FK）
- pro_bowler_id -> pro_bowlers.id

---

## pro_bowler_ranking_snapshots

### 役割
プロボウラーの年度別ランキングを、年度・性別・集計時点ごとに保持するヘッダテーブル。

JPBA公式サイトの当該年度ランキング、前年度最終ランキング、全日本選手権開催時点ランキングなどを、あとから再現できるようにスナップショットとして保存する。

### 主キー
- id (bigint)

### 主要カラム
- ranking_year（ランキング年度）
- gender（M / F / X など）
- ranking_type（ランキング種別）
  - `point` = ポイントランキング
  - 将来必要になれば別種別を追加する
- ranking_scope（集計範囲）
  - `annual` = 年度ランキング
  - `current` = 当該年度途中ランキング
  - `annual_final` = 前年度最終ランキング
  - `all_japan_cutoff` = 全日本選手権開催時点ランキング
- as_of_date（集計時点日付：nullable）
- is_final（最終ランキングかどうか）
- source_url（参照元URL：nullable）
- notes（備考：nullable）

### 注意（運用方針）
- ランキングは `pro_bowlers` の現在値だけで再計算せず、公式発表時点の情報をスナップショットとして保持する。
- 通常大会のトーナメントシード抽出では、前年度最終ランキングを使う。
- 全日本選手権では、前年度ランキングではなく開催時点の当該年度ランキングを使うため、`as_of_date` を保持する。
- ランキング行の詳細は `pro_bowler_ranking_rows` に保持する。
- `source_url` は公式ページやPDFなど、取り込み元確認用として保持する。

### 外部キー（FK）
- なし

---

## pro_bowler_ranking_rows

### 役割
`pro_bowler_ranking_snapshots` にぶら下がるランキング明細。

順位、ライセンスNo、氏名、期、所属 / 用品契約、ポイント、AVG、獲得賞金額など、公式ランキング表の1行分を保持する。

### 主キー
- id (bigint)

### 主要カラム
- ranking_snapshot_id（どのランキングスナップショットか）
- ranking_rank（ランキング順位）
- pro_bowler_id（対象プロボウラー：nullable）
- license_no（ライセンスNoスナップショット：nullable）
- name_kanji（氏名スナップショット：nullable）
- name_kana（フリガナスナップショット：nullable）
- kibetsu（期：nullable）
- organization_name（所属スナップショット：nullable）
- equipment_contract（用品契約スナップショット：nullable）
- points（ランキングポイント：nullable）
- games（ゲーム数：nullable）
- total_pin（トータルピン：nullable）
- average（AVG：nullable）
- prize_money（獲得賞金額：nullable）
- sort_order（表示順：nullable）

### 注意（運用方針）
- `pro_bowler_id` が解決できる場合は紐づける。
- ただし、公式ランキング表の当時表示を再現するため、`license_no` / `name_kanji` / `kibetsu` / `organization_name` / `equipment_contract` はスナップショットとして保持する。
- `ranking_rank` は同一 `ranking_snapshot_id` 内で一意にする。
- 前年度ランキング上位24名のトーナメントシード抽出では、このテーブルの `ranking_rank` を使う。

### 外部キー（FK）
- ranking_snapshot_id -> pro_bowler_ranking_snapshots.id（ON DELETE CASCADE）
- pro_bowler_id -> pro_bowlers.id（nullable, ON DELETE SET NULL）

---

## pro_bowler_seed_lists

### 役割
年度・性別ごとのトーナメントシード一覧ヘッダ。

前年度最終ランキング上位者、永久シード、準永久シード、全日本選手権者シード、当該年度優勝者シード、前年度優勝者シードなどをまとめ、公式サイト左メニューの「トーナメントシード」相当の一覧を再現するために使う。

### 主キー
- id (bigint)

### 主要カラム
- seed_year（シード年度）
- gender（M / F / X など）
- seed_list_type（シードリスト種別）
  - `tournament_seed` = トーナメントシード一覧
- source_ranking_snapshot_id（元ランキング：nullable）
- base_ranking_year（元ランキング年度：nullable）
- base_top_count（ランキングから自動抽出する人数。通常24）
- as_of_date（作成 / 集計時点日付：nullable）
- is_active（有効フラグ）
- source_url（参照元URL：nullable）
- notes（備考：nullable）

### 注意（運用方針）
- 通常大会では、前年度最終ランキング上位24名をトーナメントシードとして扱う。
- その年度のシード一覧は、`pro_bowler_seed_list_players` に明細として保持する。
- 前年度ランキング上位24名は `seed_category = TS` として登録する。
- 永久シード、準永久シード、全日本選手権者シード、当該年度優勝者シード、前年度優勝者シードなどは、ランキング上位24名とは別根拠として追加できるようにする。
- 全日本選手権の出場優先順位では、必要に応じて当該年度途中ランキングの `pro_bowler_ranking_snapshots` を参照する。

### 外部キー（FK）
- source_ranking_snapshot_id -> pro_bowler_ranking_snapshots.id（nullable, ON DELETE SET NULL）

---

## pro_bowler_seed_list_players

### 役割
`pro_bowler_seed_lists` にぶら下がるシード対象選手明細。

年度共通のトーナメントシード、永久シード、準永久シード、全日本選手権者シード、当該年度優勝者シード、前年度優勝者シードなどを選手単位で保持する。

### 主キー
- id (bigint)

### 主要カラム
- seed_list_id（どのシードリストか）
- pro_bowler_id（対象プロボウラー：nullable）
- license_no（ライセンスNoスナップショット：nullable）
- seed_category（シード区分）
  - `TS` = トーナメントシード
  - `V20` = 永久シード
  - `V10` = 準永久シード
  - `JS` = 全日本選手権者シード
  - `CS1` = 当該年度公認トーナメント優勝者シード
  - `CS2` = 前年度公認トーナメント優勝者シード
  - `PAST_CHAMPION` = 歴代優勝者シード
  - `MANUAL` = 手動追加
- seed_rank（シード一覧上の順位：nullable）
- ranking_snapshot_id（根拠ランキング：nullable）
- ranking_rank（根拠ランキング順位：nullable）
- source_tournament_id（根拠大会：nullable）
- pro_bowler_title_id（根拠タイトル：nullable）
- priority_order（出場優先順位：nullable）
- note（備考：nullable）
- is_active（有効フラグ）

### 注意（運用方針）
- 前年度ランキング上位24名は、`seed_category = TS` として自動生成する。
- 永久シードや準永久シードは、`pro_bowlers.permanent_seed_date` などの既存情報を参照しつつ、最終的にはこのテーブルに年度別のシード対象として保持する。
- 歴代優勝者・優勝者シードは、`pro_bowler_titles` を根拠として登録できるようにする。
- `license_no` はPDF表示や取り込み時の照合に使うため、`pro_bowler_id` が後から変わっても当時値を残す。
- 同一シードリスト内で、同じ `license_no + seed_category` は重複登録しない。

### 外部キー（FK）
- seed_list_id -> pro_bowler_seed_lists.id（ON DELETE CASCADE）
- pro_bowler_id -> pro_bowlers.id（nullable, ON DELETE SET NULL）
- ranking_snapshot_id -> pro_bowler_ranking_snapshots.id（nullable, ON DELETE SET NULL）
- source_tournament_id -> tournaments.id（nullable, ON DELETE SET NULL）
- pro_bowler_title_id -> pro_bowler_titles.id（nullable, ON DELETE SET NULL）

---

## tournament_seed_players

### 役割
大会ごとの追加シード対象選手を保持するテーブル。

年度共通のトーナメントシードだけでなく、東海オープンの歴代優勝者枠のような大会別シード、手動追加シード、大会ごとの特別扱いを保持する。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id（どの大会か）
- pro_bowler_id（対象プロボウラー：nullable）
- license_no（ライセンスNoスナップショット：nullable）
- seed_source_type（大会別シード根拠）
  - `seed_list` = 年度別シードリスト由来
  - `previous_year_ranking_top24` = 前年度ランキング上位24名
  - `current_year_ranking` = 当該年度ランキング
  - `permanent_seed` = 永久シード
  - `semi_permanent_seed` = 準永久シード
  - `all_japan_champion` = 全日本選手権者シード
  - `current_year_winner` = 当該年度優勝者シード
  - `previous_year_winner` = 前年度優勝者シード
  - `past_champion` = 公認T/M歴代優勝者シード
  - `event_sponsor_recommendation` = 本大会スポンサー推薦
  - `organizer_recommendation` = 主催者推薦
  - `pro_test_practical_exempt` = プロテスト実技免除合格者
  - `pro_test_top_passer` = プロテストトップ合格者
  - `season_trial_participant` = シーズントライアル出場者
  - `manual` = 手動追加
- seed_list_player_id（年度別シード明細：nullable）
- ranking_snapshot_id（根拠ランキング：nullable）
- ranking_rank（根拠ランキング順位：nullable）
- source_tournament_id（根拠大会：nullable）
- pro_bowler_title_id（根拠タイトル：nullable）
- priority_order（出場優先順位：nullable）
- display_label（PDFや管理画面で表示するラベル：nullable）
- note（備考：nullable）
- is_active（有効フラグ）

### 注意（運用方針）
- 大会PDFで `S 1443` のように表示するかどうかは、このテーブルと年度別シードリストを統合して判定する。
- `tournament_entries` へ単純な `is_seed` を持たせるのではなく、シード根拠をこのテーブルで保持する。
- `tournament_entries` は引き続き、申込・抽選・waitlist・チェックインの正本とする。
- 大会別に歴代優勝者、永久シード、手動追加などを設定する場合は、このテーブルに登録する。
- 通常大会では年度別シードリスト由来の対象を取り込みつつ、大会別追加シードを上乗せする。
- 全日本選手権では、前年度ランキングではなく当該年度開催時点ランキングを根拠にする場合があるため、`ranking_snapshot_id` を nullable で保持する。
- PDF上のライセンスNoは既存方針どおり下4桁表示を基本とし、シード対象の場合のみ先頭に `S` を付ける。

### 外部キー（FK）
- tournament_id -> tournaments.id（ON DELETE CASCADE）
- pro_bowler_id -> pro_bowlers.id（nullable, ON DELETE SET NULL）
- seed_list_player_id -> pro_bowler_seed_list_players.id（nullable, ON DELETE SET NULL）
- ranking_snapshot_id -> pro_bowler_ranking_snapshots.id（nullable, ON DELETE SET NULL）
- source_tournament_id -> tournaments.id（nullable, ON DELETE SET NULL）
- pro_bowler_title_id -> pro_bowler_titles.id（nullable, ON DELETE SET NULL）

---

## pro_bowler_titles

### 役割
タイトル（優勝・タイトル獲得）情報を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- pro_bowler_id（どのプロボウラーか）
- tournament_id（どの大会か）
- title_name（タイトル名：nullable）

### 外部キー（FK）
- pro_bowler_id -> pro_bowlers.id
- tournament_id -> tournaments.id

---

## pro_bowler_trainings

### 役割
講習受講（training）情報を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- pro_bowler_id
- training_id

### 外部キー（FK）
- pro_bowler_id -> pro_bowlers.id
- training_id -> trainings.id

---

## trainings

### 役割
講習（training）マスタ。

### 主キー
- id (bigint)

### 主要カラム
- name（講習名）

---

## tournaments

### 役割
大会マスタ。大会の基本情報に加えて、運営設定、抽選設定、未抽選DM設定、右サイド表示用JSON、終了後の結果カード系JSONを保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- name（大会名）
- start_date / end_date
- year（開催年）
- gender（M/F/X）
- official_type（official / approved / other）
- title_category（normal / season_trial / excluded）
- result_flow_type（予選後の進行分岐）
  - `legacy_standard` = 既存（予選 → 準々決勝 → 準決勝 → 決勝）
  - `prelim_to_rr_to_final` = 予選 → ラウンドロビン → 決勝
  - `prelim_to_quarterfinal_to_rr_to_final` = 予選 → 準々決勝 → ラウンドロビン → 決勝
  - `prelim_to_single_elimination_to_final` = 予選 → トーナメント → 最終成績
  - `prelim_to_quarterfinal_to_single_elimination_to_final` = 予選 → 準々決勝 → トーナメント → 最終成績
  - `prelim_to_semifinal_to_single_elimination_to_final` = 予選 → 準決勝通算 → トーナメント → 最終成績
  - `prelim_to_shootout_to_final` = 予選 → シュートアウト → 最終成績
  - `prelim_to_quarterfinal_to_shootout_to_final` = 予選 → 準々決勝 → シュートアウト → 最終成績
  - `prelim_to_semifinal_to_shootout_to_final` = 予選 → 準決勝通算 → シュートアウト → 最終成績
- round_robin_qualifier_count（ラウンドロビン進出人数：nullable）
- round_robin_win_bonus（勝ちボーナス：nullable。既定30）
- round_robin_tie_bonus（引き分けボーナス：nullable。既定15）
- round_robin_position_round_enabled（順位決定ポジションマッチを行うか）
- single_elimination_qualifier_count（トーナメント進出人数：nullable）
- single_elimination_seed_source_result_code（トーナメント進出元の current snapshot result_code：nullable）
  - `prelim_total` = 予選通算成績から進出者を決める
  - `quarterfinal_total` = 準々決勝通算成績から進出者を決める
  - `semifinal_total` = 準決勝通算成績から進出者を決める
- single_elimination_seed_policy（トーナメントのseed/BYE配置方針：nullable）
  - `standard` = 標準配置
  - `higher_seed_bye` = 2の累乗に満たない場合、上位seedへBYEを優先
  - `custom` = `single_elimination_seed_settings` のJSON指定を使う
- single_elimination_seed_settings（トーナメントseed/BYE詳細設定・レーン表示設定：json nullable）
  - 例: `{"seed_overrides":[{"seed":1,"entry_round":2},{"seed":2,"entry_round":2}]}`
  - 例: `{"lane_settings":{"rounds":{"1":{"start_lane":3,"step":2,"width":2},"2":{"start_lane":19,"step":2,"width":2}}}}`
  - `lane_settings.rounds.{round_no}.start_lane` は、その回戦の一番上の対戦開始レーンを表す。
  - `lane_settings.rounds.{round_no}.step` は、次の試合へ進むときに増やすレーン数を表す。通常は2。
  - `lane_settings.rounds.{round_no}.width` は、1試合で使用するレーン幅を表す。通常は2で、`3-4L` のように表示する。
  - `lane_settings.matches.{match_key}` は、必要になった場合の試合単位のレーン表示上書きに使う。例: `{"SE:R1-M1":"3-4L"}`。
- result_carry_preset（成績持ち込みプリセット：nullable）
  - `default` = 標準（現行どおり）
  - `no_carry` = 全ステージ持ち込みなし
  - `reset_after_quarterfinal` = 予選→準々決勝までは持ち込み、準決勝からリセット
  - `reset_from_quarterfinal` = 予選から準々決勝へは持ち込まない
  - `carry_to_semifinal_reset_rr` = 予選→準々決勝→準決勝までは持ち込み、ラウンドロビンからリセット
  - `carry_prelim_to_semifinal_for_tournament` = 予選＋準決勝の通算でトーナメント進出者を決定
  - `custom` = `result_carry_settings` のJSON指定を使う
- result_carry_settings（成績持ち込み詳細設定：json nullable）
  - result_code ごとに、集計対象ステージを `source_stages` で保持する
  - 例: `{"semifinal_total":{"source_stages":["予選","準決勝"]}}`
- venue_id（会場：nullable）
- venue_name / venue_address / venue_tel / venue_fax
- entry_start / entry_end
- inspection_required
- spectator_policy（paid / free / none）
- admission_fee（nullable text）
- broadcast / streaming
- broadcast_url / streaming_url（nullable）
- prize（nullable text）
- entry_conditions（nullable text）
- materials（nullable text）
- previous_event / previous_event_url（nullable）
- image_path（旧互換の単体ポスター：nullable）
- hero_image_path（トップ画像：nullable）
- title_logo_path（大会タイトル左ロゴ：nullable）
- poster_images（複数ポスター：json nullable）
- extra_venues（追加会場：json nullable）

### 抽選運営設定
- use_shift_draw（bool）
- shift_codes（nullable string）
- accept_shift_preference（bool）
- shift_draw_open_at / shift_draw_close_at（nullable datetime）
- use_lane_draw（bool）
- lane_from / lane_to（nullable int）
- lane_draw_open_at / lane_draw_close_at（nullable datetime）
- lane_assignment_mode（single_lane / box）
- box_player_count（nullable int）
- odd_lane_player_count（nullable int）
- even_lane_player_count（nullable int）

### 未抽選DM 自動送信設定（現運用）
- shift_auto_draw_reminder_enabled（bool）
- shift_auto_draw_reminder_send_on（nullable date）
- lane_auto_draw_reminder_enabled（bool）
- lane_auto_draw_reminder_send_on（nullable date）

### 旧互換カラム
- auto_draw_reminder_enabled（bool）
- auto_draw_reminder_days_before（int）
- auto_draw_reminder_pending_type（string）
  - 初期実装の「何日前 + 対象種別」方式。
  - 既存データ互換のため残すが、今後の新規運用では直接編集しない。

### 右サイド / 終了後表示用 JSON
- sidebar_schedule（json nullable）
  - 右サイド「日程・成績」
  - 例: `[{date,label,href,separator}]`
- award_highlights（json nullable）
  - 右サイド「褒章達成」
  - 例: `[{type,player,game,lane,note,title,photo}]`
- gallery_items（json nullable）
  - 終了後ギャラリー
  - 例: `[{photo,title}]`
- simple_result_pdfs（json nullable）
  - 簡易速報PDF
  - 例: `[{file,title}]`
- result_cards（json nullable）
  - 決勝・優勝ハイライト
  - 例: `[{title,player,balls,note,url,photos,photo,file}]`

### 注意（運用方針）
- シフト抽選を使わない大会では `use_shift_draw = false` とし、`shift` は不要。
- レーン抽選を使わない大会では `use_lane_draw = false` とし、`lane` は不要。
- BOX運用では `odd_lane_player_count + even_lane_player_count = box_player_count` を必須とする。
- 希望シフト受付は `use_shift_draw = true` の大会でのみ有効とする。
- シフト未抽選DMは `shift_auto_draw_reminder_send_on` 当日に送信する。
- レーン未抽選DMは `lane_auto_draw_reminder_send_on` 当日に送信する。
- シフト未抽選DM本文には `shift_draw_close_at` を締切日として差し込む。
- レーン未抽選DM本文には `lane_draw_close_at` を締切日として差し込む。
- いずれのメールにも「期日までに未対応なら事務局側で一斉抽選を行う」旨を自動で含める。
- 送信日は、それぞれの抽選締切日以前に設定する。
- `shift_draw_close_at` を過ぎても `shift` が未確定の `tournament_entries.status = entry` は、事務局側の自動一括抽選対象とする。
- `lane_draw_close_at` を過ぎても `lane` が未確定の `tournament_entries.status = entry` は、事務局側の自動一括抽選対象とする。
- 自動一括抽選の実行履歴は `tournament_auto_draw_logs` を正本とする。
- 画像 / PDF はDBへバイナリ保存せず、`storage/public` 配下の相対パス文字列を保持する。
- `result_flow_type` は、速報入力・公開順位表・正式成績反映で『予選後にどの方式へ進むか』を決める正本とする。
- ラウンドロビン方式では、直前の current snapshot（`prelim_total` または `quarterfinal_total`）上位 `round_robin_qualifier_count` 名を seed 順とみなし、総当たり + ポジションマッチを表示・集計する。
- ラウンドロビンのスコア入力自体は `game_scores.stage = ラウンドロビン` を正本として継続利用する。
- ラウンドロビンの公開表示は、JPBAサンプルに合わせて `対戦表` と `8G成績` を基本単位とし、8G成績には `W-L-T` / `Bonus` / `RR合計` / `通算ポイント` を表示できるようにする。
- トーナメント方式は敗者復活なしのシングルエリミネーションとして扱う。
- トーナメント進出者は、`single_elimination_seed_source_result_code` で指定した current snapshot の順位を seed 順として抽出する。
- `single_elimination_qualifier_count` は大会ごとに設定し、8人 / 16人 / 24人 / 32人など可変人数に対応する。
- 進出人数が2の累乗でない場合は、内部的に次の2の累乗枠まで広げ、空き枠はBYEとして扱う。
- 1回戦シード / 2回戦シードなどは、`single_elimination_seed_settings` の `entry_round` で表現する。
  - `entry_round = 1` は1回戦から出場
  - `entry_round = 2` は1回戦シード
  - `entry_round = 3` は2回戦シード
- トーナメント表のレーン表示は、`single_elimination_seed_settings.lane_settings` で大会ごとに管理する。
  - `rounds` は回戦単位の自動採番設定であり、`start_lane` / `step` / `width` を保持する。
  - `matches` は `SE:Rn-Mn` 形式の試合単位上書き用であり、特別なレーン割当がある場合のみ使う。
  - 速報入力・公開速報・PDF用トーナメント表は、このJSONを参照して同じレーン表記を出す。
- トーナメント方式では敗者ラウンドを作らない。
- 同じラウンドで負けた選手は同順位タイとして扱う。
  - 準決勝敗退者は3位タイ
  - 準々決勝敗退者は5位タイ
  - ベスト16初戦敗退者は9位タイ
  - 32枠1回戦敗退者は17位タイ
- トーナメントのスコア入力自体は `game_scores.stage = トーナメント` を正本として継続利用する。
- 正式反映時には、ブラケットサイズ、round構成、seed設定、BYE設定、順位決定方針を `tournament_result_snapshots.calculation_definition` に保存する。
- 成績持ち込み設定は `result_carry_preset` と `result_carry_settings` を正本とする。
- 画面上はプルダウン選択を基本とし、コードを書けない運用者でも設定できるようにする。
- `result_carry_settings` は内部保存用JSONであり、正式成績反映時に `tournament_result_snapshots.calculation_definition.source_sets` へ変換して保存する。
- `source_stages` の最後のステージを scratch、それ以前のステージを carry として扱う。
  - 例: `["予選","準決勝"]` は、予選を carry、準決勝を scratch として `semifinal_total` を作る。
- 過去に反映済みの snapshot は、その時点の `calculation_definition` を保持するため、後から大会設定を変えても過去snapshotの計算根拠は維持される。
- シュートアウト方式は、標準では8名進出として扱う。
- シュートアウト進出者は、`shootout_seed_source_result_code` で指定した current snapshot の順位を seed 順として抽出する。
- 標準8名シュートアウトは以下の構成とする。
  - 1stマッチ: 5位〜8位通過の4名で1Gを投球し、最上位者のみ2ndマッチへ進出
  - 2ndマッチ: 2位〜4位通過の3名 + 1stマッチ勝者の計4名で1Gを投球し、最上位者のみ優勝決定戦へ進出
  - 優勝決定戦: 1位通過者 + 2ndマッチ勝者で1Gを投球し、勝者を優勝とする
- シュートアウト各マッチのスコアは、勝ち上がり者を決めるために使う。
- シュートアウト各マッチの敗退者順位は、そのマッチのスコア順では決めない。
- シュートアウト敗退者の順位は、進出元 snapshot の通過順位を引き継ぐ。
  - 例: 5位〜8位通過者の1stマッチで8位通過者が勝ち上がった場合でも、敗退した5位通過者は6位、6位通過者は7位、7位通過者は8位として扱う。
  - 2ndマッチでも同様に、敗退者は2位〜4位通過者および1st勝者のうち、勝者を除いた元通過順位順で3位〜5位を付ける。
- シュートアウトのスコア入力自体は `game_scores.stage = シュートアウト` を正本として継続利用する。
- 正式反映時には、シュートアウト進出元 snapshot、各マッチ構成、勝ち上がり結果、敗退者順位決定方針を `tournament_result_snapshots.calculation_definition` に保存する。

### 外部キー（FK）
- venue_id -> venues.id

---

## tournament_entries

### 役割
大会エントリー（申込）を保持するテーブル。  
通常参加だけでなく、抽選状態・チェックイン状態・ウェイティング管理もこのテーブルを正本として扱う。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id（どの大会）
- pro_bowler_id（誰がエントリー）
- status（申込状態）
  - `entry` = 参加
  - `no_entry` = 不参加
  - `waiting` = ウェイティング
- shift（シフト：nullable）
- lane（レーン：nullable）
- checked_in_at（チェックイン日時：nullable）
- is_paid（支払済フラグ）
- shift_drawn（シフト抽選済フラグ）
- lane_drawn（レーン抽選済フラグ）
- waitlist_priority（ウェイティング優先順：nullable）
- waitlisted_at（ウェイティング登録日時：nullable）
- waitlist_note（ウェイティング備考：nullable）
- promoted_from_waitlist_at（ウェイティングから繰り上げた日時：nullable）
- preferred_shift_code（希望シフト：nullable）


### 注意（運用方針）
- `tournament_entries` を大会参加管理の正本とする。
- 1大会1選手につき1行を原則とし、`(tournament_id, pro_bowler_id)` で一意管理する。
- 通常の抽選・使用ボール登録・チェックイン対象は `status = entry` の行のみとする。
- `status = waiting` は管理者が登録するウェイティング行として扱う。
- `status = waiting` の行は、抽選・使用ボール登録・チェックイン対象外とする。
- ウェイティングから参加に繰り上げる場合は、同じ行の `status` を `entry` に更新し、`promoted_from_waitlist_at` を記録する。
- シフト / レーン / チェックインは `status = entry` の大会当日運用情報として保持する。
- `preferred_shift_code` は会員がエントリー時に入力する希望シフトであり、受付用情報として保持する。
- 希望シフト受付は `tournaments.accept_shift_preference = true` の大会でのみ有効。
- 実際の `shift` は抽選確定結果であり、`preferred_shift_code` とは別に保持する。


### 外部キー（FK）
- tournament_id -> tournaments.id
- pro_bowler_id -> pro_bowlers.id

---

## tournament_draw_reminder_logs

### 役割
未抽選DMの送信履歴を保持するテーブル。
手動送信と自動送信の両方を記録し、自動送信時の二重送信防止に使う。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id
- tournament_entry_id
- reminder_kind（`manual` / `auto`）
- pending_type（`shift` / `lane` / `either`）
- scheduled_for_date（送信日：nullable）
- dispatch_key（自動送信の重複防止キー：nullable unique）
- recipient_email
- subject
- status（`sent` / `failed`）
- sent_at（nullable）
- error_message（nullable）

### 注意（運用方針）
- 自動送信では `dispatch_key` を
  `auto:{tournament_id}:{tournament_entry_id}:{pending_type}:{scheduled_for_date}`
  形式で生成し、同じ送信日・同じ対象への再送を防ぐ。
- 手動送信は再送を許容するため、`dispatch_key` は NULL で保持する。

### 外部キー（FK）
- tournament_id -> tournaments.id
- tournament_entry_id -> tournament_entries.id

---

## tournament_auto_draw_logs

### 役割
締切到来後に、事務局側で自動一括抽選を実行した履歴を保持するテーブル。
シフト抽選・レーン抽選のどちらを、いつ、何件処理したかを追跡する。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id
- target_type（`shift` / `lane`）
- deadline_at（対象締切日時：nullable）
- executed_at（実行日時）
- total_pending（実行時の未抽選対象件数）
- success_count（抽選成功件数）
- failed_count（抽選失敗件数）
- details_json（失敗明細などのJSON：nullable）

### 注意（運用方針）
- 自動一括抽選は scheduler / command から実行する。
- `target_type = shift` は `shift_draw_close_at` 超過後の未シフト者を対象にする。
- `target_type = lane` は `lane_draw_close_at` 超過後の未レーン者を対象にする。
- 既存の会員向け抽選ロジックと同じ割付ルールを使い、事務局側で強制確定する。
- `details_json` には失敗対象の `entry_id` / `license_no` / `name_kanji` / `message` などを保持する。

### 外部キー（FK）
- tournament_id -> tournaments.id

---

## tournament_entry_balls

### 役割
大会エントリーで使用するボール（複数）を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- tournament_entry_id（どのエントリー）
- used_ball_id（使用ボール：nullable）

### 注意（設計改善ポイント）
- tournament_entry_id / used_ball_id が nullable になっているので、将来的に運用上「必須」にしたいなら NOT NULL + FK を強化する余地あり。

### 外部キー（FK）
- tournament_entry_id -> tournament_entries.id
- used_ball_id -> used_balls.id

---

## tournament_participants

### 役割
大会の参加者一覧（または参加枠）を保持するテーブル。
既存の `pro_bowler_license_no`（文字列）を残しつつ、段階移行のため `pro_bowler_id`（nullable）を追加して「ID参照」も可能にする。
プロ選手だけでなく、アマチュアやスポンサー推薦などプロフィールマスタに登録しない一時参加者も、大会内参加者として保持する。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id（どの大会）
- pro_bowler_license_no（参加者のライセンス番号：文字列：既存互換のため保持。アマチュアは `AM-xxx` 等の大会内一意キーを保持）
- pro_bowler_id（参加者の pro_bowlers.id：nullable。プロ選手はIDで紐付ける）
- amateur_bowler_id（アマチュア選手マスターID：nullable。アマチュアは可能な範囲でIDで紐付ける）
- participant_type（参加者種別：`pro` / `amateur` / `temporary`）
- display_name（大会内表示名：nullable。アマチュアは必須運用）
- display_license_no（表示用ライセンスNo：nullable。アマチュアは `アマ` を保持）
- display_dominant_arm（大会時点の表示用利き腕：nullable）
- display_affiliation_name（大会時点の表示用所属ボウリング場・所属先：nullable）
- display_equipment_contract（大会時点の表示用品契約：nullable）
- gender（M / F / X：nullable）
- shift（シフト：nullable）
- lane（開始レーン：nullable）
- lane_slot（同一レーン内の表示順：nullable。例: `3L-2` の `2`）
- lane_label（開始レーン表示：nullable。例: `3L-2`）
- box_no（BOX番号：nullable）
- sort_order（大会内表示順：nullable）
- source_note（登録元・備考：nullable）
- is_temporary（一時参加者フラグ）

### 注意（運用方針）
- 既存データは `pro_bowler_license_no` のままでも壊れない。
- 可能なプロ選手行は `pro_bowler_id` を埋め、アプリ側は `pro_bowler_id` 優先で参照する。
- アマチュアは `pro_bowler_id = null`、`participant_type = amateur`、`display_license_no = アマ` として保持する。
- アマチュアが `amateur_bowlers` に存在する場合は `amateur_bowler_id` で紐付け、氏名・利き腕・所属ボウリング場・用品契約を再利用する。
- アマチュアの恒久的な人物識別は `amateur_bowlers.amateur_no` を使う。`pro_bowler_license_no = AM-xxx` は大会内参加者番号として扱い、全大会横断の人物番号にはしない。
- 大会時点の表示がマスターと異なる場合は、`display_name` / `display_dominant_arm` / `display_affiliation_name` / `display_equipment_contract` を優先して表示する。
- アマチュアは `pro_bowlers` へ登録しない。`amateur_bowlers` はアマチュア再利用用マスターであり、プロプロフィールとは別管理にする。大会中のレーン表・スコア入力・速報・成績表に必要な大会時点情報はこのテーブルに保持する。
- 成績PDFなどで、プロの「期」欄に相当する場所は、アマチュアの場合は `選手` と表示する。
- `tournament_entries` は従来どおりプロ選手の申込・抽選・使用ボール登録・チェックインの正本として維持する。
- 大会当日の出場者確定リスト、特にアマチュアを含む一覧・レーン表・スコア連携では `tournament_participants` を参照する。
- アマチュアをBOXに散らす必要がある大会では、アプリまたは投入スクリプト側で `box_no` / `lane` / `lane_slot` が偏らないように割り付ける。
- アマチュアが優勝または歴代優勝者として残る場合は、プロフィール作成ではなく、結果テーブル側の `amateur_name` に大会時点名を保存する。

### 外部キー（DB上で確認できたもの）
- tournament_participants.tournament_id -> tournaments.id
- tournament_participants.pro_bowler_id -> pro_bowlers.id（ON DELETE SET NULL）
- tournament_participants.amateur_bowler_id -> amateur_bowlers.id（ON DELETE SET NULL）

## tournament_round_lane_assignments

### 役割
大会ごとのラウンド別レーン割当を保持するテーブル。
予選、準決勝、決勝ラウンドロビン、ステップラダーなど、同じ大会参加者でもラウンドごとに別のレーン配置・移動表を持てるようにする。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id（どの大会）
- source_result_snapshot_id（進出者抽出元の成績スナップショット：nullable）
- tournament_participant_id（大会内参加者ID：nullable）
- pro_bowler_id（プロボウラーID：nullable）
- stage（ステージ名。例: `準決勝`）
- round_label（表示名。例: `準決勝4G`）
- game_from / game_to（対象ゲーム番号。例: 17〜20）
- seed_rank（進出元成績の順位）
- pro_bowler_license_no（ライセンスNoスナップショット：nullable）
- display_license_no（PDF表示用ライセンスNo。例: `S 582` / `CS 586`）
- display_name（PDF表示用氏名）
- period_label（期表示。アマチュアの場合は `選手`）
- dominant_arm（投球利き腕）
- affiliation_display（所属 / 用品契約の大会時点表示）
- source_total_pin（進出元成績のトータルピン）
- source_games（進出元成績のゲーム数）
- source_average（進出元成績のAVG）
- start_lane（スタートレーン）
- lane_slot（同一レーン内の枠）
- start_lane_label（開始位置表示。例: `19L-1`）
- box_no（BOX番号：nullable）
- movement_direction（`left` / `right` / `custom`）
- movement_box_step（移動BOX数）
- movement_boxes（ゲーム別使用BOXの配列JSON。例: `["19L･20L","17L･18L","15L･16L","9L･10L"]`）
- game_start_time（1G目開始予定時刻：nullable）
- game_interval_minutes（ゲーム間隔分：nullable）
- tv_lane_from / tv_lane_to（TV収録レーン範囲：nullable）
- sort_order（表示順）
- note（備考：nullable）
- created_at / updated_at

### 注意（運用方針）
- `tournament_participants.lane / lane_slot / lane_label` は大会参加者の基本配置・予選用配置として維持する。
- 準決勝以降の再抽選・再配置で `tournament_participants` を上書きしない。
- 準決勝、決勝ラウンドロビン、ステップラダーなどのラウンド別レーン表は、このテーブルを正本とする。
- 進出元の順位・トータルピン・AVG・表示名・所属は、PDF再出力時に当時表記を維持できるよう、このテーブルにもスナップショットとして保持する。
- 公式PDFのように「1Gごとに左隣BOXへ移動」「TV収録レーン9・10L」など、大会・ラウンドごとに異なる移動規則を保存できるようにする。
- `movement_boxes` が登録されている場合は、それをPDF表示の正本とする。自動計算よりも公式表記の再現を優先する。

### 制約
- `(tournament_id, stage, round_label, tournament_participant_id)` は一意。
  - 同じ大会・同じラウンドに同じ参加者を重複登録しない。

### インデックス
- `(tournament_id, stage, round_label)`
- `(source_result_snapshot_id, seed_rank)`
- `tournament_participant_id`

### 外部キー（FK）
- tournament_id -> tournaments.id（ON DELETE CASCADE）
- source_result_snapshot_id -> tournament_result_snapshots.id（ON DELETE SET NULL）
- tournament_participant_id -> tournament_participants.id（ON DELETE SET NULL）
- pro_bowler_id -> pro_bowlers.id（ON DELETE SET NULL）

---

## tournament_results

### 役割
大会結果（順位・ポイント・トータルピン・アベレージ・賞金など）を保持するテーブル。
既存の `pro_bowler_license_no`（文字列）を残しつつ、段階移行のため `pro_bowler_id`（nullable）を追加して「ID参照」も可能にする。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id（どの大会）
- pro_bowler_license_no（誰の結果か：文字列：既存互換のため保持）
- pro_bowler_id（誰の結果か：pro_bowlers.id：nullable。埋められる行はIDで紐付ける）
- ranking（順位）
- points（獲得合計ポイント）
- award_points（入賞ポイント：nullable。公式PDF表示用）
- step_points（ステップポイント：nullable。公式PDF表示用）
- total_pin（合計ピン）
- games（ゲーム数）
- average（アベレージ）
- prize_money（賞金）
- affiliation_display（所属 / 用品契約の大会時点表示スナップショット：nullable）
- ranking_year（年度：NOT NULL）
- amateur_name（アマ参加者名：nullable）

### 注意（運用方針）
- 既存データは `pro_bowler_license_no` のままでも壊れない。
- アマ参加者は `amateur_name`（および `pro_bowler_id` NULL）で保持する。
- `tournament_results` は **最終成績の正本** として扱う。
- `points` は獲得合計ポイント、`award_points` は入賞ポイント、`step_points` はステップポイントとして扱う。
- `affiliation_display` は大会PDF再出力時に当時表記を維持するための表示用スナップショットとする。原則は `pro_bowlers.organization_name` と `pro_bowlers.equipment_contract` から生成するが、大会時点表記が必要な場合はこの列を優先する。
- 予選前半 / 予選通算 / 準々決勝通算 / 準決勝通算などの途中公開単位は、`tournament_result_snapshots` / `tournament_result_snapshot_rows` に保存し、`is_final = true` の反映単位だけを `tournament_results` に同期する。
- `tournament_results` への自動同期は、`tournament_result_snapshots` の `final_total` から行う。
- 同期時には、snapshot row 側で解決済みの `pro_bowler_id` / 正式ライセンス番号 / 表示名を優先して保持する。

### 外部キー（DB上で確認できたもの）
- tournament_results.tournament_id -> tournaments.id
- tournament_results.pro_bowler_id -> pro_bowlers.id（ON DELETE SET NULL）

---

## tournament_result_snapshots

### 役割
大会速報（`game_scores`）から正式成績へ反映した単位を保持するヘッダテーブル。  
JPBA公式ページのような「予選前半成績」「予選通算成績」「準決勝通算成績」「最終成績」など、**公開粒度ごとの確定スナップショット** を保持する。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id（どの大会か）
- result_code（反映単位コード）
  - 例: `prelim_first_half` / `prelim_second_half` / `prelim_total` / `quarterfinal_stage` / `quarterfinal_total` / `semifinal_stage` / `semifinal_total` / `final_total`
- result_name（表示名）
  - 例: `予選前半成績` / `予選通算成績` / `準決勝通算成績` / `最終成績`
- result_type（計算方式）
  - 初期実装は `total_pin`
- stage_name（主対象ステージ名：nullable）
- gender（M/F：nullable）
- shift（単独シフト集計時のみ設定：nullable）
- games_count（この反映単位で集計対象になったゲーム数）
- carry_game_count（持ち込みゲーム数）
- carry_stage_names（持ち込み元ステージ名の配列：json, nullable）
- calculation_definition（反映条件JSON：nullable）
  - 例: `source_sets` として、どのステージの何G〜何Gを `scratch` / `carry` として集計したかを保持する
- reflected_at（反映実行日時）
- reflected_by（反映実行ユーザー：nullable）
- is_final（最終成績かどうか）
- is_published（公開済みかどうか）
- is_current（同一反映単位の現行版かどうか）
- notes（備考：nullable）

### 注意（運用方針）
- `game_scores` を速報入力の正本とし、このテーブルは **正式成績反映単位のヘッダ** として使う。
- 反映は **ボタン方式** とし、入力のたび自動確定はしない。
- 同一大会・同一反映単位で再反映する場合、旧行を `is_current = false` にし、新行を `is_current = true` とする運用を想定する。
- 初期実装は `total_pin` のみ対象とし、ラウンドロビン / ステップラダー / トーナメント / ダブルエリミネーション / シュートアウトは後続で拡張する。
- `calculation_definition` は、同じ `stage_name` でも「予選前半」「予選後半」「予選通算」のようなゲーム範囲差を再現するための正本条件として使う。
- `is_final = true` の反映単位だけを、既存 `tournament_results` へ同期対象とする。
- `calculation_definition` を正本とし、公開単位ごとの scratch / carry の対象ステージ、ゲーム範囲、集計条件を JSON で保持する。
- `final_total` でも、性別やシフトで絞って反映した snapshot は `tournament_results` へ同期しない。
- `final_total` かつ 性別=全体 かつ シフト=全体 の反映単位だけを、大会全体の最終成績として `tournament_results` に同期する。


### 外部キー（FK）
- tournament_id -> tournaments.id
- reflected_by -> users.id（nullable, ON DELETE SET NULL）


---

## tournament_result_snapshot_rows

### 役割
`tournament_result_snapshots` にぶら下がる順位表明細。  
1つの反映単位に対して、選手ごとの順位・トータルピン・carry 内訳・賞金・ポイント等を保持する。

### 主キー
- id (bigint)

### 主要カラム
- snapshot_id（どの反映単位か）
- ranking（順位）
- pro_bowler_id（対象プロボウラー：nullable）
- pro_bowler_license_no（ライセンス番号スナップショット：nullable）
- amateur_name（アマ参加者名：nullable）
- display_name（画面表示名）
- gender（M/F：nullable）
- shift（シフト：nullable）
- entry_number（エントリー番号：nullable）
- scratch_pin（今回ステージ単体のピン）
- carry_pin（持ち込みピン）
- total_pin（合算後のトータルピン）
- games（集計ゲーム数）
- average（アベレージ：nullable）
- tie_break_value（タイブレーク計算値：nullable）
- points（この反映単位で確定したポイント：nullable）
- prize_money（この反映単位で確定した賞金：nullable）

### 注意（運用方針）
- `display_name` は公開表示用の固定値を保持し、後から `pro_bowlers` 側の氏名が変わっても当時の表示を再現できるようにする。
- `pro_bowler_id` が取れない参加者は、`amateur_name` または `pro_bowler_license_no` を使って保持する。
- トータルピン方式では、`scratch_pin + carry_pin = total_pin` を基本とする。
- `points` / `prize_money` は、最終成績だけを確定反映する運用でも保持できるよう nullable で持つ。
- 同順位（タイ）があり得るため、`ranking` の一意制約は張らない。
- `pro_bowler_license_no` は速報入力時の生値をそのまま使わず、反映時に解決できた場合は `pro_bowlers.license_no` の正式値へ寄せる。
- 速報入力で下4桁ライセンス番号を使った場合でも、性別と組み合わせて `pro_bowlers` を一意解決できた行は `pro_bowler_id` / 正式ライセンス番号 / 正式氏名へ正規化して保持する。

### 外部キー（FK）
- snapshot_id -> tournament_result_snapshots.id（ON DELETE CASCADE）
- pro_bowler_id -> pro_bowlers.id（nullable, ON DELETE SET NULL）

---

## tournament_points

### 役割
大会の順位に対するポイント付与（ポイント表）を保持する旧互換テーブル。  
現物スキーマ上は `tournament_id / rank / point` を持つが、現行アプリの成績反映では **`point_distributions` を正本** として扱う。

### 主キー
- 主キー列なし（現物実装）
- 制約: `rank` に UNIQUE 制約あり

### 主要カラム
- tournament_id
- rank
- point

### 注意（運用方針）
- 現物スキーマでは `rank` に UNIQUE 制約があり、複数大会で共存しづらい旧設計になっている。
- 現行の大会成績保存・再計算は `tournament_points` を参照していない。
- 今後は `point_distributions` への寄せを前提に、`tournament_points` は旧互換用途として扱う。

### 外部キー（FK）
- tournament_id -> tournaments.id

---

## tournament_awards

### 役割
大会ごとの順位別賞金表を保持する旧互換テーブル。  
現物スキーマ上は `rank / prize_money` を持つが、現行アプリの成績反映では **`prize_distributions` を正本** として扱う。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id
- rank（順位）
- prize_money（賞金額）

### 注意（運用方針）
- 現物スキーマは「表彰名」ではなく、順位別賞金表として実装されている。
- 現行の大会成績保存・再計算は `tournament_awards` を参照していない。
- 今後は `prize_distributions` への寄せを前提に、`tournament_awards` は旧互換用途として扱う。

### 外部キー（FK）
- tournament_id -> tournaments.id

---

## tournament_files

### 役割
大会に紐づく添付ファイルを保持するテーブル。
大会要項（一般 / 選手）やオイルパターン表、任意の追加資料を大会単位で管理する。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id
- type（`outline_public` / `outline_player` / `oil_pattern` / `custom`）
- title（nullable）
- file_path
- visibility（`public` / `members`）
- sort_order

### 注意（運用方針）
- `outline_public` は一般公開用の大会要項。
- `outline_player` は会員向け / 選手向けの大会要項。
- `oil_pattern` はオイルパターン表。
- `custom` は任意追加資料。
- ファイル本体は `storage/public` に保存し、DBには相対パスを保持する。

### 外部キー（FK）
- tournament_id -> tournaments.id

---

## tournament_organizations

### 役割
主催 / 特別協賛 / 協賛 / 後援 / 協力などの大会組織情報を、大会単位で複数行保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id
- category（`host` / `special_sponsor` / `sponsor` / `support` / `cooperation`）
- name
- url（nullable）
- sort_order

### 注意（運用方針）
- 画面表示の正本はこのテーブル。
- 旧カラム `host` / `special_sponsor` / `sponsor` / `support` は互換表示用としてテキスト同期を残す。
- URLは任意で、ある場合は大会詳細から外部リンク表示する。

### 外部キー（FK）
- tournament_id -> tournaments.id

---

## tournament_match_score_sheets

### 役割
大会内の1G対戦スコア表ヘッダを保持するテーブル。  
シュートアウト、ステップラダー、ラウンドロビン、トーナメント等で、公式PDFに掲載するゲーム別スコアシートの単位を管理する。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id（どの大会か）
- sheet_type（score sheet 種別）
  - `shootout`
  - `step_ladder`
  - `round_robin`
  - `single_elimination`
  - `custom`
- stage_code（ステージ識別子：nullable）
- match_code（マッチ識別子：nullable）
- match_label（表示名：nullable）
  - 例: `優勝決定戦` / `シュートアウト2ndマッチ`
- match_order（PDF表示順）
- game_number（ゲーム番号）
- lane_label（レーン表示：nullable）
- is_published（PDF表示対象か）
- confirmed_at（確定日時：nullable）
- notes（備考：nullable）

### 注意（運用方針）
- `game_scores` は順位計算・勝ち上がり判定の正本として継続利用する。
- このテーブルは、公式PDFに掲載する「1Gスコア表」の表示・保存用正本とする。
- スコア入力は手入力だが、フレームごとの累計・最終スコア・勝者判定はアプリ側で自動計算する。
- PDF出力時は `is_published = true` の行を `match_order` 順に表示する。
- シュートアウト結果図とは別に、公式PDF下部にあるゲーム別スコア表を再現するために使う。

### 外部キー（FK）
- tournament_id -> tournaments.id（ON DELETE CASCADE）

---

## tournament_match_score_sheet_players

### 役割
`tournament_match_score_sheets` にぶら下がる選手別スコア表行を保持するテーブル。  
1つのスコア表に対して、2名対戦だけでなく、シュートアウト1st/2ndのような複数名マッチにも対応する。

### 主キー
- id (bigint)

### 主要カラム
- score_sheet_id（親スコア表）
- sort_order（表示順）
- player_slot（A/B/C/D 等の枠）
- pro_bowler_id（対象プロボウラー：nullable）
- pro_bowler_license_no（ライセンス番号スナップショット：nullable）
- display_name（表示名）
- name_kana（フリガナ：nullable）
- dominant_arm（右投げ/左投げ等：nullable）
- lane_label（レーン表示：nullable）
- final_score（自動計算された最終スコア）
- is_winner（このスコア表内の勝者か）
- score_summary（計算補助JSON：nullable）

### 注意（運用方針）
- `display_name` / `pro_bowler_license_no` はPDF再現用のスナップショットとして保持する。
- `pro_bowler_id` が取れる場合はID参照を優先する。
- 勝者判定は同一 `score_sheet_id` 内の `final_score` 最大者を基本とする。同点時の扱いは後続のタイブレーク方針で拡張する。

### 外部キー（FK）
- score_sheet_id -> tournament_match_score_sheets.id（ON DELETE CASCADE）
- pro_bowler_id -> pro_bowlers.id（nullable, ON DELETE SET NULL）

---

## tournament_match_score_frames

### 役割
選手別スコア表行にぶら下がる1〜10フレームの投球記号・フレームスコア・累計スコアを保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- score_sheet_player_id（親選手行）
- frame_no（1〜10）
- throw1 / throw2 / throw3（投球記号。10フレームのみ throw3 を使用）
- frame_score（そのフレームの加算点：nullable）
- cumulative_score（そのフレーム終了時点の累計：nullable）
- display_marks（PDF表示補助JSON：nullable）

### 注意（運用方針）
- 入力記号は `X`（ストライク）、`/`（スペア）、`-`（ミス）、`F`（ファール）、`0〜9` を基本とする。
- フレーム計算はアプリ側で行い、DBには計算後の `frame_score` / `cumulative_score` を保存する。
- 10フレームのみ3投目を許容する。
- 1選手行につき同一 `frame_no` は1件のみとする。

### 外部キー（FK）
- score_sheet_player_id -> tournament_match_score_sheet_players.id（ON DELETE CASCADE）

---

## venues

### 役割
会場マスタ。

### 主キー
- id (bigint)

### 主要カラム
- name
- address（nullable）
- tel（nullable）

---

## sexes

### 役割
性別マスタ。

### 主キー
- id (smallint)

### 主要カラム
- name

---

## users

### 役割
認証ユーザー。

### 主キー
- id (bigint)

### 主要カラム
- pro_bowler_id（紐付け：nullable）
- email / password 等

### 外部キー（FK）
- pro_bowler_id -> pro_bowlers.id

- users は pro_bowler_id を正規の紐付け軸とする（FK: users.pro_bowler_id -> pro_bowlers.id）。
- users.pro_bowler_license_no / users.license_no は互換・移行用の文字列として残す（当面は削除しない）。

---

## sessions

### 役割
ログインセッション（Laravel標準）。

### 主キー
- id（string）

### 主要カラム
- user_id
- payload 等

### 外部キー（FK）
- user_id -> users.id

## registered_balls

### 外部キー（自動反映：refs_missing.md）
- registered_balls.approved_ball_id -> approved_balls.id
- registered_balls.pro_bowler_id -> pro_bowlers.id

------

## pro_dsp

### 役割
旧システム由来のプロボウラー詳細（ProDsp）情報を保持するテーブル。
旧互換として `license_no`（文字列）を保持しつつ、正規の結線軸は `pro_bowler_id`（nullable）で `pro_bowlers` に寄せる。

### 主キー
- id (bigint) ※（列詳細は columns_by_table.md を参照）

### 主要カラム（抜粋）
- license_no（旧互換：文字列）
- pro_bowler_id（pro_bowlers.id：nullable）

### 外部キー（FK）
- pro_dsp.pro_bowler_id -> pro_bowlers.id（ON DELETE SET NULL）

---
