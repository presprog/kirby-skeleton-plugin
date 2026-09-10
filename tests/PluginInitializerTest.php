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
            'classes',
            'extensions',
            'panel',
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
            'panel/index.js',
            'tests/PluginTest.php',
            'tools/init.php'
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
        $process = proc_open(
            [PHP_BINARY, $this->project . '/tools/init.php', 'your-vendor/kirby-your-plugin'],
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
            $this->read('panel/index.js')
        );
        self::assertStringContainsString(
            'panel.plugin("your-vendor/your-plugin"',
            $this->read('index.js')
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
        self::assertFileDoesNotExist($this->project . '/tools/init.php');
        self::assertFileDoesNotExist($this->project . '/tests/PluginInitializerTest.php');
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($this->project . '/' . $path);

        if ($contents === false) {
            throw new RuntimeException('Could not read fixture file: ' . $path);
        }

        return $contents;
    }
}
