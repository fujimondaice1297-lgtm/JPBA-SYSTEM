<?php
/**
 * Generate DBML (dbdiagram.io) ER diagram file from docs/db/data_dictionary.md
 *
 * Usage:
 *   php tools/generate_er_from_dictionary.php
 *
 * Output:
 *   docs/db/ER.dbml (overwritten)
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "ERROR: failed to resolve project root.\n");
    exit(1);
}

$inputPath  = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'data_dictionary.md';
$outputPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'ER.dbml';

if (!file_exists($inputPath)) {
    fwrite(STDERR, "ERROR: input not found: {$inputPath}\n");
    exit(1);
}

$md = file_get_contents($inputPath);
if ($md === false) {
    fwrite(STDERR, "ERROR: failed to read: {$inputPath}\n");
    exit(1);
}

/**
 * Parse format assumption in data_dictionary.md:
 *
 * ## table_name
 * ### 主キー
 * - id (bigint)
 *
 * ### 参照しているマスタ
 * - sex_id -> sexes.id
 * - area_id -> area.id
 * ...
 */

// 1) Collect tables (## table_name)
$tables = [];
if (preg_match_all('/^##\s+([a-zA-Z0-9_]+)\s*$/m', $md, $m)) {
    foreach ($m[1] as $t) {
        $tables[$t] = ['refs' => []];
    }
}

// 2) Collect references inside each table section
//    We'll split by "## " headings.
$sections = preg_split('/^##\s+/m', $md);
foreach ($sections as $sec) {
    $sec = trim($sec);
    if ($sec === '') continue;

    // first token is table name until newline
    $lines = preg_split("/\r\n|\n|\r/", $sec);
    $tableName = trim($lines[0] ?? '');
    if ($tableName === '' || !isset($tables[$tableName])) continue;

    // find "- xxx -> yyy.zzz" lines
    if (preg_match_all('/^\s*-\s*([a-zA-Z0-9_]+)\s*->\s*([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\s*$/m', $sec, $rm)) {
        $count = count($rm[1]);
        for ($i = 0; $i < $count; $i++) {
            $fromCol = $rm[1][$i];
            $toTable = $rm[2][$i];
            $toCol   = $rm[3][$i];
            $tables[$tableName]['refs'][] = [$fromCol, $toTable, $toCol];
        }
    }
}

// 3) Build DBML
$out = [];
$out[] = "// Auto-generated from docs/db/data_dictionary.md";
$out[] = "// Generated at: " . date('c');
$out[] = "";
$out[] = "Project JPBA_SYSTEM {";
$out[] = "  database_type: 'PostgreSQL'";
$out[] = "}";
$out[] = "";

$allTableNames = array_keys($tables);
sort($allTableNames);

// Minimal Table blocks (we won't enumerate columns here; refs are the priority)
foreach ($allTableNames as $t) {
    $out[] = "Table {$t} {";
    $out[] = "  // columns omitted (see docs/db/columns_by_table.md)";
    $out[] = "}";
    $out[] = "";
}

// Refs
$refLines = [];
foreach ($allTableNames as $fromTable) {
    foreach ($tables[$fromTable]['refs'] as [$fromCol, $toTable, $toCol]) {
        // Only output if target table exists in doc; if not, still output but mark comment
        $comment = '';
        if (!isset($tables[$toTable])) {
            $comment = " // NOTE: target table '{$toTable}' not defined in data_dictionary.md";
        }
        $refLines[] = "Ref: {$fromTable}.{$fromCol} > {$toTable}.{$toCol}{$comment}";
    }
}
sort($refLines);

$out = array_merge($out, $refLines);
$out[] = "";

$ok = file_put_contents($outputPath, implode(PHP_EOL, $out));
if ($ok === false) {
    fwrite(STDERR, "ERROR: failed to write: {$outputPath}\n");
    exit(1);
}

echo "OK: generated {$outputPath}\n";
