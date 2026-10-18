<?php
/**
 * Syntax-check plugin PHP files. Skips vendor/.
 */

$root = dirname(__DIR__);
$failed = 0;
$checked = 0;

$directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
$filter = new RecursiveCallbackFilterIterator(
    $directory,
    function ($current) {
        if ($current->isDir()) {
            $name = $current->getFilename();
            return $name !== 'vendor' && $name !== '.git';
        }
        return true;
    }
);
$iterator = new RecursiveIteratorIterator($filter);

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $relative = substr($file->getPathname(), strlen($root) + 1);
    $relative = str_replace('\\', '/', $relative);
    if (strpos($relative, 'vendor/') === 0) {
        continue;
    }

    $output = array();
    $code = 0;
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname());
    exec($command, $output, $code);
    $checked++;

    if ($code !== 0) {
        fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
        $failed++;
    }
}

if ($checked === 0) {
    fwrite(STDERR, 'No PHP files to lint.' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'Linted ' . $checked . ' PHP files.' . PHP_EOL);
exit($failed > 0 ? 1 : 0);
