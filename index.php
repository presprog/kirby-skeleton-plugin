<?php declare(strict_types=1);

@include_once __DIR__ . '/vendor/autoload.php';
@include_once __DIR__ . '/helpers.php';

\Kirby\Cms\App::plugin('presprog/my-kirby-plugin', [
    'fileMethods'  => require __DIR__ . '/methods/file.php',
    'fields'       => require __DIR__ . '/extensions/fields.php',
    'hooks'        => require __DIR__ . '/extensions/hooks.php',
    'options'      => require __DIR__ . '/extensions/options.php',
    'pageMethods'  => require __DIR__ . '/methods/page.php',
    'pagesMethods' => require __DIR__ . '/methods/pages.php',
    'siteMethods'  => require __DIR__ . '/methods/site.php',
    'snippets'     => require __DIR__ . '/extensions/snippets.php',
    'translations' => require __DIR__ . '/extensions/translations.php'
]);
