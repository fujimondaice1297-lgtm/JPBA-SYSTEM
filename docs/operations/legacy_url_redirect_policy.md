# 旧URL互換・外部リンク整理方針

確認日: 2026-06-26

## 基本方針

- Laravel側でDB正本または設定正本から表示できるページは、ローカル公開URLへ寄せる。
- 現行サイトの `index.html` / `.html` 形式は、可能な範囲で301リダイレクトする。
- まだDB化していないPDF、外部フォーム、旧大会詳細、社会貢献活動、プロボウラー紹介は、`config/jpba_public.php` の外部リンクとして残す。
- 管理画面の既存URLと衝突する場合は、公開側を現行サイトに近い単数形URLにする。

## Laravel側へ寄せたURL

| 旧URL | 新URL | 方針 |
| --- | --- | --- |
| `/player` | `/players` | 301 |
| `/player/index.html` | `/players` | 301 |
| `/tournament/index.html` | `/tournament` | 301 |
| `/instructor/index.html` | `/instructor` | 301 |
| `/protest/index.html` | `/protest` | 301 |
| `/topics.html` | `/topics` | 301 |
| `/update_logs.html` | `/topics` | 301 |
| `/inquiry` | `/contact` | 301 |
| `/inquiry/index.html` | `/contact` | 301 |
| `/media/index.html` | `/media` | 301 |
| `/ovservance` | `/commerce` | 301 |
| `/ovservance/index.html` | `/commerce` | 301 |
| `/ovservance.html` | `/commerce` | 301 |
| `/policy` | `/privacy` | 301 |
| `/policy/index.html` | `/privacy` | 301 |

## 当面外部リンクとして残すURL

| 導線 | URL | 理由 |
| --- | --- | --- |
| プロテスト実施概要 | `https://www.jpba1.jp/protest/guide.html` | PDF/詳細資料のDB化前 |
| 取材時遵守事項PDF | `https://www.jpba1.jp/media/PDF/ComplianceRules_forMedia_230508.pdf` | PDF原本を現行サイトに保持 |
| 取材申請書PDF | `https://www.jpba1.jp/media/PDF/ApplicationSheet_forMedia_2024.pdf` | PDF原本を現行サイトに保持 |
| 取材申請書フォーム | `https://ws.formzu.net/fgen/S86209866/` | 外部フォーム |
| 社会貢献活動 | `https://www.jpba.or.jp/information/Charity/Charity.html` | トピックス配下の旧コンテンツ |
| プロボウラー紹介 | `https://www.jpba.or.jp/interview/index.html` | 旧インタビューコンテンツ |
| 旧大会詳細 | `https://www.jpba.or.jp/information/tournament/tournament.html` | 大会ごとの旧詳細ページ移行前 |

## 次に判断すること

- 旧 `jpba.or.jp` の大会詳細を、`tournaments` / `tournament_files` / `result_cards` へどこまで取り込むか決める。
- PDFを現行サイト参照のままにするか、管理画面からアップロードして `storage` 配信へ寄せるか決める。
- 社会貢献活動とプロボウラー紹介を `informations` で扱うか、専用コンテンツテーブルを作るか決める。
