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

#### 2026-04-18 メモ（大会速報 → 正式成績反映単位の整理）
- [✓] 大会速報（ライブスコア）のトータルピン集計がコンプリートした前提を確認
- [✓] 次段の主題を「速報の見せ方」ではなく「正式成績へどう反映するか」の整理に切り替えた
- [✓] 速報入力の正本は `game_scores` とし、正式成績への反映は **反映ボタン方式** とする方針を固定した
- [✓] トータルピン方式で先に扱う公開粒度を整理した
  - 予選前半成績
  - 予選後半成績
  - 予選通算成績
  - 準々決勝成績
  - 準々決勝通算成績
  - 準決勝成績
  - 準決勝通算成績
  - 最終成績
- [ ] 正式成績の保存先を `tournament_results` に寄せるか、別保存先を追加するかは未決
- [ ] 反映時の順位 / ポイント / 賞金 / タイトル処理は未決
- [ ] ラウンドロビン / ステップラダー / トーナメント / ダブルエリミネーション / シュートアウトは後続

#### 2026-04-20 メモ（大会速報 → 正式成績反映 / 最終成績同期）
- [✓] `tournament_result_snapshots` / `tournament_result_snapshot_rows` を正本設計どおり追加した
- [✓] `tournament_result_snapshots.calculation_definition` により、公開単位ごとの集計条件を保持できるようにした
- [✓] `TournamentResultSnapshot` / `TournamentResultSnapshotRow` / `TournamentResultSnapshotService` を追加した
- [✓] 正式成績反映ページを追加し、トータルピン方式の反映ボタンを実装した
- [✓] `prelim_total` などの途中成績が snapshot として保存されることを確認した
- [✓] `final_total` を全体条件で反映したときだけ `tournament_results` に同期する構成を実装した
- [✓] 速報ページ / 大会成績一覧ページ / 正式成績反映ページの導線を追加した
- [✓] 下4桁ライセンス番号 + 性別で `pro_bowlers` を解決し、最終成績一覧で正式な選手名を表示できるようにした
- [✓] 大会成績一覧で、順位 / ポイント / トータルピン / G / アベレージ / 賞金 が確認できる状態まで到達した
- [✓] **トータルピン方式は、速報入力 → snapshot → 最終成績同期 まで完了**
- [ ] snapshot 専用の閲覧ページ（途中成績公開ページ）は未実装
- [ ] ラウンドロビン / ステップラダー / トーナメント / ダブルエリミネーション / シュートアウトは後続
- [ ] 最終成績同期後のタイトル反映 / ポイント再計算 / PDF導線の最終整合は後続確認

#### 2026-04-20 メモ（snapshot閲覧ページ / 途中成績導線）
- [✓] `tournaments/{tournament}/result-snapshots/{snapshot}` の詳細表示を追加した
- [✓] 正式成績反映ページから current snapshot へ直接遷移できるようにした
- [✓] snapshot 詳細画面で、予選 / 準々決勝 / 準決勝 / 決勝 / トータルピン / ゲーム数 / AVG の列表示を実装した
- [✓] `calculation_definition.source_sets` を基準に、snapshot ごとの stage/game 範囲だけを表示するよう修正した
- [✓] `予選通算成績` で準々決勝以降が混ざる問題を解消した
- [✓] snapshot 詳細画面のナビを
  - 現在の成績を見る
  - この成績の反映履歴
  に分離し、重複表示を解消した
- [✓] 集計定義ブロックを外し、日本語列中心の順位表表示へ整理した
- [✓] **snapshot 専用の閲覧ページ（途中成績公開ページ）は実装完了**
- [✓] ポイント再計算の整合確認
- [✓] タイトル反映の整合確認
- [✓] PDF出力の整合確認
- [ ] ラウンドロビン / ステップラダー / トーナメント / ダブルエリミネーション / シュートアウトは後続

#### 2026-04-21 メモ（大会成績後段処理の整合確認 / PDF日本語表示修正）
- [✓] 大会成績一覧の `賞金・ポイント再計算` が、`tournament_results` を正本に `point_distributions` / `prize_distributions` 参照で動作することを確認した
- [✓] 大会成績一覧の `タイトル反映` が、実導線上は `TournamentResultController` 側で動作し、優勝者の重複登録が起きないことを確認した
- [✓] `PDF出力` を大会別出力へ修正し、全大会一括PDFではなく当該大会だけを出力するよう整理した
- [✓] 大会成績PDFの日本語文字化けを修正し、見出し・列名・選手名が正常表示されることを確認した
- [✓] 大会成績PDFのライセンスNo表示を下4桁（末尾4文字）へ統一した
- [✓] **トータルピン方式は、速報入力 → snapshot反映 → 最終成績同期 → snapshot閲覧 → ポイント / タイトル / PDF確認 まで完了**
- [ ] 次はラウンドロビン方式の要件整理と実装着手へ進む
#### 2026-04-25 メモ（ラウンドロビン / 決勝ステップラダー / 正式成績反映 完了）
- [✓] `tournaments` にラウンドロビン進行用の設定を追加した
  - `result_flow_type`
  - `round_robin_qualifier_count`
  - `round_robin_win_bonus`
  - `round_robin_tie_bonus`
  - `round_robin_position_round_enabled`
- [✓] `tournaments.create` / `tournaments.edit` で、ラウンドロビン・決勝ステップラダー方式の大会進行設定を保存できるようにした
- [✓] `RoundRobinService` を追加し、ラウンドロビンの対戦表・W-L-T・Bonus・RR合計・通算ポイントを表示できるようにした
- [✓] `StepLadderService` を追加し、決勝ステップラダーの進出者・対戦結果・優勝者・準優勝・3位を判定できるようにした
- [✓] `scores/round_robin_result.blade.php` を追加し、ラウンドロビン最終成績にステップラダー進出者3名の写真枠・氏名・通過順位を表示できるようにした
- [✓] `scores/step_ladder_result.blade.php` を追加し、JPBAサンプルに近い決勝ステップラダー図を表示できるようにした
- [✓] ステップラダー図はCSS/SVGでの完全再現が不安定だったため、`public/images/step_ladder_tournament_bracket_template.png` を背景テンプレートとして使い、その上に氏名・スコア・勝者ルートを重ねる方式へ切り替えた
- [✓] 大会専用写真の登録導線を追加した
  - `TournamentPhotoController`
  - `routes/web.php` の大会写真登録ルート
  - ラウンドロビン進出者カードから大会専用写真を登録可能
  - 登録写真はラウンドロビン画面と決勝ステップラダー画面で共通利用
- [✓] 決勝ステップラダーでは、勝者のスコアを赤、敗者のスコアを黒で表示し、勝者側の線だけ赤で進出表示するようにした
- [✓] 決勝ステップラダーのレイアウトは、名前・点数・線が重ならない形まで調整済み
- [✓] 決勝ステップラダーのシード選手について、前ゲームが無くても速報入力を受け付けられるようにした
- [✓] `TournamentResultSnapshotController` の正式成績反映処理を拡張し、ラウンドロビン / 決勝ステップラダー後の最終順位を `tournament_results` へ同期できるようにした
- [✓] 最終成績は上位3名だけでなく、予選・ラウンドロビンを含む全選手を `tournament_results` に反映するようにした
- [✓] アマチュア選手・ライセンス番号が無い選手は、プロフィール反映対象にせず、`amateur_name` を表示名として保持する方針にした
- [✓] 上位3名の `total_pin` はステップラダー単体スコアではなく、予選・ラウンドロビン・ステップラダーを含む通算トータルピンとして反映するようにした
- [✓] `TournamentResult` モデルに `amateur_name` を保存対象として追加した
- [✓] 反映確認結果
  - `snapshot_id=31`
  - `snapshot_rows=8`
  - `tournament_results=8`
  - 1位: 山田幸 / F00000524 / total_pin=3445 / games=14 / avg=246.07
  - 2位: 三浦美里 / F00000520 / total_pin=3186 / games=13 / avg=245.08
  - 3位: 桑藤美樹 / F00000494 / total_pin=3117 / games=13 / avg=239.77
  - 7位・8位のアマチュア選手は `amateur_name=RRテストA / RRテストB` として保持確認済み
- [✓] 以下2コミットを `main` へ push 済み
  - `dfb1fad` `feat: ラウンドロビンと決勝ステップラダーの正式成績反映を追加`
  - `de607e2` `fix: 正式成績反映と大会成績表示を調整`
- [✓] ローカルに残っている `public/tournament_images/` は、テスト登録した大会写真の実ファイルであり、Git管理外。不要なら削除する
- [ ] 次チャット開始時は、まず `git status -sb` で `public/tournament_images/` が残っているか確認する
- [✓] 次の自然な後続としてトーナメント方式（シングルエリミネーション）へ着手し、速報入力・正式成績反映・PDF確認まで完了
- [ ] 次の自然な後続は、ダブルエリミネーション / シュートアウト方式の速報・正式成績反映設計

#### 2026-04-27 メモ（トーナメント方式 / シングルエリミネーション実装完了）
- [✓] `tournaments` に single elimination 用設定カラムを追加した
  - `single_elimination_qualifier_count`
  - `single_elimination_seed_source_result_code`
  - `single_elimination_seed_policy`
  - `single_elimination_seed_settings`
- [✓] 成績持ち込み設定を追加した
  - `result_carry_preset`
  - `result_carry_settings`
- [✓] `docs/db/data_dictionary.md` に single elimination 用設定・成績持ち込み設定・運用方針を追記した
- [✓] `docs/db/ER.dbml` を辞書から再生成した
- [✓] `SingleEliminationService` を追加し、可変人数ブラケットを生成できるようにした
  - 14人進出時は16枠 / 4ラウンド / BYE 2件として生成できることを確認
  - 8人 / 16人 / 24人 / 32人など、次の2の累乗枠を基準にラウンド数を算出する方針で実装
- [✓] 大会作成 / 編集画面でトーナメント進出人数・進出元成績・シード設定を保存できるようにした
- [✓] 成績持ち込み設定は、コードを書けない運用者でも扱えるようにプリセット選択を基本にした
  - 予選→準々決勝までは持ち込み、準決勝からリセット
  - 予選から準々決勝へは持ち込まない
  - 予選→準々決勝→準決勝までは持ち込み、ラウンドロビンからリセット
  - 予選＋準決勝の通算でトーナメント進出者を決定
  - カスタムJSON
- [✓] `scores/single_elimination_result.blade.php` を追加し、トーナメント表を表示できるようにした
- [✓] トーナメント表上で試合ごとのスコア入力・保存・勝者表示ができるようにした
- [✓] 勝者が次ラウンドへ自動反映されることを確認した
- [✓] シード / BYE がある場合、前ラウンド未入力でも該当選手が次ラウンドへ進めることを確認した
- [✓] 男子ライセンス番号の下4桁表示・照合が誤って下2桁扱いになる問題を修正した
- [✓] `game_scores.stage = トーナメント`、`entry_number = SE:Rn-Mn:A/B` 形式でトーナメント試合スコアを保持する方針を確認した
- [✓] 14人トーナメントで実動確認を行った
  - 1回戦 6試合 = 12行
  - 準々決勝 4試合 = 8行
  - 準決勝 2試合 = 4行
  - 決勝 1試合 = 2行
  - 合計 26行
- [✓] BBBカップで決勝まで入力し、優勝者が確定することを確認した
  - 優勝: 藤川大輔 / `M00001297`
  - 準優勝: 髙田浩規 / `M00001288`
  - 決勝スコア: 藤川大輔 300 - 298 髙田浩規
- [✓] 正式成績反映ページから `single_elimination_final` を作成できるようにした
- [✓] `single_elimination_final` を `tournament_results` へ同期できるようにした
- [✓] 同じラウンドで負けた選手を同順位タイとして保存できることを確認した
  - 1位: 藤川大輔
  - 2位: 髙田浩規
  - 3位タイ: 宮澤拓哉 / 藤井信人
  - 5位タイ: 谷合貴志 / 御手洗彰彦 / 斉藤祐哉 / 甘糟翔太
  - 9位タイ: 門川健一 / 神山匠 / 吉山将太 / 山迫 耕太 / 小林龍一 / 佐藤匡
- [✓] `single_elimination_final` 反映後、`tournament_results` に14名が同期されることを確認した
- [✓] 大会成績一覧PDFを調整した
  - 大会別PDF `/tournaments/{tournament}/results/pdf` では対象大会だけを出力
  - 全体PDF `/tournament_results/pdf` は全大会一覧として維持
  - ライセンスNoの右に `期` 列を追加
  - `¥10,000,000` などの賞金欄が折り返されないように調整
- [✓] BBBカップ成績一覧画面の `PDF出力` ボタンを、大会別PDFルートへ修正した
- [✓] 確認済みコマンド
  - `php -l app/Http/Controllers/TournamentResultController.php`
  - `php -l app/Http/Controllers/TournamentResultSnapshotController.php`
  - `php -l app/Services/SingleEliminationService.php`
  - `php artisan view:cache`
  - `php artisan view:clear`
  - `php artisan optimize:clear`
- [✓] 最終状態は `git status -sb` が `## main...origin/main`
- [✓] 最終確認HEADは `a03493d`
- [ ] 次の自然な後続は、ダブルエリミネーション方式の速報・正式成績反映設計
- [ ] その次の候補は、シュートアウト方式の速報・正式成績反映設計
- [ ] 将来的に必要なら、トーナメントの固定ブラケット保存用に `tournament_bracket_matches` のような専用テーブル追加を検討する
