# Missing Refs (auto-detected)

- Generated at: 2026-01-26T10:17:50+01:00
- DB: JPBA_MAIN (host=127.0.0.1 port=5433)
- Schema: public

このファイルは **DBのカラム（*_id）** を見て、**ER（docs/db/ER.dbml）にまだ書かれていないRef** を洗い出したものです。
下の「Suggested additions」は **そのまま data_dictionary.md にコピペ**できます。

## Suggested additions (copy/paste)

### pro_test

```md
- pro_test.record_type_id -> record_types.id
```

## Unresolved (needs decision)

### informations
- informations.required_training_id （候補: required_training, required_trainings, required_training_types, required_training_masters, required_training_master）

### pro_bowlers
- pro_bowlers.login_id （候補: login, logins, login_types, login_masters, login_master）
