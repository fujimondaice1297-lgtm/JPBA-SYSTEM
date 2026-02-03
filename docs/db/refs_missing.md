# Missing Refs (auto-detected)

- Generated at: 2026-02-03T20:37:53+09:00
- DB: (from Laravel .env)
- Schema: public

このファイルは **DBのカラム（*_id）** を見て、**ER（docs/db/ER.dbml）にまだ書かれていないRef** を洗い出したものです。
下の「Suggested additions」は **そのまま data_dictionary.md に転記**できます（正本は辞書）。

## Suggested additions (copy/paste)

（なし）

## Unresolved (needs decision)

### pro_bowlers
- pro_bowlers.login_id （参照先がDB上で未確定：FKなし）

### pro_test
- pro_test.record_type_id （参照先がDB上で未確定：FKなし）

## Pending decisions (skipped for now)

### pro_bowlers.login_id
- 状態: DB上でFKなし（参照先未確定）
- 理由: 旧システム由来の login 系マスタの実体（テーブル/仕様）が未確定のため決め打ちしない
- 対応方針: 参照先の仕様が確定した段階で、FK追加 or 正規化（login系マスタ作成）を行う

### pro_test.record_type_id
- 状態: DB上でFKなし（参照先未確定）
- 理由: ProTestは後回し方針のため、record_types 等との関係を今は確定しない
- 対応方針: ProTestフェーズで参照先を確定し、FK追加する
