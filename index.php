<?php declare(strict_types=1);

@include_once __DIR__ . '/vendor/autoload.php';
@include_once __DIR__ . '/helpers.php';

\Kirby\Cms\App::plugin('presprog/my-kirby-plugin', [
    'hooks'    => require __DIR__ . '/extensions/hooks.php',
    'snippets' => require __DIR__ . '/extensions/snippets.php'
]);
