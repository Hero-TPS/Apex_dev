<?php
// scan_includes_fixed.php
// Usage: php scan_includes_fixed.php C:\path\to\repo > include_report.json
// More robust scanner for include/require statements (handles with/without parentheses).

if ($argc < 2) {
    echo "Usage: php {$argv[0]} /path/to/repo\n";
    exit(1);
}

$root = realpath($argv[1]);
if ($root === false) {
    echo "Invalid path: {$argv[1]}\n";
    exit(1);
}

$extensions = ['php','phtml','inc','php4','php5','tpl'];
$results = [];
$fileCount = 0;

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($it as $file) {
    if (!$file->isFile()) continue;
    $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($ext, $extensions)) continue;
    $fileCount++;
    $content = @file_get_contents($file->getPathname());
    if ($content === false) continue;

    // Match literal includes/requires with or without parentheses:
    // Examples matched:
    // include 'file.php';
    // require "file.php";
    // include_once('file.php');
    // require_once ( "file.php" );
    preg_match_all('/\b(include|require|include_once|require_once)\s*(?:\(\s*)?[\'"]([^\'"]+)[\'"]\s*(?:\)\s*)?;/i', $content, $m, PREG_SET_ORDER);
    foreach ($m as $match) {
        $fn = $match[1];
        $raw = $match[2];
        $including = $file->getPathname();
        $resolved = null;
        $exists = false;
        $note = '';

        // Absolute path (Unix or Windows)
        if (preg_match('#^(?:/|[A-Za-z]:\\\\)#', $raw)) {
            $resolved = $raw;
            $exists = file_exists($resolved);
        } else {
            // Try resolve relative to including file directory
            $candidate = realpath($file->getPath() . DIRECTORY_SEPARATOR . $raw);
            if ($candidate !== false && file_exists($candidate)) {
                $resolved = $candidate;
                $exists = true;
            } else {
                // Try resolve relative to repo root
                $candidate2 = realpath($root . DIRECTORY_SEPARATOR . $raw);
                if ($candidate2 !== false && file_exists($candidate2)) {
                    $resolved = $candidate2;
                    $exists = true;
                } else {
                    // fallback: show expected relative path (not found)
                    $resolved = $file->getPath() . DIRECTORY_SEPARATOR . $raw;
                    $exists = file_exists($resolved);
                    if (!$exists) $note = 'literal path not found';
                }
            }
        }

        $results[] = [
            'including_file' => substr($including, strlen($root) + 1),
            'include_type' => $fn,
            'raw_path' => $raw,
            'resolved_path' => ($resolved ? (strpos($resolved, $root) === 0 ? substr($resolved, strlen($root)+1) : $resolved) : null),
            'exists' => $exists,
            'line_snippet' => get_line_snippet($content, $match[0]),
            'note' => $note
        ];
    }

    // Match dynamic includes/requires (expressions, __DIR__, variables, concatenation)
    // We'll capture the entire expression inside parentheses or after keyword
    preg_match_all('/\b(include|require|include_once|require_once)\s*(?:\(\s*)?([^;]+?)\s*(?:\)\s*)?;/i', $content, $m2, PREG_SET_ORDER);
    foreach ($m2 as $match) {
        // skip those already captured as literal quotes
        if (preg_match('/^[\'"]/',$match[2])) continue;
        // If expression contains __DIR__, dirname(, ROOT_DIR or variables, mark dynamic
        if (preg_match('/(__DIR__|dirname\s*\(|ROOT_DIR|\$[A-Za-z_]+)/', $match[2])) {
            $including = $file->getPathname();
            $results[] = [
                'including_file' => substr($including, strlen($root) + 1),
                'include_type' => $match[1],
                'raw_path' => trim($match[2]),
                'resolved_path' => null,
                'exists' => null,
                'line_snippet' => get_line_snippet($content, $match[0]),
                'note' => 'dynamic expression - manual review needed'
            ];
        }
    }
}

echo json_encode([
    'scanned_root' => $root,
    'file_count' => $fileCount,
    'includes_found' => count($results),
    'results' => $results
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

function get_line_snippet($content, $matchStr, $radius = 60) {
    $pos = strpos($content, $matchStr);
    if ($pos === false) return trim($matchStr);
    $start = max(0, $pos - $radius);
    $len = strlen($matchStr) + $radius * 2;
    $snippet = substr($content, $start, $len);
    $snippet = preg_replace('/\s+/m', ' ', $snippet);
    return trim($snippet);
}