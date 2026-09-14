<?php declare(strict_types=1);

namespace PresProg\MyPlugin;

use Kirby\Cms\App;

/** @psalm-api */
final readonly class Options
{
    public function __construct(
        public bool $enabled
    ) {
    }

    public static function fromConfig(?App $kirby = null): self
    {
        $kirby ??= App::instance(null, true);

        return new self(
            enabled: (bool)($kirby?->option('presprog.my-kirby-plugin.enabled', true) ?? true)
        );
    }
}
