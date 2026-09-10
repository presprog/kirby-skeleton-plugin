<?php declare(strict_types=1);

final class PluginInitializer
{
    private const DEFAULT_NAMESPACE = 'PresProg\\MyPlugin';
    private const DEFAULT_PACKAGE   = 'presprog/my-kirby-plugin';
    private const DEFAULT_PLUGIN    = 'presprog/my-kirby-plugin';

    public function __construct(private readonly string $root)
    {
    }

    /**
     * @param list<string> $arguments
     */
    public function run(array $arguments): int
    {
        if ($arguments === ['--help'] || $arguments === ['-h']) {
            $this->usage(STDOUT);
            return 0;
        }

        [$package, $namespace] = $this->parseArguments($arguments);
        $identity              = $this->identity($package, $namespace);
        $files                 = $this->prepareFiles($identity);

        $originals = $this->writeFiles($files);

        try {
            $this->dumpAutoload();
        } catch (Throwable $exception) {
            $this->restoreFiles($originals);

            try {
                $this->dumpAutoload();
            } catch (Throwable) {
                // Preserve the original initialization failure.
            }

            throw $exception;
        }

        foreach ([__FILE__, $this->root . '/tests/PluginInitializerTest.php'] as $path) {
            if (is_file($path) && !unlink($path)) {
                fwrite(STDERR, 'Warning: Could not remove ' . basename($path) . PHP_EOL);
            }
        }

        echo PHP_EOL;
        echo 'Plugin initialized:' . PHP_EOL;
        echo '  Composer package: ' . $identity['package'] . PHP_EOL;
        echo '  Kirby plugin:     ' . $identity['plugin'] . PHP_EOL;
        echo '  PHP namespace:    ' . $identity['namespace'] . PHP_EOL;
        echo '  Plugin directory: ' . $identity['slug'] . PHP_EOL;

        return 0;
    }

    /**
     * @param list<string> $arguments
     * @return array{string, string|null}
     */
    private function parseArguments(array $arguments): array
    {
        $package   = null;
        $namespace = null;

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--namespace=')) {
                $namespace = substr($argument, strlen('--namespace='));
                continue;
            }

            if (str_starts_with($argument, '-')) {
                throw new InvalidArgumentException('Unknown option: ' . $argument);
            }

            if ($package !== null) {
                throw new InvalidArgumentException('Only one package name can be provided.');
            }

            $package = $argument;
        }

        if ($package === null) {
            $this->usage(STDERR);
            throw new InvalidArgumentException('Missing package name.');
        }

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*\/[a-z0-9]+(?:-[a-z0-9]+)*$/', $package) !== 1) {
            throw new InvalidArgumentException(
                'The package name must use lowercase kebab-case in the form vendor/package.'
            );
        }

        if ($namespace !== null && preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $namespace) !== 1) {
            throw new InvalidArgumentException('The namespace is not a valid PHP namespace.');
        }

        return [$package, $namespace];
    }

    /**
     * @return array{package: string, plugin: string, slug: string, namespace: string, title: string, description: string}
     */
    private function identity(string $package, ?string $namespace): array
    {
        [$vendor, $packageName] = explode('/', $package, 2);
        $slug                   = str_starts_with($packageName, 'kirby-') ? substr($packageName, 6) : $packageName;

        if ($slug === '') {
            throw new InvalidArgumentException('The package name must contain a name after the kirby- prefix.');
        }

        $title = implode(' ', array_map(
            static fn (string $part): string => ucfirst($part),
            explode('-', $slug)
        ));

        return [
            'package'     => $package,
            'plugin'      => $vendor . '/' . $slug,
            'slug'        => $slug,
            'namespace'   => $namespace ?? $this->pascalCase($vendor) . '\\' . $this->pascalCase($slug),
            'title'       => $title,
            'description' => $title . ' for Kirby CMS'
        ];
    }

    private function pascalCase(string $value): string
    {
        return implode('', array_map(
            static fn (string $part): string => ucfirst($part),
            explode('-', $value)
        ));
    }

    /**
     * @param array{package: string, plugin: string, slug: string, namespace: string, title: string, description: string} $identity
     * @return array<string, string>
     */
    private function prepareFiles(array $identity): array
    {
        $composerPath = $this->root . '/composer.json';
        $composer     = json_decode($this->read($composerPath), true, 512, JSON_THROW_ON_ERROR);

        if (($composer['name'] ?? null) !== self::DEFAULT_PACKAGE) {
            throw new LogicException('This project has already been initialized.');
        }

        if (($composer['autoload']['psr-4'][self::DEFAULT_NAMESPACE . '\\'] ?? null) !== 'classes/') {
            throw new LogicException('Could not find the skeleton PSR-4 namespace.');
        }

        $composer['name']        = $identity['package'];
        $composer['description'] = $identity['description'];
        unset($composer['autoload']['psr-4'][self::DEFAULT_NAMESPACE . '\\']);
        $composer['autoload']['psr-4'][$identity['namespace'] . '\\'] = 'classes/';
        $composer['extra']['installer-name']                          = $identity['slug'];
        unset($composer['scripts']['plugin:init']);

        $files = [
            $composerPath              => $this->encodeJson($composer),
            $this->root . '/README.md' => $this->prepareReadme($identity)
        ];

        foreach ($this->sourceFiles() as $path) {
            $contents     = $this->read($path);
            $contents     = str_replace(self::DEFAULT_NAMESPACE, $identity['namespace'], $contents);
            $contents     = str_replace(self::DEFAULT_PLUGIN, $identity['plugin'], $contents);
            $files[$path] = $contents;
        }

        return $files;
    }

    /**
     * @param array{package: string, plugin: string, slug: string, namespace: string, title: string, description: string} $identity
     */
    private function prepareReadme(array $identity): string
    {
        $readme = $this->read($this->root . '/README.md');
        $readme = str_replace('![Kirby Skeleton Plugin]', '![' . $identity['title'] . ']', $readme);
        $readme = str_replace('# My Kirby plugin readme', '# ' . $identity['title'], $readme);
        $readme = str_replace(
            'This is our boilerplate for Kirby plugins. Put a short description of what your plugin does here.',
            $identity['description'] . '.',
            $readme
        );
        $readme = str_replace('composer require ' . self::DEFAULT_PACKAGE, 'composer require ' . $identity['package'], $readme);
        $readme = str_replace('site/plugins/my-kirby-plugin', 'site/plugins/' . $identity['slug'], $readme);

        $readme = preg_replace(
            '/\n<!-- plugin-init:start -->.*?<!-- plugin-init:end -->\n/s',
            "\n",
            $readme,
            1,
            $count
        );

        if ($readme === null || $count !== 1) {
            throw new LogicException('Could not find the initialization section in README.md.');
        }

        return $readme;
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $files = [
            $this->root . '/helpers.php',
            $this->root . '/index.js',
            $this->root . '/index.php',
            $this->root . '/panel/index.js'
        ];

        foreach (['classes', 'extensions', 'tests'] as $directory) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->root . '/' . $directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        foreach ([$this->root . '/index.js', $this->root . '/index.php', $this->root . '/panel/index.js'] as $path) {
            if (!str_contains($this->read($path), self::DEFAULT_PLUGIN)) {
                throw new LogicException('Could not find the skeleton plugin ID in ' . $path);
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encodeJson(array $data): string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return preg_replace_callback(
            '/^( +)/m',
            static fn (array $match): string => str_repeat(' ', intdiv(strlen($match[1]), 2)),
            $json
        ) . PHP_EOL;
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Could not read ' . $path);
        }

        return $contents;
    }

    /**
     * @param array<string, string> $files
     * @return array<string, string>
     */
    private function writeFiles(array $files): array
    {
        $originals = [];

        try {
            foreach ($files as $path => $contents) {
                $originals[$path] = $this->read($path);

                if (file_put_contents($path, $contents, LOCK_EX) === false) {
                    throw new RuntimeException('Could not write ' . $path);
                }
            }
        } catch (Throwable $exception) {
            foreach ($originals as $path => $contents) {
                file_put_contents($path, $contents, LOCK_EX);
            }

            throw $exception;
        }

        return $originals;
    }

    /**
     * @param array<string, string> $files
     */
    private function restoreFiles(array $files): void
    {
        foreach ($files as $path => $contents) {
            file_put_contents($path, $contents, LOCK_EX);
        }
    }

    private function dumpAutoload(): void
    {
        $composer = getenv('COMPOSER_BINARY') ?: 'composer';
        passthru(
            escapeshellarg($composer) . ' --working-dir=' . escapeshellarg($this->root) . ' dump-autoload --no-interaction',
            $exitCode
        );

        if ($exitCode !== 0) {
            throw new RuntimeException('Composer could not regenerate the autoloader.');
        }
    }

    /**
     * @param resource $stream
     */
    private function usage($stream): void
    {
        fwrite($stream, 'Usage: composer plugin:init vendor/kirby-plugin-name [--namespace=Vendor\\PluginName]' . PHP_EOL);
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        exit((new PluginInitializer(dirname(__DIR__)))->run(array_slice($argv, 1)));
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Initialization failed: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
