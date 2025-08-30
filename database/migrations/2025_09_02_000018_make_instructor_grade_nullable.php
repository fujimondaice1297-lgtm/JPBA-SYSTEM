<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
  public function up(): void {
    DB::statement("ALTER TABLE instructors ALTER COLUMN grade DROP NOT NULL");
  }
  public function down(): void {
    // 既存データにNULLがあると戻せないので、基本戻さない運用で。
  }
};
