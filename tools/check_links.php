<?php
// Simple link checker for local files in the workspace.
// Usage: run `php tools/check_links.php` from repository root (c:\xampp\htdocs\SEB).

function findFiles($dir, $exts) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    $files = [];
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        $path = $file->getPathname();
        foreach ($exts as $ext) {
            if (strtolower(substr($path, -strlen($ext))) === strtolower($ext)) {
                $files[] = $path;
                break;
            }
        }
    }
    return $files;
}

function extractRefs($content) {
    $matches = [];
    $refs = [];
    // match src="..." and href="..."
    preg_match_all('/(?:src|href)\s*=\s*"([^"]+)"/i', $content, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $m) $refs[] = $m;
    }
    return $refs;
}

$root = __DIR__ . DIRECTORY_SEPARATOR . '..';
$root = realpath($root);
if (!$root) {
    echo "Cannot determine project root\n"; exit(1);
}

$exts = ['.php','.html','.htm','.js','.css'];
$files = findFiles($root, $exts);

$missing = [];

foreach ($files as $file) {
    $content = @file_get_contents($file);
    if ($content === false) continue;
    $refs = extractRefs($content);
    if (!$refs) continue;
    $baseDir = dirname($file);
    foreach ($refs as $r) {
        // skip external and anchors and mailto and data URLs
        if (preg_match('#^(https?:)?//#i', $r)) continue;
        if (strpos($r, 'mailto:') === 0) continue;
        if (strpos($r, 'data:') === 0) continue;
        if (strpos($r, '#') === 0) continue;

        // remove query string and fragment
        $clean = preg_replace('/[\?#].*$/', '', $r);

        // absolute path (starting with /) -> relative to document root (assume project root)
        if (substr($clean,0,1) === '/') {
            $target = $root . str_replace('/', DIRECTORY_SEPARATOR, $clean);
        } else {
            $target = realpath($baseDir . DIRECTORY_SEPARATOR . $clean);
        }

        if ($target === false || !file_exists($target)) {
            $missing[] = [
                'source' => substr($file, strlen($root)+1),
                'ref' => $r,
                'resolved' => $target === false ? 'UNRESOLVED' : substr($target, strlen($root)+1)
            ];
        }
    }
}

if (empty($missing)) {
    echo "No missing local references found.\n";
    exit(0);
}

echo "Missing references found:\n\n";
foreach ($missing as $m) {
    echo "- Source: " . $m['source'] . "\n";
    echo "  Ref:    " . $m['ref'] . "\n";
    echo "  Resolved: " . $m['resolved'] . "\n\n";
}

echo "Total missing: " . count($missing) . "\n";

?>