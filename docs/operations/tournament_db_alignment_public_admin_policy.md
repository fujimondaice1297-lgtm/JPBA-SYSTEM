# 大会DB正本・公開/管理境界の照合メモ

作成日: 2026-07-01

このメモは、Active Backlog G のうち、`tournaments` 周辺スキーマ、`docs/db` 資料照合、公開側/管理側の役割分担をまとめて整理するためのものです。

## 今回の確認結果

- `docs/db/SCHEMA.sql` を現DBから `pg_dump -s` で再生成した。
  - PostgreSQL 18 の `\restrict` ランダム差分を抑えるため、固定 `--restrict-key` を指定する。
- `docs/db/columns_public.csv` を現DBの `information_schema.columns` から再生成した。
- `php tools/generate_db_docs.php` で `docs/db/columns_by_table.md` を再生成した。
- `php tools/generate_er_from_dictionary.php` で `docs/db/ER.dbml` を再生成した。
- 現DB確認で、主要大会系テーブルは以下のカラム数だった。
  - `tournaments`: 83 columns
  - `tournament_entries`: 17 columns
  - `tournament_results`: 17 columns
  - `tournament_files`: 7 columns
  - `tournament_result_snapshots`: 20 columns
  - `tournament_result_snapshot_rows`: 20 columns

再生成後の構造差分は、`SCHEMA.sql` の `informations_category_check` が現DBどおり `TV情報` を含む制約へ更新された点だけでした。
`tournaments` 周辺は、辞書、ER、columns資料、現DBのカラム数に新たなズレは見つかりませんでした。

## 大会系テーブルの正本

大会運用で読む正本は、用途ごとに以下へ分けます。

- `tournaments`: 大会マスタ、開催情報、抽選設定、成績方式設定、公開表示用JSON
- `tournament_files`: 大会に紐づく公開/管理PDF、画像、添付
- `tournament_entries`: 申込、参加/不参加、ウェイティング、シフト/レーン、チェックイン
- `score_import_batches` / `score_import_rows`: CSV、Excel、OCR/AI変換結果の一時取込と確認
- `game_scores`: 投球スコアの正本
- `tournament_result_snapshots`: 速報、途中成績、正式反映前後の計算結果
- `tournament_result_snapshot_rows`: snapshot内の選手別行
- `tournament_results`: 最終成績の正本
- `pro_bowler_seed_lists` / `tournament_seed_players`: 年度別シード、大会別追加シード、優先出場順位

`tournaments` は大会設定の正本ですが、最終成績、エントリー、シード根拠、投球スコアそのものを抱え込みません。
それぞれの専用テーブルを正本にし、公開画面やPDFでは必要な正本を合成して表示します。

## 公開側と管理側の境界

公開側はDB正本を読むだけにします。
公開画面で入力・確定・反映を行わないことを基本ルールにします。

公開側の主な読み取りルートは以下です。

- `PublicHomeController`: トップ大会枠、INFORMATION、バナー/外部リンク
- `PublicTournamentController`: 大会一覧、公開大会詳細、公開PDF、速報/成績リンク
- `PublicPageController`: スケジュール、プロテスト、トピックス、固定ページ
- `PublicPlayerController` / `PublicProfileController`: 選手検索、公開プロフィール
- `PublicInstructorController`: インストラクター公開一覧

管理側は入力、確認、反映、履歴管理を担当します。

- `TournamentController`: 大会マスタ、方式設定、公開用JSON、PDF/画像素材の入力
- `TournamentEntryAdminController`: エントリー、ウェイティング、抽選、チェックイン、取消
- `TournamentOperationLogController`: 大会終了処理チェックリスト、運用診断
- `TournamentScoreImportController`: CSV/Excel/OCR取込、確認、`game_scores` 反映
- `TournamentResultSnapshotController`: 速報/途中成績snapshot、正式成績反映
- `TournamentResultController`: 最終成績、PDF、賞金/ポイント、タイトル反映
- `TournamentSeedPlayerController`: 大会別追加シード、優先出場順位PDF
- `ProBowlerSeedListController`: 年度別シード生成

管理側で確定した結果だけを、公開側が読む構成にします。
これにより、公開サイトは現行JPBAサイトの見た目を保ちながら、裏側はDB正本から自動反映できます。

## DB資料更新の運用

DB変更を行った場合は、以下を同じコミットに含めます。

1. migration
2. `docs/db/SCHEMA.sql`
3. `docs/db/columns_public.csv`
4. `docs/db/columns_by_table.md`
5. `docs/db/data_dictionary.md`
6. `docs/db/ER.dbml`
7. 必要なら `docs/adr` または `docs/operations` の設計メモ

新しい migration を追加する前には `docs/db/PREFLIGHT.md` を確認します。
既に `SCHEMA.sql` に存在するカラムや制約を重複して追加しないこと、既存の重複timestamp migrationを不用意に変更しないことを守ります。

## 今回完了扱いにする項目

- `tournaments` 周辺の最終スキーマを辞書・ER・migrationと揃える
- `docs/db` の辞書、ER、SCHEMA、columns資料を現DBと定期的に照合する
- 公開側はDB正本を読むだけ、管理側は入力・確認・反映を行う役割分担を維持する

## 残す項目

現行サイトの見た目維持については、まだ公開画面ごとの実ブラウザ確認、HTML構造、画像/バナー、PDF/外部リンク、フッターリンクの照合が残っています。
これはDB資料照合とは別作業として、Active Backlogに残します。
