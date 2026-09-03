# 過去プロテスト結果アーカイブ（2026-09-03）

## 保存範囲

- 2008年度（男子47期・女子41期）から2026年度（男子64期・女子58期）まで
- 2020年度は新型コロナウイルス感染症の影響により中止されたため、結果PDFはなく中止表示だけを保存
- 実施17年度・239ファイル・44,697,326 bytes
- 第1次テスト、第2次テスト、最終結果・合格者（2026年度の追加合格者を含む）

## 新サイト内の保存先

- 年度一覧: `/protest`
- 年度詳細: `/protest/history/{year}`
- PDF原本: `/documents/jpba/protest/{year}/...`
- 表示定義: `config/pro_test_history.php`

公開画面から旧JPBAサイトへはリンクしない。今後のプロテストは `pro_test_events` 以下の運用DBと公開スナップショットを正本とし、この固定アーカイブは現行サイト閉鎖前に公開済みだった過去結果の保存版として扱う。

## 取得元（監査情報）

- `https://www.jpba.or.jp/information/protest/index.html`
- `https://www.jpba.or.jp/information/protest/{year}/test_{year}.html`
- 2020年度中止確認: `https://www.jpba.or.jp/update_logs/update_logs_2020.html`

取得元URLは監査用であり、新サイトの公開導線には使用しない。

## 検証

- 設定上の239パスと保存ファイルが全件一致
- 全239ファイルがPDF署名 `%PDF-` を持つ
- 2008～2026年度一覧、2018・2026年度詳細、2020年度中止表示をHTTP確認
- 2018年度最終結果PDFを新サイト内URLでブラウザ表示
- 公開HTML内の `jpba.or.jp` / `jpba1.jp` リンク0件
- 実ブラウザのコンソールエラー0件
