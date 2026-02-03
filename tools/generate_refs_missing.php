<?php
/**
 * Generate docs/db/refs_missing.md by comparing:
 * - DB columns ending with *_id (public schema)
 * - existing FK constraints in DB
 * - existing Refs in docs/db/ER.dbml
 *
 * Usage:
 *   php tools/generate_refs_missing.php
 */

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$outPath = __DIR__ . '/../docs/db/refs_missing.md';
$erPath  = __DIR__ . '/../docs/db/ER.dbml';

date_default_timezone_set('Asia/Tokyo');
$generatedAt = date('c');

// 1) Parse ER.dbml "Ref:" lines -> set of "table.column"
$erRefs = [];
if (file_exists($erPath)) {
    $lines = file($erPath, FILE_IGNORE_NEW_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (stripos($line, 'Ref:') !== 0) continue;

        // Example: Ref: informations.required_training_id > trainings.id
        if (preg_match('/^Ref:\s+([a-z0-9_]+)\.([a-z0-9_]+)\s+>\s+([a-z0-9_]+)\.([a-z0-9_]+)/i', $line, $m)) {
            $key = strtolower($m[1] . '.' . $m[2]);
            $erRefs[$key] = [
                'from_table' => strtolower($m[1]),
                'from_col'   => strtolower($m[2]),
                'to_table'   => strtolower($m[3]),
                'to_col'     => strtolower($m[4]),
                'raw'        => $line,
            ];
        }
    }
}

// 2) Collect all *_id columns from DB (public schema)
$cols = DB::select("
    SELECT table_name, column_name
    FROM information_schema.columns
    WHERE table_schema = 'public'
      AND column_name LIKE '%\\_id' ESCAPE '\\'
      AND column_name <> 'id'
    ORDER BY table_name, column_name
");

// 3) Collect actual FK constraints in DB
$fks = DB::select("
    SELECT
      tc.table_name AS from_table,
      kcu.column_name AS from_column,
      ccu.table_name AS to_table,
      ccu.column_name AS to_column
    FROM information_schema.table_constraints tc
    JOIN information_schema.key_column_usage kcu
      ON tc.constraint_name = kcu.constraint_name
     AND tc.table_schema = kcu.table_schema
    JOIN information_schema.constraint_column_usage ccu
      ON ccu.constraint_name = tc.constraint_name
     AND ccu.table_schema = tc.table_schema
    WHERE tc.constraint_type = 'FOREIGN KEY'
      AND tc.table_schema = 'public'
    ORDER BY from_table, from_column
");

$fkMap = []; // key: from_table.from_column
foreach ($fks as $fk) {
    $key = strtolower($fk->from_table . '.' . $fk->from_column);
    $fkMap[$key] = [
        'from_table' => strtolower($fk->from_table),
        'from_col'   => strtolower($fk->from_column),
        'to_table'   => strtolower($fk->to_table),
        'to_col'     => strtolower($fk->to_column),
    ];
}

// 4) Compare: columns *_id vs ER Refs
$missingSuggested = []; // those that have FK in DB but not in ER
$missingUnresolved = []; // those that have no FK in DB and not in ER

foreach ($cols as $c) {
    $key = strtolower($c->table_name . '.' . $c->column_name);

    // already represented in ER
    if (isset($erRefs[$key])) continue;

    // if DB has FK, we can "suggest"
    if (isset($fkMap[$key])) {
        $missingSuggested[] = $fkMap[$key];
    } else {
        $missingUnresolved[] = [
            'from_table' => strtolower($c->table_name),
            'from_col'   => strtolower($c->column_name),
        ];
    }
}

// 5) Group unresolved by table
$unresolvedByTable = [];
foreach ($missingUnresolved as $u) {
    $unresolvedByTable[$u['from_table']][] = $u['from_col'];
}
ksort($unresolvedByTable);

// 6) Build markdown
$md = [];
$md[] = "# Missing Refs (auto-detected)";
$md[] = "";
$md[] = "- Generated at: {$generatedAt}";
$md[] = "- DB: (from Laravel .env)";
$md[] = "- Schema: public";
$md[] = "";
$md[] = "このファイルは **DBのカラム（*_id）** を見て、**ER（docs/db/ER.dbml）にまだ書かれていないRef** を洗い出したものです。";
$md[] = "下の「Suggested additions」は **そのまま data_dictionary.md に転記**できます（正本は辞書）。";
$md[] = "";

$md[] = "## Suggested additions (copy/paste)";
$md[] = "";
if (count($missingSuggested) === 0) {
    $md[] = "（なし）";
} else {
    foreach ($missingSuggested as $s) {
        $md[] = "- Ref: {$s['from_table']}.{$s['from_col']} > {$s['to_table']}.{$s['to_col']}";
    }
}

$md[] = "";
$md[] = "## Unresolved (needs decision)";
$md[] = "";
if (count($unresolvedByTable) === 0) {
    $md[] = "（なし）";
} else {
    foreach ($unresolvedByTable as $tbl => $cols) {
        $md[] = "### {$tbl}";
        foreach ($cols as $col) {
            $md[] = "- {$tbl}.{$col} （参照先がDB上で未確定：FKなし）";
        }
        $md[] = "";
    }
}

file_put_contents($outPath, implode(PHP_EOL, $md) . PHP_EOL);

echo "done: generated {$outPath}" . PHP_EOL;
