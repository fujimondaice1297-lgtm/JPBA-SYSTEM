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
  - `Pro_colum.csv` はプロボウラー正本CSVであり、`pro_bowler` / `pro_instructor` 同期の投入元として使う
  - `AuthInstructor.csv` は認定インストラクター専用CSVであり、`certified` 同期の投入元として使う
  - `AuthInstructor.csv` には専用の認定番号列や登録年月日列が無く、`#ID` が安定した一意キーになる
  - `AuthInstructor.csv` はライセンス番号を原則持たないため、名前一致だけで `pro_bowlers` に自動結線しない

- 決定:
  - `authinstructor` を前提にした保留は解消する
  - 現存する投入元データは `Pro_colum.csv` と `AuthInstructor.csv` の2系統とする
  - `instructor_registry` の source は当面 `legacy_instructors` / `pro_bowler_csv` / `auth_instructor_csv` / `manual` の4系統で運用する
  - `AuthInstructor.csv` 由来の認定インストラクターは `source_type = auth_instructor_csv` / `instructor_category = certified` で投入する
  - `AuthInstructor.csv` では `#ID` を `source_key` とし、当面は同じ値を `cert_no` に入れて一意管理する

- 対応方針:
  - `Pro_colum.csv` 由来の `pro_bowler` / `pro_instructor` は `license_issue_date` を `source_registered_at` に使う
  - `AuthInstructor.csv` 由来の `certified` は `source_registered_at = null` とし、current/history の自動切替は行わない
  - `AuthInstructor.csv` と `pro_bowlers` の同一人物判定は、別途 review 導線で手動確認する

## 2026-04-04 instructor current/history policy（資格遷移の扱い）

- 対象:
  - `pro_bowler` / `pro_instructor` / `certified` 間の遷移
  - 認定インストラクター→プロボウラー兼インストラクター
  - 認定インストラクター→プロインストラクター
  - プロボウラー兼インストラクター→認定インストラクター

- 決定:
  - 同一人物の旧資格行は物理削除しない
  - `instructor_registry.is_current = false` と `superseded_at` / `supersede_reason` で履歴化する
  - 一覧・検索の既定は `is_current = true`
  - current 行だけ partial unique index で重複を防ぐ

- 日付優先ルール:
  - 原則は `source_registered_at` が新しい行を current とする
  - 同日または日付欠落時は自動昇格・自動降格を行わず、要確認扱いにする
  - `AuthInstructor.csv` は登録年月日を持たないため、名前一致だけで `pro_bowlers` と自動結線しない

- 対応方針:
  - `Pro_colum.csv` 由来の `pro_bowler` / `pro_instructor` は `license_issue_date` を `source_registered_at` に入れる
  - `AuthInstructor.csv` 由来の `certified` は独立投入し、別途 review 導線で同一人物確認を行う


## 2026-04-05 teaching-pro classification / list-count policy（プロインストラクター判定方針）

- 対象:
  - `Pro_colum.csv` 由来のティーチングプロ判定
  - `/pro_bowlers` と `/instructors` の件数比較条件

- 決定:
  - 教示系ライセンスの正式判定は `license_no like '%T%'` のような文字列検索ではなく、`pro_bowlers.member_class = 'pro_instructor'` を正とする
  - `instructor_registry` 側の正式判定は `instructor_category = 'pro_instructor'` かつ `is_current = true` を正とする
  - 比較対象を揃えるため、プロインストラクター件数の確認は
    - `pro_bowlers.member_class = 'pro_instructor'`
    - `instructor_registry.instructor_category = 'pro_instructor' AND is_current = true`
    で行う

- 判定対象の例:
  - `T015`
  - `M0000T015`
  - `F0000T004`

- 対応方針:
  - importer / 保存処理で `member_class = 'pro_instructor'` と `can_enter_official_tournament = false` を維持する
  - `instructor_registry` は `member_class` を見て `pro_bowler` / `pro_instructor` を同期する
  - 旧データに対しては backfill で `instructor_registry.instructor_category = 'pro_instructor'` を補正する

## 2026-04-09 instructors / certified linking policy update（2026-04-03 / 2026-04-04 メモの追補）

- 対象:
  - `AuthInstructor.csv` と `pro_bowlers` の同一人物結線方針
  - `certified` / `pro_instructor` / `pro_bowler` 間の資格遷移
  - 自動結線できなかった認定インストラクターの運用導線

- 決定:
  - `AuthInstructor.csv` は `license_no` 一致を最優先に `pro_bowlers` へ自動結線する。
  - `license_no` が空、または `license_no` で結線できない場合は、`name_kanji` を含む複数条件（例: `name_kana` / `sex` / `district_id`）で **一意に特定できた場合のみ** `pro_bowlers` へ自動結線する。
  - 名前だけ、または名前を含まない曖昧条件では `pro_bowlers` に自動結線しない。
  - `auth_instructor_csv` 由来の `certified` 行に current な `pro_bowler` / `pro_instructor` 行が見つかった場合は、
    - `promoted_to_pro_bowler`
    - `promoted_to_pro_instructor`
    のいずれかで履歴化する。
  - `Pro_colum.csv` 取込時にプロ系資格対象外になった行は、
    - 対応する有効な `certified` 行がある場合は `downgraded_to_certified`
    - 復帰先の有効な `certified` 行が無い場合は `qualification_removed`
    で履歴化する。
  - 自動結線できなかった認定行は、`/instructors` の `未結線認定` フィルタで洗い出し、編集画面から手動結線する。

- 補足:
  - 2026-04-03 / 2026-04-04 時点の
    - 「名前一致だけで `pro_bowlers` に自動結線しない」
    - 「review 導線で手動確認する」
    という方針メモは、**現在はそのままの運用ではない**。
  - 現行実装は
    - `license_no` 優先
    - `name_kanji` を含む複数条件での一意一致のみ許容
    - 未結線は一覧フィルタ + 手動結線
    という形で運用する。