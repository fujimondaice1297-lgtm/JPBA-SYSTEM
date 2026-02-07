<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pro_bowlers', function (Blueprint $table) {
            // district_id は NULL を許容しているので、FK は nullOnDelete が安全
            $table->index('district_id');

            $table->foreign('district_id')
                ->references('id')
                ->on('districts')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pro_bowlers', function (Blueprint $table) {
            $table->dropForeign(['district_id']);
            $table->dropIndex(['district_id']);
        });
    }
};
