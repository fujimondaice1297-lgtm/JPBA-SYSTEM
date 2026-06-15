<?php

namespace App\Http\Controllers;

use App\Models\ProBowler;
use App\Models\ProBowlerRankingRow;
use App\Models\ProBowlerRankingSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RankingController extends Controller
{
    public function index()
    {
        $snapshots = ProBowlerRankingSnapshot::query()
            ->where('ranking_type', 'points')
            ->where('ranking_scope', 'official_tournament')
            ->withCount('rows')
            ->orderByDesc('ranking_year')
            ->orderBy('gender')
            ->orderByDesc('as_of_date')
            ->get();

        return view('rankings.index', [
            'snapshots' => $snapshots,
            'genderLabels' => $this->genderLabels(),
            'officialRankingUrls' => $this->officialRankingUrls(),
            'defaultRankingYear' => 2025,
        ]);
    }

    public function storeOfficialRanking(Request $request)
    {
        $validated = $request->validate([
            'ranking_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'gender' => ['required', 'in:M,F'],
            'as_of_date' => ['nullable', 'date'],
            'source_url' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'ranking_text' => ['required', 'string'],
        ]);

        $rankingYear = (int) $validated['ranking_year'];
        $gender = $validated['gender'];
        $genderLabel = $this->genderLabels()[$gender] ?? $gender;
        $sourceUrl = trim((string) ($validated['source_url'] ?? ''));

        $parsedRows = $this->parseOfficialRankingRows(
            text: (string) $validated['ranking_text'],
            gender: $gender
        );

        if (count($parsedRows) === 0) {
            return back()
                ->withInput()
                ->withErrors([
                    'ranking_text' => 'ランキング行を読み取れませんでした。PDFから順位・ライセンスNo・氏名・ポイントが含まれる表部分をコピーして貼り付けてください。',
                ]);
        }

        $matchedCount = 0;
        $unmatchedRows = [];

        foreach ($parsedRows as $index => $row) {
            $bowler = $this->findBowlerByLicenseDigits($row['license_digits'], $gender);
            $parsedRows[$index]['pro_bowler_id'] = $bowler?->id;
            $parsedRows[$index]['license_no'] = $bowler?->license_no ?: $this->buildFullLicenseNo($row['license_digits'], $gender);
            $parsedRows[$index]['name_kana'] = $bowler?->name_kana;

            if ($bowler) {
                $matchedCount++;

                $parsedRows[$index]['name_kanji'] = $bowler->name_kanji ?: $row['name_kanji'];
                $parsedRows[$index]['kibetsu'] = $bowler->kibetsu ?? $row['kibetsu'];
                $parsedRows[$index]['organization_name'] = $bowler->organization_name
                    ?? $bowler->affiliation
                    ?? $bowler->belongs_to
                    ?? $row['organization_name'];
                $parsedRows[$index]['equipment_contract'] = $bowler->equipment_contract
                    ?? $bowler->contract_maker
                    ?? null;
            } else {
                $unmatchedRows[] = $row;
            }
        }

        $snapshot = null;

        DB::transaction(function () use ($rankingYear, $gender, $sourceUrl, $validated, $parsedRows, &$snapshot) {
            $snapshot = ProBowlerRankingSnapshot::query()->firstOrNew([
                'ranking_year' => $rankingYear,
                'gender' => $gender,
                'ranking_type' => 'points',
                'ranking_scope' => 'official_tournament',
                'is_final' => true,
            ]);

            $snapshot->fill([
                'as_of_date' => $validated['as_of_date'] ?: null,
                'source_url' => $sourceUrl !== '' ? $sourceUrl : null,
                'notes' => $validated['notes'] ?: '公式PDFから取り込んだ年度末最終ポイントランキング',
            ]);
            $snapshot->save();

            ProBowlerRankingRow::query()
                ->where('ranking_snapshot_id', $snapshot->id)
                ->delete();

            foreach ($parsedRows as $index => $row) {
                ProBowlerRankingRow::query()->create([
                    'ranking_snapshot_id' => $snapshot->id,
                    'ranking_rank' => $row['ranking_rank'],
                    'pro_bowler_id' => $row['pro_bowler_id'],
                    'license_no' => $row['license_no'],
                    'name_kanji' => $row['name_kanji'],
                    'name_kana' => $row['name_kana'] ?? null,
                    'kibetsu' => $row['kibetsu'],
                    'organization_name' => $row['organization_name'],
                    'equipment_contract' => $row['equipment_contract'] ?? null,
                    'points' => $row['points'],
                    'games' => $row['games'],
                    'total_pin' => $row['total_pin'],
                    'average' => $row['average'],
                    'prize_money' => $row['prize_money'],
                    'sort_order' => $index + 1,
                ]);
            }
        });

        return redirect()
            ->route('rankings.index')
            ->with('status', "{$rankingYear}年{$genderLabel}公式最終ポイントランキングを" . count($parsedRows) . "件取り込みました。")
            ->with('official_ranking_import_summary', [
                'snapshot_id' => $snapshot?->id,
                'ranking_year' => $rankingYear,
                'gender' => $gender,
                'row_count' => count($parsedRows),
                'matched_count' => $matchedCount,
                'unmatched_count' => count($unmatchedRows),
                'unmatched_rows' => array_slice($unmatchedRows, 0, 20),
            ]);
    }

    private function parseOfficialRankingRows(string $text, string $gender): array
    {
        $rows = [];
        $seenRanks = [];

        $text = str_replace(["\r\n", "\r"], "\n", $text);

        foreach (explode("\n", $text) as $line) {
            $line = $this->normalizeRankingLine($line);

            if ($line === '') {
                continue;
            }

            if (!preg_match('/^\s*(\d{1,4})\s+(\d{3,4})\s+(.+?)\s+(\d{1,3})\s+(.+?)\s+(\d{1,3})\s+(\d{1,3})\s+([0-9,]+)\s+([0-9]+\.[0-9]+)\s+([0-9,]+)\s+([0-9,]+)(?:\s+.*)?$/u', $line, $matches)) {
                continue;
            }

            $rank = (int) $matches[1];

            if ($rank <= 0 || isset($seenRanks[$rank])) {
                continue;
            }

            $licenseDigits = $this->normalizeLicenseDigits($matches[2]);

            if ($licenseDigits === '') {
                continue;
            }

            $rows[] = [
                'ranking_rank' => $rank,
                'license_digits' => $licenseDigits,
                'license_no' => $this->buildFullLicenseNo($licenseDigits, $gender),
                'pro_bowler_id' => null,
                'name_kanji' => trim($matches[3]),
                'name_kana' => null,
                'kibetsu' => (int) $matches[4],
                'organization_name' => trim($matches[5]),
                'equipment_contract' => null,
                'tournament_count' => (int) $matches[6],
                'games' => (int) $matches[7],
                'total_pin' => $this->toInt($matches[8]),
                'average' => (float) $matches[9],
                'points' => (float) str_replace(',', '', $matches[10]),
                'prize_money' => $this->toInt($matches[11]),
            ];

            $seenRanks[$rank] = true;
        }

        usort($rows, fn (array $a, array $b) => $a['ranking_rank'] <=> $b['ranking_rank']);

        return $rows;
    }

    private function normalizeRankingLine(string $line): string
    {
        $line = trim(str_replace('　', ' ', $line));
        $line = preg_replace('/[\t]+/u', ' ', $line) ?? $line;
        $line = preg_replace('/\s+/u', ' ', $line) ?? $line;

        return trim($line);
    }

    private function normalizeLicenseDigits(string $licenseNo): string
    {
        $licenseNo = strtoupper(trim($licenseNo));
        $licenseNo = preg_replace('/[^0-9]/', '', $licenseNo) ?? '';

        return ltrim($licenseNo, '0') === '' ? '' : mb_substr($licenseNo, -4);
    }

    private function buildFullLicenseNo(string $licenseDigits, string $gender): string
    {
        $prefix = $gender === 'F' ? 'F' : 'M';
        $digits = str_pad($this->normalizeLicenseDigits($licenseDigits), 8, '0', STR_PAD_LEFT);

        return $prefix . $digits;
    }

    private function findBowlerByLicenseDigits(string $licenseDigits, string $gender): ?ProBowler
    {
        $digits = $this->normalizeLicenseDigits($licenseDigits);

        if ($digits === '') {
            return null;
        }

        $fullLicenseNo = $this->buildFullLicenseNo($digits, $gender);
        $sexValues = $this->sexValuesForGender($gender);

        $query = ProBowler::query()
            ->where(function ($q) use ($digits, $fullLicenseNo) {
                $q->whereRaw('UPPER(license_no) = ?', [$fullLicenseNo])
                    ->orWhereRaw('RIGHT(license_no, 4) = ?', [$digits]);

                if (Schema::hasColumn('pro_bowlers', 'license_no_num') && ctype_digit($digits)) {
                    $q->orWhere('license_no_num', (int) ltrim($digits, '0'));
                }
            });

        if ($sexValues !== []) {
            $query->whereIn('sex', $sexValues);
        }

        return $query
            ->orderByRaw('CASE WHEN UPPER(license_no) = ? THEN 0 ELSE 1 END', [$fullLicenseNo])
            ->orderBy('id')
            ->first();
    }

    private function sexValuesForGender(string $gender): array
    {
        // pro_bowlers.sex は sexes.id を参照する数値カラム。
        // PostgreSQLでは bigint/int カラムへ 'F' などの文字列を渡すと
        // SQLSTATE[22P02] になるため、whereIn には数値だけを渡す。
        return match ($gender) {
            'M' => [1],
            'F' => [2],
            default => [],
        };
    }

    private function toInt(string $value): int
    {
        return (int) str_replace(',', '', $value);
    }

    private function genderLabels(): array
    {
        return [
            'M' => '男子',
            'F' => '女子',
        ];
    }

    private function officialRankingUrls(): array
    {
        return [
            'M' => 'https://www.jpba.or.jp/information/tournament/ranking/2025/M/M_PointRanking_251220.pdf',
            'F' => 'https://www.jpba.or.jp/information/tournament/ranking/2025/W/W_PointRanking_251213.pdf',
        ];
    }
}
