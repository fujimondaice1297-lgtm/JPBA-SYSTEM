<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('pro_bowlers', function (Blueprint $table) {
        if (!Schema::hasColumn('pro_bowlers', 'perfect_count')) {
            $table->unsignedInteger('perfect_count')->default(0);
        }
        if (!Schema::hasColumn('pro_bowlers', 'seven_ten_count')) {
            $table->unsignedInteger('seven_ten_count')->default(0);
        }
        if (!Schema::hasColumn('pro_bowlers', 'eight_hundred_count')) {
            $table->unsignedInteger('eight_hundred_count')->default(0);
        }
        if (!Schema::hasColumn('pro_bowlers', 'award_total_count')) {
            $table->unsignedInteger('award_total_count')->default(0);
        }
    });
}

public function down(): void
{
    Schema::table('pro_bowlers', function (Blueprint $table) {
        if (Schema::hasColumn('pro_bowlers', 'perfect_count')) {
            $table->dropColumn('perfect_count');
        }
        if (Schema::hasColumn('pro_bowlers', 'seven_ten_count')) {
            $table->dropColumn('seven_ten_count');
        }
        if (Schema::hasColumn('pro_bowlers', 'eight_hundred_count')) {
            $table->dropColumn('eight_hundred_count');
        }
        if (Schema::hasColumn('pro_bowlers', 'award_total_count')) {
            $table->dropColumn('award_total_count');
        }
    });
}

};
