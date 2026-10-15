<?php

use Illuminate\Support\Str;

$viewPath = dirname(__DIR__, 2) . '/resources/views';

test('no raw <table> tags outside ui component folder and vendor', function () use ($viewPath) {
    $violations = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($viewPath, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = Str::after($file->getPathname(), $viewPath . DIRECTORY_SEPARATOR);
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

        if (Str::startsWith($relative, 'components/ui/table') ||
            Str::startsWith($relative, 'vendor/') ||
            Str::startsWith($relative, 'pdf/')) {
            continue;
        }

        if (preg_match('/<table[\s>]/', file_get_contents($file->getPathname()))) {
            $violations[] = $relative;
        }
    }

    expect($violations)->toBe([]);
});

test('no off-brand Tailwind colour classes in views', function () use ($viewPath) {
    $offBrand = ['blue-', 'indigo-', 'sky-', 'violet-', 'purple-', 'cyan-', 'teal-'];
    $violations = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($viewPath, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = Str::after($file->getPathname(), $viewPath . DIRECTORY_SEPARATOR);
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

        if (Str::startsWith($relative, 'vendor/') || $relative === 'welcome.blade.php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        foreach ($offBrand as $prefix) {
            if (str_contains($contents, $prefix)) {
                $violations[] = "$relative contains \"$prefix\"";
                break;
            }
        }
    }

    expect($violations)->toBe([]);
});
