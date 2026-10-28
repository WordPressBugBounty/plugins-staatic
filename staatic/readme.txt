=== Staatic - Static Site Generator for WordPress ===
Contributors: staatic
Tags: performance, seo, security, static, speed
Stable tag: 1.15.0
Tested up to: 7.1.3
Requires at least: 5.0
Requires PHP: 7.1
License: BSD-3-Clause

Use WordPress as your CMS, then publish a fast, lower-exposure static version of your site.

== Description ==

Staatic turns WordPress into a practical static site generator. Keep editing posts, pages, menus, media, and settings in WordPress, then publish a static copy made of HTML, images, CSS, JavaScript, and other assets.

The public site can be served without WordPress, PHP, or a database in the visitor request path. That can improve performance, reduce origin load, and shrink the public attack surface while preserving the familiar WordPress editing workflow.

This is especially useful for a private-origin, public-static setup: WordPress remains available for editors and publication work, while visitors receive prebuilt files from your chosen hosting target.

Staatic includes the publication workflow and deployment methods needed to run a self-hosted static WordPress site.

Features of Staatic include:

* Crawls your WordPress site and builds a static publication from the pages and assets it discovers.
* Publishes from WordPress Admin, with publication status, logs, resource details, and downloadable output.
* Deploys to Local Directory, Amazon S3 or S3-compatible storage, GitHub, Netlify, SFTP, or a zip archive.
* Supports redirects, custom 404 pages, additional URLs and paths, exclude rules, and HTTP headers.
* Includes a WP-CLI command for command-line publication workflows.
* Works with WordPress Multisite, WPML, and HTTP Basic Auth protected WordPress origins.
* Helps separate the public site from WordPress admin, plugin runtime, and database-backed page generation.
* Provides integration hooks and compatibility improvements for common WordPress setups.

Staatic Premium and Staatic Cloud add features such as forms integration, search integration, scheduled publications, change-based publications, and managed static WordPress hosting. The Community edition remains suitable when you want to generate and deploy a static version of a WordPress site yourself.

== Installation ==

Installing Staatic is simple!

### Install from within WordPress

1. Visit the plugins page within your WordPress Admin dashboard and select ‘Add New’;
2. Search for ‘Staatic’;
3. Activate ‘Staatic’ from your Plugins page;
4. Go to ‘After activation’ below.

### Install manually

1. Upload the ‘staatic’ folder to the `/wp-content/plugins/` directory;
2. Activate the ‘Staatic’ plugin through the ‘Plugins’ menu in WordPress;
3. Go to ‘After activation’ below.

### After activation

1. Click on the ‘Staatic’ menu item on the left side navigation menu;
2. On the settings page, provide the relevant Build & Deployment settings;
3. Start publishing to your static site!

== Frequently Asked Questions ==

= How will Staatic improve the performance of my site? =

Staatic crawls your WordPress site like a visitor, starting from the site's front page or configured Custom Origin URL, plus any additional URLs or paths you provide. It captures the generated HTML and related assets, then stores them as a static publication.

When that publication is deployed, visitors receive prebuilt files instead of waiting for WordPress, PHP, plugins, and the database to build each page on demand. This usually improves time to first byte, reduces server load, and gives you a simpler public delivery path.

= Why not use a caching plugin? =

Caching plugins can be useful, but the public request still usually reaches WordPress infrastructure. Cache misses, cache purges, plugin behavior, and origin capacity can still affect visitors.

Staatic changes the architecture: the public site is a deployed set of static files. You can host those files on your own server, object storage, a static hosting provider, or a CDN-backed setup, depending on the deployment method you choose.

= Will the appearance of my site change? =

No, it should not. Staatic publishes the output that WordPress already renders for visitors.

If the static version differs, the cause is usually frontend behavior that depends on server-side requests, invalid HTML, blocked assets, authentication, or plugin/theme output that behaves differently while being crawled. The publication resources and logs can help identify what was captured and what failed.

= How will Staatic improve the security of my site? =

Static delivery reduces the amount of WordPress infrastructure exposed to public visitors. Your public site can be served as files, while WordPress remains the authoring environment behind the publication workflow.

In a private-origin, public-static setup, visitors do not hit `wp-login.php`, plugin PHP code, or database-backed page generation on the public site. This reduces the blast radius of many common WordPress risks, including plugin runtime vulnerabilities, automated login probing, and server-side behavior that only exists while WordPress is serving each request.

You still need to maintain your WordPress origin, themes, plugins, accounts, and deployment credentials. The main security benefit is architectural: fewer dynamic systems are involved in serving anonymous public traffic.

= Is Staatic compatible with all plugins? =

Not entirely. Static sites do not run WordPress, PHP, or database queries for each visitor request. Plugins that depend on server-side processing on the public site may need changes or alternatives.

Common examples include native comments, some search implementations, forms that post back to WordPress, eCommerce flows, membership areas, and plugins that fetch personalized server-side data.

For forms and search, Staatic Premium provides ready-made integrations. For other dynamic features, use a static-compatible service, client-side integration, or a hybrid architecture where needed.

= Will Staatic function on shared or heavily restricted servers? =

Staatic is designed to work on typical WordPress hosting, including many shared hosting environments. The server must be able to write to the configured work directory and make HTTP requests to the WordPress site being published.

Large sites, slow origins, strict firewalls, disabled loopback requests, or very limited PHP execution time can require tuning. The Site Health checks, publication logs, and advanced settings are the best starting points if a publication does not start or takes too long.

= Publishing my large site takes hours or uses a lot of disk space. What can I do? =

By default, the Additional Paths setting adds your whole uploads directory to every publication. That way, files that only scripts load are not missed, but every other upload is published too, including image sizes no page uses and backups that other plugins keep there.

If your pages link to the media they use, remove the uploads directory from Additional Paths under Staatic > Settings > Build, or list only the folders you need. Files your pages reference are still published. With the Local Directory deployment method, you can instead turn on Symlink/Copy Uploads, which skips the uploads directory during publication and links it into the published site (or copies it, on Windows).

On shared hosting, leave enough free disk space for the work directory as well as the published site. If a complete publication needs more time, raise the Publication Time Limit under Staatic > Settings > Advanced.

= Where can I get help? =

If you have any questions or issues, please start with the [documentation](https://staatic.com/wordpress/documentation/) and [FAQ](https://staatic.com/wordpress/faq/).

If you cannot find an answer there, feel free to open a topic on our [Support Forums](https://wordpress.org/support/plugin/staatic/).

For commercial support, Staatic Premium, or Staatic Cloud questions, you can also [contact us](https://staatic.com/wordpress/contact/).

== Screenshots ==

1. Use WordPress as your authoring environment, then publish a static version of the site when you are ready.
2. Monitor running publications and review past publication details, resources, and logs.
3. Configure build, deployment, and advanced publication settings for your site.

== Changelog ==

= 1.15.0 =

Release date: October 28th, 2026.

**Features**

* Introduces the `staatic_netlify_functions` filter to deploy Netlify Functions with a publication.

**Improvements**

* Publishes the logo that Yoast SEO's structured data refers to, even when no page links to it.

**Bug Fixes**

* Prevents a task left behind by a canceled or failed publication from running again in the next one.
* Prevents bundled `.gitignore` files from hiding plugin files in Git-managed installations.

= 1.14.0 =

Release date: October 16th, 2026.

**Improvements**

* Improves admin layout, keyboard navigation and accessibility.

**Bug Fixes**

* Keeps settings list checkboxes in sync after editing or removing rows.
* Prevents deployment stalls after interrupted or overlapping publication tasks.
* Prevents failed database transactions from being treated as successful plugin upgrades.
* Prevents admin errors and warnings on PHP 7.1.

= Earlier releases =

For the changelog of earlier releases, please refer to [the changelog on staatic.com](https://staatic.com/wordpress/changelog/).

== Staatic Premium ==

Staatic Premium supports ongoing development and adds functionality for teams that need more than the Community edition.

Premium features include forms integration, search integration, scheduled publications, change-based publications, additional automation, and Staatic Cloud deployment options.

For more information, visit [Staatic](https://staatic.com/wordpress/).
