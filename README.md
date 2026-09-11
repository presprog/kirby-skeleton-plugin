![Kirby Skeleton Plugin](/.github/banner.png)

# Kirby Skeleton Plugin

This repository is our opinionated starting point for Kirby 5 plugins to save us the time and effort of setting up a new plugin.

> Requires Kirby 5 and PHP 8.4 or later.

## What it provides

- Kirby plugin registration with starter extensions for commands, config options, fields, hooks, methods, snippets and translations
- Composer setup with isolated quality tools via `bamarni/composer-bin-plugin`
- PHPUnit, Psalm, PHP CS Fixer and Composer validation scripts
- Panel and frontend asset tooling with Yarn, Kirbyup, Vite and Vitest
- Committed generated assets so Composer installations are ready to run
- `docs/` for plugin documentation and `README.dist.md` as the derived-plugin README template

## Initialize a plugin

Create a project from this repository and initialize it with its Composer package name:

```bash
composer create-project presprog/kirby-skeleton-plugin my-new-kirby-plugin
cd my-new-kirby-plugin
composer plugin:init your-vendor/kirby-your-plugin
```

The initializer removes the conventional `kirby-` package prefix from the Kirby plugin ID and derives the remaining values. For example, `your-vendor/kirby-your-plugin` becomes plugin ID `your-vendor/your-plugin` and namespace `YourVendor\YourPlugin`.

Use `--namespace=YourVendor\\YourPlugin` to override the inferred namespace. Use `--dry-run` to preview the derived values without changing files.

To initialize the plugin manually instead:

1. Update `composer.json`:
   - Set the package name and description.
   - Replace the PSR-4 namespace.
   - Set the `installer-name`.
   - Remove the `plugin:init` script.
2. Rename `classes/MyPlugin.php` and the `MyPlugin` class.
3. Replace the plugin ID in `index.php`, `resources/panel/index.js` and `resources/frontend/index.js`.
4. Replace derived names such as `my-plugin-example`, `my-plugin:about` and `presprog.my-kirby-plugin.*`.
5. Use `README.dist.md` as the basis for the plugin README and then delete `README.dist.md`.
6. Install the frontend dependencies with `yarn install --immutable`, run `yarn build` and commit the generated assets.
7. Update the namespace and plugin ID assertions in `tests/PluginTest.php`.
8. Delete `scripts/init.php`, `tests/PluginInitializerTest.php` and this initialization-specific documentation.

## Development

Run PHP checks:

```bash
composer analyze
```

Run JavaScript tests:

```bash
yarn test:unit
```

Run the asset reproducibility check before committing Panel or frontend asset changes:

```bash
yarn asset:check
```

Use `yarn dev:panel` while developing the Panel interface and `yarn dev:frontend` while developing public frontend assets. The generated root-level `index.js` and, when styles are present, `index.css` must be committed for Kirby's Panel autoloading. Generated frontend assets in `assets/dist/` must also be committed so Composer installations are ready to run without Node.js or Yarn. CI verifies that these files match the sources in `resources/`.

### Test Pipeline & CI

The automated GitHub Actions workflow (`.github/workflows/tests.yml`) validates pull requests and pushes against `main`:

- **Panel & Assets**: Tests JavaScript components with Vitest (`yarn test:unit`) and verifies that committed compiled assets match their source files (`yarn asset:check`).
- **PHP Matrix**: Tests PHPUnit test suites and static analysis (`composer analyze`) across PHP 8.4 and 8.5 against both `lowest` and `latest` stable dependencies, plus experimental builds against Kirby pre-releases (Kirby 6 Alpha).

## License

MIT License Copyright © 2026 Present Progressive

----

<img src=".github/logo.svg?raw=true" width="200" height="43">

Made by [Present Progressive](https://www.presentprogressive.de) for the Kirby community.

*This skeleton was created with AI assistance.*
