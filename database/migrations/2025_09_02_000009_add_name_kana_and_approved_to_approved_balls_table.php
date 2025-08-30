<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // すでに列があれば何もしない
        if (!Schema::hasColumn('approved_balls', 'name_kana')) {
            Schema::table('approved_balls', function (Blueprint $table) {
                $table->string('name_kana')->nullable();
            });
        }
        if (!Schema::hasColumn('approved_balls', 'approved')) {
            Schema::table('approved_balls', function (Blueprint $table) {
                $table->boolean('approved')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('approved_balls', 'name_kana')) {
            Schema::table('approved_balls', function (Blueprint $table) {
                $table->dropColumn('name_kana');
            });
        }
        if (Schema::hasColumn('approved_balls', 'approved')) {
            Schema::table('approved_balls', function (Blueprint $table) {
                $table->dropColumn('approved');
            });
        }
    }
};
