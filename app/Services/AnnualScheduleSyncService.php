<?php

namespace App\Services;

use App\Models\AnnualSchedule;
use App\Models\AnnualScheduleRow;
use App\Models\Tournament;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AnnualScheduleSyncService
{
    public const ACTION_ASK = 'ask';
    public const ACTION_LINK = 'link';
    public const ACTION_OVERWRITE = 'overwrite';
    public const ACTION_SEPARATE = 'separate';
    public const ACTION_SKIP = 'skip';

    public function assertNoUnresolvedConflict(array $attributes, string $action): void
    {
        if ($action !== self::ACTION_ASK) {
            return;
        }

        $year = $this->yearFromAttributes($attributes);
        $title = trim((string) ($attributes['name'] ?? ''));
        if (!$year || $title === '') {
            return;
        }

        $candidate = $this->sameNameRows(
            $year,
            $title,
            $attributes['start_date'] ?? null,
            $attributes['venue_name'] ?? null,
        )->first();
        if (!$candidate) {
            return;
        }

        throw ValidationException::withMessages([
            'annual_schedule_conflict_action' => sprintf(
                '年間予定表に同名の行「%s」があります。年間予定表の反映方法から「既存行に紐づける」「既存行を大会情報で上書き」「別の行として追加」のいずれかを選んでください。',
                $candidate->title
            ),
        ]);
    }

    public function sync(Tournament $tournament, string $action): ?AnnualScheduleRow
    {
        if ($action === self::ACTION_SKIP || !$tournament->start_date) {
            return null;
        }

        $linked = AnnualScheduleRow::query()->where('tournament_id', $tournament->id)->first();
        if ($linked) {
            $this->fillFromTournament($linked, $tournament)->save();
            return $linked;
        }

        $year = (int) $tournament->start_date->format('Y');
        $schedule = AnnualSchedule::query()->firstOrCreate(
            ['year' => $year],
            [
                'title' => 'トーナメント年間予定表',
                'notice' => '※都合により、日時・会場等変更になる場合があります。',
                'status' => AnnualSchedule::STATUS_DRAFT,
                'created_by_user_id' => auth()->id(),
                'updated_by_user_id' => auth()->id(),
            ]
        );

        $candidate = $this->sameNameRows(
            $year,
            $tournament->name,
            $tournament->start_date?->toDateString(),
            $tournament->venue_name,
        )->first();
        if ($candidate && in_array($action, [self::ACTION_LINK, self::ACTION_OVERWRITE], true)) {
            $candidate->tournament_id = $tournament->id;
            if ($action === self::ACTION_OVERWRITE) {
                $this->fillFromTournament($candidate, $tournament);
            }
            $candidate->save();
            return $candidate;
        }

        $row = new AnnualScheduleRow([
            'annual_schedule_id' => $schedule->id,
            'tournament_id' => $tournament->id,
            'month' => (int) $tournament->start_date->format('n'),
            'sort_order' => ((int) $schedule->rows()->where('month', $tournament->start_date->month)->max('sort_order')) + 10,
            'source_type' => 'tournament',
        ]);
        $this->fillFromTournament($row, $tournament)->save();

        return $row;
    }

    private function fillFromTournament(AnnualScheduleRow $row, Tournament $tournament): AnnualScheduleRow
    {
        $start = $tournament->start_date;
        $end = $tournament->end_date ?: $start;
        $row->fill([
            'month' => (int) $start->format('n'),
            'start_date' => $start?->toDateString(),
            'end_date' => $end?->toDateString(),
            'date_label' => $this->dateLabel($start, $end),
            'title' => $tournament->name,
            'eligibility' => match ($tournament->gender) {
                'M' => '男子',
                'F' => '女子',
                default => '男子・女子',
            },
            'venue' => $tournament->venue_name,
            'point_mark' => $tournament->counts_for_official_points ? '○' : null,
            'average_mark' => $tournament->counts_for_average ? '○' : null,
            'prize_mark' => $tournament->counts_for_prize ? '○' : null,
            'title_mark' => $tournament->title_scope === 'official' ? '○' : null,
            'row_type' => 'event',
            'source_type' => 'tournament',
        ]);

        return $row;
    }

    private function sameNameRows(int $year, string $title, mixed $startDate = null, mixed $venue = null)
    {
        $key = $this->normalizeTitle($title);
        $date = $startDate ? (string) $startDate : '';
        $venueKey = $this->normalizeTitle((string) $venue);

        return AnnualScheduleRow::query()
            ->whereHas('schedule', fn ($query) => $query->where('year', $year))
            ->whereNull('tournament_id')
            ->get()
            ->filter(fn (AnnualScheduleRow $row) => $this->normalizeTitle((string) $row->title) === $key)
            ->sortByDesc(function (AnnualScheduleRow $row) use ($date, $venueKey): int {
                $score = 0;
                if ($date !== '' && $row->start_date?->toDateString() === $date) {
                    $score += 2;
                }
                if ($venueKey !== '' && $this->normalizeTitle((string) $row->venue) === $venueKey) {
                    $score += 1;
                }
                return $score;
            });
    }

    private function normalizeTitle(string $value): string
    {
        $value = mb_convert_kana($value, 'asKV');
        $value = preg_replace('/JPBA|トーナメント|[A-ZＡ-Ｚ]会場$/iu', '', $value) ?? '';

        return Str::lower(preg_replace('/[\s　・･\.．「」『』\'"()（）]+/u', '', $value) ?? '');
    }

    private function yearFromAttributes(array $attributes): ?int
    {
        $date = trim((string) ($attributes['start_date'] ?? ''));
        return preg_match('/^(\d{4})-/', $date, $matches) ? (int) $matches[1] : null;
    }

    private function dateLabel($start, $end): string
    {
        if (!$start) {
            return '';
        }

        $week = ['日', '月', '火', '水', '木', '金', '土'];
        $first = $start->format('n/j') . '（' . $week[$start->dayOfWeek] . '）';
        if (!$end || $start->isSameDay($end)) {
            return $first;
        }

        return $first . '－' . $end->format('n/j') . '（' . $week[$end->dayOfWeek] . '）';
    }
}
