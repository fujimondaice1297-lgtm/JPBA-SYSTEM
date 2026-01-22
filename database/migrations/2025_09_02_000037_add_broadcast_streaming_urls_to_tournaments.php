<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            if (!Schema::hasColumn('tournaments','broadcast_url')) {
                $table->string('broadcast_url')->nullable()->after('broadcast');
            }
            if (!Schema::hasColumn('tournaments','streaming_url')) {
                $table->string('streaming_url')->nullable()->after('streaming');
            }
            if (!Schema::hasColumn('tournaments','previous_event_url')) {
                $table->string('previous_event_url')->nullable()->after('previous_event');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            if (Schema::hasColumn('tournaments','broadcast_url'))  $table->dropColumn('broadcast_url');
            if (Schema::hasColumn('tournaments','streaming_url'))  $table->dropColumn('streaming_url');
            if (Schema::hasColumn('tournaments','previous_event_url'))  $table->dropColumn('previous_event_url');
        });
    }
};
