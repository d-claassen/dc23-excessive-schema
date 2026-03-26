=== DC23 Excessive Schema ===
Contributors: dennisclaassen
Tags: schema, seo, yoast, structured-data, query-loop
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Template-level schema enrichment: connects WebPage to Query Loop sections via ItemList nodes.

== Description ==

DC23 Excessive Schema automatically enriches your site's structured data by detecting WordPress Query Loop blocks and creating proper Schema.org ItemList nodes that connect to your page's WebPage schema.

= Features =

* **Automatic Detection**: Finds all Query Loop blocks on any page
* **Smart Naming**: Uses block headings, titles, or post type labels
* **Dynamic Connection**: Uses `hasPart` or `mentions` based on page type
* **Reference-Only**: Points to existing schema nodes, no duplication
* **Universal**: Works on front page, archives, taxonomies, single posts, and more

= How It Works =

The plugin hooks into WordPress rendering to:
1. Detect Query Loop blocks during page render
2. Collect post IDs and section names
3. Inject ItemList nodes into the Yoast SEO schema graph
4. Connect them to the WebPage node using the appropriate property

= Requirements =

* WordPress 6.6 or higher
* PHP 8.2 or higher
* Yoast SEO plugin

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/dc23-excessive-schema/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Ensure Yoast SEO is installed and activated
4. Query Loop blocks will automatically generate ItemList schema

== Frequently Asked Questions ==

= Does this work with custom post types? =

Yes! The plugin works with any post type displayed in Query Loop blocks.

= Do I need to configure anything? =

No. The plugin works automatically once activated, as long as Yoast SEO is active.

= What if I don't use Query Loop blocks? =

The plugin will simply not add any schema. It only activates when Query Loop blocks are present.

== Changelog ==

= 0.1.0 =
* Initial release
* Query Loop detection and ItemList generation
* Dynamic connection property based on page type
* Support for all WordPress page types

== Upgrade Notice ==

= 0.1.0 =
Initial release of DC23 Excessive Schema.
