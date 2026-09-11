<?php declare(strict_types=1);

namespace PresProg\MyPlugin;

/** @psalm-api */
final readonly class MyPlugin
{
    public function __construct(
        public Options $options
    ) {
    }
}
