<?php
/**
 * Template file for displaying OpenAI search results.
 *
 * This template file is loaded by the `openai_search_handle_query()` function.
 *
 * @package your-theme
 */
?>

<blockquote class="wp-block-quote">
    <p><?php echo $result['response_text']; ?></p>
</blockquote>

<div class="wp-block-spacer" style="height: 20px;"></div>

<div class="wp-block-columns">
    <div class="wp-block-column" style="flex-basis:66.66%">
        <?php foreach ($result['posts'] as $post) : ?>
            <h2><?php echo $post['post']->post_title; ?></h2>
            <p><?php echo wp_trim_words($post['paragraph'], 40, '...'); ?>
                <a href="<?php echo get_permalink($post['post_id']); ?>">Read more</a>
                <sup>&deg; <?php echo round($post['score'] * 100); ?></sup>
            </p>
            <hr>
        <?php endforeach; ?>
    </div>
</div>