# 過去公式トーナメント・大会資料アーカイブ運用

## 目的

現行JPBAサイト閉鎖後も、2016年以降の公式トーナメント、大会要項、成績、オイルパターン等を新サイト内で閲覧できる状態にする。
現行大会運用の `tournaments` とは別に `tournament_archives` を正本とし、過去資料の移行がエントリー・速報・ランキングへ影響しないようにする。

## 公開・管理画面

- 一般一覧: `/tournament/archive`
- 一般詳細: `/tournament/archive/{archive}`
- 管理一覧: `/admin/tournament-archives`
- 管理編集: `/admin/tournament-archives/{archive}/edit`

一般画面には移行元URLを表示しない。大会資料と成績は `public/documents/jpba/tournament-archive/assets` から配信する。

## 初回取得・再同期

```powershell
php artisan jpba:sync-tournament-archive --refresh --from=2016
```

- 公式大会一覧から当年までの大会ページを再取得する。
- 大会要項、成績、オイルパターン等の文書は全件保存する。
- 大会写真はページ肥大化を防ぐため、各大会の代表12点まで保存する。
- 保存済みファイルは再取得せず再利用するため、通信中断後も同じコマンドで再開できる。
- 資料取得は一時的な通信失敗を3回まで自動再試行し、PDF・画像・Office資料のファイル署名を検査する。
- 404または資料URLからHTMLが返る場合は壊れたファイルを保存せず、失敗理由をJSONへ残す。
- `source_key` で冪等更新し、同じ大会を重複登録しない。
- 年度と大会名が既存 `tournaments` に完全一致した場合だけ参照を結ぶ。

## 差分確認とJSON再適用

```powershell
php artisan jpba:sync-tournament-archive --dry-run
php artisan jpba:sync-tournament-archive
```

取得結果と失敗理由は `resources/data/jpba_tournament_archive.json` に記録される。
管理画面で大会名・日程・本文・公開状態を修正した行は、指紋差分で検知し、次回同期でも公式取得値に上書きしない。

## 更新時の確認

1. DBバックアップを取得する。
2. `--refresh` で当年分を含めて再取得する。
3. JSONの `page_failure_count` と `asset_failure_count` を確認する。
4. 失敗が通信起因なら同じコマンドを再実行する。
5. `/tournament/archive` で年度件数、開催日、大会名、保存資料を確認する。
6. 管理画面の手修正が保持されていることを確認する。

## 2026年9月6日現在の移行結果

- 2016～2026年: 259大会（ページ解析失敗0件）
- サイト内保存: 7,185資料、1,893,286,979 bytes
- 公式側で取得不能: 43リンク（HTTP 404が20件、資料URLからHTMLが返るものが23件）
- 大会名未判定、開催日未判定、終了日逆転、本文内の旧JPBA URLはいずれも0件

## 監査元

- `https://www.jpba.or.jp/information/tournament/tournament.html`

監査元URLは再同期と確認のためにDB・JSONへ保持するが、新サイトの一般公開リンクとしては使用しない。
