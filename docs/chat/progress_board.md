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
- [✓] information_files（1:N）が揃う（複数PDF対応）
- [✓] 一覧（ページネーション）と詳細が再現できる
- [✓] 管理（admin）CRUD（一覧/新規/編集/更新）が動作

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
- [✓] 現存する投入元データを確認
  - `Pro_colum.csv`：`pro_bowler` / `pro_instructor`
  - `AuthInstructor.csv`：`certified`
  - `manual`：画面からの手動登録・手動編集
- [✓] `license_no` 非依存で認定系も保持できる新正本 `instructor_registry` の方針を確定
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
- [✓] `authinstructor` 前提を外し、現存元データは `Pro_colum.csv` のみと整理
#### 2026-04-03 メモ（ProBowlerController 同期整合）
- [✓] `ProBowlerController` の保存時にも `instructor_registry` を同期する
- [✓] `ProBowlerImportController` と `ProBowlerController` で、プロボウラー由来インストラクターの同期先を `instructors` + `instructor_registry` に揃える

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


## Phase 2：大会（管理・公開の整合）
- [✓] 添付/動画/配信URL/サイドバー表示の構造が固まる
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
#### 2026-04-16 メモ（大会速報 / ライブスコア再整備の着手方針）



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
- [✓] 14人進出時は16枠 / 4ラウンド / BYE 2件として生成できることを確認した
- [✓] 大会作成 / 編集画面でトーナメント進出人数・進出元成績・シード設定を保存できるようにした
- [✓] 成績持ち込み設定は、コードを書けない運用者でも扱えるようにプリセット選択を基本にした
- [✓] `scores/single_elimination_result.blade.php` を追加し、トーナメント表を表示できるようにした
- [✓] トーナメント表上で試合ごとのスコア入力・保存・勝者表示ができるようにした
- [✓] 勝者が次ラウンドへ自動反映されることを確認した
- [✓] シード / BYE がある場合、前ラウンド未入力でも該当選手が次ラウンドへ進めることを確認した
- [✓] 男子ライセンス番号の下4桁表示・照合が誤って下2桁扱いになる問題を修正した
- [✓] `game_scores.stage = トーナメント`、`entry_number = SE:Rn-Mn:A/B` 形式でトーナメント試合スコアを保持する方針を確認した
- [✓] BBBカップで14人トーナメントを実動確認した
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
- [✓] 大会成績一覧PDFを調整した
  - 大会別PDF `/tournaments/{tournament}/results/pdf` では対象大会だけを出力
  - 全体PDF `/tournament_results/pdf` は全大会一覧として維持
  - ライセンスNoの右に `期` 列を追加
  - `¥10,000,000` などの賞金欄が折り返されないように調整
- [✓] BBBカップ成績一覧画面の `PDF出力` ボタンを、大会別PDFルートへ修正した
- [✓] 最終状態は `git status -sb` が `## main...origin/main`
- [✓] 最終確認HEADは `a03493d`
- [✓] 次の自然な後続としてシュートアウト方式へ着手し、速報入力・正式成績反映・PDF確認まで完了

#### 2026-04-28 メモ（シュートアウト方式 / 速報入力・正式成績反映・PDF整合 完了）
- [✓] `tournaments` にシュートアウト用設定カラムを追加した
  - `shootout_qualifier_count`
  - `shootout_seed_source_result_code`
  - `shootout_format`
  - `shootout_settings`
- [✓] `result_flow_type` にシュートアウト用フローを追加した
  - `prelim_to_shootout_to_final`
  - `prelim_to_quarterfinal_to_shootout_to_final`
  - `prelim_to_semifinal_to_shootout_to_final`
- [✓] `docs/db/data_dictionary.md` にシュートアウト方式の運用方針を追記した
- [✓] `docs/db/ER.dbml` を辞書から再生成した
- [✓] `ShootoutService` を追加し、標準8名シュートアウトを構築できるようにした
- [✓] `scores/shootout_result.blade.php` を追加し、進出者seed一覧 / 1stマッチ / 2ndマッチ / 優勝決定戦を表示できるようにした
- [✓] シュートアウト各マッチのスコア入力・保存・勝者表示を実装した
- [✓] 1stマッチ勝者が2ndマッチへ、2ndマッチ勝者が優勝決定戦へ自動表示されることを確認した
- [✓] 敗退者順位は当該マッチのスコア順ではなく、敗退ラウンドと元seed順で決定する方針を実装した
- [✓] `TournamentResultSnapshotController` に `shootout_final` 反映を追加した
- [✓] 正式成績反映ページに `シュートアウト最終成績 / shootout_final` ボタンを追加した
- [✓] `shootout_final` を `tournament_result_snapshots` / `tournament_result_snapshot_rows` に保存し、`is_final = true` として `tournament_results` に同期できることを確認した
- [✓] CCCカップで実動確認を行った
  - 予選4G + 準決勝2Gの6G通算から8名をseed化
  - 1stマッチ / 2ndマッチ / 優勝決定戦を入力
  - 優勝: 鈴木洋子 / 準優勝: 志水薫
- [✓] `shootout_final` 反映後、最終順位8名が `tournament_results` に同期されることを確認した
  - 1位: 鈴木洋子 / F00000007 / total_pin=2176 / games=9 / avg=241.78
  - 2位: 志水薫 / F0000 / total_pin=1708 / games=7 / avg=244.00
  - 3位: 石井利枝 / F00000003 / total_pin=1694 / games=7 / avg=242.00
  - 4位: 須田開代子 / F00000001 / total_pin=1698 / games=7 / avg=242.57
  - 5位: 並木惠美子 / F00000005 / total_pin=1684 / games=7 / avg=240.57
  - 6位: 海野房枝 / F00000004 / total_pin=1670 / games=7 / avg=238.57
  - 7位: 中山律子 / F00000002 / total_pin=1673 / games=7 / avg=239.00
  - 8位: 岩松八重子 / F00000006 / total_pin=1670 / games=7 / avg=238.57
- [✓] 大会成績PDFでCCCカップのみ出力されることを確認した
- [✓] `resources/views/tournament_results/pdf.blade.php` の列順を調整し、順位列を一番左に移動した
- [✓] PDFの `期` 列は維持した
- [✓] シュートアウトの公式PDF風の図式表示（線でつながるトーナメント図）はテンプレート画像重ね方式で実装した
- [✓] 公式PDF下部のスコアシート風表示は、2026-05-02〜2026-05-05 の対応でPDF掲載まで実装済み
- [✓] 以下ファイルを含むシュートアウト実装一式を commit / push 済み
  - `app/Http/Controllers/ScoreController.php`
  - `app/Http/Controllers/TournamentResultSnapshotController.php`
  - `app/Services/ShootoutService.php`
  - `resources/views/scores/shootout_result.blade.php`
  - `resources/views/tournament_result_snapshots/index.blade.php`
  - `resources/views/tournament_results/pdf.blade.php`
  - `routes/web.php`
- [✓] 最終状態は `git status -sb` が `## main...origin/main`

#### 2026-04-30 メモ（シュートアウト方式 / 公式PDF風トーナメント図表示）
- [✓] `public/images/shootout_tournament_bracket_template.png` を公式PDF風シュートアウト図の背景テンプレートとして配置した
- [✓] `resources/views/scores/shootout_result.blade.php` をテンプレート画像重ね方式へ調整した
- [✓] 標準8名シュートアウトは人数固定として扱い、フォント・配置・図サイズが人数で変わらない前提にした
- [✓] 通過順位、選手名、期、スコア、優勝者、最終順位をテンプレート上へ重ねて表示できるようにした
- [✓] `pro_bowlers.kibetsu` を使い、通過順位の右側へ `(n期生)` を表示できるようにした
- [✓] 優勝者枠に選手名とフリガナを表示できるようにした
- [✓] 左側の最終順位欄に `最終順位` 見出しを追加し、各順位ラベルを選手枠の中央へ揃えた
- [✓] 細線の対戦表はテンプレート側を正とし、勝ち上がりルートのみ太線を重ねる方式に整理した
- [✓] 勝ち上がりルートの太線は、現在の固定データではなく、各マッチの勝者seedから動的に決まる仕様にした
  - 例: 今回は鈴木洋子が優勝したため鈴木洋子の道筋が太線
  - 別結果で並木惠美子が勝てば、並木惠美子の勝ち上がり道筋が太線になる
- [✓] 勝ち上がった選手のスコアだけを赤文字・太字・少し大きめで表示するようにした
- [✓] CCCカップの画面表示で、鈴木洋子の勝ち上がりルートと `279 / 249 / 258` の勝者スコアが強調表示されることを確認した
- [✓] 公式PDF風の「線でつながるシュートアウト図」は実装完了扱い
- [✓] 公式PDF下部にあるゲーム別スコアシート風表示は、2026-05-02〜2026-05-05 の対応でPDF掲載まで実装済み
- [✓] 図式表示調整は後続のシュートアウトPDF整備とあわせて commit / push 済み

#### 2026-05-24 メモ（フォワードテスト開始・レーン移動ルール/公式風レーン移動表追加）
- [✓] フォワードテスト用に、空の大会運用データから実大会登録を開始した
- [✓] 対象大会として `ＪＰＢＡシーズントライアル ２０２５ オータムシリーズ　Ｃ会場：アソビックスあさひ` を作成した
  - `tournament_id = 10`
  - `title_category = season_trial`
  - `start_date = 2025-10-02`
  - `end_date = 2025-10-02`
- [✓] 大会作成 / 編集画面のレーン抽選入力を修正した
  - 使用レーン開始 / 終了を入力可能にした
  - BOX人数 / 奇数レーン人数 / 偶数レーン人数の入力を改善した
- [✓] 申込開始日時 / 申込締切日時で時刻を入力できるようにした
- [✓] シーズントライアル進行設定が編集画面で消える問題を修正した
- [✓] ポイント配分画面に、人数指定で最下位1ptから上位へ1ptずつ増える自動入力を追加した
- [✓] ポイント配分 / 賞金配分で、数値が入っている行だけ保存対象にする方針へ改善した
- [✓] `tournament_results.award_points` / `step_points` が既存列として存在することを確認し、シーズントライアルの入賞ポイントはDB追加なしで自動付与方針にした
- [✓] 公式レーン表をもとに、C会場64名を `tournament_entries` / `tournament_participants` へ登録した
  - `pro_bowlers.license_no_num + sex=1` で照合
  - `tournament_entries`: 64件
  - `tournament_participants`: 64件
- [✓] `storage/backups/import_st_autumn_2025_c_entries.php` を一時投入スクリプトとして作成した
  - `storage/backups/` はローカル作業用であり、Gitコミット対象外
- [✓] 全大会共通のレーン移動ルールとして `tournaments.lane_movement_settings` を追加した
  - 新規migration追加
  - `docs/db/data_dictionary.md` 更新
  - `docs/db/ER.dbml` 再生成
- [✓] `TournamentLaneMovementService` を追加し、スタートレーンから1G目〜対象ゲーム数までの移動先BOXを自動計算できるようにした
- [✓] 通常移動BOX数、移動方向、後半開始時だけ別移動、使用レーン内循環に対応した
- [✓] `lane_movement_settings.start_time` を追加し、1G目開始時刻からゲーム進行予定時間を自動表示できるようにした
  - BOX3名: +27分
  - BOX4名: +32分
  - BOX5名: +38分
  - BOX6名: +50分
- [✓] `resources/views/tournament_entries/lane_movement_table.blade.php` を追加し、公式PDF風の予選8Gレーン移動表を表示できるようにした
- [✓] レーン移動表をA4縦印刷向けに調整した
  - 1ページ36名 = 9BOX
  - 4G / 5G間の太線
  - 同一BOXのゲーム欄を縦結合
  - 氏名中央揃え
  - 姓名スペースの表記調整
  - ゲーム進行予定時間を各ゲーム列の真上へ配置
- [✓] エントリー一覧 / 抽選一覧からレーン移動表へ遷移できるボタンを追加した
- [✓] 確認済みコマンド
  - `php -l app/Http/Controllers/PointDistributionController.php`
  - `php -l app/Http/Controllers/PrizeDistributionController.php`
  - `php -l app/Http/Controllers/TournamentController.php`
  - `php -l app/Http/Controllers/TournamentEntryAdminController.php`
  - `php -l app/Http/Controllers/TournamentResultController.php`
  - `php -l app/Models/TournamentResult.php`
  - `php -l app/Services/TournamentLaneMovementService.php`
  - `php artisan migrate`
  - `php tools/generate_er_from_dictionary.php`
  - `php artisan view:clear`
  - `php artisan optimize:clear`
  - ボール登録確認
  - 予選スコア入力
  - 予選結果反映
  - 準決勝進出者確認
  - 準決勝スコア入力
  - シュートアウト
  - 最終成績
  - PDF確認


#### 2026-05-25 メモ（THE OPEN公式戦フォワードテスト・アマ対応・2日間レーン移動表）
- [✓] シーズントライアル完了後、通常公式戦として `大岡産業レディース［THE OPEN］トーナメント ２０２５` を作成した
  - `tournament_id = 11`
  - `gender = F`
  - `title_category = normal`
  - `result_flow_type = prelim_to_rr_to_final`
  - `start_date = 2025-07-25`
  - `end_date = 2025-07-27`
- [✓] アマチュア選手をプロボウラープロフィールへ恒久登録せず、一時参加者として扱う方針を整理した
- [✓] アマチュア選手のライセンス欄は `アマ` と表示する方針にした
- [✓] 一時参加者対応として、`tournament_participants` / `game_scores` を拡張するmigration方針を追加した
  - `database/migrations/2025_09_01_000089_add_temporary_participants_to_tournament_participants_and_game_scores.php`
  - `docs/db/data_dictionary.md`
  - `docs/db/ER.dbml`
- [✓] 既存長尺ファイルを短縮版で上書きしない方針を再確認した
  - `lane_movement_table.blade.php` はローカル実体601行を基準にした
- [✓] エントリー一覧で、レーン欄を `3L-1` などのレーンラベル表示へ変更した
- [✓] シフトがない大会では、シフト欄を `予選` ではなく `なし` と表示するようにした
- [✓] THE OPEN公式表に合わせ、レーン移動表を2日間ブロック表示へ拡張した
  - 1日目: `7/25（金） 予選前半8G`
  - 2日目: `7/26（土） 予選後半8G`
  - 対象ゲーム数: 16
  - 1G目開始時刻: 11:45
  - 2日目開始G: 9
  - 2日目開始時刻: 10:45
  - 2日目開始BOX補正: 8
  - 5G / 13G 開始時のみ別移動: 1BOX
- [✓] 2日目設定を保存後、編集画面に戻っても値が再表示されるように修正した
- [✓] レーン移動表の取得元を `tournament_participants` 優先へ変更し、アマチュア参加者も表示できるようにした
- [✓] THE OPENのアマチュア5名がレーン移動表へ表示されることを確認した
  - `7L-3` 坂本真貴子
  - `13L-3` 藤林　華音
  - `19L-3` 野村　緋那
  - `25L-3` 戸塚　知菜
  - `31L-3` 中村　華世
- [✓] プロ氏名の姓名スペース位置を、文字数だけでなく公式表記に合わせて補正する方針へ修正した
- [✓] `/tournaments/11/lane-movement-table` で、1ページ目の公式風レーン移動表表示を確認した
- [✓] 確認済みコマンド
  - `php -l app/Http/Controllers/TournamentController.php`
  - `php -l app/Http/Controllers/TournamentEntryAdminController.php`
  - `php -l app/Services/TournamentLaneMovementService.php`
  - `php artisan view:clear`
  - `php artisan optimize:clear`
  - `git diff --name-only`
  - 予選前半8G
  - 予選後半8G
  - 予選16G通算ランキング
  - ラウンドロビン進出者確認
  - ラウンドロビン入力
  - 決勝ステップラダー入力
  - 最終成績反映
  - PDF確認

#### 2026-06-05 メモ（THE OPEN予選16G・準決勝20G・ラウンドロビン表示調整）
- [✓] THE OPENの予選第3シリーズ9G〜12Gを投入した
  - 対象: `tournament_id = 11`
  - stage: `予選`
  - shift: `第3シリーズ`
  - score行数: 360件
  - 9〜12G総ピン: 71,113
  - 予選1〜12G総ピン: 216,407
- [✓] 9〜12G投入時に、アマチュアが誤紐づけ・合算される事故を再確認した
  - 原因は `entry_number` / `license_number = アマ` / 氏名照合の扱いが弱く、アマチュア固有の識別が不足していたこと
  - 修復スクリプトでアマチュア第3シリーズ20行を再投入し、5名が別々に12G集計されることを確認した
- [✓] 再発防止として、アマチュア識別番号とスコア誤紐づけガードを追加した
  - アマチュアマスターNoを `A000001` 形式で扱う
  - 大会内Noは従来どおり `AM-001` などを維持
  - スコア紐づけでは `tournament_participant_id` / アマチュア識別を優先し、同じ `アマ` で合算しない方針を固定
  - アマチュアの期表示は空欄ではなく `選手` とする方針を追加
- [✓] THE OPENの予選第4シリーズ13G〜16Gを投入した
  - score行数: 360件
  - 13〜16G総ピン: 74,741
  - 予選1〜16G総ピン: 291,148
  - 予選16G上位確認: 1位 幸木百合菜 3,702 / 2位 中島瑞葵 3,615 / 3位 久保田彩花 3,577 / 4位 岩見彩乃 3,564
- [✓] 予選通算PDFを調整した
  - 12G / 16Gのように横長になりすぎる場合、前半8Gは詳細ではなく `前半8G T/PIN / AVG / 順位` としてまとめ表示する
  - 後半9G〜12G、13G〜16Gはゲーム別表示する
  - 所属 / 用品契約欄は1列内で収まるよう文字サイズ・表示幅を調整した
- [✓] 準決勝4G（17G〜20G）を投入した
  - 準決勝参加者: 30名
  - 準決勝4G score行数: 120件
  - 準決勝4G総ピン: 23,263
  - 準決勝参加者30名の予選16G合計: 102,740
  - 準決勝通算20G合計: 126,003
  - 準決勝上位8名: 幸木百合菜 / 久保田彩花 / 岩見彩乃 / 中島瑞葵 / 板倉奈智美 / 飯田菜々 / 野仲美咲 / 近藤菜帆
- [✓] 準決勝用のラウンド別レーン割当機能を追加した
  - 新規テーブル: `tournament_round_lane_assignments`
  - 管理画面: `resources/views/tournament_round_lane_assignments/index.blade.php`
  - PDF: `resources/views/tournament_round_lane_assignments/pdf.blade.php`
  - 30名分の準決勝4Gレーン移動表をPDF出力できることを確認した
  - 日本語ヘッダー文字化けは `th` を使わず `td` 見出しへ寄せることで解消した
  - commit / push 済み: `55485a1` `feat: ラウンド別レーン割当と準決勝レーン表PDFを追加`
- [✓] ラウンドロビン表示の修正に着手した
  - `RoundRobinService` の持込元を `prelim_total` 固定ではなく、存在する場合は `semifinal_total` を優先するよう修正
  - THE OPENのRR進出者を準決勝通算20G上位8名から作るようにした
  - 8名総当たり7G + P.M. の対戦表を公式PDFの対戦順へ寄せた
  - レーン表示は `9L-10L / 15L-16L / 21L-22L / 27L-28L`
  - P.M. は `1位vs2位 / 3位vs4位 / 5位vs6位 / 7位vs8位`
- [✓] ラウンドロビン結果画面の表示確認を行った
  - `/scores/result?tournament_id=11&stage=ラウンドロビン&upto_game=8&gender_filter=F`
  - 対戦表が上段に表示されることを確認
  - RR進出者が準決勝通算20G上位8名になっていることを確認
  - 8G成績欄はスコア未入力のため `0-0-0 / Bonus 0 / RR合計 0` の状態
- [✓] 構文・Blade確認済み
  - `php -l app/Services/RoundRobinService.php`
  - `php -l app/Http/Controllers/TournamentResultSnapshotController.php`
  - `php artisan view:cache`
  - `php artisan view:clear`
  - `app/Http/Controllers/TournamentResultSnapshotController.php`
  - `app/Services/RoundRobinService.php`
  - `resources/views/scores/round_robin_result.blade.php`
  - `storage/backups/` は引き続きコミット対象外
  - RR 1G〜7G + P.M. のスコア投入
  - 勝者30P / 引き分け15P の計算確認
  - W-L-T / Bonus / RR合計 / 通算ポイント確認
  - 公式結果PDFとの上位3名・TOTAL POINT照合
  - RR正式反映、決勝ステップラダー入力、最終成績反映へ進む

#### 2026-06-08 メモ（THE OPENラウンドロビン8G投入・速報/成績導線改善）

- [✓] THE OPENのラウンドロビン8Gスコア投入を完了した
  - 対象: `tournament_id = 11`
  - stage: `ラウンドロビン`
  - RR 1G〜7G + P.M.
  - 8名 × 8G = 64行
  - RR8G総ピン: 13,581
  - 持込20G合計: 34,889
  - 通算28G合計: 48,470
  - Bonus合計: 960
- [✓] RR公式結果PDFとの照合を完了した
  - 1位 久保田彩花 RR 1,794 / GRAND 6,204 / Bonus 150 / W-L-T 5-3-0 / TOTAL POINT +754
  - 2位 中島瑞葵 RR 1,781 / GRAND 6,171 / Bonus 105 / W-L-T 3-4-1 / TOTAL POINT +676
  - 3位 幸木百合菜 RR 1,615 / GRAND 6,156 / Bonus 90 / W-L-T 3-5-0 / TOTAL POINT +646
  - TV決勝進出予定上位3名が公式結果と一致することを確認した
- [✓] RR投入スクリプトで、最後の表示ログ中に文字列キー + int の TypeError が出る問題を修正した
  - 原因: `foreach ($calculatedStats as $index => $stat)` の `$index` がライセンスNo文字列だった
  - 対応: 表示順位用の `$displayRank` を別カウンターとして使うよう修正
  - 例外発生時は transaction rollback され、DBに途中投入が残らないことを確認した
- [✓] ラウンドロビン8G成績画面を確認した
  - 対戦表表示OK
  - RR進出者は準決勝通算20G上位8名
  - W-L-T / Bonus / RRスクラッチ / RR合計 / TOTAL POINT 表示OK
  - 決勝ステップラダー進出者写真枠が 久保田彩花 / 中島瑞葵 / 幸木百合菜 になることを確認した
- [✓] ラウンドロビン正式成績反映を修正した
  - `total_pin` はBonus込みではなく、通算28Gピンとして扱うよう修正
  - `tie_break_value` にTOTAL POINT（Bonus込み）を保持するよう修正
  - RR snapshot詳細で、準決勝列に準決勝までの持込20Gを表示できるよう修正
  - 予選列が消える回帰を修正し、予選16G / 準決勝通算20G / RR8Gを同時に表示できるようにした
- [✓] RR正式反映後のsnapshot詳細を確認した
  - 久保田彩花: 予選 3,577 / 準決勝 4,410 / RR 1,794 / total 6,204 / 28G / AVG 221.57
  - 中島瑞葵: 予選 3,615 / 準決勝 4,390 / RR 1,781 / total 6,171 / 28G / AVG 220.39
  - 幸木百合菜: 予選 3,702 / 準決勝 4,541 / RR 1,615 / total 6,156 / 28G / AVG 219.86
- [✓] 大会結果ページの大会内移動リンクを改善した
  - 予選16G
  - 準決勝通算20G
  - ラウンドロビン
  - 決勝
  へ同一大会内で移動できるようにした
- [✓] 速報入力ページのステージ設定を、大会形式から自動設定するよう改善した
  - THE OPENでは以下が自動表示される
    - 予選 16G
    - 準決勝 4G
    - ラウンドロビン 8G
    - 決勝 2G
  - 準決勝通算20Gは「予選16G + 準決勝4G」として、入力設定とは分けて扱う
- [✓] `tournaments.shootout_settings` に大会進行設定を保存するよう修正した
  - `stage_progress`
  - `stage_game_counts`
  - THE OPENで `shootout_settings` に進行設定が保存されることを確認した
- [✓] 速報ランキングのゲーム数不一致検知を追加した
  - 途中速報で、期待ゲーム数と実入力ゲーム数が揃わない場合に警告を出す
  - 入力済みなのに `期待2G / 実0G` となる誤警告について、人物キーを見直して解消した
  - `tournament_participant_id` / `pro_bowler_id` / アマチュア識別 / 正規化ライセンス番号を優先する方針へ寄せた
- [✓] 予選2G速報で、16G全体が混ざる不具合を修正した
  - `upto_game=2` では2G分だけでランキングされることを確認した
  - アマチュアが `アマ` で合算されず、個別参加者として扱われる方針を維持した
- [✓] 準決勝通算20Gの速報表示を修正した
  - 準決勝単体4Gではなく、予選16G + 準決勝4G = 通算20Gとして表示
  - 合計順位は公式値と一致
    - 1位 幸木百合菜 4,541
    - 2位 久保田彩花 4,410
    - 3位 岩見彩乃 4,401
    - 4位 中島瑞葵 4,390
  - 内訳表示も `予選` と `準決勝` に分かれることを確認した
- [✓] 決勝ステップラダー画面は、未入力状態で未確定表示になることを確認した
- [✓] 確認済みコマンド
  - `php -l app/Http/Controllers/ScoreController.php`
  - `php -l app/Http/Controllers/TournamentController.php`
  - `php -l app/Http/Controllers/TournamentResultSnapshotController.php`
  - `php -l app/Services/RoundRobinService.php`
  - `php -l app/Services/ScoreService.php`
  - `php artisan view:cache`
  - `php artisan view:clear`
- [✓] DBスキーマ変更は行っていない
  - 既存の `tournaments.shootout_settings` JSONを使用
  - `migrations` / `docs/db/data_dictionary.md` / `docs/db/ER.dbml` の更新は不要
  - 決勝ステップラダー2Gのスコア入力
  - 決勝ステップラダーの勝者・優勝者確認
  - 最終成績反映
  - ポイント・賞金・タイトル反映
  - 大会成績PDF確認
  - 作業差分をcommit / pushする


#### 2026-06-12 メモ（THE OPEN決勝ステップラダー・最終成績反映・大会別PDF整合 完了）
- [✓] THE OPENの決勝ステップラダー2Gを入力・確認した
  - 3位決定戦: 幸木百合菜 224 - 187 中島瑞葵
  - 優勝決定戦: 久保田彩花 242 - 206 幸木百合菜
  - 優勝: 久保田彩花
  - 準優勝: 幸木百合菜
  - 3位: 中島瑞葵
- [✓] 決勝ステップラダーの最終成績反映を確認した
  - 最終順位は対戦結果で決定する
  - トータルピン / G / AVG は、予選・準決勝・ラウンドロビン・決勝ステップラダーで実際に投げた全ゲームを反映する
  - 年間アベレージに反映するため、決勝ステップラダーの投球ゲーム数・ピンも `tournament_results` 側へ加算する方針を確認した
- [✓] 最終成績一覧で賞金・ポイント・G・AVGの表示を確認した
  - 久保田彩花: 1位 / 6,446 / 29G / AVG 222.28 / 800pt / ¥1,200,000
  - 幸木百合菜: 2位 / 6,586 / 30G / AVG 219.53 / 650pt / ¥600,000
  - 中島瑞葵: 3位 / 6,358 / 29G / AVG 219.24 / 560pt / ¥400,000
  - 4位以下はRR後順位・予選16G・準決勝20G・RR8Gの結果をもとに反映
- [✓] 大会別PDF出力の500エラーを修正した
  - 大会成績一覧のPDF出力ボタンから `/tournaments/11/results/pdf` が出力できることを確認した
- [✓] THE OPEN大会別PDFを公式結果PDFに近い構成へ調整した
  - 1ページ目: 成績表 / 入賞者リスト
  - 2ページ目: 決勝ステップラダー
  - 3ページ目: ラウンドロビン最終成績
  - 4ページ目以降: 準決勝通算成績
  - 6ページ目以降: 予選通算成績
- [✓] PDF詳細成績表のプロフィール表示を修正した
  - 期
  - 投
  - 所属
  - 用品契約
  を `pro_bowlers` / `tournament_participants` から表示できるようにした
- [✓] PDF詳細成績表のスコア表示を修正した
  - 予選通算は16Gとして表示
  - 前半8Gは `前半8G T/PIN / AVG / 順位` として集約
  - 後半8Gは9G〜16Gをゲーム別表示
  - 準決勝は、DB上の `game_number 17〜20` を準決勝1G〜4Gとして表示
  - ラウンドロビン最終成績ページをPDFへ復旧し、持込20G / RR1G〜8G / Bonus / RR合計 / TOTAL POINTを表示
- [✓] 準決勝通算PDFの表示対象を修正した
  - 準決勝進出者はTHE OPENでは30名
  - 準決勝通算成績ページは、準決勝を実際に投げた30名のみ表示する
  - 予選だけで敗退した選手を準決勝ページへ `-` 表示で混ぜない
- [✓] 準決勝通算PDFの太線位置を修正した
  - ラウンドロビン進出ラインは8位下
  - 大会ごとの `round_robin_qualifier_count` / 進出者数に応じて境界線を出す方針
- [✓] PDFの氏名・所属表示の折り返しを調整した
  - `チョン・ヨンヒャン` など中黒を含む氏名を1行に収める
  - 長い所属 / 用品契約の折り返しをできるだけ抑える
- [✓] 決勝ステップラダーPDFの勝ち上がり赤線を修正した
  - 勝者側ルートを赤線で表示
  - スコア比較でも勝者判定できるようにした
  - 赤線が優勝枠などからはみ出さないよう、枠端に合わせて描画位置を調整した
- [✓] 確認済みコマンド
  - `php -l app/Http/Controllers/TournamentResultController.php`
  - `php -l app/Http/Controllers/TournamentResultSnapshotController.php`
  - `php -l app/Services/StepLadderBracketImageService.php`
  - `php artisan optimize:clear`
  - `php artisan view:clear`
- [✓] DBスキーマ変更は行っていない
  - `migrations`
  - `docs/db/data_dictionary.md`
  - `docs/db/ER.dbml`
  の更新は不要

## Phase 3：ProTest（後回し）

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

#### 2026-04-10 メモ（取込導線・会員区分表示・大会エントリー運用の整備）
- [✓] `AuthInstructor.csv` 取込画面で、対象年度指定・取込結果サマリ・一覧導線を追加
- [✓] `Pro_colum.csv` 取込画面でも、取込結果サマリと `instructor_registry` 反映結果を一覧側で確認できるようにした
- [✓] `/pro_bowlers` 管理画面で `member_class` / `can_enter_official_tournament` / `current instructor sync` を表示できるようにした
- [✓] manual 由来インストラクターは物理削除せず、`retired/history` として履歴化する運用を追加した
- [✓] 会員向け大会エントリー画面を `member_class` / `can_enter_official_tournament` / `is_active` 基準で制御するようにした
- [✓] シフト抽選 / レーン抽選 / 大会使用ボール登録でも、本人確認 + エントリー有効 + 会員区分判定のサーバー側ガードを追加した
- [✓] 大会使用ボール登録画面を本実装し、`registered_balls -> used_balls` 同期・最大12個・仮登録表示に対応した
- [✓] `registered_balls` / `used_balls` の一覧・create/edit を、検量証番号 / 仮登録 / 有効期限運用に合わせて整備した

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

#### 2026-04-21 メモ（大会成績後段処理の整合確認 / PDF日本語表示修正）
- [✓] 大会成績一覧の `賞金・ポイント再計算` が、`tournament_results` を正本に `point_distributions` / `prize_distributions` 参照で動作することを確認した
- [✓] 大会成績一覧の `タイトル反映` が、実導線上は `TournamentResultController` 側で動作し、優勝者の重複登録が起きないことを確認した
- [✓] `PDF出力` を大会別出力へ修正し、全大会一括PDFではなく当該大会だけを出力するよう整理した
- [✓] 大会成績PDFの日本語文字化けを修正し、見出し・列名・選手名が正常表示されることを確認した
- [✓] 大会成績PDFのライセンスNo表示を下4桁（末尾4文字）へ統一した
- [✓] **トータルピン方式は、速報入力 → snapshot反映 → 最終成績同期 → snapshot閲覧 → ポイント / タイトル / PDF確認 まで完了**
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
- [✓] 次の自然な後続としてトーナメント方式（シングルエリミネーション）とシュートアウト方式は完了。残る大きな方式はダブルエリミネーション

#### 2026-05-01 メモ（公式PDF風スコアシート入力・自動計算の着手）
- [✓] 公式PDF下部のゲーム別スコアシートは、スコア自体は手入力、計算・勝者判定・PDF反映は自動化する方針で確定
- [✓] スコアシート保存用の新規テーブル方針を整理
  - `tournament_match_score_sheets`
  - `tournament_match_score_sheet_players`
  - `tournament_match_score_frames`

#### 2026-05-02 メモ（シュートアウト / スコアシートPDF反映）
- [✓] `tournament_match_score_sheets` 系テーブルを使ったスコアシート入力画面が表示できることを確認した
- [✓] 大会別PDFで、公開対象のスコアシートを3ページ目以降に表示する処理を追加した

#### 2026-05-03 メモ（シュートアウト / 公式PDF風スコアシートPNG生成）

- [✓] スコアシート入力・保存用の `tournament_match_score_sheets` 系テーブルを追加した
  - `tournament_match_score_sheets`
  - `tournament_match_score_sheet_players`
  - `tournament_match_score_frames`
- [✓] `docs/db/data_dictionary.md` を更新した
- [✓] `php tools/generate_er_from_dictionary.php` で `docs/db/ER.dbml` を再生成した
- [✓] `/tournaments/{tournament}/match-score-sheets` の入力画面を追加した
- [✓] 入力した `X` / `/` / 数字 / `-` から、累計スコア・最終スコア・勝者判定を自動計算する処理を追加した
- [✓] 大会成績PDFで、公開対象のスコアシートを3ページ目以降に表示する処理を追加した
- [✓] HTML/CSSベースのスコア表表示をやめ、`MatchScoreSheetImageService` でPNG画像として生成してPDFへ貼る方式へ変更した
- [✓] ストライク表示を公式PDF風の左右三角の砂時計型マークへ調整した
- [✓] スペア表示を公式PDF風の右側三角マークへ調整した
- [✓] CCCカップPDFで、以下の構成を確認した
  - 1ページ目: 大会成績一覧
  - 2ページ目: シュートアウト勝ち上がり図
  - 3ページ目: スコアシート表
- [✓] ここまでの作業を commit / push 済み
- [✓] push後の `git status -sb` は `## main...origin/main` のみで差分なし

#### 2026-05-05 メモ（シュートアウト / 残りピン入力・公式PDF様式・進行設定 完了）
- [✓] `tournament_match_score_frames.remaining_pins jsonb nullable` を追加し、既存データを壊さず残りピンを保存できるようにした
- [✓] `docs/db/data_dictionary.md` を更新し、`php tools/generate_er_from_dictionary.php` で `docs/db/ER.dbml` を再生成した
- [✓] `TournamentMatchScoreFrame` の `fillable` / `casts` に `remaining_pins` を反映した
- [✓] `/tournaments/{tournament}/match-score-sheets` の入力画面に、10本ピン配置図から残りピンをクリック選択するUIを追加した
- [✓] 選択結果を `3.5.6` 形式で画面表示し、保存時は `[3,5,6]` のような配列として保持する方針にした
- [✓] `TournamentMatchScoreSheetController` で残りピン番号を 1〜10 に制限し、重複除去・昇順保存する処理を追加した
- [✓] 残りピン対応後に一時的に崩れたスコア計算を修正し、累計スコア・最終スコア・勝者判定が再び正しく出ることを確認した
- [✓] `MatchScoreSheetImageService` で各フレーム下へ残りピンを `3.5.6` 形式で描画できるようにした
- [✓] レーン表記を公式PDF寄せに修正し、同一ページ内の表記揺れを解消した
- [✓] `public/images/jpba_logo.png` を使い、PDF 1ページ目へJPBAロゴを表示できるようにした
- [✓] 大会別PDF 1ページ目を公式PDF風に再構成した
  - 大会名
  - シーズントライアル名
  - 会場名
  - 成績表タイトル
  - 主催 / 公認 / 主管運営 / 開催日 / 会場 / 競技内容
  - 入賞者リスト
- [✓] ライセンスNoはPDF上で下4桁表示に統一し、枠からはみ出す問題を解消した
- [✓] 大会編集画面のシュートアウト設定内に「シーズントライアル進行設定」を追加した
- [✓] 予選参加人数 / 予選G数 / 準決勝進出人数 / 準決勝G数 / 準決勝通算G数 / 決勝進出人数を後から編集できるようにした
- [✓] `tournaments.shootout_settings.stage_progress` を使い、PDFの競技内容文言を大会ごとの設定値から動的生成するようにした
- [✓] 34名→18名、70名→36名のように参加人数が変わるケースへ対応できることを確認した
- [✓] `docs/db/data_dictionary.md` に `shootout_settings.stage_progress` の運用説明を追記した
- [✓] `php -l` / `optimize:clear` / `view:clear` / `view:cache` を実行し、構文とBladeキャッシュを確認した
- [✓] 今回分は commit / push 済みで、push後に差分なしを確認した

#### 2026-05-05 メモ（ST Winter 2026 C 実データ投入・公式PDF成績表調整 完了）
- [✓] JPBA公式PDF（C_FinalResult.pdf）を基準に、2026年メリーランドカップ / JPBAシーズントライアル2026 ウィンターシリーズ C の実データ投入テストを実施した
- [✓] `SeedStWinter2026CResultCommand` 相当のコマンドで、大会作成から成績投入まで再現できるようにした
- [✓] 実データ投入後、以下の件数を確認した
  - 予選 `game_scores`: 272件（34名 × 8G）
  - 準決勝 `game_scores`: 72件（18名 × 4G）
  - `tournament_results`: 8件
- [✓] 大会別PDF 1ページ目の入賞者リストを実データ寄せした
  - 所属 / 用品契約
  - 獲得合計ポイント
  - 入賞ポイント
  - ステップポイント
  - 賞金
- [✓] 所属 / 用品契約は、選手プロフィール側に登録済みの情報をPDFへ表示する方針で確認した
- [✓] 所属 / 用品契約が長い場合でも、枠を崩さないように1行内で文字サイズを自動調整する処理を入れた
- [✓] ライセンスNo欄は右詰め寄せにし、後続でシードプロを示す `S` を入れられる余白を確保した
- [✓] PDFのライセンスNoは、公式PDFに寄せて下4桁表示を維持した
- [✓] 入賞ポイントは固定配分として表示し、ステップポイントは準決勝進出者数を基準に順位が上がるほど加算されるルールで表示した
  - 例: 準決勝進出18名なら、準決勝1位通過は18P
  - 例: 準決勝進出40名なら、準決勝1位通過は40P
- [✓] 賞金は賞金配分に基づき、入賞者リストへ表示できることを確認した
- [✓] PDF 3ページ目 / 4ページ目に、公式PDF風の準決勝成績表・予選成績表を追加した
- [✓] 公式PDFのページ順に合わせ、準決勝成績を予選成績より先に表示するようにした
- [✓] 予選成績表では、準決勝進出者の範囲に背景色を付けた
- [✓] 予選成績表では、18位 / 19位の境目に二重線を入れ、準決勝進出ラインを見やすくした
- [✓] 予選成績表では、8位 / 9位の境目にも太線を入れ、決勝進出圏との比較がしやすいようにした
- [✓] 準決勝成績表では、上位8名が決勝進出であるため、8位 / 9位の境目に太線を入れた
- [✓] 予選・準決勝の成績表で、`期` と投球表記を反映した
- [✓] 両手投げ / サムレス等は、単に `両` ではなく `右両手` / `左サムレス` のように表記できるようにした
- [✓] PDFの予選 / 準決勝の文字サイズ・罫線・背景色を、公式PDFに近い見え方へ調整した
- [✓] 現時点のPDF構成は以下で確認した
  - 1ページ目: 公式PDF風の大会概要 + 入賞者リスト
  - 2ページ目: 公式PDF風のシュートアウト勝ち上がり図 + スコアシート
  - 3ページ目: 準決勝4G・通算12Gトータルピン成績
  - 4ページ目: 予選8Gトータルピン成績
- [✓] 今回はPDF表示・投入テスト・ログ更新が中心で、DBスキーマ変更は行っていないため `docs/db/data_dictionary.md` / `docs/db/ER.dbml` の追加更新は不要と判断した

##### 次に詰める候補（更新）
  - どのテーブル / どの設定画面でシード対象を管理するか
  - PDFのライセンスNo欄に `S 1443` のように出すか、別形式にするか

#### 2026-05-06 メモ（ST Winter 2026 C シュートアウト実スコア・通算表示・優勝者コメント調整 完了）
- [✓] ST Winter 2026 C の公式PDFを基準に、シュートアウト1st / 2nd / 優勝決定戦の実スコアシートを投入・表示できる状態にした
- [✓] `SeedStWinter2026CResultCommand.php` に、公式PDFの1stマッチ / 2ndマッチ / 優勝決定戦のスコアシート実データを追加した
  - `tournament_match_score_sheets`: 3件
  - `tournament_match_score_sheet_players`: 10件
  - `tournament_match_score_frames`: 100件
  - `remaining_pins` も公式PDFの残りピン表示に合わせて投入
- [✓] `BowlingScoreCalculatorService` により、投入したフレーム別スコアと公式最終スコアが一致しない場合は例外で止まる方針にした
- [✓] 大会別PDFのページ構成を公式PDFに寄せて整理した
  - 1ページ目: 大会概要 + 入賞者リスト
  - 2ページ目: シュートアウト勝ち上がり図 + 優勝決定戦スコアシート
  - 3ページ目: シュートアウト2ndマッチ + シュートアウト1stマッチ
  - 4ページ目: 準決勝4G・通算12Gトータルピン成績
  - 5ページ目: 予選8Gトータルピン成績
- [✓] シュートアウト勝ち上がり図の優勝者枠に、優勝者・フリガナ・優勝ルートを表示できる状態へ戻した
- [✓] 一度、`ShootoutBracketImageService` の差し替えで未定義メソッドによりトーナメント図が消える事故があったため、以後は復旧版を基準に最小差分で修正する方針を明確化した
- [✓] シュートアウト図の太線・選手名・スコア・氏名スペース表記を再調整した
- [✓] スコアシートの選手名は「藤永　北斗」のように名字と名前の間にスペースを入れる表示へ統一した
- [✓] スコアシートの投球表記が「右投げ投げ」「左投げ投げ」にならないよう、`右投げ` / `左投げ` / `左両手投げ` などの表示へ正規化した
- [✓] 入賞者リスト・予選成績表・準決勝成績表でも、氏名のスペース入り表記を維持するよう調整した
- [✓] PDF 1ページ目の `期` 欄は `61期` ではなく、公式PDFに合わせて数字だけを表示する方針にした
- [✓] 3ページ目以降のスコアシート見出しを、固定文言ではなく大会情報から動的に表示するよう調整した
  - 大会名
  - シリーズ名
  - 決勝方式
  - 会場名
- [✓] 優勝者コメントを大会ごとに自由入力できる仕組みを追加した
  - 保存先は既存の `tournaments.shootout_settings.winner_note`
  - DBカラム追加は行わず、既存JSON設定を利用
  - 空欄ならPDF非表示
  - 入力欄は実際に使うシュートアウト勝敗入力画面 `resources/views/scores/shootout_result.blade.php` 側へ追加
- [✓] `ShootoutBracketImageService` で `winner_note` を読み取り、シュートアウト図右下の公式PDFと同じ位置へ複数行表示できるようにした
- [✓] 準決勝速報ランキングが4G単体表示に戻っていたため、シーズントライアル設定では予選8G + 準決勝4G = 通算12Gとして表示されるよう修正した
- [✓] `ScoreService` 側で、通算集計時のゲーム数・基準ピンを `counted_scores` / meta から正しく扱えるよう調整した
- [✓] `resources/views/scores/result.blade.php` 側で、`12G × 200 = 2,400 pin` のように通算ゲーム数に合わせて基準表示できるようにした
- [✓] `TournamentMatchScoreSheetController` / `tournament_match_score_sheets/index.blade.php` 側にも `winner_note` 対応を入れたが、実運用の入力場所はシュートアウト勝敗入力画面側と整理した
- [✓] 今回は既存の `tournaments.shootout_settings` / `game_scores` / `tournament_match_score_sheets` 系 / PDF描画処理を利用したため、DBスキーマ変更は行っていない
- [✓] そのため、`migrations` / `docs/db/data_dictionary.md` / `docs/db/ER.dbml` の追加更新は不要と判断した

##### 今回の差分ファイル（2026-05-06 時点）
- `app/Console/Commands/SeedStWinter2026CResultCommand.php`
- `app/Http/Controllers/ScoreController.php`
- `app/Http/Controllers/TournamentMatchScoreSheetController.php`
- `app/Http/Controllers/TournamentResultController.php`
- `app/Services/MatchScoreSheetImageService.php`
- `app/Services/ScoreService.php`
- `app/Services/ShootoutBracketImageService.php`
- `resources/views/scores/result.blade.php`
- `resources/views/scores/shootout_result.blade.php`
- `resources/views/tournament_match_score_sheets/index.blade.php`
- `resources/views/tournament_results/pdf.blade.php`

##### 次に詰める候補

#### 2026-05-09 メモ（大会別PDF Blade分割方針 / 回帰事故防止ルール）
- [✓] 現在の `resources/views/tournament_results/pdf.blade.php` に、シーズントライアル / シュートアウト / シングルエリミネーション / 通常成績表の表示をすべて詰め込む方針は危険と判断した
- [✓] 直近のPDF崩れは、方式別の専用表示が1つのBlade内で混在し、シーズントライアル専用表示が通常トーナメントPDFへ漏れたことが主因と整理した
- [✓] 既存PDFを壊さないため、現在のPDF修正途中差分はそのまま積み増しせず、いったんクリーンな現行ファイルを基準に分割する方針にした
- [✓] `pdf.blade.php` はPDFの入口・振り分け専用に縮小し、方式別の実体Bladeへ委譲する方針にした
  - `resources/views/tournament_results/pdfs/season_trial.blade.php`
  - `resources/views/tournament_results/pdfs/shootout.blade.php`
  - `resources/views/tournament_results/pdfs/single_elimination.blade.php`
  - `resources/views/tournament_results/pdfs/standard.blade.php`
  - `resources/views/tournament_results/pdfs/partials/*.blade.php`
- [✓] Laravel Blade の `@include` / partial 分割を使い、親ビューから渡された大会情報・成績情報を方式別Bladeで利用する構成にする
- [✓] Dompdf上で崩れやすい複雑なHTML/CSSを1枚のBladeに集約しない。方式別Bladeと、必要に応じたPNG生成サービスに分離して安定化する

##### PDF共通表示ルール（今後必ず守る）
  - 例: `藤川　大輔`
  - 例外的に元データが法人名・団体名・スペース不要名の場合は、変換関数側で吸収する
  - OK: `61`
  - NG: `61期` / `61期生` / `(61期生)`

##### 方式別に隔離する表示
  - シーズントライアル名
  - ステップポイント表記
  - 入賞ポイント / ステップポイントの専用計算表示
  - 8位 / 9位などST専用の決勝進出比較ライン
  - ST Winter 2026 C 実データ投入コマンドに依存した文言
  - 8名シュートアウト勝ち上がり図
  - 優勝決定戦 / 1st / 2nd のスコアシートPNG
  - `shootout_settings.stage_progress` に基づく進行説明
  - 優勝者メモ / 生涯勝利数などの自由記入欄
  - 公式PDF風トーナメント表PNG / SVG
  - 勝ち上がり太線
  - 対戦レーン表示
  - 24名以下 / 48名級 / 64名級のレイアウト切替
  - `single_elimination_final` の最終順位
  - トータルピン方式など、専用トーナメント図を持たない大会の基本PDF

##### 分割作業の安全手順

##### PDF分割後の回帰確認対象
  - シーズントライアル名・入賞者リスト・予選成績・準決勝成績が崩れない
  - 氏名は全角スペース入り
  - 期は数字のみ
  - ST専用ライン・ステップポイントはこの方式にだけ出る
  - シュートアウト図・スコアシートPNG・勝ち上がり太線が崩れない
  - 通過順位・期・氏名スペースの共通ルールが守られる
  - 通常トーナメントPDFにシーズントライアル文言・ST専用8位ライン・STポイント表が出ない
  - 大会名・会場・競技内容が大会情報から表示される
  - トーナメント表PNG接続前でも既存成績PDFを壊さない
  - 方式専用表示が何も漏れない

##### 次にやること


#### 2026-05-13 メモ（大会別PDF Blade分割・シュートアウト復旧・シングルエリミネーションPDF追加 完了）
- [✓] 大会別PDFの入口を `resources/views/tournament_results/pdf.blade.php` に整理し、本文を方式別Bladeへ分離する方針を実装した
- [✓] `resources/views/tournament_results/pdfs/` 配下に、PDF方式別Bladeと共通partialを配置する構成へ移行した
- [✓] PDF判定を2軸で整理した
  - 大会カテゴリ: `season_trial` / `standard`
  - 決勝方式: `shootout` / `single_elimination` / `step_ladder` など
- [✓] `season_trial` と `shootout` を排他扱いしないことを固定した
  - 例: ST Winter 2026 C / メリーランドカップは、シーズントライアルPDFの中にシュートアウト決勝図を表示する
- [✓] PDF分割途中で消えたシュートアウト図を復旧し、シーズントライアルPDF内でシュートアウト図・優勝決定戦スコア表・残りスコア表が表示される構成へ戻した
- [✓] Blade内で画像を再生成せず、Controller / Service 側で生成したPNG DataURIをBladeで表示するルールを維持した
- [✓] シングルエリミネーション方式のトーナメント表をPDFへ追加した
  - `app/Services/SingleEliminationBracketImageService.php` を追加
  - `resources/views/tournament_results/pdfs/partials/single_elimination_pages.blade.php` を追加
  - `TournamentResultController` から `singleEliminationPdf` / `singleEliminationBracketImage` を渡す構成にした
  - `single_elimination.blade.php` で `single_elimination_pages` を呼ぶ構成にした
- [✓] BBBカップPDFで、シングルエリミネーションのトーナメント表PNG表示を確認した
- [✓] `php -l app/Http/Controllers/TournamentResultController.php` を確認済み
- [✓] `php -l app/Services/SingleEliminationBracketImageService.php` を確認済み
- [✓] `php artisan optimize:clear` / `php artisan view:cache` を確認済み
- [✓] PDF分割分とシングルエリミネーションPDF追加分は commit / push 済み
- [✓] push後の `git status -sb` は `## main...origin/main` で差分なし

##### PDF分割後の固定ルール（追記）
  - `resources/views/tournament_results/pdf.blade.php`
  - `resources/views/tournament_results/pdfs/partials/context.blade.php`
  - 方式別Blade
  - partial
  - Controller / Service

#### 2026-05-15 メモ（決勝ステップラダーPDF追加・回帰確認 完了）
- [✓] 作業開始時に `git status -sb` / `git rev-parse --short HEAD` / `git diff --name-only` を確認し、開始時HEAD `38794f5`、差分なしから作業を開始した
- [✓] AAAカップ（`tournament_id = 3`）をステップラダー確認対象として特定した
- [✓] `tournament_result_snapshots` で current の `round_robin_total` / `step_ladder_final` が存在することを確認した
  - `round_robin_total`: snapshot_id=34
  - `step_ladder_final`: snapshot_id=35
- [✓] `game_scores` にラウンドロビン64行（8名×8G）と決勝4行（2試合分）が保存されていることを確認した
- [✓] `step_ladder_final` snapshot と `tournament_results` の最終順位8名が一致していることを確認した
  - 1位: 山田幸 / total_pin=3445 / games=14 / avg=246.07
  - 2位: 三浦美里 / total_pin=3186 / games=13 / avg=245.08
  - 3位: 桑藤美樹 / total_pin=3117 / games=13 / avg=239.77
  - 7位・8位は `RRテストA` / `RRテストB` としてアマチュア名が保持されている
- [✓] ラウンドロビン画面で `upto_game=8`、決勝ステップラダー画面で `upto_game=2` を指定し、画面表示がDB内容と一致することを確認した
  - 3位決定戦側: 山田幸 258 - 224 桑藤美樹
  - 優勝決定戦側: 山田幸 245 - 222 三浦美里
  - 優勝: 山田幸 / 準優勝: 三浦美里 / 3位: 桑藤美樹
- [✓] 大会別PDF `/tournaments/3/results/pdf` では成績表は出ていたが、当初は決勝ステップラダー対戦表ページが未出力だった
- [✓] `standard.blade.php` から `step_ladder_pages` を読み込み、`TournamentResultController` から `stepLadderPdf` / `stepLadderBracketImage` を渡す構成へ接続した
- [✓] `StepLadderBracketImageService` を追加し、既存の `public/images/step_ladder_tournament_bracket_template.png` を土台に、選手名・スコア・勝者ルート・優勝者名を合成したPNG DataURIを生成する方式にした
- [✓] 一時的にHTML/CSSでPDF用ステップラダー図を組む案を検討したが、既存の画面表示と同じテンプレ画像方式を正とし、HTML方式は採用しないことに戻した
- [✓] PDFの2ページ目に、テンプレ画像ベースの決勝ステップラダー対戦表が表示されることを確認した
- [✓] `php -l app/Http/Controllers/TournamentResultController.php` が通ることを確認した
- [✓] `php -l app/Services/StepLadderBracketImageService.php` が通ることを確認した
- [✓] `php artisan optimize:clear` 後にPDF再表示を確認した
- [✓] 以下ファイルを commit / push 済み
  - `app/Http/Controllers/TournamentResultController.php`
  - `app/Services/StepLadderBracketImageService.php`
  - `resources/views/tournament_results/pdfs/partials/context.blade.php`
  - `resources/views/tournament_results/pdfs/partials/step_ladder_pages.blade.php`
  - `resources/views/tournament_results/pdfs/standard.blade.php`
- [✓] コミット後の最終HEADは `f1f0419`
- [✓] push後の `git status -sb` は `## main...origin/main`、`git diff --name-only` は空で差分なし
- [✓] 今回はPDF表示・Controller / Service接続のみであり、DBスキーマ変更はなし
- [✓] そのため `migrations` / `docs/db/data_dictionary.md` / `docs/db/ER.dbml` は更新不要と判断した
  - ST Winter 2026 C / シーズントライアル + シュートアウト
  - CCCカップ / シュートアウト
  - BBBカップ / シングルエリミネーション
  - 通常トータルピン方式

---

#### 2026-05-15 メモ（シードプロ識別 `S` 表示 / ランキング・トーナメントシード設計方針）

- [✓] 大会別PDF分割後の主要4方式回帰確認を完了
  - [✓] ID 8: メリーランドカップ / シーズントライアル + シュートアウト
  - [✓] ID 5: CCCカップ / 通常大会 + シュートアウト
  - [✓] ID 4: BBBカップ / シングルエリミネーション
  - [✓] ID 3: AAAカップ / ラウンドロビン + ステップラダー
- [✓] シードプロ識別 `S` 表示の設計方針を整理
- [✓] シードプロは基本的に「前年度ポイントランキング上位者」を基準にする方針を確認
- [✓] 前年度ランキング上位24名を原則シードにする方針を確認
- [✓] 大会によっては、歴代優勝者 / 永久シード / 準永久シード / 全日本選手権者シード / 当該年度優勝者シード / 前年度優勝者シード / 手動追加シードが必要になることを確認
- [✓] 全日本選手権のみ、前年度ランキングではなく「当該年度の開催時点ランキング」を使う必要があることを確認
- [✓] `tournament_entries.is_seed` だけで済ませず、ランキング正本・シードリスト正本・大会別追加シードを分ける方針にした
- [✓] 現物DBに既存ランキング系 / シード系テーブルやカラムがないか確認する
- [✓] `docs/db/data_dictionary.md` / `docs/db/ER.dbml` を読み、既存の大会・選手・タイトル・成績周辺との関係を確認する
- [✓] 追加候補テーブルを確定する
  - [✓] `pro_bowler_ranking_snapshots`
  - [✓] `pro_bowler_ranking_rows`
  - [✓] `pro_bowler_seed_lists`
  - [✓] `pro_bowler_seed_list_players`
  - [✓] `tournament_seed_players`
- [✓] migration を追加する
- [✓] `docs/db/data_dictionary.md` を更新する
- [✓] `php tools/generate_er_from_dictionary.php` で `docs/db/ER.dbml` を再生成する
- [✓] ランキング登録画面を追加する
- [✓] トーナメントシード一覧画面を追加する
- [✓] 大会別追加シード設定画面を追加する
- [✓] 大会PDFのライセンスNo欄に `S 1443` のような表示を追加する

##### 設計メモ
- ランキングは、年度・性別・集計時点を持つスナップショットとして保存する。
- 前年度最終ランキングから、翌年度のトーナメントシード一覧を生成する。
- 前年度ランキング上位24名は `TS` として扱う。
- 永久シード、準永久シード、全日本選手権者シード、当該年度優勝者シード、前年度優勝者シード、歴代優勝者、手動追加などは `seed_category` / `seed_source_type` で根拠を持たせる。
- 大会ごとの追加シードは `tournament_seed_players` で管理する。
- PDF表示の `S` は、選手マスタ固定属性ではなく、大会ごとのシード判定結果として表示する。
- このログ記録時点ではDB変更なし。実装に入る段階で `migrations` / `docs/db/data_dictionary.md` / `docs/db/ER.dbml` をセット更新する。

#### 2026-05-20 メモ（シードプロ `S` 表示・年度別シード生成・大会優先出場者PDF 完了）
- [✓] シードプロ表示用の `ProBowlerSeedService` を追加し、ライセンスNoを通常表示 / シード表示で切り替えられるようにした
  - 通常表示: `0524`
  - シード表示: `S 0524`
- [✓] 大会成績一覧・大会別PDF・snapshot詳細・速報系画面で、同じシード判定結果を使う方針へ寄せた
- [✓] ラウンドロビン / 通常速報 / シュートアウト / シングルエリミネーションの速報表示でも、ライセンスNo欄にシード表示を反映した
- [✓] ラウンドロビン画面のライセンスNo欄を右詰め・適正幅へ調整した
- [✓] 大会編集画面から大会別シード設定画面へ遷移できるボタンを追加した
- [✓] 大会別シード設定画面を追加し、ライセンスNoから大会別追加シードを登録・解除できるようにした
- [✓] 年度別シード一覧画面を追加した
- [✓] 前年度ポイントランキングから、翌年度・性別ごとの年度別シード一覧を自動生成できるようにした
  - 通常運用: 2025年ポイントランキング上位24名 → 2026年シード
  - 同じシード年度・性別の一覧は再生成で差し替える
- [✓] 2026年男子 / 2026年女子の年度別シード一覧を、仮の2025年ポイントランキングから生成して確認した
- [✓] AAAカップ（男子）では男子シードだけが `S` 判定され、女子シードが混入しないことを確認した
- [✓] 手動追加シードと年度別シードの切り分けを確認した
  - 手動シード削除後は `S` が消える
  - 年度別シード側に対象者が入ると `S` が復活する
- [✓] 年度別シード一覧の詳細画面を追加し、シード年度・性別・元ランキング・登録選手24名を確認できるようにした
- [✓] 大会別シード設定画面に、大会優先出場者一覧（自動生成）を表示できるようにした
  - 年度別シード一覧
  - 大会別追加シード
  を統合して、出場優先順位として確認できる
- [✓] 大会優先出場者一覧PDFを追加した
  - ルート: `/tournaments/{tournament}/seed-players/pdf`
  - 表示: トーナメントシードプロ（TS）一覧
  - 表示列: 優先順位 / 前年ランキング / ライセンスNo / 氏名 / 期 / 種別
- [✓] 大会優先出場者PDFで、日本語文字化け・Dompdfフォントキャッシュ問題を解消した
  - `@font-face` の直接指定は使わない
  - Controller側で `defaultFont` を強制しない
  - PDF内の太字指定を避ける
  - 必要時は `vendor/dompdf/dompdf/lib/fonts/ipaexg*` のキャッシュを削除する
- [✓] 公式PDFサンプルに合わせ、大会優先出場者PDFからフリガナ列・由来列・備考列を削除した
- [✓] 公式PDFサンプルに合わせ、トーナメントシード以外の優先枠スペースを確保した
  - 公認T/M歴代優勝者シードプロ
  - 永久シードプロ（V20）
  - 全日本選手権者シード（JS）
  - 準永久シードプロ（V10）
  - 本大会スポンサー推薦
  - プロテスト実技免除合格者
  - プロテストトップ合格者
  - シーズントライアル出場者
  - 主催者（スポンサー）推薦
- [✓] 該当者がいない優先枠は `該当者なし（0名）` として表示する方針にした
- [✓] 構文確認・キャッシュクリア・Bladeキャッシュ確認が通った
  - `php -l app/Http/Controllers/TournamentSeedPlayerController.php`
  - `php -l app/Http/Controllers/ProBowlerSeedListController.php`
  - `php -l app/Services/ProBowlerSeedService.php`
  - `php -l routes/web.php`
  - `php artisan optimize:clear`
  - `php artisan view:cache`
- [✓] 今回作業分は commit / push 済みで、最終状態は差分なし
- [✓] ProTest は今回も対象外
  - 永久シード
  - 準永久シード
  - 歴代優勝者
  - スポンサー推薦
  - プロテスト枠
  - シーズントライアル枠

##### 今回の固定ルール（追記）
- シード表示の `S` は選手マスタ固定属性ではなく、大会年度・性別・年度別シード一覧・大会別追加シードを統合して判定する
- 年度別シードは、通常運用では前年度ポイントランキング上位24名から生成する
- PDF上のライセンスNoは既存方針どおり下4桁を基本とし、シード対象のみ `S 0524` のように表示する
- 大会優先出場者PDFは、該当者がまだいない優先枠も `0名` で場所を確保する
- Dompdfで日本語PDFを追加する場合、安易に `@font-face` や `defaultFont` を追加せず、既存の日本語表示方式に合わせる

#### 2026-05-22 メモ（大会エントリー管理への優先出場順位反映・取消/一括繰り上げ 完了）
- [✓] 大会優先出場者一覧で作成した優先順位を、エントリー管理画面へ反映した
- [✓] `tournament_seed_players` / 年度別シード一覧を読み取り、エントリー済み・ウェイティング済み選手の優先出場情報を表示できるようにした
- [✓] エントリー管理画面に `優先出場` 列を追加した
- [✓] 抽選一覧画面にも `優先出場` 列を追加した
- [✓] 優先出場者を一覧の上位に並べるようにした
- [✓] ウェイティング登録時、`waitlist_priority` が空欄なら優先出場設定から自動補完するようにした
- [✓] ウェイティング登録のライセンスNo入力で下4桁入力に対応し、4桁入力時は大会の対象性別を優先して照合するようにした
- [✓] 優先出場者なのに `tournament_entries` に存在しない選手を検出できるようにした
- [✓] エントリー管理画面に `優先出場 未登録` 件数と未登録者一覧を表示するようにした
- [✓] 抽選一覧にも、抽選前の注意として優先出場未登録件数を表示するようにした
- [✓] 優先出場未登録一覧から、該当選手をワンクリックでウェイティング登録できる導線を追加した
- [✓] エントリー者 / ウェイティング者の `取消` ボタンを追加した
  - 物理削除ではなく `status = no_entry` に戻す
  - シフト / レーン / 抽選済みフラグ / チェックイン / 待機順などの運用値をクリアする
- [✓] ウェイティング者の一括参加繰り上げを追加した
  - チェックボックスで対象者を選択
  - 参加権利ありのウェイティング行だけを `entry` へ変更
  - サーバー側でも参加権利なし行は処理対象外にする
- [✓] 優先順位の序列で、参加権利ありを最優先にした
  - 参加権利あり
  - 参加 / ウェイティング
  - 優先出場順位
  - 待機順
  - 抽選状況
  の順で管理画面上の並びを安定させた
- [✓] 追加したルート
  - `tournaments.waitlist.bulk_promote`
  - `tournaments.entries.cancel`
- [✓] 確認済みコマンド
  - `php -l app/Http/Controllers/TournamentEntryAdminController.php`
  - `php -l routes/web.php`
  - `php artisan optimize:clear`
  - `php artisan view:cache`
  - `php artisan route:list | findstr waitlist`
  - `php artisan route:list | findstr cancel`
- [✓] 今回分は commit / push 済みで、push後に差分なしを確認済み
- [✓] DBスキーマ変更は行っていないため、`migrations` / `docs/db/data_dictionary.md` / `docs/db/ER.dbml` の更新は不要と判断した


#### 2026-05-23 メモ（フォワードテスト前の大会運用データ初期化 完了）
- [✓] フォワードテスト前に、テスト投入済みの大会運用データを一度きれいに消す方針を確認した
  - 恒久的なリセット機能・Artisanコマンドは作成しない
  - 実稼働後に使う機能ではなく、今回のフォワードテスト前だけのDB整理として扱う
  - `pro_bowlers` / 会場 / 地区 / 性別 / ユーザー / マスタ系は残す
  - 大会・エントリー・スコア・成績・snapshot・シード/ランキングsnapshot・配分・タイトル反映などの大会運用データを対象にする
- [✓] 事前に `pg_dump -Fc` でバックアップを作成した
  - `storage/backups/jpba_main_before_forward_test_reset.dump`
  - バックアップはローカル保管用であり、Gitコミット対象にしない
- [✓] `psql` 接続時のDB名を `.env` に合わせて `jpba_main` とする必要があることを確認した
  - `DB_USERNAME=postgres`
  - `DB_DATABASE=jpba_main`
  - `JPBA_MAIN` ではなく小文字の `jpba_main` で接続する
- [✓] 削除対象テーブルの存在確認・件数確認を実施した
  - `tournaments`: 7件
  - `tournament_entries`: 21件
  - `game_scores`: 640件
  - `tournament_results`: 91件
  - `tournament_result_snapshots`: 50件
  - `tournament_result_snapshot_rows`: 457件
  - `tournament_match_score_sheets`: 5件
  - `tournament_match_score_sheet_players`: 14件
  - `tournament_match_score_frames`: 140件
  - `tournament_seed_players`: 4件
  - `pro_bowler_seed_lists`: 2件
  - `pro_bowler_seed_list_players`: 48件
  - `pro_bowler_ranking_snapshots`: 1件
  - `pro_bowler_ranking_rows`: 24件
  - `pro_bowler_titles`: 3件
  - `point_distributions`: 36件
  - `prize_distributions`: 18件
  - `stage_settings`: 4件
- [✓] `pro_bowler_titles.source = sync_from_results` のタイトル反映データが3件あることを確認した
- [✓] `pro_bowlers` 側のタイトル反映系カラムを確認した
  - `has_title`
  - `titles_count`
  - `perfect_count`
  - `seven_ten_count`
  - `eight_hundred_count`
  - `award_total_count`
- [✓] 今回はタイトル反映リセットとして `has_title` / `titles_count` を対象にし、褒章系カウントは触らない方針にした
- [✓] `BEGIN` + `ROLLBACK` のドライランSQLを作成し、削除順と削除後件数を確認した
  - SQLファイル作成時にUTF-8 BOM / SJISの文字コードエラーが出たため、ASCII保存に修正して再実行した
  - ドライランでは対象テーブルがすべて0件になることを確認し、最後は `ROLLBACK` で破棄した
- [✓] ドライラン結果が問題なかったため、`ROLLBACK` を `COMMIT` に変えた本実行SQLを作成した
  - `storage/backups/forward_test_reset_commit.sql`
- [✓] 本実行SQLを実行し、`COMMIT` まで完了した
- [✓] 本実行後の確認で、対象27テーブルがすべて0件になったことを確認した
  - `tournament_match_score_frames`
  - `tournament_match_score_sheet_players`
  - `tournament_match_score_sheets`
  - `tournament_result_snapshot_rows`
  - `tournament_result_snapshots`
  - `tournament_seed_players`
  - `pro_bowler_seed_list_players`
  - `pro_bowler_seed_lists`
  - `pro_bowler_ranking_rows`
  - `pro_bowler_ranking_snapshots`
  - `pro_bowler_titles`
  - `tournament_entry_balls`
  - `tournament_draw_reminder_logs`
  - `tournament_auto_draw_logs`
  - `media_publications`
  - `stage_settings`
  - `game_scores`
  - `tournament_results`
  - `tournament_entries`
  - `tournament_participants`
  - `tournament_files`
  - `tournament_organizations`
  - `point_distributions`
  - `prize_distributions`
  - `tournament_awards`
  - `tournament_points`
  - `tournaments`
- [✓] `pro_bowlers` 側のタイトル反映フラグも0件になったことを確認した
  - `has_title_true_count = 0`
  - `titles_count_nonzero_count = 0`
- [✓] DBスキーマ変更は行っていない
  - `migrations`
  - `docs/db/data_dictionary.md`
  - `docs/db/ER.dbml`
  の更新は不要と判断した
- [✓] フォワードテスト前の大会運用データ初期化は完了
  - `storage/backups/*.dump`
  - `storage/backups/forward_test_reset_*.sql`
  はローカル保管用であり、Git管理に入れない
  - 大会登録
  - 配分設定
  - 選手登録 / エントリー登録
  - スコア登録
  - snapshot反映
  - 最終成績
  - PDF確認
  - ランキング / シード反映確認


#### 2026-06-13 メモ（選手データ一覧整備・共通メニュー化・今年度シードプロ方針整理）
- [✓] THE OPEN最終PDF整合後の差分を commit / push 済み
  - commit: `857cf8f`
  - message: `fix: THE OPEN大会成績PDFのRR・準決勝・ステップラダー表示を修正`
  - push後の通常差分はなく、`storage/backups/` のみ未追跡として残した
- [✓] 選手データ一覧の初期表示を、会員更新済み・公式戦出場可の選手に寄せる方針へ変更した
  - 検索条件に `更新状態` を追加
  - 検索条件に `公式戦` を追加
  - デフォルトは `更新済` / `出場可`
  - `期別` の初期値は空欄に戻した
- [✓] 選手データ一覧から同期元表示を削除した
- [✓] `インストラクター同期` 表示を `保有インストラクター資格` へ変更した
  - A級 > B級 > C級 の順で最上位資格を表示
  - どれもない場合は `保有なし`
- [✓] 選手データ一覧のライセンスNo表示を、男女とも下4桁表示へ変更した
  - 内部管理は従来どおり `M/F` から始まるフルライセンスNoを維持
  - 下4桁検索時の重複防止のため、性別選択を必須扱いにした
- [✓] 左側メニューを共通パーツ化した
  - `resources/views/partials/side_menu.blade.php`
  - `resources/views/layouts/app.blade.php` から常時表示
  - 全プロデータ画面の直書きメニューは削除
- [✓] 左側メニューの未接続リンクを既存ルートへ接続した
  - `INFORMATION`
  - `会員用INFORMATION`
  - `トーナメントプロデータ`
  - `TP登録会受講情報`
  - `プログループ管理`
  - `大会別使用ボール登録`
- [✓] `トーナメントプロデータ` の名称を `今年度シードプロ` へ変更した
- [✓] `/tournament_pro` を `今年度シードプロ` 画面として再利用する方針にした
  - 正本は `pro_bowler_seed_lists` / `pro_bowler_seed_list_players`
  - 現時点では該当データが空のため、画面上は登録なし表示
- [✓] シードプロの表示方針を再整理した
  - 男子は `第1シード / 第2シード` に分けず、上位24名として扱う
  - 女子は `第1シード / 第2シード` の区分を扱う
  - 永久シードは一度登録したら原則外れない
  - 準永久シードは永久シードへ格上げされた場合のみ外れる
- [✓] 現在のシード系テーブル状態を確認した
  - `pro_bowler_seed_lists` は存在するが空
  - `pro_bowler_seed_list_players` は存在するが空
  - `tournament_seed_players` は存在
  1. シード選手の基となる2025年度ランキングを作成する
  2. 2025年度ランキングを基に2026年度シード選手リストを生成する
  3. 永久シードプロを登録する
  4. 過去タイトル数から準永久シードを登録する
  5. 準永久シード登録画面と、準永久シードから永久シードへの格上げ機能を作成する
  6. 大会ごとの歴代優勝者シード登録画面とリストを作成する

#### 2026-06-16 メモ（公式ランキング取込・2026年度シード生成・永久シード登録）

- [✓] 選手データ検索のライセンスNo表示を、男女共通で下4桁表示へ統一した
- [✓] 下4桁検索時は性別指定を必須にし、男女のライセンスNo重複事故を防ぐ方針へ変更した
- [✓] 共通サイドメニューを追加し、主要管理画面で左メニューを常時表示できるようにした
  - `resources/views/partials/side_menu.blade.php`
- [✓] `トーナメントプロデータ` の名称を `今年度シードプロ` へ変更した
- [✓] 公式ランキング管理画面を追加し、2025年度公式最終ポイントランキングを取り込めるようにした
- [✓] テスト生成されていた ranking snapshot / rows は、2025公式ランキングではないため削除した
- [✓] 2025女子公式最終ポイントランキングを取り込んだ
  - snapshot id: `3`
  - rows: `226件`
  - 未照合: `0件`
- [✓] 2025男子公式最終ポイントランキングを取り込んだ
  - snapshot id: `4`
  - 未照合: `0件`
- [✓] 2025確定ランキングから2026年度男子シードを生成した
  - seed_list_id: `4`
  - 男子は第1 / 第2シードに分けず、上位24名を表示
- [✓] 2025確定ランキングから2026年度女子シードを生成した
  - seed_list_id: `5`
  - 女子は1〜18位を第1シード、19〜36位を第2シードとして表示
- [✓] 年度別シード詳細画面を調整した
  - 参照URLは表示しない
  - ライセンスNoは下4桁表示
  - 期を表示
  - ポイントを表示
  - 獲得賞金を表示
  - 男子 / 女子の切替ボタンを追加
- [✓] 今年度シードプロ画面を整備した
  - 男子上位24名
  - 女子第1シード18名
  - 女子第2シード18名
  - 永久シード
  - 準永久シード
  を枠ごとに表示できるようにした
- [✓] 永久シードプロを公式リストに基づいて登録した
  - 男子7名
  - 女子9名
  - `seed_category = V20`
- [✓] 退会・故人の永久シードはDBには保持し、今年度シードプロ画面の出場権表示からは除外する方針にした
  - 男子表示: active 5名
  - 女子表示: active 7名
- [✓] 永久シード欄の順位は、表示連番ではなく公式登録順・勝数順を尊重する方針にした
- [✓] 準永久シードは今回は登録を見送る判断をした
- [✓] DBスキーマ変更は行っていない
  - `migrations`
  - `docs/db/data_dictionary.md`
  - `docs/db/ER.dbml`
  の更新は不要
- [✓] コード差分は `2c9a8b1` まで commit / push 済み
  - `feat: 公式ランキング取込と年度別シード生成を整備`

#### 2026-06-23 メモ（Codex直接編集開始・DB正本スナップショット更新）

- [✓] 元フォルダを直接編集する前提で作業開始
- [✓] 本番でも登録されていた `__debug` ルートを `local` 環境限定へ修正
- [✓] `docs/chat/context_pack.md` を現在状況が分かる引き継ぎメモへ更新
- [✓] `php artisan migrate:status` で `2025_09_02_000213_create_pro_bowler_ranking_and_seed_tables` が適用済みであることを確認
- [✓] 現DBから `docs/db/SCHEMA.sql` を再生成
- [✓] 現DBから `docs/db/columns_public.csv` を再生成
- [✓] `php tools/generate_db_docs.php` で `docs/db/columns_by_table.md` を再生成
- [✓] `SCHEMA.sql` / `columns_by_table.md` にランキング・シード系テーブルが反映されたことを確認
- [✓] `tools/generate_db_docs.php` の `fgetcsv()` 警告を解消
- [✓] 確認済み
  - `php -l routes/web.php`
  - `php -l tools/generate_db_docs.php`
  - `APP_ENV=production` 相当で `__debug` ルートが出ないこと
- [✓] 大会別シード設定画面に `PDF枠別 登録状況` を追加
- [✓] 大会別 `歴代優勝者シード` など、PDF右側の枠ごとに登録者数と対象選手を確認できるようにした
- [✓] 優先出場者PDFの⑤枠見出しを `当該年度・前年度優勝者シードプロ` に修正
- [✓] `php artisan view:cache` でBladeコンパイル確認済み
- [x] 年度別シード / 永久シード / 大会別追加シードの `S` 表示回帰確認は後続

#### 2026-06-24 メモ（スコア取込ステージング・CSV横持ち・OCR原本入口）

- [✓] `score_import_batches` / `score_import_rows` / `score_import_row_candidates` を使い、CSVやOCR解析結果を直接 `game_scores` に入れず確認用一時データへ止める方針を実装開始した
- [✓] CSV一時取込、取込詳細、行修正、候補選択、一括修正、確認済み行の `game_scores` 確定反映、操作ログ保存を追加した
- [✓] `game_number + score` 形式に加え、`1G` / `2G` / `3G`、`G1`、`game1`、`第1ゲーム` などの横持ちスコアCSVを取り込めるようにした
- [✓] 写真/PDF原本を `score_sheet_image` バッチとして保存し、OCR解析待ちとして操作ログに残せる入口を追加した
- [✓] 保存済み原本へOCR/AI解析結果JSONを投入し、`score_import_rows` へ確認用行を作成できる入口を追加した
- [✓] OCR JSON取込で日本語キー・別名キーを受けられるようにし、詳細画面からサンプルJSONをダウンロードできるようにした
- [✓] Excel/Googleスプレッドシートからコピーした表を貼り付け、既存CSV取込サービスへ流せる入口を追加した
- [x] 年度別シード / 永久シード / 大会別追加シードの `S` 表示回帰確認は引き続き後続

#### 2026-06-25 メモ（S表示回帰確認・エントリー管理表示補強）
- [✓] エントリー管理 / 抽選一覧のライセンスNo表示を `ProBowlerSeedService` 経由にし、シード対象は `S 1423` のように表示するよう補強した
- [✓] DBロールバック付き確認で、年度別シード / 永久シード / 大会別追加シードがエントリー管理表示と大会PDF用サービス表示で `S` 付きになることを確認した
- [✓] 優先出場PDF用データでは、年度別シード / 永久シード / 大会別追加シードが下4桁ライセンス表示で一覧へ含まれることを確認した

#### 2026-06-25 メモ（大会終了処理チェックリスト・選手単位の未入力一覧）
- [✓] `TournamentAutomationReadinessService` でエントリー済み選手と `game_scores` を pro ID / ライセンス / 下4桁 / 氏名で突合し、スコア未入力候補を選手単位で算出するようにした
- [✓] 大会運用ログの終了処理チェックリストへ、スコア未入力候補のEntry ID・ライセンス・氏名・シフト・レーン一覧を表示するようにした
- [✓] 表示は先頭50名までに制限し、件数自体は `score_entry_gap` として全体数を保持する

#### 2026-06-25 メモ（古い未チェック項目の棚卸し）
- [✓] `docs/chat/progress_board.md` / `docs/chat/automation_roadmap.md` / `docs/chat/context_pack.md` の未チェック項目を棚卸しした
- [✓] 未チェック139件はすべて `progress_board.md` に残っており、`automation_roadmap.md` と `context_pack.md` は未チェック0件であることを確認した
- [✓] 古い `commit / push`、`git status`、`storage/backups/`、次チャット開始時確認、旧い `S` 表示、旧い歴代優勝者シード画面メモは、履歴または重複として扱う方針にした
- [✓] 2026-06-23以降にCodexが進めた作業は、現行ログ上ではチェック済み扱いでよい
- [✓] 詳細な分類は `docs/chat/unchecked_inventory.md` に作成した
- [✓] 直近のActive Backlogは、実OCRエンジン接続、またはOCR/AI出力を現在のJSON仕様へ変換する実アダプタの実装

#### 2026-06-26 メモ（OCR/AI出力テキストアダプタ）
- [✓] `ScoreImportOcrTextAdapterService` を追加し、外部OCR/AI出力を既存OCR JSON仕様へ変換できるようにした
- [✓] JSON / Markdown表 / タブ区切り / カンマ区切り / 空白区切りの簡易表を `rows` / `games` / `scores` 形式へ正規化する
- [✓] 写真/PDFバッチ詳細画面へ `OCR/AI出力貼り付け` フォームを追加した
- [✓] 変換後は `ScoreImportOcrResultStageService::importPayload()` へ流し、既存の確認・修正・確定反映画面をそのまま使う
- [✓] `変換JSONを確認` ボタンを追加し、DB保存前にアダプタ変換結果を別タブで確認できるようにした

#### 2026-06-26 メモ（未チェック整理・現行JPBAサイト踏襲候補）
- [✓] 古い未チェック140件を棚卸しし、履歴・重複・一時確認系の未チェックを削除した
- [✓] 必要な後続候補は、下記のActive Backlogへ集約した
- [✓] 現行JPBAサイトを確認し、公開側で踏襲すべき主要導線・カテゴリ・ページ候補を追加した

#### 2026-06-26 メモ（OCR/AI取込詳細の確認情報表示）
- [✓] 取込詳細画面に、最新のOCR/AI変換サマリーを表示するカードを追加した
- [✓] 取込行一覧へ `確認情報` 列を追加し、信頼度、要確認理由、変換元ファイル/行/列を一覧で確認できるようにした
- [✓] `raw_payload` の全体だけでなく、抽出元行だけを先に開けるようにした
- [✓] DBスキーマは変更せず、既存の `confidence` / `error_message` / `raw_payload` / 操作ログ `adapter_summary` を表示に活用した

#### 2026-06-26 メモ（OCRエンジン接続境界の固定）
- [✓] `ScoreImportOcrEngineBoundaryService` を追加し、画像/PDF原本バッチからOCRエンジンへ渡す入力仕様を `buildEngineInput()` として固定した
- [✓] OCRエンジン/AI出力テキストは `stageTextResult()` で `ScoreImportOcrTextAdapterService` -> `ScoreImportOcrResultStageService` -> `score_import_rows` に流す境界にした
- [✓] 貼り付け変換画面も同じ境界サービスを通すようにし、手動貼り付けと将来の実OCRジョブの経路を揃えた
- [✓] プレビューJSONに `boundary` を追加し、バッチID、原本ファイル、既定値、直接書き込み禁止テーブル、確認必須ルールを確認できるようにした

#### 2026-06-26 メモ（スコア取込運用手順書）
- [✓] `docs/operations/score_import_runbook.md` を追加した
- [✓] CSV、Excel/Googleスプレッドシート貼り付け、写真/PDF原本、OCR JSON、OCR/AI出力貼り付けを同じ手順書へまとめた
- [✓] すべての取込方式で `score_import_rows` -> 人間確認 -> `game_scores` の順に進める運用を明記した
- [✓] 差し替え、反映後の扱い、要確認理由、トラブル時、完了条件を整理した

#### 2026-06-26 メモ（公開トップ棚卸し・INFORMATIONカテゴリ正本化）
- [✓] `docs/operations/public_site_parity_checklist.md` を追加し、現行JPBA1トップの主要導線を棚卸しした
- [✓] INFORMATIONカテゴリを `NEWS` / `大会` / `TV情報` / `ｲﾝｽﾄﾗｸﾀｰ` / `イベント` に揃えた
- [✓] `Information::categories()` / `Information::categoryValidationRule()` を追加し、公開・会員・管理画面のカテゴリ候補をモデル正本へ寄せた
- [✓] `informations_category_check` を更新するmigrationを追加し、DBでも `TV情報` を許可できるようにした
- [✓] 一般公開INFORMATIONの `/info` / `/info/{id}` / `/info/files/{id}` をログイン不要の公開ルートへ移した
- [✓] 残り未チェックは38件

#### 2026-06-26 メモ（公開トップのDB表示化）
- [✓] `/` をLaravel初期画面から `PublicHomeController` + `resources/views/public/home.blade.php` のJPBA公開トップへ差し替えた
- [✓] トップの大会バナー/日程枠を `tournaments` と公開 `tournament_files` から表示するようにした
- [✓] INFORMATION枠を `informations` の一般公開データから表示するようにした
- [✓] 現行サイト由来の外部ナビ、PDF、フッター導線を `config/jpba_public.php` にまとめた
- [✓] 残り未チェックは37件

##### 次に行う候補（Active Backlog）

###### A. 直近のスコア/OCR運用
- [ ] 実データの紙成績表画像/PDFから外部OCR/AI出力を作り、貼り付け変換プレビューで `payload.rows` を確認する
- [ ] 貼り付け変換から `score_import_rows` 作成、要確認行修正、`game_scores` 確定反映まで通し確認する
- [✓] OCR/AI変換結果の警告・信頼度・変換元行を、取込詳細画面でより見やすく表示する
- [✓] 実OCRエンジンを接続する場合の境界を、画像/PDF原本バッチ -> OCR処理 -> アダプタ -> `score_import_rows` として固定する
- [✓] CSV / Excel / OCR JSON / OCR貼り付け変換を同じ運用手順書にまとめる

###### B. 現行JPBAサイト踏襲で足りない公開導線
- [✓] 公開トップを現行JPBA1の構成に合わせ、上部メニュー、更新履歴、プロボウラー専用ページ、主要バナー、INFORMATION、協賛バナー、外部リンク、SNSリンクを棚卸しする
- [✓] INFORMATIONカテゴリを現行サイト実態に合わせ、`NEWS` / `大会` / `TV情報` / `ｲﾝｽﾄﾗｸﾀｰ` / `イベント` を扱えるようにする
- [✓] トップの大会バナー/日程枠をDB正本から表示し、PDFリンク（トーナメント予定表、観戦案内など）も管理できるようにする
- [ ] `JPBAについて` の協会概要、会長挨拶、運営機構図、役員・代議員名簿、定款、事業計画、予算、事業報告、財務PDFを公開ページとして整理する
- [ ] `スケジュール` ページを年次/月次カレンダーとPDF導線込みで現行サイトの見た目へ寄せる
- [ ] `選手データ` の公開検索を、氏名、ライセンスNo範囲、性別、地区、退会者導線まで現行サイトと照合する
- [ ] `トーナメント` 公開ページで、大会ページ、速報、成績、PDF、トピックスリンクを現行サイト互換で表示する
- [ ] `インストラクター` 公開ページで、講習情報、スクール情報、テキスト販売、制度概要、ライセンス別一覧を整理する
- [ ] `プロテスト` 公開ページで、受験の流れ、実施概要、申請/結果PDF、受験者講習会情報を整理する
- [ ] `トピックス` 公開ページで、記事本文、画像、達成記録、社会貢献活動、プロボウラー紹介、大会ページリンクを扱えるようにする
- [ ] お問い合わせ、取材のお申込み、特定商取引法に基づく表記、プライバシーポリシーを現行サイトのフッター導線として整理する
- [ ] 現行 `jpba1.jp` と `jpba.or.jp` に分かれているリンク構造を調査し、旧URL互換/リダイレクト方針を決める

###### C. 大会・成績・タイトル自動化
- [ ] `game_scores` を正本に、速報・途中成績・正式成績・PDF・タイトル反映の流れを通し確認する
- [ ] スコア順位速報で、同スコア時タイブレーク、通過人数、carry、シフト別/合算、男女別/合算を整理する
- [ ] 速報から正式成績への反映は、人間確認後の反映ボタン方式として固定する
- [ ] ラウンドロビン、ステップラダー、シュートアウト、シングルエリミネーションの既存実装を実データで回帰確認する
- [ ] ダブルエリミネーション方式の敗者側ブラケット、リセット決勝、敗者側順位、同順位扱い、再戦条件を設計する
- [ ] `tournament_awards` / `tournament_points` と `prize_distributions` / `point_distributions` の役割を整理し、辞書・現物スキーマを揃える
- [ ] 最終成績同期後のタイトル反映、ポイント再計算、賞金、シード未反映候補を大会終了チェックリストから完結できるようにする

###### D. PDF・公式帳票
- [ ] 大会PDFの方式別Blade分割後の回帰確認を行う（通常、シーズントライアル、シュートアウト、シングルエリミネーション）
- [ ] PDF共通表示ルールを固定する（氏名の全角スペース、期表示、ライセンス下4桁、賞金欄、方式別文言の漏れ防止）
- [ ] 公式PDF風スコアシートの罫線、ロゴ、会場情報、複数ページ分割を調整する
- [ ] 大会ごとにBladeを直接手修正しない運用へ寄せる

###### E. ランキング・シード・エントリー運用
- [ ] 公式ランキング取込画面を、補助画面として残すか年度末確定ランキング管理画面へ整理するか決める
- [ ] 男子ranking snapshotの `as_of_date` が公式PDF日付と一致しているか確認する
- [ ] 実ランキング取込、年度末確定処理、全日本選手権用の年度途中ランキング運用を整理する
- [ ] 大会エントリー導線へ優先出場順位をどう反映するか決める
- [ ] チェックイン、当日運用、抽選結果公開、取消理由、一括繰り上げ履歴をエントリー管理に接続する
- [ ] `registered_balls` と `used_balls` の役割分担を最終整理する

###### F. インストラクター・ProTest・会員基盤
- [ ] `instructor_registry` を正本にし、既存 `instructors` / 画面 / Controller を段階移行する
- [ ] 講習、資格、更新履歴、資格解除時の扱いを決める
- [ ] `ProBowlerController` / `ProBowlerImportController` の `instructors` 更新を互換レイヤとして維持するか整理する
- [ ] alias/旧ライセンス表記を current/history へどう寄せるか整理する
- [ ] ProTestの要件、スキーマ、申請、実技スコア、合否、公開結果PDF導線を整理する

###### G. DB正本・公開互換の横断整理
- [ ] `tournaments` 周辺の最終スキーマを辞書・ER・migrationと揃える
- [ ] `docs/db` の辞書、ER、SCHEMA、columns資料を現DBと定期的に照合する
- [ ] 公開側はDB正本を読むだけ、管理側は入力・確認・反映を行う役割分担を維持する
- [ ] 現行サイトの見た目を保つため、HTML構造、画像/バナー、PDF/外部リンク、フッターリンクを公開画面ごとに照合する
