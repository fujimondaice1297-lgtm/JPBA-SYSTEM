# 2026-07-09 Pro.csv 正会員/プロボウラー再投入記録

## 目的

実運用フォワードテスト用リセット後の空DBへ、旧DBから出力した `Pro.csv` を正本として、正会員/プロボウラー情報を再投入する。

## 入力ファイル

- `C:\Users\user\OneDrive\デスクトップ\Pro.csv`
- 文字コード: CP932
- 物理行数: 2588行
- CSVとして読み取ったデータ行: 2286件

## 実施内容

- 既存の管理画面CSV取込ロジックを使って `pro_bowlers` へ投入した。
- `Pro.csv` の地区表記 `九州･南／沖縄` がDBマスタの `九州南` に対応するよう、CSV取込時の地区正規化を補正した。
- `fgetcsv()` のPHP非推奨警告を避けるため、明示的にescape引数を指定した。
- 公開プロフィールControllerは `is_visible=true` の選手のみ表示し、公開Viewへ住所系フィールドを渡さないようにした。
- 公開プロフィールViewは「公開住所 / 所属先」ではなく「所属先」に統一し、所属先名とURLのみを一般公開する形にした。
- ログインはメールアドレス、ライセンスNo、または `pro_bowlers.login_id` に紐づく既存Userから解決し、必ず `users.password` のハッシュ検証を通す形にした。
- 会員ページのプロフィール編集リンクは、一般会員では本人用 `/athlete` へ向くようにした。

## 取込結果

- `pro_bowlers`: 2286件
- 通常公開対象: 1239件
- 退会者検索対象: 1047件
- `instructors`: 1343件
- `instructor_registry`: 1343件
- `users`: 1件（新管理者のみ。選手Userは自動作成していない）
- 地区未設定: 0件

## 公開/本人/管理者の境界

- 一般公開 `/players` / `/players/{id}`:
  - 公開検索と公開プロフィールのみ。
  - メール、電話、送付先住所、自宅住所は表示しない。
  - 生年月日、身長、体重、血液型は公開フラグが立たない限り表示しない。
- 本人マイページ `/member` / `/athlete`:
  - `auth + role:member,editor,admin` 必須。
  - 本人編集保存は、管理者でない場合 `users.pro_bowler_id` と対象選手IDの一致が必須。
- 管理側 `/pro_bowlers/*`:
  - `auth + role:editor,admin` 必須。

## 検証

- `php -l app/Http/Controllers/ProBowlerImportController.php`: OK
- `php -l app/Http/Controllers/AuthController.php`: OK
- `php -l app/Http/Controllers/PublicProfileController.php`: OK
- `php artisan view:cache`: OK
- `php artisan public:parity-audit`: 公開12ページすべて200/OK
- `/players` 実HTTP:
  - 200
  - 該当件数表示あり
  - メール/TEL/電話ラベルなし
- `/players/{id}` ブラウザ確認:
  - プロフィール表示OK
  - 所属先セクション表示OK
  - メール/TEL/送付先/自宅住所系ラベルなし
  - 公開住所/郵便番号ラベルなし
- 未ログイン `/member`:
  - `/login` へ302
- 未ログイン `/athlete`:
  - `/login` へ302

## 次に残る作業

- 2026年7月現在のインストラクター正本データを再投入する。
- 2025年度シードプロ設定を再作成する。
- 2026年1月以降の大会、参加者、成績、ポイント/賞金/タイトルを再投入する。
