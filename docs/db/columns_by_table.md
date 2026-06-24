# Columns by table (generated)

- Source: `docs/db/columns_public.csv`
- Generated: 2026-06-24 17:20:25

> ⚠️ このファイルは自動生成です。手で編集しないでください。

## amateur_bowlers (12 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | character varying | NO |
| 3 | name_kana | character varying | YES |
| 4 | gender | character varying | YES |
| 5 | dominant_arm | character varying | YES |
| 6 | affiliation_name | character varying | YES |
| 7 | equipment_contract | character varying | YES |
| 8 | note | text | YES |
| 9 | is_active | boolean | NO |
| 10 | created_at | timestamp without time zone | YES |
| 11 | updated_at | timestamp without time zone | YES |
| 12 | amateur_no | character varying | NO |

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

## approved_ball_pro_bowler (7 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | pro_bowler_license_no | character varying | NO |
| 3 | approved_ball_id | bigint | NO |
| 4 | year | integer | YES |
| 5 | created_at | timestamp without time zone | YES |
| 6 | updated_at | timestamp without time zone | YES |
| 7 | pro_bowler_id | bigint | YES |

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

## game_scores (14 columns)

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
| 13 | pro_bowler_id | bigint | YES |
| 14 | tournament_participant_id | bigint | YES |

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

## information_files (9 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | information_id | bigint | NO |
| 3 | type | character varying | NO |
| 4 | title | character varying | YES |
| 5 | file_path | character varying | NO |
| 6 | visibility | character varying | NO |
| 7 | sort_order | integer | NO |
| 8 | created_at | timestamp without time zone | YES |
| 9 | updated_at | timestamp without time zone | YES |

## informations (12 columns)

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
| 11 | category | character varying | NO |
| 12 | published_at | timestamp without time zone | YES |

## instructor_registry (29 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | source_type | character varying | NO |
| 3 | source_key | character varying | NO |
| 4 | legacy_instructor_license_no | character varying | YES |
| 5 | pro_bowler_id | bigint | YES |
| 6 | license_no | character varying | YES |
| 7 | cert_no | character varying | YES |
| 8 | name | character varying | NO |
| 9 | name_kana | character varying | YES |
| 10 | sex | boolean | YES |
| 11 | district_id | bigint | YES |
| 12 | instructor_category | character varying | NO |
| 13 | grade | character varying | YES |
| 14 | coach_qualification | boolean | NO |
| 15 | is_active | boolean | NO |
| 16 | is_visible | boolean | NO |
| 17 | last_synced_at | timestamp without time zone | YES |
| 18 | notes | text | YES |
| 19 | created_at | timestamp without time zone | YES |
| 20 | updated_at | timestamp without time zone | YES |
| 21 | source_registered_at | timestamp without time zone | YES |
| 22 | is_current | boolean | NO |
| 23 | superseded_at | timestamp without time zone | YES |
| 24 | supersede_reason | character varying | YES |
| 25 | renewal_year | smallint | YES |
| 26 | renewal_due_on | date | YES |
| 27 | renewal_status | character varying | YES |
| 28 | renewed_at | date | YES |
| 29 | renewal_note | text | YES |

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

## kaiin_status (8 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | character varying | NO |
| 3 | reg_date | timestamp without time zone | YES |
| 4 | del_flg | boolean | NO |
| 5 | update_date | timestamp without time zone | YES |
| 6 | created_by | character varying | YES |
| 7 | updated_by | character varying | YES |
| 8 | is_retired | boolean | NO |

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

## pro_bowler_ranking_rows (18 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | ranking_snapshot_id | bigint | NO |
| 3 | ranking_rank | integer | NO |
| 4 | pro_bowler_id | bigint | YES |
| 5 | license_no | character varying | YES |
| 6 | name_kanji | character varying | YES |
| 7 | name_kana | character varying | YES |
| 8 | kibetsu | smallint | YES |
| 9 | organization_name | text | YES |
| 10 | equipment_contract | text | YES |
| 11 | points | numeric | YES |
| 12 | games | integer | YES |
| 13 | total_pin | integer | YES |
| 14 | average | numeric | YES |
| 15 | prize_money | bigint | YES |
| 16 | sort_order | integer | YES |
| 17 | created_at | timestamp without time zone | YES |
| 18 | updated_at | timestamp without time zone | YES |

## pro_bowler_ranking_snapshots (11 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | ranking_year | integer | NO |
| 3 | gender | character varying | NO |
| 4 | ranking_type | character varying | NO |
| 5 | ranking_scope | character varying | NO |
| 6 | as_of_date | date | YES |
| 7 | is_final | boolean | NO |
| 8 | source_url | text | YES |
| 9 | notes | text | YES |
| 10 | created_at | timestamp without time zone | YES |
| 11 | updated_at | timestamp without time zone | YES |

## pro_bowler_seed_list_players (15 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | seed_list_id | bigint | NO |
| 3 | pro_bowler_id | bigint | YES |
| 4 | license_no | character varying | YES |
| 5 | seed_category | character varying | NO |
| 6 | seed_rank | integer | YES |
| 7 | ranking_snapshot_id | bigint | YES |
| 8 | ranking_rank | integer | YES |
| 9 | source_tournament_id | bigint | YES |
| 10 | pro_bowler_title_id | bigint | YES |
| 11 | priority_order | integer | YES |
| 12 | note | text | YES |
| 13 | is_active | boolean | NO |
| 14 | created_at | timestamp without time zone | YES |
| 15 | updated_at | timestamp without time zone | YES |

## pro_bowler_seed_lists (13 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | seed_year | integer | NO |
| 3 | gender | character varying | NO |
| 4 | seed_list_type | character varying | NO |
| 5 | source_ranking_snapshot_id | bigint | YES |
| 6 | base_ranking_year | integer | YES |
| 7 | base_top_count | integer | NO |
| 8 | as_of_date | date | YES |
| 9 | is_active | boolean | NO |
| 10 | source_url | text | YES |
| 11 | notes | text | YES |
| 12 | created_at | timestamp without time zone | YES |
| 13 | updated_at | timestamp without time zone | YES |

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

## pro_bowlers (115 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | license_no | character varying | NO |
| 3 | name_kanji | text | YES |
| 4 | name_kana | text | YES |
| 5 | sex | bigint | NO |
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
| 78 | license_no_num | integer | YES |
| 79 | titles_count | integer | NO |
| 80 | perfect_count | integer | NO |
| 81 | seven_ten_count | integer | NO |
| 82 | eight_hundred_count | integer | NO |
| 83 | award_total_count | integer | NO |
| 84 | organization_name | character varying | YES |
| 85 | organization_zip | character varying | YES |
| 86 | organization_addr1 | character varying | YES |
| 87 | organization_addr2 | character varying | YES |
| 88 | public_zip | character varying | YES |
| 89 | public_addr1 | character varying | YES |
| 90 | public_addr2 | character varying | YES |
| 91 | public_addr_same_as_org | boolean | YES |
| 92 | mailing_zip | character varying | YES |
| 93 | mailing_addr1 | character varying | YES |
| 94 | mailing_addr2 | character varying | YES |
| 95 | mailing_addr_same_as_org | boolean | YES |
| 96 | password_change_status | smallint | YES |
| 97 | login_id | character varying | YES |
| 98 | mypage_temp_password | character varying | YES |
| 99 | height_is_public | boolean | YES |
| 100 | weight_is_public | boolean | YES |
| 101 | blood_type_is_public | boolean | YES |
| 102 | dominant_arm | character varying | YES |
| 103 | motto | character varying | YES |
| 104 | equipment_contract | character varying | YES |
| 105 | coaching_history | text | YES |
| 106 | sponsor_a | character varying | YES |
| 107 | sponsor_a_url | character varying | YES |
| 108 | sponsor_b | character varying | YES |
| 109 | sponsor_b_url | character varying | YES |
| 110 | sponsor_c | character varying | YES |
| 111 | sponsor_c_url | character varying | YES |
| 112 | association_role | character varying | YES |
| 113 | a_license_number | integer | YES |
| 114 | birthdate_public_hide_year | boolean | NO |
| 115 | birthdate_public_is_private | boolean | NO |
| 116 | member_class | character varying | NO |
| 117 | can_enter_official_tournament | boolean | NO |

## pro_dsp (7 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | name | character varying | NO |
| 3 | gender | character varying | YES |
| 4 | license_no | character varying | NO |
| 5 | created_at | timestamp without time zone | YES |
| 6 | updated_at | timestamp without time zone | YES |
| 7 | pro_bowler_id | bigint | YES |

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

## registered_balls (10 columns)

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
| 12 | pro_bowler_id | bigint | YES |

## score_import_batches (18 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | import_type | character varying | NO |
| 4 | source_filename | character varying | YES |
| 5 | stored_path | text | YES |
| 6 | status | character varying | NO |
| 7 | parser_version | character varying | YES |
| 8 | imported_by | bigint | YES |
| 9 | confirmed_by | bigint | YES |
| 10 | row_count | integer | NO |
| 11 | accepted_row_count | integer | NO |
| 12 | rejected_row_count | integer | NO |
| 13 | parsed_at | timestamp without time zone | YES |
| 14 | confirmed_at | timestamp without time zone | YES |
| 15 | error_message | text | YES |
| 16 | notes | text | YES |
| 17 | created_at | timestamp without time zone | YES |
| 18 | updated_at | timestamp without time zone | YES |

## score_import_operation_logs (15 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | score_import_batch_id | bigint | YES |
| 4 | action | character varying | NO |
| 5 | status | character varying | NO |
| 6 | actor_user_id | bigint | YES |
| 7 | target_row_count | integer | NO |
| 8 | created_count | integer | NO |
| 9 | updated_count | integer | NO |
| 10 | skipped_count | integer | NO |
| 11 | message | text | YES |
| 12 | payload | json | YES |
| 13 | occurred_at | timestamp without time zone | NO |
| 14 | created_at | timestamp without time zone | YES |
| 15 | updated_at | timestamp without time zone | YES |

## score_import_row_candidates (12 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | score_import_row_id | bigint | NO |
| 3 | candidate_type | character varying | NO |
| 4 | candidate_value | character varying | YES |
| 5 | tournament_participant_id | bigint | YES |
| 6 | pro_bowler_id | bigint | YES |
| 7 | confidence | numeric | YES |
| 8 | rank | integer | YES |
| 9 | payload | json | YES |
| 10 | is_selected | boolean | NO |
| 11 | created_at | timestamp without time zone | YES |
| 12 | updated_at | timestamp without time zone | YES |

## score_import_rows (22 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | score_import_batch_id | bigint | NO |
| 3 | row_number | integer | NO |
| 4 | raw_payload | json | YES |
| 5 | parse_status | character varying | NO |
| 6 | confidence | numeric | YES |
| 7 | tournament_participant_id | bigint | YES |
| 8 | pro_bowler_id | bigint | YES |
| 9 | license_number | character varying | YES |
| 10 | name | character varying | YES |
| 11 | entry_number | character varying | YES |
| 12 | stage | character varying | YES |
| 13 | shift | character varying | YES |
| 14 | gender | character varying | YES |
| 15 | game_number | integer | YES |
| 16 | score | smallint | YES |
| 17 | error_message | text | YES |
| 18 | reviewed_by | bigint | YES |
| 19 | reviewed_at | timestamp without time zone | YES |
| 20 | confirmed_game_score_id | bigint | YES |
| 21 | created_at | timestamp without time zone | YES |
| 22 | updated_at | timestamp without time zone | YES |

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

## tournament_auto_draw_logs (11 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | target_type | character varying | NO |
| 4 | deadline_at | timestamp without time zone | YES |
| 5 | executed_at | timestamp without time zone | NO |
| 6 | total_pending | integer | NO |
| 7 | success_count | integer | NO |
| 8 | failed_count | integer | NO |
| 9 | details_json | json | YES |
| 10 | created_at | timestamp without time zone | YES |
| 11 | updated_at | timestamp without time zone | YES |

## tournament_awards (6 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | rank | integer | NO |
| 4 | prize_money | integer | NO |
| 5 | created_at | timestamp without time zone | YES |
| 6 | updated_at | timestamp without time zone | YES |

## tournament_draw_reminder_logs (14 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | tournament_entry_id | bigint | NO |
| 4 | reminder_kind | character varying | NO |
| 5 | pending_type | character varying | NO |
| 6 | scheduled_for_date | date | YES |
| 7 | dispatch_key | character varying | YES |
| 8 | recipient_email | character varying | NO |
| 9 | subject | character varying | NO |
| 10 | status | character varying | NO |
| 11 | sent_at | timestamp without time zone | YES |
| 12 | error_message | text | YES |
| 13 | created_at | timestamp without time zone | YES |
| 14 | updated_at | timestamp without time zone | YES |

## tournament_entries (17 columns)

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
| 13 | waitlist_priority | integer | YES |
| 14 | waitlisted_at | timestamp without time zone | YES |
| 15 | waitlist_note | text | YES |
| 16 | promoted_from_waitlist_at | timestamp without time zone | YES |
| 17 | preferred_shift_code | character varying | YES |

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

## tournament_match_score_frames (12 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | score_sheet_player_id | bigint | NO |
| 3 | frame_no | smallint | NO |
| 4 | throw1 | character varying | YES |
| 5 | throw2 | character varying | YES |
| 6 | throw3 | character varying | YES |
| 7 | frame_score | integer | YES |
| 8 | cumulative_score | integer | YES |
| 9 | display_marks | json | YES |
| 10 | created_at | timestamp without time zone | YES |
| 11 | updated_at | timestamp without time zone | YES |
| 12 | remaining_pins | jsonb | YES |

## tournament_match_score_sheet_players (15 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | score_sheet_id | bigint | NO |
| 3 | sort_order | integer | NO |
| 4 | player_slot | character varying | YES |
| 5 | pro_bowler_id | bigint | YES |
| 6 | pro_bowler_license_no | character varying | YES |
| 7 | display_name | character varying | NO |
| 8 | name_kana | character varying | YES |
| 9 | dominant_arm | character varying | YES |
| 10 | lane_label | character varying | YES |
| 11 | final_score | integer | NO |
| 12 | is_winner | boolean | NO |
| 13 | score_summary | json | YES |
| 14 | created_at | timestamp without time zone | YES |
| 15 | updated_at | timestamp without time zone | YES |

## tournament_match_score_sheets (14 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | sheet_type | character varying | NO |
| 4 | stage_code | character varying | YES |
| 5 | match_code | character varying | YES |
| 6 | match_label | character varying | YES |
| 7 | match_order | integer | NO |
| 8 | game_number | integer | NO |
| 9 | lane_label | character varying | YES |
| 10 | is_published | boolean | NO |
| 11 | confirmed_at | timestamp without time zone | YES |
| 12 | notes | text | YES |
| 13 | created_at | timestamp without time zone | YES |
| 14 | updated_at | timestamp without time zone | YES |

## tournament_organizations (6 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | category | character varying | NO |
| 4 | name | character varying | NO |
| 5 | url | character varying | YES |
| 6 | sort_order | integer | NO |

## tournament_participants (22 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | pro_bowler_license_no | character varying | NO |
| 4 | created_at | timestamp without time zone | YES |
| 5 | updated_at | timestamp without time zone | YES |
| 6 | pro_bowler_id | bigint | YES |
| 7 | participant_type | character varying | NO |
| 8 | display_name | character varying | YES |
| 9 | display_license_no | character varying | YES |
| 10 | gender | character varying | YES |
| 11 | shift | character varying | YES |
| 12 | lane | smallint | YES |
| 13 | lane_slot | smallint | YES |
| 14 | lane_label | character varying | YES |
| 15 | box_no | smallint | YES |
| 16 | sort_order | integer | YES |
| 17 | source_note | text | YES |
| 18 | is_temporary | boolean | NO |
| 19 | amateur_bowler_id | bigint | YES |
| 20 | display_dominant_arm | character varying | YES |
| 21 | display_affiliation_name | character varying | YES |
| 22 | display_equipment_contract | character varying | YES |

## tournament_points (5 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | tournament_id | bigint | NO |
| 2 | rank | integer | NO |
| 3 | point | integer | NO |
| 4 | created_at | timestamp without time zone | YES |
| 5 | updated_at | timestamp without time zone | YES |

## tournament_result_snapshot_rows (20 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | snapshot_id | bigint | NO |
| 3 | ranking | integer | NO |
| 4 | pro_bowler_id | bigint | YES |
| 5 | pro_bowler_license_no | character varying | YES |
| 6 | amateur_name | character varying | YES |
| 7 | display_name | character varying | NO |
| 8 | gender | character varying | YES |
| 9 | shift | character varying | YES |
| 10 | entry_number | character varying | YES |
| 11 | scratch_pin | integer | NO |
| 12 | carry_pin | integer | NO |
| 13 | total_pin | integer | NO |
| 14 | games | integer | NO |
| 15 | average | numeric | YES |
| 16 | tie_break_value | integer | YES |
| 17 | points | integer | YES |
| 18 | prize_money | numeric | YES |
| 19 | created_at | timestamp without time zone | YES |
| 20 | updated_at | timestamp without time zone | YES |

## tournament_result_snapshots (20 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | result_code | character varying | NO |
| 4 | result_name | character varying | NO |
| 5 | result_type | character varying | NO |
| 6 | stage_name | character varying | YES |
| 7 | gender | character varying | YES |
| 8 | shift | character varying | YES |
| 9 | games_count | integer | NO |
| 10 | carry_game_count | integer | NO |
| 11 | carry_stage_names | json | YES |
| 12 | reflected_at | timestamp without time zone | YES |
| 13 | reflected_by | bigint | YES |
| 14 | is_final | boolean | NO |
| 15 | is_published | boolean | NO |
| 16 | is_current | boolean | NO |
| 17 | notes | text | YES |
| 18 | created_at | timestamp without time zone | YES |
| 19 | updated_at | timestamp without time zone | YES |
| 20 | calculation_definition | json | YES |

## tournament_results (17 columns)

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
| 14 | pro_bowler_id | bigint | YES |
| 15 | affiliation_display | text | YES |
| 16 | award_points | integer | YES |
| 17 | step_points | integer | YES |

## tournament_round_lane_assignments (34 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | source_result_snapshot_id | bigint | YES |
| 4 | tournament_participant_id | bigint | YES |
| 5 | pro_bowler_id | bigint | YES |
| 6 | stage | character varying | NO |
| 7 | round_label | character varying | YES |
| 8 | game_from | smallint | YES |
| 9 | game_to | smallint | YES |
| 10 | seed_rank | integer | YES |
| 11 | pro_bowler_license_no | character varying | YES |
| 12 | display_license_no | character varying | YES |
| 13 | display_name | character varying | NO |
| 14 | period_label | character varying | YES |
| 15 | dominant_arm | character varying | YES |
| 16 | affiliation_display | text | YES |
| 17 | source_total_pin | integer | YES |
| 18 | source_games | smallint | YES |
| 19 | source_average | numeric | YES |
| 20 | start_lane | smallint | YES |
| 21 | lane_slot | smallint | YES |
| 22 | start_lane_label | character varying | YES |
| 23 | box_no | smallint | YES |
| 24 | movement_direction | character varying | NO |
| 25 | movement_box_step | smallint | NO |
| 26 | movement_boxes | json | YES |
| 27 | game_start_time | time without time zone | YES |
| 28 | game_interval_minutes | smallint | YES |
| 29 | tv_lane_from | smallint | YES |
| 30 | tv_lane_to | smallint | YES |
| 31 | sort_order | integer | YES |
| 32 | note | text | YES |
| 33 | created_at | timestamp without time zone | YES |
| 34 | updated_at | timestamp without time zone | YES |

## tournament_seed_players (16 columns)

| # | column | type | nullable |
|---:|---|---|---|
| 1 | id | bigint | NO |
| 2 | tournament_id | bigint | NO |
| 3 | pro_bowler_id | bigint | YES |
| 4 | license_no | character varying | YES |
| 5 | seed_source_type | character varying | NO |
| 6 | seed_list_player_id | bigint | YES |
| 7 | ranking_snapshot_id | bigint | YES |
| 8 | ranking_rank | integer | YES |
| 9 | source_tournament_id | bigint | YES |
| 10 | pro_bowler_title_id | bigint | YES |
| 11 | priority_order | integer | YES |
| 12 | display_label | character varying | YES |
| 13 | note | text | YES |
| 14 | is_active | boolean | NO |
| 15 | created_at | timestamp without time zone | YES |
| 16 | updated_at | timestamp without time zone | YES |

## tournaments (83 columns)

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
| 32 | title_logo_path | character varying | YES |
| 33 | year | integer | YES |
| 34 | gender | character varying | NO |
| 35 | official_type | character varying | NO |
| 36 | entry_start | timestamp without time zone | YES |
| 37 | entry_end | timestamp without time zone | YES |
| 38 | inspection_required | boolean | NO |
| 39 | title_category | character varying | NO |
| 40 | venue_id | bigint | YES |
| 41 | broadcast_url | character varying | YES |
| 42 | streaming_url | character varying | YES |
| 43 | previous_event_url | character varying | YES |
| 44 | spectator_policy | character varying | YES |
| 45 | admission_fee | text | YES |
| 46 | hero_image_path | character varying | YES |
| 47 | poster_images | json | YES |
| 48 | extra_venues | json | YES |
| 49 | sidebar_schedule | json | YES |
| 50 | award_highlights | json | YES |
| 51 | gallery_items | json | YES |
| 52 | simple_result_pdfs | json | YES |
| 53 | result_cards | json | YES |
| 54 | use_shift_draw | boolean | NO |
| 55 | use_lane_draw | boolean | NO |
| 56 | lane_assignment_mode | character varying | NO |
| 57 | box_player_count | smallint | YES |
| 58 | odd_lane_player_count | smallint | YES |
| 59 | even_lane_player_count | smallint | YES |
| 60 | accept_shift_preference | boolean | NO |
| 61 | auto_draw_reminder_enabled | boolean | NO |
| 62 | auto_draw_reminder_days_before | smallint | NO |
| 63 | auto_draw_reminder_pending_type | character varying | NO |
| 64 | shift_auto_draw_reminder_enabled | boolean | NO |
| 65 | shift_auto_draw_reminder_send_on | date | YES |
| 66 | lane_auto_draw_reminder_enabled | boolean | NO |
| 67 | lane_auto_draw_reminder_send_on | date | YES |
| 68 | result_flow_type | character varying | NO |
| 69 | round_robin_qualifier_count | integer | YES |
| 70 | round_robin_win_bonus | integer | YES |
| 71 | round_robin_tie_bonus | integer | YES |
| 72 | round_robin_position_round_enabled | boolean | NO |
| 73 | single_elimination_qualifier_count | integer | YES |
| 74 | single_elimination_seed_source_result_code | character varying | YES |
| 75 | single_elimination_seed_policy | character varying | YES |
| 76 | single_elimination_seed_settings | json | YES |
| 77 | result_carry_preset | character varying | YES |
| 78 | result_carry_settings | json | YES |
| 79 | shootout_qualifier_count | integer | YES |
| 80 | shootout_seed_source_result_code | character varying | YES |
| 81 | shootout_format | character varying | YES |
| 82 | shootout_settings | json | YES |
| 83 | lane_movement_settings | json | YES |

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
