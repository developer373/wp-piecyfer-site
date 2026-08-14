<?php
/**
 * Comments template.
 *
 * THIS FILE IS LIVE ON EVERY SINGLE POST, which is not what
 * `05-THEME-SPEC.md` §1.3 says ("Replaced by the `post-comments` widget on the
 * TB single template"). The widget does not replace it - it *calls* it.
 * Document 8502 carries `post-comments.theme_comments`, and Elementor Pro's
 * "Theme Comments" skin renders by invoking `comments_template()`, which loads
 * this file. It is why the captured single posts contain
 * `.clearboth > #comments.comments-wrapper > .respond-box > #respond`, markup
 * that exists nowhere in Elementor.
 *
 * So the indentation here is load-bearing in the same way single.php's is, and
 * for the same reason: the three tabs in front of the `comment_form()` block
 * land on the `<div id="respond">` line. There is deliberately no blank line
 * between the `endif` and that block.
 *
 * Reached from `page.php` too, where the guard below returns immediately -
 * comments are closed on every page and none has a comment history.
 *
 * Three things from the previous theme's version are not carried, and all three
 * are invisible today because no post on this site has a single comment
 * (`have_comments()` is false everywhere, so only the respond box renders):
 * the trackback/ping counting block, the `<h3>What do you think?</h3>` heading
 * (which was already suppressed whenever Elementor was active, i.e. always),
 * and the `VamtamTemplates::comments` walker callback, replaced here by
 * WordPress's default. The first post to receive a comment will render a
 * different comment list than the old theme would have. That is a real
 * difference; it is just not one anything can observe right now.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}

if ( ! comments_open() && ! have_comments() ) {
	return;
}

if ( ! post_type_supports( get_post_type(), 'comments' ) ) {
	return;
}
?>
<div class="clearboth">
	<div id="comments" class="comments-wrapper">
		<?php if ( have_comments() ) : ?>
			<div class="sep-text centered keep-always">
				<div class="content">
					<?php
					comments_number(
						esc_html__( '0 Comments:', 'piecyfer-theme' ),
						esc_html__( '1 Comment', 'piecyfer-theme' ),
						esc_html__( '% Comments:', 'piecyfer-theme' )
					);
					?>
				</div>
			</div>

			<div id="comments-list" class="comments">
				<?php
				wp_list_comments(
					array(
						'type'  => 'comment',
						'style' => 'div',
					)
				);
				?>
			</div>
		<?php endif ?>

		<?php
		the_comments_pagination(
			array(
				'prev_text' => '<span class="screen-reader-text">' . esc_html__( 'Previous', 'piecyfer-theme' ) . '</span>',
				'next_text' => '<span class="screen-reader-text">' . esc_html__( 'Next', 'piecyfer-theme' ) . '</span>',
			)
		);
		?>

		<div class="respond-box">
			<?php if ( ! comments_open() && get_comments_number() ) : ?>
				<p class="no-comments"><?php esc_html_e( 'Comments are closed.', 'piecyfer-theme' ); ?></p>
			<?php endif; ?>
			<?php
			comment_form(
				array(
					'title_reply_before' => '<h5 id="reply-title" class="comment-reply-title">',
					'title_reply_after'  => '</h5>',
				)
			);
			?>
		</div><!-- .respond-box -->
	</div><!-- #comments -->
</div>

