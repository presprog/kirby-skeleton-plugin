<?php declare(strict_types=1);

return [
    'my-plugin-example' => [
        'extends' => 'text',
        'props'   => [
            'placeholder' => fn (string|null $placeholder = null): string => $placeholder ?? 'Example value'
        ]
    ]
];
