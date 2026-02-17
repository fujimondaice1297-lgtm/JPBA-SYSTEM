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
公認ボールとプロボウラーの紐付け（年単位の使用状況）を保持する中間テーブル。

### 主キー
- id (bigint)

### 主要カラム
- pro_bowler_license_no（ライセンス番号：文字列）
- approved_ball_id（公認ボールID）
- year（年度）

### 注意（設計改善ポイント）
- pro_bowler_license_no（文字列）で持っているため、`pro_bowlers` へのFKは貼れていない。

### 外部キー（FK）
- approved_ball_id -> approved_balls.id

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
- name（地区名）

---

## game_scores

### 役割
大会のゲーム別スコア（ゲームNoごとの点数）を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- tournament_id（どの大会）
- game_no（何ゲーム目か）
- score（点数）
- pro_bowler_license_no（ライセンス番号：文字列）

### 外部キー（FK）
- tournament_id -> tournaments.id

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
- title（タイトル）
- body（本文）
- visibility（公開範囲: public/members 等）
- published_at（公開日時：nullable）

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

## instructors

### 役割
インストラクター認定情報を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム
- pro_bowler_id（対象プロボウラー）
- district_id（所属地区）
- rank（A/B/C 等：nullable）

### 外部キー（FK）
- pro_bowler_id -> pro_bowlers.id
- district_id -> districts.id

---

## kaiin_status

### 役割
会員種別マスタ（正/準/一般等）。

### 主キー
- id (bigint)

### 主要カラム
- name（会員種別名）

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
プロボウラーの基本情報（ハブ）を保持するテーブル。

### 主キー
- id (bigint)

### 主要カラム（抜粋）
- license_no（ライセンス番号：文字列）
- name_kanji / name_kana
- sex（sexes.id 参照）
- district_id（所属地区：nullable）
- is_active / is_visible
- login_id（参照先未確定のためFKなし：ADR参照）
- （他、多数）

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
