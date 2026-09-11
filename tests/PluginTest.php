<?php declare(strict_types=1);

namespace PresProg\MyPlugin\Tests;

use Kirby\Cms\App;
use Kirby\Cms\Plugin;
use PHPUnit\Framework\TestCase;
use PresProg\MyPlugin\MyPlugin;
use PresProg\MyPlugin\Options;

final class PluginTest extends TestCase
{
    public function testPluginIsRegistered(): void
    {
        $plugin = App::plugin('presprog/my-kirby-plugin');

        self::assertInstanceOf(Plugin::class, $plugin);
        self::assertSame(dirname(__DIR__), $plugin->root());
    }

    public function testPluginRegistersExtensions(): void
    {
        $plugin = App::plugin('presprog/my-kirby-plugin');

        self::assertInstanceOf(Plugin::class, $plugin);

        $extensions = $plugin->extends();

        self::assertIsCallable($extensions['commands']['my-plugin:about']['command']);
        self::assertSame(
            'Prints information about the plugin.',
            $extensions['commands']['my-plugin:about']['description']
        );
        $cli = new class () {
            public string|null $output = null;

            public function out(string $message): void
            {
                $this->output = $message;
            }
        };
        $extensions['commands']['my-plugin:about']['command']($cli);
        self::assertSame('I am the Kirby skeleton plugin by Present Progressive', $cli->output);
        self::assertSame([], $extensions['fileMethods']);
        self::assertSame('text', $extensions['fields']['my-plugin-example']['extends']);
        self::assertArrayHasKey('page.update:after', $extensions['hooks']);
        self::assertIsCallable($extensions['hooks']['page.update:after']);
        self::assertSame(['enabled' => true], $extensions['options']);
        $pluginInstance = new MyPlugin(Options::fromConfig($extensions['options']));
        self::assertTrue($pluginInstance->options->enabled);
        self::assertSame([], $extensions['pageMethods']);
        self::assertSame([], $extensions['pagesMethods']);
        self::assertSame([], $extensions['siteMethods']);
        self::assertSame(
            dirname(__DIR__) . '/extensions/../snippets/example.php',
            $extensions['snippets']['presprog/my-kirby-plugin/example']
        );
        self::assertSame(
            'My Kirby plugin',
            $extensions['translations']['en']['presprog.my-kirby-plugin.example']
        );
        self::assertSame(
            'Mein Kirby Plugin',
            $extensions['translations']['de']['presprog.my-kirby-plugin.example']
        );
    }
}
