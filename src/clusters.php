<?php
// $clusters = clusterPostEmbeddings($embeddings, $num_clusters);
// [post_clusters num_clusters="3"]

function displayPostClusters($atts)
{
    // Generate embeddings for all posts in the database
    $embeddings = loadPostEmbeddings(get_option('embedding_engine'));

    // Cluster the post embeddings
    $num_clusters = isset($atts['num_clusters']) ? $atts['num_clusters'] : 3;
    $clusters = clusterPostEmbeddings($embeddings, $num_clusters);

    // Render the clusters as HTML
    $html = '';
    foreach ($clusters as $label => $posts) {
        $html .= '<h2>Cluster ' . ($label + 1) . '</h2>';
        $completions_request_data = array(
            'model' => get_option('complete_engine', 'text-davinci-002'),
            'max_tokens' => 200,
            'temperature' => 0.5,
            'prompt' => "Compose a unique header what can sum up the following\n" .
            implode("\n", array_map(function ($post) {
                return $post->post_title;
            }, $posts))
        );
        $completions_response = openai_api_call('completions', $completions_request_data);
        $html .= '<h3>' . $completions_response['choices'][0]['text'] . '<h3>';

        $html .= '<ul>';
        foreach ($posts as $post) {
            $html .= '<li><a href="' . get_permalink($post) . '">' . $post->post_title . '</a></li>';
        }
        $html .= '</ul>';
    }

    // Return the HTML
    return $html;
}
add_shortcode('post_clusters', 'displayPostClusters');

// Define a function to cluster the post embeddings
function clusterPostEmbeddings($embeddings, $num_clusters)
{
    // Initialize the centroids randomly
    $post_ids = array_keys($embeddings);
    $num_embeddings = count($embeddings);
    $centroids = array();
    for ($i = 0; $i < $num_clusters; $i++) {
        $index = rand(0, $num_embeddings - 1);
        $post_id = $post_ids[$index];
        $centroids[] = $embeddings[$post_id];
    }

    // Run the KMeans clustering algorithm
    $labels = array_fill(0, $num_embeddings, -1);
    $iterations = 0;
    while ($iterations < 10) {
        $new_labels = array();
        for ($i = 0; $i < $num_embeddings; $i++) {
            $min_distance = INF;
            $min_index = -1;
            for ($j = 0; $j < $num_clusters; $j++) {
                $distance = euclideanDistance($embeddings[$post_ids[$i]], $centroids[$j]);
                if ($distance < $min_distance) {
                    $min_distance = $distance;
                    $min_index = $j;
                }
            }
            $new_labels[$i] = $min_index;
        }
        if ($labels === $new_labels) {
            break;
        }
        $labels = $new_labels;
        for ($j = 0; $j < $num_clusters; $j++) {
            $sum = array_fill(0, count($embeddings[$post_ids[0]]), 0);
            $count = 0;
            for ($i = 0; $i < $num_embeddings; $i++) {
                if ($labels[$i] === $j) {
                    $sum = addVectors($sum, $embeddings[$post_ids[$i]]);
                    $count++;
                }
            }
            if ($count > 0) {
                $centroids[$j] = scalarMultiplyVector(1 / $count, $sum);
            }
        }
        $iterations++;
    }

    // Assign each post to a cluster based on its label
    $clusters = array();
    foreach ($embeddings as $post_id => $embedding) {
        $index = array_search($post_id, $post_ids);
        $label = $labels[$index];
        if (!isset($clusters[$label])) {
            $clusters[$label] = array();
        }
        $clusters[$label][] = get_post($post_id);
    }

    // Return the clusters array
    return $clusters;
}


// Define a function to add two vectors component-wise
function addVectors($v1, $v2)
{
    $result = array();
    $n = count($v1);
    for ($i = 0; $i < $n; $i++) {
        $result[] = $v1[$i] + $v2[$i];
    }
    return $result;
}

// Define a function to multiply a vector by a scalar
function scalarMultiplyVector($c, $v)
{
    $result = array();
    $n = count($v);
    for ($i = 0; $i < $n; $i++) {
        $result[] = $c * $v[$i];
    }
    return $result;
}

// Define a function to compute the Euclidean distance between two vectors
function euclideanDistance($v1, $v2)
{
    $n = count($v1);
    $sum = 0;
    for ($i = 0; $i < $n; $i++) {
        $sum += pow($v1[$i] - $v2[$i], 2);
    }
    return sqrt($sum);
}

// Define a function to load the post embeddings
function loadPostEmbeddings($embedding_engine)
{
    // Get the embeddings for all posts
    $post_embeddings = get_embeddings_for_posts($embedding_engine);


    $count = 0;
    foreach ($post_embeddings as $post_id => $paragraphs) {
        // print_r($paragraphs); exit();
        if ($paragraphs[0] && count($paragraphs[0]) > 0) {
            continue;
        }
        $count++;
    }
    // echo $count; exit();
    // Combine the embeddings for each post into a single embedding
    $embeddings = array();
    foreach ($post_embeddings as $post_id => $paragraphs) {
        $post_embedding = array();


        foreach ($paragraphs as $embedding) {
            if (!$embedding) {
                // echo $post_id. ' '; 
                continue;
            }
            $post_embedding = array_merge($post_embedding, $embedding);

        }
        if (count($post_embedding) === 0) {
            continue;
        }


        $embeddings[$post_id] = $post_embedding;
    }

    // Return the embeddings array
    return ($embeddings);
}