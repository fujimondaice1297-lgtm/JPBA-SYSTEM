<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pro_bowlers', function (Blueprint $table) {
            if (Schema::hasColumn('pro_bowlers', 'official_profile_synced_at')
                && ! Schema::hasColumn('pro_bowlers', 'official_profile_imported_at')) {
                $table->renameColumn('official_profile_synced_at', 'official_profile_imported_at');
            }

            if (Schema::hasColumn('pro_bowlers', 'official_profile_sync_error')
                && ! Schema::hasColumn('pro_bowlers', 'official_profile_import_error')) {
                $table->renameColumn('official_profile_sync_error', 'official_profile_import_error');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pro_bowlers', function (Blueprint $table) {
            if (Schema::hasColumn('pro_bowlers', 'official_profile_imported_at')
                && ! Schema::hasColumn('pro_bowlers', 'official_profile_synced_at')) {
                $table->renameColumn('official_profile_imported_at', 'official_profile_synced_at');
            }

            if (Schema::hasColumn('pro_bowlers', 'official_profile_import_error')
                && ! Schema::hasColumn('pro_bowlers', 'official_profile_sync_error')) {
                $table->renameColumn('official_profile_import_error', 'official_profile_sync_error');
            }
        });
    }
};
