# 一般公開資料の新サイト保存記録（2026-08-22）

## 目的

- 旧JPBA公式サイトの閉鎖後も、一般公開ページから必要な公式資料を閲覧できるようにする。
- 一般公開導線は新サイト内の保存ファイルを参照し、取得元URLは監査情報として本記録だけに保持する。
- PDFの内容は編集せず、2026-08-22に公式サイトで公開されていた原本を保存する。

## 保存資料

| 資料 | 取得元 | 新サイト内パス | ページ | SHA-256 |
|---|---|---|---:|---|
| JPBAツアー ご観戦時のご案内 | `https://www.jpba.or.jp/information/tournament/PDF/JPBA_TournamentSpectatorRules.pdf` | `/documents/jpba/tournament-spectator-rules.pdf` | 1 | `56E4428C7067915E662E8FC8AE35007F5D4CE25BD019CF6843076CB5FA5117EE` |
| ウレタンボールの使用規制について | `https://www.jpba1.jp/mypage/notification/document/2026/RegulationsAboutUrethaneBalls_260407.pdf` | `/documents/jpba/urethane-ball-regulations-2026-04-07.pdf` | 1 | `FEA857E150C1F527AB3929EB5565DA93BA95CCC95911AD382E694A3FEFC3237C` |
| 定款 | `https://www.jpba1.jp/assets/pdf/Association/Articles_202007.pdf` | `/documents/jpba/articles-2020-07.pdf` | 11 | `5FA4B5C6026BEF87DE84454AD58B0354F57F1E015355518185340D379F897B0F` |
| 役員・代議員名簿 | `https://www.jpba1.jp/assets/pdf/Association/2025/2025_2026_Directors.pdf` | `/documents/jpba/directors-2025-2026.pdf` | 2 | `FC251507CD1CF590A81F70D4CCFF87FFFE27D9A0C35968700F27F7DE0567A34F` |
| 2026年度事業計画 | `https://www.jpba1.jp/assets/pdf/Association/2026/Plan_2026.pdf` | `/documents/jpba/business-plan-2026.pdf` | 7 | `AF1350F83ABAEFB9315A87F05DF7258B8ABDC486D55273F0D389B451B77D8A2E` |
| 2026年度収支予算 | `https://www.jpba1.jp/assets/pdf/Association/2026/Budget_2026.pdf` | `/documents/jpba/budget-2026.pdf` | 1 | `39775575CFB6C3C94F178FF022303219395726E32EF0106F7FAB4F1AF9F97593` |
| 2025年度事業報告 | `https://www.jpba1.jp/assets/pdf/Association/2026/Report_2025.pdf` | `/documents/jpba/business-report-2025.pdf` | 3 | `9ED2F3E890225F72B66B9EFA60220504C070979B26D429760139AB07E175302B` |
| 2025年度正味財産増減計算書 | `https://www.jpba1.jp/assets/pdf/Association/2026/Settlement_2025.pdf` | `/documents/jpba/financial-statement-2025.pdf` | 2 | `A444DE734202FE6EFE015AA6B957D5D2440EA62E8B6F390CB20B312BD170ACF9` |
| 取材時遵守事項 | `https://www.jpba1.jp/media/PDF/ComplianceRules_forMedia_230508.pdf` | `/documents/jpba/media-compliance-rules-2023-05-08.pdf` | 2 | `04401415B7C44C6AC43DFF50058770AC375B4129CBAF1E77FBA1FFC3CAB37309` |
| 取材申請書 | `https://www.jpba1.jp/media/PDF/ApplicationSheet_forMedia_2024.pdf` | `/documents/jpba/media-application-2024.pdf` | 2 | `02373613701D80E7A60E523590F9394DB8F52396EC75AFDECB361138EFA2E44E` |

## 置換した動的ページ

- 現行トピックスは新サイトのトピックス一覧へ置換した。
- 社会貢献活動は、社会貢献記事を含めて登録する新サイトのトピックス一覧へ置換した。
- プロボウラー紹介は新サイトの選手一覧へ置換した。
- 大会ページ一覧は新サイトの大会一覧へ置換した。

## 検証

- 10ファイルすべてのPDF署名、ファイルサイズ、SHA-256を確認した。
- Popplerで全32ページを画像化し、文字化け、破損、空白ページ、切れ、黒塗りがないことを目視確認した。
- 一般公開設定内の `jpba.or.jp` と `jpba1.jp` 参照が0件であることを自動テストする。
- 編集可能な取材ページのDB本文もデータmigrationで更新し、公開画面の旧ドメインリンク0件、ローカルPDFリンク2件、ブラウザエラー0件を確認した。
- 公式データ取込サービスに必要な取得元URLは、一般公開リンクとは別の監査情報として維持する。
