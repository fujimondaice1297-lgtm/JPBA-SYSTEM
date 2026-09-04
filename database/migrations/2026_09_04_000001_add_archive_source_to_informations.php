<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('informations', function (Blueprint $table) {
            $table->string('source_type', 32)->nullable()->after('published_at');
            $table->string('source_key', 128)->nullable()->after('source_type');
            $table->text('source_url')->nullable()->after('source_key');
            $table->string('source_fingerprint', 64)->nullable()->after('source_url');
            $table->timestamp('source_synced_at')->nullable()->after('source_fingerprint');
            $table->string('body_format', 16)->default('plain')->after('body');

            $table->unique('source_key', 'informations_source_key_unique');
            $table->index(['source_type', 'published_at'], 'informations_source_type_published_index');
        });
    }

    public function down(): void
    {
        Schema::table('informations', function (Blueprint $table) {
            $table->dropUnique('informations_source_key_unique');
            $table->dropIndex('informations_source_type_published_index');
            $table->dropColumn([
                'source_type',
                'source_key',
                'source_url',
                'source_fingerprint',
                'source_synced_at',
                'body_format',
            ]);
        });
    }
};
