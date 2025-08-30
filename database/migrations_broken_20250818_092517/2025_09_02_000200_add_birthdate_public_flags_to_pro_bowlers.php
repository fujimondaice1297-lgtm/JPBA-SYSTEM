<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('pro_bowlers', function (Blueprint $table) {
            $table->boolean('birthdate_public_hide_year')->default(false)->after('birthdate_public');
            $table->boolean('birthdate_public_is_private')->default(false)->after('birthdate_public_hide_year');
        });
    }
    public function down(): void {
        Schema::table('pro_bowlers', function (Blueprint $table) {
            $table->dropColumn(['birthdate_public_hide_year','birthdate_public_is_private']);
        });
    }
};

