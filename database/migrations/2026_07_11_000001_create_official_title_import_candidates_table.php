<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_title_import_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pro_bowler_id')->nullable()->constrained('pro_bowlers')->nullOnDelete();
            $table->string('license_no')->nullable()->index();
            $table->unsignedInteger('license_no_num')->nullable()->index();
            $table->string('name_kanji')->nullable();
            $table->string('title_name');
            $table->string('title_category')->default('normal')->index();
            $table->unsignedSmallInteger('year')->nullable()->index();
            $table->date('won_date')->nullable()->index();
            $table->string('venue_name')->nullable();
            $table->text('source_url');
            $table->text('source_result_url')->nullable();
            $table->string('source_label')->nullable();
            $table->text('raw_text')->nullable();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->string('status')->default('candidate')->index();
            $table->text('error')->nullable();
            $table->string('candidate_hash')->unique();
            $table->foreignId('promoted_pro_bowler_title_id')->nullable()->constrained('pro_bowler_titles')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('pro_bowler_titles', function (Blueprint $table) {
            if (! Schema::hasColumn('pro_bowler_titles', 'source_url')) {
                $table->text('source_url')->nullable()->after('source');
            }
            if (! Schema::hasColumn('pro_bowler_titles', 'source_label')) {
                $table->string('source_label')->nullable()->after('source_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pro_bowler_titles', function (Blueprint $table) {
            if (Schema::hasColumn('pro_bowler_titles', 'source_label')) {
                $table->dropColumn('source_label');
            }
            if (Schema::hasColumn('pro_bowler_titles', 'source_url')) {
                $table->dropColumn('source_url');
            }
        });

        Schema::dropIfExists('official_title_import_candidates');
    }
};
