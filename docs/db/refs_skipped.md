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

## 2026-04-03 instructors / certified input source（現存データ整理）

- 対象:
  - `認定インストラクター` の投入元整理

- 確認結果:
  - `OLD_JPBA/csv` 配下の現存CSVは `Pro_colum.csv` のみ
  - `Pro_colum.csv` はプロボウラー正本CSVであり、認定インストラクター専用の元表ではない
  - `OLD_JPBA` 配下で `authinstructor` / `AuthInstructor` の参照は確認できなかった
  - したがって、認定インストラクター専用の legacy 取込元は現存確認できていない

- 決定:
  - `authinstructor` を前提にした保留は解消する
  - 現存する投入元データは `Pro_colum.csv` のみとする
  - 認定インストラクターは現時点では `manual` 登録を正規の投入経路とする

- 対応方針:
  - `instructor_registry` の source は当面 `legacy_instructors` / `pro_bowler` / `manual` の3系統で運用する
  - 認定インストラクター専用の外部データが将来見つかった場合のみ、新しい source_type を追加検討する