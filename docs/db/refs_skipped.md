# Skipped refs (needs decision)

- Generated at: 2026-01-23T01:25:33+01:00

- pro_test.record_type_id

## Pending decisions (skipped for now)

### pro_bowlers.login_id
- 状態: DB上でFKなし（参照先未確定）
- 理由: 旧システム由来の login 系マスタの実体が未確定のため、決め打ちしない
- 対応方針: 候補テーブル/仕様が確定したタイミングでFK追加 or 別マスタに正規化

### pro_test.record_type_id
- 状態: DB上でFKなし（参照先未確定）
- 理由: ProTest は後回しのため、record_types との関係を今は確定しない
- 対応方針: ProTestフェーズで参照先を確定してFK追加
