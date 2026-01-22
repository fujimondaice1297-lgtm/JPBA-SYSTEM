<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * 変更理由（1行）：既存の venues テーブルに tel / fax / website_url / note / postal_code が無い環境があり、
     * create_venues_table が if (!hasTable) でスキップされたため。足りない列だけを安全に追加する。
     */
    public function up(): void
    {
        if (!Schema::hasTable('venues')) {
            // テーブル自体が無い場合は、既存の create_venues_table を走らせる想定。
            return;
        }

        Schema::table('venues', function (Blueprint $table) {
            // 住所の後ろに付けたい列は after('address') を指定（存在しない環境でも Laravel は無視して続行）
            if (!Schema::hasColumn('venues', 'postal_code')) {
                $table->string('postal_code', 8)->nullable()->after('address'); // 例: 101-0047
            }
            if (!Schema::hasColumn('venues', 'tel')) {
                $table->string('tel', 50)->nullable()->after('postal_code');
            }
            if (!Schema::hasColumn('venues', 'fax')) {
                $table->string('fax', 50)->nullable()->after('tel');
            }
            if (!Schema::hasColumn('venues', 'website_url')) {
                $table->string('website_url', 255)->nullable()->after('fax');
            }
            if (!Schema::hasColumn('venues', 'note')) {
                $table->text('note')->nullable()->after('website_url');
            }

            // 将来のため：timestamps が無い環境にだけ追加（無ければスルー）
            if (!Schema::hasColumn('venues', 'created_at') && !Schema::hasColumn('venues', 'updated_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('venues')) return;

        Schema::table('venues', function (Blueprint $table) {
            // 追加分だけ安全に削除（存在確認してから）
            if (Schema::hasColumn('venues', 'note'))        $table->dropColumn('note');
            if (Schema::hasColumn('venues', 'website_url')) $table->dropColumn('website_url');
            if (Schema::hasColumn('venues', 'fax'))         $table->dropColumn('fax');
            if (Schema::hasColumn('venues', 'tel'))         $table->dropColumn('tel');
            if (Schema::hasColumn('venues', 'postal_code')) $table->dropColumn('postal_code');

            // timestamps は他で使っている可能性もあるので down では残す（安全優先）
        });
    }
};
