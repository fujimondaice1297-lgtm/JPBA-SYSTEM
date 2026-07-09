# 2026-07-09 実運用フォワードテスト用リセット ドライラン結果

## 実施内容

- `jpba:forward-test-reset` を追加した。
- 既定動作はドライランのみ。`--force` を付けない限りDBは変更しない。
- 実削除には `--confirm=FORWARD-TEST-RESET`、`--backup-confirmed`、`--admin-email`、`--admin-password` が必須。
- 新管理者以外のユーザーは、ユーザー参照ログを削除した後、プロボウラー本体を削除する前に整理する。

## バックアップ

- DB dump: `storage/backups/forward_test_reset_20260709_092735/jpba_main.dump`
- Upload backup: `storage/backups/forward_test_reset_20260709_092735/storage_app_public.zip`
- DB: `pgsql` / `jpba_main` / `127.0.0.1:5433`

## 通常ドライラン結果

コマンド:

```bash
php artisan jpba:forward-test-reset --json
```

削除候補合計: 16,212 rows

| Group | Rows |
| --- | ---: |
| auth_runtime | 0 |
| communication_and_membership | 0 |
| score_imports | 1,434 |
| match_score_sheets | 113 |
| tournament_snapshots_and_entries | 2,744 |
| rankings_and_seeds | 666 |
| tournament_results_and_settings | 3,332 |
| pro_bowler_and_instructor_data | 7,923 |

主な内訳:

- `score_import_rows`: 480
- `score_import_row_candidates`: 948
- `tournament_result_snapshot_rows`: 2,428
- `game_scores`: 2,806
- `tournament_results`: 166
- `tournament_entries`: 198
- `tournaments`: 4
- `pro_bowler_ranking_rows`: 586
- `pro_bowler_seed_list_players`: 76
- `instructor_registry`: 4,305
- `instructors`: 1,348
- `pro_bowlers`: 2,270

## オプション込みドライラン結果

コマンド:

```bash
php artisan jpba:forward-test-reset --include-content --include-pro-test --json
```

削除候補合計: 16,213 rows

- `informations`: 1
- `information_files`: 0
- `flash_news`: 0
- `pro_test_*`: 0

公開ニュース/お知らせは現行サイト同期時に消すか判断する。既定では削除しない。

## 現在の管理者候補

- `admin@example.com`
- `role=admin`
- `is_admin=true`
- `pro_bowler_id=56`
- `pro_bowler_license_no=F00000015`

このアカウントはプロボウラーに紐づいているため、実削除前に `pro_bowler_id` に依存しない新管理者を作成する。

## 実削除前に必要な入力

- 新管理者の氏名
- 新管理者のメールアドレス
- 初期パスワード
- 公開ニュース/お知らせを今回の削除に含めるか

## 実削除コマンド例

実行前に、上記の新管理者情報と削除対象を確認すること。

```bash
php artisan jpba:forward-test-reset --force --confirm=FORWARD-TEST-RESET --backup-confirmed --admin-email="..." --admin-name="..." --admin-password="..."
```

公開ニュースも含める場合のみ `--include-content` を追加する。プロテスト運用データを含める場合のみ `--include-pro-test` を追加する。
