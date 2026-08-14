<?php
/**
 * Single post template.
 *
 * Theme Builder document 8502 is conditioned on `singular/post`, so
 * `piecyfer_location_has_template( 'single' )` is true for every post: no
 * `.page-wrapper` is emitted and the whole of `#main` is the Theme Builder
 * document inside `<article class="single-post-wrapper full ...">`.
 *
 * TWO ARTICLES ARE CORRECT HERE. The captured markup of every single post
 * contains the `<article class="single-post-wrapper ...">` twice, the second
 * one empty. Something inside the Theme Builder document - it runs its own
 * secondary loops for the related-posts widget - leaves the main query in a
 * state where `have_posts()` yields one more iteration. On that iteration
 * Elementor's `Locations_Manager` refuses to print the `single` location a
 * second time, so the article opens and closes with nothing between. The
 * `while` loop is reproduced from tecnologia/single.php rather than collapsed
 * to a single `the_post()` precisely so that this reproduces too; collapsing it
 * would delete an element from 16 captured pages. It is a wart, it is
 * invisible, and it should be removed in its own commit with its own capture.
 *
 * The fallback branch replaces `get_template_part( 'templates/post' )` - 17
 * files of the previous theme's own blog partials, none of which is carried -
 * with `the_content()`. It cannot execute while document 8502 exists.
 *
 * `$piecyfer_article_class` is assigned on its own line on purpose. PHP eats
 * the newline after each `?>`, so that line's three leading tabs land on the
 * same output line as the `<article>` that follows it, and the captured markup
 * has eight tabs there. Fold the assignment into the tag and every single post
 * shifts by three characters on three lines - invisible in a browser, a diff on
 * 16 pages in the harness.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

piecyfer_print_pending_styles();

?>

<?php
if ( have_posts() ) :
	while ( have_posts() ) : the_post(); ?>
		<?php if ( ! piecyfer_location_has_template( 'single' ) ) : ?>
			<div class="page-wrapper">
		<?php endif; ?>
			<?php $piecyfer_article_class = 'single-post-wrapper full'; ?>
			<article <?php post_class( $piecyfer_article_class ); ?>>
				<?php if ( ! piecyfer_do_location( 'single' ) ) : ?>
					<div class="page-content loop-wrapper clearfix full clearfix">
						<?php the_content(); ?>

						<?php comments_template(); ?>
					</div>
				<?php endif; ?>
			</article>

		<?php if ( ! piecyfer_location_has_template( 'single' ) ) : ?>
				</div> <!-- End of .page-wrapper -->
		<?php endif; ?>

	<?php endwhile;
endif;

get_footer();
