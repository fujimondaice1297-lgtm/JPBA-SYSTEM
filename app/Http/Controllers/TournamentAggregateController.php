<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentAggregateDefinition;
use App\Models\TournamentAggregateSource;
use App\Models\TournamentCompetitorGroup;
use App\Models\TournamentCompetitorGroupMember;
use App\Services\TournamentAggregateResultService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class TournamentAggregateController extends Controller
{
    public function index(Tournament $tournament)
    {
        $groups = TournamentCompetitorGroup::query()
            ->where('tournament_id', $tournament->id)
            ->with(['members.participant'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $participantRows = DB::table('tournament_participants as tp')
            ->leftJoin('pro_bowlers as pb', 'pb.id', '=', 'tp.pro_bowler_id')
            ->leftJoin('amateur_bowlers as ab', 'ab.id', '=', 'tp.amateur_bowler_id')
            ->where('tp.tournament_id', $tournament->id)
            ->select([
                'tp.id',
                'tp.participant_type',
                'tp.pro_bowler_license_no',
                'tp.display_license_no',
                'tp.display_name',
                'tp.gender',
                'tp.sort_order',
                'pb.license_no as master_license_no',
                'pb.name_kanji as pro_name',
                'ab.name as amateur_name',
            ])
            ->orderByRaw('COALESCE(tp.sort_order, 999999)')
            ->orderBy('tp.id')
            ->get();

        $participantLabels = $participantRows->mapWithKeys(function ($row): array {
            $license = trim((string) ($row->master_license_no ?: $row->display_license_no ?: $row->pro_bowler_license_no));
            $name = trim((string) ($row->display_name ?: $row->pro_name ?: $row->amateur_name));
            $label = trim(($license !== '' ? $license.' ' : '').($name !== '' ? $name : ('参加者 #'.$row->id)));

            return [(int) $row->id => $label];
        });

        $assignedParticipantIds = $groups
            ->flatMap(fn ($group) => $group->members->pluck('tournament_participant_id'))
            ->map(fn ($id) => (int) $id)
            ->all();

        $availableParticipants = $participantRows->reject(
            fn ($row) => in_array((int) $row->id, $assignedParticipantIds, true)
        )->values();

        $definitions = TournamentAggregateDefinition::query()
            ->where('tournament_id', $tournament->id)
            ->with([
                'sources.sourceTournament',
                'snapshots' => fn ($query) => $query->where('is_current', true)->latest('id'),
                'snapshots.rows' => fn ($query) => $query->orderBy('ranking'),
            ])
            ->orderBy('id')
            ->get();

        $candidateTournaments = Tournament::query()
            ->select(['id', 'name', 'year', 'competition_type', 'tournament_edition_id'])
            ->orderByRaw('CASE WHEN tournament_edition_id = ? THEN 0 WHEN year = ? THEN 1 ELSE 2 END', [
                $tournament->tournament_edition_id ?: -1,
                $tournament->year ?: 0,
            ])
            ->orderByDesc('year')
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        $stagesByTournament = DB::table('game_scores')
            ->select(['tournament_id', 'stage'])
            ->distinct()
            ->orderBy('tournament_id')
            ->orderBy('stage')
            ->get()
            ->groupBy('tournament_id')
            ->map(fn ($rows) => $rows->pluck('stage')->values()->all());

        return view('tournament_aggregates.index', compact(
            'tournament',
            'groups',
            'participantLabels',
            'availableParticipants',
            'definitions',
            'candidateTournaments',
            'stagesByTournament',
        ));
    }

    public function storeGroup(Request $request, Tournament $tournament)
    {
        abort_unless(in_array($tournament->competition_type, ['doubles', 'team'], true), 422);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'division' => ['nullable', 'string', 'max:80'],
            'expected_member_count' => ['required', 'integer', 'min:2', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $expectedMemberCount = $tournament->competition_type === 'doubles'
            ? 2
            : (int) $data['expected_member_count'];

        if (! empty($data['code'])) {
            $duplicate = TournamentCompetitorGroup::query()
                ->where('tournament_id', $tournament->id)
                ->where('code', $data['code'])
                ->exists();
            if ($duplicate) {
                return back()->withErrors(['code' => 'この大会ですでに使用している編成コードです。'])->withInput();
            }
        }

        TournamentCompetitorGroup::query()->create([
            'tournament_id' => $tournament->id,
            'group_type' => $tournament->competition_type,
            'code' => $data['code'] ?: null,
            'name' => trim($data['name']),
            'division' => trim((string) ($data['division'] ?? '')) ?: null,
            'expected_member_count' => $expectedMemberCount,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => true,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ]);

        return back()->with('success', '編成を登録しました。');
    }

    public function destroyGroup(Tournament $tournament, TournamentCompetitorGroup $group)
    {
        $this->guardGroupTournament($tournament, $group);
        $group->delete();

        return back()->with('success', '編成を削除しました。過去の計算スナップショットは保持しています。');
    }

    public function storeGroupMember(
        Request $request,
        Tournament $tournament,
        TournamentCompetitorGroup $group,
    ) {
        $this->guardGroupTournament($tournament, $group);
        $data = $request->validate([
            'tournament_participant_id' => ['required', 'integer'],
        ]);

        $participantId = (int) $data['tournament_participant_id'];
        $belongsToTournament = DB::table('tournament_participants')
            ->where('id', $participantId)
            ->where('tournament_id', $tournament->id)
            ->exists();
        if (! $belongsToTournament) {
            abort(422, 'この大会の参加者ではありません。');
        }

        if ($group->members()->count() >= $group->expected_member_count) {
            return back()->withErrors(['tournament_participant_id' => 'この編成は定員に達しています。']);
        }

        if (TournamentCompetitorGroupMember::query()->where('tournament_participant_id', $participantId)->exists()) {
            return back()->withErrors(['tournament_participant_id' => 'この参加者はすでに別の編成へ登録済みです。']);
        }

        $group->members()->create([
            'tournament_participant_id' => $participantId,
            'member_order' => $group->members()->max('member_order') + 1,
        ]);

        return back()->with('success', '編成メンバーを追加しました。');
    }

    public function destroyGroupMember(
        Tournament $tournament,
        TournamentCompetitorGroup $group,
        TournamentCompetitorGroupMember $member,
    ) {
        $this->guardGroupTournament($tournament, $group);
        abort_unless((int) $member->competitor_group_id === (int) $group->id, 404);
        $member->delete();

        return back()->with('success', '編成メンバーを外しました。');
    }

    public function storeDefinition(Request $request, Tournament $tournament)
    {
        $data = $request->validate([
            'definition_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:40', 'alpha_dash:ascii'],
            'name' => ['required', 'string', 'max:255'],
            'subject_type' => ['required', Rule::in(['individual', 'group'])],
            'tie_break_policy' => ['required', Rule::in(['shared_rank', 'low_high'])],
            'gender' => ['nullable', Rule::in(['M', 'F', 'X'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $definition = ! empty($data['definition_id'])
            ? TournamentAggregateDefinition::query()
                ->where('tournament_id', $tournament->id)
                ->findOrFail((int) $data['definition_id'])
            : new TournamentAggregateDefinition(['tournament_id' => $tournament->id]);

        $duplicate = TournamentAggregateDefinition::query()
            ->where('tournament_id', $tournament->id)
            ->where('code', $data['code'])
            ->when($definition->exists, fn ($query) => $query->where('id', '<>', $definition->id))
            ->exists();
        if ($duplicate) {
            return back()->withErrors(['code' => 'この大会ですでに使用している合算コードです。'])->withInput();
        }

        if ($data['subject_type'] === 'group'
            && ! in_array($tournament->competition_type, ['doubles', 'team'], true)) {
            return back()->withErrors([
                'subject_type' => '編成合算は、競技単位がダブルスまたはチームの大会で登録してください。',
            ])->withInput();
        }

        $definition->fill([
            'code' => $data['code'],
            'name' => trim($data['name']),
            'subject_type' => $data['subject_type'],
            'tie_break_policy' => $data['tie_break_policy'],
            'gender' => ($data['gender'] ?? '') ?: null,
            'require_all_sources' => $request->boolean('require_all_sources'),
            'is_published' => $request->boolean('is_published'),
            'is_active' => true,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ]);
        $definition->tournament_id = $tournament->id;
        $definition->save();

        return back()->with('success', $definition->wasRecentlyCreated ? '合算定義を登録しました。' : '合算定義を更新しました。');
    }

    public function destroyDefinition(Tournament $tournament, TournamentAggregateDefinition $definition)
    {
        $this->guardDefinitionTournament($tournament, $definition);
        $definition->delete();

        return back()->with('success', '合算定義を削除しました。計算済みスナップショットは履歴として保持しています。');
    }

    public function storeSource(
        Request $request,
        Tournament $tournament,
        TournamentAggregateDefinition $definition,
    ) {
        $this->guardDefinitionTournament($tournament, $definition);
        $data = $request->validate([
            'source_tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
            'label' => ['required', 'string', 'max:255'],
            'stage' => ['nullable', 'string', 'max:255'],
            'game_from' => ['nullable', 'integer', 'min:1', 'required_with:game_to'],
            'game_to' => ['nullable', 'integer', 'min:1', 'gte:game_from', 'required_with:game_from'],
            'expected_games_per_member' => ['nullable', 'integer', 'min:1', 'max:999'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($definition->subject_type === 'group'
            && (int) $data['source_tournament_id'] !== (int) $tournament->id) {
            return back()->withErrors([
                'source_tournament_id' => 'チーム合算では、この大会自身を選択してください。',
            ])->withInput();
        }

        $expectedGames = $data['expected_games_per_member'] ?? null;
        if (! $expectedGames && ! empty($data['game_from']) && ! empty($data['game_to'])) {
            $expectedGames = (int) $data['game_to'] - (int) $data['game_from'] + 1;
        }

        $duplicateQuery = $definition->sources()
            ->where('source_tournament_id', $data['source_tournament_id']);
        $this->applyNullableWhere($duplicateQuery, 'stage', trim((string) ($data['stage'] ?? '')) ?: null);
        $this->applyNullableWhere($duplicateQuery, 'game_from', $data['game_from'] ?? null);
        $this->applyNullableWhere($duplicateQuery, 'game_to', $data['game_to'] ?? null);
        if ($duplicateQuery->exists()) {
            return back()->withErrors(['source_tournament_id' => '同じ集計範囲はすでに登録済みです。'])->withInput();
        }

        $definition->sources()->create([
            'source_tournament_id' => (int) $data['source_tournament_id'],
            'label' => trim($data['label']),
            'stage' => trim((string) ($data['stage'] ?? '')) ?: null,
            'game_from' => $data['game_from'] ?? null,
            'game_to' => $data['game_to'] ?? null,
            'expected_games_per_member' => $expectedGames,
            'is_required' => $request->boolean('is_required'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return back()->with('success', '合算元を追加しました。');
    }

    public function destroySource(
        Tournament $tournament,
        TournamentAggregateDefinition $definition,
        TournamentAggregateSource $source,
    ) {
        $this->guardDefinitionTournament($tournament, $definition);
        abort_unless((int) $source->aggregate_definition_id === (int) $definition->id, 404);
        $source->delete();

        return back()->with('success', '合算元を削除しました。');
    }

    public function calculate(
        Tournament $tournament,
        TournamentAggregateDefinition $definition,
        TournamentAggregateResultService $service,
    ) {
        $this->guardDefinitionTournament($tournament, $definition);

        try {
            $snapshot = $service->calculate($definition, Auth::id());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['aggregate' => $exception->getMessage()]);
        }

        return back()->with(
            'success',
            sprintf('%sを再計算しました（%d件）。', $definition->name, $snapshot->rows->count())
        );
    }

    private function guardGroupTournament(Tournament $tournament, TournamentCompetitorGroup $group): void
    {
        abort_unless((int) $group->tournament_id === (int) $tournament->id, 404);
    }

    private function guardDefinitionTournament(
        Tournament $tournament,
        TournamentAggregateDefinition $definition,
    ): void {
        abort_unless((int) $definition->tournament_id === (int) $tournament->id, 404);
    }

    private function applyNullableWhere($query, string $column, mixed $value): void
    {
        $value === null ? $query->whereNull($column) : $query->where($column, $value);
    }
}
