<?php
// Usage: php tools/utf8_normalize.php
// Scans .php files under project root, creates a .bak backup, and rewrites files as UTF-8 without BOM.

$root = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
$exts = ['php', 'html', 'htm'];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$files = [];
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    $path = $f->getPathname();
    $lower = strtolower($path);
    foreach ($exts as $ext) {
        if (substr($lower, -strlen($ext)-1) === '.' . $ext) {
            // skip vendor or node_modules if present
            if (strpos($path, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR) !== false) continue;
            if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) continue;
            $files[] = $path;
            break;
        }
    }
}

$converted = 0;
foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false) continue;

    // detect BOM and strip
    $original = $content;
    // remove UTF-8 BOM if present
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }

    // detect common mojibake sequences heuristically
    // We won't attempt automatic fixed translations here; we only ensure encoding

    // try to convert to UTF-8 if not already
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8');
    }

    // If content changed (e.g., BOM removed or encoding normalized), write backup and file
    if ($content !== $original) {
        $bak = $file . '.bak';
        // create backup if not exists
        if (!file_exists($bak)) {
            file_put_contents($bak, $original);
        }
        file_put_contents($file, $content);
        $converted++;
        echo "Converted: $file\n";
    }
}

echo "Done. Files converted: $converted\n";

?>