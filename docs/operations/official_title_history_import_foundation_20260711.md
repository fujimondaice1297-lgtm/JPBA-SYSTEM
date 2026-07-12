# 公式タイトル明細復元の安全運用メモ（2026-07-11）

## 目的

現行JPBAサイトに掲載されている過去大会・シーズントライアル優勝情報を、プロ個人のタイトル明細へ復元する。

最重要条件は以下。

- `pro_bowlers` の公式集計値と `pro_bowler_titles` の明細件数をずらさない
- 大会ページから拾ったタイトル年月日と、個人プロフィールに表示されるタイトル年月日をずらさない
- 途中まで拾えた情報を、未完成のまま公開プロフィールへ出さない

## 今回追加した仕組み

- `official_title_import_candidates` を追加し、公式サイトから拾ったタイトル候補を一度ここへ保存する
- `pro_bowler_titles` に `source_url` / `source_label` を追加し、どの公式ページから作成した明細か追跡できるようにした
- `jpba:import-official-title-history` を追加した
  - 既定はドライラン
  - `--force` で候補テーブルへ保存
  - `--promote` で、集計件数と候補明細件数が完全一致する選手だけ `pro_bowler_titles` へ昇格

## 昇格ルール

シーズントライアルは `season_trial_win_count`、通常タイトルは `official_win_count` を公式集計値として扱う。

候補明細数が公式集計値と完全一致し、かつ既存明細が未登録の場合のみ本登録する。

例外:

- 公式集計値が2件なのに候補が1件しかない選手は、候補保存のみで本登録しない
- 既に同カテゴリの明細がある選手は、自動上書きや自動追加を行わない
- 不一致は `promotion_blocked` として記録し、手動確認または追加ソース取得へ回す

## 2026サマーシリーズでの確認

対象URL:

`https://www.jpba.or.jp/information/tournament/tournament2026/ST_Summer/ST_Summer2026.html`

取得候補:

- 江川司 / M00001400 / A会場 / 2026-07-02
- 市原竜太 / M00000978 / B会場 / 2026-07-01
- 安里秀策 / M00001423 / C会場 / 2026-07-07

DB確認結果:

- 候補3件を `official_title_import_candidates` へ保存
- 江川司は `season_trial_win_count = 1`、候補1件のため本登録
- 市原竜太は `season_trial_win_count = 1`、候補1件のため本登録
- 安里秀策は `season_trial_win_count = 2`、候補1件のみのため本登録せず候補止まり
- `pro_bowler_titles` の `source_url` 付き本登録は2件
- 公開プロフィール内部レンダリングで、市原竜太・江川司にはタイトル名が表示され、候補止まりの安里秀策には表示されないことを確認
- 同じURLを `--force --promote` で再実行しても、新規昇格0件で重複登録されないことを確認

検証:

- `php -l` 対象5ファイル OK
- `php artisan view:cache` OK
- `php artisan route:list --except-vendor` OK
- `php artisan public:parity-audit` OK
- `/players/13334` / `/players/13756` / `/players/13779` の内部レンダリングでタイトル表示有無を確認

確認コマンド:

```bash
php artisan jpba:import-official-title-history --url=https://www.jpba.or.jp/information/tournament/tournament2026/ST_Summer/ST_Summer2026.html --sleep-ms=0 --json
php artisan jpba:import-official-title-history --url=https://www.jpba.or.jp/information/tournament/tournament2026/ST_Summer/ST_Summer2026.html --force --promote --sleep-ms=0 --json
```

## 次の候補

- 安里秀策のもう1件のシーズントライアル優勝元を公式サイトから特定する
- JPBA公式大会ページの年度別一覧をたどり、同じ候補方式でタイトル明細を増やす
- PDF最終成績からしか優勝者が取れない大会は、PDF抽出を別段階で追加する
- 個人プロフィールの年度別出場大会で1位行が取れる場合は、公式大会ページと突合して候補化する
- `docs/db/columns_public.csv` は現DBからの再生成が別途必要なため、DB資料更新は次のDB辞書更新フェーズで行う

## 2026-07-12 公式ST過去ページ自動発見

ユーザー指摘により、2026サマーシリーズだけではなく、JPBA公式大会一覧から遡れるシーズントライアルをまとめて確認した。

対象インデックス:

`https://www.jpba.or.jp/information/tournament/tournament.html`

追加した処理:

- `jpba:import-official-title-history` に `--discover-season-trials=` を追加し、公式大会一覧からシーズントライアルページURLを自動収集するようにした
- 旧ページ形式の `【B会場優勝 水野成祐】 24期 No.544` のような表記からも氏名・会場を拾えるようにした
- `10/26(木)` のような曜日付き日付、日付行と会場行が分かれているページ、日付行に会場が同居するページを扱えるようにした
- `No.9315` のように公式ページ側の表記がDBライセンス番号とずれる場合でも、氏名完全一致で選手を解決するフォールバックを追加した
- 会場名先頭の `【` / `[` を保存前に除去し、既存候補も正規化会場名で更新できるようにした
- 既に `pro_bowler_titles` へ昇格済みの候補は、再実行時に `promoted` 状態へ同期し、重複登録しないようにした
- 公開プロフィールのタイトル欄へ、シーズントライアル優勝履歴の明細リストを追加した。ただし候補件数と公式集計が一致しない選手は件数表示のみで、未確認明細は表示しない

実行コマンド:

```bash
php artisan jpba:import-official-title-history --discover-season-trials=https://www.jpba.or.jp/information/tournament/tournament.html --force --promote --sleep-ms=100 --json
```

実行結果:

- 公式大会一覧からシーズントライアルページ38件を発見
- 取得候補132件
- DB選手照合132件、未照合0件
- エラー0件
- `official_title_import_candidates` のシーズントライアル候補132件
- `pro_bowler_titles` の `source_url` 付き本登録24件
- `source_url` 付き本登録の `won_date` NULL 0件
- 候補の重複グループ0件
- 会場名先頭に `【` / `[` が残る候補0件
- 公式集計と候補数が合わない26名は `promotion_blocked` として本登録を止めた

安里秀策の扱い:

- 現行JPBAプロフィールのシーズントライアル優勝回数は2
- 2024年度プロフィール上では、2024スプリングC会場と2024サマーC会場の1位を確認できる
- 2026サマーC会場ページでも優勝候補が取れるため、公式大会ページから確認できる候補は3件になる
- 現行プロフィール集計2件と候補3件が衝突するため、`pro_bowler_titles` へは昇格せず候補止まりにした
- 公開プロフィールでは `シーズントライアル優勝：2` を表示し、2026サマー明細は表示しないことを内部レンダリングで確認した

完全一致で本登録できた代表例:

- 森本健太: `season_trial_win_count = 5`、本登録明細5件
- 西川徹: `season_trial_win_count = 2`、本登録明細2件
- 渡邊雄也: `season_trial_win_count = 2`、本登録明細2件
- 笹田泰裕: `season_trial_win_count = 2`、本登録明細2件
- 江川司: `season_trial_win_count = 1`、本登録明細1件
- 市原竜太: `season_trial_win_count = 1`、本登録明細1件

検証:

- `php -l app/Services/JpbaOfficialTitleCandidateService.php` OK
- `php -l app/Console/Commands/ImportOfficialTitleHistoryCommand.php` OK
- `php artisan view:cache` OK
- `php artisan route:list --except-vendor` OK
- `php artisan public:parity-audit` OK
- 公開プロフィール内部レンダリングで、森本健太はST履歴明細と `シーズントライアル優勝：5`、安里秀策は `シーズントライアル優勝：2` と未確認明細非表示を確認

## 2026-07-13 ST優勝明細の全件本登録

安里秀策の2026サマー優勝が現行プロフィールの集計へ未反映であり、ST優勝は3勝であることがユーザー確認で確定した。これにより、「集計回数と候補明細数の完全一致」だけを本登録条件とする方式は、直近優勝の反映遅れと過去ページの取得範囲差を扱えないため更新した。

更新後の規則:

- JPBA公式大会ページで優勝者、年度、シリーズを確認でき、DB選手に照合できた明細は `pro_bowler_titles` へ本登録する
- 集計回数は、既存集計値と本登録明細数の大きい方を保持する
- 未発見の過去実績がある選手の集計値は減らさない
- 公式プロフィールの更新遅れにより本登録明細数が上回った場合は、集計回数を自動で増やす
- 同一選手、大会、年度、開催日、出典URLが同じ候補は、会場表記が違っても1件に統合する
- 同一日付の下に複数会場が並ぶ旧ページは、次の日付行までの全会場に同じ開催日を割り当てる

実行結果:

- 対象公式STページ: 38件（2016-2026）
- 重複除外後の優勝明細: 131件
- DB選手照合: 131件、未照合0件
- `official_title_import_candidates`: promoted 131件
- `pro_bowler_titles`: ST明細131件
- 重複明細: 0件
- `won_date` NULL: 0件（2018スプリングC会場は2018-05-21、D会場は2018-05-22として補完）
- 明細数より集計回数が少ない選手: 0件
- 安里秀策: ST集計3回、ST明細3件（2024スプリング、2024サマー、2026サマー）

表示確認:

- `http://127.0.0.1:8000/players/13779` を実ブラウザで表示
- `シーズントライアル優勝：3` を確認
- 2026サマー、2024サマー、2024スプリングの3明細を確認
- タイトル欄の配置崩れがないことをスクリーンショットで目視確認

今後の大会についても、成績の1位をタイトル反映した際に、通常の公式戦は公式タイトル欄、シーズントライアルはST優勝履歴欄へ登録し、集計回数も同時に更新する。

追加検証:

- 同じ2026サマー公式ページを再取込し、新規昇格0件、状態再同期0件、集計更新0件を確認
- トランザクション内で通常公式戦とST明細を順に仮登録し、公式集計とST集計が別々に増えることを確認後ロールバック
- `php -l` 対象3ファイル OK
- `php artisan view:cache` OK
- `php artisan route:list --except-vendor` OK（319ルート）
- `php artisan public:parity-audit` OK（公開12ページすべて200/OK）
- `tournament:result-flow-regression` は既存RR/ステップラダー/シュートアウト大会がDB初期化後で存在しないためその3件は対象なし、トランザクション内のシングルエリミネーションfixtureはOK、fixture残存0件
