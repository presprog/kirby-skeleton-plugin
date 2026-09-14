<?php declare(strict_types=1);

final class PluginInitializer
{
    private const DEFAULT_CLASS     = 'MyPlugin';
    private const DEFAULT_NAMESPACE = 'PresProg\\MyPlugin';
    private const DEFAULT_PACKAGE   = 'presprog/my-kirby-plugin';
    private const DEFAULT_PLUGIN    = 'presprog/my-kirby-plugin';
    private const DEFAULT_PREFIX    = 'presprog.my-kirby-plugin';
    private const DEFAULT_SLUG      = 'my-plugin';

    /**
     * @var resource
     */
    private mixed $input;

    /**
     * @var resource
     */
    private mixed $output;

    /**
     * @param resource|null $input
     * @param resource|null $output
     */
    public function __construct(
        private readonly string $root,
        $input = null,
        $output = null
    ) {
        $this->input  = is_resource($input) ? $input : (defined('STDIN') ? STDIN : fopen('php://stdin', 'r'));
        $this->output = is_resource($output) ? $output : (defined('STDOUT') ? STDOUT : fopen('php://stdout', 'w'));
    }

    /**
     * @param list<string> $arguments
     */
    public function run(array $arguments): int
    {
        if ($arguments === ['--help'] || $arguments === ['-h']) {
            $this->usage($this->output);
            return 0;
        }

        try {
            [$package, $namespace, $dryRun, $noInteraction] = $this->parseArguments($arguments);
        } catch (InvalidArgumentException $exception) {
            $this->usage(STDERR);
            fwrite(STDERR, PHP_EOL . 'Error: ' . $exception->getMessage() . PHP_EOL);
            return 1;
        }

        $identity = $this->identity($package, $namespace);

        if ($noInteraction === false) {
            $identity = $this->interact($identity);
        }

        if ($dryRun === true) {
            $this->success($identity, true);
            return 0;
        }

        $files = $this->prepareFiles($identity);

        $originals = $this->writeFiles($files);
        $classMove = null;

        try {
            $classMove = $this->renameClassFile($identity['class']);
            $this->dumpAutoload();
        } catch (Throwable $exception) {
            if ($classMove !== null && is_file($classMove['to'])) {
                unlink($classMove['to']);
            }

            $this->restoreFiles($originals);

            try {
                $this->dumpAutoload();
            } catch (Throwable) {
                // Preserve the original initialization failure.
            }

            throw $exception;
        }

        foreach ([__FILE__, $this->root . '/README.dist.md', $this->root . '/tests/PluginInitializerTest.php'] as $path) {
            if (is_file($path) && !unlink($path)) {
                fwrite(STDERR, 'Warning: Could not remove ' . basename($path) . PHP_EOL);
            }
        }

        $this->success($identity);

        return 0;
    }

    /**
     * @param list<string> $arguments
     * @return array{string, string|null, bool, bool}
     */
    private function parseArguments(array $arguments): array
    {
        $package       = null;
        $namespace     = null;
        $dryRun        = false;
        $noInteraction = false;

        foreach ($arguments as $argument) {
            if ($argument === '--dry-run') {
                $dryRun = true;
                continue;
            }

            if ($argument === '--no-interaction' || $argument === '-n') {
                $noInteraction = true;
                continue;
            }

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

        return [$package, $namespace, $dryRun, $noInteraction];
    }

    /**
     * @return array{package: string, plugin: string, slug: string, namespace: string, class: string, title: string, description: string}
     */
    private function identity(string $package, ?string $namespace): array
    {
        [$vendor, $packageName] = explode('/', $package, 2);
        $slug                   = str_starts_with($packageName, 'kirby-') ? substr($packageName, 6) : $packageName;
        $slug                   = str_ends_with($slug, '-plugin') ? substr($slug, 0, -7) : $slug;

        if ($slug === '') {
            throw new InvalidArgumentException('The package name must contain a name between the kirby- prefix and -plugin suffix.');
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
            'class'       => $this->pascalCase($slug),
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
     * @param array{package: string, plugin: string, slug: string, namespace: string, class: string, title: string, description: string} $identity
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
        if (isset($composer['autoload-dev']['psr-4'][self::DEFAULT_NAMESPACE . '\\Tests\\'])) {
            unset($composer['autoload-dev']['psr-4'][self::DEFAULT_NAMESPACE . '\\Tests\\']);
            $composer['autoload-dev']['psr-4'][$identity['namespace'] . '\\Tests\\'] = 'tests/';
        }
        $composer['extra']['installer-name'] = $identity['slug'];
        unset($composer['scripts']['plugin:init']);

        $files = [
            $composerPath              => $this->encodeJson($composer),
            $this->root . '/README.md' => $this->prepareReadme($identity)
        ];

        foreach ($this->sourceFiles() as $path) {
            $contents     = $this->read($path);
            $contents     = str_replace(self::DEFAULT_NAMESPACE, $identity['namespace'], $contents);
            $contents     = str_replace(self::DEFAULT_PLUGIN, $identity['plugin'], $contents);
            $contents     = str_replace(self::DEFAULT_PREFIX, str_replace('/', '.', $identity['plugin']), $contents);
            $contents     = str_replace(self::DEFAULT_SLUG . '-example', $identity['slug'] . '-example', $contents);
            $contents     = str_replace(self::DEFAULT_SLUG . ':', $identity['slug'] . ':', $contents);
            $contents     = str_replace(lcfirst(self::DEFAULT_CLASS), lcfirst($identity['class']), $contents);
            $contents     = str_replace(self::DEFAULT_CLASS, $identity['class'], $contents);
            $files[$path] = $contents;
        }

        return $files;
    }

    /**
     * @param array{package: string, plugin: string, slug: string, namespace: string, class: string, title: string, description: string} $identity
     */
    private function prepareReadme(array $identity): string
    {
        $readme = $this->read($this->root . '/README.dist.md');
        $readme = str_replace('![Kirby Skeleton Plugin]', '![' . $identity['title'] . ']', $readme);
        $readme = str_replace('# My Kirby Plugin', '# ' . $identity['title'], $readme);
        $readme = str_replace(
            'Describe what this Kirby plugin does and when someone should install it.',
            rtrim($identity['description'], '.') . '.',
            $readme
        );
        $readme = str_replace('composer require ' . self::DEFAULT_PACKAGE, 'composer require ' . $identity['package'], $readme);
        $readme = str_replace('site/plugins/my-kirby-plugin', 'site/plugins/' . $identity['slug'], $readme);
        $readme = str_replace('utm_content=my-kirby-plugin', 'utm_content=' . $identity['slug'], $readme);
        $readme = str_replace(self::DEFAULT_PLUGIN, $identity['plugin'], $readme);
        $readme = str_replace(self::DEFAULT_PREFIX, str_replace('/', '.', $identity['plugin']), $readme);
        $readme = str_replace(self::DEFAULT_SLUG . '-example', $identity['slug'] . '-example', $readme);
        $readme = str_replace(self::DEFAULT_SLUG . ':', $identity['slug'] . ':', $readme);

        return $readme;
    }

    /**
     * @return array{from: string, to: string}|null
     */
    private function renameClassFile(string $class): array|null
    {
        $from = $this->root . '/classes/' . self::DEFAULT_CLASS . '.php';
        $to   = $this->root . '/classes/' . $class . '.php';

        if ($from === $to || is_file($from) === false) {
            return null;
        }

        if (is_file($to) === true) {
            throw new LogicException('Could not rename the plugin class because ' . $to . ' already exists.');
        }

        if (rename($from, $to) === false) {
            throw new RuntimeException('Could not rename the plugin class to ' . basename($to) . '.');
        }

        return [
            'from' => $from,
            'to'   => $to
        ];
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $files = [];

        foreach ([
            $this->root . '/helpers.php',
            $this->root . '/index.js',
            $this->root . '/index.php'
        ] as $path) {
            if (is_file($path)) {
                $files[] = $path;
            }
        }

        foreach (['assets/dist', 'classes', 'extensions', 'methods', 'resources', 'snippets', 'tests', 'translations'] as $directory) {
            if (!is_dir($this->root . '/' . $directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->root . '/' . $directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['css', 'js', 'php', 'ts', 'vue', 'yml'], true) === true) {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        foreach ([
            $this->root . '/index.js'                    => self::DEFAULT_PLUGIN,
            $this->root . '/index.php'                   => self::DEFAULT_PLUGIN,
            $this->root . '/resources/frontend/index.js' => self::DEFAULT_PLUGIN,
            $this->root . '/resources/panel/index.js'    => self::DEFAULT_PLUGIN
        ] as $path => $needle) {
            if (!is_file($path)) {
                continue;
            }

            if (!str_contains($this->read($path), $needle)) {
                throw new LogicException('Could not find the skeleton placeholder in ' . $path);
            }
        }

        $classPath = $this->root . '/classes/' . self::DEFAULT_CLASS . '.php';

        if (is_file($classPath) && preg_match('/\bclass\s+' . preg_quote(self::DEFAULT_CLASS, '/') . '\b/', $this->read($classPath)) !== 1) {
            throw new LogicException('Could not find the skeleton placeholder in ' . $classPath);
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
        fwrite(
            $stream,
            <<<'TXT'
Usage:
  composer plugin:init your-vendor/kirby-do-something-plugin [options]

Options:
  --namespace=YourVendor\PluginName  Override the inferred PHP namespace.
  -n, --no-interaction               Do not ask any interactive question.
  --dry-run                          Show derived values without changing files.
  -h, --help                         Show this help.

Examples:
  composer plugin:init your-vendor/kirby-do-something-plugin
  composer plugin:init your-vendor/kirby-do-something-plugin --namespace=YourVendor\DoSomething
  composer plugin:init your-vendor/kirby-do-something-plugin --no-interaction

TXT
        );
    }

    /**
     * @param array{package: string, plugin: string, slug: string, namespace: string, class: string, title: string, description: string} $identity
     * @return array{package: string, plugin: string, slug: string, namespace: string, class: string, title: string, description: string}
     */
    private function interact(array $identity): array
    {
        $package = $this->ask(
            'Composer package',
            $identity['package'],
            static fn (string $value): bool => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*\/[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1,
            'The package name must use lowercase kebab-case in the form vendor/package.'
        );

        $plugin = $this->ask(
            'Kirby plugin',
            $identity['plugin'],
            static fn (string $value): bool => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*\/[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1,
            'The plugin ID must use lowercase kebab-case in the form vendor/plugin-name.'
        );

        $slug = $this->ask(
            'Plugin slug',
            $identity['slug'],
            static fn (string $value): bool => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1,
            'The plugin slug must use lowercase kebab-case.'
        );

        $namespace = $this->ask(
            'PHP namespace',
            $identity['namespace'],
            static fn (string $value): bool => preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $value) === 1,
            'The namespace is not a valid PHP namespace.'
        );

        $class = $this->ask(
            'PHP class',
            $identity['class'],
            static fn (string $value): bool => preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1,
            'The class name is not a valid PHP class name.'
        );

        $title = $this->ask(
            'Plugin title',
            $identity['title'],
            static fn (string $value): bool => trim($value) !== '',
            'The plugin title cannot be empty.'
        );

        $description = $this->ask(
            'Plugin description',
            $identity['description'],
            static fn (string $value): bool => trim($value) !== '',
            'The plugin description cannot be empty.'
        );

        return [
            'package'     => $package,
            'plugin'      => $plugin,
            'slug'        => $slug,
            'namespace'   => $namespace,
            'class'       => $class,
            'title'       => $title,
            'description' => $description
        ];
    }

    /**
     * @param callable(string): bool|null $validator
     */
    private function ask(string $label, string $default, ?callable $validator = null, ?string $errorMessage = null): string
    {
        while (true) {
            fwrite($this->output, $label . ' [' . $default . ']: ');
            $input = fgets($this->input);

            if ($input === false) {
                return $default;
            }

            $value = trim($input);

            if ($value === '') {
                return $default;
            }

            if ($validator === null || $validator($value) === true) {
                return $value;
            }

            if ($errorMessage !== null) {
                fwrite($this->output, 'Error: ' . $errorMessage . PHP_EOL);
            }
        }
    }

    /**
     * @param array{package: string, plugin: string, slug: string, namespace: string, class: string, title: string, description: string} $identity
     */
    private function success(array $identity, bool $dryRun = false): void
    {
        fwrite($this->output, PHP_EOL);
        fwrite($this->output, ($dryRun ? 'Plugin initialization preview:' : 'Plugin initialized:') . PHP_EOL);
        fwrite($this->output, '  Composer package: ' . $identity['package'] . PHP_EOL);
        fwrite($this->output, '  Kirby plugin:     ' . $identity['plugin'] . PHP_EOL);
        fwrite($this->output, '  PHP namespace:    ' . $identity['namespace'] . PHP_EOL);
        fwrite($this->output, '  PHP class:        ' . $identity['namespace'] . '\\' . $identity['class'] . PHP_EOL);
        fwrite($this->output, '  Plugin directory: ' . $identity['slug'] . PHP_EOL);

        if ($dryRun === true) {
            fwrite($this->output, PHP_EOL . 'No files changed.' . PHP_EOL);
        }
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
