=== Search Magic Fields 2 Widget ===
Contributors: Magenta Cuda
Tags: search, custom fields
Requires at least: 3.6
Tested up to: 3.9
Stable tag: 0.4.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Search Magic Fields 2 custom posts for posts that have user specified values for Magic Fields 2 custom fields.

== Description ==
This search widget can search for Magic Fields 2 custom posts, WordPress posts and pages by the value of Magic Fields 2 custom fields, WordPress taxonomies and post content. It is designed to be used with Magic Fields 2 only and makes use of Magic Fields 2's proprietary database format to generate user friendly field names and field values. The widget uses user friendly substitutions for the actual values in the database when appropriate, e.g. post title is substituted for post id in related type custom fields. Please visit the [online documentation](http://magicfields17.wordpress.com/magic-fields-2-search-0-4-1/) for more details. This plugin requires at least PHP 5.4 and is not compatible with Magic Fields 2 Toolkit 0.4.2.

== Installation ==
1. Upload the folder "search-magic-fields-2-widget" to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Activate this widget by dragging it to a sidebar through the "Appearance->Widgets" menu in WordPress.

== Frequently Asked Questions ==
= Does this plugin require that Magic Fields 2 to be installed? =
Yes.

= Where is the documentation? =
[http://magicfields17.wordpress.com/magic-fields-2-search-0-4-1/](http://magicfields17.wordpress.com/magic-fields-2-search-0-4-1/).

= After upgrading to version 0.4.6 the search results table of post is not sortable. =

Version 0.4.6 has a new default content macro for sortable tables. However, the toolkit will not automatically replace an existing content macro - because you may have customized it. However, you can restore the default content macro by completely erasing the content macro definition.
   
== Screenshots ==
1. The Adminstrator's Interface for Field Selection.
2. The Adminstrator's Interface for Settings.
3. The User's Interface for Post Type Selection.
4. The User's Interface for Searching Posts of the Selected Type.
5. The User's Interface for Settings.

== Changelog ==
= 0.4.6 =
* The search results table of posts is now a sortable table. Note that this requires a manual upgrade of search widget's content macro.
= 0.4.5.3 =
* fix pagination bug for search results output
* added search by post author
* added support for post type specific css file for alternate search result output
* omit select post type if there is only one post type
= 0.4.5 =
* optionally display seach results in an alternate format using content macros from Magic Fields 2 toolkit
* optionally set query type to is_search so only excerpts are displayed for applicable themes
= 0.4.4 =
* added range search for numeric and date custom fields
= 0.4.3 =
* made items shown per custom field user settable
* fix bug where AND post content searches incorrectly failed
* some css style changes for cleaner appearance
= 0.4.2 =
* Added support for selecting and/or on search condtions.
= 0.4.1.1 =
* Initial release.

== Upgrade Notice ==
= 0.4.6 =
* The search results table of posts is now a sortable table. Note that this requires a manual upgrade of search widget's content macro.
= 0.4.5.3 =
* fix pagination bug for search results output
* added search by post author
* added support for post type specific css file for alternate search result output
* omit select post type if there is only one post type
= 0.4.5 =
* optionally display seach results in an alternate format using content macros from Magic Fields 2 toolkit
* optionally set query type to is_search so only excerpts are displayed for applicable themes
= 0.4.4 =
* added range search for numeric and date custom fields
= 0.4.3 =
* some small enhancements - minor upgrade
= 0.4.2 =
* Added support for selecting and/or on search condtions.
= 0.4.1.1 =
* Initial release.

