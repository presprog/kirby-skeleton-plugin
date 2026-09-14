<?php declare(strict_types=1);

namespace PresProg\MyPlugin\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use PresProg\MyPlugin\InitCommand;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

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
            'classes/InitCommand.php',
            'classes/MyPlugin.php',
            'classes/Options.php',
            'extensions/commands.php',
            'extensions/fields.php',
            'extensions/hooks.php',
            'extensions/methods.php',
            'extensions/options.php',
            'extensions/snippets.php',
            'helpers.php',
            'index.js',
            'index.php',
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
            'translations/en.yml'
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
        self::assertArrayNotHasKey('symfony/console', $composer['require-dev']);
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
        self::assertFileDoesNotExist($this->project . '/classes/InitCommand.php');
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
        self::assertFileExists($this->project . '/classes/InitCommand.php');
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

    public function testInitializesWithNoInteractionOption(): void
    {
        [$exitCode, $output, $error] = $this->runInitializer(
            'your-vendor/kirby-do-something-plugin',
            '--no-interaction',
            '--dry-run'
        );

        self::assertSame(0, $exitCode, $output . PHP_EOL . $error);
        self::assertStringNotContainsString('Composer package [', $output);
        self::assertStringNotContainsString('Kirby plugin [', $output);
        self::assertStringContainsString('Plugin initialization preview:', $output);
        self::assertStringContainsString('Kirby plugin:     your-vendor/do-something', $output);
    }

    public function testInitializesWithShortNoInteractionOption(): void
    {
        [$exitCode, $output, $error] = $this->runInitializer(
            'your-vendor/kirby-do-something-plugin',
            '-n',
            '--dry-run'
        );

        self::assertSame(0, $exitCode, $output . PHP_EOL . $error);
        self::assertStringNotContainsString('Composer package [', $output);
        self::assertStringNotContainsString('Kirby plugin [', $output);
        self::assertStringContainsString('Plugin initialization preview:', $output);
    }

    public function testInitializesInteractivelyWithCustomValues(): void
    {
        $input = implode(PHP_EOL, [
            'custom-vendor/kirby-custom-tool-plugin',
            'custom-vendor/custom-tool',
            'custom-tool',
            'CustomVendor\\CustomTool',
            'CustomTool',
            'Custom Tool',
            'Custom Tool for Kirby'
        ]) . PHP_EOL;

        [$exitCode, $output, $error] = $this->runInitializerWithInput(
            $input,
            'your-vendor/kirby-do-something-plugin'
        );

        self::assertSame(0, $exitCode, $output . PHP_EOL . $error);
        self::assertStringContainsString('Composer package [your-vendor/kirby-do-something-plugin]:', $output);
        self::assertStringContainsString('Kirby plugin [your-vendor/do-something]:', $output);
        self::assertStringContainsString('Plugin slug [do-something]:', $output);
        self::assertStringContainsString('PHP namespace [YourVendor\\DoSomething]:', $output);
        self::assertStringContainsString('PHP class [DoSomething]:', $output);
        self::assertStringContainsString('Plugin title [Do Something]:', $output);
        self::assertStringContainsString('Plugin description [Do Something for Kirby CMS]:', $output);

        $composer = json_decode(
            $this->read('composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame('custom-vendor/kirby-custom-tool-plugin', $composer['name']);
        self::assertSame('Custom Tool for Kirby', $composer['description']);
        self::assertSame('classes/', $composer['autoload']['psr-4']['CustomVendor\\CustomTool\\']);
        self::assertSame('tests/', $composer['autoload-dev']['psr-4']['CustomVendor\\CustomTool\\Tests\\']);
        self::assertSame('custom-tool', $composer['extra']['installer-name']);
        self::assertFileExists($this->project . '/classes/CustomTool.php');
        self::assertFileDoesNotExist($this->project . '/classes/MyPlugin.php');
        self::assertStringContainsString('class CustomTool', $this->read('classes/CustomTool.php'));
        self::assertStringContainsString('namespace CustomVendor\\CustomTool;', $this->read('classes/Options.php'));

        $readme = $this->read('README.md');
        self::assertStringContainsString('# Custom Tool', $readme);
        self::assertStringContainsString('Custom Tool for Kirby.', $readme);
        self::assertStringContainsString('composer require custom-vendor/kirby-custom-tool-plugin', $readme);
    }

    public function testRePromptsOnInvalidInteractiveInput(): void
    {
        $input = implode(PHP_EOL, [
            'INVALID_PACKAGE_NAME',
            'custom-vendor/kirby-valid-plugin',
            '', // default plugin
            '', // default slug
            '', // default namespace
            '', // default class
            '', // default title
            ''  // default description
        ]) . PHP_EOL;

        [$exitCode, $output, $error] = $this->runInitializerWithInput(
            $input,
            'your-vendor/kirby-do-something-plugin',
            '--dry-run'
        );

        self::assertSame(0, $exitCode, $output . PHP_EOL . $error);
        self::assertStringContainsString('The package name must use lowercase kebab-case', $output);
        self::assertStringContainsString('Composer package: custom-vendor/kirby-valid-plugin', $output);
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
        return $this->runInitializerWithInput('', ...$arguments);
    }

    /**
     * @return array{int, string, string}
     */
    private function runInitializerWithInput(string $input, string ...$arguments): array
    {
        $command = new InitCommand($this->project);
        $tester  = new CommandTester($command);

        if ($input !== '') {
            $tester->setInputs(explode(PHP_EOL, rtrim($input, "\r\n")));
        }

        $params        = [];
        $isInteractive = true;

        foreach ($arguments as $argument) {
            if ($argument === '--dry-run') {
                $params['--dry-run'] = true;
            } elseif ($argument === '--no-interaction' || $argument === '-n') {
                $isInteractive = false;
            } elseif (str_starts_with($argument, '--namespace=')) {
                $params['--namespace'] = substr($argument, strlen('--namespace='));
            } elseif (!str_starts_with($argument, '-')) {
                $params['package'] = $argument;
            }
        }

        $exitCode = $tester->execute($params, ['interactive' => $isInteractive]);
        $output   = $tester->getDisplay();

        return [$exitCode, $output, ''];
    }
}
