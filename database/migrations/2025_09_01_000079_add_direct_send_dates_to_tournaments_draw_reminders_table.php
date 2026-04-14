<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tournaments')) {
            return;
        }

        Schema::table('tournaments', function (Blueprint $table) {
            if (!Schema::hasColumn('tournaments', 'shift_auto_draw_reminder_enabled')) {
                $table->boolean('shift_auto_draw_reminder_enabled')
                    ->default(false)
                    ->after('auto_draw_reminder_pending_type');
            }

            if (!Schema::hasColumn('tournaments', 'shift_auto_draw_reminder_send_on')) {
                $table->date('shift_auto_draw_reminder_send_on')
                    ->nullable()
                    ->after('shift_auto_draw_reminder_enabled');
            }

            if (!Schema::hasColumn('tournaments', 'lane_auto_draw_reminder_enabled')) {
                $table->boolean('lane_auto_draw_reminder_enabled')
                    ->default(false)
                    ->after('shift_auto_draw_reminder_send_on');
            }

            if (!Schema::hasColumn('tournaments', 'lane_auto_draw_reminder_send_on')) {
                $table->date('lane_auto_draw_reminder_send_on')
                    ->nullable()
                    ->after('lane_auto_draw_reminder_enabled');
            }
        });

        $rows = DB::table('tournaments')
            ->select([
                'id',
                'use_shift_draw',
                'use_lane_draw',
                'shift_draw_close_at',
                'lane_draw_close_at',
                'auto_draw_reminder_enabled',
                'auto_draw_reminder_days_before',
                'auto_draw_reminder_pending_type',
            ])
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $daysBefore = max(0, (int) ($row->auto_draw_reminder_days_before ?? 7));
            $pendingType = (string) ($row->auto_draw_reminder_pending_type ?? 'either');
            $enabled = (bool) ($row->auto_draw_reminder_enabled ?? false);

            $updates = [
                'shift_auto_draw_reminder_enabled' => false,
                'shift_auto_draw_reminder_send_on' => null,
                'lane_auto_draw_reminder_enabled' => false,
                'lane_auto_draw_reminder_send_on' => null,
            ];

            if ($enabled) {
                if (
                    in_array($pendingType, ['shift', 'either'], true) &&
                    (bool) ($row->use_shift_draw ?? false) &&
                    !empty($row->shift_draw_close_at)
                ) {
                    $updates['shift_auto_draw_reminder_enabled'] = true;
                    $updates['shift_auto_draw_reminder_send_on'] = Carbon::parse($row->shift_draw_close_at)
                        ->startOfDay()
                        ->subDays($daysBefore)
                        ->toDateString();
                }

                if (
                    in_array($pendingType, ['lane', 'either'], true) &&
                    (bool) ($row->use_lane_draw ?? false) &&
                    !empty($row->lane_draw_close_at)
                ) {
                    $updates['lane_auto_draw_reminder_enabled'] = true;
                    $updates['lane_auto_draw_reminder_send_on'] = Carbon::parse($row->lane_draw_close_at)
                        ->startOfDay()
                        ->subDays($daysBefore)
                        ->toDateString();
                }
            }

            DB::table('tournaments')
                ->where('id', $row->id)
                ->update($updates);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('tournaments')) {
            return;
        }

        Schema::table('tournaments', function (Blueprint $table) {
            $dropColumns = [];

            foreach ([
                'shift_auto_draw_reminder_enabled',
                'shift_auto_draw_reminder_send_on',
                'lane_auto_draw_reminder_enabled',
                'lane_auto_draw_reminder_send_on',
            ] as $column) {
                if (Schema::hasColumn('tournaments', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};