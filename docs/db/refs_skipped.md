# Skipped refs / exceptions (curated)

- Generated at: 2026-02-05T00:00:00+09:00
- Notes: This file is curated. refs_missing.md is auto-detected.

## Naming exceptions (NOT foreign keys)

- pro_bowlers.login_id (varchar: legacy login identifier; NOT a FK)
- instructor_registry.legacy_instructor_license_no (varchar: 旧 instructors.license_no の退避列; NOT a FK)

### instructor_registry.legacy_instructor_license_no
- 決定: **FKにはしない**（互換移行用の退避列）
- 根拠:
  - `instructors` は当面互換レイヤとして残すが、新正本は `instructor_registry`
  - この列は旧主キーを保持するためのスナップショットであり、参照整合の正本ではない
- 対応方針:
  - 既存画面の移行完了までは文字列として保持
  - `instructors` 廃止時に削除可否を判断する

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

## 2026-03-12 instructors / authinstructor（legacy未接続のため保留）

- 対象:
  - `認定インストラクター` の投入元確認

- 候補:
  - `mysql_legacy.authinstructor`
  - 根拠: `App\Models\Legacy\AuthInstructorLegacy` が `authinstructor` を参照する想定になっている

- 現状:
  - `pdo_mysql` / `mysqli` は有効化済み
  - ただし `.env` に `DB_MYSQL_*` 設定が無い
  - MySQL / MariaDB サービスが見つからない
  - 3306 / 3307 に待受が無い
  - `authinstructor` 相当の SQL / CSV / Excel も未発見

- 判断:
  - `authinstructor` の実カラム未確認のため、`cert_no` や `source_key` の決め打ちは行わない
  - `認定インストラクター` の schema / import 設計は保留

- 再開条件:
  - legacy DB 接続情報の入手
  - または `authinstructor` 相当データの入手