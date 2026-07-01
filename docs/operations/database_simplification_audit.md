# DB・構成整理監査

最終更新: 2026-07-01

## 目的

DB、migration、Controller、Service の重複や孤立ファイルを確認し、運用に影響しない範囲で軽量化する。

DBテーブルそのものの削除は、本番移行や履歴復元への影響が大きいため、今回の整理では実施しない。削除対象は、ルート未接続かつ静的参照がない古いController/View/Requestに限定する。

## 監査結果

| 対象 | 結果 | 対応 |
|---|---|---|
| migration適用状況 | 167件すべて適用済み | 未適用migrationなし |
| migration timestamp重複 | `2025_09_02_000026` が2件 | 既知レガシーとして維持。安易なリネーム/削除は禁止 |
| Model -> DB table照合 | `app/Models/*.php` の全Modelが現DBテーブルを参照 | Model側のテーブル不一致なし |
| Service参照 | 完全未参照のServiceは検出なし | 削除なし |
| Controllerルート接続 | 整理前は74本中6本がルート未接続。整理後は68本すべて接続済み | 孤立Controllerを削除 |
| 現DBテーブル | 95テーブル中31テーブルにデータ、64テーブルは空 | 空テーブルは将来運用/互換/標準テーブルを含むため削除しない |

## 削除した孤立ファイル

ルート未接続、静的参照なし、または現行Controllerへ置き換え済みのため削除した。

| 種別 | ファイル | 理由 |
|---|---|---|
| Controller | `app/Http/Controllers/MemberAuthController.php` | 現行ログインは `AuthController` / Laravel password reset 系で運用。該当Viewも存在しない |
| Controller | `app/Http/Controllers/PasswordSetupController.php` | ルート未接続。現行は `ForgotPasswordController` のリセット導線 |
| Controller | `app/Http/Controllers/PlayerBallController.php` | `player_balls.index` だけを返す仮Controller。現行は `UsedBallController` |
| Controller | `app/Http/Controllers/TournamentBallController.php` | `tournament_balls.index` だけを返す仮Controller。現行は `TournamentEntryBallController` |
| Controller | `app/Http/Controllers/ProfileController.php` | `routes/profile.php` 未読込。会員プロフィールは現行導線で扱う |
| Controller | `app/Http/Controllers/VenueController.php` | 現行は `VenuePageController` の検索/API/CRUDに統合済み |
| Request | `app/Http/Requests/ProfileUpdateRequest.php` | `ProfileController` 専用で孤立 |
| Route | `routes/profile.php` | `bootstrap/app.php` から未読込 |
| View | `resources/views/auth/password_setup_request.blade.php` | `PasswordSetupController` 専用で孤立 |
| View | `resources/views/auth/password_setup_reset.blade.php` | `PasswordSetupController` 専用で孤立 |
| View | `resources/views/player_balls/index.blade.php` | `PlayerBallController` 専用で孤立 |
| View | `resources/views/tournament_balls/index.blade.php` | `TournamentBallController` 専用で孤立 |
| View | `resources/views/profile/**` | `ProfileController` / `routes/profile.php` 専用で孤立 |

## DBテーブル削除を見送った理由

空テーブルが多いが、以下を含むため今回削除しない。

- Laravel標準: `jobs`, `failed_jobs`, `sessions`, `cache_locks`, `password_reset_tokens`
- 今後の運用導線: `score_import_*`, `tournament_entry_operation_logs`, `tournament_auto_draw_logs`, `tournament_draw_reminder_logs`
- 公開/管理画面の入力先: `calendar_events`, `flash_news`, `information_files`, `organization_masters`, `venues`, `registered_balls`, `used_balls`
- 旧互換・段階移行: `instructors`, `tournament_points`, `tournament_awards`, `tournamentscore`, `pro_dsp`
- ProTest/インストラクターなど将来機能: `pro_test_*`, `trainings`, `pro_bowler_trainings`

DBを軽くする作業は、テーブル削除ではなく「正本テーブルを明確にし、空の旧互換テーブルを新規自動化で読まない」方針で進める。

## 検証

- `php artisan migrate:status`
- Model -> table照合: 全Modelの `getTable()` が `Schema::hasTable()` true
- Controller接続再集計: 68 Controller / 68 route-connected / 0 unroute
- 削除対象名の静的検索: 削除対象Controller/View/Requestの参照なし

