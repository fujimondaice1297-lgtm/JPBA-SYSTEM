# INFORMATION・トピックス移行運用

## 目的

現行JPBAサイト閉鎖後も、過去のINFORMATION・トピックスを新サイトだけで閲覧・編集できるようにする。

## 初期移行結果（2026-09-05）

- 記事: 978件
  - INFORMATION: 395件
  - トピックス: 583件
- サイト内保存ファイル: 1,756件（約437.6MB）
- 取得不能: 14件
  - 旧サイト側404: 11件
  - 旧サイト側が空内容を返すもの: 3件
- DB添付参照: 1,762件（同一ファイルを複数記事が参照するため保存ファイル数より多い）

取得不能14件は `resources/data/jpba_public_content_archive.json` の `missing_assets` にURLと理由を保持する。

## 実行コマンド

保存済みJSONをDBへ反映する（通常はこちら）:

```powershell
php artisan jpba:sync-public-content-archive
```

現行公式サイトを再取得し、JSON・画像・PDFを更新してDBへ反映する:

```powershell
php artisan jpba:sync-public-content-archive --refresh
```

DBへ書き込まず差分件数だけ確認する:

```powershell
php artisan jpba:sync-public-content-archive --dry-run
```

## 安全仕様

- `source_key` で記事を一意にし、同じデータを再実行しても重複しない。
- 旧サイトの内部リンクは公開本文へ残さない。画像・PDFは新サイト内のパスを使う。
- 管理画面で手修正した移行記事は、次の自動取込で上書きしない。
- 管理画面では公開日、本文、既存添付の表示名・公開範囲・参照解除、新規添付を編集できる。
- 添付参照を外しても、共有される可能性がある保存ファイルの実体は削除しない。
- 添付配信は `public` または `storage/app/public` 配下の実在ファイルだけを許可し、親ディレクトリ参照を拒否する。

## 更新時の確認

```powershell
php artisan test tests/Unit/JpbaPublicContentArchiveServiceTest.php tests/Unit/InformationFilePathSafetyTest.php tests/Unit/PublicContentArchiveSnapshotTest.php tests/Feature/PublicInformationLayoutTest.php tests/Feature/InformationArchiveAdminEditTest.php
php artisan view:cache
```

`PublicContentArchiveSnapshotTest` は、記事978件、移行元別件数、一意キー、空本文、旧サイトURL残存、添付1,756件の実在と保存範囲を検査する。
