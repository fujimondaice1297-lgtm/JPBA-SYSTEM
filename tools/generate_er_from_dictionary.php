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

// -------------------------
// 1) Collect tables from headings: "## table_name"
// -------------------------
$tables = [];
if (preg_match_all('/^##\s+([a-zA-Z0-9_]+)\s*$/m', $md, $m)) {
    foreach ($m[1] as $t) {
        $tables[$t] = ['refs' => []];
    }
}

// Helper: ensure table exists in $tables
$ensureTable = function(string $t) use (&$tables): void {
    if ($t === '') return;
    if (!isset($tables[$t])) {
        $tables[$t] = ['refs' => []]; // auto-add (even if no section exists)
    }
};

// -------------------------
// 2) Parse references from ANY section lines like:
//    A) "- col -> table.col"
//    B) "- table.col -> table.col"
// -------------------------
$refs = [];

// Split by headings "## " to keep context (not strictly required)
$sections = preg_split('/^##\s+/m', $md);
foreach ($sections as $sec) {
    $sec = trim($sec);
    if ($sec === '') continue;

    // Determine section table name (first line until newline)
    $lines = preg_split("/\r\n|\n|\r/", $sec);
    $sectionTable = trim($lines[0] ?? '');

    // Match BOTH formats. We read line-by-line.
    foreach ($lines as $line) {
        $line = trim($line);

        // Only lines beginning with "-" are considered
        if (!preg_match('/^\-\s+/', $line)) continue;

        // Remove leading "- "
        $body = preg_replace('/^\-\s+/', '', $line);

        // Format B: fromTable.fromCol -> toTable.toCol
        if (preg_match('/^([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\s*->\s*([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\s*$/', $body, $rm)) {
            $fromTable = $rm[1];
            $fromCol   = $rm[2];
            $toTable   = $rm[3];
            $toCol     = $rm[4];

            $ensureTable($fromTable);
            $ensureTable($toTable);

            $key = "{$fromTable}.{$fromCol}>{$toTable}.{$toCol}";
            $refs[$key] = [$fromTable, $fromCol, $toTable, $toCol];
            continue;
        }

        // Format A: fromCol -> toTable.toCol
        if (preg_match('/^([a-zA-Z0-9_]+)\s*->\s*([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\s*$/', $body, $rm)) {
            $fromCol = $rm[1];
            $toTable = $rm[2];
            $toCol   = $rm[3];

            // If section table is known, use it; otherwise we can't place it.
            if ($sectionTable === '') {
                continue;
            }
            $fromTable = $sectionTable;

            $ensureTable($fromTable);
            $ensureTable($toTable);

            $key = "{$fromTable}.{$fromCol}>{$toTable}.{$toCol}";
            $refs[$key] = [$fromTable, $fromCol, $toTable, $toCol];
            continue;
        }
    }
}

// -------------------------
// 3) Build DBML
// -------------------------
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

// Minimal Table blocks (columns are omitted)
foreach ($allTableNames as $t) {
    $out[] = "Table {$t} {";
    $out[] = "  // columns omitted (see docs/db/columns_by_table.md)";
    $out[] = "}";
    $out[] = "";
}

// Refs (sorted, de-duplicated)
$refLines = [];
foreach ($refs as [$fromTable, $fromCol, $toTable, $toCol]) {
    $refLines[] = "Ref: {$fromTable}.{$fromCol} > {$toTable}.{$toCol}";
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
