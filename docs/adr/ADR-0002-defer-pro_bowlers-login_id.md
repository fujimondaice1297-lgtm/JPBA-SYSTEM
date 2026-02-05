# ADR-0002: pro_bowlers.login_id の参照先を現時点では確定しない

- Date: 2026-02-05
- Status: Accepted

## Context
- `pro_bowlers.login_id` は *_id 形式だが、DB上で FK 制約が存在しない（参照先未確定）。
- 旧システム由来の「login 系マスタ」の実体（テーブル/仕様）が未確定で、現時点で決め打ちすると誤設計になるリスクが高い。
- 現行の認証/運用は `users.pro_bowler_id -> pro_bowlers.id` を軸に成立しており、`login_id` は必須ではない。

## Decision
- 現時点では `pro_bowlers.login_id` に FK を追加しない。
- `docs/db/refs_skipped.md` に「未確定のため保留」として残し、参照先確定時に対応する。
- 参照先が確定した段階で以下いずれかを実施する：
  - A) login 系マスタ（例: login_accounts）を新設して FK を追加
  - B) 既存の仕様（users 等）に統合し、`login_id` を置換/廃止

## Consequences
- ER（ER.dbml）上は Ref を引かないため、図上では孤立カラムとして扱われる。
- アプリ側で `login_id` を使う実装を入れる場合は、先に参照先仕様の確定が必須になる。
