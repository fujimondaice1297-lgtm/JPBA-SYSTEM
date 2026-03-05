# JPBA Codex Operating Rules (AGENTS.md)

## 0) 必読（作業開始前に必ず参照）
- docs/db/PREFLIGHT.md（作業前チェック）
- docs/db/SCHEMA.sql（現物スキーマのスナップショット＝根拠）
- docs/db/MIGRATIONS_INDEX.md（migration一覧）
- docs/chat/worklog_db.md（過去経緯・判断の文脈）
- docs/chat/progress_board.md（今の優先順位）

## 1) 最重要（破ったら作業停止）
- ユーザーはコードを書けず、ほとんど読めない。説明は必ず「コピペできるコマンド」と「変更ファイル一覧」と「確認方法」だけで構成すること。
- 作業開始前に必ず Preflight を行う（PREFLIGHT.md を読み、SCHEMA.sql を根拠に確認する）。
- 推測でカラム名/テーブル名を書かない。必ず SCHEMA.sql を参照すること。
- 新しい migration を作る前に、database/migrations を検索して同目的や近いものが既に無いか確認すること。
- 1タスク=最小差分=1コミット。関係ないファイルは触らない。
- 既存migrationの安易な rename/delete は禁止（migrate履歴が壊れる可能性がある）。必要なら「追加migration」か「計画的な整理手順」を提案する。

## 2) 出力フォーマット（毎回この順）
1) Preflight（参照したファイル名・確認したコマンド・影響範囲）
2) 変更するファイル一覧（フルパス）
3) ユーザーが実行するコマンド（コピペ）
4) 期待される結果（OK/NGの見分け）

## 3) DB変更後の必須
- DB/Schemaに影響がある作業をしたら、必ず SCHEMA.sql を更新するためのコマンドを提示する：
  - pg_dump -s -h 127.0.0.1 -p 5433 -U postgres -d jpba_main -f "docs\db\SCHEMA.sql"
- MIGRATIONS_INDEX.md が古くなる場合は更新する。
- DB関連の作業をしたら、docs/chat/worklog_db.md に「何をしたか」を追記する（短くてよい）。

## 4) 既知の注意（レガシー）
- 2025_09_02_000026 の重複タイムスタンプ migration が存在する。
  - 安易に rename/delete しない（PREFLIGHT.md の注記に従う）
  - 必要になった場合は「計画的なcleanup手順」を提案する