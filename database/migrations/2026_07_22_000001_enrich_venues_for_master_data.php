<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('canonical_key')->nullable()->unique();
            $table->json('aliases')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('source_url')->nullable();
            $table->date('source_checked_at')->nullable();
            $table->unsignedSmallInteger('first_hosted_year')->nullable();
            $table->unsignedSmallInteger('last_hosted_year')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropUnique(['canonical_key']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['last_hosted_year']);
            $table->dropColumn([
                'canonical_key',
                'aliases',
                'is_active',
                'source_url',
                'source_checked_at',
                'first_hosted_year',
                'last_hosted_year',
            ]);
        });
    }
};
