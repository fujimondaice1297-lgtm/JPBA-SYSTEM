<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users','pro_bowler_id')) {
                $t->foreignId('pro_bowler_id')->nullable()
                  ->unique()->constrained('pro_bowlers')->nullOnDelete();
            }
            if (!Schema::hasColumn('users','license_no')) {
                $t->string('license_no')->nullable()->unique();
            }
            if (!Schema::hasColumn('users','role')) {
                $t->string('role')->default('member');
            }
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users','pro_bowler_id')) $t->dropConstrainedForeignId('pro_bowler_id');
            if (Schema::hasColumn('users','license_no'))    $t->dropColumn('license_no');
            if (Schema::hasColumn('users','role'))          $t->dropColumn('role');
        });
    }
};

