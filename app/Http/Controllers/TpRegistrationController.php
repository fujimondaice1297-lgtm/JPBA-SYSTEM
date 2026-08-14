<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\TrainingSession;
use App\Models\TrainingSessionParticipant;
use App\Services\TrainingComplianceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TpRegistrationController extends Controller
{
    public function index(Request $request): View
    {
        $year = max(2000, min(2100, $request->integer('year', now()->year)));
        $sessions = TrainingSession::query()
            ->withCount([
                'participants',
                'participants as attended_count' => fn ($query) => $query->where('attendance_status', TrainingSessionParticipant::STATUS_ATTENDED),
                'participants as absent_count' => fn ($query) => $query->where('attendance_status', TrainingSessionParticipant::STATUS_ABSENT),
            ])
            ->where('session_year', $year)
            ->orderBy('held_on')
            ->get();

        $sessionId = $request->integer('session');
        $selectedSession = $sessionId
            ? $sessions->firstWhere('id', $sessionId)
            : $sessions->first();

        if ($selectedSession) {
            $selectedSession->load([
                'training',
                'participants.bowler.userAccount',
                'participants.trainingRecord',
            ]);
        }

        return view('tp_registration.index', [
            'year' => $year,
            'availableYears' => TrainingSession::query()->pluck('session_year')
                ->push($year)->push(now()->year)->push(now()->addYear()->year)
                ->unique()->sortDesc()->values(),
            'sessions' => $sessions,
            'selectedSession' => $selectedSession,
            'statusCounts' => ProBowler::query()
                ->where('is_active', true)
                ->where('member_class', 'player')
                ->selectRaw('training_compliance_status, count(*) as total')
                ->groupBy('training_compliance_status')
                ->pluck('total', 'training_compliance_status'),
        ]);
    }

    public function storeSession(Request $request, TrainingComplianceService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'held_on' => ['required', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $session = TrainingSession::query()->create([
            ...$data,
            'training_id' => $service->mandatoryTraining()->id,
            'session_year' => (int) date('Y', strtotime($data['held_on'])),
            'status' => TrainingSession::STATUS_OPEN,
            'created_by_user_id' => auth()->id(),
            'updated_by_user_id' => auth()->id(),
        ]);

        return redirect()->route('tp_registration.index', [
            'year' => $session->session_year,
            'session' => $session->id,
        ])->with('success', '講習会を作成しました。受講予定者を登録してください。');
    }

    public function addParticipants(Request $request, TrainingSession $trainingSession): RedirectResponse
    {
        abort_if($trainingSession->status === TrainingSession::STATUS_COMPLETED, 422, '確定済み講習会には追加できません。');
        $data = $request->validate([
            'license_nos' => ['required', 'string', 'max:50000'],
        ]);

        $tokens = collect(preg_split('/[\s,、]+/u', strtoupper($data['license_nos'])) ?: [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->take(500)
            ->values();

        $numericTails = $tokens->filter(fn ($value) => ctype_digit($value))->map(fn ($value) => (int) $value)->values();
        $bowlers = ProBowler::query()
            ->whereIn(DB::raw('UPPER(license_no)'), $tokens->all())
            ->when($numericTails->isNotEmpty(), fn ($query) => $query->orWhereIn('license_no_num', $numericTails->all()))
            ->get()
            ->keyBy(fn (ProBowler $bowler) => strtoupper((string) $bowler->license_no));

        $added = 0;
        $notFound = [];
        foreach ($tokens as $token) {
            $bowler = $bowlers->get($token);
            if (!$bowler && ctype_digit($token)) {
                $bowler = $bowlers->first(fn (ProBowler $candidate) => (int) $candidate->license_no_num === (int) $token);
            }
            if (!$bowler) {
                $notFound[] = $token;
                continue;
            }

            $participant = TrainingSessionParticipant::query()->firstOrCreate([
                'training_session_id' => $trainingSession->id,
                'pro_bowler_id' => $bowler->id,
            ], ['attendance_status' => TrainingSessionParticipant::STATUS_REGISTERED]);
            $added += $participant->wasRecentlyCreated ? 1 : 0;
        }

        $message = $added.'名を受講予定者へ追加しました。';
        if ($notFound) {
            $message .= ' 見つからない番号：'.implode('、', array_slice($notFound, 0, 10));
        }

        return back()->with('success', $message);
    }

    public function updateParticipants(Request $request, TrainingSession $trainingSession): RedirectResponse
    {
        abort_if($trainingSession->status === TrainingSession::STATUS_COMPLETED, 422, '確定済み講習会は、先に確定解除してください。');
        $data = $request->validate([
            'participants' => ['required', 'array'],
            'participants.*.attendance_status' => [
                'required',
                Rule::in([
                    TrainingSessionParticipant::STATUS_REGISTERED,
                    TrainingSessionParticipant::STATUS_ATTENDED,
                    TrainingSessionParticipant::STATUS_ABSENT,
                    TrainingSessionParticipant::STATUS_EXEMPT,
                ]),
            ],
            'participants.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($trainingSession, $data): void {
            foreach ($data['participants'] as $participantId => $attributes) {
                $trainingSession->participants()->whereKey((int) $participantId)->update([
                    'attendance_status' => $attributes['attendance_status'],
                    'notes' => $attributes['notes'] ?? null,
                ]);
            }
            $trainingSession->update(['updated_by_user_id' => auth()->id()]);
        });

        return back()->with('success', '受講結果を保存しました。');
    }

    public function finalize(
        TrainingSession $trainingSession,
        TrainingComplianceService $service,
    ): RedirectResponse {
        $pending = $trainingSession->participants()
            ->where('attendance_status', TrainingSessionParticipant::STATUS_REGISTERED)
            ->count();
        if ($pending > 0) {
            return back()->withErrors(['attendance' => '「受講予定」が'.$pending.'名残っています。全員を受講済み・未受講・免除のいずれかへ変更してください。']);
        }

        $totals = $service->finalizeSession($trainingSession, (int) auth()->id());

        return back()->with('success', sprintf(
            '受講結果を確定しました。受講済み%d名、未受講%d名、免除%d名です。',
            $totals['attended'],
            $totals['absent'],
            $totals['exempt'],
        ));
    }

    public function reopen(TrainingSession $trainingSession): RedirectResponse
    {
        $trainingSession->update([
            'status' => TrainingSession::STATUS_OPEN,
            'finalized_at' => null,
            'finalized_by_user_id' => null,
            'updated_by_user_id' => auth()->id(),
        ]);

        return back()->with('success', '確定を解除しました。受講結果を修正後、もう一度確定してください。');
    }

    public function export(TrainingSession $trainingSession): StreamedResponse
    {
        $trainingSession->load('participants.bowler.userAccount', 'participants.trainingRecord');
        $filename = sprintf('tp_training_%s_%d.csv', $trainingSession->held_on?->format('Ymd'), $trainingSession->id);

        return response()->streamDownload(function () use ($trainingSession): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['ライセンスNo', '氏名', '受講結果', '受講日', '有効期限', 'メール', '備考']);
            foreach ($trainingSession->participants as $participant) {
                fputcsv($stream, [
                    $participant->bowler?->license_no,
                    $participant->bowler?->name_kanji,
                    $participant->attendance_status_label,
                    $participant->trainingRecord?->completed_at?->format('Y-m-d'),
                    $participant->trainingRecord?->expires_at?->format('Y-m-d'),
                    $participant->bowler?->userAccount?->email,
                    $participant->notes,
                ]);
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
