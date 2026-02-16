# Skipped refs / exceptions (curated)

- Generated at: 2026-02-05T00:00:00+09:00
- Notes: This file is curated. refs_missing.md is auto-detected.

## Naming exceptions (NOT foreign keys)

- pro_bowlers.login_id (varchar: legacy login identifier; NOT a FK)

### pro_bowlers.login_id
- 決定: **FKにはしない**（login_id は文字列）
- 根拠: migration で varchar(255)、アプリ側 validation も nullable|string|max:255
- 対応方針:
  - DB上は単なる識別子として保持（参照整合はアプリ側で担保）
  - 将来的に rename（legacy_login_id 等）/ 置換 / 廃止を検討

## Pending decisions (skipped for now)

- pro_test.record_type_id (ADR-0003-defer-pro_test-record_type_id)

### pro_test.record_type_id
- 状態: DB上でFKなし（参照先未確定）
- 理由: ProTest は後回しのため、record_types 等との関係を今は確定しない
- 対応方針:
  - ProTestフェーズで参照先（マスタ or 列挙）を確定する
  - 確定後、FK追加（+ 必要なら正規化）を実施
