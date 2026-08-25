# 2026年7月末公式大会・ランキング確定取込（2026-08-26）

## 結論

- 大岡産業レディース［THE OPEN］トーナメント2026、シーズントライアル2026サマーD会場、男女7月末ポイントランキングを同じ正本単位で確定投入した。
- 公式PDFから再構築できる決定的なデータ生成ツールを追加し、書込み前の資料内集計値検証と再実行差分検査を必須にした。
- 2大会の成績完全性、公式ランキング全540名との累計、再ドライランはいずれも差分0件。
- 両大会の公認パーフェクト、800シリーズ、7－10メイド追加候補は0件。

## 公式入力資料

| 対象 | ローカル原本 | SHA-256 |
|---|---|---|
| サマーD最終成績 | `tmp/p0_2_sources_20260822/D_FinalResult.pdf` | `5CE2525FCA480178FAC3A7606780457F4DBAB7AFD4561D594CDD9DB6639B04BC` |
| 大岡・予選12G | `tmp/pdfs/p0_2_20260826/ooka_prelim_12g.pdf` | `B889FCE554C1F69F65B68BF74C21D478C5AB37F631DB6468D4BA944D5092365F` |
| 大岡・準決勝4G | `tmp/pdfs/p0_2_20260826/ooka_semifinal_4g.pdf` | `CB2E7319532ADFD11CD71FB3EB03FC8C8F518596503C6883E354E718F25735F1` |
| 大岡・ラウンドロビン8G | `tmp/pdfs/p0_2_20260826/ooka_rr_8g.pdf` | `AFC52E9611E4A96AB0A097B6CF9886EA251B59220E14518E1D4C8AEE11C6A2F1` |
| 大岡・決勝 | `tmp/pdfs/p0_2_20260826/ooka_final.pdf` | `59A2915E3066C45A5F7779DCCE7F7B37F6B78646DFF3EBD45F0035F3DD8BE7B2` |
| 男子ポイントランキング | `tmp/pdfs/p0_2_20260826/male_ranking_0728.pdf` | `D6202028332AD6799162D12C1AD29570F23C33E340BFF0FBE48817FE5D0A92D8` |
| 女子ポイントランキング | `tmp/pdfs/p0_2_20260826/female_ranking_0727.pdf` | `2941E0F775E3380E0E6FA6D091884A7808BEB2187FE31B86D2920793204D733F` |

`tmp/` の原本・生成物はGit対象外とし、正規化したJSON、生成ツール、監査値をGit正本として残す。

## 正規化データ

- `tools/build_official_2026_late_july_dataset.py` は7PDFを読み、資料内の順位・ゲーム数・ピン・ポイント・賞金・ラウンドロビン組合せを検証する。
- `--write` を付けない限りJSONを書き換えない。
- 集約データは25大会、77スナップショット。男子ランキング328名、女子ランキング212名。
- シーズントライアル詳細は全12大会。サマーDは予選290、準決勝80、シュートアウト10。
- 通常公式戦詳細は全13大会、合計15,978ゲーム。大岡の詳細は予選1,191、準決勝128、ラウンドロビン64の計1,383ゲームで、決勝取込のステップラダー4ゲームを加えたDB登録総数は1,387件。
- 通常公式戦決勝は全12大会、成績表31枚、フレーム対象67名、670フレーム。

## DB確定結果

### サマーD・大会ID119

- 参加者39名、ゲームスコア380件、成績表3枚、決勝フレーム100件。
- 優勝は太田隆昌プロ（M00000905）。14G、2,930ピン、70ポイント、賞金78,700円。
- シーズントライアル優勝履歴へ接続済み。
- 成績完全性は `score_gap=0`、公式集計差分0件。

### 大岡産業レディース・大会ID189

- 参加者100名、ゲームスコア1,387件、成績表2枚、決勝フレーム40件。
- 優勝は小林あゆみプロ（F00000478）。25G、5,231ピン、800ポイント、賞金1,200,000円。
- 公式タイトル履歴へ接続済み。
- 公式ラウンドロビン7ゲームの対戦組合せ、勝敗、ボーナス、合計ポイントを大会固有設定として保存した。
- 成績完全性は `score_gap=0`、公式集計差分0件。

### ランキング

- 男子は2026-07-28時点328名、女子は2026-07-27時点212名を最新正本へ更新した。
- 公開対象26単位を再集計し、公式ライセンス540名、集計ライセンス540名、差分0件。

## 実装上の保護

- 詳細成績とステップラダーが別資料の大会は、詳細取込時に不完全な最終成績を公開せず、決勝取込後に一度だけ公開する。
- 確定済み大会の参加者、スコア、成績表、決勝フレーム、タイトル、公認記録を集約再取込から保護する。
- 全ゲームブラインドの公式成績行は、0G・0ピンかつゲームスコアなしの場合に限り完全性判定で許可する。
- 大会固有のラウンドロビン組合せがある場合は、それを優先し、各ラウンドで全選手が重複なく1回ずつ出場することを検証する。
- 3日以上の大会PDFは2行目の日付見出しを「最終日」とし、「2日目」と誤表示しない。

## PDF確認

- `tmp/p0_2_generated_20260826/tournament_119.pdf`：5ページ。
- `tmp/p0_2_generated_20260826/tournament_189.pdf`：9ページ。
- 全14ページを画像化し、空白ページ、文字化け、重なり、切れ、勝者欠落がないことを目視確認した。
- 大岡のラウンドロビン順位・勝敗・ボーナス・TOTAL POINTが公式PDFと一致した。

## 再検証コマンド

```powershell
php artisan jpba:import-official-2026-results --json
php artisan jpba:repair-official-2026-season-trials --event=stsu_d --json
php artisan jpba:repair-official-2026-standard-tournaments --event=ooka_2026 --json
php artisan jpba:repair-official-2026-standard-finals --event=ooka_2026 --json
php artisan jpba:audit-official-2026-rankings --json
php artisan tournament:pdf-regression --tournament-id=119 --tournament-id=189 --output-dir=tmp/p0_2_generated_20260826
php artisan test tests/Unit/Official2026LateJulyDatasetTest.php tests/Unit/Official2026StandardDetailDatasetTest.php tests/Unit/Official2026StandardFinalDatasetTest.php tests/Unit/RoundRobinConfiguredRoundsTest.php tests/Unit/TournamentResultCompletenessServiceTest.php
```

期待結果は、全取込dry-runのDB差分0、ランキング監査差分0、大会ID119・189のPDF生成成功、関連テスト全件成功。書込みを伴う再投入は、必ず既存暗号化バックアップを確認してから `--force` 等の確定オプションを付ける。
