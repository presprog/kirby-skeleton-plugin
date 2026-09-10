<?php declare(strict_types=1);

namespace PresProg\MyPlugin\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

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
            self::assertTrue(mkdir($this->project . '/' . $directory, 0777, true));
        }

        foreach ([
            'composer.json',
            'helpers.php',
            'index.php',
            'README.md',
            'panel/index.js',
            'tests/PluginTest.php',
            'tools/init.php'
        ] as $path) {
            self::assertTrue(copy(dirname(__DIR__) . '/' . $path, $this->project . '/' . $path));
        }

        self::assertNotFalse(file_put_contents(
            $this->project . '/classes/Example.php',
            '<?php namespace PresProg\\MyPlugin; final class Example {}' . PHP_EOL
        ));
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

        self::assertIsResource($process);

        $output   = stream_get_contents($pipes[1]);
        $error    = stream_get_contents($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, $output . PHP_EOL . $error);
        self::assertStringContainsString('Composer package: your-vendor/kirby-your-plugin', $output);
        self::assertStringContainsString('Kirby plugin:     your-vendor/your-plugin', $output);
        self::assertStringContainsString('PHP namespace:    YourVendor\\YourPlugin', $output);

        $composer = json_decode(
            $this->read('composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame('your-vendor/kirby-your-plugin', $composer['name']);
        self::assertSame('Your Plugin for Kirby CMS', $composer['description']);
        self::assertSame('classes/', $composer['autoload']['psr-4']['YourVendor\\YourPlugin\\']);
        self::assertArrayNotHasKey('PresProg\\MyPlugin\\', $composer['autoload']['psr-4']);
        self::assertSame('your-plugin', $composer['extra']['installer-name']);
        self::assertArrayNotHasKey('plugin:init', $composer['scripts']);
        self::assertSame('^5.0', $composer['require-dev']['getkirby/cms']);

        self::assertStringContainsString(
            "App::plugin('your-vendor/your-plugin'",
            $this->read('index.php')
        );
        self::assertStringContainsString(
            'panel.plugin("your-vendor/your-plugin"',
            $this->read('panel/index.js')
        );
        self::assertStringContainsString(
            'namespace YourVendor\\YourPlugin;',
            $this->read('classes/Example.php')
        );
        self::assertStringContainsString(
            'namespace YourVendor\\YourPlugin\\Tests;',
            $this->read('tests/PluginTest.php')
        );

        $readme = $this->read('README.md');
        self::assertStringContainsString('# Your Plugin', $readme);
        self::assertStringContainsString('composer require your-vendor/kirby-your-plugin', $readme);
        self::assertStringNotContainsString('plugin-init:start', $readme);

        self::assertFileExists($this->project . '/vendor/autoload.php');
        self::assertFileDoesNotExist($this->project . '/tools/init.php');
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($this->project . '/' . $path);
        self::assertNotFalse($contents);

        return $contents;
    }
}
