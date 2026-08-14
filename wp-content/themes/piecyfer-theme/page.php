<?php
/**
 * Single page template.
 *
 * This template really does run: pages match no Theme Builder `single`
 * document (8502 is conditioned on `singular/post`, 8716 on `not_found404`),
 * so `piecyfer_location_has_template( 'single' )` is false for every page and
 * the theme emits `.page-wrapper > article > .page-content` around Elementor's
 * `the_content()` output. That is the markup on 23 of the 39 captured pages.
 *
 * The indentation is not cosmetic. PHP swallows exactly one newline after each
 * `?>`, so the leading tabs of the following line concatenate onto the previous
 * output line - which is why the captured `</div>` that closes `.page-content`
 * carries nine tabs and no source line has nine tabs. Moving a `<?php` tag onto
 * a different line changes the rendered whitespace. It is reproduced from
 * tecnologia/page.php line for line.
 *
 * Two lines of the original are gone and neither changes a rendered byte:
 * `VamtamTemplates::$in_page_wrapper = true` (a flag read only by the sidebar
 * partial, which never renders) and `get_template_part( 'templates/share' )`
 * (a file that does not exist in the previous theme either). The share call
 * emitted four tabs of pure whitespace, so its indentation is preserved by
 * splitting `the_content()` and `wp_link_pages()` into two PHP blocks - the
 * same two whitespace emissions, from code that does something.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<?php if ( have_posts() ) : the_post(); ?>

	<?php if ( ! piecyfer_location_has_template( 'single' ) ) : ?>
			<div class="page-wrapper">
	<?php endif; ?>

		<article id="post-<?php the_ID(); ?>" <?php post_class( 'full' ); ?>>
		<?php if ( ! piecyfer_do_location( 'single' ) ) : ?>
			<div class="page-content clearfix the-content-parent">
				<?php the_content(); ?>
				<?php
					wp_link_pages(
						array(
							'before' => '<nav class="navigation post-pagination" role="navigation"><span class="screen-reader-text">' . esc_html__( 'Pages:', 'piecyfer-theme' ) . '</span>',
							'after'  => '</nav>',
						)
					);
				?>
			</div>
		<?php endif; ?>
			<?php comments_template( '', true ); ?>
		</article>

	<?php if ( ! piecyfer_location_has_template( 'single' ) ) : ?>
			</div> <!-- End of .page-wrapper -->
	<?php endif; ?>


<?php endif;

get_footer();
