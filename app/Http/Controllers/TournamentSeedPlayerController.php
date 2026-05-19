<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\Tournament;
use App\Models\TournamentSeedPlayer;
use App\Services\ProBowlerSeedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class TournamentSeedPlayerController extends Controller
{
    public function index(Tournament $tournament)
    {
        $seedPlayers = TournamentSeedPlayer::query()
            ->with([
                'bowler',
                'seedListPlayer',
                'rankingSnapshot',
                'sourceTournament',
                'title',
            ])
            ->where('tournament_id', $tournament->id)
            ->where('is_active', true)
            ->orderByRaw('priority_order is null')
            ->orderBy('priority_order')
            ->orderBy('id')
            ->get();

        return view('tournament_seed_players.index', [
            'tournament' => $tournament,
            'seedPlayers' => $seedPlayers,
            'seedSourceOptions' => $this->seedSourceOptions(),
        ]);
    }

    public function store(Request $request, Tournament $tournament, ProBowlerSeedService $seedService)
    {
        $validated = $request->validate([
            'license_no' => ['required', 'string', 'max:50'],
            'seed_source_type' => ['required', 'string', Rule::in(array_keys($this->seedSourceOptions()))],
            'priority_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'display_label' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $licenseNo = $this->normalizeLicenseNo($validated['license_no']);
        $bowler = $this->findBowlerByLicenseNo($licenseNo);

        $seedService->addTournamentSeed(
            tournament: $tournament,
            bowler: $bowler,
            seedSourceType: $validated['seed_source_type'],
            attributes: [
                'license_no' => $licenseNo,
                'priority_order' => $validated['priority_order'] ?? null,
                'display_label' => $validated['display_label'] ?? null,
                'note' => $validated['note'] ?? null,
                'is_active' => true,
            ]
        );

        return redirect()
            ->route('tournaments.seed_players.index', $tournament)
            ->with('success', '大会別シードを追加しました。');
    }

    public function destroy(Tournament $tournament, TournamentSeedPlayer $seedPlayer)
    {
        if ((int) $seedPlayer->tournament_id !== (int) $tournament->id) {
            abort(404);
        }

        $seedPlayer->update([
            'is_active' => false,
        ]);

        return redirect()
            ->route('tournaments.seed_players.index', $tournament)
            ->with('success', '大会別シードを解除しました。');
    }

    private function seedSourceOptions(): array
    {
        return [
            ProBowlerSeedService::SOURCE_PREVIOUS_YEAR_RANKING_TOP24 => '前年度ランキング上位24名',
            ProBowlerSeedService::SOURCE_CURRENT_YEAR_RANKING => '当該年度ランキング',
            ProBowlerSeedService::SOURCE_PERMANENT_SEED => '永久シード',
            ProBowlerSeedService::SOURCE_SEMI_PERMANENT_SEED => '準永久シード',
            ProBowlerSeedService::SOURCE_ALL_JAPAN_CHAMPION => '全日本選手権者シード',
            ProBowlerSeedService::SOURCE_CURRENT_YEAR_WINNER => '当該年度優勝者シード',
            ProBowlerSeedService::SOURCE_PREVIOUS_YEAR_WINNER => '前年度優勝者シード',
            ProBowlerSeedService::SOURCE_PAST_CHAMPION => '歴代優勝者',
            ProBowlerSeedService::SOURCE_MANUAL => '手動追加',
        ];
    }

    private function findBowlerByLicenseNo(string $licenseNo): ?ProBowler
    {
        $candidates = array_values(array_filter([
            $licenseNo,
            $this->last4($licenseNo),
        ]));

        $licenseColumns = collect(['license_no', 'license_number', 'pro_bowler_license_no'])
            ->filter(fn (string $column) => Schema::hasColumn('pro_bowlers', $column))
            ->values();

        if ($licenseColumns->isEmpty()) {
            return null;
        }

        return ProBowler::query()
            ->where(function ($query) use ($licenseColumns, $candidates) {
                foreach ($licenseColumns as $column) {
                    foreach ($candidates as $candidate) {
                        $query->orWhere($column, $candidate);
                        $query->orWhere($column, 'like', '%' . $candidate);
                    }
                }
            })
            ->orderBy('id')
            ->first();
    }

    private function normalizeLicenseNo(?string $licenseNo): string
    {
        return strtoupper(trim((string) $licenseNo));
    }

    private function last4(?string $licenseNo): ?string
    {
        $licenseNo = $this->normalizeLicenseNo($licenseNo);

        if ($licenseNo === '') {
            return null;
        }

        return mb_substr($licenseNo, -4);
    }
}
