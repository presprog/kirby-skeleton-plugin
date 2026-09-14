<?php declare(strict_types=1);

namespace PresProg\MyPlugin;

use InvalidArgumentException;
use LogicException;
use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * @psalm-suppress UnusedClass
 */
final class InitCommand extends Command
{
    private const DEFAULT_CLASS     = 'MyPlugin';
    private const DEFAULT_NAMESPACE = 'PresProg\\MyPlugin';
    private const DEFAULT_PACKAGE   = 'presprog/my-kirby-plugin';
    private const DEFAULT_PLUGIN    = 'presprog/my-kirby-plugin';
    private const DEFAULT_PREFIX    = 'presprog.my-kirby-plugin';
    private const DEFAULT_SLUG      = 'my-plugin';

    private string $root;

    public function __construct(?string $name = null)
    {
        parent::__construct($name ?? 'plugin:init');
        $this->root = dirname(__DIR__);
    }

    #[Override]
    protected function configure(): void
    {
        $this->setName('plugin:init')
            ->setDescription('Initializes the Kirby plugin.')
            ->addArgument('package', InputArgument::OPTIONAL, 'The Composer package name (e.g. your-vendor/kirby-do-something-plugin)')
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Override the inferred PHP namespace')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show derived values without changing files');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io        = new SymfonyStyle($input, $output);
        $package   = $input->getArgument('package');
        $namespace = $input->getOption('namespace');
        $dryRun    = (bool)$input->getOption('dry-run');

        if (is_string($package) && $package !== '') {
            $this->validatePackage($package);
        } else {
            $package = null;
        }

        if (is_string($namespace) && $namespace !== '') {
            $this->validateNamespace($namespace);
        } else {
            $namespace = null;
        }

        $identity = $this->identity($package, $namespace);

        if ($input->isInteractive()) {
            $identity = $this->promptIdentity($io, $identity);
        }

        if ($dryRun === true) {
            $this->success($output, $identity, true);
            return Command::SUCCESS;
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

        foreach ([
            $this->root . '/classes/InitCommand.php',
            $this->root . '/scripts/init.php',
            $this->root . '/README.dist.md',
            $this->root . '/tests/PluginInitializerTest.php'
        ] as $path) {
            if (is_file($path) && !unlink($path)) {
                $output->writeln('<error>Warning: Could not remove ' . basename($path) . '</error>');
            }
        }

        $this->success($output, $identity);

        return Command::SUCCESS;
    }

    private function validatePackage(string $package): void
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*\/[a-z0-9]+(?:-[a-z0-9]+)*$/', $package) !== 1) {
            throw new InvalidArgumentException('The package name must use lowercase kebab-case in the form vendor/package.');
        }
    }

    private function validateNamespace(string $namespace): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $namespace) !== 1) {
            throw new InvalidArgumentException('The namespace is not a valid PHP namespace.');
        }
    }

    /**
     * @return array{package: string, plugin: string, slug: string, namespace: string, class: string, title: string, description: string}
     */
    private function identity(?string $package = null, ?string $namespace = null): array
    {
        $package ??= 'your-vendor/kirby-do-something-plugin';
        [$vendor, $name] = explode('/', $package);

        $pluginName = preg_replace('/^kirby-/', '', $name) ?? $name;
        $pluginName = preg_replace('/-plugin$/', '', $pluginName) ?? $pluginName;
        $plugin     = $vendor . '/' . $pluginName;
        $slug       = $pluginName;

        $words = array_filter(
            explode(' ', (string)preg_replace('/[^a-z0-9]+/i', ' ', $slug)),
            static fn (string $word): bool => $word !== ''
        );

        $defaultClass = $words !== []
            ? implode('', array_map(static fn (string $word): string => ucfirst(strtolower($word)), $words))
            : self::DEFAULT_CLASS;

        if ($namespace !== null) {
            $parts     = explode('\\', $namespace);
            $className = end($parts) ?: $defaultClass;
        } else {
            $vendorNamespace = implode(
                '',
                array_map(
                    static fn (string $word): string => ucfirst(strtolower($word)),
                    array_filter(
                        explode(' ', (string)preg_replace('/[^a-z0-9]+/i', ' ', $vendor)),
                        static fn (string $word): bool => $word !== ''
                    )
                )
            );

            $className = $defaultClass;
            $namespace = ($vendorNamespace !== '' ? $vendorNamespace : 'YourVendor') . '\\' . $className;
        }

        $title = $words !== []
            ? implode(' ', array_map(static fn (string $word): string => ucfirst(strtolower($word)), $words))
            : 'Do Something';

        return [
            'package'     => $package,
            'plugin'      => $plugin,
            'slug'        => $slug,
            'namespace'   => $namespace,
            'class'       => $className,
            'title'       => $title,
            'description' => $title . ' for Kirby CMS'
        ];
    }

    /**
     * @param array{package: string, plugin: string, slug: string, namespace: string, class: string, title: string, description: string} $identity
     * @return array{package: string, plugin: string, slug: string, namespace: string, class: string, title: string, description: string}
     */
    private function promptIdentity(SymfonyStyle $io, array $identity): array
    {
        $package = (string)$io->ask(
            'Composer package',
            $identity['package'],
            function (?string $value): string {
                $value = trim((string)$value);
                if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*\/[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1) {
                    throw new InvalidArgumentException('The package name must use lowercase kebab-case in the form vendor/package.');
                }
                return $value;
            }
        );

        $plugin = (string)$io->ask(
            'Kirby plugin',
            $identity['plugin'],
            function (?string $value): string {
                $value = trim((string)$value);
                if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*\/[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1) {
                    throw new InvalidArgumentException('The plugin ID must use lowercase kebab-case in the form vendor/plugin-name.');
                }
                return $value;
            }
        );

        $slug = (string)$io->ask(
            'Plugin slug',
            $identity['slug'],
            function (?string $value): string {
                $value = trim((string)$value);
                if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1) {
                    throw new InvalidArgumentException('The plugin slug must use lowercase kebab-case.');
                }
                return $value;
            }
        );

        $namespace = (string)$io->ask(
            'PHP namespace',
            $identity['namespace'],
            function (?string $value): string {
                $value = trim((string)$value);
                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $value) !== 1) {
                    throw new InvalidArgumentException('The namespace is not a valid PHP namespace.');
                }
                return $value;
            }
        );

        $class = (string)$io->ask(
            'PHP class',
            $identity['class'],
            function (?string $value): string {
                $value = trim((string)$value);
                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
                    throw new InvalidArgumentException('The class name is not a valid PHP class name.');
                }
                return $value;
            }
        );

        $title = (string)$io->ask(
            'Plugin title',
            $identity['title'],
            function (?string $value): string {
                $value = trim((string)$value);
                if ($value === '') {
                    throw new InvalidArgumentException('The plugin title cannot be empty.');
                }
                return $value;
            }
        );

        $description = (string)$io->ask(
            'Plugin description',
            $identity['description'],
            function (?string $value): string {
                $value = trim((string)$value);
                if ($value === '') {
                    throw new InvalidArgumentException('The plugin description cannot be empty.');
                }
                return $value;
            }
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
        unset($composer['scripts']['plugin:init'], $composer['require-dev']['symfony/console']);


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

        foreach (['assets/dist', 'classes', 'extensions', 'resources', 'snippets', 'tests', 'translations'] as $directory) {
            if (!is_dir($this->root . '/' . $directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->root . '/' . $directory, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $pathname = $file->getPathname();
                    if ($pathname === $this->root . '/classes/InitCommand.php') {
                        continue;
                    }
                    $files[] = $pathname;
                }
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @param array<string, string> $files
     * @return array<string, string>
     */
    private function writeFiles(array $files): array
    {
        $originals = [];

        foreach ($files as $path => $contents) {
            $originals[$path] = $this->read($path);
            $this->write($path, $contents);
        }

        return $originals;
    }

    /**
     * @param array<string, string> $originals
     */
    private function restoreFiles(array $originals): void
    {
        foreach ($originals as $path => $contents) {
            $this->write($path, $contents);
        }
    }

    private function dumpAutoload(): void
    {
        $command = 'composer dump-autoload --working-dir=' . escapeshellarg($this->root);
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Could not refresh Composer autoload mappings.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encodeJson(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Could not read file: ' . $path);
        }

        return $contents;
    }

    private function write(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Could not write file: ' . $path);
        }
    }

    /**
     * @param array{package: string, plugin: string, slug: string, namespace: string, class: string, title: string, description: string} $identity
     */
    private function success(OutputInterface $output, array $identity, bool $dryRun = false): void
    {
        $output->writeln('');
        $output->writeln($dryRun ? 'Plugin initialization preview:' : 'Plugin initialized:');
        $output->writeln('  Composer package: ' . $identity['package']);
        $output->writeln('  Kirby plugin:     ' . $identity['plugin']);
        $output->writeln('  PHP namespace:    ' . $identity['namespace']);
        $output->writeln('  PHP class:        ' . $identity['namespace'] . '\\' . $identity['class']);
        $output->writeln('  Plugin directory: ' . $identity['slug']);

        if ($dryRun === true) {
            $output->writeln('');
            $output->writeln('No files changed.');
        }
    }
}
