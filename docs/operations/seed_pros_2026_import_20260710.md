# 2026年度シードプロ再投入ログ

作成日: 2026-07-10

## 目的

実運用フォワードテスト用データリセット後の空DBに、2026年度シードプロを再投入する。

ユーザー訂正により、対象は「2025年度シード」ではなく「2026年度シードプロ」とする。
女子は公式シードページに掲載されている第1シード18名に加え、2025年度女子最終ポイントランキングの19-36位を第2シードとして写し取る。

## 参照元

- 公式シードページ: `https://www.jpba.or.jp/information/tournament/seed.html`
- 2025男子最終ポイントランキングPDF: `https://www.jpba.or.jp/information/tournament/ranking/2025/M/M_PointRanking_251220.pdf`
- 2025女子最終ポイントランキングPDF: `https://www.jpba.or.jp/information/tournament/ranking/2025/W/W_PointRanking_251213.pdf`

PDF本文の日付を `as_of_date` として採用した。

- 男子: `2025-12-23`
- 女子: `2025-12-13`

## 実装

`app/Console/Commands/ImportOfficial2026SeedProsCommand.php` を追加した。

コマンド:

```bash
php artisan jpba:import-official-2026-seed-pros
php artisan jpba:import-official-2026-seed-pros --force
```

既定はドライランのみで、`--force` を付けた場合だけDBへ書き込む。
公式ソースから写し取った固定データをもとに、ライセンスNoで `pro_bowlers` と照合し、欠番または氏名不一致がある場合は書き込み前に止める。

## DB投入結果

- `pro_bowler_ranking_snapshots`
  - 男子 snapshot id: `5`、2025年度男子最終ポイントランキング、24行
  - 女子 snapshot id: `6`、2025年度女子最終ポイントランキング、36行
- `pro_bowler_seed_lists`
  - 男子 seed_list id: `6`、2026年度、上位24名
  - 女子 seed_list id: `7`、2026年度、上位36名
- `pro_bowler_seed_list_players`
  - 男子: 24名
  - 女子第1シード: 18名
  - 女子第2シード: 18名

シード種別はランキング由来の `TS` とした。
CS優勝者、永久シード、準永久シードはランキング由来シードとは別根拠なので、今回の投入には混ぜていない。

## 確認

- `php -l app/Console/Commands/ImportOfficial2026SeedProsCommand.php`: OK
- `php artisan jpba:import-official-2026-seed-pros --json`: 欠番0、氏名警告0
- `php artisan jpba:import-official-2026-seed-pros --force --json`: OK
- DB確認:
  - ranking rows: 男子24、女子36
  - seed rows: 男子24、女子第1シード18、女子第2シード18
- `php artisan view:cache`: OK
- `php artisan route:list --except-vendor`: OK、319 routes
- `php artisan public:parity-audit`: OK、公開12ページすべて200/OK
- ブラウザ確認:
  - `http://127.0.0.1:8000/tournament_pro?year=2026&gender=M`
    - 上位24名、24名表示
    - 1位 安里秀策、24位 佐藤貴啓を確認
  - `http://127.0.0.1:8000/tournament_pro?year=2026&gender=F`
    - 第1シード18名、第2シード18名表示
    - 1位 石田万音、19位 坂倉にいな、36位 内藤真裕実を確認

## 注意

`public:parity-audit` は通常サンドボックス内では `storage/logs` と `storage/framework/views` の書き込み権限で失敗したため、通常権限で再実行して確認した。
