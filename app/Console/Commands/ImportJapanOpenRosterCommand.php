<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Services\JapanOpenFormatService;
use App\Services\JapanOpenRosterImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class ImportJapanOpenRosterCommand extends Command
{
    protected $signature = 'jpba:import-japan-open-roster
        {year : Tournament year}
        {--men-file= : Men roster TSV; defaults to database/data/jpba_japan_open_YEAR_roster_men.tsv}
        {--women-file= : Women roster TSV; defaults to database/data/jpba_japan_open_YEAR_roster_women.tsv}
        {--force : Write changes; otherwise validate only}
        {--json : Output JSON}';

    protected $description = 'Validate and import the confirmed Japan Open team rosters into team, doubles and singles components.';

    public function handle(JapanOpenRosterImportService $service): int
    {
        try {
            $year = (int) $this->argument('year');
            $components = $this->components($year);
            $files = [
                'men' => $this->rosterPath('men', $year),
                'women' => $this->rosterPath('women', $year),
            ];
            $texts = collect($files)->map(fn (string $path): string => $this->readRoster($path))->all();
            $parsed = [
                'men' => $service->parse($components['men_team'], $texts['men']),
                'women' => $service->parse($components['women_team'], $texts['women']),
            ];

            $report = [
                'mode' => $this->option('force') ? 'write' : 'dry-run',
                'year' => $year,
                'edition_id' => (int) $components['men_team']->tournament_edition_id,
                'files' => $files,
                'summary' => collect($parsed)->map(fn (array $rows): array => [
                    'team_count' => collect($rows)->pluck('team_code')->unique()->count(),
                    'member_count' => count($rows),
                    'professional_count' => collect($rows)->where('is_professional', true)->count(),
                    'amateur_count' => collect($rows)->where('is_professional', false)->count(),
                ])->all(),
                'results' => null,
            ];

            if ($this->option('force')) {
                $report['results'] = DB::transaction(fn (): array => [
                    'men' => $service->import($components['men_team'], $texts['men']),
                    'women' => $service->import($components['women_team'], $texts['women']),
                ]);
            }

            if ($this->option('json')) {
                $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            } else {
                $this->info('Japan Open roster: '.$report['mode']);
                foreach (['men' => '男子', 'women' => '女子'] as $key => $label) {
                    $summary = $report['summary'][$key];
                    $this->line(sprintf(
                        '%s: %dチーム / %d名（プロ%d・アマ%d）',
                        $label,
                        $summary['team_count'],
                        $summary['member_count'],
                        $summary['professional_count'],
                        $summary['amateur_count'],
                    ));
                }
                if (! $this->option('force')) {
                    $this->warn('確認のみです。登録する場合は --force を付けてください。');
                }
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @return array{men_team:Tournament,women_team:Tournament} */
    private function components(int $year): array
    {
        $rows = Tournament::query()
            ->where('year', $year)
            ->whereHas('series', fn ($query) => $query->where('code', JapanOpenFormatService::SERIES_CODE))
            ->get()
            ->mapWithKeys(function (Tournament $tournament): array {
                $code = (string) data_get($tournament->template_snapshot, 'japan_open.component_code');

                return $code === '' ? [] : [$code => $tournament];
            });

        foreach (['men_team', 'women_team'] as $required) {
            if (! $rows->has($required)) {
                throw new RuntimeException("{$year}年ジャパンオープンの{$required}がありません。先に標準構成を作成してください。");
            }
        }

        return [
            'men_team' => $rows->get('men_team'),
            'women_team' => $rows->get('women_team'),
        ];
    }

    private function rosterPath(string $gender, int $year): string
    {
        $option = trim((string) $this->option($gender.'-file'));

        return $option !== ''
            ? $option
            : database_path("data/jpba_japan_open_{$year}_roster_{$gender}.tsv");
    }

    private function readRoster(string $path): string
    {
        if (! is_file($path)) {
            throw new RuntimeException("編成ファイルが見つかりません: {$path}");
        }

        $contents = file_get_contents($path);
        if ($contents === false || trim($contents) === '') {
            throw new RuntimeException("編成ファイルが空です: {$path}");
        }

        return $contents;
    }
}
