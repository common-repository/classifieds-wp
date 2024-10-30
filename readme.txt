=== Classifieds WP ===
Author:       classifiedswp
Contributors: classifiedswp
Tags:         classified manager, classified listings, classifieds board, classifieds management, classified lists, classified list, classified, classifieds, new, used, classified listings, board, directory, listing, craigslist, ebay, gumtree, appthemes, classipress, classipro, wp adverts, awpcp, another wordpress classifieds plugin
Requires at least: 4.1
Tested up to: 4.6
Stable tag:   1.2
License:      GPLv3
License URI:  http://www.gnu.org/licenses/gpl-3.0.html

Manage classified listings from the WordPress admin panel, and allow users to post classified listings directly to your site.

== Description ==

[DEMO Site](http://demo.classifiedswp.com/)

Classifieds WP is a classifieds listing plugin for adding classifieds to your WordPress site. Classifieds WP uses shortcodes and should work with most themes out of the box.

> #### NEW!!
> * Sidebar for single classified page
> * Allow uploading multiple images per classified using the native WP media browser (frontend and backend)
> * Require images before submitting a listing
> * Added classified categories and types widget

= Features =

* Create and manage classified listings using the traditional WordPress interface.
* Ability to post ads without being registered (registration is done while ad is being posted)
* Ability to add classified search forms using shortcodes
* Frontend forms to submit & manage classified listings.
* Allow advertisers to preview their listing before going live.
* Advertisers can be contacted via phone or email as specified during listing submission
* Allow logged in employers to edit their active listings.
* Super extendable with developer friendly code for customisations

The plugin comes with several shortcodes to show classifieds which can easily be changed with themes / child themes.

> #### Classifieds WP Premium Add-ons
> Classifieds WP comes with several premium add-ons to extend the core functionality<br />
>
> [See All Add-ons](http://classifiedswp.com/add-ons/#utm_source=wp-plugin-repo&utm_medium=link&utm_campaign=after-features-link)

[Read more about Classifieds WP](http://classifiedswp.com/).

= Documentation =

Documentation for Classifieds WP and their add-ons can be found under [documentation](documentation.classifiedswp.com). Have a look there before submitting a support request - most questions should be answered there already.

= Add-ons =

Classifieds WP plugin is free and covers all functionality you'll need to running a simple classified board site.

In the event that you require more advanced functionality, you could take a look at our growing list of add-ons (both developed by us and by 3rd party developers). You can browse all available add-ons by:

* Installing the Classifieds WP plugin and going to **Classifieds WP > Add-ons**
* Viewing the Add-ons directly on the [Classifieds WP website](documentation.classifiedswp.com)

= Support =

Use the WordPress.org forums for community support where we try to help all users.

If you need help with one of our add-ons, [please open a ticket in our help desk](http://classifiedswp.com/support/).

== Installation ==

= Automatic installation =

Installation through WordPress is the absolute easiest. To do an automatic install:

* log in to your WordPress admin panel
* Navigate to the Plugins menu
* Click Add New at the top
* In the search field type "Classifieds WP"
* Click Search Plugins
* Once you've found Classifieds WP, click _Install Now_

= Manual installation =

The manual installation method involves downloading the plugin and uploading it to your web server via your favorite FTP contact.

* Download the plugin file to your computer and unzip it
* Using an FTP program, or your hosting control panel, upload the unzipped plugin folder to your WordPress installation's `wp-content/plugins/` directory.
* Activate the plugin from the Plugins menu within the WordPress admin.

= Getting started =

Once installed:

1. Create a page called "classifieds" and inside place the `[classifieds]` shortcode. This will list your classifieds.
2. Create a page called "submit classified" and inside place the `[submit_classified_form]` shortcode if you want front-end submissions.
3. Create a page called "classified dashboard" and inside place the `[classified_dashboard]` shortcode for logged in users to manage their listings.

**Note when using shortcodes**, if the content looks blown up/spaced out/poorly styled, edit your page and above the visual editor click on the 'text' tab. Then remove any 'pre' or 'code' tags wrapping your shortcode.

For more information, [read the documentation](http://documentation.classifiedswp.com/).

== Frequently Asked Questions ==

= How do I setup Classifieds WP? =
Have a look at the [Getting Started](http://documentation.classifiedswp.com/getting-started/) guides for help and advice on getting started with the plugin. In most cases it's just a case of adding some shortcodes to your pages.

= Can I use Classifieds WP without frontend classified submission? =
Yes, you don't need to have a dedicated page / shortcode for front end submission at all. This would imply that you'll be manually adding classified listings through the WordPress backend.

= How can I customize the classified submission form? =
There are three ways to customise the fields in Classifieds WP;

1. For simple word / string changes, you could use 3rd party plugins like [Say What](https://wordpress.org/plugins/say-what/) or [Loco Translate](https://wordpress.org/plugins/loco-translate/)
2. For field changes or adding new fields, you would need to use CWP hooks / filters inside your theme / child theme functions.php file. This does require a coding background and it'd be best to get in touch with a web developer for this.

If you'd like to learn about WordPress filters, we recommend having a look at the following guide: [A Quick Introduction to using Filters](https://pippinsplugins.com/a-quick-introduction-to-using-filters/)

= How can I be notified of new classifieds via email? =
The easiest would be to use a 3rd party plugin like [Post Status Notifier](http://wordpress.org/plugins/post-status-notifier-lite/).

= What language files are available? =
Classifieds WP doesn't have any native translations available for download. However, it does come with a .pot file which can be used alongside [Loco Translate](https://wordpress.org/plugins/loco-translate/) or [Poedit](https://poedit.net/).

= My images disappeared after upgrading to 1.x =
After v.1.1, images were upgraded to native WordPress featured images. The upgrade should be automatic but if you don't see your images after the update try deactivating/activating the plugin.


== Screenshots ==

1. (Frontend) The submit classified form.
2. (Frontend) Classifieds listings and filters.
3. (Frontend) A single classified listing.
4. (Backend)  Classifieds listings and filters.
5. (Backend)  Submit classified.
6. (Backend)  Classifieds listings settings.
7. (Backend)  Classifieds submission settings.

== Changelog ==

= 1.2 =
* Fixes:   Image upload button not working while required fields were empty
* Fixes:   Image upload button not working on some themes
* Fixes:   Missing featured image meta box on single classifieds backend page
* Fixes:   Empty image selected when opening the media viewer for the first time
* Changes: New custom template for displaying single classified (this will impact your current single classified page, please make sure to check it)
* Changes: New custom template for single classifieds sidebar
* Changes: New sidebar added to the widgets page for using with the the new single classified pages template
* Changes: Changed language text domain to 'classifieds-wp' for full WP translation support
* Changes: Setting 'Max Images' to 1 will revert to the old single file upload field

= 1.1 =
* NEW: Support for multiple image uploads using the native WP media viewer (with support for easy images re-ordering, images deletion, familiar UI)
* NEW: Added new admin settings to the 'Classified Submission' tab: Require Images, Max Images per Listing and Max File Size per Image
* NEW: Added images gallery meta box in the admin single classified listing page
* NEW: Featured images now use the native WP featured image functionality
* NEW: New taxonomies widget for filtering classified types and/or classified categories
* NEW: Added real time validation on required fields

= 1.0 =
* First stable release.

== Upgrade Notice ==
This is the first stable version.
