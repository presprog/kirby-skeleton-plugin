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

        self::assertSame([], $extensions['fileMethods']);
        self::assertArrayHasKey('page.update:after', $extensions['hooks']);
        self::assertIsCallable($extensions['hooks']['page.update:after']);
        self::assertSame(['enabled' => true], $extensions['options']);
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
