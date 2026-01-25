<?php
// scan_includes.php
// Usage: php scan_includes.php C:\path\to\repo
// Outputs JSON to stdout with list of include/require statements and whether target exists.

if ($argc < 2) {
    echo "Usage: php {$argv[0]} /path/to/repo\n";
    exit(1);
}

$root = realpath($argv[1]);
if ($root === false) {
    echo "Invalid path: {$argv[1]}\n";
    exit(1);
}

$extensions = ['php', 'inc', 'phtml'];
$results = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($ext, $extensions) && $ext !== 'php') continue;

    $content = @file_get_contents($file->getPathname());
    if ($content === false) continue;

    // regex to find include/require variants with single/double quotes
    // captures the function and the path expression
    preg_match_all('/\b(include|require|include_once|require_once)\s*\(\s*([\'"])(.*?)\\2\s*\)\s*;?/i', $content, $m, PREG_SET_ORDER);

    foreach ($m as $match) {
        $fn = $match[1];
        $raw = $match[3];
        $filePath = $file->getPathname();
        $resolved = null;
        $exists = false;
        $note = '';

        // If path starts with '/', treat as absolute (server root)
        if (preg_match('#^(?:/|[A-Za-z]:\\\\)#', $raw)) {
            // absolute or windows path
            $resolved = $raw;
            if (file_exists($resolved)) $exists = true;
        } else {
            // relative to the including file's directory
            $candidate = realpath($file->getPath() . DIRECTORY_SEPARATOR . $raw);
            if ($candidate !== false) {
                $resolved = $candidate;
                $exists = file_exists($resolved);
            } else {
                // Might be using ROOT_DIR or __DIR__ or variable concatenation; mark as unresolved
                $resolved = $file->getPath() . DIRECTORY_SEPARATOR . $raw;
                $exists = file_exists($resolved);
                if (!$exists) {
                    // detect common patterns that indicate dynamic include (variables / constants)
                    if (preg_match('/\b(__DIR__|dirname\(|ROOT_DIR|ROOT_PATH|\$)/', $raw)) {
                        $note = 'dynamic or uses constants/variables';
                    } else {
                        $note = 'literal path not found';
                    }
                }
            }
        }

        $results[] = [
            'including_file' => substr($filePath, strlen($root) + 1),
            'line_snippet' => trim(substr($content, max(0, strpos($content, $match[0]) - 40), strlen($match[0]) + 80)),
            'include_type' => $fn,
            'raw_path' => $raw,
            'resolved_path' => $resolved ? (is_string($resolved) ? (strpos($resolved, $root) === 0 ? substr($resolved, strlen($root)+1) : $resolved) : $resolved) : null,
            'exists' => $exists,
            'note' => $note
        ];
    }

    // Also detect includes built with concatenation / variables (simple cases)
    preg_match_all('/\b(include|require|include_once|require_once)\s*\(\s*([^\)]+)\s*\)\s*;?/i', $content, $m2, PREG_SET_ORDER);
    foreach ($m2 as $match) {
        $expr = $match[2];
        // skip those already matched with literal quotes
        if (preg_match('/^[\'"].+[\'"]$/', trim($expr))) continue;
        if (preg_match('/(__DIR__|dirname\(|ROOT_DIR|\$)/', $expr)) {
            $results[] = [
                'including_file' => substr($file->getPathname(), strlen($root) + 1),
                'line_snippet' => trim(substr($content, max(0, strpos($content, $match[0]) - 40), strlen($match[0]) + 80)),
                'include_type' => $match[1],
                'raw_path' => $expr,
                'resolved_path' => null,
                'exists' => null,
                'note' => 'dynamic expression - manual check needed'
            ];
        }
    }
}

// print JSON
echo json_encode([
    'scanned_root' => $root,
    'file_count' => count(iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)))),
    'includes_found' => count($results),
    'results' => $results
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);