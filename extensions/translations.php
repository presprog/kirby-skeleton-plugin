<?php declare(strict_types=1);

use Kirby\Data\Data;

return [
    'de' => Data::read(__DIR__ . '/../translations/de.yml'),
    'en' => Data::read(__DIR__ . '/../translations/en.yml')
];
