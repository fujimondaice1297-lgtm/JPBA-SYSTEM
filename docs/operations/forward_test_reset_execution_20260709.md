# 2026-07-09 実運用フォワードテスト用リセット実行結果

## 実行概要

実運用フォワードテスト開始のため、プロトタイプ中に投入した入力済みデータを削除した。
削除対象は行データのみで、テーブル、カラム、インデックス、外部キー、migration、Model、Controller、Service、View、Route は残した。

## 実行条件

- 新管理者氏名: `admin`
- 新管理者メール: `yamaguchi@jpba.or.jp`
- `--include-content`: なし
- `--include-pro-test`: なし
- `informations`: 残す
- バックアップ確認: 済み

パスワードはログに記録しない。

## バックアップ

- DB dump: `storage/backups/forward_test_reset_20260709_092735/jpba_main.dump`
- Upload backup: `storage/backups/forward_test_reset_20260709_092735/storage_app_public.zip`

## 削除後の確認

`php artisan jpba:forward-test-reset --admin-email="yamaguchi@jpba.or.jp" --json` のドライランで、通常対象の削除候補が0行であることを確認した。

主要テーブル件数:

| Table | Rows |
| --- | ---: |
| users | 1 |
| pro_bowlers | 0 |
| instructors | 0 |
| instructor_registry | 0 |
| tournaments | 0 |
| tournament_results | 0 |
| game_scores | 0 |
| score_import_rows | 0 |
| record_types | 0 |
| informations | 1 |
| pro_test | 0 |
| pro_test_schedule | 0 |

新管理者:

- `yamaguchi@jpba.or.jp`
- `name=admin`
- `role=admin`
- `is_admin=true`
- `pro_bowler_id=null`
- `pro_bowler_license_no=null`
- 指定された初期パスワードでハッシュ検証OK

`record_types` は行0件だが、以下のカラムが残っていることを確認した。

- `id`
- `pro_bowler_id`
- `record_type`
- `tournament_name`
- `game_numbers`
- `frame_number`
- `awarded_on`
- `certification_number`
- `created_at`
- `updated_at`

## 画面/構成確認

- `php artisan view:cache`: OK
- `php artisan route:list --except-vendor`: 319 routes 読み込みOK
- `php artisan public:parity-audit`: 12ページすべて status 200 / OK

公開監査対象:

- `/`
- `/about`
- `/schedule`
- `/players`
- `/tournament`
- `/instructor`
- `/protest`
- `/topics`
- `/contact`
- `/media`
- `/commerce`
- `/privacy`

## 残したもの

- `informations` 1行
- public content schema
- pro-test schema
- `record_types` など将来利用する空テーブル/カラム
- UI、入力導線、自動反映システム、PDF生成、OCR/スコア取込、公開互換、DB構造、コード資産
- バックアップファイル

## 次に行う候補

1. 2026年7月現在の正会員/プロボウラー情報を再投入する。
2. 2026年7月現在のインストラクター情報を再投入する。
3. 2025年度シードプロ設定を再作成する。
4. 2026年1月以降の大会、参加者、成績、ポイント/賞金/タイトルを再投入する。
5. 現行JPBAサイトとの公開表示整合性を確認する。
