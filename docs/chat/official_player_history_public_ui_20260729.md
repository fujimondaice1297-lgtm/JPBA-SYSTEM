# 選手別・年度別公式戦記録／出場大会履歴（2026-07-29）

## 公開表示

- 一般公開選手ページの「公式戦記録」直下に「年度別公式戦記録」を表示する。
- 表示項目は年度、順位、ゲーム数、トータルピン、ポイント、アベレージ、獲得賞金。
- 初期表示は最新10行。11行目以降は「もっと見る」で表示し、「閉じる」で元へ戻す。
- `2020-21` は公式プロフィールの表記どおり1シーズンとして保持・表示する。
- 当年度は最新の `pro_bowler_ranking_snapshots` / `pro_bowler_ranking_rows` を優先する。
- ランキングが年度途中の場合は「n/j現在」を年度欄へ表示する。

## 出場大会

- 年度見出しをクリックしたときだけ、その年度の開催年月日、大会名、最終順位、アベレージ、獲得賞金を表示する。
- 旧サイト移行行は `pro_bowler_tournament_histories` に保持する。
- 新システムに正式成績がある開催日は `tournament_results` を優先し、旧サイト移行行との二重表示を避ける。

## 旧サイト閉鎖後の自動更新

- 新年度のランキングスナップショットと選手行が登録されると、当年度行が自動で先頭へ追加される。
- 表示は常に最新10行を基準にするため、前年以前は自動的に繰り下がる。
- 新しい大会の正式成績が `tournament_results` に登録されると、該当選手の出場大会へ自動反映される。
- 年度更新専用の手入力や旧サイト再取得は不要。

## 旧サイト移行コマンド

既定はドライラン。`--force` を付けた場合だけDBへ保存する。

```powershell
php artisan jpba:import-official-player-profile-stats --license=M00001219 --with-history --history-missing-only --force --sleep-ms=500 --json
```

全選手移行時は、過去に公式プロフィール取得が成功して監査スナップショットがある選手だけを対象にする。

```powershell
php artisan jpba:import-official-player-profile-stats --all-visible --snapshot-existing-only --with-history --history-pending-only --history-missing-only --history-concurrency=1 --force --sleep-ms=500 --json
```

`--history-missing-only` は年度全行の保存後に作られる完了マーカーがある年度だけを再取得しない。途中停止で一部行だけ保存された年度は再取得されるため、同じコマンドで安全に再開できる。

`--history-pending-only` は全出場年度の完了マーカーが揃った選手を基礎プロフィール取得前に除外する。再実行は未完了選手から再開する。

JPBA旧サイトは同一選手の年度ページ同時取得を待機させるため、`--history-concurrency=1` を使用する。

公式サイトの応答エラーが1件発生した場合は次の選手へ進まず安全停止する。サイト回復後に同じコマンドを再実行する。明示的に全選手を継続確認したい監査時だけ `--continue-after-history-error` を付ける。

サイトの一時的な応答制限を待ちながら自動再開する場合は、次の運用スクリプトを使用する。

```powershell
powershell -ExecutionPolicy Bypass -File tools\run_official_player_history_backfill.ps1
```
