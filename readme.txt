=== BrainySearch ===
Contributors: procoders
Tags: openai, search, AI, WordPress
Requires at least: 5.0
Tested up to: 6.1
Stable tag: 1.0.1
Requires PHP: 7.0
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://procoders.tech/


The BrainySearch plugin allows you to perform advanced search queries on your WordPress site using the OpenAI API.

== Description ==

This WordPress plugin indexes your blog posts using the embeddings feature of OpenAI, and uses this data to enrich the BrainySearch request. When a user searches for content using the `[brainy_search]` shortcode, the plugin returns the most relevant search results based on the content of the indexed blog posts, along with a temperature label (0-100) that indicates the "creativity" or "diversity" of the results.

The BrainySearch plugin can be used on any WordPress site where advanced search functionality is desired. Some potential use cases include:

- E-commerce sites looking to provide more accurate and relevant product search results to customers
- News or blog sites looking to provide more targeted search results based on article content
- Educational sites or online course providers looking to provide more effective search results for course content
- Knowledge base sites looking to improve the search experience for users trying to find specific information
- Expert systems or decision-making systems that require fast and accurate search functionality to deliver results to users

== Installation ==

1. Upload the `brainy-search` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the BrainySearch settings page and enter your API key
4. Customize the search settings to your preference
5. To use this plugin, simply install and activate it in your WordPress installation.
6. The `/aisearch` page will be created automatically, and you can use the `[brainy_search]` shortcode to display the search form on the page.

== Frequently Asked Questions ==

= What is OpenAI? =

OpenAI is an artificial intelligence research laboratory consisting of the for-profit corporation OpenAI LP and its parent company, the non-profit OpenAI Inc.

= How do I get an OpenAI API key? =

To use this plugin, you'll need to sign up for an OpenAI API key. You can do so by visiting the [OpenAI website](https://openai.com/api/), creating an account, and generating an API key.

= Can I customize the appearance of the search form and search results? =

Yes, the BrainySearch plugin provides template files for customization of search results display. You can copy the appropriate template file to your theme's directory and modify it as needed.

= When my content appears in search results?

BrainySearch indexes your content such as blog posts after publishing, but it needs to wait a minute while your content appear in AI Search. It happens because BrainySearch use cron in background. Normally it happens once per minute but you must be sure that your cron is working.

== Screenshots ==

1. Search, prompt and ask as it would be a human
2. Multiply settings to adjust output
3. Output results based on your posts content
4. Multilanguage support. Ask it in any language
5. Smart answers even for abstract question 
6. Strict mode for expert systems. Don't try to compose when there is no information for it. 

== Changelog ==

= 1.0.0 =
* Initial release

= 1.0.1 =
* CI/CD from Github 

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade necessary.