# ランキング・シード・優先出場順位の運用方針

更新日: 2026-06-30

## 目的

公式ランキング取込、年度末確定ランキング、翌年度シード生成、全日本選手権用の年度途中ランキング、大会別優先出場順位を、別々の手入力運用に戻さないための方針を固定する。

この文書は、次のActive Backlog項目の判断をまとめる。

- 公式ランキング取込画面を、補助画面として残すか年度末確定ランキング管理画面へ整理するか決める
- 男子ranking snapshotの `as_of_date` が公式PDF日付と一致しているか確認する
- 実ランキング取込、年度末確定処理、全日本選手権用の年度途中ランキング運用を整理する
- 大会エントリー導線へ優先出場順位をどう反映するか決める

## 結論

`/rankings` は一時的な補助画面ではなく、公式ランキング・年度末確定ランキング管理画面として残す。

正本は以下の順にする。

1. `pro_bowler_ranking_snapshots`
2. `pro_bowler_ranking_rows`
3. `pro_bowler_seed_lists`
4. `pro_bowler_seed_list_players`
5. `tournament_seed_players`
6. `tournament_entries`

`tournament_entries` は参加申込、チェックイン、抽選、欠場、繰り上げなどの参加状態を管理するテーブルであり、シードや優先出場順位の根拠そのものは持たせない。

## 公式ランキング管理

`/rankings` は、JPBA公式PDFから取り込んだランキングを確定snapshotとして保存する画面にする。

年度末最終ランキングの場合:

- `ranking_year`: 対象年度
- `gender`: `M` / `F`
- `ranking_type`: `points`
- `ranking_scope`: `official_tournament`
- `is_final`: `true`
- `as_of_date`: 公式PDF本文に印字された日付
- `source_url`: 公式PDF URL
- `notes`: 公式PDFから取り込んだ年度末最終ポイントランキング、など

`as_of_date` はURLやファイル名ではなく、PDF本文の日付を優先する。

### 2025男子ランキング日付確認

DB上のsnapshot:

- snapshot id: `4`
- `ranking_year`: `2025`
- `gender`: `M`
- `ranking_type`: `points`
- `ranking_scope`: `official_tournament`
- `is_final`: `true`
- `as_of_date`: `2025-12-23`
- `source_url`: `https://www.jpba.or.jp/information/tournament/ranking/2025/M/M_PointRanking_251220.pdf`
- row count: `360`

公式PDF本文:

- `２０２５男子プロボウリング 最終ポイントランキング`
- `（HANDA CUP 第５９回全日本プロボウリング選手権大会 終了）2025.12.23`

したがって、男子ranking snapshotの `as_of_date` は公式PDF本文の日付と一致している。PDFファイル名の `251220` ではなく、PDF本文の `2025.12.23` を正とする。

## 年度末確定処理

年度末の通常処理は以下の順に行う。

1. 最終大会の公式成績を確定する。
2. 賞金・ポイントを `tournament_results` へ反映する。
3. 年度別ランキングを確認する。
4. JPBA公式PDFが出たら `/rankings` で公式ランキングsnapshotを保存する。
5. `row_count`、未照合行、`as_of_date`、`source_url` を確認する。
6. `/pro-bowler-seed-lists` で翌年度の年度別シード一覧を生成する。
7. 大会別の優先出場者一覧・PDFで、年度別シードが反映されることを確認する。

JPBA公式PDFが正本として出た後は、DB内の集計結果より公式PDF由来snapshotを優先する。

## 翌年度シード生成

翌年度シードは、年度末確定ランキングsnapshotから `pro_bowler_seed_lists` / `pro_bowler_seed_list_players` を生成する。

通常大会の基本:

- 男子: 前年度男子ポイントランキング上位24名を年度別トーナメントシードにする。
- 女子: 前年度女子ポイントランキングから第1シード・第2シードを扱えるようにする。現行運用では第1シード18名 + 第2シード18名を基本候補とする。
- 永久シード、準永久シード、歴代優勝者シード、大会別追加シードはランキング由来シードとは別根拠として扱う。

年度別シード一覧に入れた選手は、同年度・同性別の大会で `S 0524` のようなシード表示に使う。

## 全日本選手権用の年度途中ランキング

全日本選手権は、前年度最終ランキングではなく、開催時点の当該年度ランキングを優先出場根拠にする場合がある。

この場合も `pro_bowler_ranking_snapshots` / `pro_bowler_ranking_rows` を使う。ただし年度末最終ランキングとは別snapshotとして保存する。

推奨値:

- `ranking_year`: 当該年度
- `ranking_type`: `points`
- `ranking_scope`: `all_japan_entry_priority`
- `is_final`: `false`
- `as_of_date`: 全日本選手権の出場資格判定に使う公式PDFまたは公式発表日
- `source_url`: 判定に使った公式PDFまたは公式ページ
- `notes`: 全日本選手権優先出場順位判定用、など

このsnapshotから大会別の `tournament_seed_players` へ反映するか、専用の大会別シードリストを作る。年度末確定ランキングsnapshotを上書きしない。

## 大会エントリーへの優先出場順位反映

優先出場順位は、エントリー登録時に `tournament_entries` へ直接コピーしない。

大会ごとの優先出場順位は、次を合成して表示・PDF出力する。

1. 大会年度・性別に一致する有効な年度別シード一覧
2. 大会別に追加された `tournament_seed_players`
3. 大会別 `priority_order` による手動上書き

現在の `TournamentSeedPlayerController::buildTournamentPriorityPlayers()` は、この方針に沿って年度別シードと大会別追加シードを合成している。

今後、エントリー管理へ接続するときは次の使い方にする。

- 申込一覧: 優先出場順位、シード根拠、年度別TS該当、大会別追加シードを表示する。
- waitlist: 優先出場順位順に繰り上げ候補を並べる。
- チェックイン: 優先順位は参照情報として出すが、チェックイン状態は `tournament_entries.status` 側で管理する。
- 抽選: シード・優先順位をレーン抽選やシフト抽選の除外/固定条件として使う場合は、大会設定に明示する。

## 画面の役割

| 画面 | 役割 |
|---|---|
| `/rankings` | 公式ランキング・年度末確定ランキングの保存 |
| `/pro-bowler-seed-lists` | 年度別シード一覧の生成・確認 |
| `/tournaments/{tournament}/seed-players` | 大会別追加シード、優先出場順位、優先出場PDF |
| `/tournament_entries/...` | 参加申込、チェックイン、抽選、欠場、繰り上げ |

## 未完了として残すもの

チェックイン、当日運用、抽選結果公開、取消理由、一括繰り上げ履歴は、別作業として残す。ここは `tournament_entries` の状態遷移と運用ログの接続が必要であり、ランキング・シード正本の整理とは分ける。
