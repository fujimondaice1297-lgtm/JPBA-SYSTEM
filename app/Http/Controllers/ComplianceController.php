<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\TrainingComplianceNotification;
use App\Models\TrainingOfficialList;
use App\Services\TrainingComplianceService;
use App\Services\TrainingExpiryNotificationService;
use App\Services\TrainingOfficialListImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplianceController extends Controller
{
    public function index(Request $request, TrainingComplianceService $compliance): View
    {
        $status = trim((string) $request->query('status', 'action_required'));
        $keyword = trim((string) $request->query('q', ''));

        $query = ProBowler::query()
            ->where('is_active', true)
            ->where('member_class', 'player')
            ->with(['latestMandatoryTraining', 'userAccount']);

        if ($status === 'action_required') {
            $query->whereIn('training_compliance_status', ['missing', 'expired', 'expiring_this_year', 'expiring_next_year']);
        } elseif ($status !== 'all') {
            $query->where('training_compliance_status', $status);
        }

        if ($keyword !== '') {
            $query->where(function ($nested) use ($keyword): void {
                $nested->where('license_no', 'like', "%{$keyword}%")
                    ->orWhere('name_kanji', 'like', "%{$keyword}%")
                    ->orWhere('name_kana', 'like', "%{$keyword}%");
            });
        }

        $bowlers = $query->orderBy('license_no')->paginate(50)->withQueryString();
        $evidenceByBowler = collect($bowlers->items())->mapWithKeys(
            fn (ProBowler $bowler): array => [$bowler->id => $compliance->statusAt($bowler)]
        );

        return view('compliance.index', [
            'bowlers' => $bowlers,
            'evidenceByBowler' => $evidenceByBowler,
            'status' => $status,
            'keyword' => $keyword,
            'latestOfficialList' => TrainingOfficialList::query()
                ->where('is_current', true)
                ->latest('source_published_at')
                ->first(),
            'statusCounts' => ProBowler::query()
                ->where('is_active', true)
                ->where('member_class', 'player')
                ->selectRaw('training_compliance_status, count(*) as total')
                ->groupBy('training_compliance_status')
                ->pluck('total', 'training_compliance_status'),
            'notificationCounts' => TrainingComplianceNotification::query()
                ->where('notice_year', now()->year)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function syncOfficialList(TrainingOfficialListImportService $service): RedirectResponse
    {
        $result = $service->import(userId: (int) auth()->id());
        $summary = $result['summary'];

        if (! $result['created']) {
            return back()->with('success', '同じ公式修了者一覧は取り込み済みです。重複登録は行いませんでした。');
        }

        return back()->with('success', sprintf(
            '公式修了者一覧を登録しました。掲載%d名／照合%d名／有効会員%d名／非アクティブ%d名。',
            $summary['total'],
            $summary['matched'],
            $summary['active'],
            $summary['inactive'],
        ));
    }

    public function reconcile(TrainingComplianceService $service): RedirectResponse
    {
        $count = 0;
        ProBowler::query()
            ->where('is_active', true)
            ->where('member_class', 'player')
            ->orderBy('id')
            ->chunkById(100, function ($bowlers) use ($service, &$count): void {
                foreach ($bowlers as $bowler) {
                    $service->syncBowler($bowler);
                    $count++;
                }
            });

        app(\App\Services\ProBowlerMembershipClassificationService::class)->syncAll((int) now()->year);

        return back()->with('success', $count.'名の講習状態と会員種別を現在の履歴で再判定しました。');
    }

    public function notify(Request $request, TrainingExpiryNotificationService $service): RedirectResponse
    {
        $data = $request->validate([
            'expiry_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'bowler_ids' => ['nullable', 'array', 'max:500'],
            'bowler_ids.*' => ['integer', 'exists:pro_bowlers,id'],
        ]);

        $summary = $service->sendForExpiryYear(
            (int) $data['expiry_year'],
            $data['bowler_ids'] ?? null,
            (int) auth()->id(),
        );

        return back()->with('success', sprintf(
            '更新案内：対象%d名／送信%d名／送信済み・宛先なし%d名／失敗%d名',
            $summary['candidates'],
            $summary['sent'],
            $summary['skipped'],
            $summary['failed'],
        ));
    }

    public function export(Request $request, TrainingComplianceService $compliance): StreamedResponse
    {
        $status = trim((string) $request->query('status', 'all'));
        $query = ProBowler::query()
            ->where('is_active', true)
            ->where('member_class', 'player')
            ->with(['latestMandatoryTraining', 'userAccount'])
            ->orderBy('license_no');
        if ($status !== 'all') {
            $query->where('training_compliance_status', $status);
        }
        $bowlers = $query->get();

        return response()->streamDownload(function () use ($bowlers, $compliance): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['ライセンスNo', '氏名', '受講状態', '最終受講日', '有効期限', '確認根拠', 'メール']);
            foreach ($bowlers as $bowler) {
                $evidence = $compliance->statusAt($bowler);
                $officialList = $evidence['official_evidence']?->officialList;
                fputcsv($stream, [
                    $bowler->license_no,
                    $bowler->name_kanji,
                    $bowler->training_compliance_status,
                    $evidence['completed_at']?->format('Y-m-d'),
                    $evidence['expires_at']?->format('Y-m-d'),
                    $officialList?->title ?: ($evidence['record'] ? '個別受講記録' : ''),
                    $bowler->userAccount?->email ?: $bowler->email,
                ]);
            }
            fclose($stream);
        }, 'tp_training_compliance_'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
