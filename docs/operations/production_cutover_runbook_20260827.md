# JPBA新サイト 本番公開・旧サイト切替手順

作成日：2026-08-27

## 1. この手順の範囲

新サイトを本番公開する直前から、公開確認、監視、切戻しまでを扱う。ローカル環境の `.env` は本番値へ変更せず、`.env.production.example` を本番サーバー上で複製して秘密情報を設定する。

公開作業を始める前に、次を確定する。

- 本番ドメインとHTTPS証明書
- PostgreSQL本番DB、DB利用者、接続制限
- SMTP接続情報、送信元アドレス、試験受信者
- queue workerとLaravel schedulerの常駐方法
- バックアップ暗号鍵の保管先と復旧担当者
- 管理者、スタッフ、協力選手のスモークテスト担当
- 公開日時、旧サイトの停止方法、問い合わせ窓口

## 2. 本番設定

本番サーバーで `.env.production.example` を `.env` へ複製し、空欄と例示値を実値に置き換える。`.env`、バックアップ鍵、SMTP・DB認証情報はGitへ追加しない。

必須条件は `php artisan jpba:release-readiness --production` が検査する。

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://...`
- `APP_TIMEZONE=Asia/Tokyo`、`APP_LOCALE=ja`
- `SESSION_SECURE_COOKIE=true`
- PostgreSQL、永続cache・session、非同期queue
- `MAIL_MAILER` は `log` / `array` 以外
- 公開日に `JPBA_ACHIEVEMENT_CUTOVER_DATE=YYYY-MM-DD`
- `npm run build` 済みのVite manifest

`APP_KEY` は新規環境で一度だけ生成する。既存暗号化データがある環境では変更しない。

## 3. 配置と事前検査

本番・ローカル環境で全自動テストを実行する場合は、先に `docs/operations/automated_testing_guide_20260827.md` を参照し、必ず `composer test` を使う。`php artisan optimize` または `php artisan config:cache` の後に単独の `php artisan test` を実行してはならない。テスト基底クラスは `testing` 環境かつ `_test` 終端のDB以外を安全停止する。

```powershell
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer audit --locked --no-interaction
npm ci
npm audit --omit=dev
npm run build
php artisan storage:link
php artisan migrate --force
php artisan optimize
php artisan jpba:release-readiness --production
php artisan public:parity-audit
php artisan route:list --except-vendor
php artisan schedule:list
```

すべてOKになるまでDNS・リバースプロキシ・旧サイト停止を行わない。`release-readiness --production` は本番必須条件が1件でも不足すると終了コード1で停止する。

## 4. 常駐処理

- Webプロセスとは別に `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` をサービス管理下で常駐させる。
- OS schedulerから `php artisan schedule:run` を毎分実行する。
- デプロイ後は `php artisan queue:restart` を実行し、新コードへ切り替える。
- `schedule:list` でバックアップ、講習会期限通知、ボール期限処理等の登録時刻を確認する。

## 5. 公開直前の正本差分

公開当日に公式更新履歴と大会一覧を再確認し、リンク、資料、選手写真、完了大会、ランキング、公認記録、TP講習会受講者一覧の差分を取り込む。取込は既存の手順どおり、dry-run、件数照合、バックアップ、確定投入、再実行確認の順に行う。

切替直前に次を実行する。

```powershell
php artisan jpba:backup
php artisan jpba:backup-verify --restore-test
php artisan jpba:release-readiness --production
php artisan public:parity-audit
```

## 6. 権限別スモークテスト

公開URLで次を1件ずつ確認する。試験用データには識別できる名称を付け、確認後の削除可否を事前に決める。

1. 一般：トップ、年間予定、選手検索・プロフィール、大会結果・速報、登録ボール公開表示、規程ページ、PDF。
2. 管理者：管理ホーム、選手編集、選手アカウント状態、大会作成・編集、講習会、承認、バックアップ状況。
3. スタッフ：代理入力できる範囲と、管理者専用操作を拒否する範囲。
4. 協力選手：ログイン、本人プロフィール、マイボール、年度登録、大会使用ボール登録。別選手情報は更新できないこと。
5. メール：協力選手1名の初期設定メールと、承認済み宛先だけを使った講習会通知試験。

## 7. 切替と監視

- 旧サイトを読取専用またはメンテナンス表示にし、新規更新を止める。
- 最終差分0件を確認後、DNSまたはリバースプロキシを新サイトへ向ける。
- 公開直後、15分後、1時間後、翌営業日に、HTTP 5xx、Laravelログ、failed jobs、メール失敗、DB接続、ディスク残量、バックアップを確認する。
- 問い合わせ窓口へ、対象URL、発生時刻、選手・大会ID、操作、画面表示を記録してもらう。

## 8. 切戻し

重大障害時は新規操作を止め、書込みの有無を記録する。

1. `php artisan down --secret=<復旧確認用文字列>` で一般書込みを停止する。
2. DNS／リバースプロキシまたはリリース参照先を直前版へ戻す。
3. DB変更を伴わない障害はコードだけを直前版へ戻し、`php artisan optimize` と `queue:restart` を行う。
4. DB復元が必要な場合は、暗号化バックアップを必ず別DB・別フォルダへ復元して件数・ハッシュを照合する。現行DBへの上書きは復旧責任者の判断後に行う。
5. 権限別スモークテスト後、`php artisan up` で再開する。

バックアップの詳細は `docs/chat/backup_restore_guide_20260826.md` を正本とする。

## 9. 現時点で外部決定待ちの項目

- 本番ドメイン、サーバー、HTTPS証明書
- SMTP、queue worker、schedulerの実設備設定
- 協力選手と本人確認済みメール
- 実際のTP講習会開催回、受講者、送信対象
- 公開日時、`JPBA_ACHIEVEMENT_CUTOVER_DATE`、旧サイト停止担当

これらはコードだけでは確定できないため、公開作業日に入力・確認する。
