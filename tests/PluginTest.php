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
}
