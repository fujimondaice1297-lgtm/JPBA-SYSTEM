<?php

namespace App\Services;

use App\Models\Tournament;

class TournamentResultCarryService
{
    /**
     * 画面表示用の選択肢。
     *
     * value は tournaments.result_carry_preset に保存する値。
     */
    public function presetOptions(): array
    {
        return [
            'default' => [
                'label' => '標準（個別設定なし）',
                'description' => '既存の大会設定・画面指定を優先します。新規大会では非推奨です。',
            ],
            'no_carry' => [
                'label' => '① 予選通算→次ステージへ持ち込まない',
                'description' => '予選、準々決勝、準決勝、決勝、ラウンドロビンをそれぞれ単独集計します。',
            ],
            'carry_prelim_to_quarterfinal_reset_semifinal' => [
                'label' => '② 予選→準々決勝まで合算、準決勝からリセット',
                'description' => '準々決勝通算は予選＋準々決勝。準決勝以降はリセットします。',
            ],
            'carry_prelim_quarterfinal_semifinal_reset_final' => [
                'label' => '③ 予選→準々決勝→準決勝まで全合算、決勝へ持ち込まない',
                'description' => '準決勝通算は予選＋準々決勝＋準決勝。決勝は単独集計です。',
            ],
            'carry_prelim_to_semifinal_reset_final' => [
                'label' => '④ 予選→準決勝まで合算、決勝へ持ち込まない',
                'description' => '準決勝通算は予選＋準決勝。決勝は単独集計です。',
            ],
            'carry_all_to_semifinal_then_rr' => [
                'label' => '⑤ 予選→準々決勝→準決勝を合算し、RRへ持ち込み',
                'description' => 'ラウンドロビン通算は予選＋準々決勝＋準決勝＋ラウンドロビンです。',
            ],
            'carry_prelim_semifinal_to_rr' => [
                'label' => '⑥ 予選→準決勝を合算し、RRへ持ち込み',
                'description' => 'ラウンドロビン通算は予選＋準決勝＋ラウンドロビンです。',
            ],
            'carry_prelim_semifinal_final' => [
                'label' => '⑦ 予選→準決勝→決勝までスコア持ち込み',
                'description' => '決勝通算は予選＋準決勝＋決勝です。',
            ],
            'carry_prelim_semifinal_to_tournament_seed' => [
                'label' => '⑧ 予選→準決勝通算でトーナメントシード決定',
                'description' => '準決勝通算は予選＋準決勝。トーナメントのシード元に使う想定です。',
            ],
            'carry_all_to_semifinal_to_tournament_seed' => [
                'label' => '⑨ 予選→準々決勝→準決勝通算でトーナメントシード決定',
                'description' => '準決勝通算は予選＋準々決勝＋準決勝。トーナメントのシード元に使う想定です。',
            ],
            'carry_prelim_semifinal_to_shootout_seed' => [
                'label' => '⑩ 予選→準決勝通算で決勝/SO/トーナメント進出順を決定',
                'description' => 'シーズントライアル型。準決勝通算は予選＋準決勝です。',
            ],
            'custom' => [
                'label' => 'カスタムJSON',
                'description' => '上級者向け。result_code ごとに source_stages を直接指定します。',
            ],
        ];
    }

    /**
     * バリデーション用。旧プリセット名も互換として許可する。
     */
    public function allowedPresetKeys(): array
    {
        return array_values(array_unique(array_merge(
            array_keys($this->presetOptions()),
            [
                'reset_after_quarterfinal',
                'reset_from_quarterfinal',
                'carry_to_semifinal_reset_rr',
                'carry_prelim_to_semifinal_for_tournament',
            ]
        )));
    }

    /**
     * プリセットごとの標準設定。
     *
     * result_code => source_stages の形を正本にする。
     * ScoreService / TournamentResultSnapshotController から共通利用しやすい構造。
     */
    public function presetSettings(string $preset): array
    {
        $preset = $this->canonicalPresetKey($preset);

        return match ($preset) {
            'no_carry' => [
                'prelim_total' => ['source_stages' => ['予選']],
                'quarterfinal_total' => ['source_stages' => ['準々決勝']],
                'semifinal_total' => ['source_stages' => ['準決勝']],
                'round_robin_total' => ['source_stages' => ['ラウンドロビン']],
                'final_total' => ['source_stages' => ['決勝']],
            ],

            'carry_prelim_to_quarterfinal_reset_semifinal' => [
                'prelim_total' => ['source_stages' => ['予選']],
                'quarterfinal_total' => ['source_stages' => ['予選', '準々決勝']],
                'semifinal_total' => ['source_stages' => ['準決勝']],
                'round_robin_total' => ['source_stages' => ['準決勝', 'ラウンドロビン']],
                'final_total' => ['source_stages' => ['決勝']],
            ],

            'carry_prelim_quarterfinal_semifinal_reset_final' => [
                'prelim_total' => ['source_stages' => ['予選']],
                'quarterfinal_total' => ['source_stages' => ['予選', '準々決勝']],
                'semifinal_total' => ['source_stages' => ['予選', '準々決勝', '準決勝']],
                'round_robin_total' => ['source_stages' => ['予選', '準々決勝', '準決勝', 'ラウンドロビン']],
                'final_total' => ['source_stages' => ['決勝']],
            ],

            'carry_prelim_to_semifinal_reset_final',
            'carry_prelim_semifinal_to_tournament_seed',
            'carry_prelim_semifinal_to_shootout_seed' => [
                'prelim_total' => ['source_stages' => ['予選']],
                'semifinal_total' => ['source_stages' => ['予選', '準決勝']],
                'round_robin_total' => ['source_stages' => ['予選', '準決勝', 'ラウンドロビン']],
                'final_total' => ['source_stages' => ['決勝']],
            ],

            'carry_all_to_semifinal_then_rr' => [
                'prelim_total' => ['source_stages' => ['予選']],
                'quarterfinal_total' => ['source_stages' => ['予選', '準々決勝']],
                'semifinal_total' => ['source_stages' => ['予選', '準々決勝', '準決勝']],
                'round_robin_total' => ['source_stages' => ['予選', '準々決勝', '準決勝', 'ラウンドロビン']],
                'final_total' => ['source_stages' => ['決勝']],
            ],

            'carry_prelim_semifinal_to_rr' => [
                'prelim_total' => ['source_stages' => ['予選']],
                'semifinal_total' => ['source_stages' => ['予選', '準決勝']],
                'round_robin_total' => ['source_stages' => ['予選', '準決勝', 'ラウンドロビン']],
                'final_total' => ['source_stages' => ['決勝']],
            ],

            'carry_prelim_semifinal_final' => [
                'prelim_total' => ['source_stages' => ['予選']],
                'semifinal_total' => ['source_stages' => ['予選', '準決勝']],
                'final_total' => ['source_stages' => ['予選', '準決勝', '決勝']],
            ],

            'carry_all_to_semifinal_to_tournament_seed' => [
                'prelim_total' => ['source_stages' => ['予選']],
                'quarterfinal_total' => ['source_stages' => ['予選', '準々決勝']],
                'semifinal_total' => ['source_stages' => ['予選', '準々決勝', '準決勝']],
                'final_total' => ['source_stages' => ['決勝']],
            ],

            default => [],
        };
    }

    public function canonicalPresetKey(string $preset): string
    {
        $preset = trim($preset) ?: 'default';

        return match ($preset) {
            // 旧名互換
            'reset_after_quarterfinal' => 'carry_prelim_to_quarterfinal_reset_semifinal',
            'reset_from_quarterfinal' => 'carry_prelim_quarterfinal_semifinal_reset_final',
            'carry_to_semifinal_reset_rr' => 'carry_all_to_semifinal_then_rr',
            'carry_prelim_to_semifinal_for_tournament' => 'carry_prelim_semifinal_to_tournament_seed',
            default => $preset,
        };
    }

    public function normalizeSettings(string $preset, mixed $customJson): array
    {
        $preset = $this->canonicalPresetKey($preset);

        if ($preset === 'custom') {
            if (is_array($customJson)) {
                return $this->normalizeCarrySettingsArray($customJson);
            }

            $json = trim((string) $customJson);
            if ($json === '') {
                return [];
            }

            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                return [];
            }

            return $this->normalizeCarrySettingsArray($decoded);
        }

        return $this->presetSettings($preset);
    }

    public function settingsForTournament(?Tournament $tournament): array
    {
        if (!$tournament) {
            return [];
        }

        $settings = $tournament->result_carry_settings ?? [];

        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        if (is_array($settings) && !empty($settings)) {
            return $this->normalizeCarrySettingsArray($settings);
        }

        return $this->presetSettings((string) ($tournament->result_carry_preset ?? 'default'));
    }

    public function resultCodeForStage(string $stage): ?string
    {
        return match ($this->normalizeStageLabel($stage)) {
            '予選' => 'prelim_total',
            '準々決勝' => 'quarterfinal_total',
            '準決勝' => 'semifinal_total',
            'ラウンドロビン' => 'round_robin_total',
            '決勝' => 'final_total',
            default => null,
        };
    }

    public function sourceStagesForStage(?Tournament $tournament, string $stage): array
    {
        $resultCode = $this->resultCodeForStage($stage);
        if ($resultCode === null) {
            return [];
        }

        $settings = $this->settingsForTournament($tournament);
        $setting = $settings[$resultCode] ?? null;

        if (!is_array($setting)) {
            return [];
        }

        $sourceStages = $setting['source_stages'] ?? [];

        if (!is_array($sourceStages) || empty($sourceStages)) {
            return [];
        }

        return array_values(array_unique(array_map(
            fn ($value) => $this->normalizeStageLabel((string) $value),
            array_filter($sourceStages, fn ($value) => trim((string) $value) !== '')
        )));
    }

    /**
     * 速報ランキング / snapshot 共通で扱いやすい source_sets を返す。
     *
     * @param array<string,int> $stageGameCounts
     * @return array<int,array{stage:string,game_from:int,game_to:int,bucket:string}>
     */
    public function sourceSetsForStage(?Tournament $tournament, string $stage, array $stageGameCounts, int $currentUptoGame): array
    {
        $stage = $this->normalizeStageLabel($stage);
        $sourceStages = $this->sourceStagesForStage($tournament, $stage);

        if (empty($sourceStages)) {
            return [];
        }

        $sourceSets = [];
        $lastIndex = count($sourceStages) - 1;

        foreach ($sourceStages as $index => $sourceStage) {
            $games = (int) ($stageGameCounts[$sourceStage] ?? 0);

            if ($sourceStage === $stage) {
                $games = $currentUptoGame > 0 ? $currentUptoGame : $games;
            }

            if ($games <= 0) {
                continue;
            }

            $sourceSets[] = [
                'stage' => $sourceStage,
                'game_from' => 1,
                'game_to' => $games,
                'bucket' => $index === $lastIndex ? 'scratch' : 'carry',
            ];
        }

        return $sourceSets;
    }

    public function normalizeStageLabel(string $stage): string
    {
        $stage = trim($stage);

        return match ($stage) {
            'prelim', '予選前半', '予選後半' => '予選',
            'quarterfinal', '準々決勝' => '準々決勝',
            'semifinal', 'semi', '準決勝' => '準決勝',
            'round_robin', 'rr', 'ラウンドロビン' => 'ラウンドロビン',
            'final', 'finals', '決勝' => '決勝',
            'shootout', 'シュートアウト' => 'シュートアウト',
            'single_elimination', 'tournament', 'トーナメント' => 'トーナメント',
            default => $stage,
        };
    }

    private function normalizeCarrySettingsArray(array $settings): array
    {
        $normalized = [];

        foreach ($settings as $resultCode => $setting) {
            if (!is_string($resultCode) || !is_array($setting)) {
                continue;
            }

            $sourceStages = $setting['source_stages'] ?? [];

            if (!is_array($sourceStages)) {
                continue;
            }

            $stages = array_values(array_unique(array_map(
                fn ($stage) => $this->normalizeStageLabel((string) $stage),
                array_filter($sourceStages, fn ($stage) => trim((string) $stage) !== '')
            )));

            if (empty($stages)) {
                continue;
            }

            $normalized[$resultCode] = [
                'source_stages' => $stages,
            ];
        }

        return $normalized;
    }
}
