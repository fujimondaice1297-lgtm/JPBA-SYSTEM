# 定期処理運用ガイド（2026-08-26）

## 正本

- Laravel 12の定期処理定義は `routes/console.php` を正本とする。
- `app/Console/Kernel.php` は旧構成の互換ファイルであり、定期処理を登録しない。
- 本番環境ではLaravel schedulerを1分ごとに起動するOS側設定が別途必要。

## 登録済み処理

| 時刻（Asia/Tokyo） | コマンド | 目的 | 重複防止・ログ |
|---|---|---|---|
| 毎日 09:00 | `tournament:send-draw-reminders` | 指定日のシフト／レーン未抽選者へ通知 | `dispatch_key`、`withoutOverlapping(120)`、DB運用ログ、`storage/logs/scheduled-tournament-draw-reminders.log` |
| 毎時 00分 | `tournament:auto-draw-pending` | 締切後の未抽選者を事務局抽選 | `withoutOverlapping(120)`、DB運用ログ、`storage/logs/scheduled-tournament-auto-draw.log` |
| 毎年12月31日 00:05 | `balls:audit-retention` | 検量証待ち・期限切れ・大会履歴あり件数を監査 | `withoutOverlapping(120)`、`storage/logs/scheduled-ball-retention-audit.log` |

既存のUSBC同期、TP講習通知、会員種別同期も同じ正本へ登録済み。

## 安全確認コマンド

```powershell
php artisan schedule:list
php artisan tournament:send-draw-reminders --date=2026-08-26 --dry-run
php artisan tournament:auto-draw-pending --datetime="2026-08-26 12:00:00" --dry-run
php artisan balls:audit-retention --date=2026-08-26 --json
```

期待結果：各コマンドが終了コード0で完了する。dry-runではメール、抽選確定、運用ログ追加を行わない。保持監査の `deleted` は常に0となる。

## ボール履歴保持方針

- 検量証未登録、検量証期限切れ、過去大会で使用したボールは物理削除しない。
- 状態は既存の `inspection_number` と `expires_at` から判定し、管理一覧で確認する。
- 旧 `balls:delete-without-certificate`、`usedballs:delete-expired`、別名 `app:delete-expired-used-balls` は安全な監査表示だけを行い、削除件数0を返す。
- 大会当日に検量証が有効かどうかは大会登録側で判定し、過去大会へ遡及適用しない。

## NG時の確認先

- スケジュールが表示されない：`routes/console.php` と `bootstrap/app.php` の `commands` 指定を確認する。
- 同じ通知が重複する：`tournament_draw_reminder_logs.dispatch_key` とスケジューラーの多重起動を確認する。
- 自動抽選に失敗する：管理画面の大会運用ログと `scheduled-tournament-auto-draw.log` を確認する。
- ボール監査に失敗する：`registered_balls`、`used_balls`、`tournament_entry_balls` のmigration適用状態を確認する。
