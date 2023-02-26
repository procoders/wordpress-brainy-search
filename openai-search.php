<?php
/*
Plugin Name: OpenAI Search
Plugin URI: https://procoders.tech/
Description: Uses OpenAI's GPT-3 API to power a search engine on your WordPress site.
Version: 1.0.0
Author: Oleg Kopachovets
Author URI: https://procoders.tech/
License: GPL2
*/

// Set up OpenAI API client
function openai_api_call($endpoint, $data)
{
    $openai_key = get_option('openai_key');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/" . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array(
            "Content-Type: application/json",
            "Authorization: Bearer " . $openai_key
        )
    );
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function openai_search_load_template($template_name)
{
    // Attempt to load the theme's template file
    $theme_template = locate_template('openai-search/' . $template_name . '.php');
    if ($theme_template) {
        // Use the theme's template file if it exists
        return $theme_template;
    } else {
        // Use the plugin's template file as a fallback
        return plugin_dir_path(__FILE__) . 'partials/' . $template_name . '.php';
    }
}

function openai_search_init()
{

    $embedding_engine = get_option('embedding_engine', 'text-davinci-002');
    $complete_engine = get_option('complete_engine', 'text-davinci-002');
    // Define function to generate embeddings for a paragraph
    function get_embedding($paragraph, $model_engine)
    {
        $data = array(
            'model' => $model_engine,
            'input' => $paragraph
        );
        $response = openai_api_call('embeddings', $data);
        return $response['data'][0]['embedding'];
    }

    // Get paragrahps of the text
    function get_paraphs_normalized($html)
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
    function generate_embeddings_for_post($post_id, $model_engine)
    {
        $post = get_post($post_id);
        $combined_paragraphs = get_paraphs_normalized($post->post_content);

        $embeddings = array();
        foreach ($combined_paragraphs as $paragraph) {
            $embedding = get_embedding($paragraph, $model_engine);
            $embeddings[] = $embedding;
        }

        set_transient("embeddings_{$post_id}", $embeddings, 60 * 60 * 24);
    }

    // Define function to retrieve embeddings for a post
    function get_embeddings_for_post($post_id, $model_engine)
    {
        $embeddings = get_transient("embeddings_{$post_id}");
        if (!$embeddings) {
            generate_embeddings_for_post($post_id, $model_engine);
            $embeddings = get_transient("embeddings_{$post_id}");
        }
        return $embeddings;
    }

    // Define function to retrieve embeddings for all posts
    function get_embeddings_for_posts($model_engine)
    {
        $posts = get_posts(
            array(
                'numberposts' => -1
            )
        );
        $all_embeddings = array();
        foreach ($posts as $post) {
            $embeddings = get_embeddings_for_post($post->ID, $model_engine);
            $all_embeddings[$post->ID] = $embeddings;
        }
        return $all_embeddings;
    }

    // Define function to compute cosine similarity between two vectors
    function cosine_similarity($a, $b)
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
    function openai_search($query, $embedding_engine, $complete_engine, $max_tokens, $temperature, $top_p, $stop)
    {
        $posts = get_posts(
            array(
                'numberposts' => -1
            )
        );

        $query_embedding = get_embedding($query, $embedding_engine);

        $all_embeddings = get_embeddings_for_posts($embedding_engine);

        $scores = array();

        foreach ($all_embeddings as $post_id => $post_embeddings) {
            if (!$post_embeddings)
                return;
            foreach ($post_embeddings as $paragraph_id => $embedding) {
                $score = cosine_similarity($embedding, $query_embedding);
                $scores[$post_id . '__' . $paragraph_id] = $score;
            }
        }
        arsort($scores);
        $best_scores = array_slice($scores, 0, 2);

        $best_posts = array();
        foreach ($best_scores as $post_paragraph_id => $score) {
            list($post_id, $paragraph_id) = array_pad(explode('__', $post_paragraph_id), 2, null);

            $best_post = get_post($post_id);
            $best_paragraph = get_paraphs_normalized($best_post->post_content)[$paragraph_id];

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
        $completions_response = openai_api_call('completions', $completions_request_data);
        // var_dump($completions_response); exit();
        $response_text = $completions_response['choices'][0]['text'];
        $response_text = ucfirst(trim(str_replace("\nA:", "", $response_text)));
        $reference = $best_posts[0]['paragraph'];
        $link = "You can read the full article at " . get_permalink($best_posts[0]['post_id']) . ".";
        return ['response_text' => $response_text, 'posts' => $best_posts];

        //$response_text . " " . $reference . $link;


    }
    function openai_search_get_complete_engines()
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

    function openai_search_get_embedding_engines()
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
    function openai_search_settings_page_html()
    {
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('OpenAI Search Settings', 'openai-search'); ?>
            </h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('openai_search_options');
                do_settings_sections('openai_search_options');
                ?>
                <h2>
                    <?php esc_html_e('API Settings', 'openai-search'); ?>
                </h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="openai_key">
                                <?php esc_html_e('API Key', 'openai-search'); ?>
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
                    <?php esc_html_e('General Settings', 'openai-search'); ?>
                </h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="suggestion_output">
                                <?php esc_html_e('Suggestion Output', 'openai-search'); ?>
                            </label></th>
                        <td>
                            <select id="suggestion_output" name="suggestion_output">
                                <option value="always" <?php selected(get_option('suggestion_output'), 'always'); ?>><?php
                                   esc_html_e('Always', 'openai-search'); ?></option>
                                <option value="user-defined" <?php selected(get_option('suggestion_output'), 'user-defined'); ?>><?php esc_html_e('User-defined', 'openai-search'); ?></option>
                                <option value="never" <?php selected(get_option('suggestion_output'), 'never'); ?>><?php
                                   esc_html_e('Never', 'openai-search'); ?></option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Output suggestion even if no articles found, could be hallucinations effect', 'openai-search'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="suppose_phrase">
                                <?php esc_html_e('Suppose phrase', 'openai-search'); ?>
                            </label></th>
                        <td><input type="text" id="suppose_phrase" name="suppose_phrase"
                                value="<?php echo esc_attr(get_option('suppose_phrase')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="results_limit">
                                <?php esc_html_e('Results Limit', 'openai-search'); ?>
                            </label></th>
                        <td><input type="number" id="results_limit" name="results_limit"
                                value="<?php echo esc_attr(get_option('results_limit', 5)); ?>" min="1" max="10" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="paragraph_limit">
                                <?php esc_html_e('Paragraph Characters Limit', 'openai-search'); ?>
                            </label></th>
                        <td><input type="number" id="paragraph_limit" name="paragraph_limit"
                                value="<?php echo esc_attr(get_option('paragraph_limit', 400)); ?>" min="50" max="1000" /></td>
                    </tr>
                </table>
                <h2>
                    <?php esc_html_e('OpenAI Settings', 'openai-search'); ?>
                </h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="embedding_engine">
                                <?php esc_html_e('Embedding Engine', 'openai-search'); ?>
                            </label></th>
                        <td>
                            <select id="embedding_engine" name="embedding_engine">
                                <?php foreach (openai_search_get_embedding_engines() as $engine): ?>
                                    <option value="<?php echo esc_attr($engine); ?>" <?php selected(get_option('embedding_engine', 'text-embedding-ada-002'), $engine); ?>><?php echo esc_html($engine); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="complete_engine">
                                <?php esc_html_e('Completion Engine', 'openai-search'); ?>
                            </label></th>
                        <td>
                            <select id="complete_engine" name="complete_engine">
                                <?php foreach (openai_search_get_complete_engines() as $engine): ?>
                                    <option value="<?php echo esc_attr($engine); ?>" <?php selected(get_option('complete_engine', 'text-davinci-003'), $engine); ?>><?php echo esc_html($engine); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><a href="https://beta.openai.com/docs/models/overview" target="_blank">
                                    <?php esc_html_e('Read more about engines', 'openai-search'); ?>
                                </a></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="max_tokens">
                                <?php esc_html_e('Max Tokens', 'openai-search'); ?>
                            </label></th>
                        <td><input type="number" id="max_tokens" name="max_tokens"
                                value="<?php echo esc_attr(get_option('max_tokens', 50)); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="temperature">
                                <?php esc_html_e('Temperature', 'openai-search'); ?>
                            </label></th>
                        <td><input type="number" id="temperature" name="temperature"
                                value="<?php echo esc_attr(get_option('temperature', 0.5)); ?>" step="0.01" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="top_p">
                                <?php esc_html_e('Top P', 'openai-search'); ?>
                            </label></th>
                        <td><input type="number" id="top_p" name="top_p" value="<?php echo esc_attr(get_option('top_p', 1)); ?>"
                                step="0.01" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stop">
                                <?php esc_html_e('Stop', 'openai-search'); ?>
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

    function openai_search_register_settings()
    {
        register_setting('openai_search_options', 'openai_key');
        register_setting('openai_search_options', 'suggestion_output');
        register_setting(
            'openai_search_options',
            'suppose_phrase',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => esc_attr('I suppose'),
            )
        );
        register_setting(
            'openai_search_options',
            'results_limit',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 5,
            )
        );
        register_setting(
            'openai_search_options',
            'paragraph_limit',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 400,
            )
        );
        register_setting('openai_search_options', 'embedding_engine');
        register_setting('openai_search_options', 'complete_engine');
        register_setting(
            'openai_search_options',
            'max_tokens',
            array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 50,
            )
        );
        register_setting(
            'openai_search_options',
            'temperature',
            array(
                'type' => 'number',
                'sanitize_callback' => 'floatval',
                'default' => 0.5,
            )
        );
        register_setting(
            'openai_search_options',
            'top_p',
            array(
                'type' => 'number',
                'sanitize_callback' => 'floatval',
                'default' => 1,
            )
        );
        register_setting('openai_search_options', 'stop');
    }


    function openai_search_settings_page()
    {
        add_options_page(
            __('OpenAI Search', 'openai-search'),
            __('OpenAI Search', 'openai-search'),
            'manage_options',
            'openai-search',
            'openai_search_settings_page_html'
        );
    }

    add_action('admin_menu', 'openai_search_settings_page');
    add_action('admin_init', 'openai_search_register_settings');

    function openai_search_settings_validate($input)
    {
        if (empty(trim($input['openai_key']))) {
            add_settings_error('openai_search_options', 'openai_key', __('Please enter your OpenAI API key.', 'openai-search'), 'error');
        }
        return $input;
    }
    add_filter('pre_update_option_openai_search_options', 'openai_search_validate_settings');


    function openai_search_form()
    {
        include openai_search_load_template('openai-search-form');
        ?>
        <link rel="stylesheet" type="text/css" href="<?php echo plugin_dir_url(__FILE__) . 'openai-search.css'; ?>" />
    <?php
    }

    // Define shortcode to display search form and results
    function openai_search_shortcode()
    {
        ob_start();
        ?><div class="openai-search">
            <?php
            openai_search_form();
            openai_search_handle_query();
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
    // Add shortcode and search query handler to WordPress hooks
    add_shortcode('openai_search', 'openai_search_shortcode');

    // Define shortcode to display search form on a page or post
    function openai_search_form_shortcode()
    {
        ob_start();
        ?>
        <div class="openai-search form">
            <?php
            openai_search_form();
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
    // Add shortcode and search query handler to WordPress hooks
    add_shortcode('openai_search_form', 'openai_search_form_shortcode');
    // Define shortcode to display search form on a page or post
    function openai_search_results_shortcode()
    {
        ob_start();
        ?>
        <div class="openai-search results">
            <?php
            openai_search_handle_query();
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
    // Add shortcode and search query handler to WordPress hooks
    add_shortcode('openai_search_results', 'openai_search_results_shortcode');

    // Define function to handle search query
    function openai_search_handle_query()
    {
        if (isset($_GET['q'])) {
            $query = sanitize_text_field($_GET['q']);
            $cache_key = 'openai_search_results_' . md5($query); // Use a unique cache key based on the query

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
                $result = openai_search($query, $embedding_engine, $complete_engine, $max_tokens, $temperature, $top_p, $stop);
                set_transient($cache_key, $result, 60 * MINUTE_IN_SECONDS); // Cache for 5 minutes
            }
            // Load the template openai-search-results
            include openai_search_load_template('openai-search-results');
        }
    }



    function create_aisearch_page()
    {
        // Check if the "aisearch" page exists
        $aisearch_page = get_page_by_title('aisearch');
        if (!$aisearch_page) {
            // Create the "aisearch" page and add the [aisearch] shortcode
            $page_id = wp_insert_post(
                array(
                    'post_title' => 'AI Search',
                    'post_content' => '[openai_search]',
                    'post_status' => 'publish',
                    'post_type' => 'page'
                )
            );
            // Add a permalink to the "aisearch" page
            update_post_meta($page_id, '_wp_page_template', 'page.php');
        }
    }
    register_activation_hook(__FILE__, 'create_aisearch_page');

    function openai_search_activate()
    {
        if (!get_option('openai_key')) {
            add_action('admin_notices', 'openai_search_activation_notice');
        }
    }

    function openai_search_activation_notice()
    {
        ?>
        <div class="error">
            <p>
                <?php printf(__('Please <a href="%s">enter your OpenAI API key</a> to use the OpenAI Search plugin.', 'openai_search'), esc_url(admin_url('options-general.php?page=openai-search'))); ?>
            </p>
        </div>
    <?php
    }
    register_activation_hook(__FILE__, 'openai_search_activate');



    add_filter('plugin_row_meta', 'openai_search_add_plugin_row_meta', 10, 2);

    function openai_search_add_plugin_row_meta($links, $file)
    {
        if (plugin_basename(__FILE__) === $file) {
            $links[] = '<a href="' . admin_url('admin.php?page=openai-search') . '">' . esc_html__('Settings', 'openai-search') . '</a>';
            $links[] = '<a href="' . home_url('/aisearch') . '">' . esc_html__('AI Search page', 'openai-search') . '</a>';
        }
        return $links;
    }
    // Define function to handle post updates and generate embeddings
    function openai_search_update_post($post_id, $post, $update)
    {
        if ($post->post_status == 'publish') {
            generate_embeddings_for_post($post_id, get_option('embedding_engine', 'text-davinci-002'));
        }
    }

    // Add hook to generate embeddings when a post is updated or published
    add_action('save_post', 'openai_search_update_post', 10, 3);
}
openai_search_init();