# BrainySearch

The BrainySearch plugin allows you to perform advanced search queries on your WordPress site using the OpenAI API. 

This WordPress plugin indexes your blog posts using the embeddings feature of OpenAI, and uses this data to enrich the BrainySearch request. When a user searches for content using the `[brainy_search]` shortcode, the plugin returns the following:

* An AI summary response of the most relevant results based on the content of the indexed blog posts
* The most relevant search results based on the content of the indexed blog posts, along with a temperature label (0-100) that indicates the "creativity" or "diversity" of the results
* Links to the indexed blog posts that match the user's search query.

## Where to Use BrainySearch

The BrainySearch plugin can be used on any WordPress site where advanced search functionality is desired. Some potential use cases include:

- E-commerce sites looking to provide more accurate and relevant product search results to customers
- News or blog sites looking to provide more targeted search results based on article content
- Educational sites or online course providers looking to provide more effective search results for course content
- Knowledge base sites looking to improve the search experience for users trying to find specific information
- Expert systems or decision-making systems that require fast and accurate search functionality to deliver results to users

## Features

- Advanced search queries using the OpenAI API
- Customizable search settings, including API key, engines, and bunch of parameters
- Template files for customization of search results display
- Shortcodes for displaying search form and results on any post or page

## Requirements

- WordPress 5.0 or later
- PHP 7.3+
- OpenAI API key

## Installation

1. Upload the `brainy-search` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the BrainySearch settings page and enter your API key
4. Customize the search settings to your preference
5. To use this plugin, simply install and activate it in your WordPress installation. 
6. The `/aisearch` page will be created automatically, and you can use the `[brainy_search]` shortcode to display the search form on the page.

## Settings

The BrainySearch plugin provides the following settings:

- **API Key**: Enter your OpenAI API key to enable search functionality
- **Embedding Engine**: Choose the OpenAI embedding engine to use for search queries
- **Completion Engine**: Choose the OpenAI completion engine to use for search queries
- **Max Tokens**: Set the maximum number of tokens to generate for each query
- **Temperature**: Set the temperature parameter for search queries
- **Top P**: Set the top P parameter for search queries
- **Stop**: Set the stop sequence for search queries
- **General Suggestions**: Choose whether to display general suggestions when no results are found
- **Results Limit**: Set the maximum number of results to display for each query
- **Paragraph Characters Limit**: Set the maximum number of characters to display for each search result

## Shortcodes

The BrainySearch plugin provides the following shortcodes:

- `[brainy_search]`: Displays both the search form and results in the same place
- `[brainy_search_form]`: Displays the search form only
- `[brainy_search_results]`: Displays the search results only


To use a shortcode, simply add it to any post or page where you want it to appear.

## Indexing content

BrainySearch indexes your content such as blog posts after publishing, but it needs to wait a minute while your content appear in AI Search. It happens because BrainySearch use cron in background. Normally it happens once per minute but you must be sure that your cron is working.

## Template Files

The BrainySearch plugin provides the following template files for customization:

- `brainy-search-form.php`: Template file for the search form
- `brainy-search-results.php`: Template file for the search results

To customize the appearance of the search form or search results, copy the appropriate template file to your theme's directory and modify it as needed.

## Support

If you need help with this plugin, please contact us at office@procoders.tech.

## License

The BrainySearch plugin is released under the [GPLv2](https://www.gnu.org/licenses/gpl-2.0.html) license.


## Contact Us

Thank you for using our WordPress plugin! If you have any questions, feedback, or suggestions for improvement, please don't hesitate to contact us. [Procoders](https://procoders.tech) specializes not only in developing WordPress plugins, but also in creating a wide range of web and mobile applications that leverage the power of AI.

Please feel free to reach out to us at [office@procoders.tech](mailto:office@procoders.tech) for any inquiries or collaboration opportunities.

## About Us

Our company is dedicated to creating cutting-edge technology solutions that transform the way businesses operate. We specialize in developing web and mobile applications that leverage the power of AI to improve efficiency, reduce costs, and deliver unparalleled user experiences.

For more information about our company and the services we offer, please visit our website at [Procoders](https://procoders.tech).