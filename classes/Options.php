<?php declare(strict_types=1);

namespace PresProg\MyPlugin;

/** @psalm-api */
final readonly class Options
{
    public function __construct(
        public bool $enabled
    ) {
    }

    /**
     * @param array{enabled?: bool} $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            enabled: $config['enabled'] ?? true
        );
    }
}
