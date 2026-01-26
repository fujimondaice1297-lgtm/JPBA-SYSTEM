# Data Dictionary（JPBA-system）

このファイルは「テーブルの説明書」です。
新しいテーブルを作ったら、ここに追記します。

## Tables（テーブル一覧）

### Laravel/システム系（基盤）
- cache
- cache_locks
- failed_jobs
- job_batches
- jobs
- migrations
- password_reset_tokens
- sessions
- users

### マスタ/共通
- area
- districts
- informations
- kaiin_status
- license
- organization_masters
- place
- sexes
- sponsors
- stage_settings
- venues

### ボール関連
- approved_ball_pro_bowler
- approved_balls
- ball_info
- registered_balls
- used_balls

### グループ/連絡系
- group_mail_recipients
- group_mailouts
- group_members
- groups
- pro_group

### 殿堂/紹介・メディア系
- hof_inductions
- hof_photos
- match_videos
- media_publications

### プロボウラー関連
- annual_dues
- instructors
- pro_bowler_biographies
- pro_bowler_instructor_info
- pro_bowler_links
- pro_bowler_profiles
- pro_bowler_sponsors
- pro_bowler_titles
- pro_bowler_trainings
- pro_bowlers
- pro_dsp
- trainings

### 実績/記録系（※マスタではない）
- record_types

### プロテスト関連
- pro_test
- pro_test_attachment
- pro_test_category
- pro_test_comment
- pro_test_result_status
- pro_test_schedule
- pro_test_score
- pro_test_score_summary
- pro_test_status_log
- pro_test_venue

### 大会関連
- calendar_days
- calendar_events
- distribution_patterns
- game_scores
- point_distributions
- prize_distributions
- tournament_awards
- tournament_entries
- tournament_entry_balls
- tournament_files
- tournament_organizations
- tournament_participants
- tournament_points
- tournament_results
- tournaments
- tournamentscore

---

## pro_bowlers

### 役割
プロボウラーの中心テーブル。個人基本情報・連絡先・住所・公開用情報・資格・SNS・スポンサー・マイページ関連などが1テーブルに集約されている（現状は “全部入り” 構造）。

### 主キー
- id (bigint)

### このテーブルを参照しているFK（DB上で確認できたもの）
- users.pro_bowler_id -> pro_bowlers.id
- annual_dues.pro_bowler_id -> pro_bowlers.id
- group_mail_recipients.pro_bowler_id -> pro_bowlers.id
- group_members.pro_bowler_id -> pro_bowlers.id
- tournament_entries.pro_bowler_id -> pro_bowlers.id

### カラムの分類（迷子防止のための“地図”）
#### 1) ID・所属・状態
- license_no, kibetsu, membership_type, district_id
- acquire_date, license_issue_date, pro_entry_year
- is_active, is_visible
- has_title, is_district_leader, has_sports_coach_license, sports_coach_name

#### 2) 氏名・基本属性
- name_kanji, name_kana, sex
- birthdate, birthplace, blood_type
- height_cm, weight_kg, dominant_arm

#### 3) 連絡先
- phone_home, phone_work, phone_mobile, fax_number, email

#### 4) 住所・勤務先
- home_zip, home_address
- work_zip, work_address
- organization_name, organization_url, organization_zip, organization_addr1, organization_addr2

#### 5) 公開用住所・郵送先
- public_zip, public_addr1, public_addr2, public_addr_same_as_org
- mailing_preference, mailing_zip, mailing_addr1, mailing_addr2, mailing_addr_same_as_org

#### 6) 画像・QR
- image_path, public_image_path, qr_code_path

#### 7) SNS・外部リンク
- facebook, twitter, instagram, rankseeker

#### 8) プロフィール文章・自由記入
- hobby, bowling_history, other_sports_history
- season_goal, coach, selling_point, free_comment
- memo, motto, equipment_contract, coaching_history

#### 9) 資格・コーチ/インストラクター関連
- coach_qualification, jbc_driller_cert, usbc_coach
- a_license_date, a_license_number, permanent_seed_date, hall_of_fame_date
- a_class_status, a_class_year
- b_class_status, b_class_year
- c_class_status, c_class_year
- master_status, master_year
- coach_4_status, coach_4_year
- coach_3_status, coach_3_year
- coach_1_status, coach_1_year
- kenkou_status, kenkou_year
- school_license_status, school_license_year

#### 10) 記録・カウント系
- perfect_count, seven_ten_count, eight_hundred_count, award_total_count

#### 11) スポンサー
- sponsor_a, sponsor_a_url
- sponsor_b, sponsor_b_url
- sponsor_c, sponsor_c_url

#### 12) マイページ/認証・公開制御
- login_id, mypage_temp_password, password_change_status
- birthdate_public, birthdate_public_hide_year, birthdate_public_is_private
- height_is_public, weight_is_public, blood_type_is_public

#### 13) その他
- association_role
- created_at, updated_at

### 外部キー（自動反映：refs_missing.md）
- pro_bowlers.district_id -> districts.id

---
## tournaments

### 役割
大会の中心テーブル。大会名・開催日・会場情報（会場名/住所/連絡先）・主催/協賛・配信/告知・エントリー期間・レーン抽選時間・PDF/画像などの公開素材・サイドバー表示用JSONなどを保持する。

### 主キー
- id (bigint)

### 外部キー（DB上で確認できたもの）
- tournaments.venue_id -> venues.id
- game_scores.tournament_id -> tournaments.id
- stage_settings.tournament_id -> tournaments.id
- tournament_entries.tournament_id -> tournaments.id
- tournament_files.tournament_id -> tournaments.id
- tournament_organizations.tournament_id -> tournaments.id

### カラム分類（迷子防止の“地図”）
#### 1) 基本
- name
- start_date, end_date
- year
- created_at, updated_at

#### 2) 会場（入力/表示用）
- venue_id（venues 参照）
- venue_name, venue_address, venue_tel, venue_fax
- extra_venues (json)（追加会場などがあれば）

#### 3) 主催・協賛・関係者
- host
- special_sponsor
- sponsor
- support
- supervisor
- authorized_by

#### 4) 公開情報（配信・告知・リンク）
- broadcast
- streaming
- broadcast_url
- streaming_url
- previous_event
- previous_event_url

#### 5) エントリー・運営スケジュール
- entry_conditions (text)
- materials (text)
- entry_start, entry_end（timestamp）
- inspection_required (boolean)
- shift_codes（character varying）
- shift_draw_open_at, shift_draw_close_at
- lane_draw_open_at, lane_draw_close_at
- lane_from, lane_to

#### 6) 区分・公式設定
- gender（NO: NOT NULL）
- official_type（NO: NOT NULL）
- title_category（NO: NOT NULL）

#### 7) 表彰/賞・観客向け
- prize
- audience
- admission_fee (text)

#### 8) 画像・PDF・表示素材（主に公開/フロント用）
- image_path
- hero_image_path
- title_logo_path
- poster_images (json)
- gallery_items (json)
- award_highlights (json)
- sidebar_schedule (json)
- simple_result_pdfs (json)
- result_cards (json)

---

## tournament_entries

### 役割
大会への「出場エントリー」を表す中心テーブル。プロボウラーと大会をつなぎ、支払い状況・抽選状況・レーン/シフト割当・チェックイン時刻など運営状態を持つ。

### 主キー
- id (bigint)

### 外部キー（DB上で確認できたもの）
- tournament_entries.pro_bowler_id -> pro_bowlers.id
- tournament_entries.tournament_id -> tournaments.id
- tournament_entry_balls.tournament_entry_id -> tournament_entries.id

### 主要カラム
- pro_bowler_id（誰が）
- tournament_id（どの大会に）
- status（状態：文字列）
- is_paid（支払い済み）
- shift_drawn / lane_drawn（抽選済みフラグ）
- shift（シフトコード）
- lane（レーン番号）
- checked_in_at（チェックイン時刻）

---

## tournament_entry_balls

### 役割
「エントリー（tournament_entries）」と「使用ボール（used_balls）」を紐づける中間テーブル。

### 主キー
- id (bigint)

### 外部キー（DB上で確認できたもの）
- tournament_entry_balls.tournament_entry_id -> tournament_entries.id
- tournament_entry_balls.used_ball_id -> used_balls.id

### 注意
- tournament_entry_id / used_ball_id が nullable になっているので、将来的に運用上「必須」にしたいなら NOT NULL + FK を強化する余地あり。

---

## tournament_participants

### 役割
大会の参加者一覧（または参加枠）を保持するテーブル。`pro_bowler_license_no` を持っているので、現状は「ライセンス番号文字列」で参加者を管理している形。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id（どの大会）
- pro_bowler_license_no（参加者のライセンス番号：文字列）

### 注意（設計改善ポイント）
- `pro_bowler_id` ではなく `pro_bowler_license_no` で持っているため、
  - `pro_bowlers` へのFKが貼れない
  - ライセンス番号変更時の整合性が弱い
- 将来的には `pro_bowler_id` に寄せる（または両方持つ）方がDB的に強い。

### 外部キー（自動反映：refs_missing.md）
- tournament_participants.tournament_id -> tournaments.id

---
## tournament_results

### 役割
大会結果（順位・ポイント・トータルピン・アベレージ・賞金など）を保持するテーブル。参加者は `pro_bowler_license_no`（文字列）で識別している。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id（どの大会）
- pro_bowler_license_no（誰の結果か：文字列）
- ranking（順位）
- points（ポイント）
- total_pin（合計ピン）
- games（ゲーム数）
- average（アベレージ）
- prize_money（賞金）
- ranking_year（年度：NOT NULL）
- amateur_name（アマ参加者名：nullable）

### 注意（設計改善ポイント）
- `pro_bowler_license_no` で管理しているため、`pro_bowlers` へのFKが貼れない。
- アマ参加者は `amateur_name` で持てる設計だが、将来は参加者マスタを作るとさらに整理できる。

### 外部キー（自動反映：refs_missing.md）
- tournament_results.tournament_id -> tournaments.id

---
## tournament_points

### 役割
大会内の「順位→ポイント表」（配点表）を持つテーブル。大会ごとにポイント配分が違う場合に対応。

### 主キー
- （現状DB上のPKは未確認/未設定の可能性）
- 推奨：複合キー (tournament_id, rank)

### 主要カラム
- tournament_id（どの大会の配点表か）
- rank（順位：NOT NULL）
- point（ポイント：NOT NULL）

### 注意（設計改善ポイント）
- 行構造的には `(tournament_id, rank)` を複合一意にしたい可能性が高い。

### 外部キー（自動反映：refs_missing.md）
- tournament_points.tournament_id -> tournaments.id

---
## pro_test

### 役割
「プロボウラー試験」の受験者・受験情報を管理する中心テーブル。性別・エリア・ライセンス区分・会場・種別・結果ステータスなどの“参照マスタID”を束ねる。

※あなたの申告どおり、現状は「まだ中身を作成していない（空）」でOK。

### 主キー
- id (bigint)

### 参照しているマスタ（想定：FK制約は未定義）
- sex_id -> sexes.id
- area_id -> area.id
- license_id -> license.id
- place_id -> place.id
- kaiin_status_id -> kaiin_status.id
- test_category_id -> pro_test_category.id
- test_venue_id -> pro_test_venue.id
- test_result_status_id -> pro_test_result_status.id
- record_type_id -> （要確認：現状は参照先未確定）

### 主なカラム
- name（受験者名）
- remarks（備考）
- update_date（更新日時）
- created_by / updated_by（更新者）

### 注意（超重要）
- `record_type_id` は bigint（NOT NULL）なので「何かのID参照」を想定している。
- ただし `record_types` は “個人の実績/履歴” テーブル寄りで、受験者（pro_test）の参照先としては不自然になりやすい。
- ここは後で必ず整理ポイント：
  - A案：`pro_test_record_types` のような「受験種別マスタ」を新設してそこを参照
  - B案：`record_type_id` をやめて `record_type`（文字列）で持つ
  - C案：既存のどこか別テーブルが本来の参照先（命名だけズレている）

---

## pro_test_schedule

### 役割
年度ごとの「プロテスト日程」を管理するテーブル。募集期間・実施期間・会場（venue_id）を持つ。

### 主キー
- id (bigint)

### 主なカラム
- year（年度：NOT NULL）
- schedule_name（名称：NOT NULL）
- start_date / end_date（実施期間）
- application_start / application_end（応募期間）
- venue_id（会場：nullable）
- update_date / created_by / updated_by

### 注意（設計ポイント）
- venue_id は nullable なので、会場未確定の状態にも対応できる。

### 外部キー（自動反映：refs_missing.md）
- pro_test_schedule.venue_id -> venues.id

---
## pro_test_status_log

### 役割
プロテスト受験者（pro_test）に対する「状態遷移ログ」。status_code で状態を持ち、changed_at にいつ変わったかを記録する。

### 主キー
- id (bigint)

### 外部キー（想定：※DB上のFKは未確認）

- pro_test_status_log.pro_test_id -> pro_test.id

- pro_test_id（pro_test.id を参照する想定）

### 主なカラム
- pro_test_id（対象）
- status_code（状態コード：NOT NULL）
- memo（メモ）
- changed_at（変更日時）
- updated_by（更新者）

---

## pro_test_score

### 役割
プロテスト受験者（pro_test）の「ゲームごとのスコア」を管理する。

### 主キー
- id (bigint)

### 外部キー（想定：※DB上のFKは未確認）

- pro_test_score.pro_test_id -> pro_test.id

- pro_test_id（pro_test.id を参照する想定）

### 主なカラム
- pro_test_id（誰のスコアか）
- game_no（何ゲーム目か：NOT NULL）
- score（スコア：NOT NULL）
- update_date / created_by / updated_by

---

## pro_test_score_summary

### 役割
プロテスト受験者（pro_test）のスコアを集計したサマリ。合計・平均・合否を保持する。

### 主キー
- id (bigint)

### 外部キー（想定：※DB上のFKは未確認）

- pro_test_score_summary.pro_test_id -> pro_test.id

- pro_test_id（pro_test.id を参照する想定）

### 主なカラム
- total_score（合計：nullable）
- average_score（平均：numeric：nullable）
- passed_flag（合否：NOT NULL）
- remarks（備考）
- update_date / created_by / updated_by

---

## pro_test_result_status

### 役割
プロテスト結果のステータスマスタ（合格/不合格/保留など）。`pro_test.test_result_status_id` が参照する前提。

### 主キー
- id (bigint)

### 主なカラム
- status（ステータス名：NOT NULL）
- update_date / created_by / updated_by

---

## sexes

### 役割
性別マスタ。`pro_test.sex_id` が参照する想定。
（`pro_bowlers.sex` も内部的にこのマスタ相当を想定している可能性あり）

### 主キー
- id (bigint)

### 主なカラム
- label（表示名：NOT NULL）
- update_date / created_by / updated_by

---

## area

### 役割
エリア（地区/地域）マスタ。`pro_test.area_id` などから参照される想定。

### 主キー
- id (bigint)

### 主なカラム
- name（名称：NOT NULL）
- update_date / created_by / updated_by

---

## license

### 役割
ライセンス種別マスタ（例：会員種別・受験区分など）。`pro_test.license_id` などから参照される想定。

### 主キー
- id (bigint)

### 主なカラム
- name（名称：NOT NULL）
- update_date / created_by / updated_by

---

## kaiin_status

### 役割
会員ステータスマスタ（例：現役/退会/休会など想定）。`pro_test.kaiin_status_id` などから参照される想定。

### 主キー
- id (bigint)

### 主なカラム
- name（名称：NOT NULL）
- reg_date（登録日時）
- del_flg（削除/無効フラグ：NOT NULL）
- update_date / created_by / updated_by

---

## pro_test_category

### 役割
プロテストのカテゴリ/種別マスタ。`pro_test.test_category_id` が参照する想定。

### 主キー
- id (bigint)

### 主なカラム
- name（名称：NOT NULL）
- update_date / created_by / updated_by

---

## pro_test_venue

### 役割
プロテスト会場マスタ。`pro_test.test_venue_id` が参照する想定。

### 主キー
- id (bigint)

### 主なカラム
- name（会場名：NOT NULL）
- address（住所）
- phone（電話）
- update_date / created_by / updated_by

---

## place

### 役割
場所マスタ（出身地/在住地/会場とは別の「場所」用途がありそう）。`pro_test.place_id` が参照する想定。

### 主キー
- id (bigint)

### 主なカラム
- name（名称：NOT NULL）
- address（住所：nullable）
- phone（電話：nullable）
- update_date / created_by / updated_by

---

## record_types

### 役割
※名前は “マスタっぽい” が、実体は「個人の実績/履歴」テーブル。
`pro_bowler_id` を持ち、大会名・ゲーム数・フレーム数・認定番号・授与日などを記録する。

### 主キー
- id (bigint)

### 参照（想定）
- pro_bowler_id -> pro_bowlers.id（FKは未設定/未確認の可能性あり）

### 主なカラム
- record_type（実績種別：文字列）
- tournament_name（大会名）
- game_numbers / frame_number（回数・フレーム）
- awarded_on（授与日）
- certification_number（認定番号）
- created_at / updated_at

### 注意（整合性チェックポイント）
- pro_test.record_type_id（bigint）と、record_types（履歴テーブル）は役割が一致していない可能性が高い。
  後で「pro_testが本当に参照したいテーブル」はどれかを確認して整理する。
### 外部キー（自動反映：refs_missing.md）
- record_types.pro_bowler_id -> pro_bowlers.id

---
## approved_ball_pro_bowler

### 外部キー（自動反映：refs_missing.md）
- approved_ball_pro_bowler.approved_ball_id -> approved_balls.id
---

## group_mailouts

### 外部キー（自動反映：refs_missing.md）
- group_mailouts.group_id -> groups.id
- group_mailouts.sender_user_id -> users.id
---

## group_members

### 外部キー（自動反映：refs_missing.md）
- group_members.group_id -> groups.id
---

## instructors

### 外部キー（自動反映：refs_missing.md）
- instructors.pro_bowler_id -> pro_bowlers.id
- instructors.district_id -> districts.id
---

## media_publications

### 外部キー（自動反映：refs_missing.md）
- media_publications.tournament_id -> tournaments.id
---

## point_distributions

### 外部キー（自動反映：refs_missing.md）
- point_distributions.tournament_id -> tournaments.id
- point_distributions.pattern_id -> distribution_patterns.id
- prize_distributions.pattern_id -> distribution_patterns.id
---

## prize_distributions

### 外部キー（自動反映：refs_missing.md）
- prize_distributions.tournament_id -> tournaments.id
---

## pro_bowler_biographies

### 外部キー（自動反映：refs_missing.md）
- pro_bowler_biographies.pro_bowler_id -> pro_bowlers.id
---

## pro_bowler_instructor_info

### 外部キー（自動反映：refs_missing.md）
- pro_bowler_instructor_info.pro_bowler_id -> pro_bowlers.id
---

## pro_bowler_links

### 外部キー（自動反映：refs_missing.md）
- pro_bowler_links.pro_bowler_id -> pro_bowlers.id
---

## pro_bowler_profiles

### 外部キー（自動反映：refs_missing.md）
- pro_bowler_profiles.pro_bowler_id -> pro_bowlers.id
---

## pro_bowler_sponsors

### 外部キー（自動反映：refs_missing.md）
- pro_bowler_sponsors.pro_bowler_id -> pro_bowlers.id
---

## pro_bowler_titles

### 外部キー（自動反映：refs_missing.md）
- pro_bowler_titles.pro_bowler_id -> pro_bowlers.id
- pro_bowler_titles.tournament_id -> tournaments.id
---

## pro_bowler_trainings

### 外部キー（自動反映：refs_missing.md）
- pro_bowler_trainings.pro_bowler_id -> pro_bowlers.id
- pro_bowler_trainings.training_id -> trainings.id
---

## pro_test_attachment

### 外部キー（自動反映：refs_missing.md）
- pro_test_attachment.pro_test_id -> pro_test.id
---

## pro_test_comment

### 外部キー（自動反映：refs_missing.md）
- pro_test_comment.pro_test_id -> pro_test.id
---

## registered_balls

### 外部キー（自動反映：refs_missing.md）
- registered_balls.approved_ball_id -> approved_balls.id
---

## sessions

### 外部キー（自動反映：refs_missing.md）
- sessions.user_id -> users.id
---

## tournament_awards

### 外部キー（自動反映：refs_missing.md）
- tournament_awards.tournament_id -> tournaments.id
---

## used_balls

### 外部キー（自動反映：refs_missing.md）
- used_balls.pro_bowler_id -> pro_bowlers.id
- used_balls.approved_ball_id -> approved_balls.id

## group_mail_recipients
### 外部キー（DB上で確認できたもの）
- group_mail_recipients.mailout_id -> group_mailouts.id

## hof_photos
### 外部キー（DB上で確認できたもの）
- hof_photos.hof_id -> hof_inductions.id

## hof_inductions
### 外部キー（DB上で確認できたもの）
- hof_inductions.pro_id -> pro_bowlers.id

