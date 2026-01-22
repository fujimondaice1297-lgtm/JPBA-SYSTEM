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
- record_types
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
