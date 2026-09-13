<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsors', function (Blueprint $table): void {
            $table->string('alt_text')->nullable()->after('logo_path');
            $table->boolean('is_published')->default(true)->after('description');
            $table->timestamp('starts_at')->nullable()->after('is_published');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
            $table->unsignedSmallInteger('sort_order')->default(100)->after('ends_at');
            $table->foreignId('created_by_user_id')->nullable()->after('sort_order')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->after('created_by_user_id')
                ->constrained('users')->nullOnDelete();

            $table->index(['is_published', 'sort_order'], 'sponsors_public_sort_index');
            $table->index(['starts_at', 'ends_at'], 'sponsors_public_period_index');
        });
    }

    public function down(): void
    {
        Schema::table('sponsors', function (Blueprint $table): void {
            $table->dropIndex('sponsors_public_sort_index');
            $table->dropIndex('sponsors_public_period_index');
            $table->dropConstrainedForeignId('updated_by_user_id');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn(['alt_text', 'is_published', 'starts_at', 'ends_at', 'sort_order']);
        });
    }
};
