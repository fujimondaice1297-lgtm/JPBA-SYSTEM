<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // プロのライセンス番号を紐づけ（NULL許可、インデックス）
            if (!Schema::hasColumn('users', 'pro_bowler_license_no')) {
                $table->string('pro_bowler_license_no')->nullable()->index()->after('email');
            }
            // 管理者フラグ（なければ追加）
            if (!Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false)->after('password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'pro_bowler_license_no')) {
                $table->dropIndex(['pro_bowler_license_no']);
                $table->dropColumn('pro_bowler_license_no');
            }
            if (Schema::hasColumn('users', 'is_admin')) {
                $table->dropColumn('is_admin');
            }
        });
    }
};
