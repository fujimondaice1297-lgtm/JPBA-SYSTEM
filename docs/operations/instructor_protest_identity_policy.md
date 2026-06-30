# インストラクター・ProTest・会員基盤の運用方針

作成日: 2026-06-30

このメモは、Active Backlog F の残項目をまとめて整理するための方針です。
目的は、インストラクター情報、旧ライセンス表記、資格更新、ProTest 申請・成績・合否導線を、後続の自動化で迷わない形に固定することです。

## 結論

- インストラクター情報の正本は `instructor_registry` とする。
- 旧 `instructors` は、既存画面・既存処理・帳票互換のため当面残す互換レイヤとする。
- 新規検索、公開一覧、資格更新、current/history 判定、ProTest 連携は `instructor_registry` を参照する。
- `ProBowlerController` / `ProBowlerImportController` が旧 `instructors` も更新する処理は、互換レイヤ同期として維持する。
- alias や旧ライセンス表記は、現在の本人情報を上書きする材料ではなく、`source_key` / `legacy_instructor_license_no` / `cert_no` / history 行として保持する。
- ProTest は `pro_test_*` テーブル群を受験・スコア・合否の正本候補とし、公開 `/protest` はDBと INFORMATION / 添付PDFを読む表示専用導線にする。

## 現状確認

- `InstructorController` は一覧、登録、編集、PDF出力の中心を `InstructorRegistry` に寄せている。
- `PublicInstructorController` は公開ライセンス別一覧で `instructor_registry` の current / active / visible 行を読む。
- `ProBowlerController` は管理画面保存時に `syncLegacyInstructor()` と `syncRegistryInstructor()` を両方実行する。
- `ProBowlerImportController` は `Pro_colum.csv` 取込時に `syncLegacyInstructorFromBowler()` と `syncRegistryFromBowler()` を両方実行する。
- `AuthInstructorImportController` は `AuthInstructor.csv` を `source_type = auth_instructor_csv` として `instructor_registry` へ取り込む。
- `InstructorRegistry` には `is_current`、`superseded_at`、`supersede_reason`、`renewal_year`、`renewal_due_on`、`renewal_status`、`renewed_at`、`renewal_note` が実装済み。
- `ProTest` の公開ページ `/protest` は `pro_test_schedule`、`calendar_events.kind = pro_test`、関連 INFORMATION を読む構成になっている。

## `instructors` の扱い

`instructors` は削除しない。
理由は、古い `license_no` 主キー前提の処理、既存帳票、過去データ確認が残る可能性が高いためです。

ただし、今後の新規実装で `instructors` を正本として読まないようにします。
読み分けは以下に固定します。

- 正本: `instructor_registry`
- 互換: `instructors`
- 旧移行元: `legacy_instructors`
- プロ系取込元: `pro_bowler_csv`
- 認定系取込元: `auth_instructor_csv`
- 管理者手入力: `manual`

`ProBowlerController` / `ProBowlerImportController` の `instructors` 更新は、互換レイヤを最新に保つための同期処理として残します。
新規の集計、公開表示、資格判定、更新期限判定は `instructor_registry` を見る方針です。

## 講習・資格・更新履歴

講習の受講履歴と、インストラクター資格そのものは分けて扱います。

- 講習・受講: `trainings` / `pro_bowler_trainings`
- インストラクター資格: `instructor_registry`
- 認定インストラクター年次更新: `AuthInstructor.csv` -> `AuthInstructorImportController` -> `instructor_registry`
- プロボウラー / プロインストラクター資格: `Pro_colum.csv` または管理画面 -> `pro_bowlers` -> `instructor_registry`

`instructor_registry` の current/history は以下で判定します。

- current 行: `is_current = true`
- history 行: `is_current = false`
- 置換日時: `superseded_at`
- 置換理由: `supersede_reason`
- 更新対象年度: `renewal_year`
- 更新期限: `renewal_due_on`
- 更新状態: `renewal_status`

資格解除や失効は削除ではなく履歴化します。
代表的な理由は以下です。

- `certified_not_renewed`: 認定インストラクターが当年更新されなかった
- `inactive_in_source`: 取込元CSV上で無効
- `qualification_removed`: プロ系資格が取込結果上で消滅し、復帰先の有効認定資格もない
- `promoted_to_pro_instructor`: 認定からプロインストラクターへ昇格
- `promoted_to_pro_bowler`: 認定またはプロインストラクターからプロボウラーへ昇格
- `downgraded_to_certified`: プロ系資格から認定インストラクターへ戻った

## alias / 旧ライセンス表記

旧ライセンス番号、認定番号、旧CSV上の番号、氏名揺れは、本人の現在値を安易に上書きしません。

照合の優先順位は以下に固定します。

1. `pro_bowler_id`
2. 正規化した現行 `license_no`
3. `cert_no`
4. `legacy_instructor_license_no`
5. 氏名 + カナ + 性別 + 地区などの複合候補

複合候補だけで一意に決まらない場合は、自動結線しません。
管理者確認後に `pro_bowler_id` を結線する運用にします。

旧表記を保存する場所は以下です。

- 取込元内の一意キー: `source_key`
- 旧 `instructors.license_no`: `legacy_instructor_license_no`
- 認定インストラクター番号: `cert_no`
- 現在のプロライセンス番号: `license_no`
- 由来や補足: `notes`

## ProTest の運用方針

ProTest は大会ではなく、プロ資格取得の申請・審査・実技スコア・合否管理として扱います。

既存の正本候補は以下です。

- `pro_test_category`: テスト種別
- `pro_test_venue`: 会場
- `pro_test_schedule`: 実施日程と申請期間
- `pro_test`: 受験者、申請、属性、合否状態
- `pro_test_score`: 実技スコア明細
- `pro_test_score_summary`: 合計、AVG、通過フラグ
- `pro_test_result_status`: 合否区分
- `pro_test_attachment`: 添付PDF / 申請書 / 結果資料
- `pro_test_comment`: 内部メモ
- `pro_test_status_log`: 申請状態・審査状態の履歴

公開ページの役割は以下です。

- `/protest`: 受験の流れ、実施概要、申請期間、講習会導線、関連INFORMATION、公開PDFを表示する。
- 公開ページは入力しない。入力・確認・合否更新は管理画面側に寄せる。
- 結果PDFや実施概要PDFは INFORMATION 添付、または将来 `pro_test_attachment` の public 添付として扱う。

管理側の理想フローは以下です。

1. 年度、カテゴリ、会場、日程、申請期間を登録する。
2. 受験者申請を `pro_test` に登録する。
3. 必要書類や申請PDFを `pro_test_attachment` に紐づける。
4. 実技スコアを `pro_test_score` に入力する。
5. 合計・AVG・通過判定を `pro_test_score_summary` に保持する。
6. 合否を `pro_test_result_status` に紐づける。
7. 変更履歴を `pro_test_status_log` に残す。
8. 公開結果PDFまたは INFORMATION 添付へ反映する。

合格後の連携は以下に固定します。

- 合格者を `pro_bowlers` へ登録または更新する。
- 必要に応じて `license_no`、期、地区、性別、氏名を確定する。
- 実技免除合格者やトップ合格者など、シード扱いが必要な場合は `ProBowlerSeedService` の `pro_test_practical_exempt` / `pro_test_top_passer` を使う。
- インストラクター資格を持つ場合は `instructor_registry` へ `source_type = manual` または将来の ProTest 由来 source として登録する。

## 未確定の保留点

`pro_test.record_type_id` は ADR-0003 の通り、まだ参照先を確定しません。
ProTest 管理画面を本格化する時点で、次のどれかに決めます。

- 受験種別マスタを新設する。
- `record_type_id` をやめて文字列の `record_type` にする。
- 旧DB上の本来の参照先を特定してそこへFKを張る。

この保留は、今回の運用方針を妨げません。
申請、実技スコア、合否、公開導線の整理は `pro_test_*` テーブル群で進められます。

## 次の実装候補

- ProTest 管理画面を作る前に `pro_test.record_type_id` の参照先を確定する。
- `pro_test` に現在不足している申請ステータス、受験番号、連絡先、公開可否、添付公開区分が必要か棚卸しする。
- `instructors` を直接読む古い処理を定期的に検索し、新規コードが `instructor_registry` へ寄っているか確認する。
- current/history 行の手動修正画面で、誤結線解除、再結線、旧番号確認を扱いやすくする。
