# JPBA暗号化バックアップ・復元ガイド（2026-08-26）

## 方針

- PostgreSQL、`storage/app/public`、`storage/app/private` を毎日01:15（Asia/Tokyo）にバックアップする。
- 完了済みの最新14世代を保持する。手動作成済みの旧チェックポイントは自動世代管理の対象外。
- DB、会員情報、選手写真、公式取込資料を含むため、全payloadをZIP AES-256で暗号化する。
- ファイル名も平文で露出させないため、内部payload ZIPを1ファイルとしてAES-256暗号化する。
- 暗号鍵はGit、公開領域、バックアップarchive、manifestへ入れない。
- 復元先DBは `jpba_restore_` で始まる自動生成名だけを許可し、現行 `jpba_main` への復元を拒否する。
- 復元先ファイルも `storage/backups/restore-tests` 配下の自動生成フォルダへ限定する。

## 初回設定

```powershell
php artisan jpba:backup --dry-run
php artisan jpba:backup --initialize-key --isolated
```

期待結果：初回だけ `storage/jpba-backup.key` が作られ、`storage/backups/automated/backup_YYYYMMDD_HHMMSS` に3暗号化archiveと `manifest.json` が作られる。

鍵を失うと復元できない。本番では `JPBA_BACKUP_KEY_PATH` をサーバーの秘密情報領域へ設定し、バックアップ媒体とは別の安全な場所にも復旧用コピーを保管する。鍵の内容をメール、作業ログ、Git、manifestへ記載しない。

## 日常確認

```powershell
php artisan schedule:list
php artisan jpba:backup-verify
```

期待結果：日次バックアップが一覧に表示され、DB・公開・非公開の各archiveで次がすべて `true` になる。

- `archive_exists`
- `sha256_matches`
- `password_readable`
- `wrong_key_rejected`

実行ログ：`storage/logs/scheduled-jpba-backup.log`

## 実復元試験

```powershell
php artisan jpba:backup-verify --restore-test
```

処理内容：

1. archiveのSHA-256、正しい鍵、誤鍵拒否を確認する。
2. 公開・非公開ファイルを別フォルダへ全展開する。
3. `jpba_restore_YYYYMMDD_HHMMSS` という別DBを作成して `pg_restore` する。
4. manifest記録の主要テーブル件数、公開／非公開ファイル件数とbytes、選手写真件数を照合する。
5. 選手写真1件を画像として開き、寸法とMIMEを検査する。
6. 成功後、試験用DBと試験用フォルダだけを削除する。

調査のため復元先を残す場合だけ `--keep` を付ける。`--keep` なしでは現行DB・現行ファイルを変更しない。

## 2026-08-26 初回実績

- 世代：`backup_20260826_004934`
- DB：選手2,286、大会25、カタログ916、ゲームスコア23,246、最終成績2,053、ユーザー1で復元前後一致。
- 公開：3,157ファイル、501,709,525 bytesで一致。
- 非公開：4,807ファイル、921,023,999 bytesで一致。
- 選手写真：2,180ファイル、84,560,110 bytesで一致。復元画像200×267 JPEGを読込成功。
- 暗号化archive：正しい鍵で読込成功、誤った鍵は全3archiveで拒否。
- 復元先：別DB・別フォルダを使用し、検査後に削除。`jpba_restore_` DB残存0件、現行DBは `jpba_main` のまま。
- 現行公開スモーク：トップ、選手一覧、選手プロフィール、選手写真、2026年間予定表がすべてHTTP 200。

## NG時

- `pg_dump` 等が見つからない：`.env` の `JPBA_*_BINARY` に実行ファイルの絶対パスを設定する。
- 鍵がない：既存archiveを復元する場合は新規生成せず、保管済みの同じ鍵を `JPBA_BACKUP_KEY_PATH` へ戻す。
- SHA-256不一致：その世代は使用せず、前の世代を検証する。
- 件数不一致：復元先は残さず、manifestと実行ログを保存して原因を確認する。
- 本番復旧：まず別DB・別フォルダへ復元して検証し、現行環境の切替は別工程として明示的に行う。
