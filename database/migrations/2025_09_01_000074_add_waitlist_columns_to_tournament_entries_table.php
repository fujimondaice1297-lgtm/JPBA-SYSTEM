<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tournament_entries')) {
            return;
        }

        Schema::table('tournament_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('tournament_entries', 'waitlist_priority')) {
                $table->unsignedInteger('waitlist_priority')->nullable()->after('checked_in_at');
            }

            if (!Schema::hasColumn('tournament_entries', 'waitlisted_at')) {
                $table->timestamp('waitlisted_at')->nullable()->after('waitlist_priority');
            }

            if (!Schema::hasColumn('tournament_entries', 'waitlist_note')) {
                $table->text('waitlist_note')->nullable()->after('waitlisted_at');
            }

            if (!Schema::hasColumn('tournament_entries', 'promoted_from_waitlist_at')) {
                $table->timestamp('promoted_from_waitlist_at')->nullable()->after('waitlist_note');
            }
        });

        try {
            Schema::table('tournament_entries', function (Blueprint $table) {
                $table->index(['tournament_id', 'status'], 't_entries_tournament_status_idx');
            });
        } catch (\Throwable $e) {
            // 既存なら無視
        }

        try {
            Schema::table('tournament_entries', function (Blueprint $table) {
                $table->index(['tournament_id', 'status', 'waitlist_priority'], 't_entries_waitlist_idx');
            });
        } catch (\Throwable $e) {
            // 既存なら無視
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('tournament_entries')) {
            return;
        }

        try {
            Schema::table('tournament_entries', function (Blueprint $table) {
                try {
                    $table->dropIndex('t_entries_waitlist_idx');
                } catch (\Throwable $e) {
                }

                try {
                    $table->dropIndex('t_entries_tournament_status_idx');
                } catch (\Throwable $e) {
                }

                $dropColumns = [];
                foreach ([
                    'waitlist_priority',
                    'waitlisted_at',
                    'waitlist_note',
                    'promoted_from_waitlist_at',
                ] as $column) {
                    if (Schema::hasColumn('tournament_entries', $column)) {
                        $dropColumns[] = $column;
                    }
                }

                if (!empty($dropColumns)) {
                    $table->dropColumn($dropColumns);
                }
            });
        } catch (\Throwable $e) {
            // 既存差異があっても down で落ちにくくする
        }
    }
};