<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterStageSettingsAddEnabled extends Migration
{
    public function up()
    {
        Schema::table('stage_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('stage_settings', 'enabled')) {
                $table->boolean('enabled')->default(true)->after('total_games');
            }
        });
    }

    public function down()
    {
        Schema::table('stage_settings', function (Blueprint $table) {
            if (Schema::hasColumn('stage_settings', 'enabled')) {
                $table->dropColumn('enabled');
            }
        });
    }
}
