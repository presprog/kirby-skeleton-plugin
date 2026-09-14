<?php declare(strict_types=1);

namespace PresProg\MyPlugin\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class PluginInitializerTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/kirby-plugin-init-' . bin2hex(random_bytes(8));

        foreach ([
            'assets',
            'assets/dist',
            'classes',
            'extensions',
            'methods',
            'resources',
            'resources/frontend',
            'resources/panel',
            'resources/panel/fields',
            'scripts',
            'snippets',
            'tests',
            'translations',
            'tools'
        ] as $directory) {
            if (!mkdir($this->project . '/' . $directory, 0777, true)) {
                throw new RuntimeException('Could not create fixture directory: ' . $directory);
            }
        }

        foreach ([
            'composer.json',
            'classes/MyPlugin.php',
            'classes/Options.php',
            'extensions/commands.php',
            'extensions/fields.php',
            'extensions/hooks.php',
            'extensions/options.php',
            'extensions/snippets.php',
            'helpers.php',
            'index.js',
            'index.php',
            'methods/file.php',
            'methods/page.php',
            'methods/pages.php',
            'methods/site.php',
            'README.md',
            'README.dist.md',
            'assets/dist/frontend.css',
            'assets/dist/frontend.js',
            'resources/frontend/index.css',
            'resources/frontend/index.js',
            'resources/frontend/index.test.js',
            'resources/panel/index.css',
            'resources/panel/fields/example.js',
            'resources/panel/index.js',
            'resources/panel/index.test.js',
            'snippets/example.php',
            'tests/PluginTest.php',
            'translations/de.yml',
            'translations/en.yml',
            'scripts/init.php'
        ] as $path) {
            if (!copy(dirname(__DIR__) . '/' . $path, $this->project . '/' . $path)) {
                throw new RuntimeException('Could not copy fixture file: ' . $path);
            }
        }
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->project)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->project, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($this->project);
    }

    public function testInitializesCopiedSkeleton(): void
    {
        [$exitCode, $output, $error] = $this->runInitializer('your-vendor/kirby-do-something-plugin');

        self::assertSame(0, $exitCode, $output . PHP_EOL . $error);

        $composer = json_decode(
            $this->read('composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame('your-vendor/kirby-do-something-plugin', $composer['name']);
        self::assertSame('classes/', $composer['autoload']['psr-4']['YourVendor\\DoSomething\\']);
        self::assertArrayNotHasKey('PresProg\\MyPlugin\\', $composer['autoload']['psr-4']);
        self::assertSame('tests/', $composer['autoload-dev']['psr-4']['YourVendor\\DoSomething\\Tests\\']);
        self::assertArrayNotHasKey('PresProg\\MyPlugin\\Tests\\', $composer['autoload-dev']['psr-4']);
        self::assertSame('do-something', $composer['extra']['installer-name']);
        self::assertArrayNotHasKey('plugin:init', $composer['scripts']);
        self::assertFileExists($this->project . '/classes/DoSomething.php');
        self::assertFileDoesNotExist($this->project . '/classes/MyPlugin.php');
        self::assertStringContainsString('class DoSomething', $this->read('classes/DoSomething.php'));
        self::assertStringContainsString('namespace YourVendor\\DoSomething;', $this->read('classes/Options.php'));
        self::assertStringContainsString(
            'function doSomething(array $config): \\YourVendor\\DoSomething\\DoSomething',
            $this->read('helpers.php')
        );
        self::assertStringContainsString(
            'return new YourVendor\\DoSomething\\DoSomething(Options::fromConfig($config));',
            $this->read('helpers.php')
        );

        self::assertStringContainsString(
            "App::plugin('your-vendor/do-something'",
            $this->read('index.php')
        );
        self::assertStringContainsString(
            "'do-something:about'",
            $this->read('extensions/commands.php')
        );
        self::assertStringContainsString(
            "'your-vendor/do-something/example'",
            $this->read('extensions/snippets.php')
        );
        self::assertStringContainsString(
            'your-vendor.do-something.example',
            $this->read('translations/en.yml')
        );
        self::assertStringContainsString(
            'panel.plugin("your-vendor/do-something"',
            $this->read('resources/panel/index.js')
        );
        self::assertStringContainsString(
            '"do-something-example"',
            $this->read('resources/panel/index.js')
        );
        self::assertStringContainsString(
            "'do-something-example'",
            $this->read('extensions/fields.php')
        );
        self::assertStringContainsString(
            'panel.plugin).toHaveBeenCalledWith("your-vendor/do-something"',
            $this->read('resources/panel/index.test.js')
        );
        self::assertStringContainsString(
            'panel.plugin("your-vendor/do-something"',
            $this->read('index.js')
        );
        self::assertStringContainsString(
            '"do-something-example"',
            $this->read('index.js')
        );
        self::assertStringContainsString(
            'your-vendor/do-something',
            $this->read('resources/frontend/index.js')
        );
        self::assertStringContainsString(
            'your-vendor/do-something',
            $this->read('assets/dist/frontend.js')
        );
        $pluginTest = $this->read('tests/PluginTest.php');
        self::assertStringContainsString(
            'namespace YourVendor\\DoSomething\\Tests;',
            $pluginTest
        );
        self::assertStringContainsString(
            "App::plugin('your-vendor/do-something')",
            $pluginTest
        );
        self::assertStringContainsString(
            'use YourVendor\\DoSomething\\DoSomething;',
            $pluginTest
        );
        self::assertStringContainsString(
            'new DoSomething(Options::fromConfig($extensions[\'options\']))',
            $pluginTest
        );

        $readme = $this->read('README.md');
        self::assertStringContainsString('# Do Something', $readme);
        self::assertStringContainsString('composer require your-vendor/kirby-do-something-plugin', $readme);
        self::assertStringContainsString('your-vendor.do-something.enabled', $readme);
        self::assertStringContainsString('type: do-something-example', $readme);
        self::assertStringContainsString('kirby do-something:about', $readme);
        self::assertStringContainsString('utm_content=do-something', $readme);
        self::assertStringNotContainsString('utm_content=my-kirby-plugin', $readme);

        self::assertFileExists($this->project . '/vendor/autoload.php');
        self::assertFileDoesNotExist($this->project . '/README.dist.md');
        self::assertFileDoesNotExist($this->project . '/scripts/init.php');
        self::assertFileDoesNotExist($this->project . '/tests/PluginInitializerTest.php');
    }

    public function testPreviewsInitializationWithoutChangingFiles(): void
    {
        $composerBefore = $this->read('composer.json');

        [$exitCode, $output, $error] = $this->runInitializer(
            'your-vendor/kirby-do-something-plugin',
            '--dry-run'
        );

        self::assertSame(0, $exitCode, $output . PHP_EOL . $error);
        self::assertStringContainsString('Plugin initialization preview:', $output);
        self::assertStringContainsString('Kirby plugin:     your-vendor/do-something', $output);
        self::assertStringContainsString('PHP class:        YourVendor\\DoSomething\\DoSomething', $output);
        self::assertStringContainsString('No files changed.', $output);
        self::assertSame($composerBefore, $this->read('composer.json'));
        self::assertFileExists($this->project . '/README.dist.md');
        self::assertFileExists($this->project . '/scripts/init.php');
        self::assertFileExists($this->project . '/classes/MyPlugin.php');
    }

    public function testStripsPrefixAndSuffixCombinationsInPreview(): void
    {
        [$exitCode, $output] = $this->runInitializer(
            'your-vendor/kirby-do-something-plugin',
            '--dry-run'
        );
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Kirby plugin:     your-vendor/do-something', $output);

        [$exitCode, $output] = $this->runInitializer(
            'your-vendor/kirby-do-something',
            '--dry-run'
        );
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Kirby plugin:     your-vendor/do-something', $output);

        [$exitCode, $output] = $this->runInitializer(
            'your-vendor/do-something-plugin',
            '--dry-run'
        );
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Kirby plugin:     your-vendor/do-something', $output);
    }

    public function testInitializesWithCustomNamespace(): void
    {
        [$exitCode, $output, $error] = $this->runInitializer(
            'your-vendor/kirby-do-something-plugin',
            '--namespace=YourVendor\\DoSomething',
            '--dry-run'
        );

        self::assertSame(0, $exitCode, $output . PHP_EOL . $error);
        self::assertStringContainsString('PHP namespace:    YourVendor\\DoSomething', $output);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($this->project . '/' . $path);

        if ($contents === false) {
            throw new RuntimeException('Could not read fixture file: ' . $path);
        }

        return $contents;
    }

    /**
     * @return array{int, string, string}
     */
    private function runInitializer(string ...$arguments): array
    {
        $process = proc_open(
            [PHP_BINARY, $this->project . '/scripts/init.php', ...$arguments],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ],
            $pipes,
            $this->project
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Could not start the initializer.');
        }

        $output   = stream_get_contents($pipes[1]);
        $error    = stream_get_contents($pipes[2]);
        $exitCode = proc_close($process);

        return [$exitCode, $output, $error];
    }
}
