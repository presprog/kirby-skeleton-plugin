<?php declare(strict_types=1);

namespace PresProg\MyPlugin\Tests;

use Kirby\Cms\App;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase
{
    public function testPluginIsRegistered(): void
    {
        $plugin = App::plugin('presprog/my-kirby-plugin');

        self::assertNotNull($plugin);
        self::assertSame('presprog/my-kirby-plugin', $plugin->name());
        self::assertSame(dirname(__DIR__), $plugin->root());
    }
}
