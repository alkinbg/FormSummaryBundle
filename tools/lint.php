<?php

declare(strict_types=1);

$root = \dirname(__DIR__);
$files = [];
foreach (['src', 'tests', 'translations', 'tools'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && 'php' === $file->getExtension()) {
            $files[] = $file->getPathname();
        }
    }
}
sort($files);

foreach ($files as $file) {
    $process = proc_open([PHP_BINARY, '-l', $file], [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes);
    if (false === $process) {
        fwrite(STDERR, 'Unable to start PHP syntax validation.'.PHP_EOL);
        exit(1);
    }
    fclose($pipes[0]);
    $exitCode = proc_close($process);
    if (0 !== $exitCode) {
        exit(1);
    }
}
