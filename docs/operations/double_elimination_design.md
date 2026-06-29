# ダブルエリミネーション方式 設計メモ

更新日: 2026-06-30

## 重要: JPBA資料確認前の仮設計

この文書は、ダブルエリミネーションを既存のシングルエリミネーションへ混ぜず、独立した方式として追加するための仮設計メモである。

リセット決勝、敗者側順位、同順位扱い、再戦条件は、一般的なダブルエリミネーションで見られる候補であり、JPBAの正式ルールとして確定したものではない。実装前に、対象大会のJPBA公式大会要項PDF、公式成績PDF、現行サイト上の速報・成績、既存DBデータを確認する。

確認できないものは固定ロジックにせず、`double_elimination_settings` と snapshot の `calculation_definition` に大会ごとの設定として保存する。調査・実装ルールは `docs/operations/tournament_format_source_policy.md` を正本とする。

## 目的

ダブルエリミネーション方式を、既存のシングルエリミネーション実装へ無理に混ぜず、将来追加できる独立した大会方式として設計する。  
この文書は設計固定用であり、この時点ではDB migrationや画面実装は追加しない。

## 基本方針

- 既存の `single_elimination_*` カラムや `SingleEliminationService` はそのまま維持する。
- ダブルエリミネーションは `double_elimination` という別の `result_type` / service / PDF partial として追加する。
- 速報入力の正本は引き続き `game_scores`。
- 正式成績反映時は、確定したブラケット構造を `tournament_result_snapshots.calculation_definition` に保存する。
- 最終成績の正本は、他方式と同じく `tournament_results`。

## 将来追加する result_flow_type 候補

現行の `TournamentController` バリデーションにはまだ追加しない。実装時に以下を候補にする。

- `prelim_to_double_elimination_to_final`
- `prelim_to_quarterfinal_to_double_elimination_to_final`
- `prelim_to_semifinal_to_double_elimination_to_final`

## 将来追加する大会設定候補

`tournaments` へ専用カラムを追加する場合の候補。

- `double_elimination_qualifier_count`
- `double_elimination_seed_source_result_code`
- `double_elimination_seed_policy`
- `double_elimination_settings` json nullable

`double_elimination_settings` に入れる候補。

```json
{
  "bracket_policy": "standard_double_elimination",
  "ranking_policy": "same_lost_round_shared_rank",
  "reset_final_policy": "if_winners_side_champion_loses_first_grand_final",
  "rematch_policy": "avoid_immediate_rematch_when_possible",
  "lane_settings": {
    "winners": {"rounds": {}},
    "losers": {"rounds": {}},
    "grand_final": {}
  }
}
```

## game_scores の entry_number 形式

速報入力・OCR取込・手入力を同じ形式へ寄せるため、以下のキー形式を候補にする。

- 勝者側: `DE:W:R{round}-M{match}:{slot}`
- 敗者側: `DE:L:R{round}-M{match}:{slot}`
- グランドファイナル: `DE:GF:M1:{slot}`
- リセット決勝: `DE:GF:M2:{slot}`

`slot` は `A` / `B`。例: `DE:L:R3-M2:A`

## ブラケット構造

`DoubleEliminationService::buildBracket()` が返す想定の構造。

```json
{
  "type": "double_elimination",
  "qualifier_count": 16,
  "bracket_size": 16,
  "seed_policy": "standard",
  "winners_rounds": [],
  "losers_rounds": [],
  "grand_final": {
    "match_key": "GF-M1",
    "reset_match_key": "GF-M2",
    "reset_required": false
  },
  "summary": {
    "actual_match_count": 0,
    "completed_match_count": 0,
    "winner_name": null
  }
}
```

## 敗者側ブラケット

- 勝者側で負けた選手は、負けた勝者側ラウンドに対応する敗者側ラウンドへ落とす。
- 敗者側は「既に1敗している選手だけ」が進む。
- 敗者側で負けた選手はその時点で敗退する。
- シード順と公式表記を優先しつつ、直前対戦の即再戦は可能な範囲で避ける。
- 即再戦を避けられない場合は、`calculation_definition.rematch_notes` に理由を残す。

## リセット決勝

以下は標準的なダブルエリミネーションを採用する場合の候補である。JPBA公式資料で別ルールが確認できた場合は、`reset_final_policy` を大会別設定で上書きする。

- 勝者側代表は0敗、敗者側代表は1敗でグランドファイナルへ進む。
- グランドファイナル1戦目で勝者側代表が勝った場合、優勝者確定。
- グランドファイナル1戦目で敗者側代表が勝った場合、両者1敗になるためリセット決勝を行う。
- リセット決勝の勝者を優勝、敗者を準優勝とする。
- 同点は自動確定しない。タイブレーク後のスコア修正または手動確定を必要とする。

## 順位決定

以下は仮の既定候補であり、公式要項・公式成績PDFで確認してから採用する。確認できない場合は、同順位扱いを固定せず `ranking_policy` で大会ごとに選べるようにする。

候補: `same_lost_round_shared_rank`

- 優勝: 1位
- 準優勝: 2位
- 敗者側決勝の敗者: 3位
- 同じ敗者側ラウンドで敗退した選手は同順位扱い
- 同順位内の表示順は、進出元seed順、必要に応じて総ピン/AVG順

順位決定戦を行う大会が出た場合は、`ranking_policy = placement_match_required` を追加候補にする。

## snapshot へ保存するもの

正式成績反映時、`tournament_result_snapshots.calculation_definition` に以下を保存する。

- `type = double_elimination`
- seed source result code / seed snapshot id
- qualifier count / bracket size
- winners_rounds / losers_rounds / grand_final
- reset final policy
- ranking policy
- rematch policy and notes
- entry_number key map
- lane settings
- completed match summary

## PDF方針

- 新規Bladeを大会名ごとに作らない。
- 方式用 partial として `double_elimination_pages.blade.php` を追加する。
- 図は `DoubleEliminationBracketImageService` で生成する。
- スコア表は既存の `tournament_match_score_sheets` / `MatchScoreSheetImageService` を使う。
- PDF文言は `result_flow_type` と `double_elimination_settings` から決める。

## 実装順

1. `double_elimination_*` カラムまたは設定JSONの保存先を追加する。
2. `DoubleEliminationService` を追加し、ブラケット生成・スコア反映・順位決定を実装する。
3. `TournamentController` の入力・バリデーションへ result_flow_type と設定を追加する。
4. `ScoreController` / 速報入力で `DE:*` entry_number を扱う。
5. `TournamentResultSnapshotController` で `double_elimination_final` を作れるようにする。
6. `TournamentResultController` とPDF partialを接続する。
7. fixture大会で、速報、snapshot、`tournament_results` 同期、PDFまで回帰確認する。
