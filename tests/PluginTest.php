<?php declare(strict_types=1);

namespace PresProg\MyPlugin\Tests;

use Kirby\Cms\App;
use Kirby\Plugin\Plugin;
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

        self::assertSame(['enabled' => true], $extensions['options']);
        $pluginInstance = new MyPlugin(Options::fromConfig());
        self::assertTrue($pluginInstance->options->enabled);
        self::assertArrayNotHasKey('api', $extensions);
        self::assertArrayNotHasKey('blueprints', $extensions);
        self::assertArrayNotHasKey('commands', $extensions);
        self::assertArrayNotHasKey('fields', $extensions);
        self::assertArrayNotHasKey('hooks', $extensions);
        self::assertArrayNotHasKey('methods', $extensions);
        self::assertArrayNotHasKey('routes', $extensions);
        self::assertArrayNotHasKey('snippets', $extensions);
        self::assertArrayNotHasKey('fileMethods', $extensions);
        self::assertArrayNotHasKey('pageMethods', $extensions);
        self::assertArrayNotHasKey('siteMethods', $extensions);
        self::assertArrayNotHasKey('pagesMethods', $extensions);
        self::assertSame(
            'My Kirby plugin',
            $extensions['translations']['en']['presprog.my-kirby-plugin.example']
        );
        self::assertSame(
            'Mein Kirby Plugin',
            $extensions['translations']['de']['presprog.my-kirby-plugin.example']
        );
    }

    public function testHelperFunctionReturnsPluginInstance(): void
    {
        $pluginInstance = myPlugin();

        self::assertInstanceOf(MyPlugin::class, $pluginInstance);
        self::assertTrue($pluginInstance->options->enabled);
    }

    public function testHelperFunctionWithCustomKirbyInstance(): void
    {
        $kirby = $this->createMock(App::class);
        $kirby->method('option')
            ->with('presprog.my-kirby-plugin.enabled', true)
            ->willReturn(false);

        $pluginInstance = myPlugin($kirby);

        self::assertInstanceOf(MyPlugin::class, $pluginInstance);
        self::assertFalse($pluginInstance->options->enabled);
    }
}
