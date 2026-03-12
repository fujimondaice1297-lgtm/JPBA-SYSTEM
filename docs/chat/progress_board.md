# progress_board（JPBA DB整備ロードマップ）

## Phase 0：整合の土台
- [✓] data_dictionary.md を正本として更新する運用が回っている
- [✓] generate_er_from_dictionary.php で ER.dbml が生成される（手編集しない）
- [✓] refs_missing / refs_skipped の運用が回っている（不明FKを決め打ちしない）
- [✓] 「同じ変更を複数migrationでやっていない」状態になっている（重複排除）
- [✓] docs/db/SCHEMA.sql（pg_dump -s）を“現物スキーマのスナップショット”として更新運用する
- [✓] docs/db/PREFLIGHT.md を「作業前チェック」として運用し、重複migration/存在しないカラム事故を防ぐ
- [✓] Codex（CLI）導入済み：作業開始時にリポジトリをスキャンさせてから変更する（前提共有の手貼りを減らす）
- [✓] SCHEMA.sql の現物スナップショット（pg_dump -s）を前提に、推測ではなく実スキーマ基準で作業する
- [✓] 作業開始前に PREFLIGHT.md を必ず実施し、既存migration重複と命名衝突を先に潰す
- [✓] Codexワークフロー（先読みによる前提共有＋最小差分編集）を標準手順として固定する
- [✓] SCHEMA.sql スナップショット確認を、DB変更作業の開始前・終了後の両方で実施する
- [✓] migration作業は必ず PREFLIGHT.md のチェックを通してから着手する
- [✓] Codex運用（リポジトリ先読みによる前提共有）を標準ワークフローとして固定する

## Phase 1：JPBAサイト踏襲（最優先）
### 1) INFORMATION
- [✓] informations のカテゴリ/公開/日付/本文が揃う
- [ ] カテゴリ値がサイト実態と一致（NEWS / イベント / 大会 / ｲﾝｽﾄﾗｸﾀｰ）
- [✓] information_files（1:N）が揃う（複数PDF対応）
- [✓] 一覧（ページネーション）と詳細が再現できる
- [✓] 管理（admin）CRUD（一覧/新規/編集/更新）が動作
- [ ] （任意）添付の表示用サイズ（KB等）を扱う方針が決まる

### 2) PLAYER DATA
- [✓] pro_bowlers のステータス（現役/退会等）が一意に決まる
- [ ] districts/sex のマスタがサイト表示と一致
- [ ] 検索条件（氏名/Noレンジ/地区/性別/退会者）が再現できる
- [✓] ライセンスNoの並び替え/レンジ検索のための設計が入る（文字混在考慮）
- [✓] 年度別成績サマリ（順位/ゲーム数/ピン/ポイント/AVG/賞金）の保存先が確定
#### 2026-03-10 メモ（pro_bowlers 再取込）
- [✓] 新CSV正本で `pro_bowlers` の地区・期別再取込を実施
- [✓] `district_id` 未反映を解消（`district_null = 0`）
- [✓] `T` ライセンス（ティーチングプロ）は `kibetsu = null` で統一
- [✓] 検索条件（氏名/Noレンジ/地区/性別/退会者）が再現できる
  - `/athletes` で 名前 / ライセンスNo / 地区 / 期別 / 性別 を確認
  - `/pro_bowlers/list?id_from=1&id_to=20` で Noレンジ検索を確認（41件）
  - `/pro_bowlers/list` は 1249件、`/pro_bowlers/list?include_inactive=1` は 2267件で、退会者を含む切替も確認
- [✓] districts / sexes マスタがサイト表示と一致
  - districts の `該当なし` 重複は解消済み（`id=27 / name=not_applicable / label=該当なし` に統一）
  - sexes は `0=不明 / 1=男性 / 2=女性` を確認済み

### 3) INSTRUCTOR
- [✓] 区分マスタ（A/B/C等）が確定
- [✓] 名簿表示に必要な項目が揃う
- [ ] 講習/資格/更新履歴の持ち方が決まる

## Phase 2：大会（管理・公開の整合）
- [ ] tournaments 周辺の最終スキーマが辞書に確定
- [ ] 添付/動画/配信URL/サイドバー表示の構造が固まる
- [ ] 結果・表彰・ポイントが破綻なく集計できる

## Phase 3：ProTest（後回し）
- [ ] 要件整理
- [ ] スキーマ確定
- [ ] インポート/運用導線の設計
