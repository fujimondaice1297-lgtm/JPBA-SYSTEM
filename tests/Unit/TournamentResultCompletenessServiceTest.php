<?php

namespace Tests\Unit;

use App\Services\ShootoutService;
use App\Services\StepLadderService;
use App\Services\TournamentResultCompletenessService;
use PHPUnit\Framework\TestCase;

final class TournamentResultCompletenessServiceTest extends TestCase
{
    public function test_complete_facts_pass(): void
    {
        $result = $this->service()->evaluateFacts([
            'snapshot_count' => 3,
            'stage_setting_count' => 2,
            'result_output_count' => 5,
            'score_count' => 480,
            'invalid_score_count' => 0,
            'duplicate_score_count' => 0,
            'snapshot_gaps' => [],
            'flow_errors' => [],
            'publication_stat_mismatches' => [],
        ]);

        self::assertTrue($result['is_complete']);
        self::assertSame([], $result['errors']);
    }

    public function test_aggregate_only_result_fails(): void
    {
        $result = $this->service()->evaluateFacts([
            'snapshot_count' => 3,
            'stage_setting_count' => 0,
            'result_output_count' => 0,
            'score_count' => 0,
            'invalid_score_count' => 0,
            'duplicate_score_count' => 0,
            'snapshot_gaps' => [['result_code' => 'prelim_total']],
            'flow_errors' => ['シュートアウト3試合の得点または勝者が確定していません。'],
            'publication_stat_mismatches' => [['display_name' => '選手A']],
        ]);

        self::assertFalse($result['is_complete']);
        self::assertContains('競技ステージ設定がありません。', $result['errors']);
        self::assertContains('各ゲームスコアが1件もありません。集計値だけでは確定公開できません。', $result['errors']);
        self::assertContains('公開成績のゲーム数・トータルピンが実投球スコアと一致していません。', $result['errors']);
    }

    public function test_duplicate_or_invalid_scores_fail(): void
    {
        $result = $this->service()->evaluateFacts([
            'snapshot_count' => 1,
            'stage_setting_count' => 1,
            'result_output_count' => 1,
            'score_count' => 8,
            'invalid_score_count' => 1,
            'duplicate_score_count' => 1,
            'snapshot_gaps' => [],
            'flow_errors' => [],
            'publication_stat_mismatches' => [],
        ]);

        self::assertFalse($result['is_complete']);
        self::assertContains('0～300の範囲外のゲームスコアがあります。', $result['errors']);
        self::assertContains('同一選手・同一ステージ・同一ゲームの重複スコアがあります。', $result['errors']);
    }

    private function service(): TournamentResultCompletenessService
    {
        return new TournamentResultCompletenessService(
            new ShootoutService,
            new StepLadderService,
        );
    }
}
