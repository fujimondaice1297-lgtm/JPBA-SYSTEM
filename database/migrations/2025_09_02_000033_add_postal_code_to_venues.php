<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            if (!Schema::hasColumn('venues', 'postal_code')) {
                $table->string('postal_code', 8)->nullable()->after('address'); // 例: 101-0047
            }
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            if (Schema::hasColumn('venues', 'postal_code')) {
                $table->dropColumn('postal_code');
            }
        });
    }
};
