# 会場マスター初回登録・更新手順（2026-07-22）

## 目的

大会作成時に会場を一覧から選ぶだけで、会場名、住所、電話、FAX、公式サイトを反映できるようにする。過去大会との参照を保ちながら、閉鎖会場を新規大会の候補から外す。

## 初回データの範囲

- 根拠: JPBA公式大会一覧と2022～2026年の個別大会ページ127件
- 登録: 国内現役58会場
- 除外: スポルト名古屋、星が丘ボウル、牧野松園ボウル
- 対象外: 海外会場、イベント用仮設レーン
- データファイル: `database/data/jpba_venues_2022_2026.json`

予定表にだけ載った仮会場や中止大会を混ぜないため、実際に公開された個別大会ページを根拠にする。

## 初回投入

最初に必ずドライランする。

```bash
php artisan jpba:import-recent-venues --json
```

`dataset_count`、`created_count`、`linked_tournament_count`、`warnings` を確認してから確定する。

```bash
php artisan jpba:import-recent-venues --force --json
```

同じコマンドをもう一度実行し、`created_count = 0`、`updated_count = 0`、`unchanged_count = 58` になることを確認する。

## 更新ルール

- 同一会場は `canonical_key`、正式名、`aliases` の順で照合する。
- 一括取込は空欄だけを補完し、管理画面で手修正された住所、電話、FAX、公式URL、メモを上書きしない。
- 確認元、確認日、初回／最終使用年、別名は取込データから更新できる。
- `venue_name` が一致する既存大会は、`venue_id` が空の場合だけ自動結線する。
- 閉鎖した会場は削除せず、会場編集画面で「大会会場の候補に表示する」をOFFにする。
- 停止会場は新しい大会の選択候補から外れるが、その会場を参照する過去大会の編集では保持される。

## 管理画面

- 会場一覧: `/venues`
- 大会作成: `/tournaments/create`
- 一覧は使用中、使用停止、すべてを切り替えられる。
- 別名は1行に1件入力する。

## 2026-07-22 実行結果

- 登録58件
- 使用中58件
- 住所欠損0件
- TEL欠損0件
- 正規化キー重複0件
- 閉鎖会場混入0件
- 大会ID61をサンスクエアボウルID18へ結線
- 冪等再実行は新規0件、更新0件、変更なし58件

## 確認元

- JPBA公式大会一覧: https://www.jpba.or.jp/information/tournament/tournament.html
- 2026年ラウンドワン予選: https://www.jpba.or.jp/information/tournament/tournament2026/R1GCB_2026/JPBA_Qualify_2026.html
- 2026年アントールカップ: https://www.jpba.or.jp/information/tournament/tournament2026/09_ANTOL/AntolCup_2026.html
