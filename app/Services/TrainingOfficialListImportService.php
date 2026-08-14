<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\TrainingOfficialList;
use App\Models\TrainingOfficialListEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrainingOfficialListImportService
{
    public const DEFAULT_DATASET = 'database/data/jpba_official_tp_training_25th_20260813.json';

    public function __construct(private readonly TrainingComplianceService $compliance) {}

    /** @return array<string, mixed> */
    public function preview(?string $datasetPath = null): array
    {
        $path = $this->resolveDatasetPath($datasetPath);
        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $this->validatePayload($payload);

        $entryRows = collect($payload['entries'])
            ->flatMap(function (array $licenseNumbers, string $gender): array {
                return collect($licenseNumbers)
                    ->values()
                    ->map(fn (int $licenseNumber, int $index): array => [
                        'gender' => $gender,
                        'license_no_num' => $licenseNumber,
                        'source_order' => $index + 1,
                    ])
                    ->all();
            })
            ->values();

        $numbers = $entryRows->pluck('license_no_num')->unique()->values();
        $bowlersByKey = ProBowler::query()
            ->whereIn('license_no_num', $numbers)
            ->get()
            ->filter(fn (ProBowler $bowler): bool => preg_match('/^[MF][0-9]{8}$/', (string) $bowler->license_no) === 1)
            ->groupBy(fn (ProBowler $bowler): string => substr((string) $bowler->license_no, 0, 1).'-'.(int) $bowler->license_no_num);

        $matches = $entryRows->map(function (array $row) use ($bowlersByKey): array {
            /** @var Collection<int, ProBowler> $candidates */
            $candidates = $bowlersByKey->get($row['gender'].'-'.$row['license_no_num'], collect());
            $bowler = $candidates->count() === 1 ? $candidates->first() : null;

            $matchStatus = match (true) {
                $candidates->isEmpty() => 'unmatched',
                $candidates->count() > 1 => 'ambiguous',
                ! $bowler->is_active => 'inactive',
                default => 'matched',
            };

            return $row + [
                'pro_bowler_id' => $bowler?->id,
                // 公開PDFの日本語氏名は文字抽出の信頼性を確保できないため、推測値で埋めない。
                'source_name' => null,
                'match_status' => $matchStatus,
                'notes' => $matchStatus === 'ambiguous'
                    ? $candidates->pluck('license_no')->implode(', ')
                    : null,
            ];
        });

        $matched = $matches->whereIn('match_status', ['matched', 'inactive'])->count();

        return [
            'path' => $path,
            'payload' => $payload,
            'matches' => $matches,
            'summary' => [
                'total' => $matches->count(),
                'male' => $matches->where('gender', 'M')->count(),
                'female' => $matches->where('gender', 'F')->count(),
                'matched' => $matched,
                'active' => $matches->where('match_status', 'matched')->count(),
                'inactive' => $matches->where('match_status', 'inactive')->count(),
                'unmatched' => $matches->where('match_status', 'unmatched')->count(),
                'ambiguous' => $matches->where('match_status', 'ambiguous')->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function import(?string $datasetPath = null, ?int $userId = null): array
    {
        $preview = $this->preview($datasetPath);
        $payload = $preview['payload'];
        $summary = $preview['summary'];
        $allowUnmatched = (bool) ($payload['allow_unmatched'] ?? false);

        if (! $allowUnmatched && ($summary['unmatched'] > 0 || $summary['ambiguous'] > 0)) {
            throw ValidationException::withMessages([
                'dataset' => sprintf(
                    '公式一覧に未照合%d名・重複候補%d名があります。誤登録防止のため取り込みを中止しました。',
                    $summary['unmatched'],
                    $summary['ambiguous'],
                ),
            ]);
        }

        $existing = TrainingOfficialList::query()
            ->where('source_sha256', $payload['source_sha256'])
            ->first();
        if ($existing) {
            return $preview + ['official_list' => $existing, 'created' => false, 'synced_bowlers' => 0];
        }

        $training = $this->compliance->mandatoryTraining();
        $officialList = DB::transaction(function () use ($training, $payload, $summary, $preview, $userId): TrainingOfficialList {
            $isCurrent = (bool) ($payload['is_current'] ?? true);
            if ($isCurrent) {
                TrainingOfficialList::query()
                    ->where('training_id', $training->id)
                    ->where('is_current', true)
                    ->update(['is_current' => false, 'updated_at' => now()]);
            }

            $list = TrainingOfficialList::query()->create([
                'training_id' => $training->id,
                'edition_number' => $payload['edition_number'],
                'title' => $payload['title'],
                'valid_from' => $payload['valid_from'],
                'valid_through' => $payload['valid_through'],
                'source_page_url' => $payload['source_page_url'] ?? null,
                'source_url' => $payload['source_url'],
                'source_published_at' => Carbon::parse($payload['source_published_at']),
                'source_sha256' => $payload['source_sha256'],
                'is_current' => $isCurrent,
                'sync_status' => 'imported',
                'total_count' => $summary['total'],
                'male_count' => $summary['male'],
                'female_count' => $summary['female'],
                'matched_count' => $summary['matched'],
                'unmatched_count' => $summary['unmatched'] + $summary['ambiguous'],
                'inactive_count' => $summary['inactive'],
                'imported_at' => now(),
                'imported_by_user_id' => $userId,
                'notes' => $payload['notes'] ?? null,
            ]);

            $now = now();
            $preview['matches']->chunk(250)->each(function (Collection $chunk) use ($list, $now): void {
                TrainingOfficialListEntry::query()->insert($chunk->map(fn (array $row): array => [
                    'training_official_list_id' => $list->id,
                    'pro_bowler_id' => $row['pro_bowler_id'],
                    'gender' => $row['gender'],
                    'license_no_num' => $row['license_no_num'],
                    'source_order' => $row['source_order'],
                    'source_name' => $row['source_name'],
                    'match_status' => $row['match_status'],
                    'notes' => $row['notes'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

            return $list;
        });

        $syncedBowlers = 0;
        ProBowler::query()
            ->whereIn('id', $preview['matches']->where('match_status', 'matched')->pluck('pro_bowler_id'))
            ->orderBy('id')
            ->chunkById(100, function ($bowlers) use (&$syncedBowlers): void {
                foreach ($bowlers as $bowler) {
                    $this->compliance->syncBowler($bowler);
                    $syncedBowlers++;
                }
            });

        return $preview + [
            'official_list' => $officialList->fresh(),
            'created' => true,
            'synced_bowlers' => $syncedBowlers,
        ];
    }

    private function resolveDatasetPath(?string $datasetPath): string
    {
        $path = $datasetPath ?: base_path(self::DEFAULT_DATASET);
        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            $path = base_path($path);
        }
        if (! is_file($path) || ! is_readable($path)) {
            throw ValidationException::withMessages(['dataset' => "データセットを読み込めません: {$path}"]);
        }

        return $path;
    }

    /** @param array<string, mixed> $payload */
    private function validatePayload(array $payload): void
    {
        foreach (['title', 'edition_number', 'valid_from', 'valid_through', 'source_url', 'source_published_at', 'source_sha256', 'entries'] as $key) {
            if (! array_key_exists($key, $payload)) {
                throw ValidationException::withMessages(['dataset' => "必須項目 {$key} がありません。"]);
            }
        }
        if (! preg_match('/^[a-f0-9]{64}$/i', (string) $payload['source_sha256'])) {
            throw ValidationException::withMessages(['dataset' => 'source_sha256 が不正です。']);
        }
        foreach (['M', 'F'] as $gender) {
            $numbers = $payload['entries'][$gender] ?? null;
            if (! is_array($numbers) || $numbers === [] || count($numbers) !== count(array_unique($numbers))) {
                throw ValidationException::withMessages(['dataset' => "{$gender} の番号一覧が空、または重複しています。"]);
            }
            foreach ($numbers as $number) {
                if (! is_int($number) || $number <= 0) {
                    throw ValidationException::withMessages(['dataset' => "{$gender} のライセンス番号が不正です。"]);
                }
            }
        }
        if (Carbon::parse($payload['valid_from'])->gt(Carbon::parse($payload['valid_through']))) {
            throw ValidationException::withMessages(['dataset' => '有効期間の開始日と終了日が逆転しています。']);
        }
    }
}
