# ADR-0003: pro_test.record_type_id の参照先を現時点では確定しない

- Date: 2026-02-05
- Status: Accepted

## Context
- `pro_test.record_type_id` は bigint（NOT NULL）で「何かのID参照」を意図している可能性が高い。
- ただし候補の `record_types` は「個人の実績/履歴」寄りの実体であり、`pro_test`（受験者/受験情報）から参照する設計として不自然になる可能性が高い（命名ズレの疑い）。:contentReference[oaicite:4]{index=4}
- `pro_test` 自体が後回しフェーズであり、ここを今決め打ちすると手戻りが発生しやすい。

## Decision
- 現時点では `pro_test.record_type_id` の参照先を確定せず、FK も追加しない。
- `docs/db/refs_skipped.md` に保留として残し、ProTestフェーズで以下のどれかに確定する：
  - A) 受験種別マスタ（例: pro_test_record_types）を新設して参照
  - B) `record_type_id` をやめて `record_type`（文字列）で保持
  - C) 既存の別テーブルが本来の参照先（命名ズレ）であることを確認してそこへ参照

## Consequences
- ER（ER.dbml）上は Ref を引かない。
- ProTestフェーズ開始時に、最優先で「参照先の正体」を特定するタスクが発生する。
