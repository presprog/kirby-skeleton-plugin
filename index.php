<?php declare(strict_types=1);

use Kirby\Cms\App;

@include_once __DIR__ . '/vendor/autoload.php';
@include_once __DIR__ . '/helpers.php';

App::plugin('presprog/my-kirby-plugin', [
    'options'      => require __DIR__ . '/extensions/options.php',
    'translations' => require __DIR__ . '/extensions/translations.php'
]);
