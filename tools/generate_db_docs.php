<?php
/**
 * Generate docs/db/columns_by_table.md from docs/db/columns_public.csv
 * Usage: php tools/generate_db_docs.php
 */

$root = dirname(__DIR__); // project root (tools/..)
$inputCsv = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'columns_public.csv';
$outputMd = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'columns_by_table.md';

if (!file_exists($inputCsv)) {
    fwrite(STDERR, "ERROR: CSV not found: {$inputCsv}\n");
    exit(1);
}

$fh = fopen($inputCsv, 'r');
if (!$fh) {
    fwrite(STDERR, "ERROR: cannot open: {$inputCsv}\n");
    exit(1);
}

$header = fgetcsv($fh);
if ($header === false) {
    fwrite(STDERR, "ERROR: CSV is empty.\n");
    exit(1);
}

// Normalize header map
$index = [];
foreach ($header as $i => $col) {
    $key = strtolower(trim($col));
    $index[$key] = $i;
}

$required = ['table_name','ordinal_position','column_name','data_type','is_nullable'];
foreach ($required as $r) {
    if (!isset($index[$r])) {
        fwrite(STDERR, "ERROR: CSV missing required column: {$r}\n");
        fwrite(STDERR, "Found headers: " . implode(', ', $header) . "\n");
        exit(1);
    }
}

$tables = []; // table_name => rows
while (($row = fgetcsv($fh)) !== false) {
    $table = $row[$index['table_name']] ?? '';
    if ($table === '') continue;

    $tables[$table][] = [
        'pos' => (int)($row[$index['ordinal_position']] ?? 0),
        'name' => (string)($row[$index['column_name']] ?? ''),
        'type' => (string)($row[$index['data_type']] ?? ''),
        'nullable' => (string)($row[$index['is_nullable']] ?? ''),
    ];
}
fclose($fh);

ksort($tables);

// Build markdown
$now = date('Y-m-d H:i:s');
$md = [];
$md[] = "# Columns by table (generated)";
$md[] = "";
$md[] = "- Source: `docs/db/columns_public.csv`";
$md[] = "- Generated: {$now}";
$md[] = "";
$md[] = "> ⚠️ このファイルは自動生成です。手で編集しないでください。";
$md[] = "";

foreach ($tables as $tableName => $rows) {
    usort($rows, fn($a, $b) => $a['pos'] <=> $b['pos']);
    $count = count($rows);

    $md[] = "## {$tableName} ({$count} columns)";
    $md[] = "";
    $md[] = "| # | column | type | nullable |";
    $md[] = "|---:|---|---|---|";

    foreach ($rows as $r) {
        $pos = $r['pos'];
        $col = $r['name'];
        $type = $r['type'];
        $nul = $r['nullable'];
        $md[] = "| {$pos} | {$col} | {$type} | {$nul} |";
    }

    $md[] = "";
}

file_put_contents($outputMd, implode("\n", $md));

echo "OK: generated {$outputMd}\n";
