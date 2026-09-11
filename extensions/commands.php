<?php declare(strict_types=1);

return [
    'my-plugin:about' => [
        'description' => 'Prints information about the plugin.',
        'args'        => [],
        'command'     => static function ($cli): void {
            $cli->out('I am the Kirby skeleton plugin by Present Progressive');
        }
    ]
];
