# Lookit Cache Purge for Sucuri

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/lookit-cache-purge-for-sucuri.svg)](https://wordpress.org/plugins/lookit-cache-purge-for-sucuri/)
[![License](https://img.shields.io/badge/license-GPLv2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Lint](https://github.com/Lookit-Design/sucuri-cache-purge/actions/workflows/lint.yml/badge.svg)](../../actions/workflows/lint.yml)
[![Coding Standards](https://github.com/Lookit-Design/sucuri-cache-purge/actions/workflows/coding-standards.yml/badge.svg)](../../actions/workflows/coding-standards.yml)
[![Plugin Check](https://github.com/Lookit-Design/sucuri-cache-purge/actions/workflows/plugin-check.yml/badge.svg)](../../actions/workflows/plugin-check.yml)
[![Tests](https://github.com/Lookit-Design/sucuri-cache-purge/actions/workflows/test.yml/badge.svg)](../../actions/workflows/test.yml)

Surgical Sucuri cache control from the WordPress admin bar — purge the page you are editing or the entire site, right from wp-admin.

Supports `WordPress >= 5.9` on `PHP >= 7.4`.

## Features

* **Single-URL purge**: Clear only the page you changed and leave the rest of your cache warm.
* **Context-aware**: Detects the URL of the post or page you are editing or viewing — in both the wp-admin editor and on the frontend (when logged in).
* **Full-site purge**: Available when you genuinely need it, behind a confirmation dialog so it is never accidental.
* **Rate limited**: Caps purges at 6 per minute per user to prevent accidental hammering, and handles Sucuri's own HTTP 429 responses with clear retry-wait messages.
* **Stays out of your settings**: Calls only Sucuri's cache-purge action — never reads or changes WAF rules, SSL, IP allowlists, or any other Sucuri configuration.
* **Secure by design**: Never echoes the saved API key back to the browser, keeps it out of autoloaded options, and removes it on uninstall.
* **Compatible**: Works alongside WP Rocket, the official Sucuri plugin, or any other caching setup.

## Table of Contents

- [Getting Started](#getting-started)
  - [Installation](#installation)
  - [Getting Your Sucuri API Key](#getting-your-sucuri-api-key)
  - [Configuration](#configuration)
- [Usage](#usage)
- [Why Single-URL Purging](#why-single-url-purging)
- [Compatibility](#compatibility)
- [Security and Privacy](#security-and-privacy)
- [Frequently Asked Questions](#frequently-asked-questions)
- [Development](#development)
  - [Setup](#setup)
  - [Running the Test Suite](#running-the-test-suite)
  - [Coding Standards](#coding-standards)
  - [Continuous Integration](#continuous-integration)
- [Deployment](#deployment)
- [Contributing](#contributing)
- [License](#license)

## Getting Started

### Installation

From the WordPress.org plugin directory:

1. In wp-admin, go to **Plugins → Add New** and search for "Lookit Cache Purge for Sucuri".
2. Click **Install Now**, then **Activate**.

Or install manually:

1. Download the plugin and upload the `lookit-cache-purge-for-sucuri` folder to `/wp-content/plugins/`.
2. Activate it through the **Plugins** menu in WordPress.

### Getting Your Sucuri API Key

Log into your Sucuri Website Firewall (WAF) dashboard, select your site, then go to **API → API Details**. Copy the value labeled **"API Key (for plugin)"** — a single string in the format `32characters/32characters`.

> Use the combined **"API Key (for plugin)"** value, not the separate "API Key" and "API Secret" fields.

You will need a Sucuri Website Firewall account with your site connected.

### Configuration

1. Go to **Settings → Sucuri Cache Purge**.
2. Paste your Sucuri **API Key (for plugin)**.
3. Save. The **🛡 Sucuri Cache Purge** menu now appears in your admin bar.

## Usage

From the **🛡 Sucuri Cache Purge** admin bar menu:

* **Purge This URL** — clears only the page you are currently on or editing. It appears whenever the plugin can resolve a canonical URL for the current view (singular posts and pages, taxonomy and author archives, the homepage, and the post editor screen).
* **Purge Entire Site** — a full cache purge, behind a confirmation dialog.

A toast notification confirms success or failure after every purge. Because Sucuri reports success for any well-formed path — even one that does not exist — the plugin only purges URLs WordPress itself generates, rather than offering a free-text URL field that could silently "succeed" on a typo.

## Why Single-URL Purging

Sucuri's Website Firewall caches your pages on its global edge network. When you purge the entire cache, every cached page is cleared at once — but Sucuri does not rebuild those caches for you. A page is only re-cached *after* the next request for it, and that first request is a cache miss that goes all the way back to your origin. Busy pages re-cache almost immediately, but low-traffic pages can sit uncached far longer.

Single-URL purging avoids this: you clear only the page you actually changed, and every other page keeps its warm cache. Sucuri's dashboard can clear a single URL too, but only by leaving WordPress, logging into the WAF dashboard, and pasting the path by hand — this plugin puts that button in the admin bar instead.

Two Sucuri behaviors are worth knowing:

* **Static files cache for up to 72 hours.** Images, CSS, JS, PDFs, and fonts are cached on Sucuri's edge regardless of per-URL purging. To force new versions, use **Purge Entire Site** or version the asset (`?ver=1.2.3`).
* **Propagation takes up to 2 minutes.** After a successful purge, Sucuri needs up to two minutes to flush the cache across all edge servers. If you do not see your change immediately, wait and reload.

## Compatibility

This plugin connects directly to the Sucuri Website Firewall API and does not depend on any other plugin. It works alongside WP Rocket, the official Sucuri plugin, or any other caching setup.

WP Rocket's Sucuri add-on only triggers a full-site purge when WP Rocket clears its own cache — the nuclear option. This plugin adds the per-URL control that add-on does not offer, and it never touches your Sucuri configuration.

## Security and Privacy

* The API Key is stored in a single WordPress option and is **never** rendered back into the settings form — the field is always blank, and submitting it blank keeps the saved value.
* The credentials option is **not autoloaded**, so the key is not pulled into memory on every front-end request.
* On uninstall, the stored credentials are **removed from the database**.

The plugin sends data to Sucuri only when you trigger a purge. Each request includes your API Key (for authentication) and, for a single-URL purge, the path of the page being purged — sent directly to `https://waf.sucuri.net/api?v2`. No visitor data, personal information, or site content is ever transmitted.

See Sucuri's [Terms of Service](https://sucuri.net/terms/) and [Privacy Policy](https://sucuri.net/privacy/).

## Frequently Asked Questions

A full FAQ is available on the [WordPress.org plugin page](https://wordpress.org/plugins/lookit-cache-purge-for-sucuri/). A few common questions:

* **Where do I find my Sucuri API Key?** In your Sucuri WAF dashboard under **API → API Details** — copy the combined "API Key (for plugin)" value.
* **Why is there no manual URL field?** Sucuri reports success for any well-formed path, so a typo would look like it worked. The plugin only purges URLs WordPress can resolve, to avoid silent failures.
* **Will it change my Sucuri settings?** No. It only calls Sucuri's `clear_cache` action and cannot read or modify any other configuration.
* **Why is my change not visible right away?** Sucuri takes up to two minutes to propagate a purge across its edge network — this is normal, not a plugin fault.

## Development

### Setup

Install the development dependencies with [Composer](https://getcomposer.org/):

```bash
composer install
```

### Running the Test Suite

The integration tests run against a real WordPress test install and a MySQL database. Install the test suite once, then run PHPUnit:

```bash
# bin/install-wp-tests.sh <db-name> <db-user> <db-pass> <db-host> <wp-version>
bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest

composer test
```

The AJAX handler tests run in a separate process group and must be invoked explicitly:

```bash
vendor/bin/phpunit --group ajax
```

### Coding Standards

This project follows the WordPress Coding Standards and checks PHP cross-version compatibility:

```bash
composer phpcs    # check coding standards
composer phpcbf   # auto-fix what can be fixed
composer compat   # check PHP 7.4+ compatibility
composer lint     # php -l syntax check on all files
```

### Continuous Integration

Every push and pull request runs the following GitHub Actions workflows:

| Workflow | Purpose |
| --- | --- |
| [Lint](../../actions/workflows/lint.yml) | `php -l` syntax check across the supported PHP versions |
| [Coding Standards](../../actions/workflows/coding-standards.yml) | WordPress Coding Standards (PHPCS) |
| [Plugin Check](../../actions/workflows/plugin-check.yml) | Official WordPress Plugin Check, including readme validation |
| [Test](../../actions/workflows/test.yml) | PHPUnit across a broad WordPress × PHP matrix |

A scheduled [Version Monitor](../../actions/workflows/version-monitor.yml) workflow watches for new PHP and WordPress releases so compatibility can be reviewed proactively.

## Deployment

See [DEPLOY.md](DEPLOY.md) for the release and WordPress.org deployment process.

## Contributing

Bug reports and pull requests are welcome on [GitHub](../../issues).

## License

This plugin is available as open source under the terms of the [GPL-2.0-or-later License](https://www.gnu.org/licenses/gpl-2.0.html).

---

_Lookit&reg; is a registered trademark of ZENOVA CORP. Sucuri is a trademark of its respective owner (a GoDaddy brand); this plugin is an independent integration and is not affiliated with, sponsored by, or endorsed by Sucuri._
