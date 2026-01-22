<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            if (!Schema::hasColumn('tournaments', 'title_category')) {
                // タイトル区分：normal / season_trial / excluded
                $table->string('title_category', 32)->default('normal')->after('official_type');
            }
            if (!Schema::hasColumn('tournaments', 'venue_id')) {
                $table->unsignedBigInteger('venue_id')->nullable()->after('end_date');
                $table->foreign('venue_id')->references('id')->on('venues')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            if (Schema::hasColumn('tournaments', 'venue_id')) {
                $table->dropForeign(['venue_id']);
                $table->dropColumn('venue_id');
            }
            if (Schema::hasColumn('tournaments', 'title_category')) {
                $table->dropColumn('title_category');
            }
        });
    }
};
