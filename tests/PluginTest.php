<?php declare(strict_types=1);

namespace PresProg\MyPlugin\Tests;

use Kirby\Cms\App;
use Kirby\Cms\Plugin;
use PHPUnit\Framework\TestCase;

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

        self::assertArrayHasKey('page.update:after', $extensions['hooks']);
        self::assertIsCallable($extensions['hooks']['page.update:after']);
        self::assertSame(
            dirname(__DIR__) . '/extensions/../snippets/example.php',
            $extensions['snippets']['presprog/my-kirby-plugin/example']
        );
    }
}
