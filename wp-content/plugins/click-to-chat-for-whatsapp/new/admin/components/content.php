<?php
/**
 * content template
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$title = (isset($input['title'])) ? $input['title'] : '';
$parent_class = (isset($input['parent_class'])) ? $input['parent_class'] : '';
$description = (isset($input['description'])) ? $input['description'] : '';

?>

<div class="row ctc_component_content <?= $parent_class ?>">
    <?php

    // title
    if ('' !== $title) {
        ?>
        <p class="description ht_ctc_subtitle"><?php _e( $title, 'click-to-chat-for-whatsapp' ); ?></p>
        <?php
    }

    // description
    if (isset($input['description'])) {
        ?>
        <p class="description"><?php _e( $description, 'click-to-chat-for-whatsapp' ); ?></p>
        <?php
    }

    ?>
</div>