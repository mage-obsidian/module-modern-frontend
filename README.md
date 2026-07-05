<p align="center">
  <img src="https://raw.githubusercontent.com/mage-obsidian/.github/main/profile/assets/storefront-home.png" alt="MageObsidian storefront — a modern frontend for Magento" width="840">
</p>

<h1 align="center">MageObsidian — Modern Frontend for Magento 2</h1>

<p align="center">
  <a href="https://packagist.org/packages/mage-obsidian/module-modern-frontend"><img src="https://img.shields.io/packagist/v/mage-obsidian/module-modern-frontend.svg?style=flat-square" alt="Latest Version"></a>
  <a href="https://github.com/mage-obsidian/module-modern-frontend/actions/workflows/ci.yml"><img src="https://github.com/mage-obsidian/module-modern-frontend/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="https://packagist.org/packages/mage-obsidian/module-modern-frontend"><img src="https://img.shields.io/packagist/l/mage-obsidian/module-modern-frontend.svg?style=flat-square" alt="License"></a>
</p>

<p align="center">
  <a href="https://mage-obsidian.jeanmarcos.dev/">📚 Documentation</a> ·
  <a href="https://mage-obsidian-demo.jeanmarcos.dev/">🚀 Live demo (Lighthouse 100)</a> ·
  <a href="https://github.com/mage-obsidian/module-modern-frontend/discussions">💬 Discussions</a>
</p>

**MageObsidian** replaces Magento 2's legacy frontend stack (LESS, RequireJS, jQuery) with **Vite, Tailwind CSS 4, Vue 3 islands and native ESM** — while keeping Magento's Layouts, Blocks, Templates, theme inheritance and module system working exactly as you know them. No headless rewrite, no parallel stack.

This is the **core module**: it detects compatible modules/themes, generates the config contract consumed by the JS build engine, integrates with `setup:static-content:deploy`, and provides the ViewModels templates use to reach Vite output (including `renderVueComponent()` for Vue islands and JSON-LD emission).

## Installation

```bash
composer require mage-obsidian/component-modern-frontend   # pulls in this module
pnpm --prefix vite install
bin/magento setup:upgrade
bin/magento mage-obsidian:frontend:config --generate
```

Full guide: [Getting started](https://mage-obsidian.jeanmarcos.dev/getting-started/installation/).

## Highlights

- ⚡ **Vite dev server with HMR** inside Magento
- 🎨 **Tailwind CSS 4** with per-theme extension and inheritance
- 🏝️ **Vue 3 islands** mounted lazily over server-rendered pages
- 🧩 **JS interceptors** — Magento-style `before`/`around`/`after` plugins for JavaScript
- 🌿 Optional [Twig engine](https://github.com/mage-obsidian/module-modern-frontend-twig)

## The MageObsidian stack

| Package | Role |
|---|---|
| **module-modern-frontend** (this repo) | Core module: compatibility detection, config contract, deploy integration, ViewModels |
| [component-modern-frontend](https://github.com/mage-obsidian/component-modern-frontend) | Vite build harness mapped into the Magento root |
| [js-package-utils](https://github.com/mage-obsidian/js-package-utils) | JS build engine (npm: [`mage-obsidian`](https://www.npmjs.com/package/mage-obsidian)) |
| [module-modern-frontend-cli](https://github.com/mage-obsidian/module-modern-frontend-cli) | `bin/magento` CLI commands |
| [module-storefront](https://github.com/mage-obsidian/module-storefront) + `module-*` | Luma-parity storefront and domain compatibility modules |

## Support the project

If MageObsidian saves you time, consider [buying me a coffee](https://ko-fi.com/Q5Q816Z9WN). ❤️
