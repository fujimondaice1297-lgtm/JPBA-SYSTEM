<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            if (!Schema::hasColumn('tournaments', 'spectator_policy')) {
                // 可(有料)=paid / 可(無料)=free / 不可=none
                $table->string('spectator_policy', 16)->nullable()->after('audience');
            }
            if (!Schema::hasColumn('tournaments', 'admission_fee')) {
                $table->text('admission_fee')->nullable()->after('spectator_policy');
            }
            if (!Schema::hasColumn('tournaments', 'hero_image_path')) {
                $table->string('hero_image_path')->nullable()->after('image_path');
            }
            if (!Schema::hasColumn('tournaments', 'poster_images')) {
                $table->json('poster_images')->nullable()->after('hero_image_path');
            }
            if (!Schema::hasColumn('tournaments', 'extra_venues')) {
                // 追加会場：[{venue_id,name,address,tel,fax,website_url,memo}]
                $table->json('extra_venues')->nullable()->after('venue_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            if (Schema::hasColumn('tournaments', 'spectator_policy')) $table->dropColumn('spectator_policy');
            if (Schema::hasColumn('tournaments', 'admission_fee'))   $table->dropColumn('admission_fee');
            if (Schema::hasColumn('tournaments', 'hero_image_path')) $table->dropColumn('hero_image_path');
            if (Schema::hasColumn('tournaments', 'poster_images'))   $table->dropColumn('poster_images');
            if (Schema::hasColumn('tournaments', 'extra_venues'))    $table->dropColumn('extra_venues');
        });
    }
};
