
<?php
/**
 * Template file for displaying OpenAI search form.
 *
 * This template file is loaded by the `openai_search_handle_query()` function.
 *
 * @package openai-search
 */
?>

<form role="search" method="get" class="wp-block-search__button-outside wp-block-search__icon-button wp-block-search" action="<?php echo esc_url(home_url('/aisearch')) ?>">
    <label for="search-input" class="screen-reader-text"><?php echo _x('Search for:', 'label') ?></label>
    <div class="wp-block-search__inside-wrapper">
        <input type="search" id="search-input" class="wp-block-search__input" placeholder="<?php echo esc_attr_x('What do you think about … ?', 'placeholder') ?>" value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES) ?>" name="q" />
        <button type="submit" class="wp-block-search__button has-icon wp-element-button"><?php echo _x('Ask', 'submit button') ?></button>
    </div>
</form>

