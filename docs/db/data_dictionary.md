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
  - [area](#area)
  - [distribution_patterns](#distribution_patterns)
  - [districts](#districts)
  - [game_scores](#game_scores)
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
  - [tournament_results](#tournament_results)
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

### 主キー
- id (bigint)

### 主要カラム（DB実体）
- tournament_id（どの大会）
- stage（ステージ：入力元の値を保持）
- shift（シフト：入力元の値を保持）
- gender（性別：入力元の値を保持）
- license_number（ライセンス番号：文字列）
- name（氏名：入力元の値を保持）
- entry_number（エントリー番号：入力元の値を保持）
- game_number（ゲーム番号）
- score（スコア）
- pro_bowler_id（pro_bowlers.id：nullable。埋められる行はIDで紐付ける）

### 外部キー（FK）
- tournament_id -> tournaments.id
- pro_bowler_id -> pro_bowlers.id（ON DELETE SET NULL）

### 注意（運用方針）
- `license_number` / `name` はスナップショット（当時の表記）として残す。
- アプリ側は将来的に `pro_bowler_id` 優先で参照する。

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
ポイント配分（順位→ポイント）を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id（どの大会）
- pattern_id（配分パターン）
- rank（順位）
- point（ポイント）

### 外部キー（FK）
- tournament_id -> tournaments.id
- pattern_id -> distribution_patterns.id

---

## prize_distributions

### 役割
賞金配分（順位→賞金）を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id（どの大会）
- pattern_id（配分パターン）
- rank（順位）
- prize_money（賞金額）

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
大会マスタ（大会の基本情報）を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム（抜粋）
- name（大会名）
- start_date / end_date
- venue_id（会場：nullable）
- （他、多数）

### 外部キー（FK）
- venue_id -> venues.id

---

## tournament_entries

### 役割
大会エントリー（申込）を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id（どの大会）
- pro_bowler_id（誰がエントリー）
- status（申込状態：nullable）

### 外部キー（FK）
- tournament_id -> tournaments.id
- pro_bowler_id -> pro_bowlers.id

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

### 主キー
- id (bigint)

### 主要カラム
- tournament_id（どの大会）
- pro_bowler_license_no（参加者のライセンス番号：文字列：既存互換のため保持）
- pro_bowler_id（参加者の pro_bowlers.id：nullable。埋められる行はIDで紐付ける）

### 注意（運用方針）
- 既存データは `pro_bowler_license_no` のままでも壊れない。
- 可能な行は `pro_bowler_id` を埋め、アプリ側は将来的に `pro_bowler_id` 優先で参照する。

### 外部キー（DB上で確認できたもの）
- tournament_participants.tournament_id -> tournaments.id
- tournament_participants.pro_bowler_id -> pro_bowlers.id（ON DELETE SET NULL）

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
- points（ポイント）
- total_pin（合計ピン）
- games（ゲーム数）
- average（アベレージ）
- prize_money（賞金）
- ranking_year（年度：NOT NULL）
- amateur_name（アマ参加者名：nullable）

### 注意（運用方針）
- 既存データは `pro_bowler_license_no` のままでも壊れない。
- アマ参加者は `amateur_name`（および `pro_bowler_id` NULL）で保持する。

### 外部キー（DB上で確認できたもの）
- tournament_results.tournament_id -> tournaments.id
- tournament_results.pro_bowler_id -> pro_bowlers.id（ON DELETE SET NULL）

---

## tournament_points

### 役割
大会の順位に対するポイント付与（ポイント表）を保持するテーブル。

### 主キー
- （実装は複合主キー相当：tournament_id + rank）

### 主要カラム
- tournament_id
- rank
- point

### 外部キー（FK）
- tournament_id -> tournaments.id

---

## tournament_awards

### 役割
大会の表彰（賞）情報を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id
- award_name
- award_rank（nullable）

### 外部キー（FK）
- tournament_id -> tournaments.id

---

## tournament_files

### 役割
大会に紐づく添付ファイルを保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id
- file_path
- kind（pdf/image 等：nullable）

### 外部キー（FK）
- tournament_id -> tournaments.id

---

## tournament_organizations

### 役割
主催/共催/後援などの大会組織情報を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id
- organization_name
- role（host/cohost/support 等：nullable）

### 外部キー（FK）
- tournament_id -> tournaments.id

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