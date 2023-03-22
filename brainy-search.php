<?php
/*
Plugin Name: BrainySearch
Plugin URI: https://github.com/procoders/wordpress-brainy-search
Description: AI Search to power a search engine on your WordPress site
Version: 1.0.0
Author: Oleg Kopachovets
Author URI: https://procoders.tech/
License: GPL2
*/

// Set up OpenAI API client
function brainy_search_openai_api_call($endpoint, $data) {
    $openai_key = get_option('openai_key');
    $url = "https://api.openai.com/v1/" . $endpoint;

    $args = array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $openai_key
        ),
        'body' => json_encode($data),
        'method' => 'POST',
        'data_format' => 'body',
    );

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        // Handle the error accordingly.
        // You may want to return an error message or throw an exception.
        return null;
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}


function brainy_search_load_template($template_name)
{
    // Attempt to load the theme's template file
    $theme_template = locate_template('brainy-search/' . $template_name . '.php');
    if ($theme_template) {
        // Use the theme's template file if it exists
        return $theme_template;
    } else {
        // Use the plugin's template file as a fallback
        return plugin_dir_path(__FILE__) . 'partials/' . $template_name . '.php';
    }
}

function brainy_search_init()
{

    $embedding_engine = get_option('embedding_engine', 'text-davinci-002');
    $complete_engine = get_option('complete_engine', 'text-davinci-002');
    // Define function to generate embeddings for a paragraph
    function brainy_search_get_embedding($paragraph, $model_engine)
    {
        $data = array(
            'model' => $model_engine,
            'input' => $paragraph
        );
        $response = brainy_search_openai_api_call('embeddings', $data);
        return $response['data'][0]['embedding'];
    }

    // Get paragrahps of the text
    function brainy_search_get_paraphs_normalized($html)
    {
        // Remove any HTML tags from the input text
        $text = strip_tags($html);

        // Split the text into paragraphs
        $paragraphs = preg_split('/\n|\r\n?/', $text);

        // Loop over the paragraphs and group them into blocks
        $blocks = array();
        $block = '';
        $word_count = 0;
        foreach ($paragraphs as $paragraph) {
            $word_count += str_word_count($paragraph);
            if ($word_count <= 400) {
                // Add the paragraph to the current block
                $block .= $paragraph . ' ';
            } else {
                // Start a new block with the current paragraph
                $blocks[] = trim($block);
                $block = $paragraph . ' ';
                $word_count = str_word_count($paragraph);
            }
        }

        // Add the final block
        $blocks[] = trim($block);

        return $blocks;
    }


    // Define function to generate embeddings for all paragraphs in a post
    function brainy_search_generate_embeddings_for_post($post_id, $model_engine)
    {
        $post = get_post($post_id);
        $combined_paragraphs = brainy_search_get_paraphs_normalized($post->post_content);

        $embeddings = array();
        foreach ($combined_paragraphs as $paragraph) {
            $embedding = brainy_search_get_embedding($paragraph, $model_engine);
            $embeddings[] = $embedding;
        }

        set_transient("embeddings_{$post_id}", $embeddings, 60 * 60 * 24 * 365);
    }


    // Hook the brainy_search_display_embeddings_notice() function to the admin_notices action
    function brainy_search_display_embeddings_notice()
    {
        $notice = '<div class="notice notice-info">';
        $notice .= '<p>The post embeddings are currently being generated. This process may take a few minutes to complete.</p>';
        $notice .= '</div>';
        echo $notice;
    }

    function brainy_search_schedule_embedding_generation($post_id, $model_engine)
    {
        $timestamp = time(); // Schedule the event to run immediately
        wp_schedule_single_event($timestamp, 'generate_embeddings_event', array($post_id, $model_engine));

        // add_action('admin_notices', 'display_embeddings_notice');

    }

    // Hook the brainy_search_schedule_embedding_generation() function to a custom WordPress action
    add_action('generate_embeddings_action', 'brainy_search_schedule_embedding_generation', 10, 2);

    // Define the function that will generate the embeddings and hook it to the generate_embeddings_event
    add_action('generate_embeddings_event', 'brainy_search_generate_embeddings_for_post', 10, 2);




    // Define function to retrieve embeddings for a post
    function brainy_search_get_embeddings_for_post($post_id, $model_engine)
    {
        $embeddings = get_transient("embeddings_{$post_id}");

        if (!$embeddings || !$embeddings[0]) {

            //brainy_search_generate_embeddings_for_post($post_id, $model_engine);
            brainy_search_schedule_embedding_generation($post_id, $model_engine);
            $embeddings = get_transient("embeddings_{$post_id}");
        }
        return $embeddings;
    }

    // Define function to retrieve embeddings for all posts
    function brainy_search_get_embeddings_for_posts($model_engine)
    {
        $posts = get_posts(
            array(
                'numberposts' => -1
            )
        );
        $all_embeddings = array();
        foreach ($posts as $post) {
            $embeddings = brainy_search_get_embeddings_for_post($post->ID, $model_engine);
            $all_embeddings[$post->ID] = $embeddings;
        }
        return $all_embeddings;
    }

    // Define function to compute cosine similarity between two vectors
    function brainy_search_cosine_similarity($a, $b)
    {
        if (!$a or !$b)
            return 0;
        $dot_product = 0;
        $a_norm = 0;
        $b_norm = 0;
        for ($i = 0; $i < count($a); $i++) {
            $dot_product += $a[$i] * $b[$i];
            $a_norm += pow($a[$i], 2);
            $b_norm += pow($b[$i], 2);
        }
        $a_norm = sqrt($a_norm);
        $b_norm = sqrt($b_norm);
        return $dot_product / ($a_norm * $b_norm);
    }

    // Define function to perform a search
    function brainy_search($query, $embedding_engine, $complete_engine, $max_tokens, $temperature, $top_p, $stop)
    {
        $posts = get_posts(
            array(
                'numberposts' => -1
            )
        );

        $query_embedding = brainy_search_get_embedding($query, $embedding_engine);

        $all_embeddings = brainy_search_get_embeddings_for_posts($embedding_engine);

        $scores = array();

        foreach ($all_embeddings as $post_id => $post_embeddings) {
            if (!$post_embeddings)
                return;
            foreach ($post_embeddings as $paragraph_id => $embedding) {
                $score = brainy_search_cosine_similarity($embedding, $query_embedding);
                $scores[$post_id . '__' . $paragraph_id] = $score;
            }
        }
        arsort($scores);
        $best_scores = array_slice($scores, 0, 2);

        $best_posts = array();
        foreach ($best_scores as $post_paragraph_id => $score) {
            list($post_id, $paragraph_id) = array_pad(explode('__', $post_paragraph_id), 2, null);

            $best_post = get_post($post_id);
            $best_paragraph = brainy_search_get_paraphs_normalized($best_post->post_content)[$paragraph_id];

            $best_posts[] = array('post_id' => $post_id, 'post' => $best_post, 'paragraph' => $best_paragraph, 'score' => $score);
        }
        $support_phrase = get_option('suppose_phrase', 'I suppose');

        $prompt = "Answer the question as truthfully as possible using the provided text, and if the answer is not contained within the text below ";
        $prompt .= "say '{$support_phrase}' and add suggestion";
        $prompt .= "\n \n Context: \n {$best_posts[0]['post']->post_title}  \n {$best_posts[0]['paragraph']}  \n {$best_posts[1]['paragraph']}  ";
        $prompt .= "Q: {$query}. What can you tell?\nA: ";

        $completions_request_data = array(
            'model' => $complete_engine,
            'prompt' => trim($prompt),
            'max_tokens' => intval($max_tokens),
            'temperature' => floatval($temperature),
            'top_p' => floatval($top_p),
            'stop' => $stop
        );
        $completions_response = brainy_search_openai_api_call('completions', $completions_request_data);
        // var_dump($completions_response); exit();
        $response_text = $completions_response['choices'][0]['text'];
        $response_text = ucfirst(trim(str_replace("\nA:", "", $response_text)));
        $reference = $best_posts[0]['paragraph'];
        $link = "You can read the full article at " . get_permalink($best_posts[0]['post_id']) . ".";
        return ['response_text' => $response_text, 'posts' => $best_posts];

        //$response_text . " " . $reference . $link;


    }
    function brainy_search_get_complete_engines()
    {
        return array(
            'text-davinci-001',
            'text-davinci-002',
            'text-davinci-003',
            'text-curie-001',
            'text-curie-002',
            'text-babbage-001',
            'text-ada-001',
        );
    }

    function brainy_search_get_embedding_engines()
    {
        return array(
            'text-embedding-ada-001',
            'text-embedding-ada-002',
            'text-embedding-babbage-001',
            'text-embedding-curie-001',
            'text-embedding-curie-002',
            'text-embedding-davinci-001',
            'text-embedding-davinci-002',
        );
    }

    // Define function to render settings page HTML
    function brainy_search_settings_page_html()
    {
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('BrainySearch Settings', 'brainy-search'); ?>
            </h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('brainy_search_options');
                do_settings_sections('brainy_search_options');
                ?>
                <h2>
                    <?php esc_html_e('API Settings', 'brainy-search'); ?>
                </h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="openai_key">
                                <?php esc_html_e('API Key', 'brainy-search'); ?>
                                <span class="required">*</span>
                            </label>
                        </th>
                        <td><input type="text" id="openai_key" name="openai_key"
                                value="<?php echo esc_attr(get_option('openai_key')); ?>" required />
                            <p class="description">
                                Please enter your OpenAI API key to enable search functionality. <br />
                                You can sign up for a key on the
                                <a href="https://beta.openai.com/signup/" target="_blank">OpenAI website</a>
                            </p>
                        </td>
                    </tr>
                </table>
                <h2>
                    <?php esc_html_e('General Settings', 'brainy-search'); ?>
                </h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="suggestion_output">
                                <?php esc_html_e('Suggestion Output', 'brainy-search'); ?>
                            </label></th>
                        <td>
                            <select id="suggestion_output" name="suggestion_output">
                                <option value="always" <?php selected(get_option('suggestion_output'), 'always'); ?>><?php
                                   esc_html_e('Always', 'brainy-search'); ?></option>
                                <option value="user-defined" <?php selected(get_option('suggestion_output'), 'user-defined'); ?>><?php esc_html_e('User-defined', 'brainy-search'); ?></option>
                                <option value="never" <?php selected(get_option('suggestion_output'), 'never'); ?>><?php
                                   esc_html_e('Never', 'brainy-search'); ?></option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Output suggestion even if no articles found, could be hallucinations effect', 'brainy-search'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="suppose_phrase">
                                <?php esc_html_e('Suppose phrase', 'brainy-search'); ?>
                            </label></th>
                        <td><input type="text" id="suppose_phrase" name="suppose_phrase"
                                value="<?php echo esc_attr(get_option('suppose_phrase')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="results_limit">
                                <?php esc_html_e('Results Limit', 'brainy-search'); ?>
                            </label></th>
                        <td><input type="number" id="results_limit" name="results_limit"
                                value="<?php echo esc_attr(get_option('results_limit', 5)); ?>" min="1" max="10" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="paragraph_limit">
                                <?php esc_html_e('Paragraph Characters Limit', 'brainy-search'); ?>
                            </label></th>
                        <td><input type="number" id="paragraph_limit" name="paragraph_limit"
                                value="<?php echo esc_attr(get_option('paragraph_limit', 400)); ?>" min="50" max="1000" /></td>
                    </tr>
                </table>
                <h2>
                    <?php esc_html_e('OpenAI Settings', 'brainy-search'); ?>
                </h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="embedding_engine">
                                <?php esc_html_e('Embedding Engine', 'brainy-search'); ?>
                            </label></th>
                        <td>
                            <select id="embedding_engine" name="embedding_engine">
                                <?php foreach (brainy_search_get_embedding_engines() as $engine): ?>
                                    <option value="<?php echo esc_attr($engine); ?>" <?php selected(get_option('embedding_engine', 'text-embedding-ada-002'), $engine); ?>><?php echo esc_html($engine); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="complete_engine">
                                <?php esc_html_e('Completion Engine', 'brainy-search'); ?>
                            </label></th>
                        <td>
                            <select id="complete_engine" name="complete_engine">
                                <?php foreach (brainy_search_get_complete_engines() as $engine): ?>
                                    <option value="<?php echo esc_attr($engine); ?>" <?php selected(get_option('complete_engine', 'text-davinci-003'), $engine); ?>><?php echo esc_html($engine); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><a href="https://beta.openai.com/docs/models/overview" target="_blank">
                                    <?php esc_html_e('Read more about engines', 'brainy-search'); ?>
                                </a></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="max_tokens">
                                <?php esc_html_e('Max Tokens', 'brainy-search'); ?>
                            </label></th>
                        <td><input type="number" id="max_tokens" name="max_tokens"
                                value="<?php echo esc_attr(get_option('max_tokens', 50)); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="temperature">
                                <?php esc_html_e('Temperature', 'brainy-search'); ?>
                            </label></th>
                        <td><input type="number" id="temperature" name="temperature"
                                value="<?php echo esc_attr(get_option('temperature', 0.5)); ?>" step="0.01" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="top_p">
                                <?php esc_html_e('Top P', 'brainy-search'); ?>
                            </label></th>
                        <td><input type="number" id="top_p" name="top_p" value="<?php echo esc_attr(get_option('top_p', 1)); ?>"
                                step="0.01" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stop">
                                <?php esc_html_e('Stop', 'brainy-search'); ?>
                            </label></th>
                        <td><input type="text" id="stop" name="stop"
                                value="<?php echo esc_attr(get_option('stop', '###')); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
    <?php
    }

    function brainy_search_register_settings()
    {
        register_setting('brainy_search_options', 'openai_key');
        register_setting('brainy_search_options', 'suggestion_output');
        register_setting(
            'brainy_search_options',
            'suppose_phrase',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => esc_attr('I suppose'),
            )
        );
        register_setting(
            'brainy_search_options',
            'results_limit',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 5,
            )
        );
        register_setting(
            'brainy_search_options',
            'paragraph_limit',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 400,
            )
        );
        register_setting('brainy_search_options', 'embedding_engine');
        register_setting('brainy_search_options', 'complete_engine');
        register_setting(
            'brainy_search_options',
            'max_tokens',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 50,
            )
        );
        register_setting(
            'brainy_search_options',
            'temperature',
            array(
                'type' => 'number',
                'sanitize_callback' => 'floatval',
                'default' => 0.5,
            )
        );
        register_setting(
            'brainy_search_options',
            'top_p',
            array(
                'type' => 'number',
                'sanitize_callback' => 'floatval',
                'default' => 1,
            )
        );
        register_setting('brainy_search_options', 'stop');
    }


    function brainy_search_settings_page()
    {
        add_options_page(
            __('BrainySearch', 'brainy-search'),
            __('BrainySearch', 'brainy-search'),
            'manage_options',
            'brainy-search',
            'brainy_search_settings_page_html'
        );
    }

    add_action('admin_menu', 'brainy_search_settings_page');
    add_action('admin_init', 'brainy_search_register_settings');

    function brainy_search_settings_validate($input)
    {
        if (empty(trim($input['openai_key']))) {
            add_settings_error('brainy_search_options', 'openai_key', __('Please enter your OpenAI API key.', 'brainy-search'), 'error');
        }
        return $input;
    }
    add_filter('pre_update_option_brainy_search_options', 'brainy_search_validate_settings');


    function brainy_search_form()
    {
        include brainy_search_load_template('brainy-search-form');
    }

    function brainy_search_enqueue_styles() {
        wp_enqueue_style('brainy-search', plugin_dir_url(__FILE__) . 'brainy-search.css');
    }
    add_action('wp_enqueue_scripts', 'brainy_search_enqueue_styles');
    

    // Define shortcode to display search form and results
    function brainy_search_shortcode()
    {
        ob_start();
        ?><div class="brainy-search">
            <?php
            brainy_search_form();
            brainy_search_handle_query();
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
    // Add shortcode and search query handler to WordPress hooks
    add_shortcode('brainy_search', 'brainy_search_shortcode');

    // Define shortcode to display search form on a page or post
    function brainy_search_form_shortcode()
    {
        ob_start();
        ?>
        <div class="brainy-search form">
            <?php
            brainy_search_form();
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
    // Add shortcode and search query handler to WordPress hooks
    add_shortcode('brainy_search_form', 'brainy_search_form_shortcode');
    // Define shortcode to display search form on a page or post
    function brainy_search_results_shortcode()
    {
        ob_start();
        ?>
        <div class="brainy-search results">
            <?php
            brainy_search_handle_query();
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
    // Add shortcode and search query handler to WordPress hooks
    add_shortcode('brainy_search_results', 'brainy_search_results_shortcode');

    // Define function to handle search query
    function brainy_search_handle_query()
    {
        if (isset($_GET['q'])) {
            $query = sanitize_text_field($_GET['q']);
            $cache_key = 'brainy_search_results_' . md5($query); // Use a unique cache key based on the query

            // Try to retrieve the result from the cache
            $result = get_transient($cache_key);

            if ($result === false) {
                $openai_key = get_option('openai_key');
                $embedding_engine = get_option('embedding_engine', 'text-davinci-002');
                $complete_engine = get_option('complete_engine', 'text-davinci-002');
                $max_tokens = get_option('max_tokens', 75);
                $temperature = get_option('temperature', 0.3);
                $top_p = get_option('top_p', 0.5);
                $stop = get_option('stop', '###');
                $query = sanitize_text_field($_GET['q']);
                $result = brainy_search($query, $embedding_engine, $complete_engine, $max_tokens, $temperature, $top_p, $stop);
                if (!$result) {
                    echo "Some issue. No results";
                }
                set_transient($cache_key, $result, 60 * MINUTE_IN_SECONDS); // Cache for 5 minutes
            }
            // Load the template brainy-search-results
            include brainy_search_load_template('brainy-search-results');
        }
    }



    function brainy_search_create_aisearch_page()
    {
        // Check if the "aisearch" page exists
        $aisearch_page = get_page_by_title('aisearch');
        if (!$aisearch_page) {
            // Create the "aisearch" page and add the [aisearch] shortcode
            $page_id = wp_insert_post(
                array(
                    'post_title' => 'BrainySearch',
                    'post_content' => '[brainy_search]',
                    'post_status' => 'publish',
                    'post_type' => 'page'
                )
            );
            // Add a permalink to the "aisearch" page
            update_post_meta($page_id, '_wp_page_template', 'page.php');
        }
    }
    register_activation_hook(__FILE__, 'brainy_search_create_aisearch_page');

    function brainy_search_activate()
    {
        if (!get_option('openai_key')) {
            add_action('admin_notices', 'brainy_search_activation_notice');
        }
    }

    function brainy_search_activation_notice()
    {
        ?>
        <div class="error">
            <p>
                <?php printf(__('Please <a href="%s">enter your OpenAI API key</a> to use the BrainySearch plugin.', 'brainy_search'), esc_url(admin_url('options-general.php?page=brainy-search'))); ?>
            </p>
        </div>
    <?php
    }
    register_activation_hook(__FILE__, 'brainy_search_activate');



    add_filter('plugin_row_meta', 'brainy_search_add_plugin_row_meta', 10, 2);

    function brainy_search_add_plugin_row_meta($links, $file)
    {
        if (plugin_basename(__FILE__) === $file) {
            $links[] = '<a href="' . admin_url('admin.php?page=brainy-search') . '">' . esc_html__('Settings', 'brainy-search') . '</a>';
            $links[] = '<a href="' . home_url('/aisearch') . '">' . esc_html__('BrainySearch page', 'brainy-search') . '</a>';
        }
        return $links;
    }
    // Define function to handle post updates and generate embeddings
    function brainy_search_update_post($post_id, $post, $update)
    {
        if ($post->post_status == 'publish') {
            // brainy_search_generate_embeddings_for_post($post_id, get_option('embedding_engine', 'text-davinci-002'));
            brainy_search_schedule_embedding_generation($post_id, get_option('embedding_engine', 'text-davinci-002'));
        }
    }

    // Add hook to generate embeddings when a post is updated or published
    add_action('save_post', 'brainy_search_update_post', 10, 3);

    include 'src/clusters.php';
}
brainy_search_init();