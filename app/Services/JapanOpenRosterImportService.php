<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class JapanOpenRosterImportService
{
    /** @return array<string,mixed> */
    public function import(Tournament $teamTournament, string $text): array
    {
        $this->assertTeamComponent($teamTournament);
        $rows = $this->parse($teamTournament, $text);
        $grouped = collect($rows)->groupBy('team_code');

        foreach ($grouped as $teamCode => $members) {
            if ($members->count() !== 4 || $members->pluck('member_order')->unique()->count() !== 4) {
                throw new InvalidArgumentException("{$teamCode} は1～4番の4名をそろえてください。");
            }
            if ($members->where('is_professional', true)->count() > 2) {
                throw new InvalidArgumentException("{$teamCode} はプロ2名までです。");
            }
            foreach ([[1, 2], [3, 4]] as $pairOrders) {
                $proCount = $members->whereIn('member_order', $pairOrders)->where('is_professional', true)->count();
                if ($proCount > 1) {
                    throw new InvalidArgumentException(
                        "{$teamCode} のダブルス組（{$pairOrders[0]}・{$pairOrders[1]}番）はプロ1名までです。"
                    );
                }
            }
        }

        $components = $this->editionComponents($teamTournament);
        $genderPrefix = $teamTournament->gender === 'F' ? 'women' : 'men';
        foreach ([$genderPrefix.'_team', $genderPrefix.'_doubles', $genderPrefix.'_singles'] as $required) {
            if (! isset($components[$required])) {
                throw new InvalidArgumentException('同年度のジャパンオープン競技構成が不足しています: '.$required);
            }
        }

        return DB::transaction(function () use ($grouped, $components, $genderPrefix): array {
            $team = $components[$genderPrefix.'_team'];
            $doubles = $components[$genderPrefix.'_doubles'];
            $singles = $components[$genderPrefix.'_singles'];
            $participantCount = 0;
            $teamCount = 0;
            $doublesCount = 0;

            foreach ($grouped as $teamCode => $members) {
                $members = $members->sortBy('member_order')->values();
                $teamGroupId = $this->upsertGroup($team, $teamCode, (string) $members->first()['team_name'], 4);
                DB::table('tournament_competitor_group_members')->where('competitor_group_id', $teamGroupId)->delete();
                $teamCount++;

                $doublesGroupIds = [
                    1 => $this->upsertGroup($doubles, $teamCode.'-1', (string) $members->first()['team_name'].' 1組', 2),
                    2 => $this->upsertGroup($doubles, $teamCode.'-2', (string) $members->first()['team_name'].' 2組', 2),
                ];
                DB::table('tournament_competitor_group_members')
                    ->whereIn('competitor_group_id', array_values($doublesGroupIds))
                    ->delete();
                $doublesCount += 2;

                foreach ($members as $member) {
                    $teamParticipantId = $this->upsertParticipant($team, $member);
                    $doublesParticipantId = $this->upsertParticipant($doubles, $member);
                    $this->upsertParticipant($singles, $member);

                    DB::table('tournament_competitor_group_members')->insert([
                        'competitor_group_id' => $teamGroupId,
                        'tournament_participant_id' => $teamParticipantId,
                        'member_order' => $member['member_order'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $pair = $member['member_order'] <= 2 ? 1 : 2;
                    DB::table('tournament_competitor_group_members')->insert([
                        'competitor_group_id' => $doublesGroupIds[$pair],
                        'tournament_participant_id' => $doublesParticipantId,
                        'member_order' => (($member['member_order'] - 1) % 2) + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $participantCount++;
                }
            }

            return [
                'team_count' => $teamCount,
                'doubles_count' => $doublesCount,
                'member_count' => $participantCount,
                'synced_tournament_ids' => [$team->id, $doubles->id, $singles->id],
            ];
        });
    }

    /** @return array<int,array<string,mixed>> */
    public function parse(Tournament $teamTournament, string $text): array
    {
        $rows = [];
        $errors = [];
        $lines = preg_split('/\R/u', trim($text)) ?: [];

        foreach ($lines as $lineNumber => $line) {
            if (trim($line) === '') {
                continue;
            }
            $columns = str_contains($line, "\t") ? explode("\t", $line) : str_getcsv($line);
            $columns = array_map(static fn ($value): string => trim((string) $value), $columns);

            if ($lineNumber === 0 && str_contains(implode('', $columns), 'チームコード')) {
                continue;
            }
            if (count($columns) < 5) {
                $errors[] = ($lineNumber + 1).'行目は5列必要です。';

                continue;
            }

            [$teamCode, $teamName, $memberOrder, $license, $displayName] = array_slice($columns, 0, 5);
            $order = (int) $memberOrder;
            if ($teamCode === '' || $teamName === '' || $order < 1 || $order > 4) {
                $errors[] = ($lineNumber + 1).'行目のチームコード・チーム名・順番（1～4）を確認してください。';

                continue;
            }
            $normalizedTeamCode = $this->normalizeTeamCode($teamCode);
            if ($normalizedTeamCode === '') {
                $errors[] = ($lineNumber + 1).'行目のチームコードには英数字を含めてください。';

                continue;
            }

            $bowler = $license !== '' ? $this->resolveProBowler($teamTournament, $license) : null;
            if ($license !== '' && $bowler === null) {
                $errors[] = ($lineNumber + 1)."行目のプロライセンスNo. {$license} を確認できません。";

                continue;
            }
            if ($bowler === null && $displayName === '') {
                $errors[] = ($lineNumber + 1).'行目のアマチュア選手名が空欄です。';

                continue;
            }

            $rows[] = [
                'team_code' => $normalizedTeamCode,
                'team_name' => $teamName,
                'member_order' => $order,
                'is_professional' => $bowler !== null,
                'pro_bowler_id' => $bowler?->id,
                'license_no' => $bowler?->license_no,
                'display_name' => $bowler?->name_kanji ?: $displayName,
            ];
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', array_slice($errors, 0, 8)));
        }
        if ($rows === []) {
            throw new InvalidArgumentException('編成データがありません。');
        }

        $duplicates = collect($rows)->groupBy(
            fn (array $row): string => $row['team_code'].'|'.$row['member_order']
        )->filter(fn ($values) => $values->count() > 1);
        if ($duplicates->isNotEmpty()) {
            throw new InvalidArgumentException('同じチーム内で順番が重複しています: '.$duplicates->keys()->implode('、'));
        }

        $duplicatePros = collect($rows)
            ->filter(fn (array $row): bool => $row['pro_bowler_id'] !== null)
            ->groupBy('pro_bowler_id')
            ->filter(fn ($values) => $values->count() > 1);
        if ($duplicatePros->isNotEmpty()) {
            $names = $duplicatePros->map(fn ($values) => $values->first()['display_name'])->values()->implode('、');
            throw new InvalidArgumentException('同じプロが複数行に登録されています: '.$names);
        }

        return $rows;
    }

    private function assertTeamComponent(Tournament $tournament): void
    {
        $component = (string) data_get($tournament->template_snapshot, 'japan_open.component_code');
        if ($tournament->competition_type !== 'team' || ! in_array($component, ['men_team', 'women_team'], true)) {
            throw new InvalidArgumentException('ジャパンオープンの男女チーム戦で実行してください。');
        }
    }

    /** @return array<string,Tournament> */
    private function editionComponents(Tournament $tournament): array
    {
        return Tournament::query()->where('tournament_edition_id', $tournament->tournament_edition_id)
            ->get()
            ->mapWithKeys(function (Tournament $row): array {
                $code = (string) data_get($row->template_snapshot, 'japan_open.component_code');

                return $code === '' ? [] : [$code => $row];
            })->all();
    }

    private function resolveProBowler(Tournament $tournament, string $license): ?ProBowler
    {
        $normalized = strtoupper(preg_replace('/\s+/u', '', $license) ?? $license);
        if (preg_match('/^[MF]\d{8}$/', $normalized)) {
            return ProBowler::query()->where('license_no', $normalized)->first();
        }

        $digits = preg_replace('/\D+/', '', $normalized) ?? '';
        if ($digits === '') {
            return null;
        }
        $canonical = ($tournament->gender === 'F' ? 'F' : 'M').str_pad($digits, 8, '0', STR_PAD_LEFT);

        return ProBowler::query()->where('license_no', $canonical)->first();
    }

    private function normalizeTeamCode(string $value): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?? $value);

        return trim($normalized, '-');
    }

    /** @param array<string,mixed> $member */
    private function upsertParticipant(Tournament $tournament, array $member): int
    {
        $isProfessional = (bool) $member['is_professional'];
        $internalLicense = $isProfessional
            ? (string) $member['license_no']
            : sprintf(
                'JO-%d-%s-%s-%d',
                $tournament->year,
                $tournament->gender,
                $member['team_code'],
                $member['member_order'],
            );
        $query = DB::table('tournament_participants')->where('tournament_id', $tournament->id);
        $isProfessional
            ? $query->where('pro_bowler_id', $member['pro_bowler_id'])
            : $query->where('pro_bowler_license_no', $internalLicense);
        $existing = $query->first();
        $payload = [
            'pro_bowler_license_no' => $internalLicense,
            'pro_bowler_id' => $member['pro_bowler_id'],
            'participant_type' => $isProfessional ? 'pro' : 'amateur',
            'display_name' => $member['display_name'],
            'display_license_no' => $isProfessional ? $member['license_no'] : null,
            'gender' => $tournament->gender,
            'source_note' => 'ジャパンオープン編成一括取込',
            'is_temporary' => ! $isProfessional,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('tournament_participants')->where('id', $existing->id)->update($payload);

            return (int) $existing->id;
        }

        return (int) DB::table('tournament_participants')->insertGetId($payload + [
            'tournament_id' => $tournament->id,
            'created_at' => now(),
        ]);
    }

    private function upsertGroup(Tournament $tournament, string $code, string $name, int $memberCount): int
    {
        $existing = DB::table('tournament_competitor_groups')
            ->where('tournament_id', $tournament->id)
            ->where('code', $code)
            ->first();
        $payload = [
            'group_type' => $tournament->competition_type,
            'name' => $name,
            'division' => $tournament->gender === 'F' ? '女子' : '男子',
            'expected_member_count' => $memberCount,
            'is_active' => true,
            'updated_at' => now(),
        ];
        if ($existing) {
            DB::table('tournament_competitor_groups')->where('id', $existing->id)->update($payload);

            return (int) $existing->id;
        }

        return (int) DB::table('tournament_competitor_groups')->insertGetId($payload + [
            'tournament_id' => $tournament->id,
            'code' => $code,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
    }
}
