# Saeid Scrapper Project

Saeid Scrapper is a WordPress plugin that helps WooCommerce shop managers import large batches of external product URLs, scrape their details, and synchronise categories before publishing new products. The plugin was designed around the markup used on [tehran-control.com](https://tehran-control.com/product/atv310hu55n4e/) and handles duplicate detection, structured data parsing, and live progress reporting while the scraper is running.

## Repository layout

```
wp-content/plugins/saeid-scrapper/
├── assets/                 # Admin JavaScript and CSS used by the plugin UI
├── languages/              # Translation placeholders
├── README.md               # Persian end-user guide for the plugin
└── saeid-scrapper.php      # Main plugin implementation
```

The project root additionally contains automated tests and Composer configuration used for local development.

## Prerequisites

* PHP 7.4 or newer with the DOM and JSON extensions enabled.
* A WordPress installation with WooCommerce activated.
* Composer for installing development dependencies (PHPUnit).

## Installation

1. Copy the `wp-content/plugins/saeid-scrapper` directory into the same location inside your WordPress project.
2. Log in to the WordPress dashboard and activate **Saeid Scrapper** from the Plugins page. Activation creates the custom database table used to store source URLs.
3. Ensure WooCommerce is active so that the scraper can create `product` posts.

## Key capabilities

* **Bulk URL collection** – import hundreds of source addresses in one form submission while the plugin prevents duplicates via URL hashes.
* **Category synchronisation** – run the “همگام‌سازی دسته‌بندی” tool to detect products that already exist and create any missing product categories from the destination site before scraping begins.
* **Product scraper** – queue stored URLs for scraping, stream live logs, show remaining URLs, and render a progress bar with ETA calculations. Created WooCommerce products include titles, descriptions, slugs, and structured specifications but intentionally skip featured images.
* **Source-aware parsing** – extract data from WooCommerce layouts, Elementor blocks, additional information tables, and JSON-LD schema to match the content structure used on tehran-control.com.

## Recommended workflow

1. **Bulk import** all target URLs from the admin submenu.
2. Launch the **category synchronisation** page to build matching WooCommerce categories while identifying URLs whose products already exist.
3. Run the **scraper** once categories are prepared. Monitor logs for failures and review the success/error tables to track progress.
4. Repeat the scraper run if new URLs are added later; already-processed entries are skipped automatically.

## Testing

Automated tests validate the parser utilities that power the scraper. To run them locally:

```bash
composer install
vendor/bin/phpunit
```

The first command installs PHPUnit; the second executes the test suite defined in `phpunit.xml`. If your environment blocks access to packagist.org, configure Composer to use an alternative mirror before running `composer install`.

## Development tips

* Test scraping in a staging environment before running against production sites.
* Use the live log stream to monitor HTTP errors from the source site—adjust the request rate if you encounter rate limits.
* Extend the parser cautiously: prefer augmenting the HTML/XPath logic or JSON-LD handling instead of replacing it, and add unit tests covering new branches.

## License

This project does not include an explicit license. Consult the repository owner before redistributing or using the code commercially.
