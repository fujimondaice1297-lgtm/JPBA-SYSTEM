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
- [✓] districts/sex のマスタがサイト表示と一致
- [✓] 検索条件（氏名/Noレンジ/地区/性別/退会者）が再現できる
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
- [✓] 現存する投入元データを確認
  - `Pro_colum.csv`：`pro_bowler` / `pro_instructor`
  - `AuthInstructor.csv`：`certified`
  - `manual`：画面からの手動登録・手動編集
- [✓] `license_no` 非依存で認定系も保持できる新正本 `instructor_registry` の方針を確定
- [ ] 既存 `instructors` / 画面 / Controller を `instructor_registry` ベースへ段階移行
- [✓] instructor_registry を新正本として導入
- [✓] /instructors 一覧・PDF を instructor_registry 読みへ切替
- [✓] pro_bowlers CSV再取込時に instructor_registry も同期する構成へ変更
- [✓] 認定インストラクター / プロインストラクターの投入経路を整理した
  - `pro_bowler_csv`：`Pro_colum.csv` 由来の `pro_bowler` / `pro_instructor`
  - `auth_instructor_csv`：`AuthInstructor.csv` 由来の `certified`
  - `manual`：画面からの手動登録・手動編集
  - `legacy_instructors`：旧 `instructors` bootstrap
- [✓] `pro_bowlers.member_class` / `can_enter_official_tournament` を導入し、競技者判定を文字列検索ではなく業務判定列へ寄せた
- [✓] `instructor_registry.is_current` / `source_registered_at` / `superseded_at` / `supersede_reason` を導入し、資格遷移の current/history を扱えるようにした
#### 2026-03-18 メモ（認定インストラクター手動登録導線）
- [✓] 認定インストラクターを手動登録できる
- [✓] 手動登録した認定インストラクターが `/instructors` 一覧に表示される
- [✓] 一覧の氏名リンクから編集画面へ遷移できる
- [✓] 編集更新後の変更が一覧へ反映される
- [✓] 認定インストラクター専用の元表は存在せず、現状は manual 登録が投入経路であることを確認
#### 2026-04-03 メモ（instructor_registry 正本化の棚卸し）
- [✓] `/instructors` 画面本体が `InstructorRegistry` 正本で動作していることを確認
- [✓] `GroupRuleEngine` の `instructor_grade` 判定を `instructor_registry` 基準へ寄せる
- [ ] `ProBowlerController` / `ProBowlerImportController` の `instructors` 更新は互換レイヤとして当面維持する方針を整理
- [✓] `authinstructor` 前提を外し、現存元データは `Pro_colum.csv` のみと整理
- [ ] `pro_bowler` / `manual` / `legacy_instructors` の役割分担を docs 上で最終整理する
#### 2026-04-03 メモ（ProBowlerController 同期整合）
- [✓] `ProBowlerController` の保存時にも `instructor_registry` を同期する
- [✓] `ProBowlerImportController` と `ProBowlerController` で、プロボウラー由来インストラクターの同期先を `instructors` + `instructor_registry` に揃える
- [ ] 資格解除時に既存 `instructors` / `instructor_registry` 行をどう扱うかは後続で整理する

#### 2026-04-05 メモ（AuthInstructor.csv 取込導線 + プロインストラクター整合）
- [✓] `AuthInstructor.csv` を `instructor_registry` へ取り込む導線（controller / route / view）を追加
- [✓] `source_type = auth_instructor_csv` / `instructor_category = certified` で認定インストラクターを投入できることを確認
- [✓] `Pro_colum.csv` 由来のティーチングプロ判定を `member_class = pro_instructor` 基準へ修正
  - `T015`
  - `M0000T015`
  - `F0000T004`
  のような教示系ライセンスを `pro_instructor` として扱う
- [✓] `/pro_bowlers` 側の件数確認を `license_no like '%T%'` ではなく `member_class = pro_instructor` 基準へ寄せた
- [✓] `pro_bowlers.member_class = pro_instructor` と `instructor_registry.instructor_category = pro_instructor` の件数が 23 / 23 で一致することを確認
- [ ] alias/旧ライセンス表記を current/history へどう寄せるかは後続で整理する


## Phase 2：大会（管理・公開の整合）
- [ ] tournaments 周辺の最終スキーマが辞書に確定
- [✓] 添付/動画/配信URL/サイドバー表示の構造が固まる
- [ ] 結果・表彰・ポイントが破綻なく集計できる
#### 2026-04-16 メモ（大会詳細 / 成績 / 配分 / 会場検索UIの整備）
- [✓] `tournaments.create` の大会詳細入力UIを `tournaments.edit` と同水準まで拡張した
- [✓] `tournaments.create` / `tournaments.edit` の会場検索で、会場マスタ検索 → 候補表示 → 選択反映が動作することを確認した
- [✓] venue API を `/api/venues/search` / `/api/venues/{id}` の1系統へ整理した
- [✓] 大会作成 → 詳細表示 → 成績一覧 の基本導線が通ることを確認した
- [✓] `ポイント配分` / `賞金配分` → `大会成績一覧` → `賞金・ポイント再計算` の運用導線を整備した
- [✓] `tournament_results` の新規登録 / 一括登録 / 編集で、配分済み順位の `points` / `prize_money` が保存時に自動反映される構成を確認した
- [✓] `タイトル反映` の冪等性を確認した（初回: 新規作成 / 再実行: 既存扱い）
- [✓] `tournaments.index` をカード型UIへ整理し、`詳細` / `成績一覧` / `ポイント配分` / `賞金配分` へ迷わず遷移できるようにした
- [✓] `tournament_results.create` / `batch_create` / `edit` のUIを整理し、補助導線と平均目安表示を追加した
- [ ] `tournament_awards` / `tournament_points` と `prize_distributions` / `point_distributions` の役割整理、および辞書・現物スキーマ整合は未完了
#### 2026-04-16 メモ（大会速報 / ライブスコア再整備の着手方針）
- [ ] 大会速報は **①スコア順位速報** を先行実装し、②ラウンドロビン / ③トーナメント / ④ダブルエリミネーション / ⑤シュートアウトは後続フェーズに分離する
- [ ] 速報の正本は `game_scores` を起点にし、`stage_settings` と大会ごとの速報設定で集計条件を切り替えられるようにする
- [ ] ①スコア順位速報では以下を扱う
  - [ ] スコア高順位
  - [ ] 同スコア時のタイブレーク（同大会内の過去ゲーム差）
  - [ ] ステージごとの通過人数
  - [ ] ステージごとの carry（持ち越し）有無
  - [ ] シフト別集計 / シフト合算 / 後から全体合算
  - [ ] 男女別集計 / 男女合算
- [ ] 公開速報はスマホ優先・軽量表示を前提に、管理UIとは分離した公開画面で運用する
- [ ] 速報から大会成績への反映は「自動即時」ではなく **反映ボタン方式** で行う
- [ ] 既存の `game_scores` / `stage_settings` / `速報ランキング(result)` の実装痕跡を棚卸ししてから、残すものと作り直すものを分ける



#### 2026-04-17 メモ（大会速報 / ライブスコア入力UI・結果表示の実装前進）
- [✓] `ScoreController` / `ScoreService` / `scores.input` / `scores.result` を中心に、速報入力→速報表示の基本導線を再整備した
- [✓] レイアウトから速報入力ページへ遷移できる導線を追加した
- [✓] 入力画面で大会 / ステージ / ゲーム番号 / シフト / 性別 / 識別方法を切り替えられるようにした
- [✓] 2ゲーム目以降の入力時に、同一選手の過去ゲーム点数と今回込合計をその場で確認できるようにした
- [✓] ライセンス番号入力時に照合情報（氏名 / ライセンス）を表示できるようにした
- [✓] 氏名入力時の候補表示を追加した
- [✓] 2ゲーム目以降の候補表示を「大会登録選手のみ」に絞り、性別指定も反映するようにした
- [✓] 速報表示でライセンス番号だけでなく氏名も解決表示できるようにした
- [✓] 速報表示側から `〇ゲーム目まで` を切り替えて再表示できる導線を追加した
- [✓] score input 内ではライセンス番号下4桁入力でも、過去点数参照・氏名照合・同一選手判定が通るように整理した
- [ ] `ScoreController.php` のローカル最新版とチャット添付版に差異があり、1000行問題の切り分けが未完了
- [ ] 速報表示で `3G → 2G → 3G` に戻れなくなるケースの最終修正は未完了
- [ ] `0099 古川` のように見えている行を削除できないケースの最終修正は未完了
- [ ] 下4桁ライセンス入力の共通化（速報以外の画面への横展開）は改善候補として後続へ送る


## Phase 3：ProTest（後回し）
- [ ] 要件整理
- [ ] スキーマ確定
- [ ] インポート/運用導線の設計

#### 2026-04-09 メモ（資格遷移検証 + 未結線認定の運用導線）
- [✓] 資格遷移の先回り検証用CSVを作成し、4パターンの動作確認を実施
  - ① 認定 → プロインストラクター / プロボウラー
  - ② 認定未更新 → `certified_not_renewed` + `expired`
  - ③ プロインストラクター → プロボウラー
  - ④ プロボウラー / プロインストラクター → 認定インストラクター
- [✓] `AuthInstructor.csv` は `license_no` 一致を最優先に `pro_bowlers` へ自動結線する
- [✓] `AuthInstructor.csv` で `license_no` が空、または一致しない場合は、`name_kanji` を含む複数条件で一意に特定できた場合のみ自動結線する
- [✓] `Pro_colum.csv` 取込時、プロ系資格対象外になった行は
  - 有効な `certified` 行があれば `downgraded_to_certified`
  - 復帰先が無ければ `qualification_removed`
  で履歴化する
- [✓] `/instructors` 一覧に `未結線認定` フィルタを追加
- [✓] `auth_instructor_csv` 由来の `certified` 行を編集画面から手動で `pro_bowlers` に結線できる
- [✓] `/instructors` 一覧で `結線先プロ` / `取込元` / `履歴理由` を確認できる
- [ ] 既存 `instructors` 互換レイヤの撤去可否は後続で整理する

#### 2026-04-10 メモ（取込導線・会員区分表示・大会エントリー運用の整備）
- [✓] `AuthInstructor.csv` 取込画面で、対象年度指定・取込結果サマリ・一覧導線を追加
- [✓] `Pro_colum.csv` 取込画面でも、取込結果サマリと `instructor_registry` 反映結果を一覧側で確認できるようにした
- [✓] `/pro_bowlers` 管理画面で `member_class` / `can_enter_official_tournament` / `current instructor sync` を表示できるようにした
- [✓] manual 由来インストラクターは物理削除せず、`retired/history` として履歴化する運用を追加した
- [✓] 会員向け大会エントリー画面を `member_class` / `can_enter_official_tournament` / `is_active` 基準で制御するようにした
- [✓] シフト抽選 / レーン抽選 / 大会使用ボール登録でも、本人確認 + エントリー有効 + 会員区分判定のサーバー側ガードを追加した
- [✓] 大会使用ボール登録画面を本実装し、`registered_balls -> used_balls` 同期・最大12個・仮登録表示に対応した
- [✓] `registered_balls` / `used_balls` の一覧・create/edit を、検量証番号 / 仮登録 / 有効期限運用に合わせて整備した
- [ ] `TournamentEntry` 後続（チェックイン / 当日運用 / 抽選結果公開）までは未着手
- [ ] `registered_balls` と `used_balls` の役割分担最終整理（統合可否を含む）は後続で整理する

#### 2026-04-11 メモ（大会エントリー後続 + waitlist）
- [✓] 管理者用の大会エントリー一覧を追加
- [✓] 参加選手向けの大会エントリー一覧を追加
- [✓] 管理者用 / 参加選手向けの抽選結果一覧を追加
- [✓] 管理者用の未抽選一覧（シフト / レーン未確定）を追加
- [✓] `tournament_entries` に waitlist 用カラムを追加
- [✓] 管理者から `waiting` 登録 / `entry` への繰り上げ導線を追加

#### 2026-04-18 メモ（大会速報 / ライブスコア：トータルピン集計 完了）
- [✓] `ScoreController` のローカル実ファイル基準で「1000行問題」を切り分け直した
- [✓] 2G以降の入力時に、下4桁ライセンス入力で過去点数参照・同一選手判定が通るよう整理した
- [✓] `scores.result` 側で `〇ゲーム目まで` の切替時に、`3G → 2G → 3G` で戻れるようにした
- [✓] サンプル速報データを大会単位でクリアし、混在データを仕切り直した
- [✓] 最小サンプルで 1G → 2G の再入力確認を行い、トータルピン入力継続が通ることを確認した
- [✓] `準々決勝` で `予選` の carry が反映されることを確認した
- [✓] `準決勝` で `予選 + 準々決勝` の carry が反映されることを確認した
- [✓] `決勝` で `予選 + 準々決勝 + 準決勝` の carry が反映されるよう修正した
- [✓] **大会速報（ライブスコア）のトータルピン集計は、現時点でコンプリート**
- [ ] ラウンドロビン / トーナメント / ダブルエリミネーション / シュートアウトの速報方式は後続
- [ ] 速報から正式成績への反映導線は後続
- [ ] 必要なら `docs/db` 正本整理（辞書 / ER / migration 要否切り分け）は後続