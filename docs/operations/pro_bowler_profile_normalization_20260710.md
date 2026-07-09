# Pro Bowler Profile Normalization / Search Scope - 2026-07-10

## Purpose

`Pro.csv` 再投入後のプロフィール揺れを、実運用前に安全に揃えるための整理。

- `九州南` 表記を公式サイトに近い `九州・南／沖縄` へ更新
- 講習/資格年度の `25`、`25年`、和暦表記などを4桁西暦へ統一
- 郵便番号欄に住所が入っている、住所欄に郵便番号が混ざっているなど、明確に判定できる住所系フィールドを補正
- 通常検索の既定値を「現役選手」にし、海外プロ/退会者は専用区分を選んだ場合だけ表示
- 一般公開画面には、引き続き住所・TEL・メールなどの非公開情報を出さない

## Code Changes

- `App\Services\ProBowlerProfileNormalizer`
  - 年度表記を4桁西暦へ正規化
  - 郵便番号を `NNN-NNNN` 形式へ正規化
  - 郵便番号欄に入った住所や、住所欄に混ざった郵便番号を安全に分離
- `App\Console\Commands\NormalizeProBowlerProfilesCommand`
  - `jpba:normalize-pro-bowler-profiles` を追加
  - 既定はドライラン、実更新は `--force`
  - JSONレポートは件数のみで、個人情報値は出さない
- `App\Services\ProBowlerSearchScopeService`
  - `active` / `overseas` / `retired` の検索区分を共通化
  - 公開画面、管理一覧、管理検索で同じ除外ルールを使用
- 公開選手検索、管理一覧、管理検索に「検索区分」プルダウンを追加

## Executed Normalization

実行コマンド:

```bash
php artisan jpba:normalize-pro-bowler-profiles --force --json
```

結果:

```json
{
    "mode": "executed",
    "district_label_updates": 1,
    "pro_bowler_rows_checked": 2286,
    "pro_bowler_rows_changed": 300,
    "field_changes": {
        "mailing_addr1": 114,
        "mailing_addr2": 79,
        "mailing_zip": 69,
        "organization_addr1": 8,
        "organization_addr2": 6,
        "organization_zip": 2,
        "public_addr1": 45,
        "public_addr2": 68,
        "public_zip": 1
    }
}
```

再ドライラン:

```json
{
    "mode": "dry-run",
    "district_label_updates": 0,
    "pro_bowler_rows_checked": 2286,
    "pro_bowler_rows_changed": 0,
    "field_changes": []
}
```

## Search Scope Check

DB側の検索区分件数:

```json
{
    "active": 882,
    "overseas": 357,
    "retired": 1047,
    "district_label_kyushu_south": "九州・南／沖縄"
}
```

混入チェック:

```json
{
    "active": {
        "count": 882,
        "retired_in_result": 0,
        "overseas_class_in_result": 0
    },
    "overseas": {
        "count": 357,
        "retired_in_result": 0,
        "overseas_class_in_result": 257
    },
    "retired": {
        "count": 1047,
        "retired_in_result": 1047,
        "overseas_class_in_result": 0
    }
}
```

補足:

- 現行DBでは `member_class=honorary_or_overseas` が「名誉/海外」系として分類されているため、検索区分 `海外プロ` で表示対象にしている。
- `その他` を海外プロ側に含めない運用へ変える場合は、CSV分類ルールと検索スコープを別途調整する。

## Verification

- `php -l`
  - `app/Services/ProBowlerProfileNormalizer.php`
  - `app/Services/ProBowlerSearchScopeService.php`
  - `app/Console/Commands/NormalizeProBowlerProfilesCommand.php`
  - `app/Http/Controllers/PublicPlayerController.php`
  - `app/Http/Controllers/ProBowlerController.php`
  - `app/Http/Controllers/ProBowlerImportController.php`
- `php artisan view:cache`
- `php artisan route:list --except-vendor`
- `php artisan public:parity-audit`
- 公開 `/players`
  - `現役選手検索結果` を確認
  - `海外プロ検索結果` を確認
  - `退会者検索結果` を確認
  - 旧ラベル `九州南` が出ず、`九州・南／沖縄` が出ることを確認
  - 一般公開HTMLに `メールアドレス`、`TEL`、`電話` が出ていないことを確認
- ブラウザ表示で `/players` を確認
  - 検索区分セレクトが表示され、既定値が `現役選手` であることを確認
  - `現役選手検索結果` と該当件数表示を確認
  - 旧ラベル `九州南`、メール/TEL系ラベルが出ていないことを確認
- 管理一覧/検索はController描画で「検索区分：現役選手/海外プロ/退会者」が出ることを確認
