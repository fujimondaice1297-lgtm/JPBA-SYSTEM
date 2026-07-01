# 現行JPBA公開サイト踏襲チェックリスト

確認日: 2026-07-01
確認元: https://www.jpba1.jp/

## 目的

現行公開サイトの見た目と導線を大きく変えず、裏側だけをDB正本・管理画面入力・自動反映へ置き換えるための棚卸しです。
公開側は「現行サイトの利用者が迷わないこと」を優先し、管理側は重複手入力を減らす方針で進めます。

## トップページで踏襲する構成

- 上部ロゴ: 公益社団法人 日本プロボウリング協会 JPBA
- 上部メニュー: JPBAについて / スケジュール / 選手データ / トーナメント / インストラクター / プロテスト / トピックス
- 補助導線: 更新履歴 / プロボウラー専用ページ
- フッター導線: お問い合わせ / 取材のお申込み / 特定商取引法に基づく表記 / プライバシーポリシー
- 大会バナー枠: 直近または注目大会の画像、開催日、詳細リンク
- PDFリンク枠: JPBAトーナメント予定表、観戦案内、重要なお知らせPDF
- 動画・外部サービス枠: JPBA LIVE、io.LEAGUE関連リンク
- INFORMATION枠: 日付、カテゴリ、タイトル、詳細リンク
- 協賛・関連団体バナー枠: 画像、外部URL、表示順
- SNS枠: Facebook / X / Instagram

## INFORMATIONカテゴリ

現行サイトのトップINFORMATIONに合わせ、以下を正本カテゴリとして扱います。

- NEWS
- 大会
- TV情報
- ｲﾝｽﾄﾗｸﾀｰ
- イベント

実装メモ:

- カテゴリ候補は `App\Models\Information::categories()` を正本にします。
- 管理画面の登録/更新バリデーションは `Information::categoryValidationRule()` を使います。
- DBの `informations_category_check` も同じカテゴリを許可します。
- 一覧のカテゴリ絞り込みは、公開INFORMATION、会員用INFORMATION、管理画面で同じ候補を使います。

## 今後DB化するトップ要素

- `informations`: INFORMATION本文、カテゴリ、公開対象、公開期間、添付ファイル
- `tournaments`: 大会名、開催日、性別、会場、状態
- `tournament_photos` または後続の公開バナー管理: 大会バナー画像、表示順、リンク先
- `calendar_events`: 年間/月間スケジュール、PDF出力
- 後続候補の公開リンク管理: PDF、外部リンク、協賛バナー、SNSリンク

## 実装メモ

- `/` は `PublicHomeController@index` で表示します。
- `/about` は `PublicPageController@about` で表示し、協会概要と現行サイトの主要PDF導線を `config/jpba_public.php` から読みます。
- `/schedule` は `PublicPageController@schedule` で表示し、`tournaments` / `calendar_events` を年別・月別に並べます。
- `/players` は `PublicPlayerController@index` で表示し、`pro_bowlers` を氏名、ライセンスNo範囲、性別、地区、退会者で検索します。
- `/players/{id}` は `PublicProfileController@show` で表示し、公開検索から個別プロフィールへ遷移します。
- `/tournament` は `PublicTournamentController@index` で表示し、`tournaments` を大会区分、年、月、地区で検索します。
- `/tournament/{tournament}` は `PublicTournamentController@show` で表示し、公開PDF、速報/成績リンク、結果カード、上位成績を表示します。
- `/instructor` は `PublicInstructorController@index` で表示し、制度導線と `instructor_registry` 正本のライセンス別一覧を表示します。
- `/protest` は `PublicPageController@protest` で表示し、受験の流れ、実施概要リンク、受験者講習会導線、`pro_test_schedule` / `calendar_events.kind=pro_test` / 関連INFORMATIONを表示します。
- `/topics` は `PublicPageController@topics` で表示し、`informations` と公開 `information_files` を正本に、記事本文、画像、添付、カテゴリ、現行サイトの社会貢献活動・プロボウラー紹介・大会ページ導線を表示します。
- `/contact`、`/media`、`/commerce`、`/privacy` は `PublicPageController@staticPage` で表示し、現行フッター導線をLaravel側に寄せます。
- トップ大会枠は `tournaments` と公開 `tournament_files` を読みます。
- INFORMATION枠は `Information::active()->public()` を読みます。
- 現行サイト由来の外部ナビ、PDF、フッター導線は `config/jpba_public.php` に集約しています。
- 旧URLは `.html` / `index.html` 互換を原則301でローカル公開ページへ寄せ、未移行のPDF・外部フォーム・旧大会詳細は外部リンクとして残します。

## 実装順の候補

1. INFORMATIONカテゴリとDB制約を現行サイトに合わせる。
2. 公開トップのLaravel初期画面をJPBAトップ構成へ差し替える。
3. JPBAについて・スケジュールの公開ページをLaravel側に用意する。
4. 選手データ公開検索をLaravel側に用意する。
5. トーナメント公開ページをLaravel側に用意する。
6. インストラクター公開ページをLaravel側に用意する。
7. プロテスト、トピックス、フッター固定ページをLaravel側に用意する。
8. 旧URL互換と `jpba1.jp` / `jpba.or.jp` の分担を決める。
9. PDFリンク・協賛バナー・外部リンク・SNSリンクを管理可能にする。

## 完了条件

- 現行トップにある主要導線が、Laravel側の公開ページまたは後続タスクとして漏れなく管理されている。
- 公開画面はDB正本を読むだけにし、手作業でHTMLを書き換える箇所を増やさない。
- 管理画面で入力したINFORMATIONカテゴリが、公開一覧・会員一覧・トップ表示で同じ表示になる。

## 2026-07-01 公開画面照合監査

現行トップを再確認し、以下の導線をローカル設定へ反映した。

- 2026 JPBAトーナメント予定表(PDF)
- JPBAツアー ご観戦時のご案内(PDF)
- ウレタンボールの使用規制について(PDF)
- JPBA LIVEチャンネル
- io.LEAGUEチャンネル
- io.LEAGUE Official Website

`php artisan public:parity-audit` を追加し、公開ページをLaravel内部でレンダリングして以下を確認できるようにした。

- HTTP 200で表示できること
- 現行サイト相当のロゴ、主要ナビ、補助導線、フッター導線が欠落していないこと
- ページ固有の主要見出しが欠落していないこと
- 画像数、PDFリンク数、外部リンク数、内部リンク数をページごとに把握できること
- ローカル画像/添付ファイルの参照切れがないこと

2026-07-01実行結果:

| page | path | status | images | pdf_links | external_links | internal_links | missing_assets |
|---|---|---|---:|---:|---:|---:|---:|
| home | `/` | OK | 3 | 4 | 6 | 18 | 0 |
| about | `/about` | OK | 1 | 6 | 8 | 16 | 0 |
| schedule | `/schedule` | OK | 1 | 1 | 0 | 18 | 0 |
| players | `/players` | OK | 1 | 0 | 0 | 61 | 0 |
| tournaments | `/tournament` | OK | 1 | 1 | 0 | 22 | 0 |
| instructors | `/instructor` | OK | 1 | 0 | 10 | 30 | 0 |
| protest | `/protest` | OK | 1 | 0 | 3 | 16 | 0 |
| topics | `/topics` | OK | 1 | 0 | 4 | 22 | 0 |
| contact | `/contact` | OK | 1 | 0 | 1 | 16 | 0 |
| media | `/media` | OK | 1 | 2 | 4 | 16 | 0 |
| commerce | `/commerce` | OK | 1 | 0 | 1 | 16 | 0 |
| privacy | `/privacy` | OK | 1 | 0 | 1 | 16 | 0 |

全12ページで、主要HTML構造、画像/バナー、PDF/外部リンク、フッターリンクの自動照合はOK。
