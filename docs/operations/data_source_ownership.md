# データ正本の役割分担

2026-06-30 時点で、賞金・ポイント配分と登録ボール運用の正本を以下のように固定する。

## 賞金・ポイント配分

### 正本

- ポイント配分の正本は `point_distributions`。
- 賞金配分の正本は `prize_distributions`。
- `tournament_results.points` と `tournament_results.prize_money` は、正式成績反映時に配分を適用した結果を保持する反映先カラム。

### 旧互換

- `tournament_points` は旧互換テーブルとして残す。
- `tournament_awards` は旧互換テーブルとして残す。
- 新規の自動化、集計、PDF出力、タイトル反映では旧互換テーブルを参照しない。
- 履歴データの移行や旧処理の互換が必要な場合だけ、明示的な移行計画を立てて `point_distributions` / `prize_distributions` へ寄せる。

### 運用ルール

- 配分は `tournament_id + rank` 単位で1件に揃える。
- テンプレート由来の配分は `pattern_id` を保持できる。
- 手入力のカスタム配分では `pattern_id` は NULL を許容する。
- 正式成績反映後の表示・PDF・タイトル関連処理は、まず `tournament_results` を読み、再計算が必要なときだけ配分正本を読む。

## 登録ボール・使用ボール

### 正本

- 公式登録台帳の正本は `registered_balls`。
- 大会エントリーで選べる使用ボール候補の正本は `used_balls`。
- エントリーごとの選択結果の正本は `tournament_entry_balls`。
- 承認ボールのマスタは `approved_balls`。

### 役割

- `registered_balls` は、ライセンス番号、承認ボール、シリアル番号、検量証番号、登録日、有効期限を持つ公式登録台帳。
- `used_balls` は、プロボウラーIDに紐づく大会運用用の選択候補リスト。
- `tournament_entry_balls` は、1大会1エントリーで実際に選択した使用ボールの履歴。

### 同期ルール

- `registered_balls` の作成・更新後は、同じ選手・同じシリアルの `used_balls` へ同期する。
- 大会使用ボール画面の表示前にも `registered_balls -> used_balls` 同期を行い、公式登録台帳側の修正漏れを拾う。
- 同じシリアルの `used_balls` が存在する場合は、無視せず公式登録台帳側の値で更新する。
- 検量証番号が空、または有効期限が NULL の行は仮登録として扱う。
- 期限切れの `used_balls` は通常のエントリー選択候補から外す。

### 注意

- `registered_balls.license_no` は旧互換の結線軸として残す。
- 新規画面・新規処理では、可能な限り `pro_bowler_id` を解決した状態で扱う。
- 大会エントリーは `registered_balls` を直接参照せず、`used_balls` と `tournament_entry_balls` を通して扱う。
- 現行画面の使用ボール上限は1エントリー12個で、Controller側で制御している。
