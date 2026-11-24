<?php

/**
 * Builds the demo plugin, for a check of the administrator by hand on a local site. Not part of the package.
 *
 * Usage: php tests/demo/build.php <folder for the archive>
 */

$source = __DIR__ . '/plg_content_mxrdemo';
$target = rtrim($argv[1] ?? exit("the folder for the archive\n"), '/\\') . '/plg_content_mxrdemo-test.zip';

@unlink($target);

$zip = new ZipArchive();
$zip->open($target, ZipArchive::CREATE);

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
    $zip->addFile($file->getPathname(), str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1)));
}

$zip->close();

echo "Built $target\n";
