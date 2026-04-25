<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('result_flow_type', 64)->default('legacy_standard')->after('title_category');
            $table->unsignedInteger('round_robin_qualifier_count')->nullable()->after('result_flow_type');
            $table->unsignedInteger('round_robin_win_bonus')->nullable()->after('round_robin_qualifier_count');
            $table->unsignedInteger('round_robin_tie_bonus')->nullable()->after('round_robin_win_bonus');
            $table->boolean('round_robin_position_round_enabled')->default(false)->after('round_robin_tie_bonus');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn([
                'result_flow_type',
                'round_robin_qualifier_count',
                'round_robin_win_bonus',
                'round_robin_tie_bonus',
                'round_robin_position_round_enabled',
            ]);
        });
    }
};
