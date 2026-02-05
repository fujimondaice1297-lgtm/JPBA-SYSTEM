# Skipped refs (needs decision)

- Generated at: 2026-02-05T00:00:00+09:00
- Notes: This file is curated. refs_missing.md is auto-detected.

- pro_bowlers.login_id (ADR-0002-defer-pro_bowlers-login_id)
- pro_test.record_type_id (ADR-0003-defer-pro_test-record_type_id)

## Pending decisions (skipped for now)

### pro_bowlers.login_id
- 状態: DB上でFKなし（参照先未確定）
- 理由: 旧システム由来の login 系マスタの実体（テーブル/仕様）が未確定のため決め打ちしない
- 対応方針:
  - login系の参照先（users なのか、別の login_accounts 的マスタなのか）を確定する
  - 確定後、FK追加 or 正規化（login系マスタ作成）を実施
  - それまでは「単なる文字列/識別子」として扱い、参照整合はアプリ側で担保する

### pro_test.record_type_id
- 状態: DB上でFKなし（参照先未確定）
- 理由: ProTest は後回しのため、record_types 等との関係を今は確定しない
- 対応方針:
  - ProTestフェーズで参照先（record_types など）を確定する
  - 確定後、FK追加（+ 必要なら正規化）を実施
