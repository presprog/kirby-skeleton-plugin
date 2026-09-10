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
            'resources',
            'resources/frontend',
            'resources/panel',
            'scripts',
            'tests',
            'tools'
        ] as $directory) {
            if (!mkdir($this->project . '/' . $directory, 0777, true)) {
                throw new RuntimeException('Could not create fixture directory: ' . $directory);
            }
        }

        foreach ([
            'composer.json',
            'helpers.php',
            'index.js',
            'index.php',
            'README.md',
            'assets/dist/frontend.css',
            'assets/dist/frontend.js',
            'resources/frontend/index.css',
            'resources/frontend/index.js',
            'resources/frontend/index.test.js',
            'resources/panel/index.css',
            'resources/panel/index.js',
            'resources/panel/index.test.js',
            'tests/PluginTest.php',
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
        [$exitCode, $output, $error] = $this->runInitializer('your-vendor/kirby-your-plugin');

        self::assertSame(0, $exitCode, $output . PHP_EOL . $error);

        $composer = json_decode(
            $this->read('composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame('your-vendor/kirby-your-plugin', $composer['name']);
        self::assertSame('classes/', $composer['autoload']['psr-4']['YourVendor\\YourPlugin\\']);
        self::assertArrayNotHasKey('PresProg\\MyPlugin\\', $composer['autoload']['psr-4']);
        self::assertSame('your-plugin', $composer['extra']['installer-name']);
        self::assertArrayNotHasKey('plugin:init', $composer['scripts']);

        self::assertStringContainsString(
            "App::plugin('your-vendor/your-plugin'",
            $this->read('index.php')
        );
        self::assertStringContainsString(
            'panel.plugin("your-vendor/your-plugin"',
            $this->read('resources/panel/index.js')
        );
        self::assertStringContainsString(
            'panel.plugin).toHaveBeenCalledWith("your-vendor/your-plugin"',
            $this->read('resources/panel/index.test.js')
        );
        self::assertStringContainsString(
            'panel.plugin("your-vendor/your-plugin"',
            $this->read('index.js')
        );
        self::assertStringContainsString(
            'your-vendor/your-plugin',
            $this->read('resources/frontend/index.js')
        );
        self::assertStringContainsString(
            'your-vendor/your-plugin',
            $this->read('assets/dist/frontend.js')
        );
        $pluginTest = $this->read('tests/PluginTest.php');
        self::assertStringContainsString(
            'namespace YourVendor\\YourPlugin\\Tests;',
            $pluginTest
        );
        self::assertStringContainsString(
            "App::plugin('your-vendor/your-plugin')",
            $pluginTest
        );

        $readme = $this->read('README.md');
        self::assertStringContainsString('composer require your-vendor/kirby-your-plugin', $readme);
        self::assertStringNotContainsString('plugin-init:start', $readme);

        self::assertFileExists($this->project . '/vendor/autoload.php');
        self::assertFileDoesNotExist($this->project . '/scripts/init.php');
        self::assertFileDoesNotExist($this->project . '/tests/PluginInitializerTest.php');
    }

    public function testPreviewsInitializationWithoutChangingFiles(): void
    {
        $composerBefore = $this->read('composer.json');

        [$exitCode, $output, $error] = $this->runInitializer(
            'your-vendor/kirby-your-plugin',
            '--dry-run'
        );

        self::assertSame(0, $exitCode, $output . PHP_EOL . $error);
        self::assertStringContainsString('Plugin initialization preview:', $output);
        self::assertStringContainsString('Kirby plugin:     your-vendor/your-plugin', $output);
        self::assertStringContainsString('No files changed.', $output);
        self::assertSame($composerBefore, $this->read('composer.json'));
        self::assertFileExists($this->project . '/scripts/init.php');
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
