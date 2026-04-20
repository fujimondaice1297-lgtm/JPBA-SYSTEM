# DB作業履歴（チャット全要約）
> 生成元: 作業履歴.txt（ユーザー提供の要約テキスト）
> 用途: このまま `docs/chat/worklog_db.md` に保存して参照する

---

作業履歴

## 2026-04-20 snapshot閲覧ページの実装と途中成績導線の整理

- 目的:
  - 前段で追加した `tournament_result_snapshots` を、反映履歴テーブル上の管理用途だけでなく、**途中成績を実際に閲覧できる公開用画面** として扱える状態にする。
  - 特に
    - 予選通算成績
    - 準々決勝成績 / 準々決勝通算成績
    - 準決勝成績 / 準決勝通算成績
    - 決勝成績 / 最終成績
    を snapshot ごとに確認できるようにし、正式成績反映ページから直接辿れるようにする。

- 実施内容:
  - `app/Http/Controllers/TournamentResultSnapshotController.php`
    - snapshot 詳細表示 `show()` を追加し、`tournament_result_snapshot_rows` を順位表として表示できるようにした。
    - 表示用に
      - 予選
      - 準々決勝
      - 準決勝
      - 決勝
      のステージ別トータルピン列を構成した。
    - ただし単純に大会全体の `game_scores` を合算すると、`予選通算成績` を開いても準々決勝以降が見えてしまうため、**`snapshot.calculation_definition.source_sets` を正本として、その snapshot が実際に使っている stage / game 範囲だけを再集計する** 方式へ修正した。
    - これにより
      - `予選通算成績` では予選列のみ値が入り、準々決勝 / 準決勝 / 決勝は `-`
      - `準々決勝通算成績` では予選 + 準々決勝のみ値が入り、準決勝 / 決勝は `-`
      - `最終成績` では予選 + 準々決勝 + 準決勝 + 決勝が入る
      形に整理した。
    - さらに、snapshot 詳細画面で使うナビを2段に分離し、
      - **現在の成績を見る**：予選 / 準々決勝 / 準決勝 / 決勝ごとの current snapshot 一覧
      - **この成績の反映履歴**：今開いている `result_code` だけの履歴
      に整理した。
    - これにより、以前のように
      - 予選と決勝しか見えない
      - 決勝欄に `最終成績` が複数並ぶ
      といった混乱を避けられる状態にした。

  - `resources/views/tournament_result_snapshots/index.blade.php`
    - 正式成績反映ページに **現在の成績を見る** ブロックを追加した。
    - `buildPresets()` で定義済みの公開単位を基準に
      - 予選
      - 準々決勝
      - 準決勝
      - 決勝
      ごとに、反映済みならリンク、未反映なら `未反映` を表示する構成へ整理した。
    - 反映ボタンカードごとにも **現在の成績を見る** ボタンを追加し、その単位の current snapshot があれば直接開けるようにした。
    - これにより、正式成績反映ページから
      - 予選通算成績
      - 準々決勝通算成績
      - 準決勝通算成績
      - 最終成績
      へ直接辿れるようになった。

  - `resources/views/tournament_result_snapshots/show.blade.php`
    - 新規追加。
    - snapshot 詳細表示として
      - タイトル / 大会名
      - 現行 / 最終成績バッジ
      - 性別 / シフト / 主ステージ
      - 反映日時 / ゲーム数 / 持込G数 / 行数 / 反映者
      - ステージ別トータルピン列付き順位表
      を表示するようにした。
    - 表示ラベルを日本語寄せし、
      - `total_pin` → `トータルピン方式`
      - `current` → `現行`
      - `scratch / carry / total` の内部表現は表から外し、代わりに
        - 決勝（nゲーム）
        - 準決勝（nゲーム）
        - 準々決勝（nゲーム）
        - 予選（nゲーム）
        - トータルピン
        - ゲーム数
        - AVG
        の列構成に変更した。
    - 画面上部の集計定義ブロックは、日本語列で内容が読み取れるようになったため最終的に削除した。

  - `routes/web.php`
    - `tournaments/{tournament}/result-snapshots/{snapshot}` の show route を追加し、反映履歴や現在成績ナビから詳細画面へ遷移できるようにした。

- 確認できたこと:
  - 正式成績反映ページから `予選通算成績` / `最終成績` に直接遷移できることを確認した。
  - `予選通算成績` では予選列のみ値が入り、準々決勝 / 準決勝 / 決勝は `-` になることを確認した。
  - `最終成績` では
    - 決勝（1ゲーム）
    - 準決勝（2ゲーム）
    - 準々決勝（2ゲーム）
    - 予選（4ゲーム）
    - トータルピン
    - ゲーム数
    - AVG
    の列で表示されることを確認した。
  - 正式成績反映ページ・snapshot 詳細画面の両方で、途中成績導線が見える状態まで到達した。

- 現時点の判断:
  - **トータルピン方式については、速報入力 → 反映 → 最終成績同期 → snapshot閲覧ページ まで一連の導線が通った。**
  - 後続は
    1. ポイント再計算
    2. タイトル反映
    3. PDF出力
    の整合確認へ進むのが自然。

## 2026-04-20 大会速報 → 正式成績反映 / 最終成績同期 / 導線整備

- 目的:
  - 4/18 時点で安定化したトータルピン方式の速報集計を、正式成績として反映・閲覧できる状態まで進める。
  - 特に
    - 途中成績（予選前半 / 通算 / 準々決勝通算 / 準決勝通算）
    - 最終成績
    - 大会成績一覧への同期
    - 速報ページ・成績一覧ページからの導線
    を一連で運用できるようにする。

- 実施内容:
  - DB / 正本整理:
    - `tournament_result_snapshots` / `tournament_result_snapshot_rows` を正式に追加した。
    - `tournament_result_snapshots` には `calculation_definition` を追加し、公開単位ごとの
      - 対象ステージ
      - 対象ゲーム範囲
      - carry 対象
      - scratch / carry の集計条件
      を JSON で保持できるようにした。
    - `docs/db/data_dictionary.md` を更新し、
      - `game_scores` は速報入力の正本
      - `tournament_result_snapshots` 系は途中公開・確定スナップショット
      - `tournament_results` は最終成績の正本
      という役割分担を明文化した。
    - `docs/db/ER.dbml` は辞書から再生成した。

  - モデル / サービス:
    - `TournamentResultSnapshot`
    - `TournamentResultSnapshotRow`
    - `TournamentResultSnapshotService`
    を追加した。
    - `TournamentResultSnapshotService` で、`game_scores` を起点に
      - 予選前半成績
      - 予選後半成績
      - 予選通算成績
      - 準々決勝成績
      - 準々決勝通算成績
      - 準決勝成績
      - 準決勝通算成績
      - 決勝成績
      - 最終成績
      の snapshot を作れるようにした。
    - 同一 `tournament_id + result_code + gender + shift` の旧 current snapshot は `is_current = false` に落とし、新しい反映結果を current とする構成にした。
    - `final_total` の反映時だけ `tournament_results` を大会単位で再構築し、最終成績一覧へ同期する処理を追加した。

  - 同期仕様:
    - `tournament_results` への同期は **`final_total` かつ 性別=全体 かつ シフト=全体** のときだけ実行するよう整理した。
    - 性別 / シフトで絞った反映は snapshot のみ保存し、最終成績一覧への同期は行わない仕様にした。
    - これにより、
      - 途中成績や女子別 / シフト別成績は反映ページで管理
      - 大会成績一覧は大会全体の最終成績のみ表示
      という役割分離を明確にした。

  - 4桁ライセンス番号解決:
    - 速報入力では下4桁ライセンス入力を継続使用できるようにしたまま、正式成績反映時には
      - 下4桁
      - 性別（M/F）
      を使って `pro_bowlers.license_no` の下4桁と照合する処理を追加した。
    - 一意に解決できた場合は
      - `pro_bowler_id`
      - フルライセンス番号
      - 正式な選手名
      を snapshot row / `tournament_results` に保存するようにした。
    - その結果、大会成績一覧で「不明な選手」と出ていた状態を解消した。

  - 画面 / 導線:
    - `TournamentResultSnapshotController` と正式成績反映ページを追加した。
    - `routes/web.php` に正式成績反映ページの route を追加した。
    - 速報ページ `scores.result` に
      - 正式成績反映へ
      - 大会成績一覧へ
      の導線を追加した。
    - 大会検索 / 大会成績一覧側にも、正式成績反映ページへの導線を追加した。
    - `tournament_results` が空で、snapshot 側に current データだけが存在する場合の説明導線も整理した。

- 確認できたこと:
  - `prelim_total` の snapshot が `tournament_result_snapshots` / `tournament_result_snapshot_rows` に保存されることを確認した。
  - `final_total` を全体条件で反映すると、`tournament_results` へ同期されることを確認した。
  - 同期後の大会成績一覧で、選手名・順位・ポイント・賞金・トータルピン・ゲーム数・平均が表示されることを確認した。
  - 速報ページ → 正式成績反映 → 大会成績一覧 の運用導線が一応通る状態まで到達した。

- 現時点の判断:
  - **トータルピン方式については、速報入力 → snapshot 反映 → 最終成績同期 まで一連の運用導線が通った。**
  - ただし、途中成績（女子別 / シフト別 / 通算途中）の公開専用ページはまだ無く、現状は反映ページの履歴確認が中心。
  - 次の自然な後続は
    1. snapshot 専用の閲覧ページ追加
    2. ラウンドロビン / ステップラダー等の別方式拡張
    3. 最終成績同期後のタイトル反映・ポイント再計算との連携確認
    の順が安全。

## 2026-04-18 大会速報 → 正式成績反映単位の整理（トータルピン先行）

- 目的:
  - 4/18 時点で完成した「大会速報（ライブスコア）のトータルピン集計」を、次段で **正式成績へどう反映するか** を整理する。
  - 速報画面の完成後は、入力済み `game_scores` を「どの公開粒度で切り出して確定成績にするか」を先に固める必要がある。

- この時点の前提:
  - 速報入力の正本は `game_scores` とする。
  - ステージごとのゲーム数や有効ステージは `stage_settings` を前提に扱う。
  - 速報から正式成績への反映は **自動即時ではなく反映ボタン方式** とする。
  - まずは **トータルピン方式のみ** を先行対象とし、ラウンドロビン / ステップラダー / トーナメント / ダブルエリミネーション / シュートアウトは後続に分離する。

- JPBA公式ページを踏まえた公開粒度の考え方:
  - 大会成績は「大会全体で1回」ではなく、ステージ単体成績と通算成績を分けて公開する前提で考える。
  - このため JPBA-system 側でも、速報から正式成績へ反映する単位を **公開粒度ベース** で切る方針にする。

- トータルピン方式で先に正本化する反映単位:
  - 予選前半成績
  - 予選後半成績
  - 予選通算成績
  - 準々決勝成績
  - 準々決勝通算成績
  - 準決勝成績
  - 準決勝通算成績
  - 最終成績

- この時点の判断:
  - `game_scores` は速報入力の正本として継続使用する。
  - 正式成績は、速報をそのまま使い回すのではなく、**反映操作後の公開単位** として扱う。
  - ただし現時点では、正式成績保存先を
    - 既存 `tournament_results` に寄せるのか
    - 別の保存先を追加するのか
    までは未確定とする。
  - 先にトータルピン方式の反映単位だけを固定し、その後にラウンドロビン等の方式別ロジックへ拡張する。

- 次にやること:
  1. トータルピン方式の正式成績保存先を決める
  2. `game_scores` → 正式成績 への反映ボタン仕様を決める
  3. 反映対象ごとの順位 / ポイント / 賞金 / タイトルの扱いを整理する
  4. その後にラウンドロビン等の別方式へ拡張する

## 2026-04-18 大会速報（ライブスコア）トータルピン集計の安定化完了

- 目的:
  - `scores.input` → `scores.result` の速報導線について、トータルピン集計を実運用できる状態まで安定化する。
  - 特に
    - 2G/3G以降の入力継続
    - 下4桁ライセンス入力での過去点数参照
    - 個別削除の切り分け
    - ステージ間 carry（予選 / 準々決勝 / 準決勝 / 決勝）
    の整合を取る。

- 実施内容:
  - `app/Http/Controllers/ScoreController.php`
    - `ScoreController.php` の「1000行問題」を切り分け、ローカル実ファイル基準で確認し直した。
    - `store()` / `apiExistingIds()` / `buildHistoryPayload()` の流れを整理し、速報入力時に使う
      - `prevKeys`
      - `existsThisGame`
      - `ambiguousKeys`
      - `historyMap`
      の返却整合を調整した。
    - ライセンス番号下4桁入力時の過去点数参照・同一選手判定が通るよう、比較キーの正規化を整理した。
    - 個別更新 / 個別削除の切り分けも進めたが、途中でサンプルデータ混在が判明したため、大会単位クリアで仕切り直した。
  - `app/Services/ScoreService.php`
    - `〇ゲーム目まで` の切替で、現在ステージの全ゲーム内訳を保持したまま、集計だけを `upto_game` までに限定するよう整理。
    - これにより、速報表示で `3G → 2G → 3G` の切替ができる状態になった。
    - carry（持ち越し）対象ステージを整理し、
      - `準々決勝` では `予選`
      - `準決勝` では `予選 + 準々決勝`
      - `決勝` では `予選 + 準々決勝 + 準決勝`
      を条件に応じて合算できるよう修正した。
    - その結果、準決勝で準々決勝が反映されない問題、決勝で準決勝が反映されない見込み問題を解消した。
  - データ / 切り分け:
    - `AAAカップ` のサンプル速報データを `game_scores` から大会単位で削除し、混在データを一度クリアした。
    - その後、最小サンプルで
      - 1G入力
      - 2G入力
      - 準々決勝入力
      - 準決勝入力
      を順に再確認し、トータルピン集計の carry が正しく動くことを確認した。
    - 途中、`古川順子 / 0099` が見えているのに削除できない事象は、古いサンプル表示と実データ切り分けが混在していたため、まず大会単位クリアを優先した。

- この時点で完了したこと:
  - 速報入力ページへの導線追加
  - 大会 / ステージ / ゲーム番号 / シフト / 性別 / 識別方法の切替
  - 2ゲーム目以降の過去点数表示
  - 今回込合計の表示
  - ライセンス番号入力時の照合情報表示
  - 氏名入力時の候補表示
  - 2ゲーム目以降の候補表示を「大会登録選手のみ」に絞る対応
  - 速報ページで氏名表示
  - 速報ページ側で `〇ゲーム目まで` の切替UI追加
  - 下4桁ライセンス入力での過去点数参照・照合・同一選手判定
  - `3G → 2G → 3G` 切替
  - 準々決勝 / 準決勝 / 決勝を含むトータルピン carry 集計

- 現時点の判断:
  - **トータルピン方式の速報集計は、現時点でコンプリート** とみなしてよい段階に入った。
  - 一方、今回の作業では docs への記録が後追いになったため、次バッチでは進捗完了時に
    - `docs/chat/worklog_db.md`
    - `docs/chat/progress_board.md`
    を先に更新する流れへ戻す。
  - 次の自然な後続は、
    - トーナメント方式以外（ラウンドロビン / トーナメント / ダブルエリミネーション / シュートアウト）
    - 速報から正式成績への反映導線
    - 必要なら `docs/db` 側の正式整理
    の順で進めるのが安全。

## 2026-04-15 大会詳細 edit 側の再編集整合

- 目的:
  - `tournaments.edit` で既存大会の詳細構造を正しく再編集できるようにする。
  - 特に
    - `sidebar_schedule`
    - `award_highlights`
    - `result_cards`
    - `tournament_files`
    - `tournament_organizations`
    の既存値表示・保持・更新を安定化する。

- 実施内容:
  - `app/Http/Controllers/TournamentController.php`
    - `validateAndNormalize()` に抽選運営設定を追加し、edit 画面の
      - `use_shift_draw`
      - `shift_codes`
      - `accept_shift_preference`
      - `shift_draw_open_at / shift_draw_close_at`
      - `use_lane_draw`
      - `lane_assignment_mode`
      - `lane_from / lane_to`
      - `box_player_count / odd_lane_player_count / even_lane_player_count`
      を保存対象へ反映。
    - `title_logo` の保存処理が二重に走っていたため整理し、`title_logos` 配下へ統一。
    - 既存ロジックのまま
      - `sidebar_schedule`
      - `award_highlights`
      - `gallery_items`
      - `simple_result_pdfs`
      - `result_cards`
      - `tournament_files`
      を更新できるよう、edit 側入力と整合する controller に整理。
  - `resources/views/tournaments/edit.blade.php`
    - 組織・右サイド日程・褒章・結果カードで、既存値を初期表示できるよう整理。
    - バリデーションエラー後は `old()` を優先して再描画できるよう修正。
    - PDF差し替え導線を追加し、
      - `outline_public`
      - `outline_player`
      - `oil_pattern`
      - `custom`
      の現在値確認と追加・差し替えが可能なUIへ整理。
    - 既存ギャラリー / 既存簡易速報PDF の keep 入力名を index 付きに修正し、controller 側の期待構造と一致させた。

- 現時点の判断:
  - 大会詳細は
    - create / clone
    - edit / update
    - show
    の主要導線が揃った。
  - 次段では、必要に応じて create 側にも edit と同じ水準の
    - 右サイド / 褒章 / 結果カード
    - PDF添付の細かい入力UI
    をそろえていけばよい。

## 2026-04-15 大会詳細 clone/create 導線の実働化と辞書正本合わせ

- 目的:
  - `TournamentController@clone()` で前回大会を下書きコピーしても、create 画面の `old(...)` ベース入力へ十分に反映されない問題を解消する。
  - あわせて、`tournament_files` / `tournament_organizations` / `tournaments` の詳細系定義を、実装済みの migration / model / controller に合わせて辞書正本へ反映する。

- 実施内容:
  - `app/Http/Controllers/TournamentController.php`
    - `create()` で `tournament_prefill` を `flashInput()` へ流し、create 画面の `old(...)` に clone 下書きが乗るよう修正。
    - `clone()` で大会基本情報、抽選運営設定、右サイド / 褒章 / 結果カードのテキスト系情報を下書きコピーするよう整理。
    - 日付 / 日時系は毎回見直す前提で空にする方針へ整理。
    - `buildPrefillOldInput()` / `buildPrefillScheduleRows()` / `buildPrefillAwardRows()` / `buildPrefillResultCardRows()` を追加。
  - `resources/views/tournaments/create.blade.php`
    - clone 由来の下書き読み込み中であることを示す案内を追加。
  - `docs/db/data_dictionary.md`
    - `tournaments` に詳細表示用JSON・画像系・メディア系の現行定義を反映。
    - `tournament_files` を `type / title / file_path / visibility / sort_order` へ修正。
    - `tournament_organizations` を `category / name / url / sort_order` へ修正。

- 現時点の判断:
  - 大会詳細まわりは、DBスキーマ自体は既に実装済みで、今回は clone/create 導線と辞書正本のズレ修正が中心。
  - 画像 / PDF の既存アップロード済みファイルを完全自動複製する運用は別論点とし、今回はまずテキスト系・構造系の下書きコピーを実働化した。

## 2026-04-14 抽選運用ログの管理画面化

- 目的:
  - 既に実装済みの
    - 未抽選DM（手動 / 自動）
    - 締切到来後の事務局側 自動一括抽選
    の履歴を、管理画面から確認できるようにする。
  - あわせて、送信失敗や自動抽選失敗の明細を大会単位で追えるようにする。

- 実施内容:
  - `app/Http/Controllers/TournamentOperationLogController.php`
    - 大会ごとの運用ログ一覧画面を追加。
    - `tournament_draw_reminder_logs`
    - `tournament_auto_draw_logs`
    を大会単位で参照し、絞り込み・集計表示できるようにした。
  - `resources/views/tournament_entries/operation_logs.blade.php`
    - 未抽選DM送信履歴
    - 締切到来後の自動一括抽選ログ
    を1画面で確認できる管理画面を追加。
    - 失敗明細は details 表示で展開確認できるようにした。
  - `resources/views/tournaments/index.blade.php`
    - 大会一覧に `運用ログ` 導線を追加。
  - `resources/views/tournament_entries/admin_draws.blade.php`
    - 抽選一覧の上部導線に `運用ログ` を追加。
  - `routes/web.php`
    - `tournaments.operation_logs.index` を追加。

- 現時点の判断:
  - 大会抽選導線は
    - 本人抽選
    - 管理者の手動一括抽選
    - 未抽選DM（手動 / 自動）
    - 締切到来後の事務局側 自動一括抽選
    - それらの運用ログ確認
    まで一通り揃った。
  - 今後さらに必要になれば、再送・再抽選の実行導線はこの運用ログ画面を起点に追加できる。

## 2026-04-14 締切到来後の事務局側 自動一括抽選

- 目的:
  - 未抽選DMで告知したとおり、締切を過ぎても本人が抽選していないエントリー済み選手について、事務局側で自動一括抽選できるようにする。
  - あわせて、いつ・どの大会で・何件処理したかをDB上に記録できるようにする。

- 実施内容:
  - `database/migrations/2025_09_01_000080_create_tournament_auto_draw_logs_table.php`
    - `tournament_auto_draw_logs` を追加。
    - 大会ごとの自動一括抽選実行履歴を保持できるようにした。
  - `app/Services/TournamentAutoDrawService.php`
    - 締切到来後の未抽選者を抽出し、
      - シフト自動一括抽選
      - レーン自動一括抽選
      を実行する共通処理を追加。
    - 既存の抽選ロジックと同じ割付方針で自動確定するよう整理。
  - `app/Console/Commands/RunTournamentAutoDraws.php`
    - 自動一括抽選用の Artisan Command を追加。
  - `app/Console/Kernel.php`
    - `tournament:auto-draw-pending` を hourly で登録。
  - `docs/db/data_dictionary.md`
    - `tournament_auto_draw_logs` を追加し、`tournaments` の運用方針にも自動一括抽選を追記。
  - `docs/db/ER.dbml`
    - 辞書から再生成。

- 現時点の判断:
  - 大会抽選導線は
    - 会員本人抽選
    - 管理者の手動一括抽選
    - 未抽選DM（手動 / 自動）
    - 締切到来後の事務局側 自動一括抽選
    まで揃った。
  - 今後、管理画面で自動一括抽選ログを参照する画面が必要になれば、`tournament_auto_draw_logs` を正本として追加すればよい。

## 2026-04-14 未抽選DMの送信日直接指定（シフト / レーン分離）

- 目的:
  - 既存の「何日前 + 1セット設定」ではなく、
    - シフト未抽選DM
    - レーン未抽選DM
    を別々に管理し、送信日を直接指定できるようにする。
  - あわせて、メール本文に「期日までに未対応なら事務局側で一斉抽選を行う」旨を自動で含める。

- 実施内容:
  - `database/migrations/2025_09_01_000079_add_direct_send_dates_to_tournaments_draw_reminders_table.php`
    - `tournaments` に
      - `shift_auto_draw_reminder_enabled`
      - `shift_auto_draw_reminder_send_on`
      - `lane_auto_draw_reminder_enabled`
      - `lane_auto_draw_reminder_send_on`
      を追加。
    - 既存の
      - `auto_draw_reminder_enabled`
      - `auto_draw_reminder_days_before`
      - `auto_draw_reminder_pending_type`
      から、新カラムへ初期バックフィルを行う。
  - `app/Services/TournamentDrawReminderService.php`
    - 自動送信を「何日前逆算」から「送信日直接指定」へ変更。
    - シフト未抽選 / レーン未抽選を別々に判定して送信できるよう整理。
    - 本文トークンに締切日時と「事務局側で一斉抽選する」文言を追加。
  - `app/Http/Controllers/TournamentDrawReminderController.php`
    - 手動送信の初期文面も service のテンプレートを使うよう整理。
  - `app/Http/Controllers/DrawController.php`
    - 抽選設定画面から
      - シフト未抽選DM 送信有無 / 送信日
      - レーン未抽選DM 送信有無 / 送信日
      を保存できるよう拡張。
  - `app/Models/Tournament.php`
    - 新カラムを `fillable` / `casts` に追加。
  - `resources/views/tournaments/draw_settings.blade.php`
    - 未抽選DM自動送信UIを
      - シフト用
      - レーン用
      に分割し、送信日直接指定UIへ変更。
  - `docs/db/data_dictionary.md`
    - `tournaments` の未抽選DM定義を直接送信日方式へ更新。
  - `docs/db/ER.dbml`
    - 辞書から再生成。

- 現時点の判断:
  - 未抽選DMは
    - 手動送信
    - 一括抽選
    - 自動送信
    に加え、
    - 送信日直接指定
    - シフト / レーン分離
    まで対応できるようになった。
  - 旧「何日前」方式のカラムは互換保持のため残すが、今後の新規運用では新カラムを正とする。

## 2026-04-14 未抽選DMの自動送信

- 目的:
  - 手動送信まで実装済みの未抽選DMについて、締切○日前に自動送信できるようにする。
  - あわせて、自動送信の二重送信を防ぐ最低限の履歴管理を入れる。

- 実施内容:
  - `tournaments`
    - `auto_draw_reminder_enabled`
    - `auto_draw_reminder_days_before`
    - `auto_draw_reminder_pending_type`
    を追加。
  - `tournament_draw_reminder_logs`
    - 手動送信 / 自動送信の履歴を保持する新規テーブルを追加。
    - 自動送信は `dispatch_key` で重複防止。
  - `app/Services/TournamentDrawReminderService.php`
    - 手動送信と自動送信の共通処理を追加。
    - 未抽選対象抽出、本文トークン置換、送信履歴記録を共通化。
  - `app/Http/Controllers/TournamentDrawReminderController.php`
    - 手動送信を service 経由に整理。
  - `app/Console/Commands/SendTournamentDrawReminders.php`
    - 自動送信用 Artisan Command を追加。
  - `app/Console/Kernel.php`
    - `tournament:send-draw-reminders` を dailyAt('09:00') で登録。
  - `app/Http/Controllers/DrawController.php`
    - 抽選設定画面から自動送信設定も保存できるよう拡張。
  - `resources/views/tournaments/draw_settings.blade.php`
    - 自動送信ON/OFF、何日前、対象種別を設定できるUIを追加。
  - `docs/db/data_dictionary.md`
    - `tournaments` と `tournament_draw_reminder_logs` の正本定義を更新。
  - `docs/db/ER.dbml`
    - 辞書から再生成。

- 現時点の判断:
  - 未抽選DMは
    - 手動送信
    - 一括抽選
    - 自動送信
    まで一通り揃う。
  - 今回の自動送信は「1回分の自動通知」を安全に行うことを主眼にし、`dispatch_key` による重複防止を入れている。

## 2026-04-14 未抽選者の一括抽選（事務局対応）

- 目的:
  - 期日までに本人が抽選を行わなかった選手について、事務局側で未抽選者をまとめて抽選できるようにする。

- 実施内容:
  - `app/Http/Controllers/DrawController.php`
    - 管理者用の一括抽選 `bulk()` を追加。
    - `target = all / shift / lane` を受け取り、
      - 未抽選者を一括抽選
      - シフト未抽選のみ一括抽選
      - レーン未抽選のみ一括抽選
      に対応。
    - 事務局による一括抽選では、会員向けの抽選受付期間制限をバイパスできるようにした。
    - 既存の `performShiftDraw()` / `performLaneDraw()` を再利用し、個別抽選と同じロジックで確定するよう整理。
  - `resources/views/tournament_entries/admin_draws.blade.php`
    - 抽選一覧に
      - 未抽選者を一括抽選
      - シフト未抽選だけ一括抽選
      - レーン未抽選だけ一括抽選
      のボタンを追加。
  - `routes/web.php`
    - `tournaments.draws.bulk` を追加。

- 現時点の判断:
  - 会員本人が抽選しなかった場合でも、事務局側で一括処理できる運用が可能になった。
  - 今回は既存の抽選ロジックを流用しているため、DBスキーマ変更は不要。

## 2026-04-13 大会運営設定・希望シフト・未抽選DM

- 目的:
  - 大会ごとに「シフト抽選の有無」「レーン抽選の有無」「使用レーン範囲」「BOX運用」「希望シフト受付」を設定できるようにする。
  - 会員エントリー時に希望シフトを受け付け、管理者側で確認できるようにする。
  - 抽選未完了の選手へ、管理者が手動で一括DM送信できる導線を追加する。

- 実施内容:
  - `tournaments`
    - `use_shift_draw`
    - `use_lane_draw`
    - `lane_assignment_mode`
    - `box_player_count`
    - `odd_lane_player_count`
    - `even_lane_player_count`
    - `accept_shift_preference`
    を追加。
  - `tournament_entries`
    - `preferred_shift_code`
    を追加。
  - `TournamentController`
    - 大会作成 / 編集時に上記設定を保存できるよう拡張。
  - `DrawController`
    - 運営 / 抽選設定画面を拡張。
    - シフト抽選なし / レーン抽選なし大会に対応。
    - BOX運用時のレーン割付ロジックに対応。
    - 希望シフトがある場合、最少人数候補内なら優先採用するよう補強。
  - `TournamentEntryController`
    - 会員エントリー時の希望シフト保存を追加。
  - `TournamentEntryAdminController`
    - 管理者用一覧 / 抽選一覧で希望シフト確認を追加。
  - `TournamentDrawReminderController`
    - 未抽選者（シフト / レーン / 両方）を対象に、手動一括DM送信画面を追加。
  - `resources/views/tournaments/draw_settings.blade.php`
    - 運営 / 抽選設定画面として再整理。
  - `resources/views/member/entry_select.blade.php`
    - 希望シフト入力欄を追加。
  - `resources/views/tournament_entries/admin_index.blade.php`
  - `resources/views/tournament_entries/admin_draws.blade.php`
    - 希望シフト列と未抽選DM導線を追加。
  - `docs/db/data_dictionary.md`
    - `tournaments` / `tournament_entries` の正本定義を更新。
  - `docs/db/ER.dbml`
    - 辞書から再生成。

- 現時点の判断:
  - ① シフト有無
  - ② 使用レーン範囲
  - ③ BOX運用
  - ④ 希望シフト受付
  - ⑤ 未抽選者への手動DM
  までは実務運用可能な状態に近づいた。
  - ただし「締切1週間前に自動で送る」処理は、Scheduler / Command / 再送制御を含むため次段階に分離する。

## 2026-04-11 TOURNAMENT ENTRY 後続一覧 + waitlist

- 目的:
  - 大会エントリー後の管理導線を整備し、
    - 管理者用の参加一覧
    - 管理者用の抽選結果一覧
    - 管理者用の未抽選一覧
    - 参加選手向けの参加一覧 / 抽選結果一覧
    を追加する。
  - あわせて、大会参加権利外の選手を待機させる waitlist 運用を `tournament_entries` 正本上で扱えるようにする。

- 実施内容:
  - `database/migrations/2026_04_11_000001_add_waitlist_columns_to_tournament_entries_table.php`
    - `tournament_entries` に
      - `waitlist_priority`
      - `waitlisted_at`
      - `waitlist_note`
      - `promoted_from_waitlist_at`
      を追加。
  - `app/Models/TournamentEntry.php`
    - waitlist 関連カラムを `fillable` / `casts` に追加。
  - `app/Http/Controllers/TournamentEntryAdminController.php`
    - 管理者用の参加一覧
    - 抽選結果一覧
    - 未抽選絞り込み
    - waitlist 登録
    - waitlist から参加への繰り上げ
    を追加。
  - `app/Http/Controllers/TournamentEntryPublicController.php`
    - 参加選手向けの参加一覧 / 抽選結果一覧を追加。
  - `resources/views/tournament_entries/admin_index.blade.php`
  - `resources/views/tournament_entries/admin_draws.blade.php`
  - `resources/views/member/tournament_entries_index.blade.php`
  - `resources/views/member/tournament_draws_index.blade.php`
    - 上記画面を追加。
  - `resources/views/member/entry_select.blade.php`
    - 参加一覧 / 抽選結果への導線を追加。
    - `status = waiting` の行は、通常エントリーと分けて表示するように整理。
  - `resources/views/tournaments/index.blade.php`
    - 管理者 / 編集者向けに
      - エントリー一覧
      - 抽選一覧
      - 未抽選一覧
      の導線を追加。
  - `docs/db/data_dictionary.md`
    - `tournament_entries` を waitlist / 抽選 / チェックインを含む正本定義へ更新。
  - `docs/db/ER.dbml`
    - 辞書から再生成。

- 現時点の判断:
  - 大会エントリー後続の管理導線は、今回の一覧整備で実務運用可能な状態に近づいた。
  - `tournament_participants` は現時点では正本にせず、`tournament_entries` を参加導線の正本として維持する。

## 2026-04-09 INSTRUCTOR 資格遷移の先回り検証と import/current-history 補強

- 目的:
  - 認定インストラクター / プロインストラクター / プロボウラー間の資格遷移について、
    将来発生し得るケースを先回りで検証し、`instructor_registry` の current/history 運用を実装どおりに固める。
  - 併せて、`AuthInstructor.csv` と `Pro_colum.csv` の取込後に未結線認定を人手で処理できる運用導線を整える。

- 対象ケース:
  - ① 認定インストラクター → プロインストラクター / プロボウラーへの昇格
  - ② 認定インストラクター未更新者の失効（退会済み扱い）
  - ③ プロインストラクター → プロボウラーへの昇格
  - ④ プロボウラー / プロインストラクター → 認定インストラクターへの降格

- 実施内容:
  - `resources/views/instructors/pdf.blade.php`
    - 一覧と同じ表示方針へ寄せ、`識別番号` / `更新年度` / `更新期限` / `更新状態` / `更新日` をPDFにも表示するよう修正。
  - `app/Http/Controllers/ProBowlerImportController.php`
    - `license_no + instructor_category` 単位で `pro_bowler_csv` row を current/history 管理するよう整理。
    - 資格カテゴリ変更時に既存 current row を上書きせず、旧行を `supersede_reason` 付きで履歴化し、新資格 row を別 current row として立てるよう修正。
    - プロ系資格対象外になった場合、
      - 有効な `certified` 行があるときは `downgraded_to_certified`
      - 復帰先の有効な `certified` 行が無いときは `qualification_removed`
      で履歴化するよう整理。
  - `app/Http/Controllers/AuthInstructorImportController.php`
    - `AuthInstructor.csv` 取込時、`license_no` 一致を最優先に `pro_bowlers` と自動結線するよう修正。
    - `license_no` が空、または一致しない場合は、`name_kanji` を含む複数条件（例: `name_kana` / `sex` / `district_id`）で一意に特定できた場合のみ `pro_bowlers` に自動結線するよう修正。
    - 自動結線できた認定行は `pro_bowler_id` と補完 `license_no` を保持できるよう整理。
  - 検証用CSV（repo管理外）
    - `pro_bowlers_test_step1/2.csv`
    - `auth_instructors_test_step1/2.csv`
    を作成し、資格遷移の4パターンを意図的に再現できるようにした。
  - `app/Http/Controllers/InstructorController.php`
  - `resources/views/instructors/index.blade.php`
    - `/instructors` に `unlinked_certified` フィルタを追加。
    - `source_type = auth_instructor_csv` / `instructor_category = certified` / `pro_bowler_id is null` の current 行を抽出できるようにした。
  - `app/Http/Controllers/InstructorController.php`
  - `resources/views/instructors/edit.blade.php`
    - `auth_instructor_csv` 由来の `certified` 行について、編集画面から手動で `pro_bowlers` に結線できるよう修正。
  - `resources/views/instructors/index.blade.php`
    - 一覧に `結線先プロ` / `取込元` / `履歴理由` を追加し、未結線・昇格・降格・失効の状態を画面で判別しやすくした。
  - `docs/db/data_dictionary.md`
    - `instructor_registry` の運用方針に、上記の自動結線・昇格・降格・失効ポリシーを反映済み。
  - `docs/db/refs_skipped.md`
    - 過去の review 前提メモが残っていたため、現行運用に合わせて追補が必要。

- 検証結果:
  - ① 認定 → プロインストラクター：確認済み
  - ② 認定未更新 → 失効（`certified_not_renewed` + `expired`）：確認済み
  - ③ プロインストラクター → プロボウラー：確認済み
  - ④ プロ側 → 認定降格：
    - 認定側 current 復帰は確認済み
    - 当初は旧プロ側 row の `supersede_reason` が `qualification_removed` になっていたため、`downgraded_to_certified` を記録するよう importer を追加修正して解消

- 現時点の判断:
  - `instructor_registry` の資格遷移（昇格 / 降格 / 未更新失効）の current/history 管理は、先回り検証まで含めて運用可能な状態になった。
  - 残課題は「自動結線できなかった認定行をどう見つけて運用するか」だったが、
    - 一覧の `未結線認定` フィルタ
    - 編集画面の手動結線
    - 一覧の `結線先プロ` / `取込元` / `履歴理由`
    まで入り、実務運用可能な導線が揃った。
  - 今回はアプリ実装と履歴整理のみであり、DBスキーマ変更は発生していないため migration 変更は無し。
  
## 2026-04-05 INSTRUCTOR AuthInstructor.csv 取込導線とプロインストラクター整合

- 目的:
  - 認定インストラクターの一括投入元 `AuthInstructor.csv` を正式導線にし、`pro_bowler` / `pro_instructor` / `certified` の3分類を current 正本 `instructor_registry` で扱える状態にする。
  - あわせて、ティーチングプロの判定を `license_no like '%T%'` ではなく `member_class` / `instructor_category` 基準へ揃える。

- 実施内容:
  - `database/migrations/2025_09_02_000207_add_member_class_and_entry_flag_to_pro_bowlers_table.php`
    - `pro_bowlers` に `member_class` / `can_enter_official_tournament` を追加。
  - `database/migrations/2025_09_02_000208_add_current_tracking_to_instructor_registry_table.php`
    - `instructor_registry` に `source_registered_at` / `is_current` / `superseded_at` / `supersede_reason` を追加。
  - `app/Http/Controllers/AuthInstructorImportController.php`
    - `AuthInstructor.csv` 取込処理を追加。
    - `source_type = auth_instructor_csv` / `source_key = #ID` / `cert_no = #ID` / `instructor_category = certified` で投入。
  - `app/Models/InstructorRegistry.php`
    - current/history 用カラムの `fillable` / `casts` を追加。
  - `routes/web.php`
    - `instructors/import/auth` の GET / POST ルートを追加。
  - `resources/views/instructors/import_auth.blade.php`
    - `AuthInstructor.csv` 取込画面を追加。
  - `app/Http/Controllers/ProBowlerImportController.php`
  - `app/Http/Controllers/ProBowlerController.php`
    - ティーチングプロ判定を `resolveMemberClass()` へ寄せ、`T015` / `M0000T015` / `F0000T004` のような教示系ライセンスを `pro_instructor` として扱うよう修正。
    - `member_class` / `can_enter_official_tournament` を `pro_bowlers` へ保存し、その値から `instructor_registry.instructor_category` を `pro_bowler` / `pro_instructor` に振り分けるよう整理。
  - `app/Models/ProBowler.php`
    - `member_class` / `can_enter_official_tournament` を `fillable` / `casts` に追加。
  - `app/Http/Controllers/InstructorController.php`
    - current 正本 `instructor_registry` 基準の一覧・検索・PDF を維持しつつ、認定インストラクター / プロインストラクター / プロボウラー兼インストラクターの検索条件を整理。
  - `docs/db/data_dictionary.md`
    - `instructor_registry` に current/history と `auth_instructor_csv` / `pro_bowler_csv` を反映。
    - `pro_bowlers` に `member_class` / `can_enter_official_tournament` の運用方針を反映。
  - `docs/db/refs_skipped.md`
    - `AuthInstructor.csv` を正式投入元として整理し、`current/history` ポリシーを追記。
  - `docs/db/ER.dbml`
    - 辞書から再生成。

- 確認結果:
  - `AuthInstructor.csv` 取込 route:
    - `GET instructors/import/auth`
    - `POST instructors/import/auth`
    が有効。
  - `AuthInstructor.csv` を取り込み後、`/instructors` 一覧で認定インストラクター検索が動作することを確認。
  - `pro_bowlers.member_class = pro_instructor` 件数: 23
  - `instructor_registry.instructor_category = pro_instructor AND is_current = true` 件数: 23
  - `M0000T015` は最終的に
    - `pro_bowlers.member_class = pro_instructor`
    - `pro_bowlers.can_enter_official_tournament = false`
    - `instructor_registry.instructor_category = pro_instructor`
    で整合することを確認。

- 現時点の判断:
  - `認定インストラクター / プロインストラクターの投入経路整理` は完了扱いでよい。
  - ただし、同一人物の alias / 旧ライセンス表記をどのタイミングで `is_current = false` に落とすかは後続で整理する。
  - `/pro_bowlers` 側のプロインストラクター確認は、今後 `license_no` 文字列検索ではなく `member_class = pro_instructor` を正本条件とする。


## 2026-04-03 INSTRUCTOR ProBowlerController の同期整合
- `ProBowlerController` の `syncInstructor()` は、これまで `instructors` しか更新しておらず、画面からの管理者保存後に `instructor_registry` 正本が更新されないズレがあった。
- 一方で `ProBowlerImportController` では、`syncLegacyInstructorFromBowler()` と `syncRegistryFromBowler()` の両方が走るため、CSV再取込時だけ `instructors` / `instructor_registry` の両方が同期される状態だった。
- 今回、`ProBowlerController` にも `InstructorRegistry` 同期処理を追加し、管理画面保存時の同期先を importer と揃えた。
- 同期条件も importer と揃え、A級 / B級 / C級、マスター、スクール開講資格、スポーツ協会認定コーチ、健康ボウリング指導士のいずれかがある場合のみ同期するよう整理した。
- 互換レイヤ `instructors` は引き続き残しつつ、新正本 `instructor_registry` も同時に更新される状態にした。
- 今回はアプリ実装の整合修正のみであり、DBスキーマ変更は発生していないため、`docs/db/data_dictionary.md` / `docs/db/ER.dbml` の更新は不要。

## 2026-04-03 INSTRUCTOR 投入元整理（authinstructor 仮説の解消）
- 目的:
  - `認定インストラクター` / `プロインストラクター` の投入元について、仮説ではなく現存データに合わせて整理し直す。

- 確認したこと:
  - `OLD_JPBA/csv` 配下には `Pro_colum.csv` しか存在しない。
  - `Pro_colum.csv` はプロボウラー正本CSVであり、A級 / B級 / C級 / マスター / スポーツ協会認定コーチ / USBCコーチ / スクール開講資格など、プロボウラー由来の資格情報を含む。
  - 一方で、認定インストラクター専用の元表は存在しない。
  - `OLD_JPBA` 配下を `authinstructor` / `AuthInstructor` で検索しても参照は見つからなかった。
  - 以前 repo 上に `App\Models\Legacy\AuthInstructorLegacy` が存在したため `authinstructor` を候補視していたが、現存データ根拠は確認できなかった。

- この時点の判断:
  - 現存する投入元データは `Pro_colum.csv` のみとする。
  - `プロボウラー` 由来インストラクターは `Pro_colum.csv` → `pro_bowlers` → `instructor_registry` 同期で扱う。
  - `認定インストラクター` は現時点では manual 登録が唯一の投入経路。
  - `authinstructor` を前提にした import 設計・legacy 接続待ちは打ち切り、docs 上の記述も現状に合わせて修正する。
  - 既存の `legacy_instructors` は、すでに `instructors` から bootstrap した履歴ソースとしてのみ残す。

- ドキュメント更新方針:
  - `docs/chat/progress_board.md`
    - `authinstructor` 候補/保留の表現を外し、現存元データは `Pro_colum.csv` のみと明記する。
  - `docs/db/data_dictionary.md`
    - `instructor_registry` の注意書きから `authinstructor` 仮説を削除し、実ソースを `legacy_instructors` / `pro_bowler` / `manual` に整理する。
  - `docs/db/refs_skipped.md`
    - `authinstructor` 保留セクションを削除し、「認定インストラクター専用の元表は未存在、現状は manual 登録運用」とする注記へ差し替える。

- 補足:
  - 今回はスキーマ変更ではないため migration 変更は不要。
  - `data_dictionary.md` 更新後はルールどおり `php tools/generate_er_from_dictionary.php` を実行して `docs/db/ER.dbml` を再生成する。
  - ただし今回の変更は説明文のみのため、`ER.dbml` は無差分の可能性が高い。

## 2026-04-03 INSTRUCTOR instructor_registry 正本化の棚卸し
- `InstructorController` / `/instructors` 一覧・作成・編集・PDF が `InstructorRegistry` を参照していることを確認した。
- 一方で `Instructor` 直参照は `GroupRuleEngine` / `ProBowlerController` / `ProBowlerImportController` に残っていた。
- `ProBowlerImportController` は `syncLegacyInstructorFromBowler()` と `syncRegistryFromBowler()` の二重同期になっており、互換レイヤ `instructors` 維持の実装と判断した。
- `ProBowlerController` にも `Instructor::updateOrCreate()` が残っており、こちらも互換同期側の処理と整理した。
- `GroupRuleEngine` の `instructor_grade` 判定は旧 `instructors` を参照していたため、`InstructorRegistry` + `instructor_category = pro_bowler` 基準へ修正した。
- あわせて、ルール入力値 `A` / `B` / `C` と、DB保存値 `A級` / `B級` / `C級` の差を吸収するため、級表記の正規化を追加した。
- `mysql_legacy` 接続は `DB_MYSQL_*` 環境変数を見る設定だったが、`.env` に該当設定が無く、既定値 `forge` にフォールバックしていた。
- `authinstructor` は接続拒否で実表確認ができず、投入元確定は継続保留とした。
- この棚卸し段階ではスキーマ変更は発生していないため、`docs/db/data_dictionary.md` / `docs/db/ER.dbml` の更新は不要。

## 2026-03-18 INSTRUCTOR 手動登録した認定インストラクターの編集導線復旧

- 目的:
  - 認定インストラクターを手動登録したあと、一覧表示・氏名リンク・編集更新まで通る状態にする。

- 背景:
  - `instructor_registry` を読む一覧画面はすでに動いていたが、manual 登録した認定インストラクターは一覧上の氏名リンク条件から漏れ、編集画面へ入れなかった。
  - create / edit / index の導線を、現行の互換レイヤ運用に合わせて整える必要があった。

- 実施内容:
  - `app/Http/Controllers/InstructorController.php`
    - 既存の create / store / edit / update を維持しつつ、保存・更新後に `instructor_registry` 同期が走る前提で manual 登録導線を整理。
  - `resources/views/instructors/create.blade.php`
    - 認定インストラクター登録時の入力UIを調整。
  - `resources/views/instructors/edit.blade.php`
    - 認定インストラクター編集時の入力UIを調整。
  - `resources/views/instructors/index.blade.php`
    - manual 登録行でも氏名をリンク表示し、編集画面へ遷移できるよう修正。

- 確認結果:
  - 認定インストラクターを手動登録できることを確認。
  - `/instructors` 一覧に認定インストラクターが表示されることを確認。
  - 一覧の氏名リンクから編集画面へ遷移できることを確認。
  - 編集更新後の変更が一覧へ反映されることを確認。

- 現時点の判断:
  - manual source の認定インストラクターについて、登録 → 一覧表示 → 編集更新 の基本導線は完了扱いでよい。
  - ただし `authinstructor` 由来データの一括投入は未着手のため、投入元確定タスクは継続する。

## 2026-03-13 instructor_registry 参照化（第2段階）

- 目的:
  - 第1段階で作成した `instructor_registry` を、実際の一覧画面 / PDF 出力 / CSV再取込同期で使う側へ寄せる。
  - 旧 `instructors` 依存を段階的に薄め、新正本へ移行する。

- 実施内容:
  - `app/Http/Controllers/InstructorController.php`
    - 一覧とPDFの検索元を `Instructor` から `InstructorRegistry` に変更。
    - フィルタ条件は `name / license_no / district_id / sex / instructor_class / grade` を `instructor_registry` 基準で適用。
    - 既存の create / store / edit / update は当面維持しつつ、保存時に `instructor_registry` も同期する構成へ変更。
  - `app/Http/Controllers/ProBowlerImportController.php`
    - `pro_bowlers` 再取込時に、旧 `instructors` だけでなく `instructor_registry` も同時同期する処理を追加。
    - `A級 / B級 / C級` から `grade` を決定し、`instructor_category = pro_bowler` として登録するよう整理。
  - `resources/views/instructors/index.blade.php`
    - 一覧画面を `instructor_registry` 前提の項目表示へ変更。
    - ライセンスNo. / 認定番号 / legacy license を吸収できる表示に変更。
  - `resources/views/instructors/pdf.blade.php`
    - PDF出力も `instructor_registry` の表示仕様へ合わせて修正。

- 確認結果:
  - `instructor_registry_total = 1345`
  - `instructor_category = pro_bowler` が 1345件
  - `/instructors` 画面で検索確認済み
    - 例: 九州北 × 男性 × プロボウラー × C級 = 33件
  - 一覧画面は `instructor_registry` 読みへ切替済み

- 現時点の判断:
  - 第2段階（読む側の registry 参照化）は完了扱いでよい。
  - ただし `pro_instructor` / `certified` はまだデータ未投入のため、今後は投入元の確定が必要。
  - 旧 `instructors` は当面互換維持とし、完全撤去は後続タスクに分離する。

## 2026-03-12 INSTRUCTOR 新正本 `instructor_registry` 追加準備

- 目的:
  - `instructors` が `license_no` 主キー前提で、認定インストラクターのような「license_no 前提でない行」を自然に保持しづらい問題を、既存画面を壊さずに解消する。

- 判断:
  - 既存 `instructors` は互換レイヤとして残す。
  - 新正本 `instructor_registry` を追加し、以後の管理対象をこちらへ段階移行する。
  - 今回は非破壊変更のみとし、既存画面・既存Controllerの書込先まではまだ切り替えない。

- 実施内容:
  - `database/migrations/2025_09_02_000205_create_instructor_registry_table.php`
    - 新正本 `instructor_registry` を追加。
    - 正本キーを `(source_type, source_key)` に変更。
    - `license_no` / `cert_no` を nullable にし、source 単位で一意管理できる形にした。
    - `instructor_category` を `pro_bowler / pro_instructor / certified` に整理。
  - `database/migrations/2025_09_02_000206_bootstrap_instructor_registry_from_instructors_table.php`
    - 既存 `instructors` を `source_type = legacy_instructors` として新正本へ bootstrap。
  - `docs/db/data_dictionary.md`
    - `instructor_registry` を追加。
    - `instructors` を「互換テーブル」として再定義。
  - `docs/db/ER.dbml`
    - 辞書更新後に再生成。
  - `docs/db/refs_skipped.md`
    - `legacy_instructor_license_no` を移行用の非FK列として明記。

- 設計要点:
  - 既存 `instructors` の主キーは今後も `license_no` のまま残す。
  - 新規正本は `id` 主キー + `(source_type, source_key)` 一意で管理する。
  - `legacy_instructor_license_no` は旧 `instructors.license_no` の退避列。
  - `license_no` が無い認定系でも `cert_no` や manual source で登録できる土台にした。

- 今回まだやっていないこと:
  - `InstructorController` の読込先切替
  - `ProBowlerImportController` の書込先切替
  - 旧 `authinstructor` 系データの取り込み

- 次タスク:
  - 既存画面の参照先を `instructor_registry` に段階移行する。
  - `authinstructor` の dump / CSV / 接続のどれかが取れたら、認定インストラクターを `source_type` 別に投入する。

## 2026-03-12 INSTRUCTOR 名簿表示改善

- 目的:
  - インストラクター一覧画面で、名簿表示に必要な項目が揃っているかを確認し、表示不具合を解消する。

- 調査結果:
  - `instructors` テーブル自体には名簿表示に必要な列が揃っていた。
    - `license_no`
    - `name`
    - `sex`
    - `district_id`
    - `instructor_type`
    - `grade`
    - `is_active`
    - `is_visible`
  - 一方で、実データは `instructors_total = 1` しかなく、`pro_bowlers` 側の資格フラグ件数（A/B/C級など）と大きく乖離していた。
  - 原因は、`ProBowlerController` のフォーム保存時には `syncInstructor()` が走るが、CSV再取込時の `ProBowlerImportController` では `instructors` 同期が行われていなかったこと。

- 実施内容:
  - `app/Http/Controllers/InstructorController.php`
    - 一覧検索処理を整理。
    - `district_id` / `sex` / `grade` / `instructor_class` の条件を正しく適用。
    - PDF出力でも現在の検索条件を使うよう修正。
  - `resources/views/instructors/index.blade.php`
    - 文字化けを解消。
    - 一覧表示列を整理（氏名 / ライセンスNo. / 地区 / 性別 / 種別 / 区分 / 有効 / 表示）。
    - 地区・性別・種別・区分の検索UIを修正。
  - `resources/views/instructors/pdf.blade.php`
    - 文字化けを解消。
    - 一覧画面と同じ主要列で PDF 出力するよう修正。
  - `app/Models/Instructor.php`
    - 種別ラベルの文字化けを修正。
  - `app/Http/Controllers/ProBowlerImportController.php`
    - `pro_bowlers` の資格フラグから `instructors` を同期する処理を追加。
  - `database/migrations/2025_09_02_000204_sync_pro_instructors_from_pro_bowlers.php`
    - 既存 `pro_bowlers` を元に `instructors` を backfill。
    - `A級 / B級 / C級` を単一の `grade` に正規化して投入。
  - `docs/db/data_dictionary.md`
    - `instructors` の役割と同期方針を更新。
  - `docs/db/ER.dbml`
    - 辞書から再生成。

- 実行後の確認結果:
  - `instructors_total = 1345`
  - `instructor_type = pro` のみ 1345件
  - `grade` 内訳
    - `A級` 104件
    - `B級` 189件
    - `C級` 1000件
    - `null` 52件
  - ブラウザ確認では、`プロボウラー × C級` の検索で 1000件が表示されることを確認。

- 結論:
  - `INSTRUCTOR` の「名簿表示に必要な項目が揃う」は完了扱いでよい。
  - なお、`プロインストラクター` / `認定インストラクター` が 0 件なのは検索不具合ではなく、現時点でその種別データが未投入であるため。これは別タスクとして扱う。

## 2026-03-12 INSTRUCTOR 認定系投入元確認（blocker整理）

- 目的:
  - `プロインストラクター` / `認定インストラクター` の投入元を確定する。

- 確認したこと:
  - repo 上では `App\Models\Legacy\AuthInstructorLegacy` が存在し、`authinstructor` を参照する想定になっていた。
  - 当初はモデル定義にコメント崩れがあり、`protected $table = 'authinstructor';` が実質無効になっていたため修正した。
  - PHP CLI 側の `pdo_mysql` / `mysqli` は有効化できた。
  - ただし `mysql_legacy` 接続は未到達だった。
    - `.env` に `DB_MYSQL_HOST` / `DB_MYSQL_PORT` / `DB_MYSQL_DATABASE` / `DB_MYSQL_USERNAME` が未設定
    - Windows 上で MySQL / MariaDB サービスが見つからない
    - 3306 / 3307 に待受が無い
  - repo / ローカル探索でも `authinstructor` の SQL ダンプや CSV は見つからなかった。
  - このため、`認定インストラクター` の投入元候補は `mysql_legacy.authinstructor` だが、現時点では実体確認できない。

- この時点の判断:
  - `プロボウラー` 由来インストラクターは `pro_bowlers` から同期する方針で確定。
  - `認定インストラクター` 由来は `authinstructor` 候補だが、legacy 未接続のため未確定。
  - 根拠不十分のため、`cert_no` 追加や `license_no` nullable 化などの schema 変更は行わない。
  - このタスクは blocker として保留し、legacy 接続情報または dump / CSV 入手後に再開する。

- 再開条件:
  - `mysql_legacy` に接続できる `.env` 設定
  - または `authinstructor` 相当の SQL / CSV / Excel データ

## 2026-03-11 INSTRUCTOR 区分マスタ確認

- 目的:
  - `INSTRUCTOR` の残タスクである「区分マスタ（A/B/C等）が確定」を整理する。

- 調査結果:
  - `instructors` テーブルには `grade` カラムが存在する。
  - インストラクター画面（create / edit / index）の選択肢は以下の7値で統一されていた。
    - `C級`
    - `準B級`
    - `B級`
    - `準A級`
    - `A級`
    - `2級`
    - `1級`
  - `pro_bowlers` 側には `a_class_status / b_class_status / c_class_status / master_status` があるが、これは資格保持フラグであり、`instructors.grade` の単一値とは別概念として扱うのが自然と判断。
  - `master_status` も `instructors.grade` には含めず、別資格として扱う方針にした。
  - `docs/db/data_dictionary.md` の `instructors` セクションでは `rank` と記載されていたが、実カラム名は `grade` であるため修正対象とした。

- 実施内容:
  - `database/migrations/2025_09_02_000203_add_check_constraint_to_instructors_grade.php`
    - `instructors.grade` に CHECK 制約を追加。
    - 許容値を `C級 / 準B級 / B級 / 準A級 / A級 / 2級 / 1級` に固定。
  - `docs/db/data_dictionary.md`
    - `instructors.grade` を正本カラムとして明記。
    - `master_status` は別資格であることを追記。
  - `docs/db/ER.dbml`
    - 辞書から再生成。

- 結論:
  - `INSTRUCTOR` の「区分マスタ（A/B/C等）が確定」は完了扱いでよい。

## 2026-03-10 pro_bowlers 地区・期別の再取込（新CSV正本への切替）

- 目的:
  - 既存 `pro_bowlers` の `district_id` / `kibetsu` が旧CSV由来で壊れていたため、「既存値の補正」ではなく **新CSVを正本として再取込** する方針へ切替。
  - `/athletes` の検索（名前・ライセンスNo・地区・期別）が成立する状態まで戻す。

- 前提整理:
  - 既存データでは `district_id` / `kibetsu` が大量に未反映。
  - 旧 backfill は `skipped_invalid_license=2263` で失敗。
  - 原因は「ライセンス列の読み違い」だけではなく、**期別データ自体が壊れていた**こと。
  - このため、CSV正本の再投入を優先し、DBスキーマ変更は行わない方針にした。

- 実施内容（コード修正）:
  - `app/Http/Controllers/ProBowlerImportController.php`
    - CSVのライセンス番号取得を見直し、`#ID` / `ライセンスNo` の両方に対応。
    - `F00000001` だけでなく `M0000K001` / `F0000T004` のような **途中英字を含む license_no** をそのまま正規化して保持する実装へ修正。
    - 取込時に `district_id` を再解決し、地区空欄は **「該当なし」** へ寄せる実装を追加。
    - 地区表記ゆれとして、中点入り（例: `九州・北` / `神奈川・西` / `関西・西` / `関西・南`）を吸収するように修正。
    - `T` から始まるライセンス番号は **ティーチングプロ** とみなし、`kibetsu` は常に `null` にするよう修正。
    - 更新時、CSV側が `null` の項目で既存 `district_id` / `kibetsu` / `pro_entry_year` を不用意に潰さないようガードを追加。
    - `dominant_arm` / `sex` / 郵便番号 / 電話番号などの正規化ロジックも再整理。

  - `database/seeders/DistrictSeeder.php`
    - ファイル自体の文字コード/文字化け状態を修正し、地区ラベルを **UTF-8の正しい日本語** で定義し直した。
    - `updateOrCreate` のキーを `label` ではなく `name` に変更し、既存 `name` unique 制約と衝突しないよう修正。
    - `not_applicable` / `該当なし` をシーダーに追加し、地区空欄の受け皿を永続化。

- 作業中に判明したこと:
  - `districts` は `name` が NOT NULL + UNIQUE のため、`label` だけで `該当なし` を追加しようとすると失敗する。
  - そのため `DistrictSeeder` で `name=not_applicable, label=該当なし` を持つ状態に整理した。
  - 残り未反映の地区は最終的に「表記ゆれ」ではなく **途中英字を含む license_no を importer が正しく突合できていなかったこと** が原因だった。

- 確認結果:
  - 再取込後の件数は以下。
    - total = 2267
    - district_filled = 2267
    - district_null = 0
    - kibetsu_filled = 2148
    - kibetsu_null = 119
  - `T` ライセンスで `kibetsu IS NOT NULL` は 0 件。
  - `/athletes` で **名前・ライセンスNo・地区・期別** の検索が通ることを確認。

- 結論:
  - **地区未反映は全件解消。**
  - **ティーチングプロは期別なし** の扱いも反映完了。
  - 残りの `kibetsu_null=119` は、CSV正本側の未設定/年度のみデータを含むため、今回の取込仕様では許容とする。

- 今回の変更ファイル:
  - `app/Http/Controllers/ProBowlerImportController.php`
  - `database/seeders/DistrictSeeder.php`

- ドキュメント更新方針:
  - 今回は **DBスキーマ変更なし** のため、
    - `docs/db/data_dictionary.md`
    - `docs/db/ER.dbml`
    - `docs/db/refs_missing.md`
    - `docs/db/refs_skipped.md`
    は更新なし。

- 追加確認（検索動作の実地確認）:
  - `/athletes`
    - 名前 / ライセンスNo / 地区 / 期別 に加え、**性別検索**も確認。
    - 男性指定時、一覧の性別列が男性で揃うことを確認。
  - `/pro_bowlers/list?id_from=1&id_to=20`
    - **Noレンジ検索**を確認。
    - 結果は 41件で、`F00000001` だけでなく `M0000K001` / `M0000P001` / `M0000S001` など、
      英字混在ライセンスも同じ数字帯として検索対象に入ることを確認。
  - `/pro_bowlers/list`
    - デフォルト表示は **1249件**。
  - `/pro_bowlers/list?include_inactive=1`
    - **2267件** となり、`退会者も含む` の切替が実際に動作することを確認。

- 今回の確認で確定したこと:
  - `PLAYER DATA` の検索条件のうち、**氏名 / Noレンジ / 地区 / 性別 / 退会者** は再現可能。
  - 画面の役割は以下のとおり。
    - `/athletes` = 簡易検索（氏名 / ライセンスNo / 地区 / 性別 / 期別）
    - `/pro_bowlers/list` = 詳細検索（Noレンジ / 会員種別 / 退会者を含む など）
- 追加確認（districts / sexes マスタ）:
  - `districts` 実値を確認。
    - 日本語ラベル自体は整理済み。
    - ただし `該当なし` が 2 件存在。
      - `id=25, name=該当なし, label=該当なし`
      - `id=27, name=not_applicable, label=該当なし`
    - 画面側は `label` を使って地区選択肢を出す箇所があるため、districts は **サイト表示と完全一致とはまだ言えない** と判断。
  - `sexes` 実値を確認。
    - `0=不明 / 1=男性 / 2=女性`
    - `pro_bowlers.sex` の表示・検索実装とも整合しており、sexes は一致扱いで問題なし。

- 現時点の判断:
  - `districts / sexes マスタがサイト表示と一致` のうち、
    - `sexes` は確認完了
    - `districts` は `該当なし` 重複の整理が残課題
- 追加対応（districts の `該当なし` 重複解消）:
  - `districts` には一時的に以下の 2 件が存在していた。
    - `id=25, name=該当なし, label=該当なし`
    - `id=27, name=not_applicable, label=該当なし`
  - 実参照を確認したところ、運用上の正本は `id=27 / name=not_applicable` 側だった。
    - `pro_bowlers.district_id=27` : 333件
    - `instructors.district_id=27` : 1件
  - `districts.id` を FK 参照しているのは `pro_bowlers.district_id` のみと確認。
    - その後、新規 migration `2025_09_02_000054_cleanup_duplicate_not_applicable_district` を追加して実行し、`該当なし` は以下の 1 件に統一された。
    - `id=27, name=not_applicable, label=該当なし`

- この時点の判断:
  - `districts / sexes マスタがサイト表示と一致` は完了扱いでよい。
  - 今回はスキーマ変更ではなくデータ整理のため、`docs/db/data_dictionary.md` / `docs/db/ER.dbml` の更新は不要。
- 追加確認（ライセンスNoの並び替え/レンジ検索設計）:
  - `ProBowlerController` で `license_no_num` を用いた並び替え・レンジ検索が実装済みであることを確認。
  - 互換入力として `id_from` / `id_to` を受けつつ、`license_pattern`（A/B）・`license_prefix` にも対応している。
  - `/pro_bowlers/list?id_from=1&id_to=20` の実地確認では 41件ヒットし、`F00000001` だけでなく `M0000K001` / `M0000P001` / `M0000S001` などの英字混在ライセンスも同じ数字帯として検索対象に入ることを確認。
  - 以上より、「文字混在を考慮したライセンスNoの並び替え/レンジ検索設計」は導入済みと判断。
- 追加確認（年度別成績サマリの保存先）:
  - `tournament_results` に以下の年度別成績サマリ構成要素がすでに存在することを確認。
    - `ranking`（順位）
    - `points`（ポイント）
    - `total_pin`（合計ピン）
    - `games`（ゲーム数）
    - `average`（アベレージ）
    - `prize_money`（賞金）
    - `ranking_year`（年度）
  - `TournamentResultController` でも、`ranking_year` を基準に `tournament_results` から年度別ランキングを集計していることを確認。
    - 賞金合計
    - ポイント合計
    - アベレージ集計
  - `pro_bowler_yearly` / `season_summary` / `result_summary` などの別テーブルは存在せず、現行設計では **`tournament_results` を正本として年度別サマリを集計利用する方針** と判断。
  - 以上より、`PLAYER DATA` の「年度別成績サマリ（順位/ゲーム数/ピン/ポイント/AVG/賞金）の保存先」は `tournament_results` で確定とした。
- 追加対応（pro_bowlers ステータス整合）:
  - 調査の結果、`pro_bowlers` は `membership_type` と `kaiin_status.is_retired` で退会判定できる一方、`is_active` がその状態を正しく表していなかった。
  - 確認時点では、`is_active = true` かつ `membership_type in (死亡, 除名, 退会届)` が 1018 件あり、ステータスが一意に決まらない状態だった。
  - 対応として以下を実施。
    - `database/migrations/2025_09_02_000043_add_is_retired_to_kaiin_status.php`
      - 文字化けを修正し、`死亡` / `除名` / `退会届` を `is_retired = true` とする定義を明確化。
    - `database/migrations/2025_09_02_000202_backfill_pro_bowlers_is_active_from_membership_type.php`
      - 既存 `pro_bowlers` 全件について、`membership_type` と `kaiin_status.is_retired` を正本に `is_active` を backfill。
    - `app/Http/Controllers/ProBowlerImportController.php`
      - 今後のCSV再取込でも `membership_type` から `is_active` を自動決定するよう修正。
    - `docs/db/data_dictionary.md`
      - `kaiin_status` と `pro_bowlers` の運用方針を更新。
    - `docs/db/ER.dbml`
      - 辞書から再生成。
  - 実行後の確認結果:
    - `active_but_retired = 0`
    - `inactive_but_not_retired = 0`
  - 以上より、`pro_bowlers` のステータス（現役/退会等）は一意に扱える状態になった。

## 2026-03-05 Codex導入（OpenAI Codex CLI）＋DBガードレール

- 目的:
  - ChatGPT/AIが「既存migrationを再生成」「存在しないカラムを参照」などの事故を起こしやすい問題を、リポジトリ側の“参照物”と“作業前チェック”で潰す。
  - 作業者がコードを読めなくても、AIが「現状の正本」を毎回確認できる状態を作る。

- 実施内容（要点）:
  - Node.js（Windows）導入で権限/セキュリティ制約に当たりやすい環境のため、PowerShellでは `.ps1` がブロックされる前提で運用。
    - 実行は `npm` ではなく `npm.cmd` / `npx.cmd` を優先（PowerShellの実行ポリシー回避）。
    - Codex起動も `codex` がダメなら `codex.cmd` を使う（PowerShellが `codex.ps1` を優先して失敗するケースを回避）。
  - Codex CLIの対話画面で貼り付けできない場合は、右クリック貼り付けで回避（Ctrl+Vが効かない環境向け）。
  - Codex CLI を導入し、リポジトリをスキャンしてから変更させる運用へ切替（手元の前提共有を毎回貼り直さないため）。

- DBガードレール（新規追加・正本化）:
  - `docs/db/SCHEMA.sql` を作成（pg_dump -sでスキーマだけをスナップショット化）
    - 以後「実DBの現物スキーマ確認」はこのファイルを参照する。
  - `docs/db/MIGRATIONS_INDEX.md` を作成（database/migrationsの一覧を可視化）
    - タイムスタンプ重複（例：`2025_09_02_000026` が2件）を検知できる状態に。
  - `docs/db/PREFLIGHT.md` を作成（作業前チェックリスト）
    - 重複timestampの既存2件は、migrate履歴を壊す可能性があるため「既知のレガシー問題」として扱い、無計画にリネーム/削除しない。

- コミット:
  - `24050b8923ab50c7b78d87fb37b469ec7a093663` Add DB schema snapshot and migration preflight docs
  - 短縮表記: `24050b8923...`

- 今後の運用ルール（超重要）:
  1) DB変更（migration追加/修正）をする前に `docs/db/PREFLIGHT.md` を必ず通す
  2) DB変更後は `docs/db/SCHEMA.sql` を pg_dump で更新してコミットする（現物とドキュメントのズレ防止）
  3) 既存migrationファイルの“安易なリネーム/削除”は禁止（migrate履歴が死ぬ）

## 2026-02-27 INFORMATION 管理（admin/informations）最小CRUD 追加
- 目的: 管理者が「お知らせ」を一覧・新規作成・編集更新できるようにする（削除/添付管理は後回し）
- 追加:
  - routes/web.php: admin グループに admin.informations.*（index/create/store/edit/update）を追加
  - app/Http/Controllers/Admin/InformationAdminController.php 新規
  - resources/views/admin/informations/index.blade.php / create.blade.php / edit.blade.php / form.blade.php 新規
- 動作確認:
  - /admin/informations 表示OK（一覧）
  - 新規作成/編集→保存 OK（テスト1件で確認）
- 補足:
  - 反映が怪しいときは route:clear / view:clear を実行して確認
  - 今回はDBスキーマ変更なし（辞書/ERは再生成して差分有無を確認する方針）

## 2026-02-27 新PC環境: PHP CLI を導入
- Visual C++ 再頒布可能パッケージ（2015–2022）を導入
- PHP 8.4.16（NTS / VS17 x64）を C:\PHP に配置し、PATH を通して `php artisan` が動く状態に復旧

## 2026-02-25 ProBowler CSV インポート不具合（license列誤指定）対処
- Pro_colum.csv の実ライセンスは「#ID（idx=0）」に F00000001… が入っている。ヘッダ「ライセンスNo（idx=1）」は空。
- ライセンス形式は複数あり、例：F00000001（英字+8桁）/ M0000P014（英字+4桁+英字+3桁）
- 誤って別列を license-index 指定すると license_no に郵便番号（099-0403 等）が混入するため、Importerに形式検証を追加して弾く
- 混入済み40件は psql で license_no 正規表現に合わない行を削除し、件数を 2263 に復旧

2026-02-16 Task-001: tournament_results/participants に pro_bowler_id 追加（非破壊・バックフィル）

## 2026-02-06 お知らせ（info）詳細＋添付DL

- 目的: /info の一覧から詳細へ遷移し、添付をDLできるようにする
- 変更:
  - app/Models/InformationFile.php 新規
  - app/Models/Information.php files() 追加
  - app/Http/Controllers/InformationController.php show()/downloadFile() 追加
  - resources/views/informations/show.blade.php 新規
  - resources/views/informations/index.blade.php タイトルを詳細リンクに（必要なら）
- 動作確認:
  - /info → 詳細リンク → /info/{id} 表示OK
  - 添付DL: /info/files/{id} OK
  - ルート確認: informations.show / information_files.download / information_files.member.download が route:list で確認できた
  - 画面: /info は表示できる（現時点では一般公開のお知らせ0件のため「ありません」表示）
  - 補足: show.blade.php / InformationFile モデルを新規作成し、添付DL導線まで実装
- 次:
  - （必要なら）会員向け表示条件・ファイルvisibilityの制御


チャット①

これまでの作業履歴（DB構築 / このチャットの要約）

目的：旧MySQL（JPBA旧サイト由来）のスキーマを整理し、dbdiagram（DBML）でER図化 → 正規化 → Laravel＋PostgreSQL（JPBA_MAIN / port 5433）で再構築する方針を確立。

入力データ：旧DBの CREATE TABLE を多数提示（例：ProBall/ProBallList/ProConEntry/ProConResult/ProGroup/ProGroupList/ProInfo/ProInfoList/ProRecord/ProTest/ProTestSupport/Schedule/YearConResult など）。

ER図作成（dbdiagram）：

DBMLを一括生成し、dbdiagram上で表示できる形に修正。

途中で発生した 重複インデックス/構文エラー/不正なRef を修正し、表示まで到達。

縦長で見づらい問題について、dbdiagram上での配置・分割の考え方も整理。

重要な解釈修正：

ProTest は「大会」ではなく プロテスト（プロになるための試験） 系データとして扱う前提に変更し、構成を見直し。

正規化（ER図上）で実施したこと（代表）：

ProInfo：カテゴリを ProInfoCategory に分離、添付ファイルを ProInfoFile に分離、検索条件を ProInfoSearchCondition に分離。

ProRecord：種別を RecordType マスタ化。開催場所・使用ボール等も参照化の方向で整理（Place / BallInfo 等）。

ProTest：巨大テーブルを役割別に分割（例：Media / Entry / Place / Sponsor / Note / File / FreeColumn / FreeDate 等）。

YearConResult：年度を YearMaster、種別を ResultType として参照化。pro_name/pro_affiliation 等の冗長項目は正規化方針に寄せた。

共通カラムの統一：

全テーブルに reg_date, del_flg の有無を確認し、存在しないものへ追加・定義を統一。

追加要望として update_date, created_by, updated_by を全テーブルへ追加（監査ログ目的）。

KaiinStatus は値が限定的なため enum化（例：enum('一般','学生','ジュニア','その他')）。

ユーザー要件確認：

既存HPのUI/操作性は基本維持（DB正規化は裏側の構造変更。表示ロジック側で吸収する方針）。

Laravel移行（jpba-system）：

正規化ER図を基に マイグレーション作成を開始（テーブル単位で1ファイル方針）。

マイグレーション雛形一式（zip）を作成・提示（各テーブルのテンプレートファイル群）。


チャット②

これまでの作業履歴（DB構築・移行関連）

Laravelプロジェクト jpba-system を前提に、新DBを PostgreSQL（JPBA_MAIN / port 5433） として運用開始

dbdiagram（DBML）でER図を更新しつつ、マイグレーションを1テーブルずつ作成→php artisan migrateで反映の流れを確立

1) 既存マスタ系テーブルの作成（新DB側）

sex, area, license, kaiin_status, record_type, place, ball_info などを作成

2) プロテスト系（ProTest）テーブル群の作成（新DB側）

pro_test_category, pro_test_venue, pro_test_result_status, pro_test, pro_test_score, pro_test_attachment, pro_test_comment, pro_test_schedule, pro_test_status_log, pro_test_score_summary を作成

※途中で「interviewは現段階で作らない」方針を明確化し、作成対象から除外

3) 公式戦（トーナメント）系テーブル群の作成（新DB側）

tournaments, tournament_participants, tournament_results, match_scores

追加で venues, sponsors, media_publications, tournament_awards, match_videos を作成

4) 発生した主なエラーと対応

Duplicate table（ball_info）：migrationファイル名リネームで整合が崩れ、テーブルが既に存在 → migrate:reset / fresh 等で整合を取り直し

Migration not found：migrationsテーブルに記録されたファイル名と実ファイル名が不一致（リネーム起因）

psqlが5432に接続して失敗：Postgresは5433運用のため -p 5433 などで接続修正

外部キー参照先テーブル不存在（ProDsp/prodsp）：テーブル名・作成順・FK参照先を調整して解決

MySQL接続設定ミス（pgsqlの5433にmysqlで接続しに行く等）：.envのDB接続を整理して解決

5) データ移行の方針転換（旧DB→新DB）

当初：旧DB（MySQL/MariaDB mydb）から Eloquent（Legacyモデル）＋Artisanコマンドで移行する練習（例：AuthInstructorLegacy::count()で接続確認）

しかし：mydbのテーブル一覧に プロボウラー本体っぽい pro_dsp が存在せず、各モデル count() が0 → DBに目的データが無い/別経路の可能性が濃厚

その後：プロボウラー情報がCSVで存在することを発見 → CSVインポート前提に切り替え

6) CSV構造を踏まえた設計（途中）

「氏名/期別/ライセンスNo等は公式戦でも共通利用」→ 基本情報テーブルを中心に、詳細は分割して正規化する方針を再確認

※一時的に“1テーブルに大量カラム案”が出たが、ユーザー指摘により 基本情報＋詳細分割の正規化方針へ戻す方向で整理中

7) 現状

php artisan migrate:fresh が 全テーブルで完走する状態まで到達（新DBのスキーマ構築は成功）

次の作業は **CSV → 新DB（Postgres）へのインポート設計（カラム対応表・投入順・バリデーション）**にフォーカス


チャット③

これまでの作業履歴（DB構築・Laravel実装まとめ）

目的：HTMLフォーム→CSV出力→FTP取込の運用をやめ、**LaravelでDB管理（入力→自動反映→画面表示）**へ移行。

Laravelプロジェクト：jpba-system（ローカルで動作確認しながら実装を進行）

1) DB（マイグレーション）

database/migrations/ に多数のマイグレーションを作成済み（画像で確認した一式）

主に以下を含む構成を作成・反映済み：

選手系：pro_bowlers, pro_bowler_profiles, pro_bowler_links, pro_bowler_sponsors, pro_bowler_instructor_info, pro_bowler_biographies

マスタ系：districts（ほか、sex/area/license等）

大会/試験系：tournaments, venues, tournament_entries, tournament_results, pro_test_*（schedule/status_log/score_summary等）

一度、districts テーブル不在エラーが出たため、テーブル/マイグレーションの存在とJOIN先を修正する流れを実施。

2) 画面（Blade）とメニュー

既存のインデックスUI（検索フォーム付き）を基準に、メニュー項目に沿って各ページ（ダミー含む）を用意。

resources/views/ 配下に

layouts/app.blade.php

pro_bowlers/index.blade.php（元の「選手データ」検索UI）

pro_bowlers/athlete_form.blade.php（登録フォーム）

pro_bowlers/list.blade.php（「全プロデータ」一覧）
を中心に作成/更新。

3) ルーティング/コントローラー

routes/web.php にメニュー用ルートを追加し、各Controller→各Bladeへ遷移する骨組みを作成。

app/Http/Controllers/ProBowlerController.php を中心に実装：

index()：検索UI表示

create()：登録フォーム表示

list()：**「全プロデータ」**一覧＋検索/フィルター

store()：登録保存（現在ここで詰まり中）

4) 「全プロデータ」検索/フィルター（list）

pro_bowlers をベースに districts をJOINして表示する実装を作成。

検索条件（既存UI流用）：

名前部分一致、ID範囲、地区、性別、年齢範囲

追加フィルター（拡張予定含む）：

タイトル保有者、地区長、スポーツコーチ資格、インストラクタークラス（certifications等でLIKE）

「インデックスへ戻る」リンクで route('index') not defined が出たため、存在するルート名（例：athlete.index）へ修正して解消。

5) 発生した主要エラーと対応履歴

Route [index] not defined → ルート名を athlete.index 等に修正して解消。

Class App\Http\Controllers\ProBowler not found → Controller/参照の誤りを修正。

Table mydb.districts doesn't exist → districtsテーブル/参照を整備。

Class App\Models\District not found → Districtモデル/参照を整備。

The pro bowler id field is required → フォーム送信値とバリデーション（pro_bowler_id）の不整合が原因で調整。

現状の詰まり：Class "App\Models\ProBowler" not found（store内 ProBowler::create() 実行時）

※「テーブルがある/マイグレーションがある」と「ModelクラスがLaravelに認識される」は別問題で、Modelの名前空間/クラス名/自動読込が絡む可能性が高い状態。

6) 現在の状態（直近）

「全プロデータ」ページ表示はできる状態まで到達。

登録（store）処理で App\Models\ProBowler 認識エラーが出て止まっている。

storeの方針は「ProBowler（基本）→ ProBowlerProfile（詳細）」で保存する案を試行中。

7) 次にやるべきこと（作業再開ポイント）

store() 実行時の App\Models\ProBowler エラーを解消し、サンプル登録→一覧反映まで通す。

その後：CSVインポートは後回しで、画面/検索/表示項目の精度を上げる。


チャット④

これまでの作業履歴（DB周り・超要約）

DBは PostgreSQL（pgsql） 前提で作業。

tournaments テーブルを作る流れで migration 作成。

当初：tournaments を Schema::create() しようとして Duplicate table（tournaments 既に存在） に遭遇。

既存の create_tournaments_table が見つかり、内容（year / edition / start_date / end_date / event_type / is_team_event / location / poster_image_path 等）を確認。

要件（大会名、開始/終了日、会場情報、主催/協賛系、配信/賞金/観戦、参加条件、資料、前年大会、画像）に合わせて カラム構成を拡張する方針に寄せた。

migrate 実行時の別エラー

pro_bowlers migration で Duplicate column（license_issue_date / phone_home が二重定義） が発生 → migration 側の重複カラムを排除して解消。

migrate:fresh（フレッシュ）実施（一度DB作り直し）して、テーブル整合を取り直した。

その後、更新処理で Undefined column: start_date が存在しない が発生。

原因：DB上の tournaments に start_date が無く、別名（例：date 等）だった/古いスキーマが残っていた可能性。

対処方針：migrate:fresh で作り直すか、start_date/end_date を追加する migration を切ってスキーマを合わせる。

参考：php artisan schema:dump は pg_dump 実行失敗（環境変数/パス/文字化け系）で失敗。DB構造の問題というよりツール実行環境側。


チャット⑤

これまでの作業履歴（DB / Laravel）

目的定義

プロ大会の成績をDBに記録 → 年度別に集計 → 選手ランキング（ポイント/賞金/AVG）を表示

CRUD（新規/更新/編集/削除/集計）と一覧UI（画像の見た目）を作る

作成/整理したテーブル（マイグレーション）

tournaments（既存・動作済みを流用）

tournament_results

pro_bowler_license_no（FK → pro_bowlers.license_no）

tournament_id（FK → tournaments.id）

ranking, points, total_pin, games, average, prize_money, ranking_year

tournament_participants（大会出場選手一覧用途）

tournament_awards（大会別順位→賞金表：tournament_id, rank, prize_money）

tournament_points（順位→ポイント表：rank, point）

※migrate:fresh時に関係ないテーブルのFK（例：match_scores）で落ちる問題があり、原因特定・順序/依存関係の調整で通過させた

モデル作成（1テーブル1モデル）

TournamentResult 等（関連：TournamentResult -> belongsTo(Tournament) / belongsTo(ProBowler, fk=pro_bowler_license_no, ownerKey=license_no)）

ルーティング/コントローラ

Route::resource('tournament_results', TournamentResultController::class)->except(['show']) に整理（show未実装でエラー→除外）

TournamentResultController：index/create/store/edit/update 実装

自動計算/自動反映

average = total_pin / games

points：tournament_points から順位参照

prize_money：tournament_awards（大会ID+順位）参照

Blade（表示/UI）

tournament_results/index.blade.php：一覧表示＋ボタン（新規/一括/PDF/戻る/編集）

「不明な選手」問題：Blade側の参照ミス（nameやyearなど）を修正（player->name_kanji, ranking_year）

編集画面 edit.blade.php 作成、更新処理 update() 接続

一括登録（同一大会に複数選手）用に batch_create.blade.php 作成（10行フォーム）＋ batchCreate/batchStore 追加

大会一覧から tournament_id をクエリで渡して初期選択（selected）

大会一覧から成績入力へ

tournaments/index.blade.php に「成績入力」リンク追加

tournament_results.batchCreate（クエリ：tournament_id）へ遷移させる方式を採用

PDF出力

barryvdh/laravel-dompdf 使用

exportPdf() と tournament_results/pdf.blade.php を追加

ルート tournament_results.pdf

ハマりどころ（原因と対処）

外部キー参照先テーブル未作成/順序不整合で migrate:fresh 失敗 → migration順/依存関係調整

show()未実装で resource の自動ルートが死ぬ → ->except(['show'])

Bladeの参照名ミス（year/name）→ ranking_year/name_kanji に修正

ルート名のタイプミス（batch_Create 等）→ routes/web.php の ->name() と 完全一致させる

URL/ルートの - と _ 混在で 404 → プロジェクト内で _ 系に統一（/tournament_results/... と tournament_results.xxx）


チャット⑥

これまでの作業履歴（DB/CRUD 周り）

環境：Laravel 12 / PHP 8.2 / PostgreSQL（pgAdmin 使用）

RecordType 機能（褒章/記録）を新規実装して DB～画面まで通した

1) ルーティング/名前揺れ整理

Route::resource('record_type', ...) → Route::resource('record_types', RecordTypeController::class) に統一

画面URLも /record_types に統一

route 名も record_types.index/store/edit/update/destroy に統一（record_type.index 等の誤りを潰した）

404 / Route not defined を解消

2) モデル/マイグレーション調整（命名地獄の解消）

エラー対応しながら命名を統一：

award_type ↔ record_type ↔ record_types の混在を 最終的に record_type（カラム）へ統一

テーブル名は record_types

RecordType モデルの $fillable に必要カラムを追加して MassAssignmentException 解消

主な解消エラー：

Class App\Models\RecordType not found（モデル/namespace）

Undefined table record_types（migration の create 名不一致）

Undefined column award_type（古いカラム参照が残ってた）

Not null violation record_type（保存データに record_type が入っていない/カラム名違い）

Duplicate table pro_dsp / pro_bowlers（DBに残骸テーブルが残ってて refresh が失敗）

Blueprint まわりの migration エラー（use/定義不足）

FK 依存の順序も意識：record_types.pro_bowler_id → pro_bowlers.id

3) Postgres 接続/削除作業

PowerShell に DROP TABLE 直打ちして失敗 → pgAdmin/psql 経由で削除に切替

Postgres サービス稼働確認（postgresql-x64-17 Running）

接続先DB/ホスト/ポート不一致を疑って修正（Connection refused 対応）

4) Create（登録）画面：2000人対策

選手プルダウンを廃止し、ライセンス番号入力→選手特定方式へ変更

追加：/api/pro-bowler-by-license/{licenseNo}（route/web.php に簡易API）

create.blade：

pro_bowler_license_no 入力

JS fetch で選手を引き、hidden pro_bowler_id にセット & 画面に氏名表示

controller store：

license_no で ProBowler を検索して pro_bowler_id を確定して保存

5) Index（一覧/検索）画面

検索：player_identifier（氏名/カナ/ライセンス番号 どれでもヒット）＋ record_type ＋ tournament_name

表示：記録種別の表示マッピング（perfect / seven_ten / eight_hundred）

記録種別が「-」になる問題：blade が $record->record_types を参照していたのを $record->record_type に修正

大会名が出ない等の表示調整を実施

追加：公認番号（certification_number）表示、IDは非表示にできるように整理

行/氏名クリックで edit に遷移する導線を作成

6) Edit（編集）＋ Delete（削除）

edit.blade を作成（create と同様のUIを踏襲）

update：validate→update

edit 画面下部に 削除ボタン（DELETE） を追加して destroy へ送る


チャット⑦

これまでの作業履歴（DB構築まわり）
1) インストラクター管理（instructors）

目的：プロボウラーと紐づくインストラクター／非プロのインストラクターを同一管理。

テーブル：instructors

PK：license_no（string, primary, incrementなし）

主カラム：name, name_kana, sex( boolean ), district_id (unsignedBigInteger, nullable), instructor_type (pro/certified), grade, is_active, is_visible, coach_qualification

FK：pro_bowler_id → pro_bowlers.id（nullable, onDelete set null）
district_id → districts.id（nullable, onDelete set null）

モデル：Instructor

primaryKey=license_no, incrementing=false, keyType=string

fillable/casts 設定、district()/proBowler() リレーション

種別の表示用 type_label（プロボウラー/プロイントラ/認定イントラの判定）＋scope（proBowler/proInstructor/certified）

コントローラ：InstructorController

index検索（name/license_no/district/sex/type/grade）

store時：instructor_type=pro の場合 pro_bowlers.license_no で探索→ pro_bowler_id を自動セット

プロボウラー登録時の自動反映

ProBowlerController@store/update で Instructorを同期生成/更新（pro_bowler_id埋め、一覧側で自動表示）

等級（C→準B→B→準A→A）や性別の取り扱いを調整（bool/intズレを修正）

2) PDF出力（instructors検索結果）

instructors.exportPdf を追加し、検索条件を維持したままPDF生成。

dompdfで日本語フォント導入（storage/fonts 配下、Windows環境の権限/コマンド差異に対応）。

文字化け対策：dompdf設定（font_dir/font_cache/default_font/font_data）＋ビュー側フォント指定。

3) 大会ごとの配分表（賞金・ポイント）

目的：大会ごとに「賞金配分」「ポイント配分」を持たせ、テンプレ（パターン）から生成しつつ個別編集可。

ルート：

Route::prefix('tournaments/{tournament}')->group(function(){

Route::resource('prize_distributions', PrizeDistributionController::class);

Route::resource('point_distributions', PointDistributionController::class);

});

ルート名は snake_case（例：tournaments.point_distributions.update）

テーブル

distribution_patterns（※後で type 列追加・migrate順で詰まったのを修正）

name, type(prize/point) など

Seederで初期パターン投入（列不足エラー→migration見直しで解消）

prize_distributions

tournament_id(FK), rank, amount, pattern_id(FK nullable)

point_distributions

tournament_id(FK), rank, points(※最終的にpoints運用), pattern_id(FK nullable)

モデル/リレーション

Tournament に prizeDistributions() / pointDistributions()（hasMany）追加（未定義でBadMethodCallException→修正）

DistributionPattern に prizeDistributions() / pointDistributions()（hasMany）追加

コントローラ

index：orderBy('rank') で常に順位ソート（編集後に末尾へ行く問題を解消）

create：existingDistributions を渡して既存値をフォームに反映（追加ボタン→入力済み再表示）

store：

テンプレ選択時：パターン行を大会用にコピー（Prizeはcreate、PointはupdateOrCreate運用）

カスタム時：enabled[] のrankのみ保存

重複防止：updateOrCreate(['tournament_id','rank'], ...) などでrank単位に上書き

null配列対策：$request->input('points', []) 等で空配列保証（null offsetエラー対応）

destroy：空実装で白画面→ delete() + indexへredirect実装

Blade（配分表UI）

1〜96位を表示、20位×5列のテーブル分割表示

enabled（表示/非表示）チェック、一括ON/OFFボタン、全クリアボタン（JS）

送信後は大会一覧ではなく 配分一覧へredirect（store後の遷移修正）

変数名/ルート名の不一致でエラー多発→統一（snake_case、compactの変数名合わせ）


チャット⑧

これまでの作業履歴（DB構築・関連修正 要約）

tournament_results 一括登録ルート不達問題

resource('tournament_results') の {id} と /batch_create が競合し "bigint"に"batch_create" エラー発生。

静的パス（batch_create等）を resource より前に定義して解消。

賞金・ポイント反映機能の実装と修正

applyAwardsAndPoints($tournamentId) を追加して、tournament_results.points / prize_money を順位から埋める処理を作成。

参照先のテーブルが tournament_points / tournament_awards ではなく、最終的に point_distributions(pointsカラム) / prize_distributions(amount等) を参照する形に修正。

カラム名違いで Undefined column (point/prize_money) が発生 → 実カラム名に合わせてクエリ修正。

ランキング集計（年度別）

大会成績（tournament_results）から年度別に

賞金合計（prize_money）

ポイント合計（points）

年間アベレージ（average の集計方針）
を算出して表示する構成に。

rankings のルートが tournaments/{tournament} と競合し "bigint"に"rankings" エラー → 静的ルートを優先定義して解消。

Tournamentの年度問題

tournaments テーブルに year カラムが存在せず、ビュー側の {{ $tournament->year }} が反映不可。

方針：start_date から年度算出 or year カラム追加して保存（選択肢として整理）。

承認ボール（approved_balls）

approved_balls の検索で brand カラム参照エラー → 正しいカラム manufacturer に統一。

発売年度 release_year を表示・登録対象に追加。

新規登録：最大10件同時登録フォーム（store_multiple）を作成し、ルート・コントローラを追加。

CSV/Excelインポート：Laravel12 + PHP8.2 に対して maatwebsite/excel のバージョン不整合が発生（1.x入ってmake:import無し、3.xはLaravel/illuminate制約で不可）→ CSVインポート方針へ切替。

使用ボール（used_balls：プロ紐付け＋有効期限）構想/構築

使用可能ボール要件：pro_bowler_license_no + approved_ball_id + serial_number + inspection_number を紐付け。

追加予定カラム：registered_at, expires_at（登録日から1年）。

期限切れ自動削除：Scheduler/Command で expires_at < now() を削除する案まで整理。

ルーティングの不具合修正

web.php 内に 全角スペース混入やルート二重定義で Route class not found 等の異常挙動 → 該当行削除＆整形。

used_balls ルートは prefix('used_balls') で group 化。

管理者フラグ（代理登録用）

users に is_admin を追加するマイグレーション作成＆適用。

User::isAdmin() を is_admin 判定に。

Tinkerで admin ユーザー作成（admin@example.com / password）。

※Tinkerの auth()->user() は常に null（セッション非共有）なので、ブラウザログインが必要。

現状の詰まり点（未解決/要対応）

ApprovedBall::count() が 0 → 承認ボール未投入のため、使用ボール登録のメーカー/ボールプルダウンが空。

管理者入力（ライセンス番号入力）も ブラウザで管理者ログインしないと auth()->user()->isAdmin() が有効にならない。


チャット⑨

これまでの作業履歴（DB構築・DB連携）

認証追加（Laravel Breeze）

Breeze導入 → /login からブラウザログイン（admin@example.com
 / password）で admin権限(isAdmin)有効化 を目指した。

PowerShellで && が使えず npm install / npm run dev を別実行した。

承認ボール（approved_balls）

CSVインポートをTinkerで実施しようとして manufacturer NOT NULL で失敗 → insert側に manufacturer を含める必要があった。

一覧・検索で name_kana カラム不存在 エラー（SQLSTATE 42703）→ DBカラムに合わせてコード/検索条件を修正（またはカラム追加が必要という整理）。

「和名/承認が一覧に出ない」系は 取得カラム/表示カラムの不整合 が原因になりがち、という確認。

ルーティング/一覧確認

php artisan route:list 実行時に 存在しないController（例: CalendarController） が混ざって落ちた → ルート定義の掃除が必要になった。

used_balls/index に GET できない問題は ルート定義ミス（PATCH/DELETEのみ） か URL誤り が原因で修正。

使用ボール（used_balls）登録フォーム

GET(絞り込み) と POST(登録) を ネストして壊す を何度かやった → GET/POSTは別フォーム に分離して解決。

バリデーションエラー後に入力が消える → old() を入れて保持。

inspection_number は nullable に変更（空でも送れる）。

registered_at は ユーザー入力に変更（実登録日と一致しないケース想定）。

expires_at は 登録日 +1年 −1日（例: 2025/08/06 → 2026/08/05）を Controllerで計算。

使用ボール一覧（used_balls.index）

検索：ライセンス番号 or 漢字名で検索したい → whereHas() を使う方向に整理。

ページネーション変数名ミス（$balls->links() vs $usedBalls->links()）を踏んだ。

名前表示（ライセンス番号 → 漢字名）で大混乱した核心

当初：UsedBall -> User を pro_bowler_license_no で結び、user->name_kanji を取りにいった
→ users テーブルに pro_bowler_license_no が無い で死亡（SQLSTATE 42703）。

次：UsedBall -> ProBowlerProfile を license_no で結ぼうとした
→ pro_bowler_profiles に license_no が無い で死亡（SQLSTATE 42703）。

pgAdminのカラム確認で判明：pro_bowler_profiles 側のキーは pro_bowler_id (bigint)（※ license_no ではない）

しかし、入力しているライセンス番号は m00001297 のような文字列
→ pro_bowler_id(bigint) に突っ込んで Invalid text representation (22P02) で死亡。

結論：UI入力は license_no（文字列）、DB保存は pro_bowler_id（数値ID） に統一する必要あり

store() で ProBowlerProfile::where(license_no=入力値)->first() して id(pro_bowler_id相当) を保存

表示/検索は proBowler リレーション経由で name_kanji を参照する方針。

PostgreSQL接続（psql）確認

psql -U postgres -d JPBA_MAIN が 5432で Connection refused

tasklist | findstr postgres でプロセスは生存確認

ポート5433 で接続成功：psql -h localhost -p 5433 -U postgres -d JPBA_MAIN

\d pro_bowler_profiles / information_schema.columns で 実カラム確認。


チャット➉

これまでの作業履歴（Registered Ball System 構築）
1. 基本構成

承認ボールマスタ

approved_balls

カラム：id / manufacturer / name / name_kana / release_year / approved

プロボウラーマスタ

pro_bowlers

license_no を主キー的に利用

プロ登録ボール

registered_balls

承認ボール・プロボウラーと紐づけ

2. registered_balls テーブル仕様

主なカラム

license_no（pro_bowlers と紐付け）

approved_ball_id

serial_number

registered_at

expired_at

certificate_number（検量証番号）

制約

同一プロ × 同一年内で同一シリアル番号は禁止

有効期限：登録日から 1年後の前日

3. モデル実装

RegisteredBall

creating / updating フックで expired_at を自動計算

belongsTo

proBowler (license_no)

approvedBall

4. Controller（RegisteredBallController）

CRUD 完成

index

プロライセンス番号・プロ名（漢字）検索

有効期限切れ除外（expired_at >= yesterday）

store / update

年単位ユニーク制約（Rule::unique + whereYear）

検量証番号が nullable

検量証なしの場合は有効期限入力不要

ページネーション対応

5. 画面（Blade）
index

検索項目

プロライセンス番号

プロ名（漢字）

検量証あり／なし

戻りボタン

承認ボール一覧

athlete.index

create / edit

プロボウラー選択

Select2 + API 検索（1000件対策）

承認ボール選択

メーカー + 発売年 → API で絞り込み

検量証番号入力時のみ

expiry_date を JSで表示

6. API

/api/pro_bowlers

license_no / name_kanji 部分一致検索

/api/approved-balls/filter

manufacturer + release_year

approved = true のみ返却

大文字小文字問題を DB 側で吸収

7. 自動削除・運用

検量証番号なしボール

毎年 12/31 に自動削除

Artisan Command 作成

Kernel.php に yearlyOn スケジュール登録

8. ハマりどころ（重要）

API 404 の原因：

RouteServiceProvider を誤って自作 → 修正

承認ボールが特定年しか出ない原因：

JS送信値と API パラメータ不一致

大文字小文字不一致

Blade 内で expired_at->format() による 500

optional() で回避


チャット⑪

これまでの作業履歴（DB・カレンダー周り要約）

既存 tournaments テーブルを前提に拡張

gender (M/F/X)、official_type (official/approved/other) を追加

start_date / end_date を基準に年・月またぎ対応

Model に casts(date) 追加、year 自動補完は維持

カレンダー用の新規テーブル追加

calendar_events（手入力イベント）

種別：pro_test / approved / other

大会とは独立、同日表示で干渉しない設計

CalendarController 実装

年間ビュー：年指定／省略時は当年

月間ビュー：月曜始まり・日曜終わり

大会＋手入力イベントをマージ表示

日付→イベント配列 (map) ＋ 背景色判定 (bgMap)

PDF 出力（年間／月間）

表示ルール

男子：水色／女子：ピンク／混合・未設定：薄緑

承認・その他・手入力：薄紫

土曜：青文字／日曜：赤文字

今日強調リング

祝日：赤文字＋薄赤背景（六曜は未採用）

祝日対応

calendar_days テーブル

2025年祝日を CSV / Seeder で投入

月間表示時に日付メタ情報として参照

UI強化

年間↔月間 相互遷移ボタン

インデックス戻り／PDF ボタン

カラーレジェンド固定表示

スマホ時は縦積み表示

UX改善

月間カレンダーでセルをドラッグ

開始日〜終了日を自動入力して新規作成モーダル表示

そのまま calendar_events.store にPOST

ルーティング整理

tournaments.show 重複解消（resource に統一）

calendar / calendar/{year}/{month} を基準導線に

手入力イベント CRUD 用ルート追加（既存 create/store は維持）

キャッシュ最適化

年・月単位キャッシュ

Tournament / CalendarEvent の save・delete 時に

該当年・該当月のみ Cache forget

全体 flush は廃止

テスト・運用

月間表示の Feature Test

キャッシュ無効化確認テスト

2025年祝日 Seeder 整備


チャット⑫

これまでの作業履歴（DB/ルーティング/機能追加 要約）

大会成績まわり（tournament_results）

tournaments.results のネスト資源ルート（shallow）に整理し、既存 Blade の互換ルートも残して動作復旧。

TournamentResultController を整理（大会別一覧/登録/一括登録/編集/更新、PDF、ランキング、賞金・ポイント反映、タイトル反映）。

既存カラム差異に備えて Schema::hasColumn() で順位カラムを自動判定する実装に。

賞金配分・ポイント配分（DB/CRUD）

prize_distributions / point_distributions テーブルを作成（tournament_id, rank, amount|points, pattern_id）。

PrizeDistributionController / PointDistributionController を追加・修正（テンプレ適用＋手入力保存）。

TournamentResult へ「賞金・ポイント反映」ボタン→各順位に応じて prize_money / points を update。

タイトル反映（ProBowlerTitle）

優勝者を tournament_results から抽出し、pro_bowler_titles に firstOrCreate で反映する仕組みを実装。

ProBowlerTitle は pro_bowler_id / tournament_id / title_name / year / won_date / source を保持する方針に寄せた。

褒章（RecordType）集計と表示

record_types（perfect / seven_ten / eight_hundred）を ProBowler に紐付け（records()）。

ProBowler 一覧に withCount() で perfect_count / seven_ten_count / eight_hundred_count を付与し表示。

Blade 側の変数ミス（$player → $bowler）等を修正し一覧反映を復旧。

ProBowler 一覧/検索（複数機能の干渉整理）

district eager load、titles_count、褒章 count、地区長、スポーツコーチ等の絞り込みを1クエリに統合。

インストラクター情報が出ない/ページが死ぬ問題は、Controller/Blade/ルート衝突を順次修正して復旧。

ログイン基盤（Breeze 残骸活用＋会員紐付け）

Breeze の routes/auth.php が存在する前提でログイン系を再利用する方向へ。

users と pro_bowlers を紐付けるため、users.pro_bowler_license_no（または追加カラム）前提で運用。

seed:users-from-bowlers コマンドを作成済み（php artisan seed:users-from-bowlers）。

実行時に users 側に pro_bowler_license_no カラムが無くエラー → **users テーブルへカラム追加（マイグレ）**して解決。

シード成功：seeded/updated users: 7


チャット⑬

これまでの作業履歴（DB構築まわり要約）

DB接続をPostgreSQLに統一

.env の DB_CONNECTION=pgsql 等を確定（ローカル 127.0.0.1:5433 / JPBA_MAIN）。

migrationの依存関係崩壊 → 全体を“親→子”順に整理する方針へ

FK参照先テーブルが未作成で Undefined table が頻発（例：tournaments, pro_bowlers, record_type 等）。

Schema::table()（ALTER系）が先に走ってテーブルが無い問題も発生（例：distribution_patterns の add column が先行）。

機械的にmigrationの中身をスキャンして可視化

PowerShellで Schema::create / Schema::table / ->on() / ->constrained() を抽出 → migration_scan.csv を生成。

Phase方式に分割（通すための現実的運用）

Phase A：CREATE（テーブル本体）を先に通す

Phase B：ALTER/add_/change_/update_（追加カラム・FK・型変更）を最後に通す

ファイル名リネームで実行順を強制

例：2025_09_01_000001_... の連番で並べ替え。

Windowsで Rename-Item のパス指定ミス等も発生し、一部取り残し→手動確認。

FK/カラム定義の破壊的バグを複数修正

MassAssignmentException → Modelに fillable 追加。

Undefined table → 実行順の修正・ALTERを後回しへ。

Duplicate column pro_bowler_id → unsignedBigInteger('pro_bowler_id') と foreignId('pro_bowler_id') の二重定義を除去。

used_balls で pro_bowler_id 列が無いのに foreign() を貼っていた → 先に列定義してからFKに修正（もしくはFKをPhase Bへ）。

一括置換の副作用で $table$table が大量発生 → 正規表現で一括修正。

migrationの down() が単数/複数不一致（例：informations 作ったのに dropIfExists('information')）→修正。

Windowsで php artisan db がTTY非対応エラー →方針変更（tinker/psqlで確認）。

最終的に migrate:fresh を通すための並びと内容を確定

Phase A 通過 → Phase B のエラー（dropForeign存在しない/既存カラム追加など）を都度修正して 全通し成功。

Information系テーブルを構築・拡張

informations を作成 → 追加仕様（audience, required_training_id 等）でカラム不足が発覚 → 追加migrationで拡張。

tinkerでテストデータ投入 → information_schema.columns でカラム確認。

（要するに：migrationが散らかって地獄だったので、“CREATE先・ALTER後”のPhase運用＋実行順リネーム＋二重カラム/FKの掃除で fresh を通し、最後に informations を仕様通りに拡張した。人間は学ばないが、DBは学習させた。）


チャット⑭

これまでの作業履歴（DB/マイグレーション周り中心・超要約）
1) 大会成績（tournament_results）系の改修

単発登録/一括登録を統一するため、入力仕様を拡張

player_mode（pro/ama）導入

プロ：pro_key（ライセンス or 氏名）→ resolvePro() で pro_bowlers を特定

アマ：amateur_name を tournament_results に保存（プロなら NULL）

DB保存ロジックを整理

プロなら pro_bowler_license_no（既存互換）＋（あれば）pro_bowler_id を保存

ranking / total_pin / games / ranking_year を保存

average 自動計算、points / prize_money は rank×配分から算出（プロのみ）

一括登録は 旧payload(results[]) と 新payload(rows[]) の両対応にして正規化して保存

ルーティングの混線を整理

tournament_results.index（大会検索/一覧）と tournaments.results.*（大会別成績）で命名衝突が発生 → ルート名/リンク先を調整

tournaments.results.show 相当の「大会別一覧」表示は index(Tournament $tournament) に寄せる形で運用

重複登録/戻り先/編集後遷移などUI導線も修正（削除ボタン追加、更新後は一覧へ戻す等）

2) 賞金配分/ポイント配分（prize_distributions / point_distributions）

tournaments/{tournament} 配下に ネストresource を追加して 404 を解消

tournaments.prize_distributions.*

tournaments.point_distributions.*

配分保存は pattern適用 or 手入力(enabled ranks) の両対応

applyAwardsAndPoints() で tournament_results に配分を反映する実装を追加/調整

PointDistribution(amount/points) と PrizeDistribution(amount) を rank で引いて更新

419（Page Expired）発生時は、POSTフォームの CSRF/送信先/ルート名の整合を見直し（主にBlade側）

3) 登録ボール（registered_balls）周りのDB不整合修正

代表的なエラー：

expires_at / certificate_number が DB列に無い or NOT NULLでNULLが入る などの不一致で insert が落ちる

モデル RegisteredBall を基準に統一

inspection_number（検量証番号）を正式列として扱う

expires_at は モデルbootで自動算出（検量証番号がある場合：registered_at + 1年 - 1日、無ければNULL）

Blade 側のフォーム名が certificate_number / expiry_date になっていたため、inspection_number / expires_at に統一して保存できるように修正

4) 追加で行ったこと（混線防止メモ）

ルート名（tournament_results.* と tournaments.results.*）の 参照先の統一が最重要ポイント

DB列名とBladeの name="" がズレると保存されない／NOT NULLで即死するので、以後は 「DB列名 → Controller validate → Blade name」を完全一致で運用する方針


チャット⑮

これまでの作業履歴（DB構築まわり要約）

既存：使用ボール管理（used_balls）

used_balls テーブル（pro_bowler_id, approved_ball_id, serial_number unique, inspection_number unique, registered_at, expires_at）

UsedBall モデル＋ UsedBallController（検索・登録・延長・削除）＋ Blade（index/create/edit）

外部アプリ連携（大会エントリー→大会使用ボール紐付け）用DBを追加

tournament_entries（大会エントリーの親）

想定カラム：tournament_id, pro_bowler_id, shift, lane, status, checked_in_at, timestamps

ユニーク制約：(tournament_id, pro_bowler_id)（同一大会に同一選手は1件）

tournament_entry_balls（中間テーブル：エントリー×使用ボール）

想定カラム：tournament_entry_id, used_ball_id, timestamps

マイグレーション衝突対応（Duplicate table/column）

personal_access_tokens / tournament_entries / tournament_entry_balls / users.pro_bowler_id などで

Duplicate table / Duplicate column が発生

対応：既存DBに存在するテーブル・カラムの 重複マイグレーションを削除 or 実行対象から除外 して migrate を通した

その結果、マイグレーションは最終的にクリーン状態に復帰

スキーマ確認

現在の全テーブル/カラム一覧、インデックス、FKをダンプして共有

FKは現状 users.pro_bowler_id -> pro_bowlers.id のみ確認（他は未設定状態）

モデル追加/変更（外部アプリ連携のため）

TournamentEntry モデル追加

tournament()（belongsTo）

bowler()（belongsTo ProBowler）

balls()（belongsToMany UsedBall via tournament_entry_balls）

UsedBall に entryLinks() 追加（hasMany TournamentEntryBall）

Tinker による動作確認

TournamentEntry::create() で pro_bowler_id が null になるケースがあり、原因は UsedBall が取れていない（$ub=null）ことだった

修正版：firstOrCreate([tournament_id, pro_bowler_id], defaults) でユニーク制約違反を回避

balls()->syncWithoutDetaching([$ub->id]) で中間テーブル紐付け確認

API/WEB ルート整備と重複整理

API側：POST/DELETE api/tournament_entries/{entry}/balls...

WEB側：POST/DELETE tournament_entries/{entry}/balls...

ルート重複・命名混在（ハイフン版/アンダースコア版、name重複）を整理し アンダースコア版に統一

route:list が Class ...Controller does not exist で落ちる問題を修正

原因：コントローラのファイル配置/namespace不整合（PSR-4違反、Modelsに置いてた等）

対応：正しい app/Http/Controllers 配下へ移動・namespace修正、composer dump-autoload、php artisan optimize:clear

現在の状態（重要）

tournament_entries / tournament_entry_balls のDB構造は存在

大会エントリー→使用ボール紐付けは Eloquent関係＋API/WEBルートで接続済

ブラウザで GET /tournament_entries/{id}/balls にアクセスすると 404 になるのは POST専用のため仕様


チャット⑯

これまでの作業履歴（DB構築/DB整備）

大会（tournaments）

entry_start / entry_end を **日時（time付き）**で扱う方針に統一。

管理画面の登録/更新で、日付だけ入力された場合に デフォルト時刻を補完（例：開始10:00、終了23:59）して保存する実装を追加。

会員側の大会エントリー選択画面で、期間を time付き表示するように調整。

大会エントリー（tournament_entries）

会員が大会ごとに entry / no_entry を保存する仕組み（updateOrCreate）を実装・動作確認。

エントリー済みの場合のみ「大会使用ボール登録」画面へ遷移できる導線を作成。

使用ボール（used_balls）と仮登録

「検量証番号（inspection_number）」を 任意にする要件が追加されたため、DB側の制約とアプリ側のバリデーションを調整。

ただし DB側で inspection_number が NOT NULL のままだと insert で落ちるため、マイグレーションで inspection_number を nullable にする（またはテーブル定義変更）が必須、という整理を実施。

仮登録（検量証未入力）でも登録できるが、expires_at は検量証がある時だけ算出、ない場合は NULL 扱いにする方針に統一。

大会使用ボール紐付け（pivot: tournament_entry_balls）

tournament_entries と used_balls の多対多を tournament_entry_balls で管理。

会員画面は「自分のボール一覧をチェックボックスで表示→一括登録」方式。

1大会あたり最大12個の制限をサーバ側で検証（既存登録 + 追加分 > 12 はエラー）。

削除は会員不可（必要なら管理者のみ detach 可）。

検量証必須大会でも 検量証未入力ボールは仮登録として紐付け可能（ピボットに状態は持たず、表示側で inspection_number null を仮登録扱い）。

RegisteredBall（registered_balls）系の整理

RegisteredBall モデルを用意し、inspection_number がある場合のみ expires_at を自動算出（ない場合は NULL）という設計を採用。

一方で、会員側の大会使用ボール登録は used_balls を見ている/管理画面の「プロ登録ボール一覧」は registered_balls を見ているなど、参照テーブルが分裂しており、仮登録が片方に出ない問題が発生。

現状の症状：大会使用ボール画面には仮登録が出るが、プロ登録ボール一覧（registered_balls）では仮登録が出ない／検索で0件になるケースあり → どのテーブルを正とするか統一が必要（テーブル統合 or 参照先を揃える）。

Seeder/データ初期化（trainings）

「講習一括登録」で「Seederを流して下さい」が出た件：TrainingSeeder を作成し、trainings テーブルに mandatory などを updateOrInsert で投入する形に整理。

php artisan db:seed --class=... を実行して投入済みであることを確認。

ルーティング権限整理（DBというより運用）

会員は「大会エントリー/大会使用ボール登録/登録ボール（仮本）/マイページ/抽選」だけ使えるように、ルートを role で分離する方向で web.php を整理（公開ページは会員も自由閲覧、管理者は全ページ）。


チャット⑰

これまでの作業履歴（DB関連）

DBは PostgreSQL（pgsql）前提で Laravel アプリを運用。

既存スキーマ前提で開発を継続（php artisan migrate を回しながら差分調整）。

users テーブル

認証用に users を使用（Laravel標準 + カスタム）。

role カラムは すでにDBに存在していた（add_role_to_users_table 実行時に Duplicate column: role で判明）。

旧運用の名残として is_admin も併存（boolean cast）。

会員紐付け用に pro_bowler_id / pro_bowler_license_no を運用（Userモデルのfillableに含める）。

pro_bowlers テーブル（会員マスタ）

登録時に license_no + email で照合して、一致した場合のみ users を作成する運用（＝勝手に会員登録できない）。

ログインは email または pro_bowler_license_no を受け付ける運用。

ボール系テーブル / 関係

approved_balls（承認ボール）

approved_ball_pro_bowler（中間/pivot：license_no と approved_ball_id を使う想定、year も保持）

registered_balls / used_balls（登録ボール・使用ボール）

大会・エントリー系

tournaments、tournament_entries 等の構造を前提に、会員がエントリー/ボール紐付けを行う画面を実装（DBは既存テーブルを利用）。

運用系（DBに影響する定期処理）

定期削除（例：期限切れボール削除、年末の「検量証なし」削除）など、DBを更新/削除するスケジュール処理が存在。


チャット⑱

これまでの作業履歴（このチャットで扱ったDB構築/整備・関連修正の要約）

ProBowler 新規登録で Unique制約違反（license_no）

エラー: SQLSTATE[23505] pro_bowlers_license_no_unique（同一 license_no が既に存在）。

実際: 画面ではエラーが出るが、DB上にはレコードが作成されている（＝二重送信/二重INSERT/保存後に例外発生の可能性）。

対応方針: insertの前後で同一license_noの存在確認、保存処理の二重実行（フォーム二重送信/JS/リダイレクト先生成/例外）を疑う。DBは license_no をユニークキーとして運用。

Instructor（インストラクター）登録の設計整理（プロ/認定）

課題: 認定インストラクターは「ライセンスNo」が無いが、UI/Validationで必須になっていた。

既存DB/Model前提:

instructors の主キーは license_no（string, incrementing=false）。

pro_bowler_id を持ち、instructor_type が pro の場合にProBowlerへ紐づく。

実施した方向性:

InstructorController の validation を instructor_type 条件で分岐しやすい形に調整（Rule::unique(...)->where(instructor_type=...) を使用）。

ただし、後続で DBに存在しないカラム参照（例: cert_no） が起きるエラーが報告され、スキーマとコードの不整合が顕在化。

Instructor登録でのDBエラー（未存在カラム参照）

エラー: SQLSTATE[42703] Undefined column: cert_no does not exist。

意味: バリデーション/ユニークチェック or クエリ条件で cert_no を参照するコードがどこかに残っているが、DBの instructors テーブルに cert_no 列が無い。

対応方針:

DB列名を確定（migrations / schema / \d instructors）し、コード側の参照列をスキーマに合わせる（または列を追加する）。

TournamentResult（大会成績）でのルーティング/名前衝突が原因のエラー連発

主なエラー:

Route [tournaments.results.create] not defined

Missing required parameter for [Route: tournament_results.store] ... {tournament}

Route [tournaments.results.show] not defined（リダイレクト/URL生成時）

原因:

routes/web.php にて tournaments.results.show という名前を /tournament_results（パラメータ無し） に誤って割り当てていたため、 route('tournaments.results.show', $tournamentId) が「パラメータ必須URL」前提とズレて落ちる/または別名に化けるなどの不整合が起きていた。

ネストresource（tournaments.results）と、旧互換ルート（tournament_results.*）が混在し、Blade/Controllerで参照している route名が揺れていた。

TournamentResult の最終方針（根本対応）

tournaments.results.show を使わず、ネストresource標準の tournaments.results.index（GET /tournaments/{tournament}/results）へ統一。

routes/web.php の誤定義（/tournament_results に show 名を付けていた行）を削除。

TournamentResultController のリダイレクト先を tournaments.results.index に統一（store/update/batchStore/destroy）。

tournament_results/create.blade.php は大会選択後にフォームactionを POST /tournaments/{id}/results に差し替えるJS実装で、

送信ボタン/一括登録ボタンを「大会選択済み」で有効化。

URLが /tournaments/{id}/results/create の場合は初期大会IDを自動反映。

現在の未解決候補（DB構築観点で残タスク）

cert_no 問題: DB側に列が無いのにコードが参照 → 列追加 or コード修正のどちらかを確定。

ProBowler重複INSERT疑惑: 保存後に例外が出る/二重送信の可能性 → トランザクション、POST後redirect、二重submit防止、ログでINSERT回数確認が必要。

（この要約は、会話内に貼られた controller / blade / routes / model / route:list 出力と、表示されたDB例外メッセージを元に作成。）


チャット⑲

これまでの作業履歴（DB構築/変更点まとめ）

DB基盤

.env を PostgreSQL（DB_CONNECTION=pgsql / JPBA_MAIN） をメインとして運用する前提に統一。

CACHE_STORE=database / QUEUE_CONNECTION=database を使う前提（＝DBテーブル利用型）。

プロボウラーグループ機能（グルーピング基盤）

groups テーブル（=Groupモデル）を前提に設計：

主なカラム：key, name, type, rule_json(JSON), retention, expires_at, show_on_mypage, preset, action_mypage, action_email, action_postal 等

rule_json は JSON で保存し、モデル側で array cast。

group_members テーブル（pivot）で pro_bowlers と groups を多対多にリンク：

主なカラム：group_id, pro_bowler_id, source, assigned_at, expires_at, timestamps

リレーション追加：

ProBowler::groups()（belongsToMany）

Group::members()（belongsToMany）

ルール判定エンジン

app/Services/GroupRuleEngine.php を使い、rule_json に基づいて ProBowler を抽出→ group_members に反映（リビルド/自動作成の基盤）。

大会参加者グループ自動作成

TournamentEntry（tournament_entries）を参照して「大会参加者」条件でグループ作成→ group_members にリンクする流れを追加（大会と参加者の紐付けは pivot に保持）。

年会費未納（ルール対応）

ルールエンジン側で annual_dues テーブル参照を想定：

annual_dues：pro_bowler_id, year, paid_at 等（※このテーブル自体は未整備なら migration 作成が必要）

メール配信履歴（グループ宛）

配信履歴用に GroupMailout / GroupMailRecipient モデルを追加（＝テーブルも前提）：

group_mailouts：グループ送信の1回分（件名/本文/送信元/状態/件数など）

group_mail_recipients：受信者行（mailout_id, pro_bowler_id, email, status, sent_at, error_message 等）

JOIN時の id 衝突対策：

pro_bowlers.id as bowler_id を明示して select（曖昧な id を排除）。


チャット⑳

これまでの作業履歴（DB構築まわり要約）

DB接続：PostgreSQL（DB_CONNECTION=pgsql, DB_PORT=5433, DB_DATABASE=JPBA_MAIN）で運用。

殿堂機能用テーブル設計（前提）

hof_inductions：殿堂入り本体（pro_id, year, citation, timestamps）

hof_photos：殿堂写真（hof_id, url, credit, sort_order, timestamps）

既存プロフDBを殿堂に流用

プロフィール元：pro_bowlers（id, name_kanji, license_no, public_image_path, dominant_arm, pro_entry_year, etc）

タイトル元：pro_bowler_titles（pro_bowler_id, year, title_name, tournament_name, etc）

.envによるカラム/テーブルのマッピング固定（環境差吸収）

JPBA_PROFILES_TABLE=pro_bowlers

JPBA_PROFILES_*_COL（id/name/slug(=license_no)/portrait/bio/hand/org…）

JPBA_TITLES_TABLE=pro_bowler_titles + JPBA_TITLES_*_COL

環境差吸収の自動検出ロジック

information_schema.columns / tables を参照して、プロフィール/タイトルのテーブル・カラム候補をスキャンして確定（HofService::detectProfileSource, detectTitlesSource）。

管理側（殿堂登録）

HofManageController: slug(=license_no)→pro_bowlers から id を引いて hof_inductions 作成。

写真アップロード：storage/app/public/hof に保存し hof_photos にURL保存、sort_order を自動付与（先頭/末尾）。

削除：写真1枚削除／殿堂レコード一括削除（物理ファイル削除も試行）。

公開側（殿堂表示）

hof.index：hof_inductions×プロフィール元をjoinして年別一覧。

hof.show：殿堂詳細＋写真＋タイトル＋プロフィール抜粋（facts）を表示。

ライセンス番号の扱い

男子/女子で番号重複があるため M/L 接頭辞の区別必須（数字だけ比較は禁止）。

M001297 等のゼロ埋めを正規化して照合する方向で修正。

褒章記録（公認PF/800/7-10等）集計

record_type 系テーブルを検出して COUNT(*) GROUP BY で集計し、プロフィール抜粋に追加する方針。

途中で pluck(DB::raw('COUNT(*)')) を使い stdClass::$COUNT(*) エラーが出たため、COUNT(*) AS cnt で別名を付けて pluck('cnt','type') に修正。


チャット㉑

これまでの作業履歴（DB/集計まわり要約）

スコア登録系

「同一ゲームへの重複登録」を防ぐ前提で、ユニーク条件（大会/ステージ/ゲーム番号/シフト/識別子=ライセンス等の組）を前提に動作調整（フロント即時検知＋サーバ側最終防波堤）。

既存登録ゲームへ同一ライセンスを入れた場合の扱いを整理（エラー表示・入力保持・赤ハイライト等のUI要件に合わせて）。

厳格ルール「1Gに存在しない番号は2G以降で不可」を“常時適用”する方針でフロント/サーバ双方の検証対象に入れた（※予選でも同ルールが効いて誤検知が出たため、適用範囲の再調整議論あり）。

削除処理で ScoreController::deleteOne() 未実装呼び出しが発生（ルーティングとコントローラ実装の整合が必要）。

ステージ設定（予選/準々/準決/決勝のG数など）

ステージごとの設定値を保存→入力画面・集計画面に反映させる流れを扱ったが、保存は成功表示されるのに反映が崩れる状態が複数回発生。

「今どの設定が反映されているか」を画面上に明示する表示（例：設定：予選6G / 準決勝3G / …）を重視して調整。

ランキング集計（速報ページ）

集計対象ステージ、持ち込み（carry）ステージ、ステージ順序（予選→準々→準決→決勝）を前提に、**breakdown（ステージ別スコア配列）**から表示・基準計算を行う構成に整理。

基準点(200×G数)：ヘッダ表示は「対象G数＋carry分」を加算して算出する仕様に寄せた（準決勝分しかカウントされない問題を修正対象に）。

差分表示：ボーダー設定時は「ボーダーより上=トップとの差／下=ボーダーとの差」の出し分けを要件化。

表示用プロフィール：ライセンス数字抽出→ ProfileService::resolveBatch() で名前/写真を解決する流れを前提化。

公開URL（public=1）

公開表示は“成績のみ”に寄せ、管理者用UI（入力に戻る、再表示条件フォーム等）を非表示化する要件が追加（最終的に「入力ページに戻る列の管理者項目を全部隠す」へ絞り込み）。



チャット㉒

これまでの作業履歴（DB構築まわり・要約）

目的：イベント（大会/講習）管理＋会員の参加申込を追加実装する前提で、DBテーブルをPHPマイグレーションで新設する設計を提示。

追加マイグレーション（.sql不使用、up/down付き）：

database/migrations/2025_09_25_000001_create_events_table.php

events テーブル新規作成（未存在なら作成）

主な列：id, title, description, start_date, end_date, capacity, is_open, created_by, created_at/updated_at

index：(is_open, start_date)、created_by

database/migrations/2025_09_25_000002_create_event_registrations_table.php

event_registrations テーブル新規作成（未存在なら作成）

主な列：id, event_id, user_id, registered_at, created_at/updated_at

制約：unique(event_id, user_id)（同一イベントへの重複申込防止）

index：event_id, user_id

方針メモ：

外部キー制約は強制せず（users 等の既存有無に依存しないため）。必要なら後日“変更が必要”扱いで追加する前提。

ロールバックは各マイグレーションの down() で events / event_registrations を drop する設計。

スコア入力/速報ランキング（GameScore）周りを改修

ScoreController@store：ライセンス番号入力の 2G以降バリデーション（「同ステージ1Gに存在する番号のみ許可」）を実装・調整。

性別コードを統一：当初 M/L 前提 → 公式プロフィール側が M/F なので入力/UI/判定を M/F に寄せる（既存互換は残しつつ）。

重複判定のキー：gender + license_number で判定（同番号でも性別で区別）。

apiExistingIds：入力画面の事前検知用API（1G存在チェック/同G既存チェック）を提供。

入力画面JS：localStorage キャッシュと window.__existingIds の更新タイミング不備で 未登録なのに重複/既存扱い になる問題が発生 →
セレクタ変更時に 必ず refreshExistingIds()、送信時の二重挙動/誤判定を抑制。

入力エラー時：**赤ハイライト維持＋入力値維持（old()）**に寄せ、全消えを防止。

プロフィール解決（ライセンス→氏名/写真）を実装

ProfileService：プロフィール元テーブルを env優先→候補テーブル探索→全テーブル推測で自動検出。

正規化：M/F + 数字 で突合（PostgreSQL REGEXP_REPLACE で M0... のゼロ削除等）。

resolveBatch()：ランキング表示側で 一括取得し、行単位フォールバック＆キャッシュ。

速報ランキング表示（result.blade.php）

ライセンス番号からプロフィール（name/portrait_url）を引いて表示。

予選/準決勝/決勝などの ステージ持ち込み（carryPrelim）を制御。

最終的に「決勝を1G勝負にしたい」要望に合わせ、不要なステージが持ち込まれないよう carry 対象を整理（決勝で準決勝も持ち込まない等）。

資格ページ（Eligibility：公開一覧）を追加

3ページ：

/eligibility/evergreen（永久シード）

/eligibility/a-class/m（男子A級）

/eligibility/a-class/f（女子A級）

判定元：プロフィール（pro_bowlers）の項目

永久シード：permanent_seed_date 等

A級：a_license_number ＋ 性別（sex/ライセンス）

追加要望：

女子A級は A級番号昇順ソート

ライセンス表示は 先頭アルファベットを省略して数字のみ表示

名前クリックで個人プロフィールへ遷移（既存の全プロデータ一覧のリンク実装を参考）

メニューリンク対応

route() の指定ミスで A級ページに遷移できない問題が発生 →
ルート引数 gender を明示して呼ぶ形（例：route('eligibility.a_class', ['gender'=>'m'])）や、暫定で url('/eligibility/a-class/m') 直指定案も提示。


チャット㉔

これまでの作業履歴（DB/ルーティング関連の要約）

資格者一覧（Eligibility）導線修正

routes/web.php に eligibility 系ルートを定義・整理

eligibility.evergreen（/eligibility/evergreen）

eligibility.a_class.m（/eligibility/a-class/m → EligibilityController@aClassMen）

eligibility.a_class.f（/eligibility/a-class/f → EligibilityController@aClassWomen）

Blade側リンクを route('eligibility.*') に統一（gender パラメータ方式→固定ルート方式へ）

公開プロフィール遷移（Pro Bowler）修正

一覧→詳細のリンクが route('pro_bowlers.public_show', $id) で解決できるようにルートを定義

コントローラは PublicProfileController@show が実体だったため、ルートのController参照をそれに修正

resources/views/pro_bowlers/public_show.blade.php 表示は既存の PublicProfileController の $view 生成ロジックに依存

大会速報リンク管理（Flash News）追加（DBあり）

新規テーブル flash_news を追加する PHPマイグレーションを作成（title, url, timestamps）

モデル App\Models\FlashNews を追加（fillable: title,url）

編集用コントローラ FlashNewsController を追加（index/create/store/edit/update）

公開用コントローラ FlashNewsPublicController を追加（/flash-news/{id} → 外部URLへredirect）

ルートは auth + role:editor,admin グループ内に /flash-news 管理画面系を定義、公開は別で /flash-news/{id}

/flash-news/{id} が /flash-news/create を誤マッチしないように ->whereNumber('id') を追加

スコア入力ページのルート名付け

既存 Route::get('/scores/input', [ScoreController::class,'input']); に ->name('scores.input') を付与し、メニューから route('scores.input') で遷移可能に

ランキング共有URL（public=1）表示調整（DBではないが運用重要）

resources/views/scores/result.blade.php で ?public=1 時に編集用UIを非表示（ページ内CSSでheader/nav等を隠す方針）

データ全消去（前方テスト用）について

“データだけ全消去”を目的に、PostgreSQL TRUNCATE ... RESTART IDENTITY CASCADE を実行する 専用マイグレーション案（publicスキーマの基盤テーブル除外）を提示した経緯あり

実際に適用したかはこのログだけでは不明（提案・方針提示段階）


チャット㉕

これまでの作業履歴（DB構築・リセット・インポート関連）

環境：Laravel 12.19.3 / PHP 8.2 / PostgreSQL（ローカル 127.0.0.1:5433）

目的：開発用データを全消しして、疑似本番環境を作り、マイグレーション完走＋本番CSVデータを投入してフォワードテスト

1) テスト爆破用DB（testing）構築

.env.testing を作成/修正し、--env=testing で Laravel が pgsql を向くことを確認

php artisan config:show database --env=testing

psql 接続時のポート違い（5432→5433）を解消

migrate:fresh --env=testing を通すため、マイグレーション順序（FK依存）を修正

典型：親テーブル未作成のままFKを張って失敗（pro_bowlers / tournaments / tournament_entry_balls）

対応：create_* を alter/add_* より前に来るようファイル名（タイムスタンプ）を調整、必要なら Schema::hasTable 等でガード

結果：php artisan migrate:fresh --env=testing が全件 DONE（100本近く）で完走

2) テスト爆破用の後片付け

jpba_testing を DROP DATABASE で削除（必要時）

.env.testing を削除または無効化

php artisan config:clear / cache:clear / route:clear / view:clear 等でキャッシュ掃除

3) 開発用DB（JPBA_MAIN）側の整備

php artisan config:show database で default=pgsql, database=JPBA_MAIN, port=5433 を確認

php artisan migrate --force 実行時に Duplicate table が発生（DBにテーブルあるが migrations 記録ズレ）

解決：開発DBを更地化して再構築

php artisan migrate:fresh --seed（seed不要なら無し）

管理者ユーザ作成：tinker/Seederで作成する方針を提示（email: admin@example.com
 等）

4) 本番CSV投入中に出た代表エラーと対処方針

varchar(5) 長すぎ：MM1465 等が 5文字制限列に入らず失敗

対応：スキーマ拡張（varchar長変更） or 取込時正規化

NOT NULL sex がNULL：pro_bowlers.sex が NULL で失敗

対応：取込側マッピング（不明ID追加＋DEFAULT） or 一時nullable化

5) 承認ボール（approved_balls）CSVインポート実装・修正

ルーティング衝突：/approved_balls/import が resource の show(/approved_balls/{id}) に吸われ、ApprovedBallController::show() 未定義で落ちた

対応：importルートをresourceより上に置く＋ show を except、リンク名も import_form(GET) / import(POST) を使い分け

CSV列順ズレで release_year に文字列が入り Invalid text representation integer 発生

対応：CSVの実列順（A:id B:brand C:ball_name D:発売日/発売年度 E:カナ F:○× G:登録日）に合わせて取り込みを修正

ID取り込み必須（CSVのA列idを主キーとして保持）

対応：updateOrCreate(['id'=>$id], [...]) で upsert

取込後にPostgresのシーケンスを MAX(id) に追従（setval(pg_get_serial_sequence(...))）

追加対応：approved は ○/× を boolean に変換、年/日付はパースして型整合

6) 発売日を“年だけ”→“日付まで”にしたい方針

要望：一覧側が年度のみで、インポートCSVは日付を含むため齟齬

方針：approved_balls に release_date (date) を導入し、既存 release_year を移行/置換

マイグレーション：release_date 追加→ release_year から YYYY-01-01 で埋め→ release_year 削除

コード：コントローラ/ビュー/CSV取込を release_date 基準に更新（castsも追加）


チャット㉖

これまでの作業履歴（DB / PostgreSQL）

要望：PostgreSQL内のカラム（列）確認を、PostgreSQL側のGUI操作なしで VSCodeだけで完結したい。

最初に提案：VSCode拡張（Microsoft PostgreSQL / SQLTools）でGUI確認＆SQL実行。

方針変更：ユーザー希望により 拡張なしでの確認方法に切替。

確定手段：VSCode内のターミナル（PowerShell）で psql を使って確認する手順を提示。

接続例：psql -h localhost -p 5433 -U postgres -d JPBA_MAIN

一覧系：\dn（スキーマ）、\dt public.*（テーブル一覧）

カラム確認：\d+ public.<table>

SQL確認：information_schema.columns で列名/型/NULL/DEFAULT を取得（ordinal順）

制約確認：pg_attribute / pg_index / pg_attrdef で PK / UNIQUE / NOT NULL / DEFAULT を確認

出力：\copy (...) TO 'columns_<table>.csv' CSV HEADER で ローカルCSVへ書き出し

文字化け対策：chcp 65001 や \encoding UTF8、ページャ無効 \pset pager off も案内

DBは PostgreSQL 前提で進行。Laravel側が一時 mysql 接続になっていたため、.env の DB_CONNECTION=pgsql 等を確認・切替する流れが発生（設定反映のため config/cache クリアも話題に）。

psql 接続で 5432 が拒否され、実際の待受ポートが 5433 であることを確認。
psql -h localhost -p 5433 -U postgres -d JPBA_MAIN で接続できる状態まで到達。

pro_bowler_profiles で license_no 列が存在しないエラーが発生。DB実態の列として pro_bowler_id が該当という整理になり、参照・検索キーを license_no → pro_bowler_id に合わせる方針が出た。

インストラクター登録で cert_no 列不足が原因のエラーが発生。
対策として instructors に cert_no 追加（NULL可 + UNIQUE想定）と、必要なら license_no の NOT NULL緩和の話が出た。あわせて 主キーが license_no の設計が認定系（license_no無し）と衝突する注意点も整理。

大会の主催/協賛 “よく使う団体マスタ” 用に organization_masters テーブルを作るマイグレーションが追加済：
id, name, url(nullable), timestamps

organization_masters の運用画面（Blade）として organizations の 一覧/新規/編集が用意されている状態。

直近は「大会作成フォーム」で organization_masters を検索して主催・協賛へ流し込む機能を復活させる作業（API/モデル/コントローラ追加）に着手している段階。



チャット㉗

これまでの作業履歴（DB/保存まわり要約）

目的
大会（tournaments）の詳細ページ右カラムに
①日程・成績（「日付グループ」＋「ラベル」＋「PDF/URL」）
②褒章（パーフェクト/800/7-10：画像＋選手名＋ゲーム＋レーン等）
を表示し、編集画面から登録・更新できるようにする。
併せて 主催/協賛等（複数・リンク）をマスタ検索で登録して詳細へ反映。

1) DB（テーブル/カラム追加）

tournaments テーブルに JSON系カラムを追加（保存先）

sidebar_schedule : JSON配列（例：[{date,label,href}]）

award_highlights : JSON配列（例：[{type,player,game,lane,note,title,photo}]）

gallery_items : JSON配列（例：[{photo,title}]）

simple_result_pdfs : JSON配列（例：[{file,title}]）

既存：poster_images / extra_venues 等も array cast

tournament_organizations テーブルを使用（大会×組織の複数紐付け）

カラム想定：tournament_id, category, name, url, sort_order

Model：TournamentOrganization は timestamps 無効（created_at/updated_at無し環境対応）

organization_masters テーブル（組織マスタ：検索用）

name, url を保持

2) Laravel Model（Cast / Relation）

Tournament の $casts に上記 JSON カラムを array として定義
sidebar_schedule, award_highlights, gallery_items, simple_result_pdfs, poster_images, extra_venues

Tournament relations

organizations() = hasMany(TournamentOrganization)

venue() / files() など既存と併用

3) Controller（保存ロジックの中心）

TournamentController@store/update で以下を処理

validateAndNormalize()：基本項目のvalidation＋日付/URL整形

buildOrgRowsAndTexts()：主催/協賛等の入力を吸収し tournament_organizations に保存
＋旧カラム host/sponsor/support/special_sponsor をテキスト同期（互換維持）

buildSidebarSchedule()：schedule[][date,label,url] と schedule_files[] を統合し sidebar_schedule に保存

URLがあれば優先、なければPDF upload→storage path

buildAwardHighlights()：awards[][type,player,game,lane,note,title] と award_files[] を統合し award_highlights に保存

buildGalleryAndResults()：既存 keep 指定＋追加アップロードを統合し JSONに保存

update は tournament_organizations を delete→saveMany で同期

4) View（入力/表示）

tournaments/edit.blade.php

主催/協賛：マスタ検索→選択カテゴリに行追加（org[][category|name|url]）

右サイド日程：繰り返し行（schedule[][date|label|url]＋schedule_files[]）

褒章：繰り返し行（awards[][type|player|game|lane|note|title]＋award_files[]）

tournaments/show.blade.php

日程：sidebar_schedule を date で groupBy して表示

褒章：カテゴリキーは type（以前 category 参照ミスが原因で表示されない事象あり）

主催/協賛：tournament_organizations からカテゴリ別表示（旧テキスト列フォールバック）

5) 発生した不具合（今回の論点）

「保存できない/反映されない」に見えた主因は 表示側のキー不一致など

褒章：保存は type だが show で category 参照していた → “該当なし” になる

ほか、controller内で ->store 行のタイポ/構文エラーが起き、一覧アクセス不能になったことがある

update 時に、フォーム送信の有無で JSONが空上書きされる懸念があり 既存維持ガードの案が出た


チャット㉘

これまでの作業履歴（DB関連・要約）

tournaments テーブルに JSON系カラムで拡張する前提で進行（既存カラムは維持）。

Tournamentモデル側で以下を $fillable に追加 / $casts を array に設定して運用：

poster_images（ポスター複数）

extra_venues

sidebar_schedule（右サイド「日程・成績」：[{date,label,href}]）

award_highlights（褒章：[{type,player,game,lane,note,title,photo}]）

gallery_items（ギャラリー：[{photo,title}]）

simple_result_pdfs（簡易速報PDF：[{file,title}]）

**右サイド日程（sidebar_schedule）**はフォーム送信をControllerで整形してJSON保存：

URL > PDF > 既存維持 の優先で href 決定

「ラベルのみ（リンク無し）」行も保存可能（見出し用途）

date+label+href で 重複排除してDBへ保存

**褒章（award_highlights）**は画像アップロード時に photo を storage/public に保存し、JSONにパス保持する想定で実装：

ただし後段で「編集画面で保持されず消える」問題が発生し、既存photoの keep（維持）処理が未完成/不足という論点が出た

主催/協賛などは別テーブル（tournament_organizations）で管理し、更新時は

delete → saveMany で同期

旧カラム（host/sponsor/support/special_sponsor）にもテキストを同期（フォールバック表示用）

画像ストレージ運用：

hero_image_path（トップ画像）

image_path（旧互換の単体ポスター）

poster_images（複数ポスター）

gallery / pdf / awards も storage/public 配下に保存し、DBには相対パスを保持

破壊的変更が起きた例：

layouts/app.blade.php が Blade として解釈されず文字列表示になる症状（※DBではなくビュー側の崩壊）

追加要件として 大会タイトル用ロゴをDBに保存するカラムが必要になり、

「マイグレーションが無いので新規作成する」方針が確定（title_logo_path などを想定）

create/edit でアップロード→DB保持→showで表示、かつ編集で保持（keep）も必要、という要求が確定

発生したDBエラー

SQLSTATE[42703]: Undefined column: 7 ERROR: tournaments.result_cards が存在しない

TournamentController@update が result_cards を tournaments テーブルへ update しようとして落ちていた。

方針（DB設計）

「終了後：優勝者・トーナメント」等の可変データは tournaments の JSON（PostgreSQLなら JSONB）列で保持する構成に寄せた。

想定カラム（いずれも配列/オブジェクトで保持し、Model側で cast 前提）

sidebar_schedule（右サイド日程・成績）

award_highlights（右サイド褒章）

gallery_items（ギャラリー）

simple_result_pdfs（簡易速報PDF）

result_cards（優勝者カード：見出し/選手/ボール/補足/URL/写真パス/PDFパス）

画像/ファイルはDBへバイナリ保存せず、public disk に保存したパス文字列をJSON内に保持（例：tournament_results/...jpg / tournament_pdfs/...pdf）。

Controller側のDB更新仕様（結果としての要件）

buildResultCards() で result_cards を生成し、$validated['result_cards'] として create/update に渡す（= DB列が必須）。

既存維持（keep）仕様：result_card_keep[i][photo|file] が来た場合、未アップロードでも既存パスを維持する。

結論（DB側で必須のこと）

tournaments テーブルに result_cards（JSON/JSONB）列が存在しないと必ず落ちる。

同様に上記の JSON列（sidebar_schedule 等）も、Controller/Viewの実装に合わせて migration + model casts を揃える必要がある。


チャット29

# これまでの作業履歴（DB/ER整備：JPBA-system）

## 目的
- DBが肥大化し会話だけだと前提共有が破綻しがち → 「正本」をGitHub/Docsに置き、ERを自動生成できる運用へ移行。

## 実施内容（要点のみ）
- PostgreSQL（JPBA_MAIN / public / 5433）を対象に、information_schema 等で
  - テーブル存在確認
  - *_id カラム/参照候補の洗い出し
  - FK制約が実DBに無いテーブル（例: pro_test）を確認（=DB上FK 0でも“参照関係”は存在する）
- docs/db/data_dictionary.md を正本化
  - テーブル一覧 + 役割 + 主キー +（DBで確認できた）FK/（想定）参照を整理
  - 重複マスタ記述の整理、record_type_id 等の曖昧点を注記
- ERの自動生成パイプラインを確立
  - `php tools/generate_er_from_dictionary.php` → `docs/db/ER.dbml` を生成（ERは手編集しない運用）
- “ERに未記載のRef” を自動検出する仕組みを導入
  - `docs/db/refs_missing.md`（*_id カラムからRef候補を抽出）
  - `tools/apply_refs_missing.php` で Suggested additions を data_dictionary.md へ一括反映
  - 未確定/不明な参照は `docs/db/refs_skipped.md` に退避（後でADRで決定する前提）
- 未解決参照の実データ整合性チェック
  - LEFT JOIN で「参照先なし件数」を確認（missing_count が 0 で不整合なし）

## 生成/更新された主要成果物
- docs/db/data_dictionary.md（正本）
- docs/db/ER.dbml（自動生成）
- docs/db/refs_missing.md（未反映Ref候補）
- docs/db/refs_skipped.md（未確定Ref保留）
- tools/generate_er_from_dictionary.php（辞書→ER生成）
- tools/apply_refs_missing.php（missing→辞書反映）

## 備考
- gitの CRLF→LF 警告はエラーではなく警告（push成功している）
- 次の作業は refs_skipped.md の未確定参照を ADR（例: ADR-0002）で決定して固定化する段階。

## 2026-01-27〜28 パスワードリセット〜ログイン復旧メモ

### 状況
- パスワードを変更したはずなのにブラウザでログインできない。
- `MAIL_MAILER=log` のため、リセットメールは実メールではなく `storage/logs/laravel.log` に出力される。

### 確認したこと（ログ）
- `ForgotPasswordController@sendResetLinkEmail hit` → リセット要求が到達
- `status: passwords.sent` → リセットリンク生成・送信（ログ出力）
- ログに `Reset Password: http://127.0.0.1:8000/reset-password/{token}?email=...` が出る
- `ForgotPasswordController@reset status: passwords.reset` → パスワード更新成功
- その後 `role-mw ... actual:"member" uid:2` → 認証後のミドルウェアも動作

### 途中で出た典型エラーと原因
- `passwords.user`：そのメールアドレスのユーザーが存在しない（入力ミスが原因）
- `passwords.token`：トークン不一致/期限切れ/メール不一致（古いURL使用 or email入力違い）
- ログイン不可：パスワードに全角/末尾スペース等が混入して `Hash::check` が false になっていた可能性

### 最終的に確実に直った手順（tinkerで検証・復旧）
1) ユーザー存在確認
```php
use App\Models\User;
User::where('email','domaine-d@i.softbank.jp')->exists(); // true

## これまでの作業履歴（DB/認証まわり：パスワードリセット不具合対応の要約）

- **事象**：パスワード変更（リセット）後にブラウザログイン不可。  
  `Password Broker` の戻り値が `passwords.user` / `passwords.token` になり分岐調査。

- **DB確認（tinker）**
  - `users` に対象メールが存在することを確認：`User::where('email', ...)->exists()` → `true`
  - 対象ユーザー取得し、`password` ハッシュ・`updated_at` を確認。
  - `Hash::check(plain, $u->password)` が `false` のケースを起点に、**DB側が更新されていない/入力値不一致**を切り分け。

- **原因パターン整理**
  - `passwords.user`：メール誤入力（例：`domane`）または users 側未作成/未一致。
  - `passwords.token`：古いURL（token）使用、token/email組み合わせ不一致、期限切れ。
  - フォーム側で email 手入力がミス誘発要因になり得る（token と email がズレると失敗）。

- **Reset URL（token）取得フロー**
  - ローカルメール送信は log に出力される前提で、`storage/logs/laravel.log` から最新の `Reset Password:` 行を抽出。
  - PowerShell：`Select-String ... "Reset Password:" | Select-Object -Last 1` で最新URLを取得し、**必ず最新リンク**でリセット画面へ。

- **ルーティング確認**
  - `GET reset-password/{token}`（フォーム表示）
  - `POST reset-password`（更新処理 / `password.update`）
  - `route:list | findstr reset-password` で一致を確認。

- **実装（DB/認証連携の要点）**
  - `Auth\ForgotPasswordController` を使用し、`reset()` で `Password::reset(...)` を実行して `users.password` を更新。
  - 成功ログ：`passwords.reset` と `updated user` を出力して **DB更新成功を確証**。

- **tinker による最終確定テスト**
  - 直接 `Hash::make()` でパスワード再設定 → `save()` → `Hash::check()` → `Auth::attempt()` を実行し、認証成功を確認。
  - 最終的に **ブラウザログインも成功**。

- **Git状態整理（作業成果物）**
  - modified：`ForgotPasswordController.php` / `reset-password.blade.php` / `docs/chat/worklog_db.md`
  - untracked：`PasswordSetupController.php` / `password_setup_request.blade.php` / `password_setup_reset.blade.php` / `check_tables.sql`
  - push手順：`git add -A` → `git commit -m ...` → `git push origin main`

（以上）

## 2026-04-10 INSTRUCTOR / tournament entry / ball workflow の運用導線仕上げ

- 目的:
  - 2026-04-09 までに整えた `instructor_registry` の資格遷移/current-history 運用を、日常運用の画面導線まで落とし込む。
  - あわせて、`pro_bowlers.member_class` / `can_enter_official_tournament` を大会エントリー実処理へ反映し、使用ボール登録まで含めて会員導線を閉じる。
  - さらに、`registered_balls` / `used_balls` の検量証番号・仮登録・有効期限の扱いを、一覧だけでなく入力画面/Controller まで一貫させる。

- 実施内容（INSTRUCTOR）:
  - `app/Http/Controllers/AuthInstructorImportController.php`
  - `resources/views/instructors/import_auth.blade.php`
  - `resources/views/instructors/index.blade.php`
    - `AuthInstructor.csv` 取込で対象年度を指定できるようにし、
      - 新規 / 更新 / スキップ
      - `license_no` 一致結線
      - 複合条件一致結線
      - 未結線
      - `renewed_current`
      - `promoted_to_pro_bowler`
      - `promoted_to_pro_instructor`
      - `inactive_in_source`
      などのサマリをセッション経由で一覧に表示できるようにした。
    - 一覧に「認定CSV取込」導線を追加し、年次更新運用を取込画面 → 一覧確認までつなげた。
  - `app/Http/Controllers/ProBowlerImportController.php`
  - `resources/views/pro_bowlers/import.blade.php`
  - `resources/views/pro_bowlers/index.blade.php`
    - `Pro_colum.csv` 取込でも、
      - 新規 / 更新 / スキップ
      - `member_class` 内訳
      - current の `pro_bowler` / `pro_instructor` 件数
      - `reactivated_certified` / `qualification_removed` / `promoted_*`
      を可視化するサマリを追加した。
    - `instructor_registry` 反映結果を import 後に画面で確認しやすいようにした。
  - `app/Models/ProBowler.php`
  - `app/Http/Controllers/ProBowlerController.php`
  - `resources/views/pro_bowlers/list.blade.php`
  - `resources/views/pro_bowlers/index.blade.php`
  - `resources/views/pro_bowlers/athlete_form.blade.php`
    - `/pro_bowlers` の一覧・管理画面で
      - `member_class`
      - `can_enter_official_tournament`
      - `currentInstructorRegistry`
      を表示できるようにし、競技対象者 / プロインストラクター / 名誉・海外などの判定結果を管理画面から確認できるようにした。
  - `app/Http/Controllers/InstructorController.php`
  - `resources/views/instructors/index.blade.php`
  - `resources/views/instructors/edit.blade.php`
    - 既存 `DELETE /admin/instructors/{instructor}` ルートに対応する `destroy()` を実装。
    - manual 由来行は物理削除せず、`is_current = false` / `is_active = false` / `supersede_reason = qualification_removed` / `renewal_status = expired` として `retired/history` 化するよう整理した。
    - 一覧・編集画面に「退会済みにする」導線を追加した。

- 実施内容（大会エントリー / 会員導線）:
  - `app/Http/Controllers/TournamentEntryController.php`
  - `resources/views/member/entry_select.blade.php`
    - 会員向け大会エントリー画面に、現在の判定（ライセンスNo / 氏名 / 会員区分 / 有効状態 / 公式戦出場可否）を表示するパネルを追加。
    - `pro_bowler_id` 未結線、`member_class != player`、`can_enter_official_tournament = false`、`is_active = false` の場合は大会エントリー自体を選択できないようにした。
  - `app/Http/Controllers/DrawController.php`
  - `app/Http/Controllers/TournamentEntryBallController.php`
    - シフト抽選 / レーン抽選 / 大会使用ボール登録の各 controller に、
      - 本人以外の entry 遮断
      - `status = entry` 以外の遮断
      - `member_class` / `can_enter_official_tournament` / `is_active` によるサーバー側ガード
      を追加した。
    - `TournamentEntryBallController` では `registered_balls -> used_balls` 同期を `edit()` 表示前に実行するようにした。
  - `resources/views/member/entry_balls_edit.blade.php`
    - 大会使用ボール登録画面をプレースホルダから本実装へ置換。
    - 対象大会 / 現在登録数 / 追加可能数 / 検量証必須フラグを表示。
    - 使用可能ボール一覧に対して、
      - すでに登録済み
      - 仮登録 / 検量証待ち
      - 期限切れ
      - 使用可能
      の状態を表示しつつ、追加のみ・最大12個までの運用を画面から扱えるようにした。

- 実施内容（registered_balls / used_balls の検量証運用整備）:
  - `app/Http/Controllers/RegisteredBallController.php`
  - `resources/views/registered_balls/index.blade.php`
    - 本登録 + 仮登録（`used_balls` 由来）を統合表示する一覧に、状態 / 表示元 / 修正導線を追加した。
  - `app/Http/Controllers/UsedBallController.php`
  - `resources/views/used_balls/index.blade.php`
    - `used_balls` 一覧に、仮登録 / 使用可能 / 期限間近 / 期限切れ の状態フィルタと表示を追加した。
    - 会員は自分のボールのみ参照・編集できるようにし、管理者/編集者のみ全件閲覧できるよう整理した。
    - `edit()` を追加し、controller 側で create/edit/update の ownership check を完結させた。
    - `inspection_number` を空に戻した場合、`expires_at = null` として仮登録に戻すよう整理した。
  - `resources/views/registered_balls/create.blade.php`
  - `resources/views/registered_balls/edit.blade.php`
  - `resources/views/used_balls/create.blade.php`
  - `resources/views/used_balls/edit.blade.php`
    - create/edit 画面で、検量証番号の有無に応じて
      - 本登録 / 仮登録
      - 有効期限の自動表示
      - 会員向けの誤操作防止
      をわかりやすく表示するUIに整理した。

- 現時点の判断:
  - `instructor_registry` を正本にした current/history 運用は、取込 → 一覧確認 → 手動結線 → 退会/履歴化 まで、業務導線として一通り回せる状態になった。
  - `member_class` / `can_enter_official_tournament` は「表示用」ではなく、大会エントリー・抽選・大会使用ボール登録まで含めた実処理条件として使う状態になった。
  - `registered_balls` / `used_balls` は、検量証番号の有無による仮登録 / 本登録 / 有効期限の扱いが、一覧・入力画面・controller で一貫した状態になった。

- 今回の扱い:
  - アプリ実装・画面導線の整備のみであり、DBスキーマ変更は発生していない。
  - そのため migration / `docs/db/data_dictionary.md` / `docs/db/ER.dbml` / `docs/db/refs_missing.md` の更新は不要。
  - `docs/db/refs_skipped.md` についても、新しい参照保留や例外ルールの追加は無かったため更新不要。

## 2026-04-16 大会詳細 / 成績 / 配分 / 会場検索UIの整備と辞書差分確認

- 目的:
  - `tournaments.create` / `tournaments.edit` の大会詳細入力と会場検索を実運用レベルまで整える。
  - `tournament_results` の新規登録 / 一括登録 / 編集 / 配分 / 再計算 / タイトル反映までを一連の運用導線として閉じる。
  - あわせて、Phase 2 の未処理である「大会系スキーマの辞書確定」に向け、現物スキーマとの差分確認を始める。

- 実施内容（大会詳細 / 会場検索）:
  - `resources/views/tournaments/create.blade.php`
    - `edit` 側と同水準になるよう大会詳細入力UIを拡張。
    - 会場検索UIを追加し、会場マスタから選ぶと `venue_id / venue_name / venue_address / venue_tel / venue_fax / venue URL` へ反映できるよう整理。
  - `resources/views/tournaments/edit.blade.php`
    - 会場検索APIの呼び先を `create` と同じ `/api/venues/search` / `/api/venues/{id}` に統一。
  - `app/Http/Controllers/VenuePageController.php`
    - 会場検索JSONと会場詳細JSONの返却を `create` / `edit` で共用できるよう整理。
  - `routes/web.php` / `routes/api.php`
    - venue API が二重登録されていた状態を整理し、最終的に `/api/venues/search` / `/api/venues/{id}` の1系統で運用する形へ整理。
  - 動作確認:
    - 仮会場データを投入し、`tournaments.create` / `tournaments.edit` の両方で
      - 会場検索
      - 候補表示
      - クリック選択
      - 会場項目への自動反映
      が通ることを確認。

- 実施内容（大会成績 / 配分 / タイトル反映）:
  - `app/Http/Controllers/TournamentResultController.php`
    - 新規登録 / 一括登録 / 編集の保存時に、配分済み順位であれば `points` / `prize_money` を自動反映する構成を確認・整理。
    - `賞金・ポイント再計算` と `タイトル反映` の処理を実地確認しやすい状態に整備。
  - `app/Http/Controllers/PointDistributionController.php`
  - `app/Http/Controllers/PrizeDistributionController.php`
    - 配分保存後に大会成績一覧へ戻るよう戻り先を整理。
  - `resources/views/tournament_results/show.blade.php`
    - `ポイント配分` / `賞金配分` / `賞金・ポイント再計算` / `タイトル反映` の運用順が分かる説明へ更新。
  - `resources/views/tournament_results/create.blade.php`
  - `resources/views/tournament_results/batch_create.blade.php`
  - `resources/views/tournament_results/edit.blade.php`
    - 見出し・戻り導線・補助リンクを追加し、平均目安がその場で分かるUIへ整理。
  - 動作確認:
    - 大会作成 → 詳細表示 → 成績一覧 → 成績登録 の基本導線が通ることを確認。
    - 配分設定後、成績登録 / 一括登録 / 編集で `points` / `prize_money` が保存時に自動反映されることを確認。
    - `賞金・ポイント再計算` は「配分を後から変更した場合の再計算」用途として動作することを確認。
    - `タイトル反映` は、1回目が新規作成、2回目が既存扱いとなり、冪等であることを確認。

- 実施内容（大会一覧 / 詳細UI整理）:
  - `resources/views/tournaments/index.blade.php`
    - 一覧をカード型UIへ変更し、開催期間 / 申込期間 / 会場 / 主操作 / その他操作を1大会ごとに把握しやすく整理。
  - `resources/views/tournaments/show.blade.php`
    - 成績一覧 / 配分導線へ迷わず移動できるよう上部導線を整理。
  - `resources/views/tournament_results/show.blade.php`
    - 成績画面のボタン順と案内文を実運用順に合わせて整理。

- 現物スキーマ確認（Phase 2 未処理の切り分け）:
  - `tournament_awards` の現物列は
    - `id`
    - `tournament_id`
    - `rank`
    - `prize_money`
    - `created_at`
    - `updated_at`
    であることを確認。
  - `tournament_entries` には
    - `waitlist_priority`
    - `waitlisted_at`
    - `waitlist_note`
    - `promoted_from_waitlist_at`
    - `preferred_shift_code`
    が存在することを確認。
  - `tournament_draw_reminder_logs` / `tournament_auto_draw_logs` の両テーブルが現物DBに存在することを確認。
  - この結果、Phase 2 の残課題は「大会系の辞書・ER・現物スキーマ再同期」に絞れることを確認した。
  - 特に `tournament_awards` / `tournament_points` と、実運用で使っている `prize_distributions` / `point_distributions` の役割整理は未完了のまま残っている。

- 現時点の判断:
  - 大会詳細 / 会場検索 / 成績登録 / 配分 / 再計算 / タイトル反映 / 一覧UI まで、このチャットで運用導線は大きく前進した。
  - 次の自然な1バッチは、DB変更の要否を切り分けたうえで
    - `docs/db/data_dictionary.md`
    - `docs/db/ER.dbml`
    - 必要なら migration
    をセットで更新し、大会系スキーマの正本を確定すること。

## 2026-04-16 大会速報（ライブスコア）再整備の着手方針

- 目的:
  - 既存の `game_scores` / `stage_settings` / 速報ランキング実装の痕跡を踏まえつつ、JPBA-system における「大会速報」の正本設計と実装順序を明文化する。
  - まずはスマホ閲覧前提で利用頻度が最も高い **①スコア順位速報** を先行対象とし、他方式は後続へ切り分ける。

- この時点の共通認識:
  - 大会速報とは、リアルタイムで進行中の公式大会について、1ゲームごとに登録されるスコアを集計し、Web上で順位表として公開する機能を指す。
  - 主用途はスマホ閲覧であり、同時アクセスが多いことを前提に **軽量で見やすい公開速報画面** を目指す。
  - 速報から大会成績への反映は、誤反映防止のため **反映ボタン方式** とする。
  - ②ラウンドロビン、③トーナメント、④ダブルエリミネーション、⑤シュートアウトは後続フェーズに分離し、今回は要件化のみ先に行う。

- 先行対象（今回の設計スコープ）:
  - ① スコア順位速報
    - スコア高順位で並べる
    - 同スコア時は、同大会内の過去ゲーム差が少ない選手を上位にする
    - 予選 / 準々決勝 / 準決勝 / 決勝のようなステージ進行を扱う
    - ステージごとに通過人数を持てる
    - ステージごとに直前ステージの持ち越し（carry）有無を持てる
    - シフト別集計 / シフト合算 / 各シフト集計後に全体合算 を扱える
    - 男女別集計 / 男女合算 を扱える

- 後続フェーズ（今回は着手しないが要件として固定）:
  - ② ラウンドロビン方式
  - ③ トーナメント方式
  - ④ ダブルエリミネーション方式
  - ⑤ シュートアウト方式

- 既存資産として確認済みのもの:
  - `docs/db/data_dictionary.md`
    - `game_scores` が `tournament_id / stage / shift / gender / license_number / name / entry_number / game_number / score / pro_bowler_id` を保持する正本候補として定義済み。
    - `stage_settings` が存在し、ステージ別設定を持つ前提がすでにある。
  - 既存 worklog には、過去に
    - スコア入力の重複防止
    - `stage_settings` の保存と反映
    - 速報ランキング（`result.blade.php`）
    - `carryPrelim`
    - `public=1` の公開表示
    - `ProfileService::resolveBatch()` による氏名 / 写真解決
    まで扱った履歴が残っている。

- 今後の実装方針（次の自然な順序）:
  1. 既存の速報関連実装の棚卸し
     - どの Controller / Blade / Route / Service が残っているか確認
     - 残す実装と捨てる実装を分ける
  2. ①スコア順位速報の集計条件を正本化
     - ステージ
     - carry 有無
     - 通過人数
     - シフト集計単位
     - 性別集計単位
     をどこに保持するか決める
  3. 公開速報画面をスマホ前提で整理
     - 管理入力画面とは分離
     - 軽量表示
     - 公開URL固定
  4. 速報 → 大会成績の反映ボタン導線を設計
     - 反映対象ステージ
     - 反映時の順位 / ポイント / 賞金 / タイトルの扱い
     を整理する
  5. ②〜⑤の他方式は、①の土台を作った後に別フォーマットとして拡張する

- 現時点の判断:
  - 「大会速報」は新規機能というより、既存の `game_scores` / `stage_settings` / 速報ランキング実装を再整理して本実装へ持っていく作業と捉えるのが自然。
  - 今回は共通認識の固定が目的であり、まだ DB 変更は行っていない。
  - 次バッチは、既存の速報関連ファイル棚卸しを行い、①スコア順位速報に必要な最小単位の実装対象を確定する。

## 2026-04-17 大会速報（ライブスコア）入力UI・結果表示の実装前進

- 目的:
  - 2026-04-16 に固定した「①スコア順位速報」を、実際に入力・確認できるレベルまで前進させる。
  - とくに、手入力運用で事故が起きやすい
    - 過去ゲーム点数の見落とし
    - 同一選手の識別揺れ
    - 速報画面と入力画面の往復不足
    を先に潰す。

- 実施内容（入力画面 / `scores.input`）:
  - `ScoreController` / `scores.input` を中心に、速報入力ページの導線を復旧・整理した。
  - レイアウトから速報入力ページへ入れる導線を追加した。
  - 入力条件として
    - 大会
    - ステージ
    - ゲーム番号
    - シフト
    - 識別方法（ライセンス番号 / エントリーナンバー / 氏名）
    - 性別
    を切り替えられるようにした。
  - 2ゲーム目以降の入力時、ライセンス番号または氏名を入れると
    - 過去ゲーム点数
    - 今回入力を加味した今回込合計
    をその場で確認できるUIへ整理した。
  - ライセンス番号入力時の照合情報として、氏名 / ライセンス番号を横に表示できるようにした。
  - 氏名入力時は候補表示（datalist）を出せるようにした。
  - 候補表示は当初「全選手」が出ていたため、2ゲーム目以降のみ・かつ大会ごとの登録選手だけを候補にする構成へ整理した。
  - 大会参加者候補は `tournament_entries.status = entry` を正本として扱い、性別指定がある場合は候補もその条件で絞るようにした。
  - score input 画面では、ライセンス番号の下4桁入力でも
    - 氏名照合
    - 過去点数参照
    - 同一選手判定
    が通るように寄せた。

- 実施内容（速報表示 / `scores.result`）:
  - 速報ページで、ライセンス番号だけでなく氏名も表示できるようにした。
  - 速報画面から `〇ゲーム目まで` を切り替えて再表示できる導線を追加した。
  - `public=1` 公開画面の運用は維持しつつ、管理入力側と公開側を分けたまま改善を進めた。

- 実施内容（識別 / 集計ロジック）:
  - `ScoreController` / `ScoreService` 側で、ライセンス番号入力・氏名入力が混在しても、同一人物として過去点数を拾いやすいように識別解決を補強した。
  - 速報表示の氏名解決は、ライセンス番号から `pro_bowlers` を引いて表示する方向で整理した。
  - 2ゲーム目以降の入力補助では、前ゲーム以前の `game_scores` を引いて history / total を返すAPIを安定化させた。

- 動作確認できたこと:
  - 入力画面で前ゲーム点数が表示されること。
  - 氏名入力時の候補表示が動くこと。
  - 速報ページで氏名が表示されること。
  - ライセンス下4桁入力でも、少なくとも score input 内では過去点数参照が動くこと。
  - 2ゲーム目以降の候補が「その大会の登録選手」に絞られること。

- この時点で残った課題:
  - `ScoreController.php` について、チャット添付版とローカル最新版に差異があり、途中で「1000行問題」が発生した。
    - そのため、以後の `ScoreController.php` 修正は、必ずユーザーのローカル実ファイルを基準に差分確認してから行う必要がある。
  - 速報画面で `3G → 2G → 3G` に戻れなくなるケースの最終修正は未完了。
  - `0099 古川` のように、画面上では見えているのに削除できない行がある。
    - これは `license_number` 保存行と `name` 保存行が別扱いになっている、または stage / game / shift / gender 条件が削除検索と噛み合っていない可能性が高い。
    - ただし、この修正は `ScoreController.php` の最新版確認後に着手すべきとして保留にした。
  - 下4桁ライセンス入力は速報入力では使いやすいことが確認できたが、他画面では `M/F + 本来のライセンス番号` 前提のままなので、共通化は今後の改善候補として後続へ送る。

- 現時点の判断:
  - 「①スコア順位速報」は、単なる方針段階から、実際に手入力・速報表示・過去点数確認まで通る段階へ前進した。
  - 一方で、削除ロジックと `ScoreController.php` のローカル最新版差分確認が残っているため、次チャットの最初はこの切り分けから再開するのが安全。
  - DB辞書の正本整理より前に、まずは現行速報実装の安定化（特に `ScoreController.php` の最新版基準化）を優先すべき段階に入った。
