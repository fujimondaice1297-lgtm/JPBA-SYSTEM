<?php

namespace App\Services;

use App\Models\ProBowler;
use App\Models\RecordCertificationSequence;
use App\Models\RecordType;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AchievementRecordService
{
    private const COUNT_COLUMNS = [
        'perfect' => 'perfect_count',
        'seven_ten' => 'seven_ten_count',
        'eight_hundred' => 'eight_hundred_count',
    ];

    public function confirm(RecordType $record, ?int $userId = null): RecordType
    {
        return DB::transaction(function () use ($record, $userId): RecordType {
            /** @var RecordType $locked */
            $locked = RecordType::query()->lockForUpdate()->findOrFail($record->id);
            if ($locked->status === RecordType::STATUS_CONFIRMED) {
                if (
                    $locked->registration_mode === RecordType::MODE_NEW
                    && $locked->count_applied_at === null
                ) {
                    $this->incrementAuthoritativeCount($locked);
                    $locked->forceFill(['count_applied_at' => now()])->save();
                } else {
                    AwardCounter::syncToProBowler((int) $locked->pro_bowler_id);
                }

                return $locked;
            }

            if (! in_array(
                $locked->status,
                [RecordType::STATUS_CANDIDATE, RecordType::STATUS_VOID],
                true
            )) {
                throw new InvalidArgumentException('却下済みの記録は確認済みにできません。');
            }

            $locked->gender = $locked->gender ?: $this->genderFromRecord($locked);
            if (! $locked->certification_number) {
                $this->allocateCertificationNumber($locked);
            } else {
                $this->syncSequenceAfterManualNumber($locked);
            }

            $locked->status = RecordType::STATUS_CONFIRMED;
            $locked->confirmed_at = now();
            $locked->confirmed_by = $userId;
            $locked->warning = null;
            $locked->save();

            if (
                $locked->registration_mode === RecordType::MODE_NEW
                && $locked->count_applied_at === null
            ) {
                $this->incrementAuthoritativeCount($locked);
                $locked->forceFill(['count_applied_at' => now()])->save();
            } else {
                AwardCounter::syncToProBowler((int) $locked->pro_bowler_id);
            }

            return $locked->fresh();
        });
    }

    public function reject(RecordType $record, ?string $reason = null): RecordType
    {
        if ($record->status === RecordType::STATUS_CONFIRMED) {
            throw new InvalidArgumentException('確認済み記録は自動で総数を減らさないため、却下できません。');
        }

        $record->update([
            'status' => RecordType::STATUS_REJECTED,
            'warning' => $reason,
        ]);

        return $record->fresh();
    }

    public function syncSequenceAfterManualNumber(RecordType $record): void
    {
        $number = $record->isDirty('certification_number')
            ? $this->numericCertificationNumber($record->certification_number)
            : (
                $record->certification_number_value
                ?: $this->numericCertificationNumber($record->certification_number)
            );
        $gender = $record->gender ?: $this->genderFromRecord($record);

        $record->certification_number_value = $number;
        if (! $number || ! $gender) {
            return;
        }

        $record->gender = $gender;

        $sequence = RecordCertificationSequence::query()
            ->where('record_type', $record->record_type)
            ->where('gender', $gender)
            ->lockForUpdate()
            ->first();

        if ($sequence && (int) $sequence->next_number <= $number) {
            $sequence->update(['next_number' => $number + 1]);
        }
    }

    private function allocateCertificationNumber(RecordType $record): void
    {
        $gender = $record->gender ?: $this->genderFromRecord($record);
        if (! $gender) {
            return;
        }

        /** @var RecordCertificationSequence|null $sequence */
        $sequence = RecordCertificationSequence::query()
            ->where('record_type', $record->record_type)
            ->where('gender', $gender)
            ->where('is_enabled', true)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            return;
        }

        $number = (int) $sequence->next_number;
        $record->certification_number_value = $number;
        $record->certification_number = ($sequence->prefix ?? '')
            . $number
            . ($sequence->suffix ?? '');
        $sequence->update(['next_number' => $number + 1]);
    }

    private function incrementAuthoritativeCount(RecordType $record): void
    {
        $column = self::COUNT_COLUMNS[$record->record_type] ?? null;
        if (! $column) {
            throw new InvalidArgumentException('対応していない公認記録種別です。');
        }

        /** @var ProBowler $bowler */
        $bowler = ProBowler::query()->lockForUpdate()->findOrFail($record->pro_bowler_id);
        $bowler->{$column} = (int) $bowler->{$column} + 1;
        $bowler->award_total_count = (int) $bowler->perfect_count
            + (int) $bowler->seven_ten_count
            + (int) $bowler->eight_hundred_count;
        $bowler->save();
    }

    private function genderFromRecord(RecordType $record): ?string
    {
        $licenseNo = (string) ($record->proBowler?->license_no ?? '');
        $prefix = strtoupper(substr($licenseNo, 0, 1));

        return in_array($prefix, ['M', 'F'], true) ? $prefix : null;
    }

    private function numericCertificationNumber(?string $value): ?int
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $value);

        return $digits === '' ? null : (int) $digits;
    }
}
