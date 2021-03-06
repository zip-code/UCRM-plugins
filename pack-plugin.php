<?php

if (! class_exists(ZipArchive::class)) {
    echo 'This script requires zip PHP extension.' . PHP_EOL;
    exit(1);
}

$plugin = $argv[1] ?? null;

$directory = __DIR__ . '/plugins/' . $plugin . '/src';

if (! $plugin || ! preg_match('~^(?:[a-z-_]++)$~', $plugin)) {
    echo 'Plugin name was not specified or is invalid.' . PHP_EOL;
    exit(1);
}

if (! is_dir($directory)) {
    $directory = __DIR__ . '/examples/' . $plugin . '/src';
    if (is_dir($directory)) {
        echo 'Specified plugin was not found in the "plugins" directory, using "examples" directory instead.' . PHP_EOL;
    } else {
        echo 'Specified plugin was not found in "plugins" nor "examples" directories.' . PHP_EOL;
        exit(1);
    }
}

chdir($directory);

if (file_exists($directory . '/composer.json')) {
    shell_exec('composer install --classmap-authoritative --no-dev --no-interaction');
}

$zipFile = $directory . '/../' . $plugin . '.zip';

if (file_exists($zipFile)) {
    unlink($zipFile);
}

$zip = new ZipArchive();

if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
    echo 'Can\'t open zip file.' . PHP_EOL;
    exit(1);
}

$files = new CallbackFilterIterator(
    new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($directory)
    ),
    function (SplFileInfo $fileInfo) {
        return ! $fileInfo->isDir();
    }
);

$reservedFiles = [
    '/ucrm.json',
    '/.ucrm-plugin-running',
    '/.ucrm-plugin-execution-requested',
];

/** @var SplFileInfo $fileInfo */
foreach ($files as $fileInfo) {
    $filename = substr(str_replace('\\', '/', $fileInfo->getPathname()), strlen($directory));

    if (in_array($filename, $reservedFiles, true) || substr($filename, 0, 6) === '/data/') {
        echo sprintf('Skipping reserved file "%s".', $filename) . PHP_EOL;
        continue;
    }

    if (! $zip->addFile($fileInfo->getPathname(), ltrim($filename, '/'))) {
        echo sprintf('Unable to add file "%s".', $filename) . PHP_EOL;
        exit(1);
    }
}

$zip->close();
