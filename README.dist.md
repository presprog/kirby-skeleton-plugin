![Kirby Skeleton Plugin](/.github/banner.png)

# My Kirby Plugin

Describe what this Kirby plugin does and when someone should install it.

> Requires Kirby 5 and PHP 8.4 or later.

## Features

- Register Kirby extension examples for options, fields, hooks, methods, snippets and translations.
- Provide Panel and frontend asset entry points.
- Include tests and code quality tooling for ongoing plugin development.

## Installation

Install the plugin with Composer:

```bash
composer require presprog/my-kirby-plugin
```

## Usage

Describe the main workflow here. Include short examples for templates, blueprints or Panel usage when they help users get started.

## Configuration

Plugin options are configured in `site/config/config.php`:

```php
<?php

return [
    'presprog.my-kirby-plugin.enabled' => true
];
```

Instantiate the main plugin class with `Options::fromConfig()` when plugin code needs typed access to the configured values.

## Panel Field

The skeleton includes an example Panel field scaffold. Use it in a blueprint like this:

```yaml
fields:
  example:
    label: Example
    type: my-plugin-example
```

## CLI

The example CLI command can be run with the Kirby CLI:

```bash
kirby my-plugin:about
```

## Development

Run PHP checks:

```bash
composer analyze
```

Run JavaScript tests:

```bash
yarn test:unit
```

Check that generated assets match their sources:

```bash
yarn asset:check
```

### Test Pipeline & CI

The automated GitHub Actions workflow (`.github/workflows/tests.yml`) validates pull requests and pushes against `main`:

- **Panel & Assets**: Tests JavaScript components with Vitest (`yarn test:unit`) and verifies that committed compiled assets match their source files (`yarn asset:check`).
- **PHP Matrix**: Tests PHPUnit test suites and static analysis across PHP 8.4 and 8.5 against `lowest`, `latest`, and pre-release dependencies.

## License

MIT License Copyright © 2026 Present Progressive

----

<img src=".github/logo.svg?raw=true" width="200" height="43">

Made by [Present Progressive](https://www.presentprogressive.de) for the Kirby community.
