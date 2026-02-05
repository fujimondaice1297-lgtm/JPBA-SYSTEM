# ADR-0001: Defer foreign keys for pro_bowlers.login_id and pro_test.record_type_id

- Status: Accepted
- Date: 2026-02-05
- Related:
  - docs/db/refs_missing.md
  - docs/db/refs_skipped.md

## Context
DBカラムの *_id を走査した結果、ER（辞書由来）に反映されていない参照候補が検出された。
ただし以下の2項目は DB上にFKが存在せず、参照先テーブル/仕様が未確定である。

- pro_bowlers.login_id
- pro_test.record_type_id

本プロジェクトの方針として「不確定な参照は決め打ちしない」「辞書を正本として運用する」。

## Decision
上記2項目は、現時点ではFKを追加せず “参照未確定” として扱う。

- pro_bowlers.login_id:
  - login 系マスタ（参照先の実体）が確定するまで、単なる識別子として保持する
- pro_test.record_type_id:
  - ProTestフェーズで参照先（record_types 等）を確定してからFK化する

この判断は docs/db/refs_skipped.md に明記し、未確定参照の一覧として運用する。

## Consequences
- DBレベルの参照整合は当面かからないため、アプリ側で最低限の入力検証が必要（必要になった時点で実装）。
- 参照先が確定したタイミングで、以下のセット対応を行う：
  - migrations（FK追加 or 正規化）
  - docs/db/data_dictionary.md 更新（正本）
  - tools/generate_er_from_dictionary.php で ER 再生成（手編集しない）

## Follow-ups
- [ ] login_id の参照先仕様を確定する（旧システム調査）
- [ ] ProTest の設計フェーズで record_type_id の参照先を確定する
