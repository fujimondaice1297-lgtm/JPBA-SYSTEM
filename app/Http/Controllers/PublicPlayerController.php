<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\ProBowler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PublicPlayerController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'name' => trim((string) $request->query('name', '')),
            'license_from' => trim((string) $request->query('license_from', '')),
            'license_to' => trim((string) $request->query('license_to', '')),
            'gender' => trim((string) $request->query('gender', '')),
            'district_id' => trim((string) $request->query('district_id', '')),
            'retired' => $request->boolean('retired'),
        ];

        $retiredNames = $this->retiredMembershipNames();

        $query = ProBowler::query()
            ->with('district')
            ->where('is_visible', true);

        if ($filters['retired']) {
            $query->where(function ($q) use ($retiredNames) {
                $q->where('is_active', false);

                if (!empty($retiredNames)) {
                    $q->orWhereIn('membership_type', $retiredNames);
                }
            });
        } else {
            $query->where('is_active', true);

            if (!empty($retiredNames)) {
                $query->where(function ($q) use ($retiredNames) {
                    $q->whereNull('membership_type')
                        ->orWhereNotIn('membership_type', $retiredNames);
                });
            }
        }

        if ($filters['name'] !== '') {
            $name = $filters['name'];
            $query->where(function ($q) use ($name) {
                $q->where('name_kanji', 'like', "%{$name}%")
                    ->orWhere('name_kana', 'like', "%{$name}%");
            });
        }

        $this->applyLicenseFilter(
            $query,
            $filters['license_from'],
            $filters['license_to']
        );

        if (in_array($filters['gender'], ['男性', '女性'], true)) {
            $query->where('sex', $filters['gender'] === '男性' ? 1 : 2);
        }

        if ($filters['district_id'] !== '' && ctype_digit($filters['district_id'])) {
            $query->where('district_id', (int) $filters['district_id']);
        }

        $bowlers = $query
            ->orderByRaw('license_no_num asc nulls last')
            ->orderBy('license_no')
            ->paginate(30)
            ->withQueryString();

        return view('public.players.index', [
            'publicConfig' => config('jpba_public', []),
            'filters' => $filters,
            'districts' => $this->orderedDistricts(),
            'bowlers' => $bowlers,
        ]);
    }

    private function applyLicenseFilter($query, string $from, string $to): void
    {
        if ($from === '' && $to === '') {
            return;
        }

        $raw = strtoupper($from . $to);
        $hasAlphabet = preg_match('/[A-Z]/', $raw) === 1;

        if ($hasAlphabet) {
            $values = collect([$from, $to])
                ->map(fn ($value) => strtoupper(preg_replace('/\s+/', '', trim((string) $value))))
                ->filter()
                ->unique()
                ->values();

            $query->where(function ($q) use ($values) {
                foreach ($values as $value) {
                    $q->orWhereRaw('upper(license_no) = ?', [$value])
                        ->orWhereRaw('upper(license_no) like ?', [$value . '%']);
                }
            });

            return;
        }

        $fromDigits = preg_replace('/[^0-9]/', '', $from);
        $toDigits = preg_replace('/[^0-9]/', '', $to);

        if ($fromDigits !== '') {
            $query->where('license_no_num', '>=', (int) $fromDigits);
        }

        if ($toDigits !== '') {
            $query->where('license_no_num', '<=', (int) $toDigits);
        }
    }

    private function orderedDistricts()
    {
        $order = [
            '北海道', '東北', '北関東', '埼玉', '千葉', '城東', '城南', '城西', '三多摩',
            '神奈川・東', '神奈川東', '神奈川・西', '神奈川西', '静岡', '甲信越', '東海', '北陸',
            '関西・東', '関西東', '関西・西', '関西西', '関西・南', '関西南', '中国四国',
            '九州・北', '九州北', '九州･南／沖縄', '九州南', '海外',
        ];

        return District::query()
            ->get()
            ->sortBy(function (District $district) use ($order) {
                $index = array_search($district->label, $order, true);

                return $index === false ? 999 : $index;
            })
            ->values();
    }

    private function retiredMembershipNames(): array
    {
        if (!Schema::hasTable('kaiin_status')) {
            return ['死亡', '除名', '退会届', '退会員'];
        }

        $names = DB::table('kaiin_status')
            ->where('is_retired', true)
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        return !empty($names) ? $names : ['死亡', '除名', '退会届', '退会員'];
    }
}
