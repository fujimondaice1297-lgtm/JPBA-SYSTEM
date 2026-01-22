# Columns by table (generated)

- Source: `docs/db/columns_public.csv`
- Generated: 2026-01-22 07:35:48

> ⚠️ このファイルは自動生成です。手で編集しないでください。

## annual_dues (7 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_bowler_id | bigint | NO |
| 3 | year | smallint | NO |
| 4 | paid_at | date | YES |
| 5 | note | character varying | YES |
| 6 | created_at | timestamp without time zone | YES |
| 7 | updated_at | timestamp without time zone | YES |

## approved_ball_pro_bowler (6 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_bowler_license_no | character varying | NO |
| 3 | approved_ball_id | bigint | NO |
| 4 | year | integer | YES |
| 5 | created_at | timestamp without time zone | YES |
| 6 | updated_at | timestamp without time zone | YES |

## approved_balls (8 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | character varying | NO |
| 3 | name_kana | character varying | YES |
| 4 | manufacturer | character varying | NO |
| 6 | approved | boolean | NO |
| 7 | created_at | timestamp without time zone | YES |
| 8 | updated_at | timestamp without time zone | YES |
| 9 | release_date | date | YES |

## area (5 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | text | NO |
| 3 | update_date | timestamp without time zone | YES |
| 4 | created_by | character varying | YES |
| 5 | updated_by | character varying | YES |

## ball_info (6 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | brand | character varying | YES |
| 3 | model | character varying | YES |
| 4 | update_date | timestamp without time zone | YES |
| 5 | created_by | character varying | YES |
| 6 | updated_by | character varying | YES |

## cache (3 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | key | character varying | NO |
| 2 | value | text | NO |
| 3 | expiration | integer | NO |

## cache_locks (3 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | key | character varying | NO |
| 2 | owner | character varying | NO |
| 3 | expiration | integer | NO |

## calendar_days (6 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | date | date | NO |
| 2 | holiday_name | character varying | YES |
| 3 | is_holiday | boolean | NO |
| 4 | rokuyou | character varying | YES |
| 5 | created_at | timestamp without time zone | YES |
| 6 | updated_at | timestamp without time zone | YES |

## calendar_events (8 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | title | character varying | NO |
| 3 | start_date | date | NO |
| 4 | end_date | date | NO |
| 5 | venue | character varying | YES |
| 6 | kind | character varying | NO |
| 7 | created_at | timestamp without time zone | YES |
| 8 | updated_at | timestamp without time zone | YES |

## distribution_patterns (5 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | created_at | timestamp without time zone | YES |
| 3 | updated_at | timestamp without time zone | YES |
| 4 | name | character varying | NO |
| 5 | type | character varying | NO |

## districts (5 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | character varying | NO |
| 3 | label | character varying | NO |
| 4 | created_at | timestamp without time zone | YES |
| 5 | updated_at | timestamp without time zone | YES |

## failed_jobs (7 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | uuid | character varying | NO |
| 3 | connection | text | NO |
| 4 | queue | text | NO |
| 5 | payload | text | NO |
| 6 | exception | text | NO |
| 7 | failed_at | timestamp without time zone | NO |

## flash_news (5 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | title | character varying | NO |
| 3 | url | character varying | NO |
| 4 | created_at | timestamp without time zone | YES |
| 5 | updated_at | timestamp without time zone | YES |

## game_scores (12 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | stage | character varying | NO |
| 4 | license_number | character varying | YES |
| 5 | name | character varying | YES |
| 6 | entry_number | character varying | YES |
| 7 | game_number | integer | NO |
| 8 | score | integer | NO |
| 9 | created_at | timestamp without time zone | YES |
| 10 | updated_at | timestamp without time zone | YES |
| 11 | shift | character varying | YES |
| 12 | gender | character varying | YES |

## group_mail_recipients (9 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | mailout_id | bigint | NO |
| 3 | pro_bowler_id | bigint | NO |
| 4 | email | character varying | NO |
| 5 | status | character varying | NO |
| 6 | sent_at | timestamp without time zone | YES |
| 7 | error_message | text | YES |
| 8 | created_at | timestamp without time zone | YES |
| 9 | updated_at | timestamp without time zone | YES |

## group_mailouts (12 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | group_id | bigint | NO |
| 3 | sender_user_id | bigint | NO |
| 4 | subject | character varying | NO |
| 5 | body | text | NO |
| 6 | from_address | character varying | YES |
| 7 | from_name | character varying | YES |
| 8 | status | character varying | NO |
| 9 | sent_count | integer | NO |
| 10 | fail_count | integer | NO |
| 11 | created_at | timestamp without time zone | YES |
| 12 | updated_at | timestamp without time zone | YES |

## group_members (8 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | group_id | bigint | NO |
| 3 | pro_bowler_id | bigint | NO |
| 4 | source | character varying | NO |
| 5 | assigned_at | timestamp without time zone | YES |
| 6 | expires_at | date | YES |
| 7 | created_at | timestamp without time zone | YES |
| 8 | updated_at | timestamp without time zone | YES |

## groups (14 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | key | character varying | NO |
| 3 | name | character varying | NO |
| 4 | type | character varying | NO |
| 5 | rule_json | json | YES |
| 6 | retention | character varying | NO |
| 7 | expires_at | date | YES |
| 8 | show_on_mypage | boolean | NO |
| 9 | created_at | timestamp without time zone | YES |
| 10 | updated_at | timestamp without time zone | YES |
| 11 | preset | character varying | YES |
| 12 | action_mypage | boolean | NO |
| 13 | action_email | boolean | NO |
| 14 | action_postal | boolean | NO |

## hof_inductions (6 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_id | bigint | NO |
| 3 | year | smallint | NO |
| 4 | citation | text | YES |
| 5 | created_at | timestamp with time zone | YES |
| 6 | updated_at | timestamp with time zone | YES |

## hof_photos (7 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | hof_id | bigint | NO |
| 3 | url | text | NO |
| 4 | credit | character varying | YES |
| 5 | sort_order | integer | NO |
| 6 | created_at | timestamp with time zone | YES |
| 7 | updated_at | timestamp with time zone | YES |

## informations (10 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | title | character varying | NO |
| 3 | body | text | NO |
| 4 | is_public | boolean | NO |
| 5 | starts_at | timestamp without time zone | YES |
| 6 | ends_at | timestamp without time zone | YES |
| 7 | audience | character varying | NO |
| 8 | required_training_id | bigint | YES |
| 9 | created_at | timestamp without time zone | YES |
| 10 | updated_at | timestamp without time zone | YES |

## instructors (13 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | license_no | character varying | NO |
| 2 | pro_bowler_id | bigint | YES |
| 3 | name | character varying | NO |
| 4 | name_kana | character varying | YES |
| 5 | sex | boolean | NO |
| 6 | district_id | character varying | YES |
| 7 | instructor_type | character varying | NO |
| 8 | grade | character varying | YES |
| 9 | is_active | boolean | NO |
| 10 | is_visible | boolean | NO |
| 11 | coach_qualification | boolean | NO |
| 12 | created_at | timestamp without time zone | YES |
| 13 | updated_at | timestamp without time zone | YES |

## job_batches (10 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | character varying | NO |
| 2 | name | character varying | NO |
| 3 | total_jobs | integer | NO |
| 4 | pending_jobs | integer | NO |
| 5 | failed_jobs | integer | NO |
| 6 | failed_job_ids | text | NO |
| 7 | options | text | YES |
| 8 | cancelled_at | integer | YES |
| 9 | created_at | integer | NO |
| 10 | finished_at | integer | YES |

## jobs (7 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | queue | character varying | NO |
| 3 | payload | text | NO |
| 4 | attempts | smallint | NO |
| 5 | reserved_at | integer | YES |
| 6 | available_at | integer | NO |
| 7 | created_at | integer | NO |

## kaiin_status (7 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | character varying | NO |
| 3 | reg_date | timestamp without time zone | YES |
| 4 | del_flg | boolean | NO |
| 5 | update_date | timestamp without time zone | YES |
| 6 | created_by | character varying | YES |
| 7 | updated_by | character varying | YES |

## license (5 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | text | NO |
| 3 | update_date | timestamp without time zone | YES |
| 4 | created_by | character varying | YES |
| 5 | updated_by | character varying | YES |

## match_videos (5 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | video_url | text | NO |
| 3 | description | text | YES |
| 4 | created_at | timestamp without time zone | YES |
| 5 | updated_at | timestamp without time zone | YES |

## media_publications (8 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | title | character varying | NO |
| 4 | type | character varying | NO |
| 5 | url | text | NO |
| 6 | published_at | date | YES |
| 7 | created_at | timestamp without time zone | YES |
| 8 | updated_at | timestamp without time zone | YES |

## migrations (3 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | integer | NO |
| 2 | migration | character varying | NO |
| 3 | batch | integer | NO |

## organization_masters (5 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | character varying | NO |
| 3 | url | character varying | YES |
| 4 | created_at | timestamp without time zone | YES |
| 5 | updated_at | timestamp without time zone | YES |

## password_reset_tokens (3 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | email | character varying | NO |
| 2 | token | character varying | NO |
| 3 | created_at | timestamp without time zone | YES |

## place (7 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | character varying | NO |
| 3 | address | character varying | YES |
| 4 | phone | character varying | YES |
| 5 | update_date | timestamp without time zone | YES |
| 6 | created_by | character varying | YES |
| 7 | updated_by | character varying | YES |

## point_distributions (7 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | rank | integer | NO |
| 4 | points | integer | NO |
| 5 | pattern_id | bigint | YES |
| 6 | created_at | timestamp without time zone | YES |
| 7 | updated_at | timestamp without time zone | YES |

## prize_distributions (7 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | rank | integer | NO |
| 4 | amount | integer | NO |
| 5 | pattern_id | bigint | YES |
| 6 | created_at | timestamp without time zone | YES |
| 7 | updated_at | timestamp without time zone | YES |

## pro_bowler_biographies (7 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_bowler_id | bigint | NO |
| 3 | motto | character varying | YES |
| 4 | message | text | YES |
| 5 | notes | text | YES |
| 6 | created_at | timestamp without time zone | YES |
| 7 | updated_at | timestamp without time zone | YES |

## pro_bowler_instructor_info (8 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_bowler_id | bigint | NO |
| 3 | instructor_flag | boolean | NO |
| 4 | lesson_center | character varying | YES |
| 5 | lesson_notes | text | YES |
| 6 | certifications | text | YES |
| 7 | created_at | timestamp without time zone | YES |
| 8 | updated_at | timestamp without time zone | YES |

## pro_bowler_links (9 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_bowler_id | bigint | NO |
| 3 | homepage_url | character varying | YES |
| 4 | twitter_url | character varying | YES |
| 5 | instagram_url | character varying | YES |
| 6 | youtube_url | character varying | YES |
| 7 | facebook_url | character varying | YES |
| 8 | created_at | timestamp without time zone | YES |
| 9 | updated_at | timestamp without time zone | YES |

## pro_bowler_profiles (26 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_bowler_id | bigint | NO |
| 3 | birthdate | date | YES |
| 4 | birthplace | character varying | YES |
| 5 | height_cm | integer | YES |
| 6 | weight_kg | integer | YES |
| 7 | blood_type | character varying | YES |
| 8 | home_zip | character varying | YES |
| 9 | home_address | character varying | YES |
| 10 | phone_home | character varying | YES |
| 11 | work_zip | character varying | YES |
| 12 | work_address | character varying | YES |
| 13 | work_place | character varying | YES |
| 14 | phone_work | character varying | YES |
| 15 | work_place_url | character varying | YES |
| 16 | phone_mobile | character varying | YES |
| 17 | fax_number | character varying | YES |
| 18 | email | character varying | YES |
| 19 | image_path | character varying | YES |
| 20 | public_image_path | character varying | YES |
| 21 | qr_code_path | character varying | YES |
| 22 | mailing_preference | smallint | YES |
| 23 | license_issue_date | date | YES |
| 24 | pro_entry_year | integer | YES |
| 25 | created_at | timestamp without time zone | YES |
| 26 | updated_at | timestamp without time zone | YES |

## pro_bowler_sponsors (8 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_bowler_id | bigint | NO |
| 3 | sponsor_name | character varying | NO |
| 4 | sponsor_note | character varying | YES |
| 5 | start_year | integer | YES |
| 6 | end_year | integer | YES |
| 7 | created_at | timestamp without time zone | YES |
| 8 | updated_at | timestamp without time zone | YES |

## pro_bowler_titles (10 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_bowler_id | bigint | NO |
| 3 | tournament_id | bigint | YES |
| 4 | title_name | character varying | NO |
| 5 | year | smallint | NO |
| 6 | won_date | date | YES |
| 7 | source | character varying | NO |
| 8 | created_at | timestamp without time zone | YES |
| 9 | updated_at | timestamp without time zone | YES |
| 10 | tournament_name | character varying | YES |

## pro_bowler_trainings (9 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_bowler_id | bigint | NO |
| 3 | training_id | bigint | NO |
| 4 | completed_at | date | NO |
| 5 | expires_at | date | NO |
| 6 | proof_path | character varying | YES |
| 7 | notes | text | YES |
| 8 | created_at | timestamp without time zone | YES |
| 9 | updated_at | timestamp without time zone | YES |

## pro_bowlers (111 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | license_no | character varying | NO |
| 3 | name_kanji | text | YES |
| 4 | name_kana | text | YES |
| 5 | sex | smallint | NO |
| 6 | district_id | bigint | YES |
| 7 | acquire_date | date | YES |
| 8 | is_active | boolean | NO |
| 9 | is_visible | boolean | NO |
| 10 | coach_qualification | boolean | NO |
| 11 | created_at | timestamp without time zone | YES |
| 12 | updated_at | timestamp without time zone | YES |
| 13 | kibetsu | smallint | YES |
| 14 | membership_type | character varying | YES |
| 15 | license_issue_date | date | YES |
| 16 | phone_home | character varying | YES |
| 17 | has_title | boolean | NO |
| 18 | is_district_leader | boolean | NO |
| 19 | has_sports_coach_license | boolean | NO |
| 20 | sports_coach_name | character varying | YES |
| 21 | birthdate | date | YES |
| 22 | birthplace | character varying | YES |
| 23 | height_cm | integer | YES |
| 24 | weight_kg | integer | YES |
| 25 | blood_type | character varying | YES |
| 26 | home_zip | character varying | YES |
| 27 | home_address | character varying | YES |
| 28 | work_zip | character varying | YES |
| 29 | work_address | character varying | YES |
| 31 | organization_url | character varying | YES |
| 32 | phone_work | character varying | YES |
| 33 | phone_mobile | character varying | YES |
| 34 | fax_number | character varying | YES |
| 35 | email | character varying | YES |
| 36 | image_path | character varying | YES |
| 37 | public_image_path | character varying | YES |
| 38 | qr_code_path | character varying | YES |
| 39 | mailing_preference | smallint | YES |
| 40 | pro_entry_year | smallint | YES |
| 42 | hobby | character varying | YES |
| 43 | bowling_history | character varying | YES |
| 44 | other_sports_history | text | YES |
| 45 | season_goal | character varying | YES |
| 46 | coach | character varying | YES |
| 47 | selling_point | text | YES |
| 48 | free_comment | text | YES |
| 49 | facebook | character varying | YES |
| 50 | twitter | character varying | YES |
| 51 | instagram | character varying | YES |
| 52 | rankseeker | character varying | YES |
| 53 | jbc_driller_cert | character varying | YES |
| 54 | a_license_date | date | YES |
| 55 | permanent_seed_date | date | YES |
| 56 | hall_of_fame_date | date | YES |
| 57 | birthdate_public | date | YES |
| 58 | memo | text | YES |
| 59 | usbc_coach | character varying | YES |
| 60 | a_class_status | character varying | YES |
| 61 | a_class_year | character varying | YES |
| 62 | b_class_status | character varying | YES |
| 63 | b_class_year | character varying | YES |
| 64 | c_class_status | character varying | YES |
| 65 | c_class_year | character varying | YES |
| 66 | master_status | character varying | YES |
| 67 | master_year | character varying | YES |
| 68 | coach_4_status | character varying | YES |
| 69 | coach_4_year | character varying | YES |
| 70 | coach_3_status | character varying | YES |
| 71 | coach_3_year | character varying | YES |
| 72 | coach_1_status | character varying | YES |
| 73 | coach_1_year | character varying | YES |
| 74 | kenkou_status | character varying | YES |
| 75 | kenkou_year | character varying | YES |
| 76 | school_license_status | character varying | YES |
| 77 | school_license_year | character varying | YES |
| 78 | perfect_count | integer | NO |
| 79 | seven_ten_count | integer | NO |
| 80 | eight_hundred_count | integer | NO |
| 81 | award_total_count | integer | NO |
| 82 | organization_name | character varying | YES |
| 83 | organization_zip | character varying | YES |
| 84 | organization_addr1 | character varying | YES |
| 85 | organization_addr2 | character varying | YES |
| 86 | public_zip | character varying | YES |
| 87 | public_addr1 | character varying | YES |
| 88 | public_addr2 | character varying | YES |
| 89 | public_addr_same_as_org | boolean | YES |
| 90 | mailing_zip | character varying | YES |
| 91 | mailing_addr1 | character varying | YES |
| 92 | mailing_addr2 | character varying | YES |
| 93 | mailing_addr_same_as_org | boolean | YES |
| 94 | password_change_status | smallint | YES |
| 95 | login_id | character varying | YES |
| 96 | mypage_temp_password | character varying | YES |
| 97 | height_is_public | boolean | YES |
| 98 | weight_is_public | boolean | YES |
| 99 | blood_type_is_public | boolean | YES |
| 100 | dominant_arm | character varying | YES |
| 101 | motto | character varying | YES |
| 102 | equipment_contract | character varying | YES |
| 103 | coaching_history | text | YES |
| 104 | sponsor_a | character varying | YES |
| 105 | sponsor_a_url | character varying | YES |
| 106 | sponsor_b | character varying | YES |
| 107 | sponsor_b_url | character varying | YES |
| 108 | sponsor_c | character varying | YES |
| 109 | sponsor_c_url | character varying | YES |
| 110 | association_role | character varying | YES |
| 111 | a_license_number | integer | YES |
| 112 | birthdate_public_hide_year | boolean | NO |
| 113 | birthdate_public_is_private | boolean | NO |

## pro_dsp (6 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | character varying | NO |
| 3 | gender | character varying | YES |
| 4 | license_no | character varying | NO |
| 5 | created_at | timestamp without time zone | YES |
| 6 | updated_at | timestamp without time zone | YES |

## pro_group (5 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | character varying | NO |
| 3 | update_date | timestamp without time zone | YES |
| 4 | created_by | character varying | YES |
| 5 | updated_by | character varying | YES |

## pro_test (15 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | character varying | NO |
| 3 | sex_id | bigint | NO |
| 4 | area_id | bigint | NO |
| 5 | license_id | bigint | NO |
| 6 | place_id | bigint | NO |
| 7 | record_type_id | bigint | NO |
| 8 | kaiin_status_id | bigint | NO |
| 9 | test_category_id | bigint | NO |
| 10 | test_venue_id | bigint | NO |
| 11 | test_result_status_id | bigint | NO |
| 12 | remarks | text | YES |
| 13 | update_date | timestamp without time zone | YES |
| 14 | created_by | character varying | YES |
| 15 | updated_by | character varying | YES |

## pro_test_attachment (9 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_test_id | bigint | NO |
| 3 | file_path | character varying | NO |
| 4 | file_type | character varying | YES |
| 5 | original_file_name | character varying | YES |
| 6 | mime_type | character varying | YES |
| 7 | update_date | timestamp without time zone | YES |
| 8 | created_by | character varying | YES |
| 9 | updated_by | character varying | YES |

## pro_test_category (5 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | character varying | NO |
| 3 | update_date | timestamp without time zone | YES |
| 4 | created_by | character varying | YES |
| 5 | updated_by | character varying | YES |

## pro_test_comment (8 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_test_id | bigint | NO |
| 3 | comment | text | NO |
| 4 | posted_by | character varying | YES |
| 5 | posted_at | timestamp without time zone | YES |
| 6 | update_date | timestamp without time zone | YES |
| 7 | created_by | character varying | YES |
| 8 | updated_by | character varying | YES |

## pro_test_result_status (5 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | status | character varying | NO |
| 3 | update_date | timestamp without time zone | YES |
| 4 | created_by | character varying | YES |
| 5 | updated_by | character varying | YES |

## pro_test_schedule (11 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | year | integer | NO |
| 3 | schedule_name | character varying | NO |
| 4 | start_date | date | YES |
| 5 | end_date | date | YES |
| 6 | application_start | date | YES |
| 7 | application_end | date | YES |
| 8 | venue_id | bigint | YES |
| 9 | update_date | timestamp without time zone | YES |
| 10 | created_by | character varying | YES |
| 11 | updated_by | character varying | YES |

## pro_test_score (7 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_test_id | bigint | NO |
| 3 | game_no | integer | NO |
| 4 | score | integer | NO |
| 5 | update_date | timestamp without time zone | YES |
| 6 | created_by | character varying | YES |
| 7 | updated_by | character varying | YES |

## pro_test_score_summary (9 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_test_id | bigint | NO |
| 3 | total_score | integer | YES |
| 4 | average_score | numeric | YES |
| 5 | passed_flag | boolean | NO |
| 6 | remarks | text | YES |
| 7 | update_date | timestamp without time zone | YES |
| 8 | created_by | character varying | YES |
| 9 | updated_by | character varying | YES |

## pro_test_status_log (6 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_test_id | bigint | NO |
| 3 | status_code | character varying | NO |
| 4 | memo | text | YES |
| 5 | changed_at | timestamp without time zone | YES |
| 6 | updated_by | character varying | YES |

## pro_test_venue (7 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | character varying | NO |
| 3 | address | character varying | YES |
| 4 | phone | character varying | YES |
| 5 | update_date | timestamp without time zone | YES |
| 6 | created_by | character varying | YES |
| 7 | updated_by | character varying | YES |

## record_types (10 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_bowler_id | bigint | NO |
| 3 | record_type | character varying | NO |
| 4 | tournament_name | character varying | NO |
| 5 | game_numbers | character varying | NO |
| 6 | frame_number | character varying | YES |
| 7 | awarded_on | date | NO |
| 8 | certification_number | character varying | NO |
| 9 | created_at | timestamp without time zone | YES |
| 10 | updated_at | timestamp without time zone | YES |

## registered_balls (9 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | license_no | character varying | NO |
| 3 | approved_ball_id | bigint | NO |
| 4 | serial_number | character varying | NO |
| 5 | registered_at | date | NO |
| 8 | created_at | timestamp without time zone | YES |
| 9 | updated_at | timestamp without time zone | YES |
| 10 | expires_at | date | YES |
| 11 | inspection_number | character varying | YES |

## sessions (6 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | character varying | NO |
| 2 | user_id | bigint | YES |
| 3 | ip_address | character varying | YES |
| 4 | user_agent | text | YES |
| 5 | payload | text | NO |
| 6 | last_activity | integer | NO |

## sexes (5 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | label | text | NO |
| 3 | update_date | timestamp without time zone | YES |
| 4 | created_by | character varying | YES |
| 5 | updated_by | character varying | YES |

## sponsors (7 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | character varying | NO |
| 3 | logo_path | character varying | YES |
| 4 | website | character varying | YES |
| 5 | description | text | YES |
| 6 | created_at | timestamp without time zone | YES |
| 7 | updated_at | timestamp without time zone | YES |

## stage_settings (7 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | stage | character varying | NO |
| 4 | total_games | integer | YES |
| 5 | created_at | timestamp without time zone | YES |
| 6 | updated_at | timestamp without time zone | YES |
| 7 | enabled | boolean | NO |

## tournament_awards (6 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | rank | integer | NO |
| 4 | prize_money | integer | NO |
| 5 | created_at | timestamp without time zone | YES |
| 6 | updated_at | timestamp without time zone | YES |

## tournament_entries (12 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_bowler_id | bigint | NO |
| 3 | tournament_id | bigint | NO |
| 4 | status | character varying | NO |
| 5 | is_paid | boolean | NO |
| 6 | shift_drawn | boolean | NO |
| 7 | lane_drawn | boolean | NO |
| 8 | created_at | timestamp without time zone | YES |
| 9 | updated_at | timestamp without time zone | YES |
| 10 | shift | character varying | YES |
| 11 | lane | smallint | YES |
| 12 | checked_in_at | timestamp without time zone | YES |

## tournament_entry_balls (5 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_entry_id | bigint | YES |
| 3 | used_ball_id | bigint | YES |
| 4 | created_at | timestamp without time zone | YES |
| 5 | updated_at | timestamp without time zone | YES |

## tournament_files (7 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | type | character varying | NO |
| 4 | title | character varying | YES |
| 5 | file_path | character varying | NO |
| 6 | visibility | character varying | NO |
| 7 | sort_order | integer | NO |

## tournament_organizations (6 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | category | character varying | NO |
| 4 | name | character varying | NO |
| 5 | url | character varying | YES |
| 6 | sort_order | integer | NO |

## tournament_participants (5 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | pro_bowler_license_no | character varying | NO |
| 4 | created_at | timestamp without time zone | YES |
| 5 | updated_at | timestamp without time zone | YES |

## tournament_points (5 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | tournament_id | bigint | NO |
| 2 | rank | integer | NO |
| 3 | point | integer | NO |
| 4 | created_at | timestamp without time zone | YES |
| 5 | updated_at | timestamp without time zone | YES |

## tournament_results (13 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_bowler_license_no | character varying | NO |
| 3 | tournament_id | bigint | NO |
| 4 | ranking | integer | YES |
| 5 | points | integer | YES |
| 6 | total_pin | integer | YES |
| 7 | games | integer | YES |
| 8 | average | numeric | YES |
| 9 | prize_money | integer | YES |
| 10 | ranking_year | integer | NO |
| 11 | created_at | timestamp without time zone | YES |
| 12 | updated_at | timestamp without time zone | YES |
| 13 | amateur_name | character varying | YES |

## tournaments (53 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | character varying | NO |
| 3 | start_date | date | YES |
| 4 | end_date | date | YES |
| 5 | venue_name | character varying | YES |
| 6 | venue_address | character varying | YES |
| 7 | venue_tel | character varying | YES |
| 8 | venue_fax | character varying | YES |
| 9 | host | character varying | YES |
| 10 | special_sponsor | character varying | YES |
| 11 | support | character varying | YES |
| 12 | sponsor | character varying | YES |
| 13 | supervisor | character varying | YES |
| 14 | authorized_by | character varying | YES |
| 15 | broadcast | character varying | YES |
| 16 | streaming | character varying | YES |
| 17 | prize | character varying | YES |
| 18 | audience | character varying | YES |
| 19 | entry_conditions | text | YES |
| 20 | materials | text | YES |
| 21 | previous_event | character varying | YES |
| 22 | image_path | character varying | YES |
| 23 | created_at | timestamp without time zone | YES |
| 24 | updated_at | timestamp without time zone | YES |
| 25 | shift_draw_open_at | timestamp without time zone | YES |
| 26 | shift_draw_close_at | timestamp without time zone | YES |
| 27 | lane_draw_open_at | timestamp without time zone | YES |
| 28 | lane_draw_close_at | timestamp without time zone | YES |
| 29 | lane_from | smallint | YES |
| 30 | lane_to | smallint | YES |
| 31 | shift_codes | character varying | YES |
| 32 | year | integer | YES |
| 33 | gender | character varying | NO |
| 34 | official_type | character varying | NO |
| 35 | entry_start | timestamp without time zone | YES |
| 36 | entry_end | timestamp without time zone | YES |
| 37 | inspection_required | boolean | NO |
| 38 | title_category | character varying | NO |
| 39 | venue_id | bigint | YES |
| 40 | broadcast_url | character varying | YES |
| 41 | streaming_url | character varying | YES |
| 42 | previous_event_url | character varying | YES |
| 43 | spectator_policy | character varying | YES |
| 44 | admission_fee | text | YES |
| 45 | hero_image_path | character varying | YES |
| 46 | poster_images | json | YES |
| 47 | extra_venues | json | YES |
| 48 | sidebar_schedule | json | YES |
| 49 | award_highlights | json | YES |
| 50 | gallery_items | json | YES |
| 51 | simple_result_pdfs | json | YES |
| 52 | title_logo_path | character varying | YES |
| 53 | result_cards | json | YES |

## tournamentscore (4 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | character varying | NO |
| 3 | created_at | timestamp without time zone | YES |
| 4 | updated_at | timestamp without time zone | YES |

## trainings (7 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | code | character varying | NO |
| 3 | name | character varying | NO |
| 4 | valid_for_months | integer | NO |
| 5 | mandatory | boolean | NO |
| 6 | created_at | timestamp without time zone | YES |
| 7 | updated_at | timestamp without time zone | YES |

## used_balls (9 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_bowler_id | bigint | NO |
| 3 | approved_ball_id | bigint | NO |
| 4 | serial_number | character varying | NO |
| 5 | inspection_number | character varying | YES |
| 6 | registered_at | date | NO |
| 7 | expires_at | date | YES |
| 8 | created_at | timestamp without time zone | YES |
| 9 | updated_at | timestamp without time zone | YES |

## users (13 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | character varying | NO |
| 3 | email | character varying | NO |
| 4 | email_verified_at | timestamp without time zone | YES |
| 5 | password | character varying | NO |
| 6 | remember_token | character varying | YES |
| 7 | created_at | timestamp without time zone | YES |
| 8 | updated_at | timestamp without time zone | YES |
| 9 | role | character varying | NO |
| 10 | is_admin | boolean | NO |
| 11 | pro_bowler_license_no | character varying | YES |
| 12 | pro_bowler_id | bigint | YES |
| 13 | license_no | character varying | YES |

## venues (12 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | character varying | NO |
| 3 | address | character varying | YES |
| 4 | postal_code | character varying | YES |
| 5 | city | character varying | YES |
| 6 | prefecture | character varying | YES |
| 7 | created_at | timestamp without time zone | YES |
| 8 | updated_at | timestamp without time zone | YES |
| 9 | tel | character varying | YES |
| 10 | fax | character varying | YES |
| 11 | website_url | character varying | YES |
| 12 | note | text | YES |
