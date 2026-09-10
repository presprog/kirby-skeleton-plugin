![Kirby Skeleton Plugin](/.github/banner.png)

# My Kirby plugin readme

This is our boilerplate for Kirby plugins. Put a short description of what your plugin does here.

> ⚡ Requires Kirby 5 and PHP 8.4 or later.

----

<!-- plugin-init:start -->
> [!NOTE]
> Initialize the plugin after creating the project, either with the command or manually.

Create a project from this repository and initialize it with its Composer package name:

```bash
composer create-project presprog/kirby-skeleton-plugin my-new-kirby-plugin
cd my-new-kirby-plugin
composer plugin:init your-vendor/kirby-your-plugin
```

The initializer removes the conventional `kirby-` package prefix from the Kirby plugin ID and derives the remaining values. For example, `your-vendor/kirby-your-plugin` becomes plugin ID `your-vendor/your-plugin` and namespace `YourVendor\YourPlugin`. Use `--namespace=YourVendor\\YourPlugin` to override the inferred namespace.
Use `--dry-run` to preview the derived values without changing files.

To initialize the plugin manually instead:

1. Update `composer.json`:
   - Set the package name and description.
   - Replace the PSR-4 namespace.
   - Set the `installer-name`.
   - Remove the `plugin:init` script.
2. Replace the plugin ID in `index.php` and `panel/index.js`.
3. Install the frontend dependencies with `yarn install --immutable`, run
   `yarn build` and commit the generated Panel assets.
4. Update the namespace and plugin ID assertions in `tests/PluginTest.php`.
5. Replace the placeholder title, description and installation details in this README.
6. Delete `scripts/init.php`, `tests/PluginInitializerTest.php` and this
   initialization section.

----
<!-- plugin-init:end -->

## 🛠️ Panel development

Run `yarn dev` while developing the Panel interface and `yarn test:unit` for
Panel JavaScript tests. Run `yarn asset:check` before
committing changes. The generated root-level `index.js` and, when styles are
present, `index.css` must be committed so Composer installations are ready to
run without Node.js or Yarn. CI verifies that these files match the sources in
`panel/`.

## 🚀 How to use

…

## ⚙️ Config

…

## 💻 How to install

Install this plugin via **Composer**:

```bash
composer require presprog/my-kirby-plugin
```

## ✅ To do

…

## 📄 License

MIT License Copyright © 2026 Present Progressive

----

<img src=".github/logo.svg?raw=true" width="200" height="43">

Made by [Present Progressive](https://www.presentprogressive.de) for the Kirby community.
