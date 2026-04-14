<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tournaments')) {
            return;
        }

        Schema::table('tournaments', function (Blueprint $table) {
            if (!Schema::hasColumn('tournaments', 'auto_draw_reminder_enabled')) {
                $table->boolean('auto_draw_reminder_enabled')
                    ->default(false)
                    ->after('accept_shift_preference');
            }

            if (!Schema::hasColumn('tournaments', 'auto_draw_reminder_days_before')) {
                $table->unsignedSmallInteger('auto_draw_reminder_days_before')
                    ->default(7)
                    ->after('auto_draw_reminder_enabled');
            }

            if (!Schema::hasColumn('tournaments', 'auto_draw_reminder_pending_type')) {
                $table->string('auto_draw_reminder_pending_type', 10)
                    ->default('either')
                    ->after('auto_draw_reminder_days_before');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tournaments')) {
            return;
        }

        Schema::table('tournaments', function (Blueprint $table) {
            $dropColumns = [];

            foreach ([
                'auto_draw_reminder_enabled',
                'auto_draw_reminder_days_before',
                'auto_draw_reminder_pending_type',
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