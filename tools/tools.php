<?php declare(strict_types=1);

$toolsDir = __DIR__;
$command  = $argv[1] ?? null;

if (!in_array($command, ['install', 'update'], true)) {
    echo "Please choose either \e[0;32minstall\e[0m or \e[0;32mupdate\e[0m" . PHP_EOL;
    exit(1);
}

$composer = getenv('COMPOSER_BINARY') ?: 'composer';

/** @var DirectoryIterator $tool */
foreach (new DirectoryIterator($toolsDir) as $tool) {
    if (!$tool->isDir() || $tool->isDot() || !is_file($tool->getPathname() . '/composer.json')) {
        continue;
    }

    chdir($tool->getPathname());
    echo PHP_EOL . '####' . PHP_EOL . PHP_EOL;
    echo sprintf("Running \e[0;32mcomposer %s\e[0m in \e[0;35m%s\e[0m", $command, $tool->getFilename());
    echo PHP_EOL;

    passthru(
        sprintf('%s %s --ansi', escapeshellarg($composer), escapeshellarg($command)),
        $exitCode
    );
    chdir($toolsDir);

    if ($exitCode !== 0) {
        exit($exitCode);
    }
}
