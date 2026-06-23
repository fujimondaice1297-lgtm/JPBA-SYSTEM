# context_pack（このチャット開始時点の状況）

## 1. いまの目的（最重要）
- JPBA1サイト踏襲を優先しつつ、バックエンドDBを正規化・DX化する
- 現在は公開中のJPBA1本番URLではなく、ローカル/プロトタイプとしてリニューアル版を整備している
- 手作業運用を減らし、大会・成績・ランキング・シード・エントリー・ボール登録などをDB正本から自動反映できる状態へ寄せる

## 2. 現在のブランチ/コミット
- branch: `main`
- upstream: `origin/main`
- HEAD: `4a91069 docs: シードプロ整備と永久シード登録の作業ログを更新`
- 作業開始時の通常差分: なし
- 未追跡: `storage/backups/`（バックアップ/一時投入スクリプト置き場。Git管理に入れない）

## 3. 直近で変更したファイル（最大10個）
- `routes/web.php`
  - 本番にも登録されていた `__debug` ルートを `app()->environment('local')` 限定へ変更
  - 構文確認: `php -l routes/web.php`

## 4. いま困っていること（あれば）
- 現DBから `docs/db/SCHEMA.sql` / `docs/db/columns_public.csv` / `docs/db/columns_by_table.md` を更新済み
- `pro_bowler_ranking_*` / `pro_bowler_seed_*` / `tournament_seed_players` は `SCHEMA.sql` とカラム資料にも反映済み
- `tools/generate_db_docs.php` は PHP の `fgetcsv()` 警告を避けるため、escape引数を明示済み

## 5. 次にやる作業（チェックリスト）
- [ ] `git status -sb` で `storage/backups/` 以外の差分を確認する
- [x] `docs/db/SCHEMA.sql` と `docs/db/columns_by_table.md` の古さを現DBで確認する
- [x] `pg_dump -s` で `docs/db/SCHEMA.sql` を更新する
- [x] カラム一覧資料を再生成する
- [ ] 大会別 `歴代優勝者シード` の登録画面・リスト作成へ進む
- [ ] 年度別シード / 永久シード / 大会別追加シードが、エントリー管理・優先出場PDF・大会PDFの `S` 表示に正しく接続されるか回帰確認する

## 6. 参照してほしい資料
- docs/db/data_dictionary.md
- docs/db/ER.dbml（辞書から自動生成）
- docs/db/refs_missing.md
- docs/db/refs_skipped.md
- docs/chat/worklog_db.md（追記済み）
- docs/chat/progress_board.md
- docs/db/PREFLIGHT.md
- docs/db/SCHEMA.sql
