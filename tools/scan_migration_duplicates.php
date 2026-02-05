<?php
/**
 * Scan migrations and detect duplicated column definitions per table.
 *
 * Usage:
 *   php tools/scan_migration_duplicates.php
 *
 * Output:
 *   docs/db/migration_duplicates.md
 */
declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "ERROR: failed to resolve project root.\n");
    exit(1);
}

$migrationsDir = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
$outPath       = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'migration_duplicates.md';

if (!is_dir($migrationsDir)) {
    fwrite(STDERR, "ERROR: migrations dir not found: {$migrationsDir}\n");
    exit(1);
}

$files = glob($migrationsDir . DIRECTORY_SEPARATOR . '*.php');
sort($files);

$targets = []; // [$table][$column][] = ['file' => ..., 'op' => 'create|table']

/**
 * Extract callback body by brace matching from a start index (points to '{').
 */
$extractBody = function(string $src, int $bracePos): array {
    $len = strlen($src);
    $depth = 0;
    $start = $bracePos + 1;
    for ($i = $bracePos; $i < $len; $i++) {
        $ch = $src[$i];
        if ($ch === '{') $depth++;
        if ($ch === '}') {
            $depth--;
            if ($depth === 0) {
                $end = $i;
                return [substr($src, $start, $end - $start), $end];
            }
        }
    }
    return ['', $len - 1];
};

foreach ($files as $file) {
    $src = file_get_contents($file);
    if ($src === false) continue;

    // Find Schema::create/table('xxx', function (Blueprint $table) {
    $pattern = "/Schema::(create|table)\\(\\s*['\\\"]([a-zA-Z0-9_]+)['\\\"]\\s*,\\s*function\\s*\\(\\s*Blueprint\\s*\\$table\\s*\\)\\s*\\{/m";
    if (!preg_match_all($pattern, $src, $m, PREG_OFFSET_CAPTURE)) {
        continue;
    }

    for ($k = 0; $k < count($m[0]); $k++) {
        $op    = $m[1][$k][0];            // create|table
        $table = $m[2][$k][0];
        $matchPos = $m[0][$k][1];
        $bracePos = $matchPos + strlen($m[0][$k][0]) - 1; // points to '{'

        [$body, $endPos] = $extractBody($src, $bracePos);
        if ($body === '') continue;

        // Extract column definitions in the body
        // Covers: string/int/bigInteger/unsignedBigInteger/foreignId/boolean/date/dateTime/text/longText/json/decimal/float/double/uuid/enum
        $colPattern = "/\\$table->(?:string|integer|bigInteger|unsignedBigInteger|foreignId|boolean|date|dateTime|text|longText|json|decimal|float|double|uuid|enum)\\(\\s*['\\\"]([a-zA-Z0-9_]+)['\\\"]/m";
        if (preg_match_all($colPattern, $body, $cm)) {
            foreach ($cm[1] as $col) {
                $targets[$table][$col][] = [
                    'file' => basename($file),
                    'op'   => $op,
                ];
            }
        }

        // Also catch dropColumn / renameColumn targets (for awareness)
        $dropPattern = "/\\$table->(?:dropColumn|renameColumn)\\(\\s*['\\\"]([a-zA-Z0-9_]+)['\\\"]/m";
        if (preg_match_all($dropPattern, $body, $dm)) {
            foreach ($dm[1] as $col) {
                $targets[$table][$col][] = [
                    'file' => basename($file),
                    'op'   => $op . ':drop/rename',
                ];
            }
        }
    }
}

// Build report: only duplicates (same table+col appears in 2+ migrations)
$lines = [];
$lines[] = "# migration_duplicates";
$lines[] = "";
$lines[] = "- Generated at: " . date('c');
$lines[] = "- Note: This is a heuristic scan (static regex). Use it to find suspicious duplicates quickly.";
$lines[] = "";

$dupCount = 0;
$tables = array_keys($targets);
sort($tables);

foreach ($tables as $t) {
    $cols = array_keys($targets[$t]);
    sort($cols);

    $tableLines = [];
    foreach ($cols as $c) {
        $hits = $targets[$t][$c];
        // Unique by file
        $byFile = [];
        foreach ($hits as $h) {
            $byFile[$h['file']] = $h['op'];
        }
        if (count($byFile) < 2) continue;

        $dupCount++;
        $tableLines[] = "## {$t}.{$c}";
        foreach ($byFile as $f => $op) {
            $tableLines[] = "- {$f} ({$op})";
        }
        $tableLines[] = "";
    }

    if (!empty($tableLines)) {
        $lines[] = "# Table: {$t}";
        $lines[] = "";
        $lines = array_merge($lines, $tableLines);
    }
}

if ($dupCount === 0) {
    $lines[] = "No duplicates detected by this scan.";
    $lines[] = "";
}

if (!is_dir(dirname($outPath))) {
    mkdir(dirname($outPath), 0777, true);
}

$ok = file_put_contents($outPath, implode(PHP_EOL, $lines));
if ($ok === false) {
    fwrite(STDERR, "ERROR: failed to write: {$outPath}\n");
    exit(1);
}

echo "OK: generated {$outPath}\n";
