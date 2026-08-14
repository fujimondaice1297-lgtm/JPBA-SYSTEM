# 2026-08-14 開発チェックポイント

## 保全対象

- 2026-07-24以降に実装した大会成績フォーマット、公認記録、選手公式戦履歴、ボールカタログ・登録、USBC照合、年間予定表、固定公開ページ、TP講習会、選手写真、会員種別自動判定。
- アプリケーションコード、migration、公式取込データ、テスト、運用資料、DB定義資料をGitコミットへ保存する。
- `tmp/` と過去の `storage/backups/` は生成物・既存退避物のためGitへ含めない。

## Git外バックアップ

保存先：`storage/backups/development_checkpoint_20260814/`

| ファイル | 内容 | サイズ | SHA-256 |
|---|---|---:|---|
| `jpba_main.dump` | PostgreSQL `jpba_main` 全体（custom format） | 15,114,910 bytes | `D4F2F0C398BE2D3E4FFF84132BD7362E792ED9F0262FC8E8C3DBA326D81387EC` |
| `player_profiles.zip` | 選手写真2,180件 | 73,733,927 bytes | `DA2FB9E60006FE1F295849CE4D9F6539F5546B0CB023A9BDA3DD57EF89D33C50` |

このフォルダは個人写真・会員データを含むためGitへ登録しない。OneDrive上のローカル保全物として扱う。

## 再開時

- Gitタグ `checkpoint-2026-08-14` をこの時点のコード正本とする。
- DB復元が必要な場合のみ `jpba_main.dump` を使用する。既存DBへ無条件に上書きせず、復元先を確認してから実施する。
- 写真欠損時は `player_profiles.zip` を `storage/app/public/` 配下へ復元する。
