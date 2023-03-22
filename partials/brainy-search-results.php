<?php
/**
 * Template file for displaying BrainySearch results.
 *
 * This template file is loaded by the `brainy_search_handle_query()` function.
 *
 */
?>

<blockquote class="wp-block-quote">
    <p><?php echo esc_html($result['response_text']); ?></p>
</blockquote>

<div class="wp-block-spacer" style="height: 20px;"></div>

<div class="wp-block-columns">
    <div class="wp-block-column" style="flex-basis:66.66%">
        <?php foreach ($result['posts'] as $post) : ?>
            <h2><?php echo esc_html($post['post']->post_title); ?></h2>
            <p><?php echo wp_kses_post(wp_trim_words($post['paragraph'], 40, '...')); ?>
                <a href="<?php echo esc_url(get_permalink($post['post_id'])); ?>">Read more</a>
                <sup>&deg; <?php echo esc_html(round($post['score'] * 100)); ?></sup>
            </p>
            <hr>
        <?php endforeach; ?>
    </div>
</div>
