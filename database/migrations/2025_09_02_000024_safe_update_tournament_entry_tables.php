<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // tournament_entries に必要カラムを“無ければ”追加（NULL許可→後で締める想定）
        Schema::table('tournament_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('tournament_entries', 'tournament_id')) {
                $table->foreignId('tournament_id')->nullable()->after('id')
                      ->constrained('tournaments')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('tournament_entries', 'pro_bowler_id')) {
                $table->foreignId('pro_bowler_id')->nullable()->after('tournament_id')
                      ->constrained('pro_bowlers')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('tournament_entries', 'shift')) {
                $table->string('shift', 20)->nullable()->after('pro_bowler_id');
            }
            if (!Schema::hasColumn('tournament_entries', 'lane')) {
                $table->unsignedSmallInteger('lane')->nullable()->after('shift');
            }
            if (!Schema::hasColumn('tournament_entries', 'status')) {
                $table->string('status', 20)->default('confirmed')->after('lane');
            }
            if (!Schema::hasColumn('tournament_entries', 'checked_in_at')) {
                $table->timestamp('checked_in_at')->nullable()->after('status');
            }
        });

        // ユニーク & インデックス（存在しなければ）を追加
        // PostgresはIF NOT EXISTSがSchemaビルダーに無いので、既に同名があると落ちます。
        // 初回のみ追加される想定です。
        try {
            Schema::table('tournament_entries', function (Blueprint $table) {
                $table->unique(['tournament_id','pro_bowler_id'], 't_entries_unique_bowler_per_tournament');
                $table->index(['pro_bowler_id'], 't_entries_bowler_idx');
            });
        } catch (\Throwable $e) {
            // 既に存在する場合は無視
        }

        // tournament_entry_balls 側
        Schema::table('tournament_entry_balls', function (Blueprint $table) {
            if (!Schema::hasColumn('tournament_entry_balls', 'tournament_entry_id')) {
                $table->foreignId('tournament_entry_id')->nullable()->after('id')
                      ->constrained('tournament_entries')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('tournament_entry_balls', 'used_ball_id')) {
                $table->foreignId('used_ball_id')->nullable()->after('tournament_entry_id')
                      ->constrained('used_balls')->cascadeOnDelete();
            }
        });

        try {
            Schema::table('tournament_entry_balls', function (Blueprint $table) {
                $table->unique(['tournament_entry_id','used_ball_id'], 't_entry_balls_unique');
            });
        } catch (\Throwable $e) {
            // 既に存在する場合は無視
        }
    }

    public function down(): void
    {
        // 可能な範囲だけ解除（存在チェックしながら）
        try { Schema::table('tournament_entry_balls', function (Blueprint $t) {
            try { $t->dropUnique('t_entry_balls_unique'); } catch (\Throwable $e) {}
            try { $t->dropConstrainedForeignId('used_ball_id'); } catch (\Throwable $e) {}
            try { $t->dropConstrainedForeignId('tournament_entry_id'); } catch (\Throwable $e) {}
        }); } catch (\Throwable $e) {}

        try { Schema::table('tournament_entries', function (Blueprint $t) {
            try { $t->dropUnique('t_entries_unique_bowler_per_tournament'); } catch (\Throwable $e) {}
            try { $t->dropIndex('t_entries_bowler_idx'); } catch (\Throwable $e) {}
            try { $t->dropConstrainedForeignId('pro_bowler_id'); } catch (\Throwable $e) {}
            try { $t->dropConstrainedForeignId('tournament_id'); } catch (\Throwable $e) {}
            try { $t->dropColumn(['shift','lane','status','checked_in_at']); } catch (\Throwable $e) {}
        }); } catch (\Throwable $e) {}
    }
};
