<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Services\TournamentResultCompletenessService;
use Illuminate\Console\Command;

final class AuditTournamentResultCompletenessCommand extends Command
{
    protected $signature = 'tournament:audit-result-completeness
        {--year= : Target tournament year}
        {--tournament-id=* : Target tournament IDs}
        {--all : Include tournaments without a current publication}
        {--json : Output the full report as JSON}';

    protected $description = 'Fail when tournament scores, result flow, published statistics, or PDF source data are incomplete.';

    public function handle(TournamentResultCompletenessService $service): int
    {
        $query = Tournament::query()->orderBy('id');
        $year = (int) ($this->option('year') ?: 0);
        if ($year > 0) {
            $query->where('year', $year);
        }

        $ids = collect((array) $this->option('tournament-id'))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values();
        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids->all());
        }
        if (! $this->option('all')) {
            $query->whereHas('resultPublications', fn ($publicationQuery) => $publicationQuery->where('status', 'current'));
        }

        $reports = $query->get()->map(function (Tournament $tournament) use ($service): array {
            $audit = $service->audit($tournament);

            return [
                'tournament_id' => (int) $tournament->id,
                'name' => (string) $tournament->name,
                'status' => $audit['is_complete'] ? 'OK' : 'FAIL',
                'score_count' => (int) ($audit['facts']['score_count'] ?? 0),
                'snapshot_gap_count' => count((array) ($audit['facts']['snapshot_gaps'] ?? [])),
                'stat_mismatch_count' => count((array) ($audit['facts']['publication_stat_mismatches'] ?? [])),
                'errors' => $audit['errors'],
                'facts' => $audit['facts'],
            ];
        })->all();

        if ($this->option('json')) {
            $this->line(json_encode($reports, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else {
            $this->table(
                ['ID', '大会', '判定', 'スコア', '不足表', '統計差異', '理由'],
                array_map(fn (array $row): array => [
                    $row['tournament_id'],
                    $row['name'],
                    $row['status'],
                    $row['score_count'],
                    $row['snapshot_gap_count'],
                    $row['stat_mismatch_count'],
                    implode(' / ', array_slice($row['errors'], 0, 3)),
                ], $reports),
            );
        }

        return collect($reports)->contains(fn (array $row): bool => $row['status'] === 'FAIL')
            ? self::FAILURE
            : self::SUCCESS;
    }
}
