
<?php
/**
 * Template file for displaying BrainySearch form.
 *
 * This template file is loaded by the `brainy_search_handle_query()` function.
 *
 * @package brainy-search
 */
?>

<form role="search" method="get" class="wp-block-search__button-outside wp-block-search__icon-button wp-block-search" action="<?php echo esc_url(home_url('/aisearch')) ?>">
    <label for="search-input" class="screen-reader-text"><?php echo _x('Search for:', 'label') ?></label>
    <div class="wp-block-search__inside-wrapper">
        <input type="search" id="search-input" class="wp-block-search__input" placeholder="<?php echo esc_attr_x('What do you think about … ?', 'placeholder') ?>" value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES) ?>" name="q" />
        <button type="submit" class="wp-block-search__button has-icon wp-element-button"><?php echo _x('Ask', 'submit button') ?></button>
    </div>

    <script>
        var form = document.querySelector('.wp-block-search__button-outside');
        var button = form.querySelector('.wp-block-search__button');
        button.addEventListener('click', function() {
            // Add spinner element to the button
            button.innerHTML = '<span class="spin dashicons dashicons-update"></span>';


            // Disable the button to prevent multiple submissions
            form.setAttribute('disabled', true);

            // Submit the form
            this.closest('form').submit();
        });
    </script>
</form>

