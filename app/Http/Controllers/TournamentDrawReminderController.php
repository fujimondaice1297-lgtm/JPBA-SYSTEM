<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\TournamentDrawReminderService;
use Illuminate\Http\Request;

class TournamentDrawReminderController extends Controller
{
    public function __construct(private TournamentDrawReminderService $service)
    {
    }

    public function create(Request $request, Tournament $tournament)
    {
        $pendingType = (string) $request->input('pending_type', 'either');
        $entries = $this->service->pendingEntriesQuery($tournament, $pendingType)
            ->with('bowler')
            ->orderBy('id')
            ->get();

        $mailReadyEntries = $entries->filter(function ($entry) {
            return filled($entry->bowler?->email);
        })->values();

        $defaults = $this->service->defaultTemplate($tournament, $pendingType);

        return view('tournament_entries.reminder_form', compact(
            'tournament',
            'pendingType',
            'entries',
            'mailReadyEntries',
            'defaults'
        ));
    }

    public function store(Request $request, Tournament $tournament)
    {
        $data = $request->validate([
            'pending_type' => ['required', 'in:shift,lane,either'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'from_address' => ['nullable', 'email'],
            'from_name' => ['nullable', 'string', 'max:100'],
            'dry_run_to' => ['nullable', 'email'],
        ]);

        $result = $this->service->sendManual(
            $tournament,
            (string) $data['pending_type'],
            (string) $data['subject'],
            (string) $data['body'],
            $data['from_address'] ?? null,
            $data['from_name'] ?? null,
            $data['dry_run_to'] ?? null,
        );

        if (($result['entry_count'] ?? 0) === 0) {
            return back()->withErrors('対象の未抽選選手（メールアドレスあり）がいません。')->withInput();
        }

        if (!empty($result['dry_run'])) {
            return back()->with('success', 'テストメールを送信しました。');
        }

        return redirect()
            ->route('tournaments.draws.index', $tournament->id)
            ->with('success', '未抽選DM送信完了：成功 ' . $result['sent'] . ' 件 / 失敗 ' . $result['failed'] . ' 件');
    }
}