<?php
/**
 * Attachment template.
 *
 * READ THIS BEFORE EDITING - THIS FILE DOES NOT RUN TODAY EITHER, AND FOR A
 * COMPLETELY DIFFERENT REASON THAN author.php.
 *
 * This install is WordPress 7.0.2 and `wp_options.wp_attachment_pages_enabled`
 * is `'0'`. Since WordPress 6.4 that option makes core redirect every
 * attachment permalink straight to the file, so no attachment template of any
 * theme is ever included. Measured, not inferred:
 *
 *   GET /piecyfer/why-ai-native-companies-.../website-articles-cover-8/
 *     -> 301 http://localhost/piecyfer/wp-content/uploads/2026/08/website-articles-cover-8.png
 *
 * There is consequently no attachment page in the pixel harness and there can
 * not be one: the URL never renders HTML. Nothing to compare against, and
 * nothing a theme swap can change - unless somebody sets that option back to
 * `1`, which is the only circumstance in which anything below executes.
 *
 * Were it re-enabled, an attachment is `is_singular()`, so Elementor maps it to
 * the `single` location. `single` has no `overwrite` flag, and neither Theme
 * Builder single document matches an attachment (8502 is `singular/post`, 8716
 * is `not_found404`), so `get_documents_for_location()` comes back empty,
 * `template_include` returns early and this file really would render. Hence the
 * `piecyfer_do_location( 'single' )` call: if a future template ever does claim
 * attachments, it wins, and the markup below is the fallback.
 *
 * The previous theme's version is reproduced minus its two dead calls -
 * `get_template_part( 'templates/share' )`, a file that never existed, and the
 * `sidebar` part, which returned nothing whenever Elementor Pro was active.
 *
 * @package piecyfer-theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<?php if ( have_posts() ) : the_post(); ?>
	<div class="page-wrapper">

		<article id="post-<?php the_ID(); ?>" <?php post_class( 'full' ); ?>>
		<?php if ( ! piecyfer_do_location( 'single' ) ) : ?>
			<div class="page-content clearfix">

				<div class="entry-attachment">
					<?php if ( wp_attachment_is_image() ) : ?>
						<p class="attachment">
							<a href="<?php echo esc_url( piecyfer_next_attachment_url() ); ?>" title="<?php the_title_attribute(); ?>" rel="attachment" class="thumbnail">
								<?php
								$piecyfer_attachment_size = (int) apply_filters( 'piecyfer_attachment_size', 900 );

								echo wp_get_attachment_image( get_the_ID(), array( $piecyfer_attachment_size, 9999 ) );
								?>
							</a>
						</p>

						<div id="nav-below" class="navigation">
							<div class="nav-previous"><?php previous_image_link( false ); ?></div>
							<div class="nav-next"><?php next_image_link( false ); ?></div>
						</div><!-- #nav-below -->
					<?php else : ?>
						<a href="<?php echo esc_url( wp_get_attachment_url() ); ?>" title="<?php the_title_attribute(); ?>" rel="attachment"><?php the_title(); ?></a>
					<?php endif; ?>
				</div><!-- .entry-attachment -->

				<div class="entry-caption">
					<?php
					if ( has_excerpt() ) {
						the_excerpt();
					}

					if ( wp_attachment_is_image() ) {
						$piecyfer_metadata = wp_get_attachment_metadata();

						if ( ! empty( $piecyfer_metadata['width'] ) && ! empty( $piecyfer_metadata['height'] ) ) {
							printf(
								/* translators: %s: a link reading "<width> x <height>". */
								esc_html__( 'Original size is %s pixels', 'piecyfer-theme' ),
								sprintf(
									'<a href="%1$s" title="%2$s">%3$s &times; %4$s</a>',
									esc_url( wp_get_attachment_url() ),
									esc_attr__( 'Link to full-size image', 'piecyfer-theme' ),
									(int) $piecyfer_metadata['width'],
									(int) $piecyfer_metadata['height']
								)
							);
						}
					}
					?>
				</div>

				<?php the_content(); ?>
			</div>
		<?php endif; ?>
		</article>

	</div>
<?php endif ?>

<?php get_footer(); ?>
