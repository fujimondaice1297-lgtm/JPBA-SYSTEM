<?php
/**
 * Apply "Suggested additions" from docs/db/refs_missing.md into docs/db/data_dictionary.md
 * - Inserts missing FK lines into the matching "## table" section
 * - Creates section if not exists
 * - De-duplicates existing lines
 * - Skips known-ambiguous columns (by default)
 *
 * Usage:
 *   php tools/apply_refs_missing.php
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "ERROR: failed to resolve project root.\n");
    exit(1);
}

$refsPath = $root . '/docs/db/refs_missing.md';
$dictPath = $root . '/docs/db/data_dictionary.md';

if (!file_exists($refsPath)) {
    fwrite(STDERR, "ERROR: not found: {$refsPath}\n");
    exit(1);
}
if (!file_exists($dictPath)) {
    fwrite(STDERR, "ERROR: not found: {$dictPath}\n");
    exit(1);
}

$refs = file_get_contents($refsPath);
$dict = file_get_contents($dictPath);
if ($refs === false || $dict === false) {
    fwrite(STDERR, "ERROR: failed to read input files.\n");
    exit(1);
}

// Skip list: ambiguous or likely-not-FK (adjust anytime)
$skip = [
    'pro_test.record_type_id', // we intentionally keep this as "needs decision"
];

// Parse "Suggested additions" blocks:
// ### table_name
// ```md
// - table.col -> target.id
// ...
// ```
$suggestions = []; // table => array of "table.col -> target.id"

$lines = preg_split("/\r\n|\n|\r/", $refs);
$inSuggested = false;
$curTable = null;
$inCode = false;

foreach ($lines as $line) {
    $trim = trim($line);

    if ($trim === '## Suggested additions (copy/paste)') {
        $inSuggested = true;
        continue;
    }
    if (str_starts_with($trim, '## Unresolved')) {
        $inSuggested = false;
        $curTable = null;
        $inCode = false;
        continue;
    }
    if (!$inSuggested) continue;

    if (preg_match('/^###\s+([a-zA-Z0-9_]+)\s*$/', $trim, $m)) {
        $curTable = $m[1];
        $suggestions[$curTable] = $suggestions[$curTable] ?? [];
        $inCode = false;
        continue;
    }
    if ($trim === '```md') {
        $inCode = true;
        continue;
    }
    if ($trim === '```') {
        $inCode = false;
        continue;
    }
    if ($inCode && $curTable !== null) {
        if (preg_match('/^\-\s+([a-zA-Z0-9_]+\.[a-zA-Z0-9_]+)\s*->\s*([a-zA-Z0-9_]+\.[a-zA-Z0-9_]+)\s*$/', $trim, $m)) {
            $from = $m[1]; // table.col
            if (in_array($from, $skip, true)) {
                continue;
            }
            $suggestions[$curTable][] = "{$m[1]} -> {$m[2]}";
        }
    }
}

// Normalize & de-dup suggestions
foreach ($suggestions as $t => $items) {
    $uniq = [];
    foreach ($items as $it) {
        $key = strtolower(preg_replace('/\s+/', '', $it));
        $uniq[$key] = $it;
    }
    $suggestions[$t] = array_values($uniq);
}

if (empty($suggestions)) {
    fwrite(STDERR, "WARN: no suggestions found.\n");
    exit(0);
}

// Helper: find section range for "## table"
function findSectionRange(string $md, string $table): ?array {
    $pattern = '/^##\s+' . preg_quote($table, '/') . '\s*$/m';
    if (!preg_match($pattern, $md, $m, PREG_OFFSET_CAPTURE)) return null;

    $start = $m[0][1];
    $afterStart = $start + strlen($m[0][0]);

    // Next "## " heading after this section
    if (preg_match('/^##\s+/m', $md, $m2, PREG_OFFSET_CAPTURE, $afterStart)) {
        $end = $m2[0][1];
    } else {
        $end = strlen($md);
    }
    return [$start, $end];
}

// Helper: insert FK lines inside section
function applyToSection(string $section, string $table, array $linesToAdd): string {
    // Collect existing "- xxx -> yyy" lines to avoid duplicates
    $existing = [];
    if (preg_match_all('/^\-\s+([a-zA-Z0-9_]+\.[a-zA-Z0-9_]+)\s*->\s*([a-zA-Z0-9_]+\.[a-zA-Z0-9_]+)\s*$/m', $section, $m)) {
        $cnt = count($m[0]);
        for ($i=0; $i<$cnt; $i++) {
            $key = strtolower(preg_replace('/\s+/', '', "{$m[1][$i]}->{$m[2][$i]}"));
            $existing[$key] = true;
        }
    }

    $add = [];
    foreach ($linesToAdd as $l) {
        // $l: "table.col -> target.id"
        $key = strtolower(preg_replace('/\s+/', '', str_replace(' -> ', '->', $l)));
        if (isset($existing[$key])) continue;

        // store as "- table.col -> target.id"
        $add[] = "- {$l}";
    }
    if (empty($add)) return $section;

    // Find best heading to place under:
    // Prefer "### 外部キー" then "### 参照しているマスタ"
    $headingPatterns = [
        '/^###\s*外部キー.*$/m',
        '/^###\s*参照しているマスタ.*$/m',
    ];

    foreach ($headingPatterns as $hp) {
        if (preg_match($hp, $section, $hm, PREG_OFFSET_CAPTURE)) {
            $hPos = $hm[0][1];
            $insertPos = $hPos + strlen($hm[0][0]);

            // Insert after heading line, before next "###" or end of section
            // We'll place immediately after the heading line.
            $before = substr($section, 0, $insertPos);
            $after  = substr($section, $insertPos);

            $block = PHP_EOL . implode(PHP_EOL, $add) . PHP_EOL;
            return $before . $block . $after;
        }
    }

    // If no suitable heading, append a new one near end of section
    $append = PHP_EOL .
        "### 外部キー（自動反映：refs_missing.md）" . PHP_EOL .
        implode(PHP_EOL, $add) . PHP_EOL;

    // If section ends with "---" separators, put before last separator if present
    if (preg_match('/\R---\R\s*$/', $section)) {
        $section = preg_replace('/\R---\R\s*$/', $append . PHP_EOL . "---" . PHP_EOL, $section);
        return $section;
    }
    return rtrim($section) . $append . PHP_EOL;
}

// Apply all suggestions to dictionary
$updated = $dict;

foreach ($suggestions as $table => $linesToAdd) {
    if (empty($linesToAdd)) continue;

    $range = findSectionRange($updated, $table);
    if ($range === null) {
        // Create new minimal section at end
        $newSec = PHP_EOL .
            "---" . PHP_EOL . PHP_EOL .
            "## {$table}" . PHP_EOL . PHP_EOL .
            "### 外部キー（自動反映：refs_missing.md）" . PHP_EOL .
            implode(PHP_EOL, array_map(fn($l) => "- {$l}", $linesToAdd)) . PHP_EOL;
        $updated = rtrim($updated) . $newSec . PHP_EOL;
        continue;
    }

    [$start, $end] = $range;
    $section = substr($updated, $start, $end - $start);
    $section2 = applyToSection($section, $table, $linesToAdd);

    $updated = substr($updated, 0, $start) . $section2 . substr($updated, $end);
}

// Backup + write
$backupPath = $dictPath . '.bak';
file_put_contents($backupPath, $dict);
file_put_contents($dictPath, $updated);

echo "OK: updated {$dictPath}\n";
echo "OK: backup  {$backupPath}\n";

// Also generate a small note for skipped items
if (!empty($skip)) {
    $notePath = $root . '/docs/db/refs_skipped.md';
    $note = [];
    $note[] = "# Skipped refs (needs decision)";
    $note[] = "";
    $note[] = "- Generated at: " . date('c');
    $note[] = "";
    foreach ($skip as $s) {
        $note[] = "- {$s}";
    }
    file_put_contents($notePath, implode(PHP_EOL, $note) . PHP_EOL);
    echo "OK: generated {$notePath}\n";
}
