# PostgreSQL自動テスト運用ガイド（2026-08-27）

## 方針

- 本番と同じPostgreSQLをテスト正本とし、SQLite固有差異や拡張不足によるskipをなくす。
- テストDB名は `jpba_test` に固定し、`phpunit.xml` の `force="true"` で `jpba_main` への誤接続を防ぐ。
- `jpba:test-database-prepare` は `_test` で終わるDBだけを新規作成できる。既存DBの削除、初期化、名称変更は行わない。
- テーブルの再作成はLaravelの `RefreshDatabase` が `jpba_test` 内だけで行う。

## ローカル実行

必要条件はPHP 8.2以上、`pdo_pgsql`、起動済みPostgreSQL、`.env` の接続情報。初回を含め、通常は次の1コマンドでよい。

```powershell
composer test
```

ComposerがPATHにない環境では、同じ処理を次の順で実行する。

```powershell
php artisan config:clear
php artisan jpba:test-database-prepare --database=jpba_test
php artisan test
```

`jpba:test-database-prepare` は `jpba_test` が存在すれば何も変更せず成功する。新規作成権限がない場合は、DB管理者が同名の空DBを一度だけ作成する。

## PDF回帰

全テスト後、同じテストDB接続を環境変数へ設定してfixture PDFを確認する。現行の `jpba_main` を使って実行しない。

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='pgsql'
$env:DB_DATABASE='jpba_test'
php artisan tournament:pdf-regression
Remove-Item Env:APP_ENV,Env:DB_CONNECTION,Env:DB_DATABASE
```

空のテストDBでは、既存大会を必要とするケースはSKIPとなり、標準、シュートアウト、シングルエリミネーションの一時fixtureがOKになれば成功。

## CI

`.github/workflows/tests.yml` はpushとpull requestで次を実行する。

1. PostgreSQL 18サービスを起動する。
2. Composer依存をインストールする。
3. `jpba_test` を安全に準備する。
4. 全migration、Unit、Featureを実行する。
5. Blade全体をコンパイルする。
6. fixture PDF回帰を実行する。

CIのDB接続情報は一時PostgreSQLサービス専用で、本番認証情報を使わない。

## NG判定

- DB名が `_test` で終わらず準備コマンドが安全停止する。
- fresh migrationが途中で停止する。
- skipped、failed、errorのテストが1件でも残る。
- Bladeキャッシュまたはfixture PDF回帰がFAILになる。
