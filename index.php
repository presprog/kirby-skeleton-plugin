<?php declare(strict_types=1);

@include_once __DIR__ . '/vendor/autoload.php';
@include_once __DIR__ . '/helpers.php';

$methods = require __DIR__ . '/extensions/methods.php';

\Kirby\Cms\App::plugin('presprog/my-kirby-plugin', [
    'api'          => require __DIR__ . '/extensions/api.php',
    'blueprints'   => require __DIR__ . '/extensions/blueprints.php',
    'commands'     => require __DIR__ . '/extensions/commands.php',
    'fields'       => require __DIR__ . '/extensions/fields.php',
    'fileMethods'  => $methods['fileMethods'] ?? [],
    'hooks'        => require __DIR__ . '/extensions/hooks.php',
    'options'      => require __DIR__ . '/extensions/options.php',
    'pageMethods'  => $methods['pageMethods'] ?? [],
    'routes'       => require __DIR__ . '/extensions/routes.php',
    'siteMethods'  => $methods['siteMethods'] ?? [],
    'snippets'     => require __DIR__ . '/extensions/snippets.php',
    'translations' => require __DIR__ . '/extensions/translations.php'
]);
