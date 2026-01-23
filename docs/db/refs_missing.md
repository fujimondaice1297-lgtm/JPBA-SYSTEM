# Missing Refs (auto-detected)

- Generated at: 2026-01-23T01:15:10+01:00
- DB: JPBA_MAIN (host=127.0.0.1 port=5433)
- Schema: public

このファイルは **DBのカラム（*_id）** を見て、**ER（docs/db/ER.dbml）にまだ書かれていないRef** を洗い出したものです。
下の「Suggested additions」は **そのまま data_dictionary.md にコピペ**できます。

## Suggested additions (copy/paste)

### approved_ball_pro_bowler

```md
- approved_ball_pro_bowler.approved_ball_id -> approved_balls.id
```

### group_mailouts

```md
- group_mailouts.group_id -> groups.id
- group_mailouts.sender_user_id -> users.id
```

### group_members

```md
- group_members.group_id -> groups.id
```

### instructors

```md
- instructors.pro_bowler_id -> pro_bowlers.id
- instructors.district_id -> districts.id
```

### media_publications

```md
- media_publications.tournament_id -> tournaments.id
```

### point_distributions

```md
- point_distributions.tournament_id -> tournaments.id
```

### prize_distributions

```md
- prize_distributions.tournament_id -> tournaments.id
```

### pro_bowler_biographies

```md
- pro_bowler_biographies.pro_bowler_id -> pro_bowlers.id
```

### pro_bowler_instructor_info

```md
- pro_bowler_instructor_info.pro_bowler_id -> pro_bowlers.id
```

### pro_bowler_links

```md
- pro_bowler_links.pro_bowler_id -> pro_bowlers.id
```

### pro_bowler_profiles

```md
- pro_bowler_profiles.pro_bowler_id -> pro_bowlers.id
```

### pro_bowler_sponsors

```md
- pro_bowler_sponsors.pro_bowler_id -> pro_bowlers.id
```

### pro_bowler_titles

```md
- pro_bowler_titles.pro_bowler_id -> pro_bowlers.id
- pro_bowler_titles.tournament_id -> tournaments.id
```

### pro_bowler_trainings

```md
- pro_bowler_trainings.pro_bowler_id -> pro_bowlers.id
- pro_bowler_trainings.training_id -> trainings.id
```

### pro_bowlers

```md
- pro_bowlers.district_id -> districts.id
```

### pro_test

```md
- pro_test.record_type_id -> record_types.id
```

### pro_test_attachment

```md
- pro_test_attachment.pro_test_id -> pro_test.id
```

### pro_test_comment

```md
- pro_test_comment.pro_test_id -> pro_test.id
```

### pro_test_schedule

```md
- pro_test_schedule.venue_id -> venues.id
```

### pro_test_score

```md
- pro_test_score.pro_test_id -> pro_test.id
```

### pro_test_score_summary

```md
- pro_test_score_summary.pro_test_id -> pro_test.id
```

### pro_test_status_log

```md
- pro_test_status_log.pro_test_id -> pro_test.id
```

### record_types

```md
- record_types.pro_bowler_id -> pro_bowlers.id
```

### registered_balls

```md
- registered_balls.approved_ball_id -> approved_balls.id
```

### sessions

```md
- sessions.user_id -> users.id
```

### tournament_awards

```md
- tournament_awards.tournament_id -> tournaments.id
```

### tournament_participants

```md
- tournament_participants.tournament_id -> tournaments.id
```

### tournament_points

```md
- tournament_points.tournament_id -> tournaments.id
```

### tournament_results

```md
- tournament_results.tournament_id -> tournaments.id
```

### used_balls

```md
- used_balls.pro_bowler_id -> pro_bowlers.id
- used_balls.approved_ball_id -> approved_balls.id
```

## Unresolved (needs decision)

### group_mail_recipients
- group_mail_recipients.mailout_id （候補: mailout, mailouts, mailout_types, mailout_masters, mailout_master）

### hof_inductions
- hof_inductions.pro_id （候補: pro, pros, pro_types, pro_masters, pro_master）

### hof_photos
- hof_photos.hof_id （候補: hof, hofs, hof_types, hof_masters, hof_master）

### informations
- informations.required_training_id （候補: required_training, required_trainings, required_training_types, required_training_masters, required_training_master）

### point_distributions
- point_distributions.pattern_id （候補: pattern, patterns, pattern_types, pattern_masters, pattern_master）

### prize_distributions
- prize_distributions.pattern_id （候補: pattern, patterns, pattern_types, pattern_masters, pattern_master）

### pro_bowlers
- pro_bowlers.login_id （候補: login, logins, login_types, login_masters, login_master）
