<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE tournament_results
ADD COLUMN IF NOT EXISTS amateur_name varchar(255) NULL
SQL);
    }
    public function down(): void { /* そのまま */ }
};