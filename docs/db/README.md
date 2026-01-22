# DB地図（JPBA-system）

このフォルダは「DBの地図」と「設計の理由」をまとめる場所です。  
コード（migrations）だけだと後から全体像が分かりにくくなるので、ここに要点を残します。

---

## このフォルダにあるもの（役割）

### 1) `ER.dbml`（DBの地図）
- テーブル同士のつながり（主キー / 外部キー）を図にするためのファイル
- 新しいテーブルを作ったら、ここにも追加して「全体像」を更新する

### 2) `data_dictionary.md`（DBの説明書）
- それぞれのテーブルが「何のためにあるか」を文章で説明する
- 主キー、ユニーク制約、重要なカラム、用途をここに書く

### 3) `decisions/ADR-*.md`（設計の理由ログ）
- 「なぜその設計にしたか」を残す
- 後から自分や他人が見ても、判断の背景が分かるようにする

---

## 一番大事なルール（これだけ守れば迷わない）

### ルール：DB変更は “3点セット” を必ず同じコミットに入れる
DBを変更したら、以下を必ずセットで更新します。

1. `database/migrations/...`（一次情報：実際にDBが変わるコード）
2. `docs/db/ER.dbml`（地図：つながりの更新）
3. `docs/db/data_dictionary.md`（説明書：テーブルの意味の更新）

※ もし設計判断（理由）が増えたら `decisions/ADR-*.md` も追加する

---

## 変更したときの作業手順（毎回これだけ）

### 1) VSCodeで作業
- migrations を追加/修正
- ER.dbml を更新
- data_dictionary.md を更新
- （必要なら）ADRを追加

### 2) セーブしてGitHubへ送る
ターミナルでこの順番：

```bash
git status
git add .
git commit -m "db: describe your change"
git push

## 3) GitHubに送る（push）
作ったら、ターミナルでこれ：

```bash
git add docs/db/README.md
git commit -m "docs: add db guide"
git push

### 4) `columns_public.csv` / `columns_by_table.md`（DBカラムの一次情報）
- `columns_public.csv` はSQLToolsのEXPORTで出したカラム一覧（元データ）
- `columns_by_table.md` はそれをテーブル別に整形した自動生成ファイル
