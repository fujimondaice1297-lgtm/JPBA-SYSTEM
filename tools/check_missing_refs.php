<?php
/**
 * Detect missing reference lines (Refs) by scanning PostgreSQL schema:
 *   - Find columns ending with *_id in public schema
 *   - Compare with currently documented refs in docs/db/ER.dbml
 *   - Generate docs/db/refs_missing.md with suggested lines to paste into data_dictionary.md
 *
 * Usage:
 *   php tools/check_missing_refs.php
 *
 * Output:
 *   docs/db/refs_missing.md (overwritten)
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "ERROR: failed to resolve project root.\n");
    exit(1);
}

$envPath = $root . DIRECTORY_SEPARATOR . '.env';
if (!file_exists($envPath)) {
    fwrite(STDERR, "ERROR: .env not found at: {$envPath}\n");
    fwrite(STDERR, "Hint: Laravel project root should contain .env\n");
    exit(1);
}

function loadEnv(string $path): array {
    $vars = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return $vars;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        // Allow "export KEY=VALUE"
        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }
        if (!str_contains($line, '=')) continue;

        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);

        // Strip surrounding quotes
        if ((str_starts_with($v, '"') && str_ends_with($v, '"')) ||
            (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
            $v = substr($v, 1, -1);
        }

        $vars[$k] = $v;
    }
    return $vars;
}

$env = loadEnv($envPath);

$dbConn = $env['DB_CONNECTION'] ?? 'pgsql';
if ($dbConn !== 'pgsql' && $dbConn !== 'postgres' && $dbConn !== 'postgresql') {
    // Still try; many Laravel projects use pgsql
    // We'll continue, but warn.
    fwrite(STDERR, "WARN: DB_CONNECTION is '{$dbConn}'. Trying PostgreSQL anyway...\n");
}

$dbHost = $env['DB_HOST'] ?? '127.0.0.1';
$dbPort = $env['DB_PORT'] ?? '5432';
$dbName = $env['DB_DATABASE'] ?? '';
$dbUser = $env['DB_USERNAME'] ?? '';
$dbPass = $env['DB_PASSWORD'] ?? '';

if ($dbName === '' || $dbUser === '') {
    fwrite(STDERR, "ERROR: DB_DATABASE or DB_USERNAME is empty in .env\n");
    exit(1);
}

try {
    $dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName};options='--client_encoding=UTF8'";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: failed to connect to PostgreSQL.\n");
    fwrite(STDERR, "       host={$dbHost} port={$dbPort} db={$dbName} user={$dbUser}\n");
    fwrite(STDERR, "       " . $e->getMessage() . "\n");
    exit(1);
}

// Get all base tables in public schema
$tables = $pdo->query("
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema='public' AND table_type='BASE TABLE'
")->fetchAll(PDO::FETCH_COLUMN);

$tableSet = array_fill_keys($tables, true);

// Get all *_id columns in public schema
$idCols = $pdo->query("
    SELECT table_name, column_name
    FROM information_schema.columns
    WHERE table_schema='public'
      AND column_name LIKE '%\\_id' ESCAPE '\\'
    ORDER BY table_name, ordinal_position
")->fetchAll(PDO::FETCH_ASSOC);

// Read existing refs from ER.dbml (source of truth for "already documented")
$erPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'ER.dbml';
$documentedFrom = []; // "table.col" => true
$documentedRefLines = [];

if (file_exists($erPath)) {
    $er = file_get_contents($erPath);
    if ($er !== false) {
        if (preg_match_all('/^Ref:\s+([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\s*>\s*([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)\s*$/m', $er, $m)) {
            $cnt = count($m[1]);
            for ($i = 0; $i < $cnt; $i++) {
                $fromTable = $m[1][$i];
                $fromCol   = $m[2][$i];
                $toTable   = $m[3][$i];
                $toCol     = $m[4][$i];

                $documentedFrom["{$fromTable}.{$fromCol}"] = true;
                $documentedRefLines[] = "Ref: {$fromTable}.{$fromCol} > {$toTable}.{$toCol}";
            }
        }
    }
}

// Simple pluralize helper (good enough for our table names)
function pluralize(string $word): string {
    // already ends with s
    if (str_ends_with($word, 's')) return $word;

    // y -> ies
    if (preg_match('/[^aeiou]y$/', $word)) {
        return substr($word, 0, -1) . 'ies';
    }

    // x/ch/sh -> es
    if (preg_match('/(x|ch|sh)$/', $word)) {
        return $word . 'es';
    }

    // default s
    return $word . 's';
}

// Guess target table candidates from *_id column
function guessTargets(string $fromCol): array {
    $base = substr($fromCol, 0, -3); // remove _id
    $cands = [];

    // Common "user" patterns
    if ($base === 'user' || str_contains($base, 'user')) {
        $cands[] = 'users';
    }

    // direct base and plural
    $cands[] = $base;
    $cands[] = pluralize($base);

    // some projects use *_types / *_masters
    $cands[] = $base . '_types';
    $cands[] = $base . '_masters';
    $cands[] = $base . '_master';

    // handle record_type -> record_types, pro_bowler -> pro_bowlers etc.
    // (pluralize already covers most, but keep)
    return array_values(array_unique(array_filter($cands)));
}

// Build missing suggestions
$missing = [];    // fromTable => list of [fromCol, toTable, toCol, note]
$unresolved = []; // fromTable => list of [fromCol, triedCandidates]

foreach ($idCols as $row) {
    $fromTable = $row['table_name'];
    $fromCol   = $row['column_name'];

    // Skip if already documented in ER
    if (isset($documentedFrom["{$fromTable}.{$fromCol}"])) {
        continue;
    }

    // Sometimes *_id is not a true FK. We'll still suggest, but try to guess carefully.
    $cands = guessTargets($fromCol);

    $found = null;
    foreach ($cands as $t) {
        if (isset($tableSet[$t])) {
            $found = $t;
            break;
        }
    }

    if ($found !== null) {
        $missing[$fromTable][] = [$fromCol, $found, 'id', 'inferred'];
    } else {
        $unresolved[$fromTable][] = [$fromCol, $cands];
    }
}

// Write markdown report
$outPath = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'refs_missing.md';
$md = [];
$md[] = "# Missing Refs (auto-detected)";
$md[] = "";
$md[] = "- Generated at: " . date('c');
$md[] = "- DB: {$dbName} (host={$dbHost} port={$dbPort})";
$md[] = "- Schema: public";
$md[] = "";
$md[] = "このファイルは **DBのカラム（*_id）** を見て、**ER（docs/db/ER.dbml）にまだ書かれていないRef** を洗い出したものです。";
$md[] = "下の「Suggested additions」は **そのまま data_dictionary.md にコピペ**できます。";
$md[] = "";

$md[] = "## Suggested additions (copy/paste)";
$md[] = "";

ksort($missing);
foreach ($missing as $fromTable => $items) {
    $md[] = "### {$fromTable}";
    $md[] = "";
    $md[] = "```md";
    foreach ($items as [$fromCol, $toTable, $toCol, $note]) {
        // Use full format to avoid "where to paste" confusion
        $md[] = "- {$fromTable}.{$fromCol} -> {$toTable}.{$toCol}";
    }
    $md[] = "```";
    $md[] = "";
}

$md[] = "## Unresolved (needs decision)";
$md[] = "";

if (empty($unresolved)) {
    $md[] = "なし ✅";
    $md[] = "";
} else {
    ksort($unresolved);
    foreach ($unresolved as $fromTable => $items) {
        $md[] = "### {$fromTable}";
        foreach ($items as [$fromCol, $cands]) {
            $md[] = "- {$fromTable}.{$fromCol} （候補: " . implode(', ', $cands) . "）";
        }
        $md[] = "";
    }
}

$ok = file_put_contents($outPath, implode(PHP_EOL, $md));
if ($ok === false) {
    fwrite(STDERR, "ERROR: failed to write: {$outPath}\n");
    exit(1);
}

echo "OK: generated {$outPath}\n";
