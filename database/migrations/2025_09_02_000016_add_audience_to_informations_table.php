<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // audience が無ければ追加
        if (!Schema::hasColumn('informations', 'audience')) {
            Schema::table('informations', function (Blueprint $table) {
                $table->enum('audience', ['public','members','district_leaders','needs_training'])
                      ->default('public')
                      ->after('is_public');
            });
        }

        // required_training_id が無ければ追加
        if (!Schema::hasColumn('informations', 'required_training_id')) {
            Schema::table('informations', function (Blueprint $table) {
                $table->unsignedBigInteger('required_training_id')->nullable()->after('audience');
                $table->index('required_training_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('informations', function (Blueprint $table) {
            if (Schema::hasColumn('informations', 'required_training_id')) {
                $table->dropIndex(['required_training_id']);
                $table->dropColumn('required_training_id');
            }
            if (Schema::hasColumn('informations', 'audience')) {
                $table->dropColumn('audience');
            }
        });
    }
};
