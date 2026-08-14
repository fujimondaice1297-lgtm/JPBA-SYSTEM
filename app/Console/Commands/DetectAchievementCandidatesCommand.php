<?php

namespace App\Console\Commands;

use App\Models\GameScore;
use App\Models\Tournament;
use App\Services\AchievementDetectionService;
use Illuminate\Console\Command;

class DetectAchievementCandidatesCommand extends Command
{
    protected $signature = 'jpba:detect-achievement-candidates
        {--tournament-id=* : Scan only the specified tournament ID(s)}
        {--json : Output a machine-readable report}';

    protected $description = 'Detect perfect games and eligible exact-three-game 800 series from stored scores.';

    public function handle(AchievementDetectionService $detection): int
    {
        $requestedIds = collect((array) $this->option('tournament-id'))
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->unique()
            ->values();

        $query = Tournament::query()
            ->whereIn('id', GameScore::query()->select('tournament_id')->distinct())
            ->orderBy('id');

        if ($requestedIds->isNotEmpty()) {
            $query->whereIn('id', $requestedIds);
        }

        $report = [
            'tournaments_scanned' => 0,
            'perfect_candidates_created' => 0,
            'eight_hundred_candidates_created' => 0,
            'tournaments' => [],
        ];

        $query->each(function (Tournament $tournament) use ($detection, &$report): void {
            $summary = $detection->scanTournament((int) $tournament->id);
            $report['tournaments_scanned']++;
            $report['perfect_candidates_created'] += $summary['perfect_candidates'];
            $report['eight_hundred_candidates_created'] += $summary['eight_hundred_candidates'];
            $report['tournaments'][] = [
                'id' => $tournament->id,
                'name' => $tournament->name,
                ...$summary,
            ];
        });

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->info('公認記録の候補検出が完了しました。');
            $this->line('対象大会: ' . $report['tournaments_scanned']);
            $this->line('パーフェクト候補（新規）: ' . $report['perfect_candidates_created']);
            $this->line('800シリーズ候補（新規）: ' . $report['eight_hundred_candidates_created']);
        }

        return self::SUCCESS;
    }
}
